const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

/** Führt die Widget-Abläufe mit einer kleinen Browser-Simulation aus. @author Kooperative Web */
const sourceFile = path.resolve(__dirname, '../../client/src/js/masha-feedly-create-entry.js');
const source = fs.readFileSync(sourceFile, 'utf8');
const compiledSource = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly-create-entry.js'), 'utf8');
const widgetTemplate = fs.readFileSync(path.resolve(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');

test('Screenshot-Aktion fragt Browserfreigabe ab und hängt das Bild optional an den bestehenden Upload', () => {
  for (const script of [source, compiledSource]) {
    assert.match(script, /data-masha-feedly-screenshot-capture/);
    assert.match(script, /mediaDevices\?\.getDisplayMedia/);
    assert.match(script, /data\.append\('Attachments\[\]'\s*,\s*screenshotFile\)/);
    assert.match(script, /screenshotSurface\.hidden = true/);
    assert.match(script, /screenshotSurface\.hidden = previousSurfaceHidden/);
    assert.match(script, /screenshotHost\.hidden = true/);
    assert.match(script, /screenshotHost\.hidden = previousHostHidden/);
    assert.match(script, /requestAnimationFrame\(\(\) => window\.requestAnimationFrame\(resolve\)\)/);
    assert.ok(script.indexOf('screenshotSurface.hidden = true') < script.indexOf('await new Promise((resolve) => window.requestAnimationFrame'));
    assert.ok(script.indexOf('await new Promise((resolve) => window.requestAnimationFrame') < script.indexOf('getDisplayMedia({ video: true, audio: false })'));
    assert.match(script, /data-masha-feedly-screenshot-remove/);
    assert.match(script, /data-masha-feedly-screenshot-cropper/);
    assert.match(script, /data-masha-feedly-screenshot-apply/);
    assert.match(script, /drawImage\(screenshotImage, left, top, width, height, 0, 0, width, height\)/);
    assert.match(script, /seiten-ausschnitt\.png/);
    assert.match(script, /screenshotSelection\.complete = true/);
    assert.match(script, /if \(!screenshotSelection \|\| screenshotSelection\.complete\)/);
    assert.match(script, /stream\?\.getTracks\(\)\.forEach\(\(track\) => track\.stop\(\)\)/);
    assert.match(script, /revokeObjectURL/);
  }
  assert.match(widgetTemplate, /Screenshot hinzufügen/);
  assert.match(widgetTemplate, /Screenshot entfernen/);
  assert.match(widgetTemplate, /Ausschnitt übernehmen/);
  assert.match(widgetTemplate, /Klicke ein zweites Mal auf die gegenüberliegende Ecke/);
});

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
  addEventListener(name, callback) {
    const previous = this.listeners[name];
    this.listeners[name] = previous ? (...args) => { previous(...args); callback(...args); } : callback;
  }
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
  const widget = new TestElement('aside', { dataset: { canManageEstimate: '1', estimateHourlyRate: '120' } });
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
  const createCategory = new TestElement('select');
  createCategory.selectedOptions = [{ dataset: { systemKey: 'backlog' } }];
  const estimateDuration = new TestElement('input'); estimateDuration.name = 'EstimatedCostDuration'; estimateDuration.disabled = true;
  const estimateNote = new TestElement('textarea'); estimateNote.name = 'EstimatedCostNote'; estimateNote.disabled = true;
  const estimatePrice = new TestElement('output');
  const estimateSection = new TestElement('section'); estimateSection.hidden = true;
  estimateSection.fields = { '[data-masha-feedly-estimate-price]': estimatePrice };
  const diagnostics = new TestElement('details');
  const diagnosticsSteps = new TestElement('textarea'); diagnosticsSteps.name = 'StepsToReproduce';
  const diagnosticsExpected = new TestElement('textarea'); diagnosticsExpected.name = 'ExpectedResult';
  const diagnosticsActual = new TestElement('textarea'); diagnosticsActual.name = 'ActualResult';
  const form = new TestElement('form', { id: 'kw-masha-feedly-create-form', dataset: { createUrl: '/__masha-feedly/createEntry', similarUrl: '/__masha-feedly/findSimilarEntries', securityId: 'csrf-token' } });
  // Native HTMLFormElement.elements is present even when the optional estimate fields are absent.
  form.elements = { EstimatedCostDuration: estimateDuration, EstimatedCostNote: estimateNote, StepsToReproduce: diagnosticsSteps, ExpectedResult: diagnosticsExpected, ActualResult: diagnosticsActual };
  const similarSection = new TestElement('section'); similarSection.hidden = true;
  const similarResults = new TestElement('div');
  form.fields = {
    '[name="PageURL"]': pageURL,
    '[name="ElementSelector"]': selector,
    '[name="ElementText"]': selectedText,
    '[name="EntryDate"]': date,
    '[name="Content"]': content,
    '[data-masha-feedly-create-category]': createCategory,
    '[data-masha-feedly-create-estimate]': estimateSection,
    '[data-masha-feedly-diagnostics]': diagnostics,
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
  form.append(estimateSection);
  panel.children = [startButton, toggle, close, cancel, modal, form, banner, context, status, toast, toastMessage, dismissToast, similarSection, similarResults];
  const lookup = new Map([
    ['[data-masha-feedly-start-selection]', startButton],
    ['[data-masha-feedly-selection-banner]', banner],
    ['[data-masha-feedly-modal]', modal],
    ['[data-masha-feedly-entry-form]', form],
    ['[data-masha-feedly-create-category]', createCategory],
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
    ['button[type="submit"][form="kw-masha-feedly-create-form"]', submit],
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
    CREATE_SAVING: 'Meldung wird gespeichert …',
    CREATE_SAVE_ERROR: 'Die Meldung konnte nicht gespeichert werden.',
    SAVE_CONFIRMED_DISPLAY_ERROR: 'Gespeichert. Die Anzeige konnte nicht aktualisiert werden. Bitte lade die Meldungsliste neu.',
    CREATE_ENTRY_FALLBACK: 'Neue Meldung',
    SIMILAR_ENTRY_NO_TITLE: 'Meldung ohne Titel', SIMILAR_SCORE: '{score}% ähnlich', SIMILAR_OPEN_ENTRY: 'Meldung ansehen',
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
  return { widget, startButton, banner, modal, context, status, toast, toastMessage, dismissToast, panel, toggle, form, content, date, pageURL, selector, selectedText, submit, column, body, documentListeners, dispatchedEvents, calls, formData, similarSection, similarResults, createCategory, estimateSection, estimateDuration, estimateNote, estimatePrice, diagnostics, diagnosticsSteps, diagnosticsExpected, diagnosticsActual, window: contextObject.window };
}

test('optionale Fehlerdetails öffnen sich bei Fehlerbegriffen automatisch und bleiben manuell verfügbar', () => {
  const env = createEnvironment();
  assert.equal(env.diagnostics.open, undefined, 'Das Details-Element startet geschlossen.');
  assert.equal(env.diagnosticsSteps.disabled, false);
  assert.equal(env.diagnosticsExpected.disabled, false);
  assert.equal(env.diagnosticsActual.disabled, false);
  env.content.value = 'Beim Speichern erscheint ein Fehler.';
  env.content.listeners.input();
  assert.equal(env.diagnostics.open, true);
  env.diagnostics.open = false;
  env.content.value = 'The bug appears when saving.';
  env.content.listeners.input();
  assert.equal(env.diagnostics.open, true);
  assert.doesNotMatch(widgetTemplate, /name="EntryKind"/);
  assert.match(widgetTemplate, /name="StepsToReproduce"[^>]*maxlength="10000"/);
  assert.doesNotMatch(widgetTemplate, /name="StepsToReproduce"[^>]*required/);
});

test('Kostenschätzungsformular erscheint nur für Berechtigte bei der Kategorie „Wartet auf Freigabe“', () => {
  const env = createEnvironment();

  assert.equal(env.estimateSection.hidden, true, 'Backlog zeigt das Schätzungsformular nicht.');
  assert.equal(env.estimateDuration.disabled, true);
  env.createCategory.selectedOptions = [{ dataset: { systemKey: 'estimate_approved' } }];
  env.createCategory.listeners.change();
  assert.equal(env.estimateSection.hidden, true, 'Auch ein anderer Schätzungsstatus zeigt kein Eingabeformular.');
  env.createCategory.selectedOptions = [{ dataset: { systemKey: 'estimate_pending' } }];
  env.createCategory.listeners.change();
  assert.equal(env.estimateSection.hidden, false);
  assert.equal(env.estimateDuration.disabled, false);
  assert.equal(env.estimateNote.disabled, false);
  env.createCategory.selectedOptions = [{ dataset: { systemKey: 'backlog' } }];
  env.createCategory.listeners.change();
  assert.equal(env.estimateSection.hidden, true, 'Beim Wechsel zurück aus der Freigabekategorie wird das Formular wieder verborgen.');
  assert.equal(env.estimateDuration.disabled, true);
  assert.equal(env.estimateNote.disabled, true);

  env.widget.dataset.canManageEstimate = '0';
  env.createCategory.selectedOptions = [{ dataset: { systemKey: 'estimate_pending' } }];
  env.createCategory.listeners.change();
  assert.equal(env.estimateSection.hidden, true, 'Ein nicht berechtigter CMS-Admin kann es auch bei passender Kategorie nicht einblenden.');
  assert.equal(env.estimateDuration.disabled, true);
  assert.equal(env.estimateNote.disabled, true);
});

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

test('speichert den Klickpunkt relativ zum ausgewählten Seitenelement', () => {
  const env = createEnvironment();
  const target = new TestElement('main', { id: 'content' });
  target.getBoundingClientRect = () => ({ left: 100, top: 50, width: 200, height: 100 });
  env.startButton.listeners.click();
  const selectionClick = {
    target,
    clientX: 150,
    clientY: 125,
    preventDefault() {},
    stopPropagation() {},
  };
  env.documentListeners.click(selectionClick);

  assert.equal(selectionClick.mashaFeedlyTargetSelectionHandled, true, 'der Tour-Schutz erkennt genau den Klick, der das Formular öffnet');
  assert.equal(env.form.fields['[name="ElementPositionX"]'].value, '0.25000');
  assert.equal(env.form.fields['[name="ElementPositionY"]'].value, '0.75000');
});

test('erfasst die Klickstelle vor einer Layoutänderung beim Beenden der Auswahl', () => {
  const env = createEnvironment();
  const target = new TestElement('main', { id: 'content' });
  target.getBoundingClientRect = () => ({
    left: env.body.classList.contains('kw-masha-feedly-is-selecting') ? 100 : 300,
    top: 50, width: 200, height: 100,
  });
  env.startButton.listeners.click();
  env.documentListeners.click({ target, clientX: 150, clientY: 125, preventDefault() {}, stopPropagation() {} });
  assert.equal(env.form.fields['[name="ElementPositionX"]'].value, '0.25000');
  assert.equal(env.form.fields['[name="ElementPositionY"]'].value, '0.75000');
});

test('unterscheidet identische Bausteine auch oberhalb der früheren fünf Ebenen', () => {
  const env = createEnvironment();
  const content = new TestElement('main', { id: 'content' });
  const targets = [];
  for (const text of ['agnen', 'Design']) {
    const section = new TestElement('div');
    content.append(section);
    let parent = section;
    for (const tag of ['div', 'a', 'div', 'h2', 'span']) {
      const child = new TestElement(tag);
      parent.append(child);
      parent = child;
    }
    const target = new TestElement('span', { text });
    target.classList.add('animated');
    target.classList.add('kw-masha-feedly-selected-target');
    parent.append(target);
    targets.push(target);
  }
  const selector = env.window.KWMashaFeedlyEnvironment.getElementSelector;
  assert.equal(selector(targets[0]), '#content > div:nth-of-type(1) > div > a > div > h2 > span > span');
  assert.equal(selector(targets[1]), '#content > div:nth-of-type(2) > div > a > div > h2 > span > span');
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

test('meldet bestätigtes Erstellen auch bei einem Fehler in der Erfolgsanzeige als gespeichert', async () => {
  const env = createEnvironment();
  env.startButton.listeners.click();
  env.documentListeners.click({ target: new TestElement('main', { id: 'main' }), preventDefault() {}, stopPropagation() {} });
  env.column.append = () => { throw new Error('Bestätigung konnte nicht dargestellt werden.'); };

  await env.form.listeners.submit({ preventDefault() {} });

  assert.equal(env.status.textContent, 'Gespeichert. Die Anzeige konnte nicht aktualisiert werden. Bitte lade die Meldungsliste neu.');
  assert.equal(env.submit.disabled, false);
  assert.equal(env.calls.fetch[0], '/__masha-feedly/createEntry');
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
    json: async () => ({ success: false, message: 'Diese Aktion ist für dein Benutzerkonto nicht freigeschaltet. Wende dich an die Person, die Masha:Feedly betreut.' }),
  }));
  env.startButton.listeners.click();
  env.documentListeners.click({ target: new TestElement('main', { id: 'main' }), preventDefault() {}, stopPropagation() {} });
  await env.form.listeners.submit({ preventDefault() {} });
  assert.equal(env.status.textContent, 'Diese Aktion ist für dein Benutzerkonto nicht freigeschaltet. Wende dich an die Person, die Masha:Feedly betreut.');
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
  assert.equal(typeof env.content.listeners.input, 'function', 'Die Beschreibung erkennt Fehlerbegriffe automatisch.');
  assert.doesNotMatch(source, /data-masha-feedly-similar|data-similar-url/);
  const widgetTemplate = fs.readFileSync(path.resolve(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  assert.doesNotMatch(widgetTemplate, /data-masha-feedly-similar|data-similar-url|kw-masha-feedly__similar/);
});

test('ausgeliefertes JavaScript entspricht der getesteten Quelldatei', () => {
  assert.equal(compiledSource, source);
});
