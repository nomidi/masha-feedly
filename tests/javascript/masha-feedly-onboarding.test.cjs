const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../client/src/js/masha-feedly-onboarding.js'), 'utf8');
const dist = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly-onboarding.js'), 'utf8');

class Element {
  constructor(dataset = {}, selector = '') {
    this.dataset = dataset; this.selector = selector; this.hidden = true; this.listeners = {}; this.textContent = ''; this.disabled = false;
    this.classes = new Set();
    this.classList = {
      add: (...names) => names.forEach((name) => this.classes.add(name)),
      remove: (...names) => names.forEach((name) => this.classes.delete(name)),
      contains: (name) => this.classes.has(name),
    };
  }
  addEventListener(name, callback) { this.listeners[name] = callback; }
  click() { return this.listeners.click?.(); }
  querySelector() { return null; }
  matches(selector) { return this.selector === selector; }
  closest(selector) { return this.matches(selector) ? this : null; }
  setAttribute() {}
}

function setup(enabled = '1', fetchResult = { ok: true, json: async () => ({ success: true }) }) {
  const nodes = new Map();
  for (const selector of [
    '[data-masha-feedly-onboarding-welcome]', '[data-masha-feedly-tour-start]', '[data-masha-feedly-tour-skip]',
    '[data-masha-feedly-onboarding-tip]', '[data-masha-feedly-onboarding-text]', '[data-masha-feedly-selection-message]',
    '[data-masha-feedly-onboarding-escape-hint]',
    '[data-masha-feedly-tour-cancel]', '[data-masha-feedly-cancel-selection]',
    '[data-masha-feedly-restart-onboarding]', '[data-masha-feedly-restart-status]', '[data-masha-feedly-help-modal]',
    '[data-masha-feedly-modal]', '[data-masha-feedly-entry-form]', '[data-masha-feedly-onboarding-thanks]', '[data-masha-feedly-thanks-close]',
    '[data-masha-feedly-comment-form]', '[data-masha-feedly-edit-form]', '[data-masha-feedly-close-edit]',
  ]) nodes.set(selector, new Element({}, selector));
  const widget = new Element({ onboardingEnabled: enabled, onboardingUrl: '/complete', onboardingRestartUrl: '/restart', securityId: 'csrf' });
  widget.getAttribute = (name) => name === 'data-panel-open' ? (widget.panelOpen ? 'true' : 'false') : null;
  const scopedControls = [
    ['[data-masha-feedly-tour-cancel]', null],
    ['comment textarea', '[data-masha-feedly-comment-form]'],
    ['comment submit', '[data-masha-feedly-comment-form]'],
    ['status select', '[data-masha-feedly-edit-form]'],
    ['assignee checkbox', '[data-masha-feedly-edit-form]'],
    ['panel help', null],
  ].map(([name, parent]) => {
    const control = new Element({}, name);
    if (name === 'entry submit') control.insideWidget = true;
    control.closest = (selector) => parent && selector === parent ? control : null;
    return control;
  });
  const entrySubmit = new Element({}, 'entry submit');
  entrySubmit.insideWidget = true;
  entrySubmit.form = nodes.get('[data-masha-feedly-entry-form]');
  scopedControls.push(entrySubmit);
  widget.querySelectorAll = () => scopedControls;
  widget.querySelector = (selector) => nodes.get(selector) || (selector === '.kw-masha-feedly__toggle' ? (nodes.set(selector, new Element({}, selector)), nodes.get(selector)) : null);
  widget.contains = (element) => element?.insideWidget === true;
  const documentListeners = {};
  const appended = [];
  const document = {
    addEventListener(name, callback) { documentListeners[name] = callback; },
    querySelector(selector) { return selector === '[data-kw-masha-feedly]' ? widget : null; },
    createElement() { return new Element(); },
    body: { append(element) { appended.push(element); } },
    dispatchEvent(event) { documentListeners[event.type]?.(event); },
  };
  const requests = [];
  class FormDataStub { set(name, value) { this[name] = value; } }
  const timers = new Map();
  let timerID = 0;
  const window = {
    KWMashaFeedlyTranslate: (key) => key,
    setTimeout(callback) { const id = ++timerID; timers.set(id, callback); return id; },
    clearTimeout(id) { timers.delete(id); },
  };
  vm.runInNewContext(source, { document, window, FormData: FormDataStub, fetch: (...args) => { requests.push(args); return fetchResult instanceof Error ? Promise.reject(fetchResult) : Promise.resolve(fetchResult); } });
  documentListeners.DOMContentLoaded();
  return { nodes, document, requests, appended, timers, documentListeners, scopedControls };
}

test('führt beim ersten Besuch durch Plus, Bereichsauswahl und Formular bis zum erfolgreichen Speichern', () => {
  const { nodes, document, requests, appended, documentListeners, scopedControls } = setup();
  const welcome = nodes.get('[data-masha-feedly-onboarding-welcome]');
  const tip = nodes.get('[data-masha-feedly-onboarding-tip]');
  assert.equal(welcome.hidden, false);
  nodes.get('[data-masha-feedly-tour-start]').click();
  assert.equal(nodes.get('.kw-masha-feedly__toggle').classList.contains('is-onboarding-target'), true);
  assert.equal(welcome.hidden, true);
  assert.equal(tip.hidden, false);
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_ICON');
  assert.equal(appended[0].className, 'kw-masha-feedly__onboarding-shade');
  assert.equal(appended[0].hidden, false);
  document.dispatchEvent({ type: 'kw-masha-feedly:opened' });
  nodes.get('.kw-masha-feedly__toggle').click();
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_PLUS');
  assert.equal(requests.length, 0, 'das Öffnen des Symbols darf die Einführung nicht abschließen');
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-selection-started' });
  assert.equal(tip.hidden, true);
  assert.equal(nodes.get('[data-masha-feedly-selection-message]').textContent, 'TOUR_SELECTION_TARGET');
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-target-selected' });
  assert.equal(tip.hidden, false);
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_FORM');
  assert.equal(scopedControls[6].disabled, false, 'der Submit-Button im Dialog-Footer bleibt trotz form="…" bedienbar');
  let submitBlocked = false;
  documentListeners.click({ target: scopedControls[6], preventDefault() { submitBlocked = true; }, stopImmediatePropagation() {} });
  assert.equal(submitBlocked, false, 'der zugeordnete Submit-Button wird von der Tour nicht blockiert');
  const submitButtonChild = new Element({}, 'entry submit label');
  submitButtonChild.closest = (selector) => selector === 'button, input, select, textarea' ? scopedControls[6] : null;
  documentListeners.click({ target: submitButtonChild, preventDefault() { submitBlocked = true; }, stopImmediatePropagation() {} });
  assert.equal(submitBlocked, false, 'Klicks auf Text oder Icon im zugeordneten Submit-Button werden ebenfalls zugelassen');
  let backdropClickPrevented = false;
  documentListeners.click({
    target: nodes.get('[data-masha-feedly-modal]'),
    preventDefault() { backdropClickPrevented = true; },
    stopImmediatePropagation() {},
  });
  assert.equal(backdropClickPrevented, true, 'ein Klick auf den Dialoghintergrund darf das Bugfenster nicht schließen');
  assert.equal(requests.length, 0);
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-saved' });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_VIEW_ENTRIES');
  assert.equal(requests.length, 0);
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-list-opened' });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_OPEN_ENTRY');
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-opened' });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_COMMENT_ENTRY');
  assert.equal(scopedControls[1].disabled, false, 'Kommentarfeld ist im Kommentarschritt aktiv');
  assert.equal(scopedControls[3].disabled, true, 'Statusauswahl ist im Kommentarschritt nativ gesperrt');
  const commentForm = nodes.get('[data-masha-feedly-comment-form]');
  let blockedComment = false;
  const commentTarget = new Element();
  commentTarget.insideWidget = true;
  commentTarget.closest = (selector) => selector === '[data-masha-feedly-comment-form]' ? commentForm : null;
  documentListeners.click({ target: commentTarget, preventDefault() { blockedComment = true; }, stopImmediatePropagation() {} });
  assert.equal(blockedComment, false);
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-comment-saved' });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_MANAGE_ENTRY');
  assert.equal(scopedControls[1].disabled, true, 'Kommentarfeld ist im Bearbeitungsschritt nativ gesperrt');
  assert.equal(scopedControls[3].disabled, false, 'Statusauswahl ist im Bearbeitungsschritt aktiv');
  assert.equal(scopedControls[4].disabled, false, 'Zuständigkeit bleibt im Bearbeitungsschritt bedienbar');
  let commentBlockedDuringEdit = false;
  documentListeners.click({ target: commentTarget, preventDefault() { commentBlockedDuringEdit = true; }, stopImmediatePropagation() {} });
  assert.equal(commentBlockedDuringEdit, true, 'Kommentare dürfen nicht vom geführten Status-/Zuweisungsschritt ablenken');
  let editFormAllowed = true;
  documentListeners.click({ target: nodes.get('[data-masha-feedly-edit-form]'), preventDefault() { editFormAllowed = false; }, stopImmediatePropagation() {} });
  assert.equal(editFormAllowed, true, 'Status- und Zuweisungsfelder bleiben bedienbar');
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-updated' });
  assert.equal(tip.hidden, true);
  assert.equal(nodes.get('[data-masha-feedly-onboarding-thanks]').hidden, false);
  assert.equal(requests.length, 1);
  assert.equal(requests[0][0], '/complete');
  assert.equal(requests[0][1].body.SecurityID, 'csrf');
});

test('öffnet beim normalen Speichern eines Eintrags keine Einführung und meldet keinen Tourabschluss', () => {
  const { nodes, document, requests } = setup('0');
  const thanks = nodes.get('[data-masha-feedly-onboarding-thanks]');
  assert.equal(thanks.hidden, true);

  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-updated' });

  assert.equal(thanks.hidden, true);
  assert.equal(requests.length, 0);
});

test('startet ohne serverseitige Freigabe keine Einführung und normale Aktionen bleiben ohne Tour', () => {
  const { nodes, document, requests } = setup('0');
  const welcome = nodes.get('[data-masha-feedly-onboarding-welcome]');
  const tip = nodes.get('[data-masha-feedly-onboarding-tip]');
  assert.equal(welcome.hidden, true, 'nicht ausdrücklich freigegebene Mitglieder sehen keine Begrüßung');
  assert.equal(tip.hidden, true);

  document.dispatchEvent({ type: 'kw-masha-feedly:opened' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-saved' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-updated' });

  assert.equal(tip.hidden, true);
  assert.equal(requests.length, 0, 'normale Widget-Aktionen starten oder speichern keine Tour');
});

test('blockiert Ablenkung mit rotem Feedback und lässt den markierten Schritt zu', () => {
  const { nodes, document, timers, appended, documentListeners } = setup();
  nodes.get('[data-masha-feedly-tour-start]').click();
  const wrongTarget = new Element();
  wrongTarget.insideWidget = true;
  let prevented = false;
  let stopped = false;
  documentListeners.click({
    target: wrongTarget,
    preventDefault() { prevented = true; },
    stopImmediatePropagation() { stopped = true; },
  });
  assert.equal(prevented, true);
  assert.equal(stopped, true);
  assert.equal(nodes.get('[data-masha-feedly-onboarding-tip]').classList.contains('is-feedback'), true);
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_BLOCKED_ICON');
  assert.equal(appended[0].hidden, false);
  assert.equal(timers.size, 1);
  const launcher = nodes.get('.kw-masha-feedly__toggle');
  launcher.insideWidget = true;
  let allowedClickPrevented = false;
  documentListeners.click({
    target: launcher,
    preventDefault() { allowedClickPrevented = true; },
    stopImmediatePropagation() {},
  });
  assert.equal(allowedClickPrevented, false);
  let keyPrevented = false;
  let keyStopped = false;
  documentListeners.keydown({
    key: 'ArrowDown',
    target: wrongTarget,
    preventDefault() { keyPrevented = true; },
    stopImmediatePropagation() { keyStopped = true; },
  });
  assert.equal(keyPrevented, true);
  assert.equal(keyStopped, true);
});

test('Begrüßung, Bereichsauswahl und Formular lassen sich abbrechen', () => {
  const { nodes, document, requests } = setup();
  nodes.get('[data-masha-feedly-tour-skip]').click();
  assert.equal(requests.length, 1);
  assert.equal(nodes.get('[data-masha-feedly-onboarding-thanks]').hidden, true);

  const selection = setup();
  selection.nodes.get('[data-masha-feedly-tour-start]').click();
  selection.document.dispatchEvent({ type: 'kw-masha-feedly:opened' });
  selection.document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-selection-started' });
  selection.nodes.get('[data-masha-feedly-cancel-selection]').click();
  selection.document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-selection-cancelled' });
  assert.equal(selection.requests.length, 1);

  const form = setup();
  form.nodes.get('[data-masha-feedly-tour-start]').click();
  form.document.dispatchEvent({ type: 'kw-masha-feedly:opened' });
  form.document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-selection-started' });
  form.document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-target-selected' });
  form.document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-form-closed' });
  assert.equal(form.requests.length, 1);
});

test('Tastatur kann die Einführung im Begrüßungsdialog und bei der Bereichsauswahl abbrechen', () => {
  const welcomeTour = setup();
  let prevented = false;
  welcomeTour.documentListeners.keydown({ key: 'Escape', preventDefault() { prevented = true; } });
  assert.equal(prevented, true);
  assert.equal(welcomeTour.nodes.get('[data-masha-feedly-onboarding-welcome]').hidden, true);

  const selectionTour = setup();
  selectionTour.nodes.get('[data-masha-feedly-tour-start]').click();
  selectionTour.document.dispatchEvent({ type: 'kw-masha-feedly:opened' });
  selectionTour.document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-selection-started' });
  let tabPrevented = false;
  selectionTour.documentListeners.keydown({ key: 'Tab', target: selectionTour.nodes.get('[data-masha-feedly-cancel-selection]'), preventDefault() { tabPrevented = true; } });
  assert.equal(tabPrevented, false, 'Tab bleibt während der Bereichsauswahl nutzbar');
  let cancelKeyPrevented = false;
  selectionTour.documentListeners.keydown({ key: 'Enter', target: selectionTour.nodes.get('[data-masha-feedly-cancel-selection]'), preventDefault() { cancelKeyPrevented = true; } });
  assert.equal(cancelKeyPrevented, false, 'Enter auf Abbrechen darf nicht vom Tour-Schutz blockiert werden');
  let unrelatedKeyPrevented = false;
  selectionTour.documentListeners.keydown({ key: 'ArrowDown', target: new Element(), preventDefault() { unrelatedKeyPrevented = true; }, stopImmediatePropagation() {} });
  assert.equal(unrelatedKeyPrevented, true, 'andere Tastaturaktionen bleiben während der Führung gesperrt');
});

test('Escape beendet die Einführung auch in Schritt 8 und gibt die Felder wieder frei', () => {
  const tour = setup();
  const { nodes, document, requests, documentListeners, scopedControls } = tour;
  nodes.get('[data-masha-feedly-tour-start]').click();
  document.dispatchEvent({ type: 'kw-masha-feedly:opened' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-selection-started' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-target-selected' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-saved' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-list-opened' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-opened' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-comment-saved' });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_MANAGE_ENTRY');
  assert.equal(scopedControls[3].disabled, false, 'Statusauswahl ist vor dem Abbruch nutzbar');

  let prevented = false;
  let stopped = false;
  documentListeners.keydown({
    key: 'Escape',
    target: scopedControls[3],
    preventDefault() { prevented = true; },
    stopImmediatePropagation() { stopped = true; },
  });

  assert.equal(prevented, true, 'Escape schließt nicht zusätzlich den Eintragsdialog');
  assert.equal(stopped, true, 'Escape wird nicht an andere Dialog-Handler weitergereicht');
  assert.equal(nodes.get('[data-masha-feedly-onboarding-tip]').hidden, true);
  assert.equal(requests.length, 1, 'Abbruch speichert den Onboarding-Abschluss');
  assert.equal(scopedControls[3].disabled, false, 'Statusauswahl bleibt nach dem Abbruch bedienbar');
  assert.equal(scopedControls[1].disabled, false, 'Kommentarfeld bleibt nach dem Abbruch bedienbar');
});

test('Anleitungstexte nennen Priorität, Anhänge, Neuigkeiten, Verknüpfungen und Profileinstellungen', () => {
  const translations = fs.readFileSync(path.resolve(__dirname, '../../lang/de.yml'), 'utf8');
  for (const feature of ['Priorität', 'Dateien anhängen', 'Neuigkeiten-Symbol', 'Einträge verknüpfen', 'E-Mail-Benachrichtigungen']) {
    assert.ok(translations.includes(feature), `Onboarding/Hilfe sollte ${feature} erklären`);
  }
});

test('Escape-Abbruch ist als sichtbarer Hinweis in Deutsch und Englisch vorhanden', () => {
  const template = fs.readFileSync(path.resolve(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  const german = fs.readFileSync(path.resolve(__dirname, '../../lang/de.yml'), 'utf8');
  const english = fs.readFileSync(path.resolve(__dirname, '../../lang/en.yml'), 'utf8');
  assert.match(template, /data-masha-feedly-onboarding-escape-hint/);
  assert.match(template, /Translations\.TOUR_ESCAPE_HINT/);
  assert.match(german, /TOUR_ESCAPE_HINT: 'Tipp: Esc beendet die Einführung jederzeit\.'/);
  assert.match(english, /TOUR_ESCAPE_HINT: 'Tip: Press Esc to stop the tour at any time\.'/);
});

test('zeigt eine abgeschlossene Einführung nicht erneut an', () => {
  const { nodes, requests, document } = setup('0');
  assert.equal(nodes.get('[data-masha-feedly-onboarding-welcome]').hidden, true);
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-saved' });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-tip]').hidden, true, 'Ein normales Speichern darf keine Tour-Stufe starten.');
  assert.equal(requests.length, 0);
});

test('ein Speichern vor dem Start der Einführung darf nicht zu Schritt 5 springen', () => {
  const { nodes, document } = setup('1');
  const welcome = nodes.get('[data-masha-feedly-onboarding-welcome]');
  const tip = nodes.get('[data-masha-feedly-onboarding-tip]');
  assert.equal(welcome.hidden, false);
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-saved' });
  assert.equal(welcome.hidden, false, 'Der Willkommensdialog bleibt offen.');
  assert.equal(tip.hidden, true, 'Schritt 5 erscheint erst nach dem Speichern aus Schritt 4.');
});

test('Neustart aus dem Hilfetext speichert den Status und öffnet die Einführung', async () => {
  const { nodes, requests } = setup('0');
  const restart = nodes.get('[data-masha-feedly-restart-onboarding]');
  const help = nodes.get('[data-masha-feedly-help-modal]');
  help.hidden = false;
  await restart.click();
  assert.equal(requests[0][0], '/restart');
  assert.equal(requests[0][1].body.SecurityID, 'csrf');
  assert.equal(help.hidden, true);
  assert.equal(nodes.get('[data-masha-feedly-onboarding-welcome]').hidden, false);
});

test('startet bei geöffnetem Widget mit sichtbarem Icon-Schritt', () => {
  const { nodes, document } = setup('0');
  const widget = document.querySelector('[data-kw-masha-feedly]');
  widget.panelOpen = true;
  let toggleClicks = 0;
  widget.querySelector('.kw-masha-feedly__toggle').click = () => { toggleClicks += 1; widget.panelOpen = false; };
  nodes.get('[data-masha-feedly-tour-start]').click();
  assert.equal(toggleClicks, 1);
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_ICON');
});

test('zeigt einen Fehler, wenn der Neustart-Button den Server nicht erreicht', async () => {
  const { nodes, requests } = setup('0', new Error('offline'));
  const restart = nodes.get('[data-masha-feedly-restart-onboarding]');
  nodes.get('[data-masha-feedly-help-modal]').hidden = false;
  await restart.click();
  assert.equal(requests.length, 1, 'der echte Button-Handler muss den Neustart-Endpunkt aufrufen');
  assert.equal(nodes.get('[data-masha-feedly-help-modal]').hidden, false, 'die Hilfe bleibt für den erneuten Versuch geöffnet');
  assert.equal(nodes.get('[data-masha-feedly-onboarding-welcome]').hidden, true);
  assert.equal(nodes.get('[data-masha-feedly-restart-status]').textContent, 'TOUR_RESTART_ERROR');
  assert.equal(restart.disabled, false);
});

test('liefert dieselbe Einführungslogik aus wie getestet wird', () => {
  assert.equal(dist, source);
});
