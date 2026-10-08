const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

/** Tests für das direkte Übernehmen einer Avatarfarbe per Farbfeld. @author Kooperative Web */
const source = fs.readFileSync(path.resolve(__dirname, '../../client/src/js/masha-feedly-colors.js'), 'utf8');

/** Erzeugt eine isolierte DOM-Umgebung für den Klick auf ein Farbfeld. */
function createColorEnvironment(fieldName = 'MashaFeedlyColor') {
  const listeners = {};
  const paletteOptions = [];
  const palette = { querySelectorAll: () => paletteOptions };
  const colorField = {
    name: fieldName,
    value: '',
    events: [],
    dispatchEvent(event) { this.events.push(event); },
  };
  const otherColorField = { name: fieldName, value: '#F4D06F' };
  const preview = { style: {} };
  const form = { querySelectorAll: () => [colorField], querySelector: () => preview };
  const option = {
    dataset: { fieldName, color: '#E95DAB' },
    attributes: {},
    classList: { toggle(name, enabled) { this[name] = enabled; } },
    closest(selector) { return selector === '.masha-feedly-color-palette' ? palette : selector === 'form' ? form : null; },
    setAttribute(name, value) { this.attributes[name] = value; },
  };
  paletteOptions.push(option);
  const document = {
    addEventListener(name, callback) { listeners[name] = callback; },
    querySelectorAll: (selector) => selector === '[data-masha-feedly-color-option]' ? paletteOptions : [otherColorField, colorField],
  };
  const Event = class { constructor(type, options) { this.type = type; this.bubbles = options.bubbles; } };
  vm.runInNewContext(source, { document, Event, window: {} });
  return { listeners, colorField, otherColorField, option, preview };
}

test('übernimmt die angeklickte Farbe im passenden Auswahlfeld', () => {
  const { listeners, colorField, option } = createColorEnvironment();
  listeners.click({ target: { closest: () => option } });
  assert.equal(colorField.value, '#E95DAB');
  assert.equal(colorField.events.length, 1);
  assert.equal(colorField.events[0].type, 'change');
  assert.equal(option.attributes['aria-pressed'], 'true');
  assert.equal(option.classList['is-selected'], true);
});

test('ignoriert Klicks außerhalb der Farbfelder', () => {
  const { listeners, colorField } = createColorEnvironment();
  listeners.click({ target: { closest: () => null } });
  assert.equal(colorField.value, '');
  assert.equal(colorField.events.length, 0);
});

test('ändert bei gleichnamigen CMS- und Widget-Feldern nur die Farbe des zugehörigen Formulars', () => {
  const state = createColorEnvironment();
  state.listeners.click({ target: { closest: () => state.option } });
  assert.equal(state.colorField.value, '#E95DAB');
  assert.equal(state.otherColorField.value, '#F4D06F');
  state.listeners.DOMContentLoaded();
  assert.equal(state.option.attributes['aria-pressed'], 'true');
  assert.equal(state.preview.style.backgroundColor, '#E95DAB');
});
