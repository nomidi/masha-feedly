const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const sourcePath = path.resolve(__dirname, '../../client/src/js/masha-feedly.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const compiled = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly.js'), 'utf8');
const hoverStyles = [
  fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly.scss'), 'utf8'),
  fs.readFileSync(path.resolve(__dirname, '../../client/dist/css/masha-feedly.css'), 'utf8'),
];

/** Simuliert das Einsetzen des globalen Widgets in Frontend und CMS-Vorschau. */
function renderWidget(frameElement = null, captureEvents = false, widgetDataResponse = null) {
  let ready;
  const documentListeners = new Map();
  const widgetDataRequests = [];
  const assigneeContainers = [
    { children: [], closest() { return null; }, replaceChildren(...children) { this.children = children; } },
    { children: [], closest() { return {}; }, replaceChildren(...children) { this.children = children; } },
  ];
  let inserted = 0;
  let createdElements = 0;
  const rootListeners = new Map();
  const appendedElements = [];
  const classes = new Set();
  const controls = new Map([
    ['.kw-masha-feedly__toggle', { addEventListener() {}, setAttribute() {}, getAttribute() { return 'false'; } }],
    ['.kw-masha-feedly__panel', { set hidden(value) { this.isHidden = value; } }],
    ['.kw-masha-feedly__close', { addEventListener() {} }],
  ]);
  const triggerAttributes = new Map([['aria-label', 'Offene Meldungen auf der gesamten Website ansehen'], ['aria-describedby', 'vorhandene-hilfe']]);
  const trigger = {
    closest(selector) { return selector.includes('kw-masha-feedly__count-button') ? this : null; },
    contains() { return false; },
    getAttribute(name) { return triggerAttributes.get(name) || null; },
    setAttribute(name, value) { triggerAttributes.set(name, value); },
    removeAttribute(name) { triggerAttributes.delete(name); },
    getBoundingClientRect() { return { left: 1660, right: 1716, top: 80, bottom: 136, width: 56, height: 56 }; },
  };
  const tooltip = {
    classList: {
      add(...names) { names.forEach((name) => classes.add(name)); },
      remove(...names) { names.forEach((name) => classes.delete(name)); },
      contains(name) { return classes.has(name); },
    },
    style: {},
    offsetWidth: 260,
    offsetHeight: 60,
    setAttribute(name, value) { this[name] = value; },
  };
  const widget = {
    dataset: { widgetDataUrl: '/__masha-feedly/widgetData' },
    querySelector(selector) { return controls.get(selector) || null; },
    setAttribute() {},
    contains(element) { return element === trigger; },
  };
  const surface = { append(element) { appendedElements.push(element); }, setAttribute() {} };
  const root = { append() {}, addEventListener(name, callback) { rootListeners.set(name, callback); }, querySelector(selector) {
    return selector === '[data-kw-masha-feedly]' ? widget : surface;
  }, querySelectorAll(selector) {
    return selector === '[data-masha-feedly-assignees] > div' ? assigneeContainers : [];
  } };
  const host = { setAttribute() {}, attachShadow(options) { assert.equal(options.mode, 'open'); this.shadowRoot = root; return root; } };
  const stylesheet = { addEventListener(name, callback) { if (name === 'load') this.loaded = callback; } };
  const document = {
    addEventListener(name, callback) {
      if (name === 'DOMContentLoaded') ready = callback;
      else if (name === 'kw-masha-feedly:opened') documentListeners.set(name, callback);
      else assert.fail(`Ereignis ${name} muss an der Widget-Shadow-Root registriert werden`);
    },
    querySelector(selector) { return inserted && selector === '[data-masha-feedly-host]' ? host : null; },
    createElement() {
      createdElements += 1;
      if (createdElements <= 4) return [{ innerHTML: '', firstElementChild: widget }, host, stylesheet, surface][createdElements - 1];
      if (!widgetDataResponse) return tooltip;
      return { children: [], dataset: {}, style: {}, setAttribute() {}, append(...children) { this.children.push(...children); } };
    },
    body: { append(element) { inserted += 1; appendedElements.push(element); } },
    dispatchEvent(event) { documentListeners.get(event.type)?.(event); },
  };
  const window = {
    KWMashaFeedlyWidgetMarkup: '<div data-kw-masha-feedly></div>',
    frameElement,
    innerWidth: 1728,
    innerHeight: 1117,
    clearTimeout() {},
    setTimeout(callback) { callback(); return 1; },
    requestAnimationFrame(callback) { callback(); },
  };
  vm.runInNewContext(source, {
    window, document, CustomEvent: function CustomEvent(type) { this.type = type; },
    fetch: async (url) => { widgetDataRequests.push(url); return { ok: true, json: async () => widgetDataResponse }; },
  });
  ready();
  if (!captureEvents) return inserted;
  return {
    inserted,
    widget, assigneeContainers, widgetDataRequests,
    openProgrammatically() { document.dispatchEvent({ type: 'kw-masha-feedly:opened' }); },
    root, surface, stylesheet, host,
    dom: window.KWMashaFeedlyDOM,
    trigger,
    dispatch(name, event) { rootListeners.get(name)?.(event); },
    get tooltip() { return appendedElements.find((element) => element === tooltip); },
    get classes() { return classes; },
  };
}

test('setzt das Widget nicht zusätzlich in die CMS-Vorschau ein', () => {
  const preview = {
    matches(selector) { return selector === '.cms-preview'; },
    closest() { return null; },
  };
  assert.equal(renderWidget(preview), 0);
});

test('setzt das Widget im normalen Frontend ein', () => {
  assert.equal(renderWidget(null), 1);
});

test('liefert dieselbe Widget-Logik aus wie sie getestet wird', () => {
  assert.equal(compiled, source);
});

test('zeigt Hover-Hinweise als schwebendes Tooltip außerhalb des scrollenden Panels', () => {
  for (const css of hoverStyles) {
    assert.match(css, /\.kw-masha-feedly__hover-tooltip\s*\{[^}]*position:\s*fixed/s);
    assert.match(css, /\.kw-masha-feedly__hover-tooltip\.is-visible/);
    assert.doesNotMatch(css, /--kw-feedly-panel-width:\s*min\(248px, calc\(100vw - 16px\)\)/);
  }
  assert.match(source, /surface\.append\(hoverHint\)/);
  assert.match(source, /'\[data-tooltip\]'/);
  assert.match(source, /target\.getAttribute\('data-tooltip'\)/);
  assert.match(source, /aria-describedby/);
  assert.match(source, /\[data-masha-feedly-rainbow\]/);
});

test('blendet einen vollständig im Viewport platzierten Tooltip ein und stellt ARIA wieder her', () => {
  const state = renderWidget(null, true);
  state.dispatch('pointerover', { target: state.trigger });

  assert.ok(state.tooltip);
  assert.equal(state.tooltip.textContent, 'Offene Meldungen auf der gesamten Website ansehen');
  assert.equal(state.tooltip['aria-hidden'], 'false');
  assert.ok(state.classes.has('is-left'));
  assert.ok(state.classes.has('is-visible'));
  assert.equal(state.trigger.getAttribute('aria-describedby'), 'vorhandene-hilfe kw-masha-feedly-hover-tooltip');
  assert.ok(Number.parseFloat(state.tooltip.style.left) >= 0);
  assert.ok(Number.parseFloat(state.tooltip.style.left) + state.tooltip.offsetWidth <= 1728);

  state.dispatch('pointerout', { target: state.trigger, relatedTarget: null });
  assert.equal(state.tooltip.hidden, true);
  assert.equal(state.trigger.getAttribute('aria-describedby'), 'vorhandene-hilfe');
});


test('kapselt Widget und Overlays gemeinsam und löst retargetete Ereignisse auf', () => {
  const state = renderWidget(null, true);
  assert.equal(state.dom.root(), state.root);
  assert.equal(state.dom.overlayRoot(), state.surface);
  assert.equal(state.surface.hidden, true);
  state.stylesheet.loaded();
  assert.equal(state.surface.hidden, false);
  state.root.activeElement = state.trigger;
  assert.equal(state.dom.activeElement(), state.trigger);
  state.dispatch('pointerover', { target: state.host, composedPath: () => [state.trigger, state.root, state.host] });
  assert.ok(state.tooltip);
});

test('öffnet das Panel durch Ziehen an der seitlichen Lasche mit klarer Schwelle und elastischer Bewegung', () => {
  const state = renderWidget(null, true);
  assert.equal(state.dom.panelPullProgress(-12, 368), 0);
  assert.equal(state.dom.panelPullProgress(24, 368), 0);
  assert.equal(state.dom.panelPullProgress(36, 368), 12 / 368);
  assert.equal(state.dom.panelPullProgress(500, 368), 1);
  assert.equal(state.dom.shouldOpenPulledPanel(71), false);
  assert.equal(state.dom.shouldOpenPulledPanel(72), true);
  assert.match(source, /addEventListener\('pointerdown'/);
  assert.match(source, /addEventListener\('pointermove'/);
  for (const css of hoverStyles) {
    assert.match(css, /data-panel-pulling(?:="true"|=true)/);
    assert.match(css, /width:\s*calc\(112px\s*\+\s*var\(--masha-panel-pull-distance, 0px\)\)/);
    assert.match(css, /data-panel-pulling[^}]*translateX\(calc\(/);
    assert.match(css, /data-panel-pulling[^}]*z-index:\s*10009/);
    assert.match(css, /__toggle:hover[^}]*width:\s*112px/);
    assert.match(css, /__toggle:hover[^}]*toggle-wink/);
    assert.match(css, /@keyframes masha-feedly-toggle-wink/);
    assert.match(css, /__toggle\s*\{[^}]*overflow:\s*hidden/s);
    assert.match(css, /__toggle \.kw-masha-feedly__logo\s*\{[^}]*width:\s*60px/s);
    assert.match(css, /data-panel-pulling[^}]*bottom:\s*-6px/);
    assert.match(css, /width:\s*80px/);
    assert.match(css, /transform:\s*translateX\(0\)/);
    assert.match(css, /prefers-reduced-motion:\s*reduce/);
  }
});

test('Avatarfarben zeigen den Hover-Effekt nur bei änderbaren Farbfeldern', () => {
  assert.match(hoverStyles[0], /&:hover:not\(:disabled\)/);
  assert.match(hoverStyles[0], /&:disabled:hover \{ transform: none !important;/);
  assert.match(hoverStyles[1], /masha-feedly-color-palette__item:hover:not\(:disabled\)/);
  assert.match(hoverStyles[1], /masha-feedly-color-palette__item:disabled:hover\{transform:none !important/);
});


/** Programmgesteuertes Öffnen muss dieselben Personen laden wie der Klick auf die Lasche. */
test('lädt Verantwortliche bei programmgesteuertem Öffnen für Erstellen und Bearbeiten', async () => {
  const state = renderWidget(null, true, {
    success: true, categories: [], priorities: [],
    members: [{ id: 7, name: 'Ada Beispiel', initials: 'AB', color: '#6383D8', imageURL: '' }],
  });
  state.openProgrammatically();
  state.openProgrammatically();
  await new Promise(setImmediate);
  assert.equal(state.widgetDataRequests.length, 1, 'Gleichzeitige Öffnungsereignisse teilen dieselbe Anfrage.');
  assert.equal(state.widget.dataset.widgetDataLoaded, 'true');
  for (const container of state.assigneeContainers) {
    assert.equal(container.children.length, 1);
    const [checkbox, avatar, title] = container.children[0].children;
    assert.equal(checkbox.name, 'AssignedMemberIDs[]');
    assert.equal(checkbox.value, 7);
    assert.equal(avatar.textContent, 'AB');
    assert.equal(title.textContent, 'Ada Beispiel');
  }
  state.openProgrammatically();
  await new Promise(setImmediate);
  assert.equal(state.widgetDataRequests.length, 1, 'Bereits geladene Personen werden bei erneutem Öffnen nicht neu angefragt.');
});


test('stellt die Personen vor dem Bearbeiten eines direkt geöffneten Eintrags bereit', async () => {
  const state = renderWidget(null, true, {
    success: true, categories: [], priorities: [],
    members: [{ id: 8, name: 'Berta Beispiel', initials: 'BB', color: '#6383D8', imageURL: '' }],
  });
  await state.dom.loadWidgetData();
  assert.equal(state.widget.dataset.widgetDataLoaded, 'true');
  assert.equal(state.assigneeContainers[1].children[0].children[0].value, 8);
});
