const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../client/src/js/masha-feedly-onboarding.js'), 'utf8');
const dist = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly-onboarding.js'), 'utf8');
const e2eOnboardingSource = fs.readFileSync(path.resolve(__dirname, '../e2e/onboarding.test.cjs'), 'utf8');

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
  addEventListener(name, callback) {
    const previous = this.listeners[name];
    this.listeners[name] = previous ? (event) => { previous(event); return callback(event); } : callback;
  }
  click() { return this.listeners.click?.(); }
  querySelector() { return null; }
  querySelectorAll() { return []; }
  removeAttribute(name) { this.removedAttributes = [...(this.removedAttributes || []), name]; }
  matches(selector) { return this.selector === selector; }
  closest(selector) { return this.matches(selector) ? this : null; }
  setAttribute(name, value) { this.attributes = { ...(this.attributes || {}), [name]: value }; }
}

function setup(enabled = '1', fetchResult = { ok: true, json: async () => ({ success: true }) }) {
  const nodes = new Map();
  for (const selector of [
    '[data-masha-feedly-onboarding-welcome]', '[data-masha-feedly-tour-start]', '[data-masha-feedly-tour-skip]', '[data-masha-feedly-tour-end]',
    '[data-masha-feedly-onboarding-tip]', '[data-masha-feedly-onboarding-text]', '[data-masha-feedly-selection-message]',
    '[data-masha-feedly-onboarding-escape-hint]',
    '[data-masha-feedly-tour-cancel]', '[data-masha-feedly-cancel-selection]',
    '[data-masha-feedly-restart-onboarding]', '[data-masha-feedly-restart-status]', '[data-masha-feedly-help-modal]',
    '[data-masha-feedly-modal]', '[data-masha-feedly-entry-form]', '[data-masha-feedly-onboarding-thanks]', '[data-masha-feedly-thanks-close]',
    '[data-masha-feedly-entry-form] [name="Content"]', '[type="submit"][form="kw-masha-feedly-create-form"]',
    '[data-masha-feedly-comment-form] textarea',
    '[data-masha-feedly-comment-form]', '[data-masha-feedly-edit-form]', '[data-masha-feedly-close-edit]',
    '[data-masha-feedly-edit-form] [data-masha-feedly-edit-category]',
    '[data-masha-feedly-edit-form] [name="PriorityID"]',
    '[data-masha-feedly-edit-form] .kw-masha-feedly__assignees',
    '[data-masha-feedly-edit-form] [type="submit"]',
  ]) nodes.set(selector, new Element({}, selector));
  const preferencesForm = new Element({}, 'profile preferences form');
  preferencesForm.scrollTop = 420;
  nodes.set('[data-masha-feedly-profile-preferences]', preferencesForm);
  const thanksDialog = new Element({}, 'thanks dialog');
  thanksDialog.scrollTop = 420;
  const thanksHeading = new Element({}, '#kw-masha-feedly-thanks-title');
  const themePreviewStatus = new Element({}, '[data-masha-feedly-theme-preview-status]');
  thanksHeading.focus = () => { thanksHeading.focused = true; };
  const colorDetails = new Element({}, '[data-masha-feedly-onboarding-colors]');
  colorDetails.open = true;
  const emailDetails = new Element({}, '.kw-masha-feedly__onboarding-email-details');
  emailDetails.open = true;
  const avatarDialog = new Element({}, '[data-masha-feedly-avatar-icon-dialog]');
  const thanks = nodes.get('[data-masha-feedly-onboarding-thanks]');
  thanks.querySelector = (selector) => ({
    '[role="dialog"]': thanksDialog,
    '[data-masha-feedly-theme-preview-status]': themePreviewStatus,
  })[selector] || null;
  thanks.contains = (element) => element?.insideThanks === true;
  thanksDialog.querySelector = (selector) => ({
    '[data-masha-feedly-onboarding-colors]': colorDetails,
    '.kw-masha-feedly__onboarding-email-details': emailDetails,
    '[data-masha-feedly-avatar-icon-dialog]': avatarDialog,
    '#kw-masha-feedly-thanks-title': thanksHeading,
  })[selector] || null;
  nodes.set('thanksDialog', thanksDialog);
  nodes.set('thanksHeading', thanksHeading);
  nodes.set('thanksColorDetails', colorDetails);
  nodes.set('thanksEmailDetails', emailDetails);
  nodes.set('thanksAvatarDialog', avatarDialog);
  const widget = new Element({ onboardingEnabled: enabled, onboardingUrl: '/complete', onboardingRestartUrl: '/restart', securityId: 'csrf' });
  const commentTextarea = nodes.get('[data-masha-feedly-comment-form] textarea');
  commentTextarea.scrollIntoView = (options) => { commentTextarea.scrollOptions = options; };
  const manageStatusField = nodes.get('[data-masha-feedly-edit-form] [data-masha-feedly-edit-category]');
  manageStatusField.scrollIntoView = (options) => { manageStatusField.scrollOptions = options; };
  nodes.set('[data-masha-feedly-edit-category]', manageStatusField);
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
  nodes.set('[type="submit"][form="kw-masha-feedly-create-form"]', entrySubmit);
  const entryDescription = nodes.get('[data-masha-feedly-entry-form] [name="Content"]');
  entryDescription.value = '';
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
  nodes.set('themePreviewStatus', themePreviewStatus);
  return { nodes, document, window, requests, appended, timers, documentListeners, scopedControls, entryDescription, entrySubmit, commentTextarea, manageStatusField };
}

test('führt beim ersten Besuch durch Plus, Bereichsauswahl und Formular bis zum erfolgreichen Speichern', () => {
  const { nodes, document, requests, appended, documentListeners, scopedControls, entryDescription, entrySubmit, timers, commentTextarea, manageStatusField } = setup();
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
  assert.equal(entryDescription.classList.contains('is-onboarding-target'), true, 'zuerst pulsiert das Beschreibungsfeld');
  entryDescription.value = 'Der Hinweistext';
  entryDescription.listeners.input();
  assert.equal(entryDescription.classList.contains('is-onboarding-target'), false);
  assert.equal(entrySubmit.classList.contains('is-onboarding-target'), true, 'nach der Eingabe pulsiert der Speichern-Button');
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
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-saved', detail: { entryID: 777 } });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_VIEW_ENTRIES');
  assert.equal(requests.length, 0);
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-list-opened' });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_OPEN_ENTRY');
  const newEntryCard = new Element({ entryId: '777' }, '.kw-masha-feedly__entry-card');
  newEntryCard.insideWidget = true;
  const otherEntryCard = new Element({ entryId: '776' }, '.kw-masha-feedly__entry-card');
  otherEntryCard.insideWidget = true;
  nodes.set('[data-entry-id="777"]', newEntryCard);
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-list-rendered' });
  assert.equal(newEntryCard.classList.contains('is-onboarding-target'), true, 'nur die gerade erstellte Meldung wird markiert');
  let wrongEntryBlocked = false;
  documentListeners.click({ target: otherEntryCard, preventDefault() { wrongEntryBlocked = true; }, stopImmediatePropagation() {} });
  assert.equal(wrongEntryBlocked, true, 'andere Meldungen bleiben in Schritt 6 gesperrt');
  timers.forEach((callback) => callback());
  timers.clear();
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-opened', detail: { entryID: 776 } });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_OPEN_ENTRY');
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-opened', detail: { entryID: 777 } });
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_COMMENT_ENTRY');
  assert.equal(commentTextarea.scrollOptions.behavior, 'smooth', 'das Scrollen zum Kommentarfeld ist weich');
  assert.equal(commentTextarea.scrollOptions.block, 'center', 'das Kommentarfeld wird mittig sichtbar');
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
  assert.equal(manageStatusField.classList.contains('is-onboarding-target'), true, 'der Status wird als bearbeitbares Feld markiert');
  assert.equal(nodes.get('[data-masha-feedly-edit-form] [name="PriorityID"]').classList.contains('is-onboarding-target'), true, 'die Priorität wird markiert');
  assert.equal(nodes.get('[data-masha-feedly-edit-form] .kw-masha-feedly__assignees').classList.contains('is-onboarding-target'), true, 'die Zuständigkeiten werden markiert');
  assert.equal(nodes.get('[data-masha-feedly-edit-form] [type="submit"]').classList.contains('is-onboarding-target'), true, 'der Speichern-Button wird markiert');
  assert.equal(manageStatusField.scrollOptions.behavior, 'smooth');
  assert.equal(manageStatusField.scrollOptions.block, 'center');
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
  assert.equal(nodes.get('[data-masha-feedly-profile-preferences]').scrollTop, 0, 'der scrollbare Einstellungsbereich startet jedes Mal am Anfang');
  assert.equal(nodes.get('thanksHeading').focused, true, 'der Fokus landet oben auf der Überschrift statt unten im Footer');
  assert.equal(nodes.get('thanksColorDetails').open, true, 'die Farbpalette bleibt beim Öffnen sichtbar');
  assert.equal(Boolean(nodes.get('thanksColorDetails').removedAttributes?.includes('open')), false);
  assert.equal(nodes.get('thanksEmailDetails').open, true, 'die E-Mail-Auswahl bleibt geöffnet, damit die Einstellungen sichtbar sind');
  assert.equal(Boolean(nodes.get('thanksEmailDetails').removedAttributes?.includes('open')), false);
  assert.equal(nodes.get('thanksAvatarDialog').attributes.hidden, '', 'ein zuvor geöffnetes Symbolfenster wird geschlossen');
  assert.equal(requests.length, 1);
  assert.equal(requests[0][0], '/complete');
  assert.equal(requests[0][1].body.SecurityID, 'csrf');
});

test('zeigt Tour-Fehler nur bei gesperrten Klicks und nicht beim Öffnen des Formulars', () => {
  const { nodes, document, documentListeners } = setup();
  nodes.get('[data-masha-feedly-tour-start]').click();
  document.dispatchEvent({ type: 'kw-masha-feedly:opened' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-selection-started' });

  let blocked = false;
  const unrelatedButton = new Element({}, 'panel help');
  unrelatedButton.insideWidget = true;
  documentListeners.click({
    target: unrelatedButton,
    preventDefault() { blocked = true; },
    stopImmediatePropagation() {},
  });
  assert.equal(blocked, true, 'ein anderer Widget-Button bleibt während der Bereichsauswahl gesperrt');
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_BLOCKED_TARGET');

  const selectionClick = { mashaFeedlyTargetSelectionHandled: true, preventDefault() { blocked = true; }, stopImmediatePropagation() {} };
  nodes.get('[data-masha-feedly-modal]').hidden = false;
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-target-selected' });
  blocked = false;
  documentListeners.click(selectionClick);
  assert.equal(blocked, false, 'der Klick, der das Formular öffnet, löst keinen Tour-Fehler aus');
  assert.equal(nodes.get('[data-masha-feedly-onboarding-text]').textContent, 'TOUR_STEP_FORM');

  blocked = false;
  documentListeners.click({
    target: unrelatedButton,
    preventDefault() { blocked = true; },
    stopImmediatePropagation() {},
  });
  assert.equal(blocked, true, 'ein nicht zum Formular gehörender Button bleibt auch in Schritt 4 gesperrt');
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
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-saved', detail: { entryID: 777 } });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-list-opened' });
  document.dispatchEvent({ type: 'kw-masha-feedly:onboarding-entry-opened', detail: { entryID: 777 } });
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
  for (const feature of ['Priorität', 'Dateien anhängen', 'Neuigkeiten', 'Verknüpfung', 'E-Mail-Benachrichtigungen']) {
    assert.ok(translations.includes(feature), `Onboarding/Hilfe sollte ${feature} erklären`);
  }
});

test('beschriftet das Beschreibungsfeld mit einer klaren Eingabeaufforderung', () => {
  const template = fs.readFileSync(path.resolve(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  const translations = fs.readFileSync(path.resolve(__dirname, '../../lang/de.yml'), 'utf8');
  const widgetExtension = fs.readFileSync(path.resolve(__dirname, '../../src/Extension/MashaFeedlyWidgetExtension.php'), 'utf8');
  assert.match(template, /Translations\.ENTRY_DESCRIPTION_LABEL/);
  assert.match(translations, /ENTRY_DESCRIPTION_LABEL: 'Beschreibung'/);
  assert.match(translations, /ENTRY_DESCRIPTION_PLACEHOLDER_DU: 'Beschreibe kurz, was falsch ist oder was du dir wünschst/);
  assert.match(translations, /TOUR_STEP_FORM: 'Schritt 4 von 8: Klicke in das große Feld „Beschreibung“/);
  assert.match(widgetExtension, /TOUR_STEP_FORM' => 'Schritt 4 von 8: Klicke in das große Feld „Beschreibung“/);
  assert.match(translations, /TOUR_STEP_VIEW_ENTRIES: 'Schritt 5 von 8: Super, du hast eine Meldung erstellt! Klicke jetzt auf das Blatt-Symbol mit der Zahl\. Damit siehst du offene Meldungen nur auf dieser Seite\. Der Globus darüber zeigt Meldungen von der ganzen Website/);
  assert.match(translations, /TOUR_STEP_OPEN_ENTRY: 'Schritt 6 von 8: Deine neue Meldung ist bunt umrandet\. Klicke genau auf diese Meldung, um sie zu öffnen\.'/);
  assert.match(translations, /TOUR_STEP_MANAGE_ENTRY: 'Schritt 8 von 8: Oben kannst du Status und Priorität ändern\. Darunter kannst du verantwortliche Personen auswählen\. Klicke danach unbedingt auf „Änderungen speichern“/);
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

test('verwendet für den Onboarding-E2E-Test ersatzweise das vorhandene Creator-Testkonto', () => {
  assert.match(e2eOnboardingSource, /onboardingEmail:[\s\S]*?MASHA_FEEDLY_E2E_CREATOR_EMAIL/);
  assert.match(e2eOnboardingSource, /onboardingPassword:[\s\S]*?MASHA_FEEDLY_E2E_CREATOR_PASSWORD/);
});


test('beschriftet den Ausstieg in Begrüßung, Tour und Abschluss einheitlich', () => {
  const template = fs.readFileSync(path.join(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  for (const control of ['tour-end', 'tour-cancel', 'thanks-close']) {
    assert.match(template, new RegExp('data-masha-feedly-' + control + '[^>]*>[\\s\\S]*?Translations\\.TOUR_CANCEL'));
  }
  assert.match(template, /Translations\.TOUR_CANCEL 'Einführung beenden'/);
});

test('zeigt den Profilbutton erst im Abschluss statt über der Einführung', () => {
  const template = fs.readFileSync(path.join(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  const welcome = template.match(/data-masha-feedly-onboarding-welcome[\s\S]*?<\/section>/)?.[0] || '';
  const thanks = template.match(/data-masha-feedly-onboarding-thanks[\s\S]*?\n        <\/section>/)?.[0] || '';
  assert.doesNotMatch(welcome, /data-masha-feedly-profile-link/);
  assert.match(thanks, /data-masha-feedly-profile-link[^>]*href="\$ProfileURL"/);
  assert.match(thanks, /Translations\.TOUR_THEME_SETTINGS_LINK 'Theme einstellen'/);
  assert.match(thanks, /dialog-header kw-masha-feedly__onboarding-welcome-header"><img class="kw-masha-feedly__onboarding-logo kw-masha-feedly__onboarding-logo--welcome"/);
  assert.match(e2eOnboardingSource, /onboarding-thanks[^\n]*data-masha-feedly-profile-link/);
});

test('erklärt die persönlichen Einstellungen am Ende der Einführung einfach', () => {
  const template = fs.readFileSync(path.join(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  const translations = fs.readFileSync(path.join(__dirname, '../../lang/de.yml'), 'utf8');
  const styles = fs.readFileSync(path.join(__dirname, '../../client/src/scss/masha-feedly.scss'), 'utf8');
  const thanks = template.match(/data-masha-feedly-onboarding-thanks[\s\S]*?\n        <\/section>/)?.[0] || '';
  assert.match(translations, /TOUR_THANKS_TEXT: 'Super, du hast deine erste Meldung erstellt![\s\S]*Erfolgsmeldung mit einer kurzen Danke-Animation/);
  assert.match(translations, /PROFILE_ADDRESS_DESCRIPTION: 'Lege fest, ob Masha:Feedly dich mit Du oder Sie anspricht/);
  assert.match(thanks, /Translations\.TOUR_THANKS_EFFECTS_LABEL 'Danke-Animation auswählen'/);
  assert.match(thanks, /Translations\.TOUR_THANKS_EFFECTS_HELP 'Wenn du eine Meldung als erledigt markierst/);
  assert.match(thanks, /Translations\.TOUR_THANKS_COLOR_LABEL 'Farbe deines Profilsymbols'/);
  assert.match(styles, /\.kw-masha-feedly__onboarding-copy \{ margin: 8px 0 0;/);
  assert.match(styles, /\.kw-masha-feedly__onboarding-preferences-help \{ color:/);
});

test('ordnet Profilvorschau, Farbe, Symbol, Danke-Animation und E-Mail-Auswahl verständlich', () => {
  const template = fs.readFileSync(path.join(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  const translations = fs.readFileSync(path.join(__dirname, '../../lang/de.yml'), 'utf8');
  const thanks = template.match(/data-masha-feedly-onboarding-thanks[\s\S]*?\n        <\/section>/)?.[0] || '';
  const compiledStyles = fs.readFileSync(path.join(__dirname, '../../client/dist/css/masha-feedly.css'), 'utf8');
  const order = [
    '$ProfileAvatarPreviewHTML.RAW',
    'name="MashaFeedlyAddress"',
    'data-masha-feedly-onboarding-colors',
    'TOUR_THANKS_ICON_TITLE',
    'name="MashaFeedlyTheme"',
    'PROFILE_EMAIL_SETTINGS',
  ].map((marker) => thanks.indexOf(marker));
  assert.ok(order.every((index) => index >= 0));
  assert.deepEqual(order, [...order].sort((a, b) => a - b), 'Farbe kommt vor Symbol, Vorschau, Animation und E-Mail-Auswahl');
  assert.match(thanks, /data-masha-feedly-email-master/);
  assert.match(thanks, /data-masha-feedly-email-option/);
  assert.match(thanks, /name="MashaFeedlyAddress"[\s\S]*?Website-Vorgabe/);
  assert.match(thanks, /<details class="kw-masha-feedly__onboarding-email-details" open>[\s\S]*?data-masha-feedly-email-option/);
  assert.match(thanks, /<details class="kw-masha-feedly__onboarding-effects-details" data-masha-feedly-theme-details open>/);
  assert.match(thanks, /TOUR_THANKS_FORM_INTRO/);
  assert.match(thanks, /<details class="kw-masha-feedly__onboarding-colors" data-masha-feedly-onboarding-colors open>/);
  assert.match(thanks, /id="kw-masha-feedly-thanks-title" tabindex="-1"/);
  assert.match(thanks, /ProfileEmailTestSucceeded/);
  assert.match(fs.readFileSync(path.join(__dirname, '../../client/src/js/masha-feedly-onboarding.js'), 'utf8'), /data\.set\(checkbox\.name, checkbox\.checked \? '1' : '0'\)/);
  assert.match(translations, /TOUR_THANKS_PREVIEW_TITLE: 'Deine Profilvorschau'/);
  assert.match(translations, /TOUR_THANKS_PREVIEW_HELP: 'Hier siehst du dein Symbol und deine Farbe/);
  assert.match(thanks, /data-masha-feedly-theme-details[\s\S]*?data-masha-feedly-theme-preview=/);
  assert.match(translations, /TOUR_THANKS_EMAIL_HELP: 'Ein Häkchen bedeutet: Du bekommst diese E-Mail/);
  assert.match(compiledStyles, /\.kw-masha-feedly__onboarding-email-settings\{display:grid;gap:12px/);
  assert.match(compiledStyles, /\.kw-masha-feedly__onboarding-email-details>summary\{display:grid/);
  assert.match(compiledStyles, /\.kw-masha-feedly__onboarding-profile-preview \.masha-feedly-profile-avatar-preview\{margin:4px 0 0\}/);
});

test('hält die Abschlussaktionen sichtbar, während die Profileinstellungen scrollen', () => {
  const template = fs.readFileSync(path.join(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  const styles = fs.readFileSync(path.join(__dirname, '../../client/src/scss/masha-feedly.scss'), 'utf8');
  const thanks = template.match(/data-masha-feedly-onboarding-thanks[\s\S]*?\n        <\/section>/)?.[0] || '';
  const formEnd = thanks.indexOf('</form>');
  const saveButton = thanks.indexOf('data-masha-feedly-onboarding-save');
  const footer = thanks.indexOf('<footer class="kw-masha-feedly__dialog-actions">');

  assert.match(thanks, /<form id="kw-masha-feedly-onboarding-preferences"[^>]*data-masha-feedly-profile-preferences/);
  assert.ok(formEnd >= 0 && footer > formEnd && saveButton > footer, 'Speichern liegt in der festen Fußleiste nach dem Formular');
  assert.match(thanks, /type="submit" form="kw-masha-feedly-onboarding-preferences"[^>]*data-masha-feedly-onboarding-save/);
  assert.match(styles, /\.kw-masha-feedly__onboarding-thanks-dialog \{ display: flex;[\s\S]*flex-direction: column; overflow: hidden;/);
  assert.match(styles, /\.kw-masha-feedly__onboarding-preferences \{ display: grid; min-height: 0; min-width: 0; flex: 1 1 0; align-content: start;[\s\S]*overflow-y: auto;/);
  assert.match(styles, /\.kw-masha-feedly__onboarding-thanks-dialog > \.kw-masha-feedly__dialog-actions \{ display: grid;/);
});

test('zeigt Profilfarben, Danke-Animationen und E-Mail-Auswahl direkt im Abschlussformular', () => {
  const template = fs.readFileSync(path.join(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
  const onboarding = fs.readFileSync(path.join(__dirname, '../../client/src/js/masha-feedly-onboarding.js'), 'utf8');
  const widgetExtension = fs.readFileSync(path.join(__dirname, '../../src/Extension/MashaFeedlyWidgetExtension.php'), 'utf8');
  const thanks = template.match(/data-masha-feedly-onboarding-thanks[\s\S]*?\n        <\/section>/)?.[0] || '';

  assert.match(thanks, /<details class="kw-masha-feedly__onboarding-colors" data-masha-feedly-onboarding-colors open>/);
  assert.match(thanks, /<details class="kw-masha-feedly__onboarding-effects-details" data-masha-feedly-theme-details open>/);
  assert.match(thanks, /<details class="kw-masha-feedly__onboarding-email-details" open>/);
  for (const setting of [
    'name="MashaFeedlyTheme"',
    'data-masha-feedly-theme-preview=',
    'name="MashaFeedlyEmailNotifications"',
    '<% loop $ProfileEmailOptions %>',
    'data-masha-feedly-email-option',
  ]) assert.ok(thanks.includes(setting), `Einstellung ${setting} bleibt im Formular`);
  for (const name of ['MashaFeedlyNotifyNewEntries', 'MashaFeedlyNotifyEntryUpdates', 'MashaFeedlyNotifyComments', 'MashaFeedlyNotifyDueDateReminders', 'MashaFeedlyNotifyCostEstimates']) {
    assert.ok(widgetExtension.includes(`'${name}' =>`), `E-Mail-Auswahl ${name} wird an die Vorlage übergeben`);
  }
  assert.doesNotMatch(onboarding, /\.kw-masha-feedly__onboarding-email-details'\)\?\.removeAttribute\?\.\('open'\)/);
  assert.doesNotMatch(onboarding, /data-masha-feedly-onboarding-colors'\)\?\.removeAttribute\?\.\('open'\)/);
});

test('zeigt aus dem Abschlussfenster eine aktive Beispiel-Animation der gewählten Kategorie', async () => {
  const { nodes, window } = setup();
  const button = new Element({ mashaFeedlyThemePreview: 'playful' }, '[data-masha-feedly-theme-preview]');
  button.insideThanks = true;
  let previewed = '';
  window.KWMashaFeedlyEffects = {
    refreshCatalogue: async () => ({ effects: [{ id: 'sparkle', categories: ['playful'] }] }),
    choose: (effects) => effects[0],
    previewFallback: async () => false,
  };
  window.KWMashaFeedlyEntries = {
    previewCompletionAnimation: async (_document, _window, id) => { previewed = id; return true; },
  };
  nodes.get('[data-masha-feedly-onboarding-thanks]').listeners.click({ target: button });
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(previewed, 'sparkle');
  assert.equal(nodes.get('themePreviewStatus').textContent, 'TOUR_THANKS_EXAMPLE_STARTED');
  assert.equal(button.disabled, false);
});


test('Später merkt die Einführung vor, Beenden schließt sie dauerhaft ab', () => {
  const later = setup();
  later.nodes.get('[data-masha-feedly-tour-skip]').click();
  assert.equal(later.requests[0][1].body.Deferred, '1');
  const ended = setup();
  ended.nodes.get('[data-masha-feedly-tour-end]').click();
  assert.equal(ended.requests[0][1].body.Deferred, undefined);
});
