const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../../client/src/js/masha-feedly-effects.js'), 'utf8');
const definition = (id = 'ducks', categories = ['playful'], weight = 1) => ({ id, name: id, categories, weight, files: { js: `/${id}.js`, css: `/${id}.css` } });
const categoryList = [{ id: 'playful', name: 'Verspielt' }, { id: 'serious', name: 'Sachlich' }, { id: 'winterzauber', name: 'Winterzauber' }];

/** Simuliert echte Datei-Ladeereignisse getrennt für Dokument und Shadow Root. */
function environment(effects = [definition()], options = {}) {
  let now = 1000;
  let requests = 0;
  let failAsset = options.failAsset;
  const appended = [];
  const played = [];
  const timers = new Map();
  const listeners = {};
  const window = {
    location: { href: 'https://site.test/' },
    KWMashaFeedlyEffectsManifestURL: 'https://effects.test/__masha-effects/manifest',
    KWMashaFeedlyEffectModules: {},
    matchMedia: () => ({ matches: !!options.reduced }),
    setTimeout: (callback) => { const key = {}; timers.set(key, callback); return key; },
    clearTimeout: (key) => timers.delete(key),
    Image: class {},
    fetch: async (url, init) => {
      requests++;
      assert.equal(init.credentials, 'same-origin');
      assert.equal(init.cache, 'no-cache');
      if (options.fetch) return options.fetch();
      if (options.failManifest) throw new Error('offline');
      return { ok: true, json: async () => ({ version: 2, maxAge: 300, categories: categoryList, effects }) };
    },
  };
  const append = (element, root) => {
    appended.push({ element, root });
    queueMicrotask(() => {
      if (options.keepPending) return;
      if (failAsset) { failAsset = false; element.events.error(); return; }
      if (element.tag === 'script') {
        const id = new URL(element.src).pathname.split('/').pop().split('.')[0];
        const version = element.src;
        window.KWMashaFeedlyEffectModules[id] = { play: () => {
          if (options.failPlay) throw new Error('Animation fehlgeschlagen');
          const layer = { removed: false, remove() { this.removed = true; } };
          played.push({ id, version, layer });
          return { layer };
        } };
      }
      element.events.load?.();
    });
  };
  const shadow = { append: (element) => append(element, 'shadow') };
  const document = {
    head: { append: (element) => append(element, 'head') },
    addEventListener: (name, callback) => { listeners[name] = callback; },
    createElement: (tag) => ({ tag, events: {}, dataset: {}, style: {}, setAttribute() {}, animate() { const animation = { cancelled: false, cancel() { this.cancelled = true; } }; this.animation = animation; return animation; }, addEventListener(name, callback) { this.events[name] = callback; }, remove() { this.removed = true; } }),
  };
  window.KWMashaFeedlyDOM = { root: () => shadow, widget: () => null };
  vm.runInNewContext(source, { window, document, URL, AbortSignal, Date: class extends Date { static now() { return now; } }, console: { warn() {} } });
  return { api: window.KWMashaFeedlyEffects, window, document, appended, played, listeners, timers, requests: () => requests, expire: () => { now += 301000; } };
}

test('führt parallele Manifest-Anfragen zusammen und aktualisiert nach fünf Minuten', async () => {
  const env = environment();
  await Promise.all([env.api.refresh(), env.api.refresh(), env.api.refresh()]);
  assert.equal(env.requests(), 1);
  await env.api.refresh(); assert.equal(env.requests(), 1);
  env.expire(); await env.api.refresh(); assert.equal(env.requests(), 2);
});

test('liefert dynamische Kategorien aus dem Anbieter-Katalog', async () => {
  const env = environment([definition('snow', ['winterzauber'])]);
  const catalogue = await env.api.refreshCatalogue();
  assert.deepEqual(catalogue.categories.map(category => category.id), ['playful', 'serious', 'winterzauber']);
  assert.deepEqual(catalogue.effects[0].categories, ['winterzauber']);
});

test('reicht die sichere Diagnose des lokalen Anbieter-Proxys an die CMS-Vorschau weiter', async () => {
  const env = environment([], { fetch: async () => ({ ok: false, json: async () => ({ message: 'Der Effekt-Anbieter lehnt den API-Schlüssel ab.' }) }) });
  await assert.rejects(env.api.refreshCatalogue(), /lehnt den API-Schlüssel ab/);
});

test('wählt nach Anbieter-Kategorie und berücksichtigt das CMS-Gewicht', async () => {
  const env = environment([definition('ducks', ['playful'], 1), definition('check', ['serious'], 1), definition('common', ['playful', 'serious'], 3)]);
  await env.api.playOnDone(env.document, env.window, '', 'serious', () => 0);
  await env.api.playOnDone(env.document, env.window, '', 'playful', () => 0.5);
  assert.deepEqual(env.played.map(item => item.id), ['check', 'common']);
});

test('lädt CSS im Shadow Root und JS nur einmal pro Version im Dokument', async () => {
  const env = environment();
  await env.api.preload(env.document, 'playful');
  await env.api.preview('ducks', env.document);
  await env.api.preview('ducks', env.document);
  assert.deepEqual(env.appended.map(({ element, root }) => [element.tag, root]), [['link', 'shadow'], ['script', 'head']]);
  assert.equal(env.played.length, 2);
  assert.equal(env.listeners.pointerdown(), 2);
  assert.ok(env.played.every(item => item.layer.removed));
});

test('spielt nach einem Abbruch auch eine noch wartende Manifest-Anfrage nicht ab', async () => {
  let resolve;
  const pending = new Promise(done => { resolve = done; });
  const env = environment([], { fetch: () => pending });
  const playback = env.api.preview('ducks', env.document);
  await new Promise(setImmediate);
  env.api.cancelActive();
  resolve({ ok: true, json: async () => ({ version: 2, categories: categoryList, effects: [definition()] }) });
  assert.equal(await playback, null);
  assert.equal(env.played.length, 0);
});

test('Anbieter- und Datei-Fehler bleiben abgefangen; fehlgeschlagene Dateien können erneut laden', async () => {
  const offline = environment([], { failManifest: true });
  assert.ok((await offline.api.playOnDone(offline.document)).layer);
  const retry = environment([definition()], { failAsset: true });
  assert.equal(await retry.api.preview('ducks', retry.document), null);
  assert.ok(await retry.api.preview('ducks', retry.document));
  assert.equal(retry.played.length, 1);
});

test('reduzierte Bewegung verhindert sowohl Downloads als auch Wiedergabe', async () => {
  const env = environment([definition()], { reduced: true });
  await env.api.preload(env.document, 'playful');
  assert.equal(await env.api.preview('ducks', env.document), null);
  assert.equal(env.requests(), 0); assert.equal(env.appended.length, 0);
});

test('lokale Standardvorschau läuft ohne Katalog und verwendet dieselbe aufräumbare Häkchenanimation', async () => {
  const env = environment([], { failManifest: true });
  const result = await env.api.previewFallback(env.document);
  assert.equal(env.requests(), 0);
  assert.equal(result.layer.textContent, '✓');
  assert.equal(env.appended.length, 1);
  assert.equal(env.api.cancelActive(), 1);
  assert.equal(result.layer.removed, true);
  assert.equal(result.layer.animation.cancelled, true);
});

test('leere saisonale Freigabe nutzt den lokalen Abschluss und fremde Datei-Hosts werden abgelehnt', async () => {
  const empty = environment([]);
  assert.ok((await empty.api.playOnDone(empty.document)).layer);
  const foreign = definition(); foreign.files.js = 'https://untrusted.test/effect.js';
  const env = environment([foreign]);
  await assert.rejects(env.api.refresh(), /Ungültige Effekt-Datei/);
  assert.equal(await env.api.preview('ducks', env.document), null);
  assert.equal(env.appended.length, 0);
});

test('neue Datei-Versionen überschreiben nicht das zwischengespeicherte alte Modul', async () => {
  const effect = definition(); const env = environment([effect]);
  await env.api.preview('ducks', env.document);
  effect.files.js = '/ducks.v2.js'; env.expire();
  await env.api.preview('ducks', env.document);
  effect.files.js = '/ducks.js'; env.expire();
  await env.api.preview('ducks', env.document);
  assert.deepEqual(env.played.map(item => item.version), ['https://effects.test/ducks.js', 'https://effects.test/ducks.v2.js', 'https://effects.test/ducks.js']);
});

test('ausgelieferter Effekt-Loader entspricht der geprüften Quelle', () => {
  assert.equal(fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly-effects.js'), 'utf8'), source);
});


test('Datei-Timeouts brechen ohne Erfolgsanimation ab und erlauben einen erneuten Versuch', async () => {
  const options = { keepPending: true };
  const env = environment([definition()], options);
  const pending = env.api.preview('ducks', env.document);
  await new Promise(setImmediate);
  assert.equal(env.timers.size, 2);
  [...env.timers.values()].forEach(callback => callback());
  assert.equal(await pending, null);
  assert.equal(env.played.length, 0);
  options.keepPending = false;
  assert.ok(await env.api.preview('ducks', env.document));
});

test('auch ein Fehler innerhalb der Animation wird abgefangen', async () => {
  const env = environment([definition()], { failPlay: true });
  assert.ok((await env.api.playOnDone(env.document)).layer);
  assert.equal(env.api.cancelActive(), 1);
});


test('lokaler Abschluss funktioniert ohne Anbieter, endet automatisch und lässt sich abbrechen', async () => {
  const env = environment();
  delete env.window.KWMashaFeedlyEffectsManifestURL;
  const first = await env.api.playOnDone(env.document);
  assert.equal(env.requests(), 0);
  assert.equal(first.layer.textContent, '✓');
  assert.equal(env.appended[0].root, 'shadow');
  assert.equal(env.api.cancelActive(), 1);
  assert.equal(first.layer.removed, true);
  assert.equal(first.layer.animation.cancelled, true);
  const second = await env.api.playOnDone(env.document);
  [...env.timers.values()].forEach(callback => callback());
  assert.equal(second.layer.removed, true);
  assert.equal(env.api.cancelActive(), 0);
});

test('Anbieter-Dateifehler nutzen den Ersatzeffekt und ein Abbruch verhindert verspäteten Ersatz', async () => {
  const broken = environment([definition()], { failAsset: true });
  assert.ok((await broken.api.playOnDone(broken.document)).layer);
  let reject;
  const env = environment([], { fetch: () => new Promise((resolve, fail) => { reject = fail; }) });
  const playback = env.api.playOnDone(env.document);
  await new Promise(setImmediate);
  env.api.cancelActive();
  reject(new Error('offline'));
  assert.equal(await playback, null);
  assert.equal(env.appended.length, 0);
});

test('reduzierte Bewegung unterdrückt auch den lokalen Ersatzeffekt', async () => {
  const env = environment([], { reduced: true });
  delete env.window.KWMashaFeedlyEffectsManifestURL;
  assert.equal(await env.api.playOnDone(env.document), null);
  assert.equal(env.appended.length, 0);
});
