const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const sourcePath = path.resolve(__dirname, '../../client/src/js/masha-feedly-avatar-icons.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const dist = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly-avatar-icons.js'), 'utf8');

function environment() {
  const listeners = {};
  const images = [{ src: '', dataset: {} }];
  const choice = {
    dataset: { iconId: 'person', iconBlack: '/black.svg', iconWhite: '/white.svg' },
    image: images[0], attributes: {}, classes: new Set(),
    querySelector: () => images[0],
    classList: { toggle(name, state) { state ? this.owner.classes.add(name) : this.owner.classes.delete(name); }, owner: null },
    setAttribute(name, value) { this.attributes[name] = value; },
  };
  choice.classList.owner = choice;
  const clear = { attributes: {}, setAttribute(name, value) { this.attributes[name] = value; } };
  const hidden = { value: '' };
  const form = { querySelector: (selector) => selector === '[name="MashaFeedlyAvatarIcon"]' ? hidden : null };
  const root = {
    dataset: {},
    closest: (selector) => selector === 'form' ? form : null,
    querySelectorAll: (selector) => selector === '[data-masha-feedly-avatar-icon-choice]' ? [choice] : [],
    querySelector: (selector) => selector === '[data-masha-feedly-avatar-icon-clear]' ? clear : null,
  };
  const colorField = { name: 'MashaFeedlyColor', value: '#F4D06F' };
  const document = {
    addEventListener(name, callback) { listeners[name] = callback; },
    querySelector: (selector) => selector === '[name="MashaFeedlyColor"]' ? colorField : null,
    querySelectorAll: (selector) => selector === '[data-masha-feedly-avatar-icons]' ? [root] : [],
  };
  vm.runInNewContext(source, { document });
  return { listeners, root, choice, clear, hidden, colorField, images };
}

test('wählt ein Anbieter-Icon aus und setzt die Avatar-Kontrastfarbe', () => {
  const state = environment();
  state.listeners.DOMContentLoaded();
  assert.equal(state.root.dataset.iconColor, 'black');
  assert.equal(state.images[0].src, '/black.svg');
  state.listeners.click({ target: { closest: (selector) => selector === '[data-masha-feedly-avatar-icon-choice]' ? state.choice : selector === '[data-masha-feedly-avatar-icons]' ? state.root : null } });
  assert.equal(state.hidden.value, 'person');
  assert.equal(state.choice.attributes['aria-pressed'], 'true');
  state.colorField.value = '#222222';
  state.listeners.change({ target: state.colorField });
  assert.equal(state.root.dataset.iconColor, 'white');
  assert.equal(state.images[0].src, '/white.svg');
});

test('die ausgelieferte Profil-Icon-Logik entspricht der getesteten Quelldatei', () => {
  assert.equal(dist, source);
});
