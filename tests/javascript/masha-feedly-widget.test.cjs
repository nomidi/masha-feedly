const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const sourcePath = path.resolve(__dirname, '../../client/src/js/masha-feedly.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const compiled = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly.js'), 'utf8');

/** Simuliert das Einsetzen des globalen Widgets in Frontend und CMS-Vorschau. */
function renderWidget(frameElement = null) {
  let ready;
  let inserted = 0;
  const controls = new Map([
    ['.kw-masha-feedly__toggle', { addEventListener() {}, setAttribute() {}, getAttribute() { return 'false'; } }],
    ['.kw-masha-feedly__panel', { set hidden(value) { this.isHidden = value; } }],
    ['.kw-masha-feedly__close', { addEventListener() {} }],
  ]);
  const widget = { querySelector(selector) { return controls.get(selector) || null; }, setAttribute() {} };
  const document = {
    addEventListener(name, callback) { if (name === 'DOMContentLoaded') ready = callback; },
    querySelector() { return null; },
    createElement() { return { innerHTML: '', firstElementChild: widget }; },
    body: { append() { inserted += 1; } },
    dispatchEvent() {},
  };
  const window = { KWMashaFeedlyWidgetMarkup: '<div data-kw-masha-feedly></div>', frameElement };
  vm.runInNewContext(source, { window, document, CustomEvent: function CustomEvent() {} });
  ready();
  return inserted;
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
