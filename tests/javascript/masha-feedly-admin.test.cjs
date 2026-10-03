const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

/** JavaScript-Tests für Sortierung und Verschieben im Masha-Feedly-Board. @author Kooperative Web */
const sourcePath = path.resolve(__dirname, '../../client/src/js/masha-feedly-admin.js');
const source = fs.readFileSync(sourcePath, 'utf8');

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
    return this.parentElement?.closest(selector) || null;
  }

  querySelector(selector) {
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
    if (selector.startsWith('.masha-feedly-menu__badge--')) {
      return this.badges?.[selector.replace('.masha-feedly-menu__badge--', '')] || null;
    }
    return null;
  }

  setAttribute(name, value) {
    this.attributes ??= {};
    this.attributes[name] = value;
  }

  getAttribute(name) {
    return this.attributes?.[name] ?? null;
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
    if (this.parentElement?.badges) {
      for (const [type, badge] of Object.entries(this.parentElement.badges)) {
        if (badge === this) this.parentElement.badges[type] = null;
      }
    }
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
    if (element.classList.contains('masha-feedly-menu__badge--general')) {
      this.badges ??= {};
      this.badges.general = element;
    }
    if (element.classList.contains('masha-feedly-menu__badge--personal')) {
      this.badges ??= {};
      this.badges.personal = element;
    }
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
  }), unreadGeneralCount = null, hasBoard = true, serverMenuTitle = null, unreadPersonalCount = 0, formalAddress = 'du', confirmImplementation = () => true, documentReadyState = 'complete', includeGlobalTranslator = true) {
  const board = new TestElement('board', {
    moveUrl: '/move-entry',
    moveCategoryUrl: '/move-category',
    deleteCategoryUrl: '/delete-category',
    createCategoryUrl: '/create-category',
    createEntryUrl: '/__masha-feedly/createEntry',
    securityId: 'csrf-test-token',
    adminTranslations: JSON.stringify({
      MENU_GENERAL_UNREAD: '{count} neue Einträge für alle',
      MENU_PERSONAL_UNREAD_DU: '{count} neue Einträge für dich',
      MENU_PERSONAL_UNREAD_SIE: '{count} neue Einträge für Sie',
      BOARD_CATEGORY_DRAG_ARIA: 'Kategorie sortieren',
      BOARD_CATEGORY_DRAG_TITLE: 'Kategorie zum Sortieren ziehen',
      BOARD_CATEGORY_DELETE: 'Leere Kategorie löschen',
    }),
  });
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

  const menuTitle = {
    value: serverMenuTitle ?? (unreadGeneralCount === null
      ? 'Masha:Feedly'
      : `Masha:Feedly (${unreadGeneralCount}/${unreadPersonalCount})`),
  };
  let titleWrites = 0;
  Object.defineProperty(menuTitle, 'textContent', {
    get() { return this.value; },
    set(value) {
      if (this.value !== value) titleWrites++;
      this.value = value;
    },
    configurable: true,
  });
  const menuLink = new TestElement('menu-link');
  menuLink.textElement = menuTitle;
  const marker = unreadGeneralCount === null ? null : {
    dataset: {
      mashaFeedlyUnreadGeneralCount: String(unreadGeneralCount),
      mashaFeedlyUnreadPersonalCount: String(unreadPersonalCount),
    },
  };
  let boardAvailable = hasBoard;
  const documentListeners = {};
  const document = {
    body: {},
    readyState: documentReadyState,
    addEventListener: (name, callback) => { documentListeners[name] = callback; },
    querySelector: (selector) => {
      if (selector === '[data-masha-feedly-unread-general-count]') return marker;
      if (selector === '[data-masha-feedly-board]') return boardAvailable ? board : null;
      return null;
    },
    querySelectorAll: (selector) => selector === '#cms-menu a[href*="masha-feedly"]' ? [menuLink] : [],
    createElement: (type) => new TestElement(type),
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
    MENU_GENERAL_UNREAD: '{count} neue Einträge für alle',
    MENU_PERSONAL_UNREAD_DU: '{count} neue Einträge für dich',
    MENU_PERSONAL_UNREAD_SIE: '{count} neue Einträge für Sie',
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
    window: {
      location: { reload: () => { reloadCount++; } },
      KWMashaFeedlyTranslations: { FORMAL_ADDRESS: formalAddress },
      KWMashaFeedlyTranslate: includeGlobalTranslator
        ? (key, values = {}) => Object.entries(values).reduce((message, [name, value]) => message.replaceAll(`{${name}}`, String(value)), dictionary[key] || key)
        : undefined,
      confirm: confirmImplementation,
    },
    FormData: TestFormData,
    fetch: fetchImplementation,
    MutationObserver: TestMutationObserver,
  });

  return {
    board,
    menuLink,
    formDataInstances,
    menuTitle,
    triggerMutation: () => mutationCallback?.(),
    titleWrites: () => titleWrites,
    documentListeners,
    clickCategoryButton: () => documentListeners.click?.({ target: board.openCategoryFormButton }),
    setBoardAvailable: (available) => { boardAvailable = available; },
    reloadCount: () => reloadCount,
  };
}

test('zeigt die Menü-Badge an, ohne den MutationObserver in eine Aktualisierungsschleife zu schicken', () => {
  const { menuTitle, menuLink, triggerMutation, titleWrites } = createBoardEnvironment(async () => ({}), 3);

  assert.equal(menuTitle.textContent, 'Masha:Feedly');
  assert.equal(menuLink.badges.general.textContent, '3');
  assert.equal(menuLink.badges.general.attributes['aria-label'], '3 neue Einträge für alle');
  const writesAfterInitialBadge = titleWrites();
  const badgeWritesAfterInitialRender = menuLink.badges.general.textContentMutationCount;
  triggerMutation();
  triggerMutation();
  assert.equal(titleWrites(), writesAfterInitialBadge);
  assert.equal(menuLink.badges.general.textContentMutationCount, badgeWritesAfterInitialRender);
});

test('zeigt die Menü-Badge auch auf anderen CMS-Seiten ohne Masha-Feedly-Board', () => {
  const { menuTitle, menuLink } = createBoardEnvironment(async () => ({}), null, false, 'Masha:Feedly (4/2)');

  assert.equal(menuLink.badges?.general?.textContent, '4');
  assert.equal(menuLink.badges?.personal?.textContent, '2');
  assert.equal(menuTitle.textContent, 'Masha:Feedly');
});

test('kennzeichnet persönlich zugeordnete neue Einträge mit einer eigenen Badge', () => {
  const { menuLink } = createBoardEnvironment(async () => ({}), 0, true, null, 2);

  assert.equal(menuLink.badges?.general ?? null, null);
  assert.equal(menuLink.badges.personal.textContent, '2');
  assert.equal(menuLink.badges.personal.classList.contains('masha-feedly-menu__badge--personal'), true);
  assert.equal(menuLink.badges.personal.attributes['aria-label'], '2 neue Einträge für dich');
});

test('verwendet die konfigurierte Sie-Anrede in der persönlichen Menü-Badge', () => {
  const { menuLink } = createBoardEnvironment(async () => ({}), 0, true, null, 2, 'sie');
  assert.equal(menuLink.badges.personal.attributes['aria-label'], '2 neue Einträge für Sie');
});

test('entfernt die gelbe Menü-Badge, sobald keine Einträge mehr ungelesen sind', () => {
  const { menuLink } = createBoardEnvironment(async () => ({}), 0);
  assert.equal(menuLink.badges?.general ?? null, null);
});

test('blendet die Menü-Badge nach dem Lesen des letzten Eintrags aus', async () => {
  const { board, menuLink } = createBoardEnvironment(async () => ({
    ok: true,
    json: async () => ({ success: true, unreadGeneralCount: 0, unreadPersonalCount: 0 }),
  }), 1);
  const list = new TestElement('list', { categoryId: '1' });
  const card = new TestElement('card', { entryId: '42' });
  list.appendChild(card);

  startDragging(board, card);
  await board.listeners.drop({ target: list, preventDefault() {} });

  assert.equal(menuLink.badges?.general ?? null, null);
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
  const { board, formDataInstances, menuTitle, menuLink } = createBoardEnvironment(async (url, options) => {
    requestURL = url;
    requestOptions = options;
    return { ok: true, json: async () => ({ success: true, unreadGeneralCount: 2, unreadPersonalCount: 1 }) };
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
  assert.equal(menuTitle.textContent, 'Masha:Feedly');
  assert.equal(menuLink.badges.general.textContent, '2');
  assert.equal(menuLink.badges.personal.textContent, '1');
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
  }, null, true, null, 0, 'du', () => false);
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
  const { board, clickCategoryButton } = createBoardEnvironment(undefined, null, true, null, 0, 'du', () => true, 'complete', false);

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
  const { board, clickCategoryButton } = createBoardEnvironment(undefined, null, true, null, 0, 'du', () => true, 'loading');

  assert.equal(board.openCategoryFormButton.listeners.click, undefined);
  assert.equal(board.categoryModal.hidden, true);
  clickCategoryButton();

  assert.equal(board.categoryModal.hidden, false);
  assert.equal(board.categoryNameInput.focusCount, 1);
});

test('öffnet das Kategorie-Overlay nach einer SilverStripe-PJAX-Navigation über den delegierten Klick', () => {
  const { board, setBoardAvailable, triggerMutation, clickCategoryButton } = createBoardEnvironment(undefined, null, false);

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
