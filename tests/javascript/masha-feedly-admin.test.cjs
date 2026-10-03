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
    if (selector === '.masha-feedly-board__card' && this.type === 'card') return this;
    if (selector === '.masha-feedly-board__drag-handle' && this.type === 'handle') return this;
    if (selector === '.masha-feedly-board__list' && this.type === 'list') return this;
    return this.parentElement?.closest(selector) || null;
  }

  querySelector(selector) {
    if (selector === '.masha-feedly-board__status') return this.status;
    if (selector === '[data-masha-feedly-assignee-filter]') return this.assigneeFilter || null;
    if (selector === '.masha-feedly-board__list') return this.list || null;
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

  remove() {
    if (this.parentElement?.badges) {
      for (const [type, badge] of Object.entries(this.parentElement.badges)) {
        if (badge === this) this.parentElement.badges[type] = null;
      }
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
    return this.children.includes(element);
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
}), unreadGeneralCount = null, hasBoard = true, serverMenuTitle = null, unreadPersonalCount = 0, formalAddress = 'du') {
  const board = new TestElement('board', {
    moveUrl: '/move-entry',
    securityId: 'csrf-test-token',
  });
  board.assigneeFilter = new TestElement('select');
  board.assigneeFilter.value = '';
  const formDataInstances = [];
  class TestFormData {
    constructor() {
      this.values = [];
      formDataInstances.push(this);
    }

    append(name, value) {
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
  const document = {
    body: {},
    querySelector: (selector) => {
      if (selector === '[data-masha-feedly-unread-general-count]') return marker;
      if (selector === '[data-masha-feedly-board]') return hasBoard ? board : null;
      return null;
    },
    querySelectorAll: (selector) => selector === '#cms-menu a[href*="masha-feedly"]' ? [menuLink] : [],
    createElement: () => new TestElement('badge'),
  };
  let mutationCallback = null;
  class TestMutationObserver {
    constructor(callback) {
      mutationCallback = callback;
    }

    observe() {}
  }
  const dictionary = {
    MENU_GENERAL_UNREAD: '{count} neue Einträge für alle',
    MENU_PERSONAL_UNREAD_DU: '{count} neue Einträge für dich',
    MENU_PERSONAL_UNREAD_SIE: '{count} neue Einträge für Sie',
    BOARD_SAVING: 'Änderung wird gespeichert …',
    BOARD_SAVE_ERROR: 'Speichern fehlgeschlagen.',
    BOARD_SAVE_SUCCESS: 'Eintrag wurde gespeichert.',
    BOARD_SAVE_FAILURE: 'Eintrag konnte nicht gespeichert werden.',
  };
  vm.runInNewContext(source, {
    document,
    window: {
      KWMashaFeedlyTranslations: { FORMAL_ADDRESS: formalAddress },
      KWMashaFeedlyTranslate(key, values = {}) {
        return Object.entries(values).reduce((message, [name, value]) => message.replaceAll(`{${name}}`, String(value)), dictionary[key] || key);
      },
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
