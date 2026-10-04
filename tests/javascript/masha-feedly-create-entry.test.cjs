const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

/** Führt die Widget-Abläufe mit einer kleinen Browser-Simulation aus. @author Kooperative Web */
const sourceFile = path.resolve(__dirname, '../../client/src/js/masha-feedly-create-entry.js');
const source = fs.readFileSync(sourceFile, 'utf8');
const compiledSource = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly-create-entry.js'), 'utf8');

/** Simuliert die für Auswahl und Dialog benötigten DOM-Klassen. */
class TestClassList {
  constructor(values = []) { this.values = new Set(values); }
  get length() { return this.values.size; }
  add(value) { this.values.add(value); }
  remove(value) { this.values.delete(value); }
  contains(value) { return this.values.has(value); }
  toggle(value, force) {
    const enabled = force === undefined ? !this.contains(value) : force;
    if (enabled) this.add(value); else this.remove(value);
    return enabled;
  }
  [Symbol.iterator]() { return this.values[Symbol.iterator](); }
}

/** Simuliert einzelne Form- und Seitenelemente samt Listenern. */
class TestElement {
  constructor(tagName = 'div', { id = '', text = '', dataset = {} } = {}) {
    this.tagName = tagName.toUpperCase();
    this.nodeType = 1;
    this.id = id;
    this.innerText = text;
    this.textContent = text;
    this.dataset = dataset;
    this.classList = new TestClassList();
    this.children = [];
    this.parentElement = null;
    this.listeners = {};
    this.attributes = {};
    this.hidden = false;
    this.value = '';
    this.disabled = false;
  }
  addEventListener(name, callback) { this.listeners[name] = callback; }
  setAttribute(name, value) { this.attributes[name] = value; }
  closest(selector) { return selector === 'body *' ? this : null; }
  append(...elements) { elements.forEach((element) => { element.parentElement = this; this.children.push(element); }); }
  remove() {
    if (this.parentElement) this.parentElement.children = this.parentElement.children.filter((child) => child !== this);
  }
  replaceChildren(...children) { this.children = children; children.forEach((child) => { child.parentElement = this; }); }
  querySelector(selector) { return this.fields?.[selector] || null; }
  querySelectorAll(selector) { return selector === '[data-masha-feedly-close-modal]' ? this.closeButtons || [] : []; }
  contains(element) {
    if (element === this) return true;
    return this.children.some((child) => child.contains(element));
  }
  focus() { this.focused = true; }
}

/** Erzeugt das Widget mit Formularfeldern und lädt das produktive Skript. */
function createEnvironment(fetchImplementation = async () => ({
  ok: true,
  json: async () => ({ success: true, title: 'Button wird abgeschnitten', message: 'Eintrag gespeichert.' }),
})) {
  const widget = new TestElement('aside');
  const startButton = new TestElement('button');
  const banner = new TestElement('div'); banner.hidden = true;
  const modal = new TestElement('div'); modal.hidden = true;
  const context = new TestElement('div');
  const status = new TestElement('p');
  const toast = new TestElement('div'); toast.hidden = true;
  const toastMessage = new TestElement('span');
  const dismissToast = new TestElement('button');
  const panel = new TestElement('section');
  const toggle = new TestElement('button');
  const cancel = new TestElement('button');
  const close = new TestElement('button');
  const column = new TestElement('section');
  const submit = new TestElement('button');
  const content = new TestElement('textarea');
  const date = new TestElement('input');
  const pageURL = new TestElement('input');
  const selector = new TestElement('input');
  const selectedText = new TestElement('input');
  const form = new TestElement('form', { id: 'kw-masha-feedly-create-form', dataset: { createUrl: '/__masha-feedly/createEntry', similarUrl: '/__masha-feedly/findSimilarEntries', securityId: 'csrf-token' } });
  const similarSection = new TestElement('section'); similarSection.hidden = true;
  const similarResults = new TestElement('div');
  form.fields = {
    '[name="PageURL"]': pageURL,
    '[name="ElementSelector"]': selector,
    '[name="ElementText"]': selectedText,
    '[name="EntryDate"]': date,
    '[name="Content"]': content,
    '[data-masha-feedly-similar]': similarSection,
    '[data-masha-feedly-similar-results]': similarResults,
  };
  form.append = (element) => {
    element.parentElement = form;
    form.children.push(element);
    form.fields[`[name="${element.name}"]`] = element;
  };
  form.reset = () => { content.value = ''; };
  widget.children = [panel, column];
  panel.children = [startButton, toggle, close, cancel, modal, form, banner, context, status, toast, toastMessage, dismissToast, similarSection, similarResults];
  const lookup = new Map([
    ['[data-masha-feedly-start-selection]', startButton],
    ['[data-masha-feedly-selection-banner]', banner],
    ['[data-masha-feedly-modal]', modal],
    ['[data-masha-feedly-entry-form]', form],
    ['[data-masha-feedly-similar]', similarSection],
    ['[data-masha-feedly-similar-results]', similarResults],
    ['[data-masha-feedly-selected-context]', context],
    ['[data-masha-feedly-form-status]', status],
    ['[data-masha-feedly-save-toast]', toast],
    ['[data-masha-feedly-save-message]', toastMessage],
    ['[data-masha-feedly-dismiss-toast]', dismissToast],
    ['[data-masha-feedly-cancel-selection]', cancel],
    ['.kw-masha-feedly__panel', panel],
    ['.kw-masha-feedly__toggle', toggle],
    ['.kw-masha-feedly__column', column],
  ]);
  modal.closeButtons = [close];
  widget.querySelector = (selectorText) => lookup.get(selectorText) || null;
  widget.querySelectorAll = (selectorText) => selectorText === '[data-masha-feedly-close-modal]' ? [close] : [];

  const documentListeners = {};
  const dispatchedEvents = [];
  const body = new TestElement('body');
  const document = {
    body,
    addEventListener(name, callback) { documentListeners[name] = callback; },
    dispatchEvent(event) { dispatchedEvents.push(event.type); },
    querySelector(selectorText) {
      if (selectorText === '[data-kw-masha-feedly]') return widget;
      if (selectorText === 'button[type="submit"][form="kw-masha-feedly-create-form"]') return submit;
      return null;
    },
    createElement(tagName) { return new TestElement(tagName); },
  };
  const formData = {
    values: new Map(),
    set(name, value) { this.values.set(name, value); },
  };
  const calls = { fetch: null };
  const textValues = {
    UNKNOWN_BROWSER: 'Unbekannter Browser',
    ENTRY_SELECTED_CONTEXT: 'Ausgewählter Bereich: „{text}“',
    ENTRY_CONTEXT_EMPTY: 'Bereich auf der Seite ausgewählt.',
    CREATE_SAVING: 'Eintrag wird gespeichert …',
    CREATE_SAVE_ERROR: 'Der Eintrag konnte nicht gespeichert werden.',
    CREATE_ENTRY_FALLBACK: 'Neuer Eintrag',
    SIMILAR_ENTRY_NO_TITLE: 'Eintrag ohne Titel', SIMILAR_SCORE: '{score}% ähnlich', SIMILAR_OPEN_ENTRY: 'Eintrag ansehen',
    CLOSE_WIDGET: 'Masha:Feedly schließen',
  };
  const contextObject = {
    document,
    URL,
    window: {
      location: { href: 'https://example.test/kontakt' },
      innerWidth: 1943,
      innerHeight: 1294,
      navigator: {
        platform: 'MacIntel',
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/152.0.0.0 Safari/537.36',
      },
      screen: { width: 2560, height: 1440, colorDepth: 24 },
      KWMashaFeedlyTranslate(key, values = {}) {
        return Object.entries(values).reduce((message, [name, value]) => message.replaceAll(`{${name}}`, String(value)), textValues[key] || key);
      },
    },
    CSS: { escape: (value) => value },
    FormData: class { constructor() { return formData; } },
    Event: class { constructor(type, options) { this.type = type; this.bubbles = options.bubbles; } },
    CustomEvent: class { constructor(type) { this.type = type; } },
    fetch: async (...args) => { calls.fetch = args; return fetchImplementation(...args); },
    setTimeout: (callback, delay) => { (calls.timeouts ||= []).push({ callback, delay }); },
    clearTimeout: () => {},
  };
  vm.runInNewContext(source, contextObject);
  documentListeners.DOMContentLoaded();
  return { widget, startButton, banner, modal, context, status, toast, toastMessage, dismissToast, panel, toggle, form, content, date, pageURL, selector, selectedText, submit, column, body, documentListeners, dispatchedEvents, calls, formData, similarSection, similarResults, window: contextObject.window };
}

test('erkennt Betriebssystem und Browser samt Version, Auflösung, Fenstergröße und Farbtiefe', () => {
  const env = createEnvironment();
  const info = env.window.KWMashaFeedlyEnvironment.collect(
    env.window.navigator.userAgent,
    env.window.navigator.platform,
    env.window.screen,
    env.window
  );
  assert.equal(info.operatingSystem, 'Mac OS 10.15.7');
  assert.equal(info.browser, 'Chrome 152.0.0.0');
  assert.equal(info.resolution, '2560 × 1440 px');
  assert.equal(info.browserWindow, '1943 × 1294 px');
  assert.equal(info.colorDepth, '24');
  assert.match(info.userAgent, /Chrome\/152/);
});

test('startet die Bereichsauswahl, übernimmt Seitenelement und Browserdaten', () => {
  const env = createEnvironment();
  env.startButton.listeners.click();
  assert.equal(env.banner.hidden, false);
  assert.equal(env.body.classList.contains('kw-masha-feedly-is-selecting'), true);
  assert.equal(env.panel.hidden, true);
  assert.equal(env.toggle.attributes['aria-expanded'], 'false');

  const target = new TestElement('button', { id: 'contact-submit', text: 'Absenden   jetzt' });
  let prevented = false;
  let stopped = false;
  env.documentListeners.click({
    target,
    preventDefault() { prevented = true; },
    stopPropagation() { stopped = true; },
  });

  assert.equal(prevented, true);
  assert.equal(stopped, true);
  assert.equal(env.banner.hidden, true);
  assert.equal(env.modal.hidden, false);
  assert.equal(env.context.textContent, 'Ausgewählter Bereich: „Absenden jetzt“');
  assert.equal(env.form.querySelector('[name="PageURL"]').value, 'https://example.test/kontakt');
  assert.equal(env.form.querySelector('[name="ElementSelector"]').value, '#contact-submit');
  assert.equal(env.form.querySelector('[name="ElementText"]').value, 'Absenden jetzt');
  assert.equal(env.form.querySelector('[name="OperatingSystem"]').value, 'Mac OS 10.15.7');
  assert.equal(env.form.querySelector('[name="Browser"]').value, 'Chrome 152.0.0.0');
  assert.equal(env.form.querySelector('[name="Resolution"]').value, '2560 × 1440 px');
  assert.equal(env.form.querySelector('[name="BrowserWindow"]').value, '1943 × 1294 px');
  assert.equal(env.form.querySelector('[name="ColorDepth"]').value, '24');
  assert.equal(env.content.focused, true);
  assert.equal(env.date.value, '', 'Der automatisch gesetzte Erstellungszeitpunkt wird nicht als editierbares Feld angezeigt.');
});

test('bricht die Bereichsauswahl mit Escape ab und stellt das Widget wieder her', () => {
  const env = createEnvironment();
  env.startButton.listeners.click();
  const target = new TestElement('main', { text: 'Ausgewählter Bereich' });
  env.documentListeners.pointerover({ target });
  assert.equal(target.classList.contains('kw-masha-feedly-selected-target'), true);

  let prevented = false;
  env.documentListeners.keydown({ key: 'Escape', preventDefault() { prevented = true; } });

  assert.equal(prevented, true);
  assert.equal(env.banner.hidden, true);
  assert.equal(env.body.classList.contains('kw-masha-feedly-is-selecting'), false);
  assert.equal(target.classList.contains('kw-masha-feedly-selected-target'), false);
  assert.equal(env.panel.hidden, false);
  assert.equal(env.toggle.attributes['aria-expanded'], 'true');
  assert.deepEqual(env.dispatchedEvents, ['kw-masha-feedly:onboarding-selection-started', 'kw-masha-feedly:closed', 'kw-masha-feedly:onboarding-selection-cancelled', 'kw-masha-feedly:opened']);
});

test('sendet das Formular mit CSRF-Token und zeigt die erfolgreiche Anlage', async () => {
  const env = createEnvironment();
  assert.equal(env.form.querySelector('[type="submit"]'), null, 'der Submit-Button befindet sich außerhalb des Formulars');
  env.startButton.listeners.click();
  const target = new TestElement('p', { id: 'intro', text: 'Hinweistext' });
  env.documentListeners.click({ target, preventDefault() {}, stopPropagation() {} });
  env.content.value = 'Der Button ist abgeschnitten.';
  let prevented = false;
  await env.form.listeners.submit({ preventDefault() { prevented = true; } });

  assert.equal(prevented, true);
  assert.equal(env.calls.fetch[0], '/__masha-feedly/createEntry');
  assert.equal(env.calls.fetch[1].method, 'POST');
  assert.equal(env.calls.fetch[1].credentials, 'same-origin');
  assert.equal(env.formData.values.get('SecurityID'), 'csrf-token');
  assert.equal(env.status.textContent, 'Eintrag gespeichert.');
  assert.equal(env.panel.hidden, false);
  assert.equal(env.toggle.attributes['aria-expanded'], 'true');
  assert.ok(env.dispatchedEvents.includes('kw-masha-feedly:opened'));
  assert.ok(env.dispatchedEvents.includes('kw-masha-feedly:refresh'));
  assert.equal(env.toast.hidden, false);
  assert.equal(env.toastMessage.textContent, 'Eintrag gespeichert.');
  assert.deepEqual(env.calls.timeouts.map(({ delay }) => delay), [500, 6000]);
  env.calls.timeouts[1].callback();
  assert.equal(env.toast.hidden, true);
  assert.equal(env.column.children.at(-1).textContent, 'Button wird abgeschnitten');
  assert.equal(env.submit.disabled, false);
});

test('stellt im Erfassungsformular ein optionales Fälligkeitsdatum bereit', () => {
  const template = fs.readFileSync(path.resolve(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  assert.match(template, /name="DueDate" type="date"/);
  assert.doesNotMatch(template, /name="EntryDate"|type="datetime-local"/, 'Der Erstellungszeitpunkt wird automatisch gesetzt und bleibt aus dem Formular heraus.');
  const styles = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly.scss'), 'utf8');
  assert.match(styles, /input\[type="date"\]/, 'Das Fälligkeitsdatum erhält dieselbe Eingabefeldgestaltung wie die übrigen Datumsfelder.');
  assert.match(source, /new FormData\(form\)/, 'Das Datumsfeld wird mit den übrigen Formulardaten an den Eintragsendpunkt gesendet.');
});

test('zeigt den Serverfehler an und lässt das Formular erneut absenden', async () => {
  const env = createEnvironment(async () => ({
    ok: false,
    json: async () => ({ success: false, message: 'Keine Berechtigung.' }),
  }));
  env.startButton.listeners.click();
  env.documentListeners.click({ target: new TestElement('main', { id: 'main' }), preventDefault() {}, stopPropagation() {} });
  await env.form.listeners.submit({ preventDefault() {} });
  assert.equal(env.status.textContent, 'Keine Berechtigung.');
  assert.equal(env.toast.hidden, true);
  assert.equal(env.submit.disabled, false);
  assert.equal(env.column.children.length, 0);
});

test('zeigt abgelehnte Uploads beim Erstellen an und meldet keinen falschen Erfolg', async () => {
  const env = createEnvironment(async () => ({
    ok: false,
    json: async () => ({ success: false, message: 'Der Dateityp passt nicht zur Dateiendung.' }),
  }));
  env.startButton.listeners.click();
  env.documentListeners.click({ target: new TestElement('main', { id: 'main' }), preventDefault() {}, stopPropagation() {} });
  await env.form.listeners.submit({ preventDefault() {} });
  assert.equal(env.status.textContent, 'Der Dateityp passt nicht zur Dateiendung.');
  assert.equal(env.toast.hidden, true);
  assert.equal(env.column.children.length, 0);
  assert.equal(env.submit.disabled, false);
});

test('zeigt bei einem Netzwerkfehler keinen Erfolgshinweis und gibt die Schaltfläche frei', async () => {
  const env = createEnvironment(async () => { throw new Error('Netzwerk nicht erreichbar.'); });
  env.startButton.listeners.click();
  env.documentListeners.click({ target: new TestElement('main', { id: 'main' }), preventDefault() {}, stopPropagation() {} });
  await env.form.listeners.submit({ preventDefault() {} });
  assert.equal(env.status.textContent, 'Netzwerk nicht erreichbar.');
  assert.equal(env.toast.hidden, true);
  assert.equal(env.submit.disabled, false);
});

test('blendet Ähnlichkeitsvorschläge beim Erstellen vorerst aus', () => {
  const env = createEnvironment();
  assert.equal(env.content.listeners.input, undefined);
  assert.doesNotMatch(source, /data-masha-feedly-similar|data-similar-url/);
  const widgetTemplate = fs.readFileSync(path.resolve(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  assert.doesNotMatch(widgetTemplate, /data-masha-feedly-similar|data-similar-url|kw-masha-feedly__similar/);
});

test('ausgeliefertes JavaScript entspricht der getesteten Quelldatei', () => {
  assert.equal(compiledSource, source);
});
