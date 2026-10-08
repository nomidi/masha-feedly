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
function renderWidget(frameElement = null, captureEvents = false) {
  let ready;
  let inserted = 0;
  let createdElements = 0;
  const documentListeners = new Map();
  const appendedElements = [];
  const classes = new Set();
  const controls = new Map([
    ['.kw-masha-feedly__toggle', { addEventListener() {}, setAttribute() {}, getAttribute() { return 'false'; } }],
    ['.kw-masha-feedly__panel', { set hidden(value) { this.isHidden = value; } }],
    ['.kw-masha-feedly__close', { addEventListener() {} }],
  ]);
  const triggerAttributes = new Map([['aria-label', 'Offene Fehler auf der gesamten Website ansehen'], ['aria-describedby', 'vorhandene-hilfe']]);
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
    querySelector(selector) { return controls.get(selector) || null; },
    setAttribute() {},
    contains(element) { return element === trigger; },
  };
  const surface = { append(element) { appendedElements.push(element); }, setAttribute() {} };
  const root = { append() {}, addEventListener() {}, querySelector(selector) {
    return selector === '[data-kw-masha-feedly]' ? widget : surface;
  } };
  const host = { setAttribute() {}, attachShadow(options) { assert.equal(options.mode, 'open'); this.shadowRoot = root; return root; } };
  const stylesheet = { addEventListener(name, callback) { if (name === 'load') this.loaded = callback; } };
  const document = {
    addEventListener(name, callback) {
      if (name === 'DOMContentLoaded') ready = callback;
      else documentListeners.set(name, callback);
    },
    querySelector(selector) { return inserted && selector === '[data-masha-feedly-host]' ? host : null; },
    createElement() {
      createdElements += 1;
      return [{ innerHTML: '', firstElementChild: widget }, host, stylesheet, surface, tooltip][createdElements - 1];
    },
    body: { append(element) { inserted += 1; appendedElements.push(element); } },
    dispatchEvent() {},
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
  vm.runInNewContext(source, { window, document, CustomEvent: function CustomEvent() {} });
  ready();
  if (!captureEvents) return inserted;
  return {
    inserted,
    root, surface, stylesheet, host,
    dom: window.KWMashaFeedlyDOM,
    trigger,
    dispatch(name, event) { documentListeners.get(name)?.(event); },
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
  assert.match(source, /aria-describedby/);
  assert.match(source, /\[data-masha-feedly-rainbow\]/);
});

test('blendet einen vollständig im Viewport platzierten Tooltip ein und stellt ARIA wieder her', () => {
  const state = renderWidget(null, true);
  state.dispatch('pointerover', { target: state.trigger });

  assert.ok(state.tooltip);
  assert.equal(state.tooltip.textContent, 'Offene Fehler auf der gesamten Website ansehen');
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

test('Avatarfarben zeigen den Hover-Effekt nur bei änderbaren Farbfeldern', () => {
  assert.match(hoverStyles[0], /&:hover:not\(:disabled\)/);
  assert.match(hoverStyles[0], /&:disabled:hover \{ transform: none !important;/);
  assert.match(hoverStyles[1], /masha-feedly-color-palette__item:hover:not\(:disabled\)/);
  assert.match(hoverStyles[1], /masha-feedly-color-palette__item:disabled:hover\{transform:none !important/);
});
