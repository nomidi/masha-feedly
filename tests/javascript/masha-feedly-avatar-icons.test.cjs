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
  const images = [{ src: '', dataset: { loaded: 'true' } }];
  const choiceLabel = { textContent: 'Person' };
  const choice = {
    dataset: { iconId: 'person', iconBlack: '/black.svg', iconWhite: '/white.svg' },
    image: images[0], attributes: {}, classes: new Set(),
    querySelector: (selector) => selector === 'span' ? choiceLabel : images[0],
    classList: { toggle(name, state) { state ? this.owner.classes.add(name) : this.owner.classes.delete(name); }, owner: null },
    setAttribute(name, value) { this.attributes[name] = value; },
  };
  choice.classList.owner = choice;
  const clear = { attributes: {}, setAttribute(name, value) { this.attributes[name] = value; } };
  const closeButton = { focused: false, focus() { this.focused = true; } };
  const openButton = { textContent: 'Symbol auswählen', focused: false, focus() { this.focused = true; } };
  const dialog = { hidden: true, querySelector: () => closeButton };
  const hidden = { value: '', dispatchEvent() {} };
  const preview = { dataset: { avatarBase: '/__masha-feedly-effects/avatar/', iconId: '', initials: 'AB', uploadUrl: '' }, style: {}, replaceChildren() { this.image = null; }, append(image) { this.image = image; } };
  const form = { querySelector: (selector) => selector === '[name="MashaFeedlyAvatarIcon"]' ? hidden : selector === '[name="MashaFeedlyColor"]' ? colorField : selector === '[data-masha-feedly-avatar-preview]' ? preview : null, querySelectorAll: () => [root] };
  const root = {
    dataset: {},
    style: { setProperty(name, value) { this[name] = value; } },
    closest: (selector) => selector === 'form' ? form : null,
    querySelectorAll: (selector) => selector === '[data-masha-feedly-avatar-icon-choice]' ? [choice] : [],
    querySelector: (selector) => selector.startsWith('[data-masha-feedly-avatar-icon-dialog') ? dialog : selector.includes('[data-masha-feedly-avatar-icon-choice]') ? choice : ({
      '[data-masha-feedly-avatar-icon-clear]': clear,
      '[data-masha-feedly-avatar-icon-dialog]': dialog,
      '[data-masha-feedly-avatar-icon-open]': openButton,
    })[selector] || null,
  };
  const colorField = { name: 'MashaFeedlyColor', value: '#F4D06F', closest: () => form };
  const document = {
    createElement: () => ({ addEventListener() {} }),
    addEventListener(name, callback) { listeners[name] = callback; },
    querySelector: (selector) => selector === '[name="MashaFeedlyColor"]' ? colorField : null,
    querySelectorAll: (selector) => selector === '[data-masha-feedly-avatar-icons]' ? [root] : [],
  };
  root.getRootNode = () => document;
  root.addEventListener = (name, callback) => { listeners[`root-${name}`] = callback; };
  vm.runInNewContext(source, { document, window: {}, Event: class {} });
  return { listeners, root, choice, clear, hidden, colorField, images, dialog, openButton, closeButton, preview };
}

test('öffnet die Icon-Auswahl als Dialog und schließt sie nach Auswahl oder Escape', () => {
  const state = environment();
  const click = (button) => state.listeners.click({ target: { closest: (selector) => selector === '[data-masha-feedly-avatar-icon-open]' && button === state.openButton ? button : selector === '[data-masha-feedly-avatar-icon-close]' && button === state.closeButton ? button : selector === '[data-masha-feedly-avatar-icon-choice]' && button === state.choice ? button : selector === '[data-masha-feedly-avatar-icons]' ? state.root : null } });
  click(state.openButton);
  assert.equal(state.dialog.hidden, false);
  assert.equal(state.closeButton.focused, true);
  click(state.choice);
  assert.equal(state.dialog.hidden, true);
  assert.equal(state.openButton.textContent, 'Symbol: Person ändern');
  click(state.openButton);
  state.listeners.keydown({ key: 'Escape', target: { closest: () => state.root } });
  assert.equal(state.dialog.hidden, true);
  assert.equal(state.openButton.focused, true);
});

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

test('Profilvorschau zeigt das ausgewählte lokale Icon in der passenden Kontrastfarbe', () => {
  const state = environment();
  state.hidden.value = 'person';
  state.listeners.DOMContentLoaded();
  state.colorField.value = '#C05CC8';
  state.listeners.change({ target: state.colorField });
  assert.equal(state.preview.style.backgroundColor, '#C05CC8');
  assert.equal(state.preview.image.src, '/__masha-feedly-effects/avatar/person/white');
  state.colorField.value = '#F4D06F';
  state.listeners.change({ target: state.colorField });
  assert.equal(state.preview.image.src, '/__masha-feedly-effects/avatar/person/black');
  for (const color of ['#35A98F', '#69b85a']) {
    state.colorField.value = color;
    state.listeners.change({ target: state.colorField });
    assert.equal(state.preview.image.src, '/__masha-feedly-effects/avatar/person/white');
    assert.equal(state.images[0].src, '/white.svg');
    assert.equal(state.root.style['--masha-avatar-color'], color);
  }
});
