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
  const option = {
    dataset: { fieldName, color: '#E95DAB' },
    attributes: {},
    classList: { toggle(name, enabled) { this[name] = enabled; } },
    closest(selector) { return selector === '.masha-feedly-color-palette' ? palette : null; },
    setAttribute(name, value) { this.attributes[name] = value; },
  };
  paletteOptions.push(option);
  const document = {
    addEventListener(name, callback) { listeners[name] = callback; },
    querySelectorAll: () => [colorField],
  };
  const Event = class { constructor(type, options) { this.type = type; this.bubbles = options.bubbles; } };
  vm.runInNewContext(source, { document, Event });
  return { listeners, colorField, option };
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
