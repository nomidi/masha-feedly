/** Lädt die vom Anbieter freigegebenen Effekte; Speicherung und Darstellung bleiben voneinander unabhängig. */
(() => {
  /**
   * @typedef {Object} EffectDefinition
   * @property {string} id Kennung der JavaScript-Registrierung.
   * @property {string} name Anzeigename.
   * @property {'playful'|'serious'|'both'} theme Zulässiges Theme.
   * @property {number} weight Gewicht in der zufälligen Auswahl.
   * @property {{js: string, css?: string, image?: string}} files Versionierte öffentliche Dateien.
   */
  window.KWMashaFeedlyEffectModules ||= {};
  let catalog = [];
  let expires = 0;
  let manifestRequest;
  const scripts = new Map();
  const stylesByRoot = new WeakMap();
  const active = new Set();
  let generation = 0;
  const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const rootFor = (document) => window.KWMashaFeedlyDOM?.root() || document;
  const assetURL = (value) => {
    const manifest = new URL(window.KWMashaFeedlyEffectsManifestURL, window.location.href);
    const url = new URL(value, manifest);
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== manifest.origin || url.username || url.password) throw new Error('Ungültige Effekt-Datei.');
    return url.href;
  };

  /** Aktualisiert den Katalog spätestens nach fünf Minuten; parallele Anfragen werden zusammengeführt. */
  const refresh = async () => {
    if (!window.KWMashaFeedlyEffectsManifestURL) return [];
    if (Date.now() < expires) return catalog;
    if (!manifestRequest) {
      manifestRequest = (async () => {
        const response = await window.fetch(window.KWMashaFeedlyEffectsManifestURL, { credentials: 'same-origin', cache: 'no-cache', signal: AbortSignal.timeout(5000) });
        if (!response.ok) throw new Error('Effekt-Anbieter nicht erreichbar.');
        const data = await response.json();
        if (data.version !== 1 || !Array.isArray(data.effects)) throw new Error('Ungültiger Effekt-Katalog.');
        catalog = data.effects.filter((effect) => /^[A-Za-z][A-Za-z0-9_-]{0,79}$/.test(effect.id)
          && ['playful', 'serious', 'both'].includes(effect.theme) && Number.isFinite(effect.weight) && effect.weight > 0
          && effect.files?.js).map((effect) => ({ ...effect, files: Object.fromEntries(Object.entries(effect.files).map(([type, url]) => [type, assetURL(url)])) }));
        expires = Date.now() + Math.min(300, Math.max(1, Number(data.maxAge) || 60)) * 1000;
        return catalog;
      })().finally(() => { manifestRequest = null; });
    }
    return manifestRequest;
  };

  /** Begrenzt auch Datei-Ladevorgänge, damit ein ausgefallener Anbieter keine wartende Animation hinterlässt. */
  const loadElement = (element, parent, timeout = 5000) => new Promise((resolve, reject) => {
    const timer = window.setTimeout(() => { element.remove(); reject(new Error('Effekt-Datei lädt zu lange.')); }, timeout);
    element.addEventListener('load', () => { window.clearTimeout(timer); resolve(element); }, { once: true });
    element.addEventListener('error', () => { window.clearTimeout(timer); element.remove(); reject(new Error('Effekt-Datei konnte nicht geladen werden.')); }, { once: true });
    parent.append(element);
  });

  /** Lädt JavaScript einmal pro Version und Stylesheets getrennt für Dokument und Shadow Root. */
  const load = async (effect, document) => {
    const root = rootFor(document);
    let styles = stylesByRoot.get(root);
    if (!styles) { styles = new Map(); stylesByRoot.set(root, styles); }
    const requests = [];
    if (effect.files.css && !styles.has(effect.files.css)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet'; link.href = effect.files.css; link.crossOrigin = 'anonymous';
      styles.set(effect.files.css, loadElement(link, root === document ? document.head : root).catch((error) => { styles.delete(effect.files.css); throw error; }));
    }
    if (effect.files.css) requests.push(styles.get(effect.files.css));
    if (!scripts.has(effect.files.js)) {
      const script = document.createElement('script');
      script.src = effect.files.js; script.async = true; script.crossOrigin = 'anonymous';
      scripts.set(effect.files.js, loadElement(script, document.head).then(() => {
        const module = window.KWMashaFeedlyEffectModules[effect.id];
        if (!module?.play) throw new Error('Effekt hat sich nicht registriert.');
        return module;
      }).catch((error) => { scripts.delete(effect.files.js); throw error; }));
    }
    requests.push(scripts.get(effect.files.js));
    await Promise.all(requests);
    if (effect.files.image) { const image = new window.Image(); image.src = effect.files.image; }
    return scripts.get(effect.files.js);
  };

  const collectLayers = (value, result = new Set(), visited = new Set()) => {
    if (!value || typeof value !== 'object' || visited.has(value)) return result;
    visited.add(value);
    if (typeof value.remove === 'function') result.add(value);
    else Object.values(value).forEach((item) => collectLayers(item, result, visited));
    return result;
  };
  /** Beendet laufende und noch ladende Effekte beim nächsten Klick. */
  const cancelActive = () => {
    generation += 1;
    const count = active.size;
    active.forEach((record) => { window.clearTimeout(record.timer); record.layers.forEach((layer) => layer.remove()); });
    active.clear();
    return count;
  };
  const start = async (effect, document, token) => {
    const module = await load(effect, document);
    if (token !== generation || reducedMotion()) return null;
    const result = module.play(document, window, effect.files.image || '');
    const record = { layers: collectLayers(result), timer: null };
    active.add(record);
    record.timer = window.setTimeout(() => { record.layers.forEach((layer) => layer.remove()); active.delete(record); }, 15000);
    return result;
  };
  const candidatesFor = (effects, theme) => effects.filter((effect) => effect.theme === theme || effect.theme === 'both');
  /** Zeigt ohne Anbieter einen lokalen, ruhigen Abschluss; benötigt keine externen Dateien. */
  const fallback = (document, token) => {
    if (token !== generation || reducedMotion()) return null;
    const layer = document.createElement('div');
    layer.setAttribute('aria-hidden', 'true');
    layer.dataset.mashaFeedlyFallbackEffect = '';
    layer.textContent = '✓';
    layer.style.cssText = 'position:fixed;inset:0;margin:auto;width:72px;height:72px;display:grid;place-items:center;border-radius:50%;background:#edf7f1;color:#26734d;border:1px solid #b9d9c7;box-shadow:0 8px 28px #183d2426;font:400 40px/1 system-ui,sans-serif;pointer-events:none;z-index:2147483647;';
    const root = window.KWMashaFeedlyDOM?.overlayRoot?.() || rootFor(document);
    (root === document ? document.body : root).append(layer);
    const animation = layer.animate?.([
      { opacity: 0, transform: 'scale(.94)' },
      { opacity: 1, transform: 'scale(1)', offset: 0.18 },
      { opacity: 1, transform: 'scale(1)', offset: 0.7 },
      { opacity: 0, transform: 'scale(1)' },
    ], { duration: 1400, easing: 'ease-out', fill: 'forwards' });
    const record = { layers: new Set([{ remove: () => { animation?.cancel(); layer.remove(); } }]), timer: null };
    active.add(record);
    record.timer = window.setTimeout(() => { record.layers.forEach((item) => item.remove()); active.delete(record); }, 1500);
    return { layer };
  };
  /** Wählt aus den CMS-Gewichten; deaktivierte und saisonal ausgeschlossene Effekte fehlen im Manifest. */
  const choose = (effects, random = Math.random) => {
    let target = Math.max(0, Math.min(0.999999, random())) * effects.reduce((sum, effect) => sum + effect.weight, 0);
    return effects.find((effect) => { target -= effect.weight; return target < 0; });
  };
  const safely = (task) => Promise.resolve().then(task).catch((error) => {
    console.warn('[Masha:Feedly] Abschluss-Effekt nicht verfügbar:', error.message);
    return null;
  });
  const preload = (document, theme) => safely(async () => {
    if (reducedMotion()) return [];
    const effects = candidatesFor(await refresh(), theme);
    await Promise.allSettled(effects.map((effect) => load(effect, document)));
    return effects;
  });
  const preview = (id, document) => safely(async () => {
    if (reducedMotion()) return null;
    const token = generation;
    const effect = (await refresh()).find((item) => item.id === id);
    return effect ? start(effect, document, token) : null;
  });
  window.KWMashaFeedlyEffects = {
    preload, refresh, choose, cancelActive,
    preview: (id, document) => preview(id, document),
    play: (id, document) => preview(id, document),
    playOnDone: (document, unusedWindow, unusedImage, theme = 'playful', random = Math.random) => safely(async () => {
      if (reducedMotion()) return null;
      const token = generation;
      try {
        const effect = choose(candidatesFor(await refresh(), theme), random);
        return effect ? await start(effect, document, token) : fallback(document, token);
      } catch (error) {
        console.warn('[Masha:Feedly] Lokaler Abschluss-Effekt:', error.message);
        return fallback(document, token);
      }
    }),
  };
  document.addEventListener('pointerdown', cancelActive, true);
  document.addEventListener('kw-masha-feedly:opened', () => preload(document, window.KWMashaFeedlyDOM?.widget()?.dataset.theme || 'playful'));
  document.addEventListener('DOMContentLoaded', () => {
    const widget = window.KWMashaFeedlyDOM?.widget();
    if (widget) window.setTimeout(() => preload(document, widget.dataset.theme || 'playful'), 1000);
  });
})();
