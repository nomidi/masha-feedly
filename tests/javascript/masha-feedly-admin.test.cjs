const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

/** JavaScript-Tests für Sortierung und Verschieben im Masha-Feedly-Board. @author Kooperative Web */
const sourcePath = path.resolve(__dirname, '../../client/src/js/masha-feedly-admin.js');
const source = fs.readFileSync(sourcePath, 'utf8');

/** Erzeugt die interaktiven Teile des Mite-Dialogs für Verhaltenstests. */
function createMiteModal() {
  const modal = new TestElement('mite-modal', { optionsUrl: '/mite-options', startUrl: '/mite-start', stopUrl: '/mite-stop' });
  modal.hidden = true;
  modal.heading = new TestElement('heading');
  modal.project = new TestElement('select');
  modal.project.replaceChildren = (...options) => { modal.project.options = options; };
  modal.project.add = (option) => { modal.project.options.push(option); };
  modal.service = new TestElement('select');
  modal.service.value = '789';
  modal.service.replaceChildren = (...options) => { modal.service.options = options; };
  modal.service.add = (option) => { modal.service.options.push(option); };
  modal.activeTimer = new TestElement('p');
  modal.message = new TestElement('p');
  modal.start = new TestElement('button');
  modal.close = new TestElement('button');
  modal.refresh = new TestElement('button');
  modal.stop = new TestElement('button');
  for (const [control, selector] of [[modal.start, '[data-mite-start]'], [modal.stop, '[data-mite-stop]'], [modal.close, '[data-mite-close]'], [modal.refresh, '[data-mite-refresh]']]) {
    control.closest = (candidate) => candidate === selector ? control : null;
  }
  modal.querySelector = (selector) => ({
    h2: modal.heading,
    '[data-mite-project]': modal.project,
    '[data-mite-service]': modal.service,
    '[data-mite-start]': modal.start,
    '[data-mite-stop]': modal.stop,
    '[data-mite-active-timer]': modal.activeTimer,
    '[data-mite-status]': modal.message,
  })[selector] || null;
  modal.querySelectorAll = () => [modal.start, modal.stop, modal.close, modal.refresh, modal.project, modal.service];
  return modal;
}

/** Verschiebt eine Testkarte und wartet auf die asynchron geladene Mite-Auswahl. */
async function moveCardWithMite(board) {
  const sourceList = new TestElement('list', { categoryId: '1' });
  const targetList = new TestElement('list', { categoryId: '2' });
  const card = new TestElement('card', { entryId: '42' });
  sourceList.appendChild(card);
  startDragging(board, card);
  await board.listeners.drop({ target: targetList, preventDefault() {} });
  await new Promise(setImmediate);
  return { card, targetList };
}

test('Doing öffnet nur die Auswahl und sendet den Timer erst nach ausdrücklichem Klick mit CSRF-Token', async () => {
  const modal = createMiteModal();
  const requests = [];
  const { board } = createBoardEnvironment(async (url, options) => {
    requests.push({ url, options });
    return { ok: true, json: async () => url === '/move-entry'
      ? { success: true, mitePrompt: true }
      : url === '/mite-options'
        ? { success: true, projects: { 123: 'Website' }, services: { 789: 'Entwicklung' }, projectID: 123, activeTimerID: 77, activeTimerNote: 'Andere Arbeit' }
        : { success: true, message: 'Mite-Timer läuft.' } };
  }, true, 'du', () => true, 'complete', true, null, null, modal);
  await moveCardWithMite(board);
  assert.equal(modal.hidden, false);
  assert.equal(modal.project.value, '123');
  assert.equal(modal.start.disabled, false);
  assert.deepEqual(requests.map(({ url }) => url), ['/move-entry', '/mite-options']);
  await modal.listeners.click({ target: modal.start });
  assert.equal(requests[2].url, '/mite-start');
  assert.equal(requests[2].options.method, 'POST');
  assert.deepEqual(requests[2].options.body.values, [
    ['SecurityID', 'csrf-test-token'], ['ConfirmedTimerID', '77'], ['EntryID', '42'], ['ProjectID', '123'], ['ServiceID', '789'],
  ]);
  assert.equal(modal.hidden, true);
  assert.equal(board.status.textContent, 'Mite-Timer läuft.');
});

test('Ohne Timer weiter behält Doing und erzeugt keinen Mite-Schreibaufruf', async () => {
  const modal = createMiteModal();
  const requests = [];
  const { board } = createBoardEnvironment(async (url) => {
    requests.push(url);
    return { ok: true, json: async () => url === '/move-entry' ? { success: true, mitePrompt: true }
      : { success: true, projects: { 123: 'Website' }, projectID: 123, activeTimerID: 0 } };
  }, true, 'du', () => true, 'complete', true, null, null, modal);
  const { card, targetList } = await moveCardWithMite(board);
  await modal.listeners.click({ target: modal.close });
  assert.equal(modal.hidden, true);
  assert.equal(card.parentElement, targetList);
  assert.deepEqual(requests, ['/move-entry', '/mite-options']);
});

test('zeigt bei laufendem Timer eine Stoppaktion und sendet keine Feedly-Eintragsdaten', async () => {
  const modal = createMiteModal();
  const requests = [];
  const { board } = createBoardEnvironment(async (url, options) => {
    requests.push({ url, options });
    return { ok: true, json: async () => url === '/move-entry' ? { success: true, mitePrompt: true }
      : url === '/mite-options'
        ? { success: true, projects: {}, services: {}, activeTimerID: requests.filter((request) => request.url === '/mite-options').length === 1 ? 77 : 0, activeTimerNote: 'Arbeit' }
      : { success: true, message: 'Mite-Timer wurde gestoppt.' } };
  }, true, 'du', () => true, 'complete', true, null, null, modal);
  await moveCardWithMite(board);
  assert.equal(modal.stop.hidden, false);
  await modal.listeners.click({ target: modal.stop });
  assert.equal(requests[2].url, '/mite-stop');
  assert.deepEqual(requests[2].options.body.values, [['SecurityID', 'csrf-test-token'], ['ConfirmedTimerID', '77']]);
  assert.equal(requests[3].url, '/mite-options');
});

test('Mite-Fehler lassen die Karte auf Doing und verlangen vor erneutem Start eine aktualisierte Auswahl', async () => {
  const modal = createMiteModal();
  const { board } = createBoardEnvironment(async (url) => ({
    ok: url !== '/mite-start', json: async () => url === '/move-entry' ? { success: true, mitePrompt: true }
      : url === '/mite-options' ? { success: true, projects: { 123: 'Website' }, projectID: 123 }
        : { success: false, message: 'Mite ist nicht erreichbar.' },
  }), true, 'du', () => true, 'complete', true, null, null, modal);
  const { card, targetList } = await moveCardWithMite(board);
  await modal.listeners.click({ target: modal.start });
  assert.equal(modal.hidden, false);
  assert.equal(modal.message.textContent, 'Mite ist nicht erreichbar.');
  assert.equal(modal.start.disabled, true);
  assert.equal(card.parentElement, targetList);
  await modal.listeners.click({ target: modal.refresh });
  await new Promise(setImmediate);
  assert.equal(modal.start.disabled, false);
});

test('initialisiert den Mite-Dialog erneut, wenn CMS-PJAX das bereits benutzte Board ersetzt', async () => {
  const modal = createMiteModal();
  const environment = createBoardEnvironment(async (url) => ({
    ok: true, json: async () => url === '/move-entry' ? { success: true, mitePrompt: true }
      : { success: true, projects: { 123: 'Website' }, projectID: 123 },
  }), true, 'du', () => true, 'complete', true, null, null, modal);
  environment.setBoardAvailable(false);
  environment.triggerMutation();
  // Eine neu geladene Board-Kopie hat weder Initialisierungsmarkierung noch gebundene Aktionen.
  delete environment.board.dataset.mashaFeedlyAdminInitialized;
  environment.board.listeners = {};
  const replacementModal = createMiteModal();
  environment.board.miteModal = replacementModal;
  environment.setBoardAvailable(true);
  environment.triggerMutation();
  await moveCardWithMite(environment.board);
  assert.equal(replacementModal.hidden, false);
  assert.equal(replacementModal.project.value, '123');
});

test('zeigt im CMS-Board das grüne Neu-Icon statt eines Aktivität-Sterns', () => {
  const renderer = fs.readFileSync(path.resolve(__dirname, '../../src/Admin/MashaFeedlyAdmin.php'), 'utf8');
  const styles = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly-admin.scss'), 'utf8');
  assert.match(renderer, /class="masha-feedly-board__new-icon" viewBox="0 0 177800 177800"[\s\S]*?fill="#48b02c"[\s\S]*?fill="#fff"/);
  assert.doesNotMatch(renderer, /masha-feedly-board__new-indicator--(?:general|personal)/);
  assert.doesNotMatch(renderer, /<\/svg><span>.*BOARD_NEW/);
  assert.doesNotMatch(styles, /&__new-indicator\s*\{[^}]*background:\s*#(?:fff2b8|fde7f2)/);
  assert.doesNotMatch(styles, /&--personal\s*\{[^}]*background:\s*#fde7f2/);
  assert.doesNotMatch(renderer, /M12 2\.5 14\.4 9\.6 21\.5 12/);
  assert.match(styles, /\.masha-feedly-board__new-icon[\s\S]*?path:first-child \{ fill: #48b02c; \}[\s\S]*?path:last-child \{ fill: #fff; \}/);
});

test('Admin-Menü und Breadcrumb erhalten keine Masha-Feedly-Zähler-Badges', () => {
  const styles = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly-admin.scss'), 'utf8');
  assert.doesNotMatch(source, /masha-feedly-menu__badge|KWMashaFeedlyMenuCounts|RenderMenuCounts/);
  assert.doesNotMatch(styles, /masha-feedly-menu__badge/);
});

test('Meldepersonen-Ansicht schaltet CMS-Tabs um, durchsucht Einträge und speichert mit CSRF-Token', () => {
  const renderer = fs.readFileSync(path.resolve(__dirname, '../../src/Admin/MashaFeedlyAdmin.php'), 'utf8');
  assert.match(renderer, /MashaFeedlyEntry::canManageReporter\(\$member\)[\s\S]*?data-admin-view-tab="reporters"/);
  assert.match(renderer, /data-reporter-form[\s\S]*?name="EntryID"[\s\S]*?name="ReportedByID"/);
  assert.match(renderer, /'saveReporter'/);
  assert.match(source, /\[data-admin-view-tab\][\s\S]*?aria-selected[\s\S]*?data-admin-view-panel/);
  assert.match(source, /\[data-reporter-search\][\s\S]*?data-reporter-row/);
  assert.match(source, /\[data-reporter-form\][\s\S]*?data\.set\('SecurityID'[\s\S]*?fetch\(form\.dataset\.saveUrl,[\s\S]*?method: 'POST'/);
});

test('speichert eine ausgewählte Meldeperson aus der CMS-Übersicht mit Statusmeldung', async () => {
  let requestURL = '';
  let requestOptions = null;
  const { documentListeners, formDataInstances } = createBoardEnvironment(async (url, options) => {
    requestURL = url;
    requestOptions = options;
    return { ok: true, json: async () => ({ success: true, message: 'Meldeperson gespeichert.' }) };
  });
  const button = { disabled: false };
  const status = { textContent: '' };
  const form = {
    dataset: { saveUrl: '/admin/saveReporter', securityId: 'csrf-report-token', savingMessage: 'Speichere …' },
    formValues: [['EntryID', '42'], ['ReportedByID', '7']],
    closest(selector) {
      if (selector === '[data-reporter-form]') return this;
      if (selector === '[data-masha-feedly-board]') return { dataset: { securityId: 'csrf-board-token' } };
      return null;
    },
    querySelector(selector) {
      if (selector === '[type="submit"]') return button;
      if (selector === '[data-reporter-status]') return status;
      return null;
    },
  };

  await documentListeners.submit({ target: form, preventDefault() {} });

  assert.equal(requestURL, '/admin/saveReporter');
  assert.equal(requestOptions.method, 'POST');
  assert.equal(requestOptions.credentials, 'same-origin');
  assert.deepEqual(formDataInstances[0].values, [
    ['EntryID', '42'],
    ['ReportedByID', '7'],
    ['SecurityID', 'csrf-report-token'],
  ]);
  assert.equal(status.textContent, 'Meldeperson gespeichert.');
  assert.equal(button.disabled, false);
});

test('spielt die im Konfigurationsbereich angeklickte Animationsvorschau ab', () => {
  const previewCalls = [];
  const entriesAPI = {
    previewCompletionAnimation(...args) {
      previewCalls.push(args.slice(2));
      return { rocket: {} };
    },
  };
  const { documentListeners } = createBoardEnvironment(undefined, true, 'du', () => true, 'complete', true, entriesAPI);
  const preview = new TestElement('animation-preview', { unicornUrl: '/unicorn.svg' });
  preview.status = { textContent: '' };
  preview.querySelector = (selector) => selector === '[data-masha-feedly-animation-preview-status]' ? preview.status : null;
  const button = new TestElement('animation-preview-button', {
    mashaFeedlyAnimationPreview: 'rocket',
    previewMessage: 'Vorschau gestartet.',
    reducedMotionMessage: 'Animation ausgeschaltet.',
  });
  preview.appendChild(button);

  documentListeners.click({ target: button });

  assert.deepEqual(previewCalls, [['rocket', '/unicorn.svg']]);
  assert.equal(preview.status.textContent, 'Vorschau gestartet.');
});

test('zeigt im CMS nur die Vorschauen des ausgewählten Themes', () => {
  const styles = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly-admin.scss'), 'utf8');
  assert.match(source, /select\[name="MashaFeedlyTheme"\][\s\S]*?data-masha-feedly-animation-preview-card/);
  assert.match(styles, /\.masha-feedly-animation-preview\[hidden\]\s*\{\s*display:\s*none\s*!important;/);
  const themeSelect = new TestElement('theme-select');
  themeSelect.value = 'playful';
  const cards = ['playful', 'playful', 'serious', 'serious'].map((theme) =>
    new TestElement('animation-preview-card', { mashaFeedlyTheme: theme }));
  const { documentListeners } = createBoardEnvironment(undefined, true, 'du', () => true, 'complete', true, null, { themeSelect, cards });

  assert.deepEqual(cards.map((card) => card.hidden), [false, false, true, true]);
  themeSelect.value = 'serious';
  documentListeners.change({ target: themeSelect });
  assert.deepEqual(cards.map((card) => card.hidden), [true, true, false, false]);
});

test('ordnet Admin-Karten als Kopfzeile, Titel-Auszug und Datum darunter an', () => {
  const renderer = fs.readFileSync(path.resolve(__dirname, '../../src/Admin/MashaFeedlyAdmin.php'), 'utf8');
  const styles = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly-admin.scss'), 'utf8');
  assert.match(
    renderer,
    /card-topline[\s\S]*?entry-number[\s\S]*?board__priority[\s\S]*?new-indicator[\s\S]*?drag-handle[\s\S]*?card-heading[\s\S]*?card-date/
  );
  assert.match(styles, /&__card-topline\s*\{[\s\S]*?display: flex/);
  assert.match(styles, /&__card-topline\s*\{[\s\S]*?position: relative[\s\S]*?padding-right: 2rem/);
  assert.match(styles, /&__drag-handle\s*\{[\s\S]*?position: absolute[\s\S]*?right: 0/);
  assert.match(styles, /&__card-heading\s*\{[\s\S]*?-webkit-line-clamp: 2/);
  assert.match(styles, /&__card-date\s*\{[\s\S]*?display: block/);
  assert.doesNotMatch(renderer, /masha-feedly-board__card-metadata/);
});

test('lässt zugewiesene Avatar-Gruppen am unteren Rand der Admin-Karte überhängen', () => {
  const styles = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly-admin.scss'), 'utf8');
  const compiledStyles = fs.readFileSync(path.resolve(__dirname, '../../client/dist/css/masha-feedly-admin.css'), 'utf8');
  for (const stylesheet of [styles, compiledStyles]) {
    assert.match(stylesheet, /board__card\[data-has-assignees=(?:"true"|true)\]\s*\{[^}]*overflow:\s*visible;/);
    assert.match(stylesheet, /data-has-assignees=(?:"true"|true)[^{}]*\{[^}]*position:\s*absolute;[^}]*right:\s*(?:0)?\.75rem;[^}]*bottom:\s*-\s*(?:0)?\.55rem;[^}]*transform:\s*none/);
    assert.match(stylesheet, /masha-feedly-board__assignees\s*\{[^}]*border-radius:\s*999px;[^}]*background:\s*(?:rgba\(255,\s*255,\s*255,\s*0\.96\)|hsla\(0,\s*0%,\s*100%,\s*\.96\));[^}]*box-shadow:/);
    assert.match(stylesheet, /masha-feedly-board__assignee:hover\s*\{[^}]*transform:\s*translateY\(-2px\) scale\(1\.06\)/);
    assert.match(stylesheet, /prefers-reduced-motion:\s*reduce[\s\S]*?masha-feedly-board__assignee\s*\{[^}]*transition:\s*none/);
  }
});

test('zentriert das Plus im Admin-Button geometrisch statt über ein Schriftzeichen', () => {
  const styles = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly-admin.scss'), 'utf8');
  const compiledStyles = fs.readFileSync(path.resolve(__dirname, '../../client/dist/css/masha-feedly-admin.css'), 'utf8');
  for (const stylesheet of [styles, compiledStyles]) {
    assert.match(stylesheet, /linear-gradient\(currentColor 0 0\) center\s*\/\s*(?:0)?\.62rem\s+(?:0)?\.1rem no-repeat/);
    assert.match(stylesheet, /linear-gradient\(currentColor 0 0\) center\s*\/\s*(?:0)?\.1rem\s+(?:0)?\.62rem no-repeat/);
    assert.match(stylesheet, /background-color:\s*#f8deeb/);
    assert.doesNotMatch(stylesheet, /content:\s*["']\+["']/);
  }
});

/** Simuliert die Klassen eines DOM-Elements für die isolierten Board-Tests. */
class TestClassList {
  constructor() {
    this.values = new Set();
  }

  add(...values) {
    values.forEach((value) => this.values.add(value));
  }

  remove(value) {
    this.values.delete(value);
  }

  contains(value) {
    return this.values.has(value);
  }
}

/** Simuliert die DOM-Elemente und Listener, die das Board-JavaScript verwendet. */
class TestElement {
  constructor(type, dataset = {}) {
    this.type = type;
    this.dataset = dataset;
    this.children = [];
    this.parentElement = null;
    this.nextElementSibling = null;
    this.classList = new TestClassList();
    this._textContent = '';
    this.textContentMutationCount = 0;
    this.value = '';
    this.hidden = false;
    this.focusCount = 0;
    Object.defineProperty(this, 'textContent', {
      get: () => this._textContent,
      set: (value) => {
        if (this._textContent !== value) this.textContentMutationCount++;
        this._textContent = value;
      },
    });
    this.listeners = {};
    this.status = { textContent: '' };
  }

  addEventListener(name, callback) {
    this.listeners[name] = callback;
  }

  closest(selector) {
    if (selector === '[data-masha-feedly-board]' && this.type === 'board') return this;
    if (selector === '[data-open-category-form]' && this.type === 'button' && this.isCategoryOpenButton) return this;
    if (selector === '.masha-feedly-board__card' && this.type === 'card') return this;
    if (selector === '.masha-feedly-board__drag-handle' && this.type === 'handle') return this;
    if (selector === '.masha-feedly-board__list' && this.type === 'list') return this;
    if (selector === '.masha-feedly-board__column' && this.type === 'column') return this;
    if (selector === '.masha-feedly-board__columns' && this.type === 'columns') return this;
    if (selector === '.masha-feedly-board__category-drag-handle' && this.type === 'category-handle') return this;
    if (selector === '[data-delete-category]' && this.type === 'delete-button') return this;
    if (selector === '[data-masha-feedly-animation-preview]' && this.type === 'animation-preview-button') return this;
    if (selector === '[data-masha-feedly-animation-previews]' && this.type === 'animation-preview') return this;
    return this.parentElement?.closest(selector) || null;
  }

  querySelector(selector) {
    if (selector === '[data-mite-modal]') return this.miteModal || null;
    if (selector === '.masha-feedly-board__status') return this.status;
    if (selector === '[data-masha-feedly-assignee-filter]') return this.assigneeFilter || null;
    if (selector === '.masha-feedly-board__list') return this.list || null;
    if (selector === '.masha-feedly-board__columns') return this.columnsContainer || null;
    if (selector === '[data-create-category-form]') return this.categoryForm || null;
    if (selector === '[data-category-modal]') return this.categoryModal || null;
    if (selector === '[data-create-category-form]') return this.categoryForm || null;
    if (selector === '[data-open-category-form]') return this.openCategoryFormButton || null;
    if (selector === '[data-open-entry-form]') return this.openEntryFormButton || null;
    if (selector === '[data-entry-modal]') return this.entryModal || null;
    if (selector === '[data-admin-create-entry-form]') return this.entryForm || null;
    if (selector === '[data-category-title-input]') return this.categoryNameInput || null;
    if (selector === '[data-cancel-category-form]') return this.cancelCategoryFormButton || null;
    if (selector === '[data-entry-form-status]') return this.entryStatus || null;
    if (selector === '[data-category-title-input]' && this.type === 'form') return this.categoryNameInput || null;
    if (selector === '.masha-feedly-board__column-header span') return this.countSpan || null;
    if (selector === '.text') return this.textElement || null;
    return null;
  }

  setAttribute(name, value) {
    this.attributes ??= {};
    this.attributes[name] = value;
  }

  getAttribute(name) {
    return this.attributes?.[name] ?? null;
  }

  matches(selector) {
    return (selector === 'select[name="MashaFeedlyTheme"]' && this.type === 'theme-select')
      || (selector === 'input[name="MashaFeedlyMiteEnabled"][type="checkbox"]' && this.type === 'mite-enabled');
  }

  focus() {
    this.focusCount++;
  }

  reset() {
    if (this.categoryNameInput) this.categoryNameInput.value = '';
    if (this.formValues) this.formValues = [];
  }

  append(...elements) {
    elements.forEach((element) => this.appendChild(element));
  }

  remove() {
    if (this.parentElement) {
      this.parentElement.children = this.parentElement.children.filter((child) => child !== this);
      this.parentElement.updateSiblings();
      this.parentElement = null;
    }
  }

  querySelectorAll(selector) {
    if (selector === '.is-drop-target') return [];
    const descendants = [];
    const visit = (element) => {
      for (const child of element.children) {
        descendants.push(child);
        visit(child);
      }
    };
    visit(this);
    if (selector === '.masha-feedly-board__card') return descendants.filter((element) => element.type === 'card');
    if (selector === '.masha-feedly-board__card:not([hidden])') {
      return descendants.filter((element) => element.type === 'card' && !element.hidden);
    }
    if (selector === '.masha-feedly-board__column') return descendants.filter((element) => element.type === 'column');
    return [];
  }

  contains(element) {
    return this.children.includes(element) || this.children.some((child) => child.contains(element));
  }

  getBoundingClientRect() {
    return this.bounds || { left: 0, width: 100, top: 0, height: 100 };
  }

  appendChild(element) {
    if (element.parentElement) {
      element.parentElement.children = element.parentElement.children.filter((child) => child !== element);
    }
    this.children.push(element);
    element.parentElement = this;
    this.updateSiblings();
  }

  insertBefore(element, nextElement) {
    if (element.parentElement) {
      element.parentElement.children = element.parentElement.children.filter((child) => child !== element);
      element.parentElement.updateSiblings();
    }
    const index = nextElement ? this.children.indexOf(nextElement) : -1;
    if (index === -1) this.children.push(element);
    else this.children.splice(index, 0, element);
    element.parentElement = this;
    this.updateSiblings();
  }

  updateSiblings() {
    this.children.forEach((child, index) => {
      child.nextElementSibling = this.children[index + 1] || null;
    });
  }
}

/** Erzeugt eine minimale DOM- und Browserumgebung für einen Testfall. */
function createBoardEnvironment(fetchImplementation = async () => ({
  ok: true,
  json: async () => ({ success: true }),
  }), hasBoard = true, formalAddress = 'du', confirmImplementation = () => true, documentReadyState = 'complete', includeGlobalTranslator = true, entriesAPI = null, animationPreviewState = null, miteModal = null) {
  const board = new TestElement('board', {
    moveUrl: '/move-entry',
    moveCategoryUrl: '/move-category',
    deleteCategoryUrl: '/delete-category',
    createCategoryUrl: '/create-category',
    createEntryUrl: '/__masha-feedly/createEntry',
    securityId: 'csrf-test-token',
    adminTranslations: JSON.stringify({
      BOARD_CATEGORY_DRAG_ARIA: 'Kategorie sortieren',
      BOARD_CATEGORY_DRAG_TITLE: 'Kategorie zum Sortieren ziehen',
      BOARD_CATEGORY_DELETE: 'Leere Kategorie löschen',
    }),
  });
  board.miteModal = miteModal;
  board.assigneeFilter = new TestElement('select');
  board.assigneeFilter.value = '';
  board.columnsContainer = new TestElement('columns');
  board.appendChild(board.columnsContainer);
  board.categoryForm = new TestElement('div');
  board.categoryNameInput = new TestElement('input');
  board.categorySubmitButton = new TestElement('button');
  board.categoryForm.categoryNameInput = board.categoryNameInput;
  board.categoryForm.categorySubmitButton = board.categorySubmitButton;
  board.categoryModal = new TestElement('modal');
  board.categoryModal.hidden = true;
  board.categoryModal.categoryForm = board.categoryForm;
  board.categoryModal.categoryNameInput = board.categoryNameInput;
  board.categoryModal.querySelectorAll = () => [];
  board.openCategoryFormButton = new TestElement('button');
  board.openCategoryFormButton.isCategoryOpenButton = true;
  board.openCategoryFormButton.parentElement = board;
  board.categoryForm.querySelector = (selector) => {
    if (selector === '[data-category-title-input]') return board.categoryNameInput;
    if (selector === '[data-submit-category-form]') return board.categorySubmitButton;
    if (selector === '[data-cancel-category-form]') return board.cancelCategoryFormButton;
    return null;
  };
  board.cancelCategoryFormButton = new TestElement('button');
  board.categoryForm.cancelCategoryFormButton = board.cancelCategoryFormButton;
  board.categorySubmitButton.parentElement = board.categoryForm;
  board.entryModal = new TestElement('modal');
  board.entryModal.hidden = true;
  board.entryModal.querySelectorAll = () => [];
  board.openEntryFormButton = new TestElement('button');
  board.entryForm = new TestElement('form', { createUrl: '/__masha-feedly/createEntry', securityId: 'csrf-test-token' });
  board.entryForm.formValues = [['Content', 'Ein Testeintrag']];
  board.entryForm.submitButton = new TestElement('button');
  board.entryForm.contentInput = new TestElement('textarea');
  board.entryForm.contentInput.value = 'Ein Testeintrag';
  board.entryForm.status = new TestElement('p');
  board.entryForm.querySelector = (selector) => {
    if (selector === '[name="Content"]') return board.entryForm.contentInput;
    if (selector === '[type="submit"]') return board.entryForm.submitButton;
    if (selector === '[data-entry-form-status]') return board.entryForm.status;
    return null;
  };
  board.entryStatus = board.entryForm.status;
  const formDataInstances = [];
  class TestFormData {
    constructor(form = null) {
      this.values = form?.formValues ? [...form.formValues] : [];
      formDataInstances.push(this);
    }

    append(name, value) {
      this.values.push([name, String(value)]);
    }

    set(name, value) {
      this.values = this.values.filter(([key]) => key !== name);
      this.values.push([name, String(value)]);
    }
  }

  let boardAvailable = hasBoard;
  const documentListeners = {};
  const simulatedWindow = {
    location: { reload: () => { reloadCount++; } },
    KWMashaFeedlyTranslations: { FORMAL_ADDRESS: formalAddress },
    KWMashaFeedlyEntries: entriesAPI,
    KWMashaFeedlyTranslate: includeGlobalTranslator
      ? (key, values = {}) => Object.entries(values).reduce((message, [name, value]) => message.replaceAll(`{${name}}`, String(value)), dictionary[key] || key)
      : undefined,
    confirm: confirmImplementation,
  };
  const document = {
    body: {},
    readyState: documentReadyState,
    addEventListener: (name, callback) => { documentListeners[name] = callback; },
    querySelector: (selector) => {
      if (selector === '[data-masha-feedly-board]') return boardAvailable ? board : null;
      if (selector === 'select[name="MashaFeedlyTheme"]') return animationPreviewState?.themeSelect || null;
      if (selector === 'input[name="MashaFeedlyMiteEnabled"][type="checkbox"]') return animationPreviewState?.miteEnabled || null;
      return null;
    },
    querySelectorAll: (selector) => selector === '[data-masha-feedly-animation-preview-card]' ? animationPreviewState?.cards || [] : selector === '[data-mite-settings]' ? animationPreviewState?.miteSettings || [] : [],
    createElement: (type) => new TestElement(type),
    createElementNS: (_namespace, type) => new TestElement(type),
  };
  let mutationCallback = null;
  class TestMutationObserver {
    constructor(callback) {
      mutationCallback = callback;
    }

    observe() {}
    disconnect() {}
  }
  const dictionary = {
    BOARD_SAVING: 'Änderung wird gespeichert …',
    BOARD_SAVE_ERROR: 'Speichern fehlgeschlagen.',
    BOARD_SAVE_SUCCESS: 'Eintrag wurde gespeichert.',
    BOARD_SAVE_FAILURE: 'Eintrag konnte nicht gespeichert werden.',
    BOARD_CATEGORY_SAVING: 'Kategorien werden sortiert …',
    BOARD_CATEGORY_SAVE_ERROR: 'Sortieren fehlgeschlagen.',
    BOARD_CATEGORY_SAVE_SUCCESS: 'Kategorienreihenfolge gespeichert.',
    BOARD_CATEGORY_SAVE_FAILURE: 'Kategorienreihenfolge konnte nicht gespeichert werden.',
    BOARD_CATEGORY_NAME_REQUIRED: 'Bitte gib einen Kategorienamen ein.',
    BOARD_CATEGORY_ADDING: 'Kategorie wird angelegt …',
    BOARD_CATEGORY_ADD_ERROR: 'Kategorie konnte nicht angelegt werden.',
    BOARD_CATEGORY_ADD_SUCCESS: 'Kategorie wurde angelegt.',
    BOARD_CATEGORY_DELETE: 'Leere Kategorie löschen',
    BOARD_CATEGORY_DRAG_ARIA: 'Kategorie sortieren',
    BOARD_CATEGORY_DRAG_TITLE: 'Kategorie zum Sortieren ziehen',
    BOARD_CATEGORY_DELETE_CONFIRM: 'Leere Kategorie löschen?',
    BOARD_CATEGORY_DELETING: 'Kategorie wird gelöscht …',
    BOARD_CATEGORY_DELETE_ERROR: 'Kategorie konnte nicht gelöscht werden.',
    BOARD_CATEGORY_DELETE_SUCCESS: 'Leere Kategorie gelöscht.',
    BOARD_CATEGORY_DELETE_FAILURE: 'Kategorie konnte nicht gelöscht werden.',
    BOARD_ENTRY_SAVING: 'Eintrag wird gespeichert …',
    BOARD_ENTRY_SAVE_ERROR: 'Eintrag konnte nicht gespeichert werden.',
    BOARD_ENTRY_SAVE_SUCCESS: 'Eintrag wurde gespeichert.',
  };
  let reloadCount = 0;
  vm.runInNewContext(source, {
    document,
    window: simulatedWindow,
    FormData: TestFormData,
    fetch: fetchImplementation,
    MutationObserver: TestMutationObserver,
    Option: class { constructor(label, value) { this.label = label; this.value = value; } },
  });

  return {
    board,
    formDataInstances,
    triggerMutation: () => mutationCallback?.(),
    documentListeners,
    clickCategoryButton: () => documentListeners.click?.({ target: board.openCategoryFormButton }),
    setBoardAvailable: (available) => { boardAvailable = available; },
    reloadCount: () => reloadCount,
  };
}

test('öffnet den Mite-Dialog auch nach einem Statuswechsel aus dem Eintragsdropdown', async () => {
  const modal = createMiteModal();
  const requests = [];
  const { documentListeners } = createBoardEnvironment(async (url) => {
    requests.push(url);
    return { ok: true, json: async () => ({ success: true, projects: { 123: 'Website' }, projectID: 123, activeTimerID: 0 }) };
  }, true, 'du', () => true, 'complete', true, null, null, modal);
  documentListeners['kw-masha-feedly:mite-prompt']({ detail: { entryID: 42 } });
  await new Promise(setImmediate);
  assert.equal(modal.hidden, false);
  assert.deepEqual(requests, ['/mite-options']);
});

test('filtert das Board nach nicht zugeordneten und ausgewählten persönlichen Einträgen', () => {
  const { board } = createBoardEnvironment();
  const column = new TestElement('column');
  column.list = new TestElement('list', { categoryId: '1' });
  column.countSpan = { textContent: '' };
  column.appendChild(column.list);
  board.appendChild(column);
  const unassignedCard = new TestElement('card', { assignedMemberIds: '' });
  const ownCard = new TestElement('card', { assignedMemberIds: '12,18' });
  const otherCard = new TestElement('card', { assignedMemberIds: '18' });
  column.list.appendChild(unassignedCard);
  column.list.appendChild(ownCard);
  column.list.appendChild(otherCard);

  board.assigneeFilter.value = '12';
  board.assigneeFilter.listeners.change();
  assert.equal(ownCard.hidden, false);
  assert.equal(unassignedCard.hidden, true);
  assert.equal(otherCard.hidden, true);
  assert.equal(column.countSpan.textContent, 1);

  board.assigneeFilter.value = '0';
  board.assigneeFilter.listeners.change();
  assert.equal(unassignedCard.hidden, false);
  assert.equal(ownCard.hidden, true);
  assert.equal(column.countSpan.textContent, 1);

  board.assigneeFilter.value = '';
  board.assigneeFilter.listeners.change();
  assert.equal(unassignedCard.hidden, false);
  assert.equal(ownCard.hidden, false);
  assert.equal(otherCard.hidden, false);
  assert.equal(column.countSpan.textContent, 3);
});

/** Beginnt einen simulierten Drag-Vorgang für eine Karte. */
function startDragging(board, card) {
  const handle = new TestElement('handle');
  card.appendChild(handle);
  board.listeners.dragstart({
    target: handle,
    dataTransfer: {
      effectAllowed: '',
      setData() {},
    },
  });
}

/** Beginnt einen simulierten Drag-Vorgang für eine Kategorie. */
function startDraggingCategory(board, category) {
  const handle = new TestElement('category-handle');
  category.appendChild(handle);
  board.listeners.dragstart({
    target: handle,
    dataTransfer: {
      effectAllowed: '',
      setData() {},
    },
  });
}

test('entfernt den Drag-Zustand nach dem Loslassen einer Karte', () => {
  const { board } = createBoardEnvironment();
  const list = new TestElement('list', { categoryId: '1' });
  const card = new TestElement('card', { entryId: '42' });
  list.appendChild(card);

  startDragging(board, card);
  assert.equal(card.classList.contains('is-dragging'), true);

  board.listeners.dragend();

  assert.equal(card.classList.contains('is-dragging'), false);
});

test('lässt den Eintragslink beim Klicken aus dem Drag-Verhalten heraus', () => {
  const { board } = createBoardEnvironment();
  const list = new TestElement('list', { categoryId: '1' });
  const card = new TestElement('card', { entryId: '42' });
  const link = new TestElement('link');
  list.appendChild(card);
  card.appendChild(link);
  let dragDataWasSet = false;

  board.listeners.dragstart({
    target: link,
    preventDefault() {},
    dataTransfer: { setData() { dragDataWasSet = true; } },
  });

  assert.equal(dragDataWasSet, false);
  assert.equal(card.classList.contains('is-dragging'), false);
});

test('sendet beim Verschieben Kategorie und Reihenfolge und macht die Karte wieder klickbar', async () => {
  let requestURL = '';
  let requestOptions = null;
  const { board, formDataInstances } = createBoardEnvironment(async (url, options) => {
    requestURL = url;
    requestOptions = options;
    return { ok: true, json: async () => ({ success: true, unreadCount: 2, feedbackCount: 1 }) };
  });
  const sourceList = new TestElement('list', { categoryId: '1' });
  const targetList = new TestElement('list', { categoryId: '2' });
  const card = new TestElement('card', { entryId: '42' });
  const firstTargetCard = new TestElement('card', { entryId: '77' });
  sourceList.appendChild(card);
  targetList.appendChild(firstTargetCard);

  startDragging(board, card);
  await board.listeners.drop({ target: targetList, preventDefault() {} });

  assert.equal(requestURL, '/move-entry');
  assert.equal(requestOptions.method, 'POST');
  assert.equal(requestOptions.credentials, 'same-origin');
  assert.deepEqual(formDataInstances[0].values, [
    ['SecurityID', 'csrf-test-token'],
    ['EntryID', '42'],
    ['CategoryID', '2'],
    ['EntryIDs[]', '77'],
    ['EntryIDs[]', '42'],
  ]);
  assert.equal(card.parentElement, targetList);
  assert.equal(card.classList.contains('is-dragging'), false);
  assert.equal(board.status.textContent, 'Eintrag wurde gespeichert.');
});

test('stellt die Karte nach einem fehlgeschlagenen Verschieben in der Ursprungskategorie wieder her', async () => {
  const { board } = createBoardEnvironment(async () => ({
    ok: false,
    json: async () => ({ success: false, message: 'Speichern fehlgeschlagen.' }),
  }));
  const sourceList = new TestElement('list', { categoryId: '1' });
  const targetList = new TestElement('list', { categoryId: '2' });
  const card = new TestElement('card', { entryId: '42' });
  sourceList.appendChild(card);

  startDragging(board, card);
  await board.listeners.drop({ target: targetList, preventDefault() {} });

  assert.equal(card.parentElement, sourceList);
  assert.equal(card.classList.contains('is-dragging'), false);
  assert.equal(board.status.textContent, 'Speichern fehlgeschlagen.');
});

test('sortiert Kategorien per Drag-and-drop und speichert ihre vollständige Reihenfolge', async () => {
  let requestURL = '';
  let requestOptions = null;
  const { board, formDataInstances, clickCategoryButton } = createBoardEnvironment(async (url, options) => {
    requestURL = url;
    requestOptions = options;
    return { ok: true, json: async () => ({ success: true }) };
  });
  const columns = new TestElement('columns');
  const first = new TestElement('column', { categoryId: '1' });
  const second = new TestElement('column', { categoryId: '2' });
  columns.appendChild(first);
  columns.appendChild(second);
  board.appendChild(columns);

  startDraggingCategory(board, first);
  await board.listeners.dragover({
    target: second,
    clientX: 80,
    preventDefault() {},
  });
  await board.listeners.drop({ target: columns, preventDefault() {} });

  assert.equal(requestURL, '/move-category');
  assert.equal(requestOptions.method, 'POST');
  assert.equal(requestOptions.credentials, 'same-origin');
  assert.deepEqual(formDataInstances[0].values, [
    ['SecurityID', 'csrf-test-token'],
    ['CategoryIDs[]', '2'],
    ['CategoryIDs[]', '1'],
  ]);
  assert.deepEqual(columns.children, [second, first]);
  assert.equal(board.status.textContent, 'Kategorienreihenfolge gespeichert.');
});

test('stellt eine Kategorie nach fehlgeschlagenem Speichern an ihre ursprüngliche Position zurück', async () => {
  const { board } = createBoardEnvironment(async () => ({
    ok: false,
    json: async () => ({ success: false, message: 'Sortieren fehlgeschlagen.' }),
  }));
  const columns = new TestElement('columns');
  const first = new TestElement('column', { categoryId: '1' });
  const second = new TestElement('column', { categoryId: '2' });
  columns.appendChild(first);
  columns.appendChild(second);
  board.appendChild(columns);

  startDraggingCategory(board, first);
  await board.listeners.dragover({ target: second, clientX: 80, preventDefault() {} });
  await board.listeners.drop({ target: columns, preventDefault() {} });

  assert.deepEqual(columns.children, [first, second]);
  assert.equal(board.status.textContent, 'Sortieren fehlgeschlagen.');
});

test('löscht eine bestätigte leere Kategorie mit CSRF-Token und blendet sie aus', async () => {
  let requestURL = '';
  let requestOptions = null;
  const { board, formDataInstances, clickCategoryButton } = createBoardEnvironment(async (url, options) => {
    requestURL = url;
    requestOptions = options;
    return { ok: true, json: async () => ({ success: true }) };
  });
  const columns = new TestElement('columns');
  const category = new TestElement('column', { categoryId: '27' });
  const deleteButton = new TestElement('delete-button');
  category.appendChild(deleteButton);
  columns.appendChild(category);
  board.appendChild(columns);

  await board.listeners.click({ target: deleteButton });

  assert.equal(requestURL, '/delete-category');
  assert.equal(requestOptions.method, 'POST');
  assert.equal(requestOptions.credentials, 'same-origin');
  assert.deepEqual(formDataInstances[0].values, [
    ['SecurityID', 'csrf-test-token'],
    ['CategoryID', '27'],
  ]);
  assert.deepEqual(columns.children, []);
  assert.equal(board.status.textContent, 'Leere Kategorie gelöscht.');
});

test('bricht das Löschen ab, solange die Bestätigung nicht erteilt ist', async () => {
  let requestCount = 0;
  const { board } = createBoardEnvironment(async () => {
    requestCount++;
    return { ok: true, json: async () => ({ success: true }) };
  }, true, 'du', () => false);
  const category = new TestElement('column', { categoryId: '27' });
  const deleteButton = new TestElement('delete-button');
  category.appendChild(deleteButton);
  board.appendChild(category);

  await board.listeners.click({ target: deleteButton });

  assert.equal(requestCount, 0);
  assert.equal(category.parentElement, board);
});

test('öffnet das Formular barrierearm und legt die Kategorie direkt im Board an', async () => {
  let requestURL = '';
  let requestOptions = null;
  const { board, formDataInstances, clickCategoryButton } = createBoardEnvironment(async (url, options) => {
    requestURL = url;
    requestOptions = options;
    return { ok: true, json: async () => ({ success: true, category: { id: 33, title: 'Qualität', sort: 70 } }) };
  });
  board.categoryForm.querySelector = (selector) => {
    if (selector === '[data-category-title-input]') return board.categoryNameInput;
    if (selector === '[data-cancel-category-form]') return board.cancelCategoryFormButton;
    return null;
  };
  board.categoryNameInput.value = '  Qualität  ';
  assert.equal(board.categoryModal.hidden, true);
  clickCategoryButton();
  assert.equal(board.categoryModal.hidden, false);
  assert.equal(board.categoryNameInput.focusCount, 1);

  await board.categorySubmitButton.listeners.click();

  assert.equal(requestURL, '/create-category');
  assert.equal(requestOptions.method, 'POST');
  assert.equal(requestOptions.credentials, 'same-origin');
  assert.deepEqual(formDataInstances[0].values, [
    ['SecurityID', 'csrf-test-token'],
    ['Title', 'Qualität'],
  ]);
  assert.equal(board.columnsContainer.children.length, 1);
  assert.equal(board.columnsContainer.children[0].dataset.categoryId, '33');
  assert.equal(board.columnsContainer.children[0].children[0].children[1].textContent, 'Qualität');
  assert.equal(board.categoryModal.hidden, true);
  assert.equal(board.status.textContent, 'Kategorie wurde angelegt.');
});

test('öffnet Kategorie-Overlay durch einen echten delegierten Klick auch ohne Frontend-Übersetzungs-JavaScript', () => {
  const { board, clickCategoryButton } = createBoardEnvironment(undefined, true, 'du', () => true, 'complete', false);

  assert.equal(board.categoryModal.hidden, true);
  clickCategoryButton();

  assert.equal(board.categoryModal.hidden, false);
  assert.equal(board.categoryNameInput.focusCount, 1);
});

test('schließt den Kategorie-Dialog mit Abbrechen, Escape und Klick auf den Hintergrund', () => {
  const { board, clickCategoryButton } = createBoardEnvironment();
  clickCategoryButton();
  assert.equal(board.categoryModal.hidden, false);
  board.cancelCategoryFormButton.listeners.click();
  assert.equal(board.categoryModal.hidden, true);
  assert.equal(board.openCategoryFormButton.focusCount, 1);

  clickCategoryButton();
  board.listeners.keydown({ key: 'Escape' });
  assert.equal(board.categoryModal.hidden, true);

  clickCategoryButton();
  board.categoryModal.listeners.click({ target: board.categoryModal });
  assert.equal(board.categoryModal.hidden, true);
});

test('öffnet das Kategorie-Overlay auch vor DOMContentLoaded über den delegierten CMS-Klick', () => {
  const { board, clickCategoryButton } = createBoardEnvironment(undefined, true, 'du', () => true, 'loading');

  assert.equal(board.openCategoryFormButton.listeners.click, undefined);
  assert.equal(board.categoryModal.hidden, true);
  clickCategoryButton();

  assert.equal(board.categoryModal.hidden, false);
  assert.equal(board.categoryNameInput.focusCount, 1);
});

test('öffnet das Kategorie-Overlay nach einer SilverStripe-PJAX-Navigation über den delegierten Klick', () => {
  const { board, setBoardAvailable, triggerMutation, clickCategoryButton } = createBoardEnvironment(undefined, false);

  assert.equal(board.openCategoryFormButton.listeners.click, undefined);
  setBoardAvailable(true);
  triggerMutation();
  clickCategoryButton();

  assert.equal(board.categoryModal.hidden, false);
});

test('behält den Kategorienamen im offenen Formular, wenn das Speichern fehlschlägt', async () => {
  const { board, clickCategoryButton } = createBoardEnvironment(async () => ({
    ok: false,
    json: async () => ({ success: false, message: 'Kategorie konnte nicht angelegt werden.' }),
  }));
  board.categoryNameInput.value = 'Support';
  clickCategoryButton();

  await board.categorySubmitButton.listeners.click();

  assert.equal(board.categoryModal.hidden, false);
  assert.equal(board.categoryNameInput.value, 'Support');
  assert.equal(board.categoryNameInput.focusCount, 2);
  assert.equal(board.status.textContent, 'Kategorie konnte nicht angelegt werden.');
});

test('sendet keinen leeren Kategorienamen an den Server', async () => {
  let requestCount = 0;
  const { board } = createBoardEnvironment(async () => {
    requestCount++;
    return { ok: true, json: async () => ({ success: true }) };
  });

  await board.categorySubmitButton.listeners.click();

  assert.equal(requestCount, 0);
  assert.equal(board.status.textContent, 'Bitte gib einen Kategorienamen ein.');
  assert.equal(board.categoryNameInput.focusCount, 1);
});

test('öffnet und schließt das Formular für neue Einträge als CMS-Overlay', () => {
  const { board } = createBoardEnvironment();
  assert.equal(board.entryModal.hidden, true);
  board.openEntryFormButton.listeners.click();
  assert.equal(board.entryModal.hidden, false);
  assert.equal(board.entryForm.contentInput.focusCount, 1);
  board.entryModal.listeners.click({ target: board.entryModal });
  assert.equal(board.entryModal.hidden, true);
  assert.equal(board.openEntryFormButton.focusCount, 1);
});

test('speichert einen neuen Eintrag aus dem CMS-Overlay mit CSRF-Token und lädt das CMS-Board neu', async () => {
  let requestURL = '';
  let requestOptions = null;
  const { board, formDataInstances, reloadCount } = createBoardEnvironment(async (url, options) => {
    requestURL = url;
    requestOptions = options;
    return { ok: true, json: async () => ({ success: true, message: 'Eintrag wurde gespeichert.' }) };
  });
  board.openEntryFormButton.listeners.click();

  await board.entryForm.listeners.submit({ preventDefault() {} });

  assert.equal(requestURL, '/__masha-feedly/createEntry');
  assert.equal(requestOptions.method, 'POST');
  assert.equal(requestOptions.credentials, 'same-origin');
  assert.deepEqual(formDataInstances[0].values, [
    ['Content', 'Ein Testeintrag'],
    ['SecurityID', 'csrf-test-token'],
  ]);
  assert.equal(board.entryModal.hidden, true);
  assert.equal(board.status.textContent, 'Eintrag wurde gespeichert.');
  assert.equal(reloadCount(), 1);
});

test('lässt das CMS-Overlay bei einem fehlgeschlagenen Eintrag offen und bewahrt den Text', async () => {
  const { board, reloadCount } = createBoardEnvironment(async () => ({
    ok: false,
    json: async () => ({ success: false, message: 'Eintrag konnte nicht gespeichert werden.' }),
  }));
  board.openEntryFormButton.listeners.click();

  await board.entryForm.listeners.submit({ preventDefault() {} });

  assert.equal(board.entryModal.hidden, false);
  assert.equal(board.entryForm.contentInput.value, 'Ein Testeintrag');
  assert.equal(board.entryForm.status.textContent, 'Eintrag konnte nicht gespeichert werden.');
  assert.equal(reloadCount(), 0);
  assert.equal(board.entryForm.submitButton.disabled, false);
});


test('Mite configuration shows project and categories only when enabled', () => {
  const miteEnabled = new TestElement('mite-enabled');
  miteEnabled.checked = false;
  const settings = new TestElement('mite-settings');
  const { documentListeners } = createBoardEnvironment(undefined, false, 'du', () => true, 'complete', true, null, { miteEnabled, miteSettings: [settings] });
  assert.equal(settings.hidden, true);
  miteEnabled.checked = true;
  documentListeners.change({ target: miteEnabled });
  assert.equal(settings.hidden, false);
  miteEnabled.checked = false;
  documentListeners.change({ target: miteEnabled });
  assert.equal(settings.hidden, true);
});
