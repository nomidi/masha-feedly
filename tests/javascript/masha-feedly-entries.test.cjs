const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

/** Tests für Sortierung, Kategorien und am Seitenbereich verankerte Eintragsblasen. @author Kooperative Web */
const source = fs.readFileSync(path.resolve(__dirname, '../../client/src/js/masha-feedly-entries.js'), 'utf8');
const compiledSource = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly-entries.js'), 'utf8');
const effectSources = ['unicorn', 'rocket', 'hearts', 'arcade', 'retro', 'dino', 'ducks', 'frogs', 'icon-shower', 'ghost-swarm', 'potion', 'cat-paws', 'flower-power', 'pinball-tilt', 'check', 'glow', 'rings', 'confirmation', 'runner'].map((name) => fs.readFileSync(path.resolve(__dirname, `../../client/src/js/effects/${name}.js`), 'utf8'));
const compiledEffectSources = ['unicorn', 'rocket', 'hearts', 'arcade', 'retro', 'dino', 'ducks', 'frogs', 'icon-shower', 'ghost-swarm', 'potion', 'cat-paws', 'flower-power', 'pinball-tilt', 'check', 'glow', 'rings', 'confirmation', 'runner'].map((name) => fs.readFileSync(path.resolve(__dirname, `../../client/dist/js/effects/${name}.js`), 'utf8'));
const scss = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly.scss'), 'utf8');
const compiledStyles = fs.readFileSync(path.resolve(__dirname, '../../client/dist/css/masha-feedly.css'), 'utf8');
const compiledAdminStyles = fs.readFileSync(path.resolve(__dirname, '../../client/dist/css/masha-feedly-admin.css'), 'utf8');
const effectStyles = ['_confetti.scss', '_unicorn.scss', '_rocket.scss', '_hearts.scss', '_arcade.scss', '_retro.scss', '_dino.scss', '_check.scss', '_glow.scss', '_rings.scss', '_confirmation.scss', '_ducks.scss', '_frogs.scss', '_icon-shower.scss', '_ghost-swarm.scss', '_potion.scss', '_cat-paws.scss', '_flower-power.scss', '_pinball-tilt.scss'].map((name) => fs.readFileSync(path.resolve(__dirname, `../../client/src/scss/effects/${name}`), 'utf8'));
const widgetTemplate = fs.readFileSync(path.resolve(__dirname, '../../templates/KW/MashaFeedly/Includes/MashaFeedlyWidget.ss'), 'utf8');
const germanTranslations = fs.readFileSync(path.resolve(__dirname, '../../lang/de.yml'), 'utf8');
const messages = {
  ENTRY_CONTEXT_STATUS: 'Status: {status}', ENTRY_WITHOUT_CATEGORY: 'Ohne Kategorie',
  ENTRY_NO_DESCRIPTION: 'Keine Beschreibung vorhanden.', ENV_LOGGED_AT: 'Erfasst am', ENV_PAGE: 'Seite',
  ENV_OPERATING_SYSTEM: 'Betriebssystem', ENV_BROWSER: 'Browser', ENV_SELECTED_AREA: 'Ausgewählter Bereich',
  ENV_ELEMENT_TEXT: 'Text im Bereich', ENV_RESOLUTION: 'Bildschirmauflösung', ENV_BROWSER_WINDOW: 'Browserfenster',
  ENV_COLOR_DEPTH: 'Farbtiefe', ENV_USER_AGENT: 'Browserkennung', ASSIGNEES_ARIA: 'Verantwortliche',
  MEMBER_FALLBACK: 'Mitglied', ENTRY_MARKER_ARIA: 'Eintrag {number}: {title}', ENTRY_MARKER_TITLE: '{category} · {title}',
  HISTORY_STATUS_CHANGE: 'Status: {oldValue} → {newValue}', HISTORY_ASSIGNEES_CHANGE: 'Zuständigkeit: {oldValue} → {newValue}',
  HISTORY_RELATIONS_CHANGE: 'Verknüpfungen: {oldValue} → {newValue}', HISTORY_NO_RELATIONS: 'Keine Verknüpfungen',
  HISTORY_REPORTED_BY: 'Meldeperson: {oldValue} → {newValue}',
  HISTORY_CREATED: 'Eintrag erstellt: {title}', HISTORY_COMMENT: 'Kommentar: {text}',
  HISTORY_ATTACHMENT: 'Datei hochgeladen: {text}',
  HISTORY_COMMENT_EDITED: 'Kommentar bearbeitet: {oldValue} → {newValue}',
  HISTORY_COMMENT_DELETED: 'Kommentar gelöscht: {text}',
  HISTORY_COMMENT_REACTION: 'Reaktion auf Kommentar geändert: {oldValue} → {newValue}', HISTORY_NO_REACTION: 'Keine Reaktion',
  HISTORY_NOBODY: 'Niemand', HISTORY_META: '{actor} · {when}', HISTORY_PRIORITY_CHANGE: 'Priorität: {oldValue} → {newValue}',
  HISTORY_DUE_DATE_CHANGE: 'Fälligkeit: {oldValue} → {newValue}', NO_DUE_DATE: 'Kein Termin', ENTRY_DUE_DATE: 'Fällig am {date}',
  ENTRY_NUMBER: 'Eintrag #{id}', ENTRY_REPORTED_BY: 'Gemeldet von {author}', ENTRY_CREATED_UNKNOWN: 'Unbekannt', CATEGORY_ALL: 'Alle Kategorien', LIST_COUNT_MINE_DU: 'für dich',
  LIST_COUNT_MINE_SIE: 'für Sie', LIST_COUNT_ALL: 'insgesamt', LIST_COUNT_PAGE: 'auf dieser Seite',
  LIST_COUNT_UNREAD: 'mit neuen Aktivitäten', NEWS_BUTTON_DU: 'Neu seit deinem letzten Besuch',
  NEWS_BUTTON_SIE: 'Neu seit Ihrem letzten Besuch', NEWS_EMPTY: 'Alles ist auf dem neuesten Stand.',
  LIST_COUNT_OPEN: 'offen', LIST_COUNT_CLOSED: 'abgeschlossen', LIST_COUNT_FEEDBACK: 'warten auf Feedback',
  OPEN_ALL_LABEL: 'Gesamte Website', OPEN_PAGE_LABEL: 'Aktuelle Seite',
  FILTER_OPEN: 'Offene Einträge', FILTER_CLOSED: 'Abgeschlossene Einträge',
  FILTER_ALL_PRIORITIES: 'Alle Prioritäten', SAVED_VIEW_NONE: 'Ansicht auswählen …',
  SAVED_VIEW_SAVED: 'Ansicht gespeichert.', SAVED_VIEW_DELETED: 'Ansicht gelöscht.',
  SAVED_VIEW_ERROR: 'Ansicht konnte nicht gespeichert werden.', SAVED_VIEW_DELETE_ERROR: 'Ansicht konnte nicht gelöscht werden.',
  ENTRY_SINGULAR: 'Eintrag', ENTRY_PLURAL: 'Einträge', EMPTY_MINE_DU: 'Du hast noch keine Einträge.',
  EMPTY_MINE_SIE: 'Sie haben noch keine Einträge.', EMPTY_ALL: 'Es gibt noch keine Einträge.',
  EMPTY_PAGE: 'Auf dieser Seite gibt es noch keine Einträge.', ENTRY_WITHOUT_TITLE: 'Eintrag ohne Titel',
  EMPTY_OPEN: 'Es gibt keine offenen Einträge.', EMPTY_CLOSED: 'Es gibt keine behobenen Einträge.',
  ENTRY_OPEN_ARIA: 'Eintrag öffnen: {title}', ENTRY_CONTEXT_AREA: 'Bereich: {text}', ENTRY_PAGE_LINK: 'Zur Seite wechseln',
  ENTRY_SHARE_ARIA: 'Link zu {title} teilen', ENTRY_SHARE_DONE: 'Direktlink wurde geteilt oder kopiert',
  ENTRY_SHARE_ERROR: 'Link konnte nicht geteilt werden',
  ENTRY_RELATIONS_ARIA: 'Verknüpfte Einträge', RELATION_RELATED: 'Thematisch verwandt',
  RELATION_RELATED_TO: 'Thematisch verwandt mit', RELATION_BLOCKED_BY: 'Blockiert durch',
  RELATION_BLOCKS: 'Blockiert', RELATION_DUPLICATE_OF: 'Duplikat von', RELATION_HAS_DUPLICATE: 'Hat Duplikat',
  ENTRY_PRIORITY_ARIA: 'Priorität: {priority}', PRIORITY_FALLBACK: 'Keine Priorität',
  LIST_LOADING: 'Einträge werden geladen …', LIST_LOAD_ERROR: 'Einträge konnten nicht geladen werden.',
  RAINBOW_EMPTY_BOARD_TITLE: 'Bugfrei – oder noch nichts eingetragen!',
  RAINBOW_EMPTY_BOARD_MESSAGE: 'Hier wurde noch nichts erfasst. Wir feiern vorsichtshalber trotzdem.',
  RAINBOW_BOARD_TITLE: 'Alles im grünen Bereich!', RAINBOW_BOARD_MESSAGE: 'Alle Einträge sind erledigt oder archiviert. Stark!',
  RAINBOW_PAGE_TITLE: 'Auf dieser Seite keine Fehler',
  RAINBOW_PAGE_MESSAGE: 'Alle Meldungen auf dieser Seite sind erledigt oder archiviert. Stark!',
  RAINBOW_EMPTY_PAGE_MESSAGE: 'Hier wurde noch nichts eingetragen – vielleicht ist die Seite schon perfekt.',
  SUCCESS_EMPTY_BOARD_TITLE: 'Noch keine Einträge',
  SUCCESS_EMPTY_BOARD_MESSAGE: 'Für Masha:Feedly liegen noch keine Einträge vor.',
  SUCCESS_BOARD_TITLE: 'Keine offenen Einträge', SUCCESS_BOARD_MESSAGE: 'Alle Einträge sind abgeschlossen oder archiviert.',
  SUCCESS_PAGE_TITLE: 'Keine offenen Einträge auf dieser Seite',
  SUCCESS_PAGE_MESSAGE: 'Auf dieser Seite gibt es derzeit keine offenen Einträge.',
  SUCCESS_EMPTY_PAGE_MESSAGE: 'Für diese Seite wurden noch keine Einträge erfasst.',
  EDIT_SAVING: 'Änderungen werden gespeichert …', EDIT_SAVE_ERROR: 'Änderungen konnten nicht gespeichert werden.',
  COMMENT_SAVING: 'Kommentar wird gesendet …',
  COMMENT_SAVED: 'Kommentar gesendet.',
  COMMENT_SAVE_ERROR: 'Kommentar konnte nicht gesendet werden.',
  COMMENT_UPDATE_ERROR: 'Kommentar konnte nicht gespeichert werden.',
  COMMENT_DELETE_ERROR: 'Kommentar konnte nicht gelöscht werden.',
  UNREAD_ACTIVITY: 'Neue Aktivität',
  NEWS_TITLE: 'Neuigkeiten',
  NEWS_SUMMARY: 'Einträge: {entries} · Kommentare: {comments}',
  OPEN_FEEDBACK_ENTRIES: 'Einträge anzeigen, bei denen Feedback aussteht',
  OPEN_CLOSED_ENTRIES: 'Abgeschlossene Einträge ansehen',
  FILTERS_TITLE: 'Filter', FILTERS_NONE: 'Keine aktiv', FILTERS_ACTIVE: '{count} aktiv',
  FILTER_REMOVE: 'Filter entfernen: {label}', FILTERS_CLEAR: 'Alle Filter zurücksetzen',
  FILTER_MODE: 'Ansicht', FILTER_OPEN: 'Offene Einträge', FILTER_PAGE_OPEN: 'Offene Fehler hier',
  SORTING_TITLE: 'Sortierung', SORT_DUE: 'Fälligkeit', SORT_CREATED: 'Erstellt am', SORT_PRIORITY: 'Priorität', SORT_ASSIGNEE: 'Zuständigkeit', SORT_ACTIVITY: 'Letzte Aktivität', SORT_ASCENDING: 'aufsteigend', SORT_DESCENDING: 'absteigend',
  FILTER_FEEDBACK: 'Wartet auf Feedback', FILTER_CLOSED: 'Abgeschlossene Einträge', FILTER_ALL: 'Alle Einträge',
  FILTER_PAGE: 'Alle Einträge auf dieser Seite', FILTER_MINE: 'Für mich', FILTER_UNREAD: 'Neuigkeiten',
  FILTER_CATEGORY: 'Kategorie', FILTER_PRIORITY: 'Priorität',
  COMMENT_REACTIONS: 'Reaktionen auf diesen Kommentar', COMMENT_REACTION_LIKE: 'Gefällt mir',
  COMMENT_REACTION_LOVE: 'Herz', COMMENT_REACTION_LAUGH: 'Lachen', COMMENT_REACTION_CRY: 'Weinen',
  COMMENT_REACTION_SURPRISED: 'Überrascht', COMMENT_REACTION_THANKS: 'Danke',
  COMMENT_REACTION_ADD: '{reaction}: Reaktion hinzufügen. Bisher {count}.',
  COMMENT_REACTION_REMOVE: '{reaction}: eigene Reaktion entfernen. Insgesamt {count}.',
  COMMENT_REACTION_SAVED: 'Reaktion gespeichert.', COMMENT_REACTION_ERROR: 'Reaktion konnte nicht gespeichert werden.',
  COMMENT_REACTION_PICKER_OPEN: 'Mit diesem Kommentar reagieren',
  COMMENT_REACTION_PICKER_CLOSE: 'Reaktionsauswahl schließen', COMMENT_REACTION_PICKER_TITLE: 'Reaktion auswählen',
};
const translate = (key, values = {}) => Object.entries(values).reduce(
  (message, [name, value]) => message.replaceAll(`{${name}}`, String(value)), messages[key] || key
);
const document = { addEventListener() {} };
const window = {
  KWMashaFeedlyTranslate: translate,
  KWMashaFeedlyTranslations: {},
  KWMashaFeedlyEffectModules: {},
};
effectSources.forEach((effectSource) => vm.runInNewContext(effectSource, { window }));
vm.runInNewContext(source, { document, window, URL });
const entriesUI = window.KWMashaFeedlyEntries;
const sharedEffects = window.KWMashaFeedlyEffects;
test('beschriftet den Zähler als Zahl abgeschlossener Einträge und nennt die Zielübersicht', () => {
  assert.match(germanTranslations, /OPEN_CLOSED_ENTRIES: 'Abgeschlossene Einträge ansehen'/);
  assert.match(germanTranslations, /CLOSED_ENTRIES_BUTTON: 'abgeschlossene Einträge'/);
  assert.match(widgetTemplate, /data-masha-feedly-open-closed[^>]*aria-label="<%t KW\\MashaFeedly\\Translations\.OPEN_CLOSED_ENTRIES/);
  assert.match(widgetTemplate, /data-masha-feedly-closed-count>[^<]*<\/strong><span class="kw-masha-feedly__sr-only"><%t KW\\MashaFeedly\\Translations\.CLOSED_ENTRIES_BUTTON/);
});

test('zeigt Urheber und lokalen Erstellungszeitpunkt direkt am geöffneten Eintrag', () => {
  const meta = entriesUI.entryCreationMeta({
    createdBy: 'Fallback',
    createdAt: '2026-10-04T12:00:00Z',
    createdByInitials: 'EM',
    createdByColor: '#123456',
    createdByImageURL: '/protected/profile.png',
    history: [
      { type: 'created', actor: 'Erika Muster', created: '2026-10-04T12:00:00Z' },
    ],
  });
  assert.equal(meta.author, 'Erika Muster');
  assert.equal(meta.initials, 'EM');
  assert.equal(meta.color, '#123456');
  assert.equal(meta.imageURL, '/protected/profile.png');
  assert.equal(meta.dateTime, '2026-10-04T12:00:00.000Z');
  assert.notEqual(meta.when, '');
  assert.match(widgetTemplate, /data-masha-feedly-entry-created[\s\S]*?data-masha-feedly-entry-created-avatar[\s\S]*?data-masha-feedly-entry-created-by[\s\S]*?data-masha-feedly-entry-created-at/);
  assert.match(scss, /\.kw-masha-feedly__entry-created \{[^}]*border-radius: 999px;[^}]*background: linear-gradient/);
  assert.match(compiledStyles, /\.kw-masha-feedly__entry-created\{[^}]*border-radius:999px;[^}]*background:linear-gradient/);
  assert.match(scss, /\.kw-masha-feedly__entry-created-avatar img \{ width: 100%; height: 100%; object-fit: cover; \}/);
  assert.match(compiledStyles, /\.kw-masha-feedly__entry-created-avatar img\{width:100%;height:100%;object-fit:cover\}/);
});

test('zeigt die konfigurierte Meldeperson statt des technischen Erstellers und rendert deren Änderung im Verlauf', () => {
  const meta = entriesUI.entryCreationMeta({
    reportedByName: 'Historische Meldung von Ada',
    createdBy: 'Historische Meldung von Ada',
    history: [
      { type: 'created', actor: 'CMS Betreiber', created: '2026-10-04T12:00:00Z' },
    ],
  });
  assert.equal(meta.author, 'Historische Meldung von Ada');

  const rows = [];
  const container = { replaceChildren() { rows.length = 0; }, append(row) { rows.push(row); } };
  const doc = {
    createElement(tag) {
      return { tag, children: [], append(...children) { this.children.push(...children); }, set textContent(value) { this.text = value; }, get textContent() { return this.text; } };
    },
  };
  entriesUI.renderHistory(container, [{ type: 'reported_by', oldValue: 'CMS Betreiber', newValue: 'Historische Meldung von Ada', actor: 'Betreiber', created: '2026-10-04T12:00:00Z' }], doc);
  assert.equal(rows[0].className, 'kw-masha-feedly__history-item is-reported-by');
  assert.equal(rows[0].children[0].text, 'Meldeperson: CMS Betreiber → Historische Meldung von Ada');
});

test('zeigt beim Ersteller dasselbe Profilbild wie bei Zuständigkeiten und nutzt Initialen als Fallback', () => {
  const images = [];
  const fakeDocument = { createElement: () => { const image = {}; images.push(image); return image; } };
  const avatar = {
    style: {},
    children: [],
    replaceChildren() { this.children = []; this.textContent = ''; },
    append(child) { this.children.push(child); },
  };
  entriesUI.renderEntryCreatorAvatar(avatar, {
    author: 'Erika Muster', initials: 'EM', color: '#123456', imageURL: '/geschuetzt/profil.png',
  }, fakeDocument);
  assert.equal(images[0].src, '/geschuetzt/profil.png');
  assert.equal(images[0].alt, '');
  assert.equal(images[0].loading, 'lazy');
  assert.equal(avatar.style.backgroundColor, '#123456');
  assert.equal(avatar.children[0], images[0]);

  entriesUI.renderEntryCreatorAvatar(avatar, {
    author: 'Erika Muster', initials: 'EM', color: '#654321', imageURL: '',
  }, fakeDocument);
  assert.equal(avatar.textContent, 'EM');
  assert.equal(avatar.children.length, 0);
  assert.equal(avatar.style.backgroundColor, '#654321');
});

test('zentriert das Fragezeichen im Hilfe-Button unabhängig von Browser-Button-Padding', () => {
  assert.match(scss, /\.kw-masha-feedly__help-button \{[^}]*display: grid;[^}]*width: 60px; min-width: 60px; height: 60px; min-height: 60px; flex: 0 0 60px; aspect-ratio: 1;[^}]*place-items: center;[^}]*padding: 0;[^}]*line-height: 1;/);
  assert.match(scss, /\.kw-masha-feedly__help-button \{ display: grid; width: 52px; min-width: 52px; height: 52px; min-height: 52px; flex: 0 0 52px; aspect-ratio: 1; margin-top: 32px; padding: 0; place-items: center;[^}]*line-height: 1; \}/);
  assert.match(compiledStyles, /\.kw-masha-feedly__help-button\{display:grid;place-items:center;padding:0;line-height:1;font-family:inherit\}/);
});

test('sortiert die Einträge zuerst nach Kategorien und danach nach Datum', () => {
  const sorted = entriesUI.sortEntries([
    { id: 1, categoryID: 2, entryDate: '2026-10-01 12:00:00' },
    { id: 2, categoryID: 1, entryDate: '2026-10-01 09:00:00' },
    { id: 3, categoryID: 2, entryDate: '2026-10-01 15:00:00' },
  ], [{ id: 1 }, { id: 2 }]);
  assert.deepEqual(Array.from(sorted, (entry) => entry.id), [2, 3, 1]);
});

test('sortiert Fälligkeiten zuerst, bald fällige Einträge zuerst und Einträge ohne Termin danach', () => {
  const sorted = entriesUI.sortEntries([
    { id: 1, categoryID: 1, dueDate: '', createdAt: '2026-10-04T10:00:00Z' },
    { id: 2, categoryID: 1, dueDate: '2026-10-20', createdAt: '2026-10-04T11:00:00Z' },
    { id: 3, categoryID: 1, dueDate: '2026-10-05', createdAt: '2026-10-04T09:00:00Z' },
  ], [{ id: 1 }], [], { key: 'due', direction: 'asc' });
  assert.deepEqual(Array.from(sorted, (entry) => entry.id), [3, 2, 1]);
  const reverse = entriesUI.sortEntries([
    { id: 1, categoryID: 1, dueDate: '', createdAt: '2026-10-04T10:00:00Z' },
    { id: 2, categoryID: 1, dueDate: '2026-10-20', createdAt: '2026-10-04T11:00:00Z' },
    { id: 3, categoryID: 1, dueDate: '2026-10-05', createdAt: '2026-10-04T09:00:00Z' },
  ], [{ id: 1 }], [], { key: 'due', direction: 'desc' });
  assert.deepEqual(Array.from(reverse, (entry) => entry.id), [2, 3, 1]);
});

test('sortiert nach Erstellungszeit, Priorität und letzter Aktivität und kehrt ein aktives Kriterium beim erneuten Klick um', () => {
  const entries = [
    { id: 1, categoryID: 1, priorityID: 2, createdAt: '2026-10-01T10:00:00Z', history: [{ created: '2026-10-01T10:00:00Z' }] },
    { id: 2, categoryID: 1, priorityID: 1, createdAt: '2026-10-03T10:00:00Z', history: [{ created: '2026-10-04T10:00:00Z' }] },
    { id: 3, categoryID: 1, priorityID: 1, createdAt: '2026-10-02T10:00:00Z', history: [{ created: '2026-10-02T10:00:00Z' }] },
  ];
  const categories = [{ id: 1 }];
  const priorities = [{ id: 1 }, { id: 2 }];
  assert.deepEqual(Array.from(entriesUI.sortEntries(entries, categories, priorities, { key: 'created', direction: 'desc' }), (entry) => entry.id), [2, 3, 1]);
  assert.deepEqual(Array.from(entriesUI.sortEntries(entries, categories, priorities, { key: 'priority', direction: 'asc' }), (entry) => entry.id), [2, 3, 1]);
  assert.deepEqual(Array.from(entriesUI.sortEntries(entries, categories, priorities, { key: 'activity', direction: 'desc' }), (entry) => entry.id), [2, 3, 1]);
  assert.deepEqual(JSON.parse(JSON.stringify(entriesUI.toggleSorting({ key: 'activity', direction: 'desc' }, 'activity'))), { key: 'activity', direction: 'asc' });
  assert.deepEqual(JSON.parse(JSON.stringify(entriesUI.toggleSorting({ key: 'activity', direction: 'asc' }, 'due'))), { key: 'due', direction: 'asc' });
  assert.match(widgetTemplate, /data-masha-feedly-sort-details[\s\S]*?data-masha-feedly-sort-option="due"[\s\S]*?data-masha-feedly-sort-option="created"[\s\S]*?data-masha-feedly-sort-option="priority"[\s\S]*?data-masha-feedly-sort-option="activity"/);
  assert.match(widgetTemplate, /data-masha-feedly-sort-current[\s\S]*?data-masha-feedly-sort-label/);
  assert.match(scss, /\.kw-masha-feedly__sort-popover \{ position: absolute;[^}]*box-shadow:/);
  assert.match(scss, /\.kw-masha-feedly__sort-current \{ position: absolute;[^}]*border-radius: 50%;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__sort-popover\{position:absolute;[^}]*box-shadow:/);
});

test('sortiert alphabetisch nach Zuständigkeit, behandelt Mehrfachzuweisungen stabil und lässt Niemand am Ende', () => {
  const sorted = entriesUI.sortEntries([
    { id: 1, categoryID: 1, assignees: [] },
    { id: 2, categoryID: 1, assignees: [{ name: 'Zoe Beispiel' }, { name: 'Ada Muster' }] },
    { id: 3, categoryID: 1, assignees: [{ name: 'Berta Beispiel' }] },
  ], [{ id: 1 }], [], { key: 'assignee', direction: 'asc' });
  assert.deepEqual(Array.from(sorted, (entry) => entry.id), [2, 3, 1]);
  const reverse = entriesUI.sortEntries([
    { id: 1, categoryID: 1, assignees: [] },
    { id: 2, categoryID: 1, assignees: [{ name: 'Zoe Beispiel' }, { name: 'Ada Muster' }] },
    { id: 3, categoryID: 1, assignees: [{ name: 'Berta Beispiel' }] },
  ], [{ id: 1 }], [], { key: 'assignee', direction: 'desc' });
  assert.deepEqual(Array.from(reverse, (entry) => entry.id), [3, 2, 1]);
  assert.match(widgetTemplate, /data-masha-feedly-sort-option="assignee"/);
});

test('hält Tab und Umschalt+Tab im Fokusbereich eines Dialogs', () => {
  const first = { focus() { document.activeElement = first; }, getAttribute() { return null; }, hidden: false };
  const last = { focus() { document.activeElement = last; }, getAttribute() { return null; }, hidden: false };
  const container = {
    querySelectorAll: () => [first, last],
    contains: (element) => element === first || element === last,
  };
  document.activeElement = last;
  let prevented = false;
  assert.equal(entriesUI.trapFocus(container, { key: 'Tab', shiftKey: false, preventDefault() { prevented = true; } }), true);
  assert.equal(document.activeElement, first);
  assert.equal(prevented, true);
  document.activeElement = first;
  prevented = false;
  entriesUI.trapFocus(container, { key: 'Tab', shiftKey: true, preventDefault() { prevented = true; } });
  assert.equal(document.activeElement, last);
  assert.equal(prevented, true);
});

test('Dialoge sind modal beschriftet, fokussierbar und Statusfelder live angekündigt', () => {
  assert.match(widgetTemplate, /kw-masha-feedly__entries-dialog" role="dialog" aria-modal="true"/);
  assert.match(widgetTemplate, /kw-masha-feedly__edit-dialog" role="dialog" aria-modal="true"/);
  assert.match(widgetTemplate, /data-masha-feedly-list-heading tabindex="-1"/);
  assert.match(widgetTemplate, /data-masha-feedly-edit-heading tabindex="-1"/);
  assert.match(widgetTemplate, /data-masha-feedly-edit-priority role="img" aria-label="Priorität"/);
  assert.equal((widgetTemplate.match(/role="status" aria-live="polite" aria-atomic="true"/g) || []).length >= 3, true);
  assert.match(scss, /:focus-visible,[\s\S]*outline: 3px solid #a91c62/);
});

test('sortiert innerhalb des Status nach Priorität und bei gleicher Priorität nach Datum', () => {
  const sorted = entriesUI.sortEntries([
    { id: 1, categoryID: 1, priorityID: 3, sort: 10, entryDate: '2026-10-01 12:00:00' },
    { id: 2, categoryID: 1, priorityID: 1, sort: 30, entryDate: '2026-10-01 09:00:00' },
    { id: 3, categoryID: 1, priorityID: 1, sort: 10, entryDate: '2026-10-01 08:00:00' },
  ], [{ id: 1 }], [{ id: 1 }, { id: 2 }, { id: 3 }]);
  assert.deepEqual(Array.from(sorted, (entry) => entry.id), [2, 3, 1]);
});

test('filtert eine gespeicherte Ansicht zusätzlich nach Priorität', () => {
  const entries = [{ id: 1, categoryID: 6, priorityID: 1 }, { id: 2, categoryID: 6, priorityID: 2 }, { id: 3, categoryID: 1, priorityID: 1 }];
  const filtered = entriesUI.filterByPriority(entriesUI.filterByCategory(entries, '6'), '1');
  assert.deepEqual(Array.from(filtered, (entry) => entry.id), [1]);
  assert.deepEqual(Array.from(entriesUI.filterByPriority(entries, ''), (entry) => entry.id), [1, 2, 3]);
});

test('bietet für Verknüpfungen alle anderen Einträge an', () => {
  const current = { id: 7, pageURL: 'https://feedly.test/kontakt' };
  const candidates = [
    current,
    { id: 8, pageURL: 'https://feedly.test/kontakt' },
    { id: 9, pageURL: 'https://feedly.test/andere-seite' },
  ];
  assert.deepEqual(Array.from(entriesUI.relatedEntryOptions(candidates, current), (entry) => entry.id), [8, 9]);
});

test('zeigt an Eintragskarten ein verständliches Icon mit Beziehungstyp und verknüpftem Ziel', () => {
  const container = {
    children: [], dataset: {}, replaceChildren() { this.children = []; }, append(child) { this.children.push(child); },
    setAttribute(name, value) { this[name] = value; },
  };
  const documentRef = { createElement(tagName) { return { tagName, dataset: {}, children: [], append(...items) { this.children.push(...items); }, setAttribute(name, value) { this[name] = value; } }; } };
  entriesUI.renderRelationBadges(container, [
    { id: 42, title: 'Login speichert nicht', type: 'duplicate_of' },
    { id: 43, title: 'API blockiert', type: 'blocked_by' },
    { id: 46, title: 'Anmeldedaten fehlen', type: 'blocks', direction: 'incoming' },
    { id: 44, title: 'Formularfehler', type: 'has_duplicate', direction: 'incoming' },
    { id: 45, title: 'Suche ist langsam', type: 'related' },
    { id: 47, title: 'Suchfeld springt', type: 'related_to', direction: 'incoming' },
  ], documentRef);
  assert.equal(container['aria-label'], 'Verknüpfte Einträge');
  assert.equal(container.children.length, 6);
  assert.equal(container.children[0].children[1].textContent, 'Duplikat von · #42 Login speichert nicht');
  assert.equal(container.children[0].dataset.relationKind, 'duplicate');
  assert.doesNotMatch(container.children[0].children[1].textContent, /RELATION_DUPLICATE_OF/);
  assert.match(container.children[0].children[0].innerHTML, /<svg viewBox="0 0 24 24"/);
  assert.match(container.children[1].children[1].textContent, /Blockiert durch · #43 API blockiert/);
  assert.match(container.children[1].children[0].innerHTML, /<svg viewBox="0 0 468\.293 468\.293"/);
  assert.equal(container.children[1].dataset.relationKind, 'blocked');
  assert.equal(container.children[2].children[1].textContent, 'Blockiert · #46 Anmeldedaten fehlen');
  assert.equal(container.children[2].children[0].innerHTML, container.children[1].children[0].innerHTML, 'Blockierungen verwenden in beiden Richtungen dasselbe Symbol.');
  assert.equal(container.children[3].children[1].textContent, 'Hat Duplikat · #44 Formularfehler');
  assert.match(container.children[3].children[0].innerHTML, /<svg viewBox="0 0 24 24"/);
  assert.equal(container.children[3].dataset.relationKind, 'duplicate');
  assert.equal(container.children[3].children[0].innerHTML, container.children[0].children[0].innerHTML, 'Ausgangs- und Zieleintrag teilen dasselbe Duplikat-Symbol.');
  assert.match(container.children[4].children[1].textContent, /Thematisch verwandt · #45 Suche ist langsam/);
  assert.match(container.children[4].children[0].innerHTML, /<svg viewBox="0 0 48 48"/);
  assert.equal(container.children[5].children[1].textContent, 'Thematisch verwandt · #47 Suchfeld springt');
  assert.equal(container.children[5].children[0].innerHTML, container.children[4].children[0].innerHTML, 'Thematische Verknüpfungen haben beidseitig dasselbe Symbol und dieselbe Bezeichnung.');
  assert.equal(container.children[4].dataset.relationKind, 'related');
  assert.match(scss, /entry-relation\[data-relation-kind="duplicate"\]/);
  assert.match(scss, /entry-relation\[data-relation-kind="blocked"\]/);
  assert.match(scss, /entry-relation\[data-relation-kind="related"\]/);
  entriesUI.renderRelationBadges(container, [], documentRef);
  assert.equal(container.children.length, 0, 'Einträge ohne Verknüpfungen erhalten keinen leeren Hinweis.');
});

test('wandelt nur HTTP- und HTTPS-Links in sichere anklickbare Links um', () => {
  const container = { children: [], append(child) { this.children.push(child); }, set textContent(value) { this.plainText = value; } };
  const documentRef = {
    createElement(tagName) { return { tagName, setAttribute(name, value) { this[name] = value; } }; },
    createTextNode(text) { return { textContent: text }; },
  };
  window.KWMashaFeedlyEntries.renderLinks(container, 'Siehe https://example.test/a?b=1. oder javascript:alert(1)', documentRef);
  const link = container.children.find((child) => child.tagName === 'a');
  assert.equal(link.href, 'https://example.test/a?b=1');
  assert.equal(link.target, '_blank');
  assert.equal(link.rel, 'noopener noreferrer');
  assert.equal(link.textContent, 'https://example.test/a?b=1');
  assert.equal(container.children[2].textContent, '.');
  assert.equal(container.children.at(-1).textContent, ' oder javascript:alert(1)');
});

test('rendert XSS-Payloads in Einträgen und Kommentaren als Text und macht nur HTTPS anklickbar', () => {
  const payload = '<script>alert(1)</script><img src=x onerror=alert(2)> <svg onload=alert(3)> javascript:alert(4) https://safe.example/path';
  const container = { children: [], append(child) { this.children.push(child); }, set textContent(value) { this.plainText = value; } };
  const documentRef = {
    createElement(tagName) { return { tagName, setAttribute(name, value) { this[name] = value; } }; },
    createTextNode(text) { return { textContent: text }; },
  };
  entriesUI.renderLinks(container, payload, documentRef);
  const elements = container.children.filter((child) => child.tagName);
  assert.deepEqual(elements.map((element) => element.tagName), ['a']);
  assert.equal(elements[0].href, 'https://safe.example/path');
  assert.equal(elements[0].rel, 'noopener noreferrer');
  assert.match(container.children.map((child) => child.textContent).join(''), /<script>alert\(1\)<\/script>/);
  assert.match(container.children.map((child) => child.textContent).join(''), /<img src=x onerror=alert\(2\)>/);
  assert.match(container.children.map((child) => child.textContent).join(''), /<svg onload=alert\(3\)> javascript:alert\(4\)/);
});

test('filtert die Übersicht nach einer ausgewählten Kategorie', () => {
  const entries = [{ id: 1, categoryID: 3 }, { id: 2, categoryID: 4 }];
  assert.deepEqual(Array.from(entriesUI.filterByCategory(entries, '4'), (entry) => entry.id), [2]);
  assert.deepEqual(Array.from(entriesUI.filterByCategory(entries, ''), (entry) => entry.id), [1, 2]);
});

test('zeigt Reaktionen kompakt und öffnet die sechs Optionen erst auf Klick', async () => {
  const makeNode = (tagName) => ({
    tagName, dataset: {}, attributes: {}, children: [], listeners: {},
    append(...items) { this.children.push(...items); },
    replaceChildren(...items) { this.children = items; },
    setAttribute(name, value) { this.attributes[name] = value; },
    addEventListener(name, callback) { this.listeners[name] = callback; },
  });
  const documentRef = { createElement: makeNode };
  const container = makeNode('div');
  let reactedTo = '';
  entriesUI.renderCommentReactions(container, [
    { emoji: '👍', count: 2, selected: true },
    { emoji: '❤️', count: 1, selected: false },
  ], documentRef, (emoji) => { reactedTo = emoji; });

  assert.equal(container.attributes.role, 'group');
  assert.equal(container.attributes['aria-label'], 'Reaktionen auf diesen Kommentar');
  assert.equal(container.children.length, 2, 'Die Auswahl ist beim ersten Anzeigen eingeklappt.');
  const summary = container.children[0];
  const picker = container.children[1];
  assert.equal(picker.hidden, true);
  const like = summary.children[0];
  assert.equal(like.dataset.reactionEmoji, '👍');
  assert.equal(like.attributes['aria-pressed'], 'true');
  assert.match(like.attributes['aria-label'], /Gefällt mir.*2/);
  assert.equal(like.children[1].textContent, '2');
  const heart = summary.children[1];
  assert.equal(heart.attributes['aria-pressed'], 'false');
  assert.equal(heart.children[1].textContent, '1');
  const pickerToggle = summary.children[2];
  assert.equal(pickerToggle.children[0].className, 'kw-masha-feedly__comment-reaction-picker-face');
  assert.equal(pickerToggle.children[0].textContent, '☺');
  pickerToggle.listeners.click();
  assert.equal(picker.hidden, false);
  assert.equal(pickerToggle.attributes['aria-expanded'], 'true');
  assert.equal(picker.children.length, 6);
  assert.equal(picker.children[0].attributes['aria-pressed'], 'true');
  picker.children[2].listeners.click();
  assert.equal(reactedTo, '😂', 'Eine Auswahl wird zum Speichervorgang übergeben und ersetzt die bisherige eigene Reaktion.');
  assert.match(scss, /comment-reaction-picker-toggle\s*\{[^}]*place-items:\s*center/);
  assert.match(scss, /comment-reaction-picker-face\s*\{[^}]*line-height:\s*1[^}]*transform:\s*translateY\(-1px\)/);
});

test('führt eine Eintragskarte zur Originalseite und übergibt die Eintragskennung', () => {
  const target = new URL(entriesUI.entryTargetURL(
    { id: 71, pageURL: 'https://feedly:8890/about-us?from=cms#kontakt' },
    'https://feedly:8890/andere-seite'
  ));
  assert.equal(target.pathname, '/about-us');
  assert.equal(target.searchParams.get('from'), 'cms');
  assert.equal(target.searchParams.get('masha-feedly-entry'), '71');
  assert.equal(target.hash, '#kontakt');
});

test('hält Karten kompakt und zeigt Beschreibung sowie Seitenbereich nach dem Öffnen', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  await new Promise((resolve) => setImmediate(resolve));
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  assert.ok(card.children.find((child) => child.className === 'kw-masha-feedly__entry-title'));
  assert.equal(card.children.some((child) => child.tagName === 'P' || child.tagName === 'A' || child.className === 'kw-masha-feedly__entry-context'), false);
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  assert.equal(env.editModal.hidden, false);
  assert.equal(env.editDescription.textContent.includes('Der Inhalt ist verschoben.'), true);
  assert.equal(env.editEnvironment.children.length > 0, true);
});

test('teilt für jeden Bug einen Direktlink und kopiert den Link für den konkreten Eintrag', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  const actions = card.children.find((child) => child.className === 'kw-masha-feedly__entry-card-actions');
  const title = card.children.find((child) => child.className === 'kw-masha-feedly__entry-title');
  const shareButton = actions.children.find((child) => child.dataset.entryShare === '71');
  assert.ok(shareButton, 'jeder Eintragstitel bekommt einen Teilen-Button');
  assert.equal(actions.children.length, 1, 'Die Teilen-Aktion bekommt eine feste Spalte und kann nicht durch die Priorität verschoben werden.');
  assert.equal(actions.children[0], shareButton);
  assert.equal(title.children.some((child) => child.className === 'kw-masha-feedly__priority'), false);
  const metadata = card.children.find((child) => child.className === 'kw-masha-feedly__entry-metadata');
  assert.deepEqual(metadata.children.map((child) => child.className), ['kw-masha-feedly__entry-status', 'kw-masha-feedly__entry-priority', 'kw-masha-feedly__entry-assignees']);
  assert.equal(metadata.children[0].textContent, 'Backlog');
  assert.equal(metadata.children[0].dataset.closed, 'false');
  assert.equal(metadata.children[1].children[0].className, 'kw-masha-feedly__priority');
  assert.equal(metadata.children[1].children[1].textContent, 'Normal');
  assert.equal(metadata.children[1].attributes['aria-label'], 'Priorität: Normal');
  assert.equal(metadata.children[2].children[0].title, 'Ada Beispiel');
  assert.match(scss, /\.kw-masha-feedly__entry-card-actions \{ display: flex; align-items: center; justify-content: flex-end; gap: 8px; \}/);
  assert.match(shareButton.innerHTML, /<svg[\s\S]*<path/);
  let prevented = false;
  let stopped = false;
  await env.listContainer.listeners.click({
    target: shareButton,
    preventDefault() { prevented = true; },
    stopPropagation() { stopped = true; },
  });
  assert.equal(shareButton.dataset.shared, 'true', `Teilen muss erfolgreich sein; Status: ${shareButton.title}`);
  const directLink = new URL(env.window.copiedText);
  assert.equal(directLink.pathname, '/about-us');
  assert.equal(directLink.searchParams.get('masha-feedly-entry'), '71');
  assert.equal(shareButton.dataset.shared, 'true');
  assert.equal(shareButton.attributes['aria-label'], 'Direktlink wurde geteilt oder kopiert');
  assert.equal(prevented, true);
  assert.equal(stopped, true);
  assert.equal(env.editModal.hidden, true, 'Teilen öffnet nicht versehentlich den Eintrag zum Bearbeiten');
});

test('zeigt Status, Priorität und Zuständige in stabiler Reihenfolge und hält Aktionen vom Titel frei', async () => {
  const env = createWidgetEnvironment();
  const longTitle = 'Ein sehr langer Fehlerberichtstitel, der die Teilen-Schaltfläche niemals verdrängen darf';
  env.setEntryTitle(longTitle);
  await env.listeners['kw-masha-feedly:opened']();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  const title = card.children.find((child) => child.className === 'kw-masha-feedly__entry-title');
  const actions = card.children.find((child) => child.className === 'kw-masha-feedly__entry-card-actions');
  assert.ok(title);
  assert.ok(actions);
  assert.deepEqual(actions.children.map((child) => child.className), ['kw-masha-feedly__entry-share']);
  const metadata = card.children.find((child) => child.className === 'kw-masha-feedly__entry-metadata');
  assert.equal(card.children.indexOf(metadata), card.children.indexOf(title) + 1, 'Der Screenreader liest Status, Priorität und Zuständige direkt nach dem Titel.');
  assert.equal(card.dataset.hasAssignees, 'true');
  assert.ok(card.children.indexOf(actions) > card.children.indexOf(metadata));
  assert.equal(title.children[1].textContent, longTitle, 'CSS darf den Titel nur optisch begrenzen; der vollständige Text bleibt im DOM.');
  assert.equal(actions.children[0].dataset.entryShare, '71');
  assert.deepEqual(metadata.children.map((child) => child.className), ['kw-masha-feedly__entry-status', 'kw-masha-feedly__entry-priority', 'kw-masha-feedly__entry-assignees']);
  assert.equal(metadata.children[0].textContent, 'Backlog');
  assert.equal(metadata.children[1].children[1].textContent, 'Normal');
  assert.equal(metadata.children[2].children[0].title, 'Ada Beispiel');
  assert.match(germanTranslations, /ENTRY_PRIORITY_ARIA: 'Priorität: \{priority\}'/);
  assert.match(scss, /entry-card \{ grid-template-columns: minmax\(0, 1fr\) auto; align-items: start;/);
  assert.match(scss, /entry-title > span:nth-child\(2\) \{[^}]*-webkit-line-clamp: 2;/);
  assert.match(scss, /entry-card-actions \{ grid-column: 2; grid-row: 1; min-width: 40px;/);
  assert.match(scss, /entry-metadata \{ display: flex; grid-column: 1 \/ -1; grid-row: 2;/);
  assert.match(scss, /entry-card\[data-has-assignees="true"\] \{ position: relative; margin-bottom: 24px; padding-bottom: 30px; \}/);
  assert.match(scss, /entry-assignees \{ position: absolute; z-index: 2; right: 14px; bottom: 0; gap: 0; margin: 0; transform: translateY\(50%\); \}/);
  assert.match(compiledAdminStyles, /masha-feedly-board__card\[data-has-assignees=true\][^{]*\{[^}]*padding-bottom:1\.8rem/);
  assert.match(compiledAdminStyles, /masha-feedly-board__assignees\{position:absolute;z-index:2;right:\.75rem;bottom:-(?:0)?\.55rem;[^}]*transform:none\}/);
  assert.doesNotMatch(source, /entry-assignee-empty|ASSIGNEES_NONE/, 'Ohne Zuständige wird kein Platzhalter gerendert.');
  const unassignedEnv = createWidgetEnvironment();
  unassignedEnv.setEntryAssignees([]);
  await unassignedEnv.listeners['kw-masha-feedly:opened']();
  const unassignedCard = unassignedEnv.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  assert.equal(unassignedCard.dataset.hasAssignees, 'false');
  const unassignedMetadata = unassignedCard.children.find((child) => child.className === 'kw-masha-feedly__entry-metadata');
  assert.deepEqual(unassignedMetadata.children.map((child) => child.className), ['kw-masha-feedly__entry-status', 'kw-masha-feedly__entry-priority']);
});

test('zeigt die Teilen-Funktion auch im geöffneten Bugfenster und teilt genau diesen Bug', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  assert.equal(env.editModal.hidden, false);
  await env.shareActiveEntryButton.listeners.click({ preventDefault() {}, stopPropagation() {} });
  const directLink = new URL(env.window.copiedText);
  assert.equal(directLink.searchParams.get('masha-feedly-entry'), '71');
  assert.equal(env.shareActiveEntryButton.dataset.shared, 'true');
});

test('kopiert den Bug-Direktlink auch mit dem Browser-Fallback, wenn die Clipboard-API blockiert ist', async () => {
  const env = createWidgetEnvironment();
  env.window.clipboardShouldFail = true;
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  await env.shareActiveEntryButton.listeners.click({ preventDefault() {}, stopPropagation() {} });
  assert.equal(env.window.clipboardCalls.length, 1);
  const copiedLink = new URL(env.window.copiedText);
  assert.equal(copiedLink.searchParams.get('masha-feedly-entry'), '71');
  assert.equal(env.shareActiveEntryButton.dataset.shared, 'true');
  assert.equal(env.document.body.children.some((child) => child.tagName === 'textarea' && child.removed), true);
});

test('bereitet die Bugbeschreibung nur zum Lesen sowie Status und Avatar-Zuständigkeiten zum Ändern auf', () => {
  const editable = entriesUI.editableEntryData({
    id: 71,
    categoryID: 3,
    categoryTitle: 'Doing',
    assignees: [{ name: 'Ada Beispiel', initials: 'EM', color: '#E95DAB', imageURL: '' }],
    assignedMemberIDs: ['12'],
    content: 'Der Originaltext darf nicht im Bearbeitungsformular landen.',
  });
  assert.equal(editable.id, 71);
  assert.equal(editable.categoryID, 3);
  assert.deepEqual(Array.from(editable.assignedMemberIDs), [12]);
  assert.equal(editable.context, 'Status: Doing');
  assert.equal(editable.description, 'Der Originaltext darf nicht im Bearbeitungsformular landen.');
  assert.equal('content' in editable, false);
  assert.equal(editable.assignees[0].initials, 'EM');
});

test('übernimmt den Fälligkeitstermin in die Bearbeitungsdaten und rendert ihn in der Verlaufshistorie', () => {
  const editable = entriesUI.editableEntryData({ id: 71, categoryID: 1, priorityID: 3, dueDate: '2026-10-10' });
  assert.equal(editable.dueDate, '2026-10-10');
  const container = { children: [], replaceChildren() { this.children = []; }, append(child) { this.children.push(child); } };
  const doc = { createElement(tagName) { return { tagName, children: [], append(...children) { this.children.push(...children); } }; } };
  entriesUI.renderHistory(container, [{ type: 'due_date', oldValue: '', newValue: '2026-10-10', actor: 'Ada', created: '2026-10-01T10:00:00Z' }], doc);
  assert.equal(container.children[0].className, 'kw-masha-feedly__history-item is-due-date');
  assert.equal(container.children[0].children[0].textContent, 'Fälligkeit: Kein Termin → 2026-10-10');
});

test('zeigt Browser- und Seitenmetadaten sicher als Eintragsdetails an', () => {
  const container = { children: [], replaceChildren() { this.children = []; }, append(...children) { this.children.push(...children); } };
  const document = { createElement(tagName) { return { tagName, textContent: '' }; } };
  entriesUI.renderEnvironment(container, {
    loggedAt: '2026-10-01 12:00:00', pageURL: 'https://example.test/expertise/publikationen',
    operatingSystem: 'Mac OS 10.15.7', browser: 'Chrome 152.0.0.0', selector: 'html > body > header > nav',
    resolution: '2560 × 1440 px', browserWindow: '1943 × 1294 px', colorDepth: 24,
    userAgent: 'Mozilla/5.0 Chrome/152.0.0.0',
  }, document);
  assert.equal(container.hidden, false);
  assert.equal(container.children.length, 18);
  assert.equal(container.children[0].textContent, 'Erfasst am');
  assert.equal(container.children[1].textContent, '2026-10-01 12:00:00');
  assert.equal(container.children[7].textContent, 'Chrome 152.0.0.0');
  assert.equal(container.children.at(-1).textContent, 'Mozilla/5.0 Chrome/152.0.0.0');
});

test('zeichnet Mitglieder als farbige Initialen oder Profilbilder ohne Zuweisungstext', () => {
  const container = {
    children: [],
    replaceChildren() { this.children = []; },
    setAttribute(name, value) { this[name] = value; },
    append(child) { this.children.push(child); },
  };
  const document = { createElement(tagName) { return { tagName, style: {}, children: [], append(child) { this.children.push(child); }, setAttribute(name, value) { this[name] = value; } }; } };
  entriesUI.renderAssignees(container, [
    { name: 'Erika Muster', initials: 'EM', color: '#E95DAB' },
    { name: 'Max Beispiel', initials: 'MB', color: '#91B8E8', imageURL: '/protected/avatar.jpg' },
  ], document);
  assert.equal(container['aria-label'], 'Verantwortliche');
  assert.equal(container.children[0].textContent, 'EM');
  assert.equal(container.children[0].style.backgroundColor, '#E95DAB');
  assert.equal(container.children[1].children[0].src, '/protected/avatar.jpg');
});

test('rendert den Verlauf mit Status, Zuständigkeit, handelnder Person und Zeitpunkt als Text', () => {
  const container = {
    children: [], replaceChildren() { this.children = []; }, append(child) { this.children.push(child); },
  };
  const document = { createElement(tagName) { return { tagName, children: [], append(...children) { this.children.push(...children); } }; } };
  entriesUI.renderHistory(container, [
    { type: 'created', newValue: 'Suchfehler', actor: 'Erika Muster', created: '2026-10-02 09:29:00' },
    { type: 'attachment', newValue: 'screenshot.png', actor: 'Erika Muster', created: '2026-10-02 09:29:10' },
    { type: 'comment', newValue: 'Keine Ergebnisse.', actor: 'Max Beispiel', created: '2026-10-02 09:29:30' },
    { type: 'comment_edited', oldValue: 'Keine Ergebnisse.', newValue: 'Die Suche ist leer.', actor: 'Max Beispiel', created: '2026-10-02 09:29:45' },
    { type: 'comment_deleted', oldValue: 'Die Suche ist leer.', actor: 'Erika Muster', created: '2026-10-02 09:29:50' },
    { type: 'status', oldValue: 'Backlog', newValue: 'Doing', actor: 'Erika Muster', created: '2026-10-02 09:30:00' },
    { type: 'assignees', oldValue: '', newValue: 'Max Beispiel', actor: 'Erika Muster', created: '2026-10-02 09:31:00' },
    { type: 'relations', oldValue: '', newValue: 'Duplikat von #18 Suchfehler', actor: 'Erika Muster', created: '2026-10-02 09:32:00' },
  ], document);
  assert.equal(container.children.length, 8);
  assert.match(container.children[0].children[0].textContent, /Eintrag erstellt: Suchfehler/);
  assert.match(container.children[1].children[0].textContent, /Datei hochgeladen: screenshot\.png/);
  assert.match(container.children[2].children[0].textContent, /Kommentar: Keine Ergebnisse\./);
  assert.match(container.children[3].children[0].textContent, /Kommentar bearbeitet: Keine Ergebnisse\. → Die Suche ist leer\./);
  assert.match(container.children[4].children[0].textContent, /Kommentar gelöscht: Die Suche ist leer\./);
  assert.match(container.children[5].children[0].textContent, /Status: Backlog → Doing/);
  assert.match(container.children[5].children[1].textContent, /Erika Muster/);
  assert.match(container.children[6].children[0].textContent, /Zuständigkeit: Niemand → Max Beispiel/);
  assert.match(container.children[7].children[0].textContent, /Verknüpfungen: Keine Verknüpfungen → Duplikat von #18 Suchfehler/);
});

test('zeigt Prioritätsänderungen mit altem und neuem Wert im Verlauf an', () => {
  const container = { children: [], replaceChildren() { this.children = []; }, append(child) { this.children.push(child); } };
  const doc = { createElement(tagName) { return { tagName, children: [], append(...children) { this.children.push(...children); } }; } };
  entriesUI.renderHistory(container, [{ type: 'priority', oldValue: 'Normal', newValue: 'Kritisch', actor: 'Erika', created: '2026-10-01T10:00:00Z' }], doc);
  assert.equal(container.children[0].className, 'kw-masha-feedly__history-item is-priority');
  assert.equal(container.children[0].children[0].textContent, 'Priorität: Normal → Kritisch');
});

test('zeigt UTC-Verlaufzeiten in der lokalen Zeitzone statt zwei Stunden zu früh', () => {
  const previousTimezone = process.env.TZ;
  process.env.TZ = 'Europe/Berlin';
  try {
    const container = { children: [], replaceChildren() { this.children = []; }, append(child) { this.children.push(child); } };
    const document = { createElement(tagName) { return { tagName, children: [], append(...children) { this.children.push(...children); } }; } };
    entriesUI.renderHistory(container, [
      { type: 'created', newValue: 'Test', actor: 'Erika Muster', created: '2026-10-02T12:00:00Z' },
    ], document);
    assert.match(container.children[0].children[1].textContent, /14:00:00/);
  } finally {
    if (previousTimezone === undefined) delete process.env.TZ;
    else process.env.TZ = previousTimezone;
  }
});

test('erstellt eine sichtbare Blase am Element und lässt sie anklicken', () => {
  let clickHandler;
  const marker = {
    dataset: {}, style: { setProperty(name, value) { this[name] = value; } },
    setAttribute(name, value) { this[name] = value; },
    addEventListener(name, callback) { if (name === 'click') clickHandler = callback; },
  };
  const fakeDocument = { createElement: (tag) => { marker.tagName = tag; return marker; } };
  const target = { getBoundingClientRect: () => ({ left: 120, top: 90, width: 180, height: 80 }) };
  const opened = [];
  const rendered = entriesUI.createMarker(
    { id: 44, title: 'Kontaktbutton fehlt', categoryTitle: 'To Do', priorityColor: '#d7a916', elementPositionX: '0.25', elementPositionY: '0.75' },
    2,
    target,
    fakeDocument,
    { innerWidth: 800, innerHeight: 600 },
    () => opened.push(44)
  );
  assert.equal(rendered.tagName, 'button');
  assert.equal(rendered.className, 'kw-masha-feedly__page-marker');
  assert.equal(rendered['data-marker-number'], '3');
  assert.match(rendered.innerHTML, /class="kw-masha-feedly__page-marker-icon" viewBox="0 0 612\.001 612\.001"/);
  assert.match(rendered.innerHTML, /M64\.601 236\.822/);
  assert.equal(rendered.style.left, '165px');
  assert.equal(rendered.style.top, '150px');
  assert.equal(rendered.style['--masha-feedly-priority-color'], '#d7a916');
  assert.equal(rendered.title, 'To Do · Kontaktbutton fehlt');
  clickHandler({});
  assert.deepEqual(opened, [44]);
});

test('führt Marker ohne Scrollereignis bei Bewegung und Größenänderung nach und stoppt vollständig', () => {
  const pendingFrames = new Map();
  let nextFrame = 0;
  const window = {
    innerWidth: 800, innerHeight: 600,
    requestAnimationFrame(callback) { pendingFrames.set(++nextFrame, callback); return nextFrame; },
    cancelAnimationFrame(frame) { pendingFrames.delete(frame); },
  };
  const marker = {
    dataset: {}, style: { setProperty() {} }, setAttribute() {}, addEventListener() {},
  };
  let bounds = { left: 100, top: 50, width: 200, height: 100 };
  entriesUI.createMarker(
    { id: 44, elementPositionX: '0.25', elementPositionY: '0.75' }, 0,
    { getBoundingClientRect: () => bounds }, { createElement: () => marker }, window, () => {}
  );
  const stop = entriesUI.trackMarkers([marker], window);
  assert.equal(pendingFrames.size, 1);
  const firstFrame = pendingFrames.get(1);
  pendingFrames.delete(1);
  bounds = { left: 300, top: 150, width: 400, height: 200 };
  firstFrame();
  assert.equal(marker.style.left, '400px');
  assert.equal(marker.style.top, '300px');
  assert.equal(pendingFrames.size, 1);
  const lateFrame = pendingFrames.get(2);
  stop();
  assert.equal(pendingFrames.size, 0);
  bounds.left = 500;
  lateFrame();
  assert.equal(marker.style.left, '400px');
  assert.equal(pendingFrames.size, 0);
  entriesUI.trackMarkers([], window);
  assert.equal(pendingFrames.size, 0);
});

test('ordnet Fehler Nr. 6 bei identischen Auswahlpfaden dem Wort Design statt agnen zu', () => {
  const campaign = { innerText: 'AGNEN', textContent: 'agnen' };
  const design = { innerText: 'DESIGN', textContent: 'Design' };
  const document = { querySelector: () => campaign, querySelectorAll: () => [campaign, design] };
  assert.equal(entriesUI.resolveTarget({ selector: 'a > div > h2 > span > span', elementText: 'DESIGN' }, document), design);
  assert.equal(entriesUI.resolveTarget({ selector: 'a > div > h2 > span > span', elementText: 'Design' }, document), design);
  assert.equal(entriesUI.resolveTarget({ selector: 'a > div > h2 > span > span', elementText: '' }, document), null);
  assert.equal(entriesUI.resolveTarget({ selector: 'a > div > h2 > span > span', elementText: 'Unbekannt' }, document), null);
  document.querySelectorAll = () => [design, { innerText: 'DESIGN' }];
  assert.equal(entriesUI.resolveTarget({ selector: 'span', elementText: 'DESIGN' }, document), null);
  assert.equal(entriesUI.resolveTarget({ selector: '[' }, { querySelector() { throw new Error('Ungültiger Selektor'); } }), null);
});

test('verschiebt die gespeicherte Klickstelle nicht zum Fensterrand', () => {
  const marker = { dataset: {}, style: { setProperty() {} }, setAttribute() {}, addEventListener() {} };
  entriesUI.createMarker(
    { id: 6, elementPositionX: '0.9', elementPositionY: '0.8' }, 0,
    { getBoundingClientRect: () => ({ left: 700, top: 550, width: 200, height: 100 }) },
    { createElement: () => marker }, { innerWidth: 800, innerHeight: 600 }, () => {}
  );
  assert.equal(marker.style.left, '880px');
  assert.equal(marker.style.top, '630px');
});

test('verwendet für Seitenmarkierungen nur gültige Prioritätsfarben', () => {
  const marker = {
    dataset: {}, style: { setProperty(name, value) { this[name] = value; } },
    setAttribute() {}, addEventListener() {},
  };
  const target = { getBoundingClientRect: () => ({ left: 120, top: 90, width: 180, height: 80 }) };
  entriesUI.createMarker(
    { id: 45, title: 'Ungültige Farbe', priorityColor: 'red; background:url(javascript:alert(1))' },
    0,
    target,
    { createElement: () => marker },
    { innerWidth: 800 },
    () => {}
  );
  assert.equal(marker.style['--masha-feedly-priority-color'], '#64748b');
});

test('lässt nur den aktuell ausgewählten Eintrag pulsiert markieren und setzt andere zurück', () => {
  const marker = (entryId) => ({
    dataset: { entryId: String(entryId) }, active: false,
    classList: { toggle(name, enabled) { if (name === 'is-active') this.owner.active = enabled; }, owner: null },
    setAttribute(name, value) { this[name] = value; },
  });
  const first = marker(24);
  const second = marker(25);
  first.classList.owner = first;
  second.classList.owner = second;
  entriesUI.setActiveMarker([first, second], 25);
  assert.equal(first.active, false);
  assert.equal(first['aria-pressed'], 'false');
  assert.equal(second.active, true);
  assert.equal(second['aria-pressed'], 'true');
  entriesUI.setActiveMarker([first, second]);
  assert.equal(second.active, false);
  assert.equal(second['aria-pressed'], 'false');
});

test('färbt das Website-Symbol in Prioritätsfarbe und zeigt es immer als Fehler-Marker', () => {
  assert.match(scss, /\.kw-masha-feedly__page-marker-icon\s*\{[^}]*fill: currentColor/);
  assert.match(scss, /\.kw-masha-feedly__page-marker\s*\{[^}]*color: var\(--masha-feedly-priority-color/);
  assert.match(scss, /\.kw-masha-feedly__page-marker\.is-active \.kw-masha-feedly__page-marker-icon\s*\{[^}]*animation: kw-masha-feedly-marker-selected/);
  assert.match(scss, /\.kw-masha-feedly__page-marker::after\s*\{[^}]*opacity: 0/);
  assert.match(scss, /\.kw-masha-feedly__page-marker\.is-active::after\s*\{[^}]*animation: kw-masha-feedly-marker-pulse/);
  assert.match(scss, /\.kw-masha-feedly__page-marker:focus-visible\s*\{[^}]*outline-color: var\(--masha-feedly-priority-color/);
  assert.match(scss, /\.kw-masha-feedly__page-marker:focus-visible\s*\{[^}]*box-shadow: 0 0 0 6px color-mix\(in srgb, var\(--masha-feedly-priority-color/);
  assert.match(scss, /:focus-visible:not\(\.kw-masha-feedly__page-marker\)\s*\{[^}]*outline-color: #334155/);
  assert.match(compiledStyles, /\.kw-masha-feedly\[data-theme=serious\] :focus-visible:not\(\.kw-masha-feedly__page-marker\)\{outline-color:#334155/);
  assert.match(compiledStyles, /\.kw-masha-feedly__page-marker:focus-visible\{outline-color:var\(--masha-feedly-priority-color/);
});

test('erzeugt 128 Konfettiteile aus allen Richtungen für Done und räumt sie wieder auf', () => {
  let cleanup;
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(piece) { this.children.push(piece); }, remove() { this.removed = true; } };
  let created = 0;
  const document = { body: { append(item) { this.layer = item; } }, createElement() { created += 1; return created === 1 ? layer : { style: {}, dataset: {}, setAttribute(name, value) { this[name] = value; } }; } };
  const window = { setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 13000); } };
  const confetti = entriesUI.celebrateDone(document, window);
  assert.equal(confetti, layer);
  assert.equal(layer.className, 'kw-masha-feedly__confetti');
  assert.equal(layer.children.length, 128);
  assert.equal(layer.children[0].style.animationDuration, '8.80s');
  assert.equal(layer.children[11].style.animationDuration, '11.44s');
  assert.deepEqual([...new Set(layer.children.map((piece) => piece.dataset.origin))], ['oben', 'rechts', 'unten', 'links']);
  cleanup();
  assert.equal(layer.removed, true);
});

test('lässt nach einem Abschluss das Einhorn-Asset über die Seite laufen', () => {
  let cleanup;
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(...items) { this.children.push(...items); }, remove() { this.removed = true; } };
  let created = 0;
  const document = { body: { append(item) { this.layer = item; } }, createElement() { created += 1; return created === 1 ? layer : {}; } };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 13000); } };
  const runner = entriesUI.celebrateClosedCategory(document, window, '/_resources/kooperativeweb/masha-feedly/client/dist/icons/masha-feedly-unicorn.svg');
  assert.equal(runner, layer);
  assert.equal(layer.className, 'kw-masha-feedly__unicorn-runner');
  assert.equal(layer.children[0].className, 'kw-masha-feedly__unicorn');
  assert.equal(layer.children[0].src, '/_resources/kooperativeweb/masha-feedly/client/dist/icons/masha-feedly-unicorn.svg');
  assert.equal(layer.children[0].alt, '');
  cleanup();
  assert.equal(layer.removed, true);
});

test('bricht alle achtzehn Abschlussanimationen beim nächsten Klick vollständig ab', () => {
  const runnerSource = fs.readFileSync(path.resolve(__dirname, '../../client/src/js/effects/runner.js'), 'utf8');
  const animationNames = ['unicorn', 'rocket', 'hearts', 'arcade', 'retro', 'dino', 'ducks', 'frogs', 'iconShower', 'ghostSwarm', 'potion', 'catPaws', 'flowerPower', 'pinballTilt', 'check', 'glow', 'rings', 'confirmation'];
  const removedByEffect = new Map();
  const modules = Object.fromEntries(animationNames.map((name) => [name, {
    play() {
      const nodes = [
        { remove() { this.removed = true; } },
        ...(name === 'unicorn' ? [{ remove() { this.removed = true; } }] : []),
      ];
      removedByEffect.set(name, nodes);
      return name === 'unicorn' ? { confetti: nodes[0], unicorn: nodes[1] } : { [name]: nodes[0] };
    },
  }]));
  const simulatedWindow = {
    KWMashaFeedlyEffectModules: modules,
    matchMedia: () => ({ matches: false }),
    setTimeout: () => 1,
    clearTimeout() {},
  };
  let clickHandler;
  const document = { addEventListener(type, callback, capture) {
    assert.equal(type, 'pointerdown');
    assert.equal(capture, true);
    clickHandler = callback;
  } };
  vm.runInNewContext(runnerSource, { window: simulatedWindow });

  animationNames.forEach((name) => {
    simulatedWindow.KWMashaFeedlyEffects.play(name, document, simulatedWindow);
    const nodes = removedByEffect.get(name);
    clickHandler({ type: 'click' });
    assert.ok(nodes.every((node) => node.removed), `${name} entfernt auch seine sichtbaren Ebenen.`);

    simulatedWindow.KWMashaFeedlyEffects.play(name, document, simulatedWindow);
    const newlyStartedNodes = removedByEffect.get(name);
    assert.ok(newlyStartedNodes.every((node) => !node.removed), `${name} bleibt nach dem Klick als neue Vorschau sichtbar.`);
    clickHandler({ type: 'pointerdown' });
    assert.ok(newlyStartedNodes.every((node) => node.removed), `${name} endet erst beim darauffolgenden Klick.`);
  });
});

test('startet beim Raketenstart eine barrierefreie Rakete mit Sternenspur und räumt sie auf', () => {
  let cleanup;
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(...items) { this.children.push(...items); }, remove() { this.removed = true; } };
  const document = {
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { tagName, children: [], style: {}, dataset: {}, setAttribute(name, value) { this[name] = value; }, append(...items) { this.children.push(...items); } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 5200); } };
  const rocket = entriesUI.celebrateRocketLaunch(document, window);
  assert.equal(rocket, layer);
  assert.equal(layer.className, 'kw-masha-feedly__rocket-runner');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.filter((child) => child.className === 'kw-masha-feedly__rocket-star').length, 14);
  const smoke = layer.children.find((child) => child.className === 'kw-masha-feedly__rocket-smoke');
  assert.ok(smoke, 'Der Start enthält eine graue Rauchwolke.');
  assert.deepEqual(smoke.children.map((puff) => puff.dataset.attempt), ['1', '2', '3']);
  assert.equal(layer.children.at(-1).className, 'kw-masha-feedly__rocket');
  assert.match(layer.children.at(-1).innerHTML, /kw-masha-feedly__rocket-nose/);
  assert.match(layer.children.at(-1).innerHTML, /kw-masha-feedly__rocket-body/);
  assert.match(layer.children.at(-1).innerHTML, /kw-masha-feedly-rocket-rainbow/);
  assert.equal((layer.children.at(-1).innerHTML.match(/kw-masha-feedly__rocket-speedline /g) || []).length, 5);
  cleanup();
  assert.equal(layer.removed, true);
});

test('lässt sieben Gummienten mit Abstand watschelnd durchs Bild ziehen', () => {
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() { this.removed = true; } };
  let cleanup;
  const document = {
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { style: { setProperty(name, value) { this[name] = value; } }, setAttribute(name, value) { this[name] = value; } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 12000); } };
  const modules = {};
  const simulatedWindow = { matchMedia: window.matchMedia, setTimeout: window.setTimeout, KWMashaFeedlyEffectModules: modules };
  vm.runInNewContext(effectSources[6], { window: simulatedWindow });

  const effect = modules.ducks.play(document, window);
  assert.equal(effect.ducks, layer);
  assert.equal(layer.className, 'kw-masha-feedly__duck-parade');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.length, 7);
  assert.equal(layer.children[0].className, 'kw-masha-feedly__parade-duck');
  assert.equal(layer.children[0].style.animationDelay, '0s');
  assert.equal(layer.children[6].style.animationDelay, '2.88s');
  assert.equal(layer.children[0].style['--duck-body-color'], '#fec80e');
  assert.equal(layer.children[3].style['--duck-body-color'], '#242424');
  assert.match(layer.children[0].innerHTML, /viewBox="0 0 512 512"/);
  assert.match(layer.children[0].innerHTML, /kw-masha-feedly__duck-beak/);
  assert.match(layer.children[0].innerHTML, /m252\.59 211\.91/);
  cleanup();
  assert.equal(layer.removed, true);
  assert.equal(modules.ducks.play(document, { matchMedia: () => ({ matches: true }) }), null);
});

test('lässt fünf bunte Frösche versetzt durchs Bild hüpfen', () => {
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() { this.removed = true; } };
  let cleanup;
  const document = {
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { style: { setProperty(name, value) { this[name] = value; } } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 10000); } };
  const modules = {};
  vm.runInNewContext(effectSources[7], { window: { KWMashaFeedlyEffectModules: modules } });

  const effect = modules.frogs.play(document, window);
  assert.equal(effect.frogs, layer);
  assert.equal(layer.className, 'kw-masha-feedly__frog-parade');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.length, 5);
  assert.equal(layer.children[0].style.animationDelay, '0s');
  assert.equal(layer.children[4].style.animationDelay, '2.08s');
  assert.match(layer.children[0].innerHTML, /kw-masha-feedly__frog-smile/);
  cleanup();
  assert.equal(layer.removed, true);
  assert.equal(modules.frogs.play(document, { matchMedia: () => ({ matches: true }) }), null);
});

test('lässt fünfzig wechselnde Motive als Icon-Schauer einfliegen', () => {
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() { this.removed = true; } };
  let cleanup;
  const document = {
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { style: { setProperty(name, value) { this[name] = value; } } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 10500); } };
  const modules = {};
  vm.runInNewContext(effectSources[8], { window: { KWMashaFeedlyEffectModules: modules } });

  const effect = modules.iconShower.play(document, window);
  assert.equal(effect.iconShower, layer);
  assert.equal(layer.className, 'kw-masha-feedly__icon-shower');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.length, 50);
  assert.match(layer.children[0].innerHTML, /viewBox="0 0 64 64"/);
  assert.notEqual(layer.children[0].innerHTML, layer.children[1].innerHTML);
  assert.ok(layer.children.every((icon) => icon.style['--icon-duration'].endsWith('s')));
  cleanup();
  assert.equal(layer.removed, true);
  assert.equal(modules.iconShower.play(document, { matchMedia: () => ({ matches: true }) }), null);
});

test('lässt einen geisterschwarm durch nebel schweben und kurz flackern', () => {
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() { this.removed = true; } };
  let cleanup;
  const document = {
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { style: { setProperty(name, value) { this[name] = value; } } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 8500); } };
  const modules = {};
  vm.runInNewContext(effectSources[9], { window: { KWMashaFeedlyEffectModules: modules } });

  const effect = modules.ghostSwarm.play(document, window);
  assert.equal(effect.ghostSwarm, layer);
  assert.equal(layer.className, 'kw-masha-feedly__ghost-swarm');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.filter((item) => item.className === 'kw-masha-feedly__swarm-ghost').length, 9);
  assert.equal(layer.children.filter((item) => item.className === 'kw-masha-feedly__ghost-mist').length, 12);
  assert.match(layer.children[0].innerHTML, /kw-masha-feedly__ghost-shape/);
  cleanup();
  assert.equal(layer.removed, true);
  assert.equal(modules.ghostSwarm.play(document, { matchMedia: () => ({ matches: true }) }), null);
});

test('blubbert Zauberblasen in leuchtenden Farben aus dem Kessel', () => {
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() { this.removed = true; } };
  let cleanup;
  const document = {
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { style: { setProperty(name, value) { this[name] = value; } } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 7600); } };
  const modules = {};
  vm.runInNewContext(effectSources[10], { window: { KWMashaFeedlyEffectModules: modules } });

  const effect = modules.potion.play(document, window);
  assert.equal(effect.potion, layer);
  assert.equal(layer.className, 'kw-masha-feedly__potion-effect');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.length, 20);
  assert.equal(layer.children[0].className, 'kw-masha-feedly__potion-glow');
  assert.match(layer.children[1].innerHTML, /kw-masha-feedly__cauldron-brew/);
  assert.equal(layer.children.filter((item) => item.className === 'kw-masha-feedly__potion-bubble').length, 18);
  cleanup();
  assert.equal(layer.removed, true);
  assert.equal(modules.potion.play(document, { matchMedia: () => ({ matches: true }) }), null);
});

test('lässt Katzenpfoten in einer geschwungenen Spur verblassen', () => {
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() { this.removed = true; } };
  let cleanup;
  const document = {
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { style: { setProperty(name, value) { this[name] = value; } } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 6500); } };
  const modules = {};
  vm.runInNewContext(effectSources[11], { window: { KWMashaFeedlyEffectModules: modules } });

  const effect = modules.catPaws.play(document, window);
  assert.equal(effect.catPaws, layer);
  assert.equal(layer.className, 'kw-masha-feedly__cat-paw-trail');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.length, 18);
  assert.equal(layer.children[0].style['--paw-x'], '-3vw');
  assert.equal(layer.children[1].style['--paw-delay'], '0.22s');
  assert.equal(layer.children[0].style['--paw-color'], '#ff1744');
  assert.match(layer.children[0].innerHTML, /viewBox="0 0 64 64"/);
  assert.match(layer.children[0].innerHTML, /m40\.1 33\.51a11\.78 11\.78/);
  assert.match(layer.children[0].innerHTML, /kw-masha-feedly__cat-paw-toe-4/);
  cleanup();
  assert.equal(layer.removed, true);
  assert.equal(modules.catPaws.play(document, { matchMedia: () => ({ matches: true }) }), null);
});

test('bringt den Flower-Power-Sonnenkreis und acht tanzende Blumen zum Leuchten', () => {
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() { this.removed = true; } };
  let cleanup;
  const document = {
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { style: { setProperty(name, value) { this[name] = value; } } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 6200); } };
  const modules = {};
  vm.runInNewContext(effectSources[12], { window: { KWMashaFeedlyEffectModules: modules } });

  const effect = modules.flowerPower.play(document, window);
  assert.equal(effect.flowerPower, layer);
  assert.equal(layer.className, 'kw-masha-feedly__flower-power');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.length, 10);
  assert.equal(layer.children[0].className, 'kw-masha-feedly__flower-power-sun');
  assert.equal(layer.children[1].textContent, 'GROOVY!');
  assert.equal(layer.children.filter((item) => item.className === 'kw-masha-feedly__flower-power-flower').length, 8);
  assert.equal((layer.children[2].innerHTML.match(/<ellipse/g) || []).length, 10);
  cleanup();
  assert.equal(layer.removed, true);
  assert.equal(modules.flowerPower.play(document, { matchMedia: () => ({ matches: true }) }), null);
});

test('rüttelt die Browseransicht und blendet die Tilt-Warnung ein', () => {
  const layer = { children: [], setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() { this.removed = true; } };
  let cleanup;
  const rootClasses = new Set();
  const document = {
    documentElement: { classList: { add(name) { rootClasses.add(name); }, remove(name) { rootClasses.delete(name); } } },
    body: { append(item) { this.layer = item; } },
    createElement(tagName) {
      if (tagName === 'div') return layer;
      return { style: { setProperty(name, value) { this[name] = value; } } };
    },
  };
  const window = { matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { cleanup = callback; assert.equal(delay, 2100); } };
  const modules = {};
  vm.runInNewContext(effectSources[13], { window: { KWMashaFeedlyEffectModules: modules } });

  const effect = modules.pinballTilt.play(document, window);
  assert.equal(effect.pinball, layer);
  assert.equal(layer.className, 'kw-masha-feedly__pinball-tilt');
  assert.equal(layer['aria-hidden'], 'true');
  assert.equal(layer.children.length, 13);
  assert.equal(layer.children[0].className, 'kw-masha-feedly__pinball-tilt-word');
  assert.equal(layer.children[0].textContent, 'TILT!');
  assert.ok(rootClasses.has('kw-masha-feedly__pinball-viewport-shake'));
  assert.equal(layer.children.filter((item) => item.className === 'kw-masha-feedly__pinball-light').length, 12);
  cleanup();
  assert.equal(layer.removed, true);
  assert.equal(rootClasses.has('kw-masha-feedly__pinball-viewport-shake'), false);
  assert.equal(modules.pinballTilt.play(document, { matchMedia: () => ({ matches: true }) }), null);
});

test('wählt theme-basiert zwischen Einhorn, Rakete, Herzen, Arcade, Retro, Dino, Gummienten, Fröschen, Icon-Schauer, Geisterschwarm, Zaubertrank, Katzenpfoten, Flower Power und Pinball Tilt', () => {
  const layers = [];
  const document = {
    documentElement: { classList: { add() {}, remove() {} } },
    body: { append(layer) { layers.push(layer); } },
    createElement(tagName) {
      return tagName === 'div'
        ? { children: [], style: { setProperty(name, value) { this[name] = value; } }, setAttribute(name, value) { this[name] = value; }, append(item) { this.children.push(item); }, remove() {} }
        : { children: [], style: { setProperty(name, value) { this[name] = value; } }, dataset: {}, setAttribute(name, value) { this[name] = value; }, append(...items) { this.children.push(...items); } };
    },
  };
  const window = { innerWidth: 1200, innerHeight: 800, matchMedia: () => ({ matches: false }), setTimeout() {} };
  const unicornCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.04);
  assert.ok(unicornCompletion.confetti);
  assert.ok(unicornCompletion.unicorn);
  assert.equal(unicornCompletion.rocket, undefined);
  assert.equal(unicornCompletion.unicorn.children[0].src, '/unicorn.svg');
  const rocketCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.1);
  assert.deepEqual(Object.keys(rocketCompletion), ['rocket']);
  assert.equal(layers.at(-1).className, 'kw-masha-feedly__rocket-runner');
  const heartsCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.18);
  assert.deepEqual(Object.keys(heartsCompletion), ['hearts']);
  assert.equal(heartsCompletion.hearts.className, 'kw-masha-feedly__heart-burst');
  assert.equal(heartsCompletion.hearts.children.length, 28);
  assert.equal(heartsCompletion.hearts.children[0].textContent, '♥');
  assert.equal(heartsCompletion.hearts.children[0].style.color, '#ff3b91');
  const arcadeCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.25);
  assert.deepEqual(Object.keys(arcadeCompletion), ['arcade']);
  assert.equal(arcadeCompletion.arcade.className, 'kw-masha-feedly__arcade-effect');
  const arcadeScreen = arcadeCompletion.arcade.children[0];
  assert.equal(arcadeScreen.className, 'kw-masha-feedly__arcade-screen');
  assert.equal(arcadeScreen.children.filter((child) => child.className === 'kw-masha-feedly__arcade-pixel').length, 56);
  assert.equal(arcadeScreen.children.filter((child) => child.className === 'kw-masha-feedly__arcade-invader').length, 4);
  assert.equal(arcadeScreen.children.at(-1).textContent, 'MISSION COMPLETE!');
  const retroCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.32);
  assert.deepEqual(Object.keys(retroCompletion), ['retro']);
  assert.equal(retroCompletion.retro.className, 'kw-masha-feedly__retro-effect');
  assert.equal(retroCompletion.retro.children[0].className, 'kw-masha-feedly__retro-dialog');
  const dinoCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.39);
  assert.deepEqual(Object.keys(dinoCompletion), ['dino']);
  assert.equal(dinoCompletion.dino.className, 'kw-masha-feedly__dino-effect');
  assert.equal(dinoCompletion.dino.children[0].textContent, 'Speichern');
  assert.match(dinoCompletion.dino.children[1].innerHTML, /viewBox="0 0 56 60"/);
  assert.equal(dinoCompletion.dino.children[2].textContent, 'HAPS!');
  assert.equal(dinoCompletion.dino.children.filter((child) => child.className === 'kw-masha-feedly__dino-pixel').length, 10);
  const ducksCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.46);
  assert.equal(ducksCompletion.ducks.className, 'kw-masha-feedly__duck-parade');
  assert.equal(ducksCompletion.ducks.children.length, 7);
  const frogsCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.53);
  assert.equal(frogsCompletion.frogs.className, 'kw-masha-feedly__frog-parade');
  assert.equal(frogsCompletion.frogs.children.length, 5);
  const iconShowerCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.6);
  assert.equal(iconShowerCompletion.iconShower.children.length, 50);
  const ghostSwarmCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.68);
  assert.equal(ghostSwarmCompletion.ghostSwarm.children.length, 21);
  const potionCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.75);
  assert.equal(potionCompletion.potion.children.length, 20);
  const catPawsCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.82);
  assert.equal(catPawsCompletion.catPaws.children.length, 18);
  const flowerPowerCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.9);
  assert.equal(flowerPowerCompletion.flowerPower.children.length, 10);
  const pinballCompletion = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'playful', () => 0.97);
  assert.equal(pinballCompletion.pinball.children.length, 13);
  const rocketPreview = entriesUI.previewCompletionAnimation(document, window, 'rocket', '/unicorn.svg');
  assert.equal(rocketPreview.confetti, undefined);
  assert.equal(rocketPreview.unicorn, undefined);
  assert.ok(rocketPreview.rocket);
  const heartsPreview = entriesUI.previewCompletionAnimation(document, window, 'hearts', '/unicorn.svg');
  assert.equal(heartsPreview.hearts.children.length, 28);
  const arcadePreview = entriesUI.previewCompletionAnimation(document, window, 'arcade', '/unicorn.svg');
  assert.equal(arcadePreview.arcade.children[0].children.at(-1).textContent, 'MISSION COMPLETE!');
  const retroPreview = entriesUI.previewCompletionAnimation(document, window, 'retro', '/unicorn.svg');
  assert.equal(retroPreview.retro.className, 'kw-masha-feedly__retro-effect');
  const dinoPreview = entriesUI.previewCompletionAnimation(document, window, 'dino', '/unicorn.svg');
  assert.equal(dinoPreview.dino.children[0].textContent, 'Speichern');
  const ducksPreview = entriesUI.previewCompletionAnimation(document, window, 'ducks', '/unicorn.svg');
  assert.equal(ducksPreview.ducks.children.length, 7);
  const frogsPreview = entriesUI.previewCompletionAnimation(document, window, 'frogs', '/unicorn.svg');
  assert.equal(frogsPreview.frogs.children.length, 5);
  const iconShowerPreview = entriesUI.previewCompletionAnimation(document, window, 'iconShower', '/unicorn.svg');
  assert.equal(iconShowerPreview.iconShower.children.length, 50);
  const ghostSwarmPreview = entriesUI.previewCompletionAnimation(document, window, 'ghostSwarm', '/unicorn.svg');
  assert.equal(ghostSwarmPreview.ghostSwarm.children.length, 21);
  const potionPreview = entriesUI.previewCompletionAnimation(document, window, 'potion', '/unicorn.svg');
  assert.equal(potionPreview.potion.children.length, 20);
  const catPawsPreview = entriesUI.previewCompletionAnimation(document, window, 'catPaws', '/unicorn.svg');
  assert.equal(catPawsPreview.catPaws.children.length, 18);
  const flowerPowerPreview = entriesUI.previewCompletionAnimation(document, window, 'flowerPower', '/unicorn.svg');
  assert.equal(flowerPowerPreview.flowerPower.children.length, 10);
  const pinballPreview = entriesUI.previewCompletionAnimation(document, window, 'pinballTilt', '/unicorn.svg');
  assert.equal(pinballPreview.pinball.children.length, 13);
  const playful = entriesUI.previewCompletionAnimation(document, window, 'playful', '/unicorn.svg');
  assert.ok(playful.confetti);
  assert.ok(playful.unicorn);
  const seriousEffects = ['check', 'glow', 'rings', 'confirmation'];
  seriousEffects.forEach((name, index) => {
    const result = entriesUI.celebrateCompletion(document, window, '/unicorn.svg', 'serious', () => (index + 0.1) / seriousEffects.length);
    assert.deepEqual(Object.keys(result), [name]);
  });
  assert.equal(entriesUI.previewCompletionAnimation(document, window, 'check').check.className, 'kw-masha-feedly__serious-check');
  const reducedWindow = { matchMedia: () => ({ matches: true }) };
  assert.equal(entriesUI.previewCompletionAnimation(document, reducedWindow, 'rocket'), null);
  assert.equal(entriesUI.celebrateCompletion(document, reducedWindow, '/unicorn.svg', 'playful', () => { throw new Error('Zufall darf bei reduzierter Bewegung nicht ausgewertet werden.'); }), null);
});

test('lässt den grünen T-Rex zum Speichern-Button laufen und zerbeißt dessen Darstellung', () => {
  const layer = { children: [], style: { setProperty(name, value) { this[name] = value; } }, setAttribute(name, value) { this[name] = value; }, append(child) { this.children.push(child); }, remove() {} };
  const saveButton = { textContent: 'Änderungen speichern →', getClientRects: () => [{}], getBoundingClientRect: () => ({ left: 320, top: 460, width: 180, height: 48 }) };
  let created = 0;
  const document = {
    querySelectorAll(selector) { assert.equal(selector, 'button.kw-masha-feedly__submit[type="submit"]'); return [saveButton]; },
    createElement() { created += 1; return created === 1 ? layer : { className: '', style: {}, children: [], append(child) { this.children.push(child); } }; },
    body: { append(item) { this.layer = item; } },
  };
  const simulatedWindow = { innerWidth: 1200, innerHeight: 800, matchMedia: () => ({ matches: false }), setTimeout(callback, delay) { assert.equal(delay, 3400); }, KWMashaFeedlyEffectModules: {} };
  vm.runInNewContext(effectSources[5], { window: simulatedWindow });
  const result = simulatedWindow.KWMashaFeedlyEffectModules.dino.play(document, simulatedWindow);
  assert.equal(result.dino, layer);
  assert.equal(layer.style['--dino-target-left'], '320px');
  assert.equal(layer.style['--dino-target-top'], '460px');
  assert.equal(layer.style['--dino-target-width'], '180px');
  assert.equal(layer.style['--dino-bite-left'], '452px');
  assert.equal(layer.children[0].textContent, 'Änderungen speichern →');
  assert.equal(layer.children[1].className, 'kw-masha-feedly__dino-character');
  assert.match(layer.children[1].innerHTML, /viewBox="0 0 56 60"/);
  assert.match(layer.children[1].innerHTML, /fill="#60a917"/);
  assert.match(effectStyles[6], /translateX\(calc\(100vw \+ 17rem\)\)/);
  assert.match(effectStyles[6], /--dino-bite-left/);
  assert.doesNotMatch(effectStyles[6], /scaleX\(-1\)/);
  assert.equal(layer.children[2].textContent, 'HAPS!');
  assert.equal(layer.children.filter((child) => child.className === 'kw-masha-feedly__dino-pixel').length, 10);
  assert.equal(saveButton.className, undefined, 'Die gespeicherte Schaltfläche wird nur visuell überlagert, nicht im DOM verändert.');
});

test('spielt beim 8-Bit-Erfolg eine kurze, freigeschaltete Chiptune-Siegsmelodie', async () => {
  const oscillators = [];
  const listeners = {};
  const fakeContext = {
    state: 'suspended', currentTime: 2, destination: {},
    resume() { this.state = 'running'; return Promise.resolve(); },
    createOscillator() {
      const oscillator = { frequency: { setValueAtTime(value, at) { this.value = value; this.at = at; } }, connect(target) { this.target = target; }, start(at) { this.startedAt = at; }, stop(at) { this.stoppedAt = at; } };
      oscillators.push(oscillator);
      return oscillator;
    },
    createGain() { return { gain: { setValueAtTime() {}, exponentialRampToValueAtTime() {} }, connect() {} }; },
  };
  const simulatedWindow = {
    AudioContext: function AudioContext() { return fakeContext; },
    addEventListener(type, callback, options) { listeners[type] = { callback, options }; },
  };
  vm.runInNewContext(effectSources[3], { window: simulatedWindow });
  assert.equal(listeners.pointerdown.options.once, true);
  listeners.pointerdown.callback();
  await Promise.resolve();
  const played = simulatedWindow.KWMashaFeedlyEffectModules.arcade.playVictorySound(simulatedWindow);
  assert.equal(played, true);
  assert.equal(oscillators.length, 6);
  assert.ok(oscillators.every((oscillator) => oscillator.type === 'square'));
  assert.deepEqual(oscillators.map((oscillator) => oscillator.frequency.value), [659.25, 783.99, 987.77, 1318.51, 987.77, 1318.51]);
  assert.ok(oscillators.every((oscillator) => oscillator.stoppedAt > oscillator.startedAt));
});

test('Admin-Vorschau nutzt die auswählbaren verspielten Abschlussanimationen', () => {
  const adminSource = fs.readFileSync(path.resolve(__dirname, '../../client/src/js/masha-feedly-admin.js'), 'utf8');
  const adminStyleSource = fs.readFileSync(path.resolve(__dirname, '../../client/src/scss/masha-feedly-admin.scss'), 'utf8');
  assert.match(adminSource, /data-masha-feedly-animation-preview/);
  assert.match(adminSource, /previewCompletionAnimation/);
  assert.match(adminStyleSource, /&__grid/);
  const adminPHP = fs.readFileSync(path.resolve(__dirname, '../../src/Admin/MashaFeedlyAdmin.php'), 'utf8');
  assert.match(adminPHP, /data-masha-feedly-animation-preview="hearts"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="arcade"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="retro"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="dino"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="ducks"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="frogs"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="iconShower"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="ghostSwarm"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="potion"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="catPaws"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="flowerPower"/);
  assert.match(adminPHP, /data-masha-feedly-animation-preview="pinballTilt"/);
  const widgetPHP = fs.readFileSync(path.resolve(__dirname, '../../src/Extension/MashaFeedlyWidgetExtension.php'), 'utf8');
  for (const name of ['check', 'glow', 'rings', 'confirmation']) {
    assert.match(adminPHP, new RegExp(`data-masha-feedly-animation-preview="${name}"`));
  }
  const orderedEffects = 'effects/unicorn\\.js[\\s\\S]*effects/rocket\\.js[\\s\\S]*effects/hearts\\.js[\\s\\S]*effects/arcade\\.js[\\s\\S]*effects/retro\\.js[\\s\\S]*effects/dino\\.js[\\s\\S]*effects/ducks\\.js[\\s\\S]*effects/frogs\\.js[\\s\\S]*effects/icon-shower\\.js[\\s\\S]*effects/ghost-swarm\\.js[\\s\\S]*effects/potion\\.js[\\s\\S]*effects/cat-paws\\.js[\\s\\S]*effects/flower-power\\.js[\\s\\S]*effects/pinball-tilt\\.js[\\s\\S]*effects/check\\.js[\\s\\S]*effects/glow\\.js[\\s\\S]*effects/rings\\.js[\\s\\S]*effects/confirmation\\.js[\\s\\S]*effects/runner\\.js[\\s\\S]*masha-feedly-entries\\.js';
  assert.match(adminPHP, new RegExp(orderedEffects));
  assert.match(widgetPHP, new RegExp(orderedEffects));
  assert.match(scss, /@use 'effects\/confetti';[\s\S]*@use 'effects\/unicorn';[\s\S]*@use 'effects\/rocket';[\s\S]*@use 'effects\/hearts';[\s\S]*@use 'effects\/arcade';[\s\S]*@use 'effects\/retro';[\s\S]*@use 'effects\/dino';[\s\S]*@use 'effects\/ducks';[\s\S]*@use 'effects\/frogs';[\s\S]*@use 'effects\/icon-shower';[\s\S]*@use 'effects\/ghost-swarm';[\s\S]*@use 'effects\/potion';[\s\S]*@use 'effects\/cat-paws';[\s\S]*@use 'effects\/flower-power';[\s\S]*@use 'effects\/pinball-tilt';[\s\S]*@use 'effects\/check';[\s\S]*@use 'effects\/glow';[\s\S]*@use 'effects\/rings';[\s\S]*@use 'effects\/confirmation';/);
  assert.deepEqual(compiledEffectSources, effectSources);
  assert.match(effectStyles[0], /kw-masha-feedly__confetti-piece/);
  assert.match(effectStyles[1], /kw-masha-feedly__unicorn-runner/);
  assert.match(effectStyles[2], /kw-masha-feedly__rocket-runner/);
  assert.match(effectStyles[2], /kw-masha-feedly__rocket-speedline/);
  assert.match(effectStyles[2], /kw-masha-feedly-rocket-speedline/);
  assert.match(effectStyles[2], /kw-masha-feedly__rocket-smoke-puff:nth-child\(3\) \{[^}]*animation-delay: 1\.52s/);
  assert.match(effectStyles[2], /kw-masha-feedly__rocket-flame \{[^}]*animation: kw-masha-feedly-rocket-flame[^}]*1\.88s/);
  assert.ok(effectStyles[2].includes('65% { opacity: 1; transform: translate(34vw, -43vh)'));
  assert.ok(effectStyles[2].includes('82% { opacity: 1; transform: translate(82vw, -97vh)'));
  assert.match(effectStyles[3], /kw-masha-feedly__burst-heart/);
  assert.match(effectStyles[3], /kw-masha-feedly-heart-pop/);
  assert.match(effectStyles[3], /animation: kw-masha-feedly-heart-bloom 2\.5s/);
  assert.match(effectStyles[3], /animation: kw-masha-feedly-heart-pop 2\.5s/);
  assert.match(effectStyles[4], /kw-masha-feedly__arcade-pixel/);
  assert.match(effectStyles[4], /kw-masha-feedly__arcade-invader/);
  assert.match(effectStyles[4], /kw-masha-feedly__arcade-message/);
  assert.match(effectStyles[5], /kw-masha-feedly__retro-dialog/);
  assert.match(effectStyles[5], /#000080/);
  assert.match(effectStyles[6], /kw-masha-feedly__dino-character/);
  assert.match(effectStyles[6], /kw-masha-feedly-dino-eaten/);
  assert.match(effectStyles[7], /kw-masha-feedly__serious-check-mark/);
  assert.match(effectStyles[8], /kw-masha-feedly__serious-glow/);
  assert.match(effectStyles[8], /::before[\s\S]*?radial-gradient[\s\S]*?opacity: 1[\s\S]*?scale\(1\.7\)/);
  assert.match(effectStyles[8], /::after[\s\S]*?border: 3px solid[\s\S]*?scale\(3\.4\)/);
  assert.match(effectStyles[9], /kw-masha-feedly__serious-ring/);
  assert.match(effectStyles[10], /kw-masha-feedly__serious-confirmation-card/);
  assert.match(effectStyles[11], /kw-masha-feedly__duck-parade/);
  assert.match(effectStyles[11], /kw-masha-feedly-duck-waddle/);
  assert.match(compiledStyles, /kw-masha-feedly__rocket-runner/);
});

test('ausgeliefertes JavaScript entspricht der getesteten Quelldatei', () => {
  assert.equal(compiledSource, source);
});

test('liefert lesbare Schrift und die Fächeranimation in den kompilierten Widget-Stilen aus', () => {
  assert.match(widgetTemplate, /saved-view-select[\s\S]*?<details class="kw-masha-feedly__saved-views-details">[\s\S]*?SAVED_VIEW_DETAILS[\s\S]*?data-masha-feedly-saved-views/);
  assert.match(scss, /\.kw-masha-feedly__saved-views-details > summary \{[^}]*cursor: pointer;/);
  assert.match(scss, /\.kw-masha-feedly__entries-count \{ padding-top: 4px; padding-bottom: 6\.4px; \}/);
  assert.match(compiledStyles, /\.kw-masha-feedly__saved-views-details>summary\{[^}]*cursor:pointer/);
  assert.match(scss, /\.kw-masha-feedly__attachment-field\s*\{ position: relative; display: grid; grid-template-columns: 49\.6px/);
  assert.match(scss, /\.kw-masha-feedly__entry-attachments img\s*\{ display: block; max-width: 100%;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__entry-attachments img\{display:block;max-width:100%/);
  assert.match(scss, /\.kw-masha-feedly__entry-description a, \.kw-masha-feedly__entry-card p a, \.kw-masha-feedly__comment p a\s*\{ color: #9e1c60; text-decoration: underline;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__entry-description a,\.kw-masha-feedly__entry-card p a,\.kw-masha-feedly__comment p a\{color:#9e1c60/);
  assert.match(scss, /\.kw-masha-feedly__entry-description\s*\{[^}]*font-size:\s*calc\(24px \* var\(--masha-font-scale, 1\)\)/s);
  assert.match(scss, /kw-masha-feedly-fan-from-under/);
  assert.match(effectStyles[1], /kw-masha-feedly-unicorn-run/);
  assert.match(effectStyles[1], /kw-masha-feedly-unicorn-run 4\.8s/);
  assert.match(scss, /\.kw-masha-feedly__rainbow \{ position: relative; display: grid; width: 72px; min-height: 72px;/);
  assert.match(scss, /kw-masha-feedly__rainbow-copy\[hidden\]/);
  assert.match(scss, /\.kw-masha-feedly__rainbow-copy \{ position: absolute; z-index: 3;/);
  assert.match(scss, /\.kw-masha-feedly__rainbow \{ overflow: visible; \}/);
  assert.match(scss, /\.kw-masha-feedly__help-button \{ align-self: flex-end; margin-top: 36px; \}/);
  assert.match(scss, /\.kw-masha-feedly__entry-environment \{ display: grid; grid-template-columns: minmax\(0, 1fr\);/);
  assert.match(scss, /\.kw-masha-feedly__entry-environment dd \{ min-width: 0; margin: 0 0 6\.4px;[\s\S]*?overflow-wrap: anywhere;/);
  assert.match(scss, /\.kw-masha-feedly__priority \{ display: inline-flex; align-items: center; justify-content: center;/);
  assert.match(scss, /\.kw-masha-feedly__edit-priority \{ display: inline-flex; width: 28px;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__priority\{display:inline-flex;align-items:center;justify-content:center/);
  assert.match(compiledStyles, /\.kw-masha-feedly__edit-priority\{display:inline-flex;width:28px/);
  assert.match(compiledAdminStyles, /\.masha-feedly-board__priority\{display:inline-flex;align-items:center;justify-content:center/);
  assert.match(scss, /\.kw-masha-feedly__attachment-field input::file-selector-button \{ margin-right: 11\.2px;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__attachment-field input::file-selector-button\{margin-right:11\.2px/);
  assert.match(scss, /\.kw-masha-feedly__entry-environment-details > summary \{ overflow-wrap: anywhere; \}/);
  assert.match(scss, /\.kw-masha-feedly__entries-dialog > \.kw-masha-feedly__dialog-header,[\s\S]*?\.kw-masha-feedly__entries-list \{ padding-right: 160px; \}/);
  assert.match(scss, /\.kw-masha-feedly__entries-modal \{ width: 528px; \}/);
  assert.match(scss, /@media \(max-width: 76rem\) \{[\s\S]*?\.kw-masha-feedly__entries-list \{ padding-right: 112px; \}/);
  assert.match(scss, /\.kw-masha-feedly__entries-modal \{ width: calc\(33vw \+ 48px\); \}/);
  assert.match(scss, /\.kw-masha-feedly__actions\s*\{ flex-direction: column; align-items: center; gap: 16px;/);
  assert.match(scss, /\.kw-masha-feedly__add\s*\{ display: flex; width: 100%; min-height: 112px; height: 112px; flex: 0 0 112px; box-sizing: border-box; align-items: center; justify-content: center;/);
  assert.match(scss, /\.kw-masha-feedly__entries-dialog > \.kw-masha-feedly__dialog-header\s*\{ padding-right: 56px;/);
  assert.match(scss, /\.kw-masha-feedly__entries-modal\s*\{ overflow: visible;/);
  assert.match(scss, /\.kw-masha-feedly__entries-dialog\s*\{ box-shadow: -14\.4px 0 40px/);
  assert.match(scss, /\.kw-masha-feedly__entry-number\s*\{ flex: 0 0 auto; padding: 2\.4px 7\.2px;/);
  assert.match(scss, /\.kw-masha-feedly__closed-button\s*\{ display: flex; width: 100%; min-height: 72px;/);
  assert.match(scss, /\.kw-masha-feedly__edit-modal,[\s\S]*?data-list-open="true"\] \.kw-masha-feedly__edit-modal \{ width: min\(688px, calc\(100vw - 576px\)\); \}/);
  assert.match(scss, /\.kw-masha-feedly__edit-dialog > \.kw-masha-feedly__dialog-header,[\s\S]*?\.kw-masha-feedly__edit-dialog > form \{ padding-right: 256px; \}/);
  assert.match(scss, /@media \(max-width: 76rem\) \{[\s\S]*?padding-right: clamp\(96px, 9vw, 128px\); \}/);
  assert.match(scss, /@media \(max-width: 40rem\) \{[\s\S]*?padding-right: 20px; \}/);
  assert.match(scss, /data-success-visible="true"\]\[data-list-open="true"\] \.kw-masha-feedly__edit-modal \{ width: min\(688px, calc\(100vw - 688px\)\); \}/);
  assert.match(scss, /\.kw-masha-feedly\[data-edit-open="true"\] \.kw-masha-feedly__entries-modal \{ visibility: hidden; \}/);
  assert.match(scss, /\.kw-masha-feedly__add > span:first-child\s*\{ display: block; margin: 0; font-size: calc\(88px \* var\(--masha-font-scale, 1\)\); font-weight: 400; line-height: 1; transform: translateY\(-0\.06em\);/);
  assert.match(scss, /\.kw-masha-feedly__help-button\s*\{ display: grid; width: 52px; min-width: 52px; height: 52px; min-height: 52px; flex: 0 0 52px; aspect-ratio: 1; margin-top: 32px; padding: 0; place-items: center;/);
  assert.match(scss, /\.kw-masha-feedly__entries-modal\s*\{ right: 256px;/);
  assert.match(scss, /data-list-open="true"\] \.kw-masha-feedly__edit-modal \{ right: 560px;/);
  assert.match(scss, /__panel > \.kw-masha-feedly__header \.kw-masha-feedly__close \{ position: absolute;/);
  assert.match(scss, /__edit-modal:not\(\[hidden\]\) \{ filter: drop-shadow/);
  assert.match(scss, /\.kw-masha-feedly__panel\s*\{\s*width:\s*288px/);
  assert.match(scss, /data-success-visible="true"[^\n]*width:\s*400px/);
  assert.match(scss, /\.kw-masha-feedly__count-button\s*\{\s*width:\s*100%/);
  assert.match(compiledStyles, /kw-masha-feedly-fan-from-under/);
  assert.match(compiledStyles, /font-size:\s*calc\(24px\*var\(--masha-font-scale, 1\)\)/);
  assert.match(scss, /data-font-size="small"\] \{ --masha-font-scale: 0\.78; \}/);
  assert.match(scss, /data-font-size="medium"\] \{ --masha-font-scale: 0\.9; \}/);
  assert.match(scss, /data-font-size="large"\] \{ --masha-font-scale: 1\.1; \}/);
  assert.match(widgetTemplate, /data-font-size="\$FontSize"/);
  const remFontSizes = [...scss.matchAll(/font-size:\s*(?:calc\()?([0-9]*\.?[0-9]+)rem/g)].map((match) => Number(match[1]));
  assert.ok(Math.min(...remFontSizes) >= 0.625, 'keine Schriftgröße darf unter 10 px bei 16 px Root-Schrift liegen');
  assert.match(scss, /right:\s*calc\(var\(--kw-feedly-panel-width\) - 15px\); width:\s*min\(var\(--kw-feedly-list-width\)/);
  assert.match(scss, /right:\s*calc\(var\(--kw-feedly-panel-width\) \+ var\(--kw-feedly-list-width\) - 30px\)/);
  assert.match(scss, /\.kw-masha-feedly__edit-dialog > form[\s\S]*?padding-right:\s*20px;/);
  assert.match(scss, /--kw-feedly-list-width:\s*592px/);
  assert.match(scss, /.kw-masha-feedly__entries-toolbar { grid-template-columns: repeat\(3, minmax\(0, 1fr\)\); }/);
  assert.match(scss, /.kw-masha-feedly__entry-card { grid-template-columns: minmax\(0, 1fr\) auto;/);
  assert.match(compiledStyles, /--kw-feedly-list-width:\s*592px/);
  assert.match(compiledStyles, /--kw-feedly-list-width:\s*clamp\(352px, 33vw, 528px\)/);
  assert.match(compiledStyles, /right:calc\(var\(--kw-feedly-panel-width\) \+ var\(--kw-feedly-list-width\) - 30px\)/);
  assert.match(scss, /--kw-feedly-panel-width:\s*clamp\(240px, 18vw, 272px\)/);
  assert.match(scss, /--kw-feedly-list-width:\s*clamp\(320px, 30vw, 480px\)/);
  assert.match(scss, /--kw-feedly-edit-width:\s*clamp\(360px, 34vw, 560px\)/);
  assert.match(scss, /@media \(max-width: 40rem\) \{[\s\S]*?--kw-feedly-panel-width: min\(82vw, 272px\); --kw-feedly-list-width: 100vw; --kw-feedly-edit-width: 100vw;/);
  assert.match(scss, /--kw-feedly-panel-width:\s*clamp\(200px, 15vw, 224px\)/);
  assert.match(scss, /__actions > \.kw-masha-feedly__add \{ min-height: 72px; height: 72px;/);
  assert.match(scss, /__actions > \.kw-masha-feedly__news-button,[\s\S]*?min-height: 56px; height: 56px;/);
  assert.match(scss, /--kw-feedly-panel-width:\s*clamp\(64px, 4\.5vw, 76px\)/);
  assert.match(scss, /__actions > \.kw-masha-feedly__add,[\s\S]*?width: 56px; min-width: 56px; min-height: 56px; height: 56px;/);
  assert.match(scss, /__count-button strong \{ position: absolute; top: -5px; right: -5px; display: grid; min-width: 20px; width: auto; height: 20px;[\s\S]*?border-radius: 999px; background: #39882d;/);
  assert.match(scss, /\.kw-masha-feedly__entries-toolbar,[\s\S]*?padding-right:\s*32px;/);
  assert.match(scss, /\.kw-masha-feedly__edit-dialog > form,[\s\S]*?padding-right:\s*40px;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__entries-toolbar\{display:grid/);
  assert.match(compiledStyles, /\.kw-masha-feedly__edit-dialog>form,[\s\S]*?padding-right:\s*40px/);
  assert.match(scss, /\.kw-masha-feedly__comment-edited\s*\{ display: inline-block;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__comment-edited\{display:inline-block/);
  assert.match(scss, /\.kw-masha-feedly__onboarding-shade\s*\{ position: fixed; z-index: 9999;/);
  assert.match(scss, /\.kw-masha-feedly__onboarding-tip\.is-feedback\s*\{ border-color: #d51b43;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__onboarding-shade\{position:fixed;z-index:9999/);
  assert.match(compiledStyles, /\.kw-masha-feedly__onboarding-tip\.is-feedback\{border-color:#d51b43/);
  assert.match(scss, /\.kw-masha-feedly__onboarding-logo \{ display: block; width: 52px;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__onboarding-logo\{display:block;width:52px/);
  assert.match(widgetTemplate, /kw-masha-feedly__onboarding-welcome-header[\s\S]*?kw-masha-feedly__onboarding-logo--welcome/);
  assert.match(scss, /\.kw-masha-feedly__onboarding-welcome-header \{ align-items: center;/);
  assert.match(scss, /\.kw-masha-feedly__onboarding-logo--welcome \{ width: clamp\(72px, 5vw, 84px\); height: clamp\(72px, 5vw, 84px\); \}/);
  assert.match(compiledStyles, /\.kw-masha-feedly__onboarding-logo--welcome\{width:clamp\(72px,5vw,84px\);height:clamp\(72px,5vw,84px\)\}/);
  assert.match(scss, /\.kw-masha-feedly__onboarding-modal a\.kw-masha-feedly__submit \{ display: inline-flex;/);
  assert.match(compiledStyles, /\.kw-masha-feedly__onboarding-modal a\.kw-masha-feedly__submit\{display:inline-flex/);
});

test('liefert beide auswählbaren Erscheinungsbilder mit sachlicher Gestaltung aus', () => {
  assert.match(widgetTemplate, /data-theme="\$Theme"/);
  assert.match(scss, /\.kw-masha-feedly\[data-theme="serious"\]/);
  assert.match(compiledStyles, /\.kw-masha-feedly\[data-theme=serious\]/);
  assert.match(germanTranslations, /CONFIG_THEME_PLAYFUL: 'Verspielt/);
  assert.match(germanTranslations, /CONFIG_THEME_SERIOUS: 'Seriös/);
  assert.match(germanTranslations, /SUCCESS_EMPTY_BOARD_TITLE: 'Noch keine Einträge'/);
});

test('zeigt den gestalteten Upload in Erstellung und Bearbeitung mit dem Upload-Symbol', () => {
  const uploadFields = [...widgetTemplate.matchAll(/<label class="kw-masha-feedly__attachment-field">([\s\S]*?)<\/label>/g)];
  assert.equal(uploadFields.length, 2);
  for (const [, markup] of uploadFields) {
    assert.match(markup, /<svg class="kw-masha-feedly__upload-icon"[^>]*aria-hidden="true"/);
    assert.match(markup, /<input type="file" name="Attachments\[\]"[^>]*multiple/);
    assert.match(markup, /accept="image\/jpeg,image\/png,image\/gif,image\/webp,application\/pdf,application\/zip,[^"]+"/);
    assert.match(markup, /<span class="kw-masha-feedly__attachment-title">/);
    assert.match(markup, /<small>Maximal 10 MB pro Datei, 20 MB insgesamt<\/small>/);
  }
  assert.match(widgetTemplate, /aria-label="Dateien auswählen"/);
  assert.match(widgetTemplate, /aria-label="Weitere Dateien auswählen"/);
});

test('liefert den Feedback-Button mit Wartetext, zugänglichem Label und passendem Symbol aus', () => {
  assert.match(widgetTemplate, /data-masha-feedly-open-feedback/);
  assert.match(widgetTemplate, /data-masha-feedly-open-page-list aria-label="[^"]*"><svg class="kw-masha-feedly__page-icon" aria-hidden="true" viewBox="0 0 512 512" focusable="false"><path d="m446\.605 124\.392-/);
  assert.match(widgetTemplate, /class="kw-masha-feedly__feedback-button"[^\n]*hidden/);
  assert.match(widgetTemplate, /data-masha-feedly-feedback-count/);
  assert.match(widgetTemplate, /viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M117\.333 149\.333H352/);
  assert.match(widgetTemplate, /value="feedback"/);
  assert.match(widgetTemplate, /value="page-open"/);
  assert.match(widgetTemplate, /Der Globus zeigt offene Fehler auf der gesamten Website/);
  assert.match(widgetTemplate, /Das Seitensymbol zeigt offene Fehler auf der Seite/);
  assert.match(scss, /\.kw-masha-feedly__count-button::after \{ content: attr\(data-label\);/);
  assert.match(scss, /\.kw-masha-feedly__feedback-button\[hidden\] \{ display: none !important; \}/);
  assert.match(compiledStyles, /\.kw-masha-feedly__feedback-button\[hidden\]\{display:none !important\}/);
});

test('zeigt Schätzungswarteschlangen für Freigabeberechtigte, mit passenden Icons, Zählern und Listenaktionen', () => {
  assert.match(widgetTemplate, /<% if \$CanApproveEstimate %>[\s\S]*?data-masha-feedly-open-estimate-pending[\s\S]*?data-masha-feedly-estimate-pending-count[\s\S]*?data-masha-feedly-open-estimate-approved[\s\S]*?data-masha-feedly-estimate-approved-count[\s\S]*?<% end_if %>/);
  for (const buttonName of ['pending', 'approved']) {
    const button = widgetTemplate.match(new RegExp(`<button\\b(?=[^>]*data-masha-feedly-open-estimate-${buttonName})[\\s\\S]*?<\\/button>`))?.[0] || '';
    assert.match(button, /\shidden>/);
  }
  assert.match(widgetTemplate, /data-masha-feedly-open-estimate-pending[\s\S]*?viewBox="0 0 512 512"[\s\S]*?data-masha-feedly-estimate-pending-count/);
  assert.match(widgetTemplate, /data-masha-feedly-open-estimate-approved[\s\S]*?viewBox="0 0 512\.12 512\.12"[\s\S]*?data-masha-feedly-estimate-approved-count/);
  assert.match(widgetTemplate, /<option value="estimate-pending">/);
  assert.match(widgetTemplate, /<option value="estimate-approved">/);
  assert.match(source, /estimatePendingButton\.hidden = !data\.canApproveEstimate \|\| estimatePendingCount === 0/);
  assert.match(source, /estimateApprovedButton\.hidden = !data\.canApproveEstimate \|\| estimateApprovedCount === 0/);
  assert.match(source, /estimatePendingButton\?\.addEventListener\('click', \(event\) => openList\('estimate-pending'/);
  assert.match(source, /estimateApprovedButton\?\.addEventListener\('click', \(event\) => openList\('estimate-approved'/);
  assert.match(source, /'estimate-pending': 'ESTIMATE_PENDING_BUTTON', 'estimate-approved': 'ESTIMATE_APPROVED_BUTTON'/);
  assert.match(scss, /\.kw-masha-feedly__estimate-queue-button\[hidden\] \{ display: none !important; \}/);
  assert.match(widgetTemplate, /<% if \$CanApproveEstimate %><section class="kw-masha-feedly__estimate"/);
  assert.match(widgetTemplate, /<% if \$CanManageEstimate %><div class="kw-masha-feedly__estimate-fields"/);
  assert.match(widgetTemplate, /data-masha-feedly-estimate-readonly/);
  assert.match(scss, /\.kw-masha-feedly__entry-estimate-status\.is-approved/);
  assert.match(source, /\['estimate_pending', 'estimate_approved'\]\.includes\(entry\.categoryRole\)/);
  assert.match(source, /metadata\.append\(estimateStatus\)/);
});

test('hält das Kostenschätzungsfeld bis zur passenden Statusauswahl visuell verborgen', () => {
  const hiddenRule = /\.kw-masha-feedly__estimate\[hidden\]\s*\{\s*display:\s*none\s*!important;?\s*\}/;
  assert.match(scss, hiddenRule);
  assert.match(compiledStyles, hiddenRule);
});

test('zeigt das Prioritätssymbol im Kopf des geöffneten Eintrags an', () => {
  assert.match(widgetTemplate, /data-masha-feedly-edit-priority role="img" aria-label="Priorität" hidden/);
  assert.match(widgetTemplate, /class="kw-masha-feedly__edit-header-actions"><span class="kw-masha-feedly__edit-priority" data-masha-feedly-edit-priority role="img" aria-label="Priorität" hidden><\/span><button type="button" class="kw-masha-feedly__entry-share"/);
  assert.match(scss, /\.kw-masha-feedly__edit-header-actions \{ display: flex; flex: 0 0 auto; align-items: center; gap: 8px; margin-left: auto; \}/);
});

test('hält die Eintragsanlage schlank und bietet typisierte Verknüpfungen beim Bearbeiten an', () => {
  assert.doesNotMatch(widgetTemplate, /data-similar-url|data-masha-feedly-similar|kw-masha-feedly__similar/);
  assert.match(widgetTemplate, /data-masha-feedly-edit-relations aria-label=/);
  assert.match(widgetTemplate, /data-masha-feedly-relation-type/);
  assert.ok(widgetTemplate.indexOf('data-masha-feedly-edit-relations') < widgetTemplate.indexOf('kw-masha-feedly__relations-details'), 'Der Zusammenhang muss vor dem einklappbaren Editor sichtbar sein.');
  assert.match(widgetTemplate, /<details class="kw-masha-feedly__entry-environment-details kw-masha-feedly__relations-details"><summary>/);
  assert.match(widgetTemplate, /value="blocked_by"/);
  assert.match(widgetTemplate, /value="duplicate_of"/);
  assert.match(widgetTemplate, /data-masha-feedly-related-entries multiple/);
  assert.match(widgetTemplate, /data-masha-feedly-relation-search/);
  assert.match(scss, /\.kw-masha-feedly__relations/);
});

test('ordnet Browserdetails, Zusammenhänge und Verlauf unter dem Speichern ein und zeigt Zuständige nur als Auswahl', () => {
  const start = widgetTemplate.indexOf('<form data-masha-feedly-edit-form');
  const form = widgetTemplate.slice(start, widgetTemplate.indexOf('</form>', start));
  assert.ok(form.indexOf('data-masha-feedly-close-edit') < form.indexOf('kw-masha-feedly__edit-extra-details'));
  assert.ok(form.indexOf('data-masha-feedly-edit-environment-details') < form.indexOf('kw-masha-feedly__relations-details'));
  assert.ok(form.indexOf('kw-masha-feedly__relations-details') < form.indexOf('kw-masha-feedly__history'));
  assert.doesNotMatch(form, /data-masha-feedly-edit-assignees/);
  assert.match(form, /name="AssignedMemberIDs\[\]"/);
  assert.match(scss, /\.kw-masha-feedly__edit-extra-details \{ display: grid; gap: 10\.4px; margin-top: 16px; \}/);
});

test('zeigt eingehende Verknüpfungen beim Öffnen schon oberhalb des eingeklappten Editors', async () => {
  const env = createWidgetEnvironment();
  env.setEntryRelations([{ id: 83, title: 'Ursprünglicher Fehler', type: 'has_duplicate', direction: 'incoming' }]);
  await env.listeners['kw-masha-feedly:opened']();
  const card = env.listContainer.children.find((child) => child.dataset?.entryId === '71');
  await env.listContainer.listeners.click({
    target: card,
    preventDefault() {},
  });
  assert.equal(env.editRelations.children.length, 1);
  assert.match(env.editRelations.children[0].children[1].textContent, /Hat Duplikat · #83 Ursprünglicher Fehler/);
});

test('übernimmt den Beziehungstyp beim Öffnen und sendet die geänderte Auswahl beim Speichern', async () => {
  const env = createWidgetEnvironment();
  env.setEntryRelations([{ id: 83, title: 'API antwortet nicht', type: 'duplicate_of', direction: 'outgoing' }]);
  await env.listeners['kw-masha-feedly:opened']();
  const card = env.listContainer.children.find((child) => child.dataset?.entryId === '71');
  await env.listContainer.listeners.click({ target: card, preventDefault() {} });
  assert.equal(env.relationTypeSelect.value, 'duplicate_of', 'Der gespeicherte Typ muss im Formular ausgewählt sein.');
  const selected = env.relatedEntrySelect.options.find((option) => option.value === '83');
  assert.equal(selected.selected, true, 'Der gespeicherte Zieleintrag muss ausgewählt sein.');
  env.relationTypeSelect.value = 'blocked_by';
  await env.editForm.listeners.submit({ preventDefault() {} });
  const body = env.postCalls.at(-1).options.body.values;
  assert.equal(body.RelationType, 'blocked_by');
  assert.deepEqual(Array.from(body.RelatedEntryIDs), ['83']);
});

test('sendet entfernte Verknüpfungen und zeigt die bestätigte Entfernung im Verlauf', async () => {
  const env = createWidgetEnvironment();
  env.setEntryRelations([{ id: 83, title: 'API antwortet nicht', type: 'blocked_by', direction: 'outgoing' }]);
  await env.listeners['kw-masha-feedly:opened']();
  const card = env.listContainer.children.find((child) => child.dataset?.entryId === '71');
  await env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.relatedEntrySelect.options.forEach((option) => { option.selected = false; });
  env.postResponses.push({ ok: true, json: async () => ({
    success: true,
    message: 'Verknüpfung entfernt.',
    relations: [],
    history: [{
      type: 'relations', oldValue: 'Blockiert durch #83 API antwortet nicht', newValue: '',
      actor: 'Erika Muster', created: '2026-10-02T10:30:00Z',
    }],
  }) });

  await env.editForm.listeners.submit({ preventDefault() {} });

  assert.deepEqual(Array.from(env.postCalls.at(-1).options.body.values.RelatedEntryIDs), []);
  assert.equal(env.editRelations.children.length, 0);
  assert.equal(env.editHistory.children[0].children[0].textContent, 'Verknüpfungen: Blockiert durch #83 API antwortet nicht → Keine Verknüpfungen');
});

/** Simuliert die Widget-Oberfläche, um Seitenabfrage und Eintragsblasen im echten Ablauf zu prüfen. */
function createWidgetEnvironment(locationHref = 'https://feedly:8890/about-us?preview=1', address = 'du', priorityIconType = 'warning', entryDueDate = '') {
  const listeners = {};
  const requests = [];
  const dispatchedEvents = [];
  const postResponses = [];
  const postCalls = [];
  const intervalCallbacks = [];
  let savedViewsData = [];
  class Element {
    constructor(dataset = {}) {
      this.dataset = dataset;
      this.children = [];
      this.listeners = {};
      this.attributes = {};
      this.options = [];
      this.value = '';
      this.textContent = '';
      this.hidden = false;
      this.style = { setProperty(name, value) { this[name] = value; } };
      this.className = '';
      this.dateTime = '';
    }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    setAttribute(name, value) { this.attributes[name] = value; }
    removeAttribute(name) { delete this.attributes[name]; }
    append(...elements) { elements.forEach((element) => { this.children.push(element); element.parentElement = this; }); }
    prepend(...elements) { elements.reverse().forEach((element) => { this.children.unshift(element); element.parentElement = this; }); }
    replaceChildren(...children) { this.children = children; this.options = children; }
    querySelector(selector) {
      if (selector === '[data-entry-unread]') {
        const find = (node) => node.dataset?.entryUnread ? node : node.children?.map(find).find(Boolean) || null;
        return this.children.map(find).find(Boolean) || null;
      }
      const match = selector.match(/\[data-entry-id="(\d+)"\]/);
      if (match) return this.children.find((child) => child.dataset.entryId === match[1]) || null;
      if (selector === '[type="submit"]') return this.submitButton || null;
      return null;
    }
    querySelectorAll(selector) {
      if (selector === '[name="AssignedMemberIDs[]"]') return this.assigneeFields || [];
      if (selector === '[name="Attachments[]"]') return this.attachmentFields || [];
      return [];
    }
    closest(selector) {
      if (selector === 'a') return this.tagName === 'a' ? this : null;
      if (selector === '[data-entry-share]') return this.dataset.entryShare ? this : null;
      if (selector === '.kw-masha-feedly__entry-card') return this.className === 'kw-masha-feedly__entry-card' ? this : (this.parentElement?.closest(selector) || null);
      if (selector === '[data-entry-id]') return this.dataset.entryId ? this : (this.parentElement?.closest(selector) || null);
      return this.parentElement?.closest(selector) || null;
    }
    scrollIntoView() { this.scrolled = true; }
    select() { this.selected = true; }
    remove() { this.removed = true; }
    cloneNode() { const clone = new Element(); clone.tagName = this.tagName; clone.className = this.className; return clone; }
    getBoundingClientRect() { return { left: 150, top: 120, width: 200 }; }
  }
const widget = new Element({ listUrl: '/__masha-feedly/listEntries', markEntryReadUrl: '/__masha-feedly/markEntryRead', savedViewsUrl: '/__masha-feedly', saveViewUrl: '/__masha-feedly', deleteViewUrl: '/__masha-feedly', securityId: 'test-token', theme: 'playful' });
  widget.dataset.address = address;
  widget.dataset.unicornUrl = '/_resources/kooperativeweb/masha-feedly/client/dist/icons/masha-feedly-unicorn.svg';
  widget.contains = () => false;
  const pageCount = new Element();
  const totalCount = new Element();
  const closedCountDisplay = new Element();
  const feedbackButton = new Element(); feedbackButton.hidden = true;
  const feedbackCountDisplay = new Element();
  const unreadCountDisplay = new Element();
  const newsSummary = new Element();
  const openListButton = new Element();
  const openClosedButton = new Element();
  const openPageListButton = new Element();
  const openNewsButton = new Element();
  const newsIconSVG = new Element(); newsIconSVG.tagName = 'svg';
  const rainbow = new Element(); rainbow.hidden = true;
  const rainbowCopy = new Element(); rainbowCopy.hidden = true;
  const rainbowTitle = new Element();
  const rainbowMessage = new Element();
  const helpModal = new Element(); helpModal.hidden = true;
  const openHelpButton = new Element();
  const closeHelpButton = new Element();
  const listModal = new Element(); listModal.hidden = true;
  const closeListButton = new Element();
  const modeField = new Element(); modeField.value = 'all';
  const categoryFilter = new Element(); categoryFilter.value = '';
  const priorityFilter = new Element(); priorityFilter.value = '';
  const filtersDetails = new Element(); filtersDetails.open = false;
  const filterCount = new Element();
  const filterState = new Element();
  const activeFilters = new Element(); activeFilters.hidden = true;
  const priorityFilterIcon = new Element();
  const savedViewSelect = new Element(); savedViewSelect.value = '';
  const savedViewName = new Element(); savedViewName.value = '';
  const saveViewButton = new Element();
  const deleteViewButton = new Element(); deleteViewButton.disabled = true;
  const savedViewStatus = new Element();
  const listCount = new Element();
  const listContainer = new Element();
  const panel = new Element(); panel.hidden = true;
  const toggle = new Element();
  const pageTarget = new Element();
  let entryPageURL = 'https://feedly:8890/about-us';
  let entryTitleValue = 'Fehler auf About us';
  let entryAssigneeList = [{ id: 12, name: 'Ada Beispiel', initials: 'EM', color: '#E95DAB', imageURL: '' }];
  let entryContent = 'Der Inhalt ist verschoben.';
  let entryAttachments = [];
  let entryRelations = [];
  const editModal = new Element(); editModal.hidden = true;
  const editStatus = new Element();
  const editHeading = new Element();
  const editPriorityIcon = new Element(); editPriorityIcon.hidden = true;
  const shareActiveEntryButton = new Element();
  const editHistory = new Element();
  const editContext = new Element();
  const editRelations = new Element();
  const relationTypeSelect = new Element(); relationTypeSelect.value = 'related';
  const relatedEntrySelect = new Element();
  const editDescription = new Element();
  const editAttachments = new Element();
  const editEnvironment = new Element();
  const editEnvironmentDetails = new Element(); editEnvironmentDetails.hidden = true;
  const editAssignees = new Element();
  const commentList = new Element();
  const commentCount = new Element();
  const commentStatus = new Element();
  const commentForm = new Element({ commentUrl: '/__masha-feedly-comment', securityId: 'test-token' });
  commentForm.elements = { EntryID: new Element(), CommentText: new Element() };
  commentForm.submitButton = new Element();
  commentForm.querySelector = (selector) => selector === '[type="submit"]' ? commentForm.submitButton : null;
  const editForm = new Element({ updateUrl: '/__masha-feedly/updateEntry', securityId: 'test-token' });
  editForm.elements = { EntryID: new Element(), CategoryID: new Element(), PriorityID: new Element(), DueDate: new Element() };
  const editAttachmentInput = new Element();
  editAttachmentInput.name = 'Attachments[]';
  editAttachmentInput.files = [];
  editForm.attachmentFields = [editAttachmentInput];
  editForm.submitButton = new Element();
  editForm.assigneeFields = [{ value: '12', checked: false }, { value: '15', checked: false }];
  editForm.querySelector = (selector) => selector === '[type="submit"]' ? editForm.submitButton : null;
  const controls = new Map([
    ['[data-masha-feedly-entry-form]', new Element()],
    ['[data-masha-feedly-page-count]', pageCount],
    ['[data-masha-feedly-total-count]', totalCount],
    ['[data-masha-feedly-closed-count]', closedCountDisplay],
    ['[data-masha-feedly-open-feedback]', feedbackButton],
    ['[data-masha-feedly-feedback-count]', feedbackCountDisplay],
    ['[data-masha-feedly-unread-count]', unreadCountDisplay],
    ['[data-masha-feedly-news-summary]', newsSummary],
    ['[data-masha-feedly-open-list]', openListButton],
    ['[data-masha-feedly-open-closed]', openClosedButton],
    ['[data-masha-feedly-open-page-list]', openPageListButton],
    ['[data-masha-feedly-open-news]', openNewsButton],
    ['[data-masha-feedly-open-news] svg', newsIconSVG],
    ['[data-masha-feedly-rainbow]', rainbow],
    ['[data-masha-feedly-rainbow-copy]', rainbowCopy],
    ['[data-masha-feedly-rainbow-title]', rainbowTitle],
    ['[data-masha-feedly-rainbow-message]', rainbowMessage],
    ['[data-masha-feedly-help-modal]', helpModal],
    ['[data-masha-feedly-open-help]', openHelpButton],
    ['[data-masha-feedly-close-help]', closeHelpButton],
    ['[data-masha-feedly-entries-modal]', listModal],
    ['[data-masha-feedly-close-list]', closeListButton],
    ['[data-masha-feedly-list-mode]', modeField],
    ['[data-masha-feedly-category-filter]', categoryFilter],
    ['[data-masha-feedly-priority-filter]', priorityFilter],
    ['[data-masha-feedly-filters-details]', filtersDetails],
    ['[data-masha-feedly-filter-count]', filterCount],
    ['[data-masha-feedly-filter-state]', filterState],
    ['[data-masha-feedly-active-filters]', activeFilters],
    ['[data-masha-feedly-priority-filter-icon]', priorityFilterIcon],
    ['[data-masha-feedly-saved-view]', savedViewSelect],
    ['[data-masha-feedly-saved-view-name]', savedViewName],
    ['[data-masha-feedly-save-view]', saveViewButton],
    ['[data-masha-feedly-delete-view]', deleteViewButton],
    ['[data-masha-feedly-saved-view-status]', savedViewStatus],
    ['[data-masha-feedly-list-count]', listCount],
    ['[data-masha-feedly-entries-list]', listContainer],
    ['[data-masha-feedly-edit-modal]', editModal],
    ['[data-masha-feedly-edit-form]', editForm],
    ['[data-masha-feedly-edit-status]', editStatus],
    ['[data-masha-feedly-edit-heading]', editHeading],
    ['[data-masha-feedly-edit-priority]', editPriorityIcon],
    ['[data-masha-feedly-history]', editHistory],
    ['[data-masha-feedly-share-active-entry]', shareActiveEntryButton],
    ['[data-masha-feedly-edit-context]', editContext],
    ['[data-masha-feedly-edit-relations]', editRelations],
    ['[data-masha-feedly-relation-type]', relationTypeSelect],
    ['[data-masha-feedly-related-entries]', relatedEntrySelect],
    ['[data-masha-feedly-edit-description]', editDescription],
    ['[data-masha-feedly-edit-attachments]', editAttachments],
    ['[data-masha-feedly-edit-environment]', editEnvironment],
    ['[data-masha-feedly-edit-environment-details]', editEnvironmentDetails],
    ['[data-masha-feedly-comment-form]', commentForm],
    ['[data-masha-feedly-comments]', commentList],
    ['[data-masha-feedly-comment-count]', commentCount],
    ['[data-masha-feedly-comment-status]', commentStatus],
    ['.kw-masha-feedly__panel', panel],
    ['.kw-masha-feedly__toggle', toggle],
  ]);
  widget.querySelector = (selector) => controls.get(selector) || null;
  widget.querySelectorAll = () => [];
  const document = {
    body: new Element(),
    documentElement: { classList: { add() {}, remove() {} } },
    addEventListener(name, callback) { listeners[name] = callback; },
    dispatchEvent(event) { dispatchedEvents.push(event.type); },
    querySelector(selector) {
      if (selector === '[data-kw-masha-feedly]') return widget;
      if (selector === '#about-title') return pageTarget;
      return null;
    },
    createElement(tagName) { const element = new Element(); element.tagName = tagName; return element; },
    createTextNode(text) { return { textContent: text }; },
    execCommand(command) {
      if (command !== 'copy') return false;
      const textarea = this.body.children.find((child) => child.tagName === 'textarea');
      if (!textarea?.selected) return false;
      window.copiedText = textarea.value;
      return true;
    },
  };
  let cleanedURL = '';
  const window = {
    location: { href: locationHref },
    navigator: { clipboard: { async writeText(value) {
      window.clipboardCalls = [...(window.clipboardCalls || []), value];
      if (window.clipboardShouldFail) throw new Error('Clipboard permission denied');
      window.copiedText = value;
    } } },
    history: { replaceState(_state, _title, url) { cleanedURL = url; } },
    innerWidth: 1280,
    addEventListener() {},
    setTimeout(callback) { callback(); },
    setInterval(callback) { intervalCallbacks.push(callback); return intervalCallbacks.length; },
    locationAssign: '',
    KWMashaFeedlyTranslate: translate,
    KWMashaFeedlyTranslations: {},
    KWMashaFeedlyEffects: sharedEffects,
    KWMashaFeedlyEntries: entriesUI,
    confirm: () => true,
  };
  window.location.assign = (url) => { window.locationAssign = url; };
  let pageEntryCount = 1;
  let pageOpenEntryCount = 1;
  let openEntryCount = 2;
  let totalEntriesCount = 2;
  let feedbackEntryCount = 1;
  let entryIsClosed = false;
  let entryIsUnread = false;
  let entryStatusOverride = null;
  const responseFor = (mode) => ({
    success: true,
    mode,
    pageCount: pageEntryCount,
    pageOpenCount: pageOpenEntryCount,
    openCount: openEntryCount,
    totalCount: totalEntriesCount,
    feedbackCount: feedbackEntryCount,
    unreadCount: entryIsUnread ? 1 : 0,
    unreadCommentCount: entryIsUnread ? 2 : 0,
    mineCount: 1,
    categories: [{ id: 1, title: 'Backlog', isClosed: false }, { id: 4, title: 'Done', isClosed: true }, { id: 5, title: 'Archiv', isClosed: true }, { id: 6, title: 'Feedback', isClosed: false }, { id: 8, title: 'In Bearbeitung', systemKey: 'restricted_estimate', isClosed: false }],
    priorities: [{ id: 1, title: 'Sofort bearbeiten', iconType: 'warning' }, { id: 2, title: 'Zeitnah bearbeiten', iconType: 'warning' }, { id: 3, title: 'Normal', iconType: 'warning' }, { id: 4, title: 'Bei Gelegenheit', iconType: 'warning' }, { id: 5, title: 'Info', iconType: 'info' }],
    entries: (totalEntriesCount === 0 || (mode === 'unread' && !entryIsUnread)) ? [] : [{
      id: 71,
      title: entryTitleValue,
      content: entryContent,
      attachments: entryAttachments,
      relations: entryRelations,
      entryDate: '2026-10-01 10:00:00',
      dueDate: entryDueDate,
      pageURL: entryPageURL,
      selector: '#about-title',
      elementText: 'Über uns',
      loggedAt: '2026-10-01 12:00:00',
      operatingSystem: 'Mac OS 10.15.7',
      browser: 'Chrome 152.0.0.0',
      userAgent: 'Mozilla/5.0 Chrome/152.0.0.0',
      resolution: '2560 × 1440 px',
      browserWindow: '1943 × 1294 px',
      colorDepth: 24,
      categoryID: entryStatusOverride?.id ?? (mode === 'feedback' ? 6 : (entryIsClosed ? 4 : 1)),
      categoryTitle: entryStatusOverride?.title ?? (mode === 'feedback' ? 'Feedback' : (entryIsClosed ? 'Done' : 'Backlog')),
      categoryRole: 'backlog',
      priorityID: 3,
      priorityTitle: priorityIconType === 'info' ? 'Info' : 'Normal',
      priorityColor: priorityIconType === 'info' ? '#4285c7' : '#d7a916',
      priorityIconType,
      isClosed: entryStatusOverride?.isClosed ?? entryIsClosed,
      isUnread: entryIsUnread,
      assignees: entryAssigneeList,
      assignedMemberIDs: entryAssigneeList.map((member) => member.id),
      history: [{ type: 'created', oldValue: '', newValue: entryTitleValue, actor: 'Erika Muster', created: '2026-10-01T10:00:00Z' }],
      comments: [
        { id: 1, author: 'Erika Muster', text: 'Erster Kommentar https://example.test/kommentar', created: '2026-10-01 10:30:00', canManage: true, edited: true },
        { id: 2, author: 'Max Beispiel', text: '<script>zweiter</script><img src=x onerror=alert(2)> <svg onload=alert(3)> javascript:alert(4)', created: '2026-10-01 10:35:00', canManage: true },
      ],
    }],
  });
  const context = {
    window,
    document,
    fetch: async (url, options = {}) => {
      const parsedURL = new URL(url, window.location.href);
      if (options.method === 'POST') {
        postCalls.push({ url: parsedURL, options });
        return postResponses.shift() || { ok: true, json: async () => ({ success: true, message: 'Änderungen wurden gespeichert.' }) };
      }
      if (parsedURL.pathname === '/__masha-feedly') {
        return { ok: true, json: async () => ({ success: true, views: savedViewsData }) };
      }
      const mode = parsedURL.searchParams.get('mode');
      requests.push(parsedURL);
      return { ok: true, json: async () => responseFor(mode) };
    },
    Option: class { constructor(text, value) { this.text = text; this.value = value; } },
    URL,
    CustomEvent: class { constructor(type) { this.type = type; } },
    FormData: class {
      constructor(form) {
        this.form = form;
        this.values = !form ? {} : form === commentForm ? {
          EntryID: form.elements.EntryID.value,
          CommentText: form.elements.CommentText.value,
        } : form?.dataset?.commentEditForm ? Object.fromEntries(
          form.children.filter((control) => control.name).map((control) => [control.name, control.value])
        ) : {
          EntryID: form.elements.EntryID.value,
          CategoryID: form.elements.CategoryID.value,
          PriorityID: form.elements.PriorityID.value,
          RelationType: relationTypeSelect.value,
          RelatedEntryIDs: relatedEntrySelect.options.filter((option) => option.selected).map((option) => option.value),
          AssignedMemberIDs: form.assigneeFields.filter((field) => field.checked).map((field) => field.value),
          Attachments: form.attachmentFields.flatMap((field) => field.files || []),
        };
      }
      set(name, value) { this.values[name] = value; }
    },
    console,
  };
  vm.runInNewContext(source, context);
  listeners.DOMContentLoaded();
  return { listeners, requests, postResponses, postCalls, intervalCallbacks, dispatchedEvents, widget, pageCount, totalCount, closedCountDisplay, feedbackButton, feedbackCountDisplay, unreadCountDisplay, newsSummary, openListButton, openClosedButton, openPageListButton, openNewsButton, rainbow, rainbowCopy, rainbowTitle, rainbowMessage, helpModal, openHelpButton, closeHelpButton, listModal, modeField, categoryFilter, priorityFilter, savedViewSelect, savedViewName, saveViewButton, deleteViewButton, savedViewStatus, listCount, listContainer, document, pageTarget, editModal, editForm, editAttachmentInput, editAttachments, editStatus, editHeading, editPriorityIcon, shareActiveEntryButton, editHistory, editContext, editRelations, relationTypeSelect, relatedEntrySelect, editDescription, editEnvironment, editEnvironmentDetails, editAssignees, commentForm, commentList, commentCount, commentStatus, panel, toggle, window, setSavedViews(views) { savedViewsData = views; }, setAddress(value) { widget.dataset.address = value; }, setEntryPageURL(url) { entryPageURL = url; }, setEntryTitle(value) { entryTitleValue = value; }, setEntryAssignees(value) { entryAssigneeList = value; }, setEntryContent(value) { entryContent = value; }, setEntryAttachments(value) { entryAttachments = value; }, setEntryRelations(value) { entryRelations = value; }, setPageEntryCount(count) { pageEntryCount = count; }, setPageOpenEntryCount(count) { pageOpenEntryCount = count; }, setOpenEntryCount(count) { openEntryCount = count; }, setTotalEntriesCount(count) { totalEntriesCount = count; }, setFeedbackEntryCount(count) { feedbackEntryCount = count; }, setEntryClosed(value) { entryIsClosed = value; }, setEntryUnread(value) { entryIsUnread = value; }, setEntryStatus(value) { entryStatusOverride = value; }, get cleanedURL() { return cleanedURL; } };
}

test('beschriftet die beiden Fehlerzähler verständlich und öffnet die Feedback-Warteschlange', async () => {
  assert.ok(widgetTemplate.indexOf('data-masha-feedly-open-feedback') < widgetTemplate.indexOf('data-masha-feedly-open-closed'), 'Der Button für abgeschlossene Einträge steht nach dem Feedback-Button.');
  assert.ok(widgetTemplate.indexOf('data-masha-feedly-open-closed') < widgetTemplate.indexOf('</div>', widgetTemplate.indexOf('data-masha-feedly-open-closed')), 'Der Button bleibt in der Aktionsgruppe des ersten Panels.');
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(env.categoryFilter.options.some((option) => option.text === 'In Bearbeitung'), false, 'restricted estimate states do not become filter choices');
  assert.equal(env.openListButton.dataset.label, 'Gesamte Website');
  assert.equal(env.openPageListButton.dataset.label, 'Aktuelle Seite');
  assert.equal(env.feedbackButton.hidden, false);
  assert.equal(env.feedbackCountDisplay.textContent, '1');
  assert.equal(env.feedbackButton.attributes['data-tooltip'], 'Einträge anzeigen, bei denen Feedback aussteht · 1');
  assert.equal(env.openClosedButton.attributes['data-tooltip'], 'Abgeschlossene Einträge ansehen · 0');
  assert.equal(env.feedbackButton.attributes.title, undefined);
  assert.equal(env.openClosedButton.attributes.title, undefined);

  await env.feedbackButton.listeners.click();
  assert.equal(env.requests.at(-1).searchParams.get('mode'), 'feedback');
  assert.equal(env.modeField.value, 'feedback');
  assert.equal(env.listContainer.children[0].textContent, 'Feedback');
  assert.match(env.listCount.textContent, /warten auf Feedback/);

  env.setFeedbackEntryCount(0);
  await env.listeners['kw-masha-feedly:refresh']();
  assert.equal(env.feedbackButton.hidden, true);
  assert.equal(env.feedbackCountDisplay.textContent, '0');
});

test('zeigt Neuigkeiten im ersten Panel und öffnet die Liste ungelesener Einträge und Kommentare', async () => {
  const env = createWidgetEnvironment();
  env.setEntryUnread(true);
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(env.unreadCountDisplay.textContent, '1');
  assert.equal(env.openNewsButton.attributes['data-has-news'], 'true');
  assert.equal(env.newsSummary.textContent, 'Einträge: 1 · Kommentare: 2');
  assert.equal(env.openNewsButton.attributes['aria-label'], 'Neuigkeiten · Einträge: 1 · Kommentare: 2');
  assert.equal(env.openNewsButton.attributes['data-tooltip'], 'Neuigkeiten · Einträge: 1 · Kommentare: 2');

  await env.openNewsButton.listeners.click();
  assert.equal(env.requests.at(-1).searchParams.get('mode'), 'unread');
  assert.equal(env.modeField.value, 'unread');
  assert.equal(env.listContainer.children.some((child) => child.className === 'kw-masha-feedly__entry-card'), true);
  assert.match(env.listCount.textContent, /mit neuen Aktivitäten/);
  const unreadBadge = env.listContainer.children.find((card) => card.className === 'kw-masha-feedly__entry-card')?.querySelector('[data-entry-unread]');
  assert.ok(unreadBadge);
  assert.equal(unreadBadge.children[0].tagName, 'svg');
  assert.equal(unreadBadge.attributes['aria-label'], 'Neue Aktivität');

  const scss = require('node:fs').readFileSync(require('node:path').join(__dirname, '../../client/src/scss/masha-feedly.scss'), 'utf8');
  assert.match(scss, /\.kw-masha-feedly__news-button\s*\{/);
  assert.match(scss, /\.kw-masha-feedly__news-copy\s*\{/);
  assert.match(scss, /\.kw-masha-feedly__news-title\s*\{[^}]*font-size:\s*calc\(16\.8px \* var\(--masha-font-scale, 1\)\)/);
  assert.match(scss, /\.kw-masha-feedly__news-copy small\s*\{[^}]*font-size:\s*calc\(12\.8px \* var\(--masha-font-scale, 1\)\)/);
  assert.match(scss, /\.kw-masha-feedly__news-button:focus-visible\s*\{/);
  assert.match(widgetTemplate, /viewBox="0 0 177800 177800"/);
  assert.match(widgetTemplate, /data-masha-feedly-open-news[^>]*data-tooltip=/);
  assert.match(widgetTemplate, /data-masha-feedly-open-feedback[^>]*data-tooltip=/);
  const actionButtons = ['news', 'feedback', 'closed'].map((name) => widgetTemplate.match(new RegExp(`<button\\b(?=[^>]*data-masha-feedly-open-${name})[\\s\\S]*?<\\/button>`))?.[0] || '');
  assert.ok(actionButtons.every((button) => button.includes('data-tooltip=')));
  assert.ok(actionButtons.every((button) => !/\stitle=/.test(button)));
  assert.ok(actionButtons.every((button) => button.includes('aria-label=')));
  assert.match(scss, /actions > \.kw-masha-feedly__news-button strong,[\s\S]*?position: absolute; top: -5\.6px;/);
  assert.match(scss, /Die Zähler bekommen eigene, großzügige Zeilen statt enger Mini-Kacheln/);
  assert.match(scss, /\.kw-masha-feedly__actions \{ grid-template-columns: minmax\(0, 1fr\); gap: 11\.2px; \}/);
  assert.match(scss, /actions > \.kw-masha-feedly__news-button,[\s\S]*?grid-template-columns: minmax\(0, 1fr\) auto;[^}]*min-height: 64px; height: 64px;/);
  assert.match(scss, /Größere Statussymbole mit genug Raum rundherum/);
  assert.match(scss, /\.kw-masha-feedly__news-icon svg \{ width: 74\.4px; height: 74\.4px; \}/);
  assert.match(scss, /actions > \.kw-masha-feedly__feedback-button svg,[\s\S]*?width: 64px; height: 64px;/);
  assert.match(scss, /Feines Hover-Feedback; Hinweise zeigt das zentrale Tooltip außerhalb des Buttons/);
  assert.match(scss, /\.kw-masha-feedly__hover-tooltip\s*\{/);
  assert.doesNotMatch(scss, /\.kw-masha-feedly__actions > \.kw-masha-feedly__(?:news|feedback|closed)-button::(?:before|after)/);
  assert.match(scss, /\.kw-masha-feedly__sr-only \{ position: absolute !important;/);
  const compiledStyles = require('node:fs').readFileSync(require('node:path').join(__dirname, '../../client/dist/css/masha-feedly.css'), 'utf8');
  assert.match(compiledStyles, /\.kw-masha-feedly__news-button:focus-visible\{outline:3px solid/);
});

test('hält Filter eingeklappt, zeigt aktive Filter als Chips und erlaubt Entfernen sowie Zurücksetzen', async () => {
  assert.match(widgetTemplate, /<details class="kw-masha-feedly__filters-details" data-masha-feedly-filters-details>/);
  assert.match(widgetTemplate, /filter-summary-icon[\s\S]*?FILTERS_TITLE/);
  assert.match(widgetTemplate, /filter-label[\s\S]*?FILTER_MODE/);
  assert.match(widgetTemplate, /filter-label[\s\S]*?FILTER_CATEGORY/);
  assert.match(widgetTemplate, /data-masha-feedly-priority-filter-icon[\s\S]*?FILTER_PRIORITY/);
  assert.match(germanTranslations, /FILTER_MODE: 'Ansicht'/);
  assert.match(scss, /__filter-label \{[^}]*white-space: nowrap;[^}]*text-overflow: ellipsis;/);
  assert.match(scss, /filters-details \.kw-masha-feedly__entries-toolbar label \{[^}]*border-radius: 12px;[^}]*background: linear-gradient/);
  assert.match(scss, /filters-details \.kw-masha-feedly__entries-toolbar select \{[^}]*width: 100%;[^}]*text-overflow: ellipsis;/);
  const env = createWidgetEnvironment();
  const details = env.widget.querySelector('[data-masha-feedly-filters-details]');
  const count = env.widget.querySelector('[data-masha-feedly-filter-count]');
  const state = env.widget.querySelector('[data-masha-feedly-filter-state]');
  const chips = env.widget.querySelector('[data-masha-feedly-active-filters]');
  assert.equal(details.open, false);
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(count.textContent, '0');
  assert.equal(state.textContent, 'Keine aktiv');
  assert.equal(chips.hidden, true);

  details.open = true;
  env.modeField.value = 'open';
  await env.modeField.listeners.change();
  assert.equal(details.open, true, 'Änderungen lassen den Filterbereich geöffnet.');
  env.categoryFilter.value = '6';
  env.categoryFilter.listeners.change();
  env.priorityFilter.value = '1';
  env.priorityFilter.listeners.change();
  assert.equal(count.textContent, '3');
  assert.equal(chips.hidden, false);
  assert.deepEqual(chips.children.slice(0, 3).map((chip) => chip.dataset.removeFilter), ['mode', 'category', 'priority']);
  assert.match(chips.children[1].textContent, /Feedback/);
  assert.match(env.widget.querySelector('[data-masha-feedly-priority-filter-icon]').innerHTML, /<svg[\s\S]*<path/);

  await chips.children[1].listeners.click();
  assert.equal(env.categoryFilter.value, '');
  assert.equal(count.textContent, '2');
  const clear = chips.children.find((chip) => chip.dataset.clearFilters === 'true');
  await clear.listeners.click();
  assert.equal(env.modeField.value, 'page');
  assert.equal(env.categoryFilter.value, '');
  assert.equal(env.priorityFilter.value, '');
  assert.equal(count.textContent, '0');
  assert.equal(chips.hidden, true);
});

test('lädt eine persönliche Filterkombination und wendet Liste, Kategorie und Priorität gemeinsam an', async () => {
  const env = createWidgetEnvironment();
  env.setSavedViews([{ id: 'mine-feedback', title: 'Mein Feedback', mode: 'mine', categoryID: 6, priorityID: 1 }]);
  await env.openListButton.listeners.click();
  assert.equal(env.savedViewSelect.options[1].text, 'Mein Feedback');
  env.savedViewSelect.value = 'mine-feedback';
  await env.savedViewSelect.listeners.change();
  assert.equal(env.modeField.value, 'mine');
  assert.equal(env.categoryFilter.value, '6');
  assert.equal(env.priorityFilter.value, '1');
  assert.equal(env.requests.at(-1).searchParams.get('mode'), 'mine');
});

test('speichert und löscht eine benannte persönliche Filterkombination über geschützte POST-Endpunkte', async () => {
  const env = createWidgetEnvironment();
  await env.openListButton.listeners.click();
  env.modeField.value = 'open';
  env.categoryFilter.value = '1';
  env.priorityFilter.value = '2';
  env.savedViewName.value = 'Meine offenen dringenden Fehler';
  const saved = { id: 'view-1', title: env.savedViewName.value, mode: 'open', categoryID: 1, priorityID: 2 };
  env.postResponses.push({ ok: true, json: async () => ({ success: true, view: saved, views: [saved] }) });
  await env.saveViewButton.listeners.click();
  assert.equal(env.postCalls[0].url.pathname, '/__masha-feedly');
  assert.equal(env.postCalls[0].options.body.values.ViewAction, 'save');
  assert.equal(env.postCalls[0].options.body.values.SecurityID, 'test-token');
  assert.equal(env.postCalls[0].options.body.values.Title, saved.title);
  assert.equal(env.postCalls[0].options.body.values.Mode, 'open');
  assert.equal(env.postCalls[0].options.body.values.PriorityID, '2');
  assert.equal(env.savedViewSelect.value, 'view-1');
  assert.equal(env.savedViewStatus.textContent, 'Ansicht gespeichert.');

  env.postResponses.push({ ok: true, json: async () => ({ success: true, views: [] }) });
  await env.deleteViewButton.listeners.click();
  assert.equal(env.postCalls[1].url.pathname, '/__masha-feedly');
  assert.equal(env.postCalls[1].options.body.values.ViewAction, 'delete');
  assert.equal(env.postCalls[1].options.body.values.ViewID, 'view-1');
  assert.equal(env.savedViewSelect.value, '');
  assert.equal(env.savedViewStatus.textContent, 'Ansicht gelöscht.');
});

test('zeigt Kommentare als abwechselnde sichere Sprechblasen und sendet neue Kommentare', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  assert.equal(env.commentForm.elements.EntryID.value, '71');
  assert.deepEqual(env.commentList.children.map((item) => item.className), [
    'kw-masha-feedly__comment is-left', 'kw-masha-feedly__comment is-right',
  ]);
  assert.equal(env.commentList.children[1].children[1].textContent, '<script>zweiter</script><img src=x onerror=alert(2)> <svg onload=alert(3)> javascript:alert(4)');
  assert.equal(env.commentCount.textContent, '2');
  assert.equal(env.commentList.children[0].children[1].children.find((node) => node.tagName === 'a').href, 'https://example.test/kommentar');
  const firstActions = env.commentList.children[0].children.find((child) => child.className === 'kw-masha-feedly__comment-actions');
  assert.equal(env.commentList.children[0].children[3].children.length, 2, 'Die Reaktionsauswahl bleibt zunächst eingeklappt.');
  assert.equal(env.commentList.children[0].children[3].children[1].hidden, true);
  assert.deepEqual(firstActions.children.map((button) => button.textContent), ['Bearbeiten', 'Löschen']);

  firstActions.children[0].listeners.click();
  const editor = env.commentList.children[0].children[1];
  assert.deepEqual(editor.children[3].children.map((button) => button.textContent), ['Abbrechen', 'Änderungen speichern']);
  editor.children[2].value = 'Kommentar aktualisiert';
  env.postResponses.push({ ok: true, json: async () => ({ success: true, comment: { id: 1, author: 'Erika Muster', text: 'Kommentar aktualisiert', created: '2026-10-01 10:30:00', canManage: true, edited: true }, history: [{ type: 'comment_edited', oldValue: 'Erster Kommentar', newValue: 'Kommentar aktualisiert', actor: 'Erika Muster', created: '2026-10-01T10:30:00Z' }] }) });
  await editor.listeners.submit({ preventDefault() {} });
  assert.equal(env.postCalls[0].url.pathname, '/__masha-feedly-comment');
  assert.equal(env.postCalls[0].options.body.values.CommentAction, 'edit');
  assert.equal(env.postCalls[0].options.body.values.EntryID, '71');
  assert.equal(env.postCalls[0].options.body.values.CommentID, '1');
  assert.equal(env.postCalls[0].options.body.values.CommentText, 'Kommentar aktualisiert');
  assert.equal(env.commentList.children[0].children[1].textContent, 'Kommentar aktualisiert');
  assert.match(env.editHistory.children[0].children[0].textContent, /Kommentar bearbeitet: Erster Kommentar → Kommentar aktualisiert/);
  assert.equal(env.commentList.children[0].children[2].children[0].className, 'kw-masha-feedly__comment-edited');
  assert.equal(env.commentList.children[0].children[2].children[0].textContent, 'bearbeitet');

  env.commentForm.elements.CommentText.value = 'Noch ein Hinweis';
  env.postResponses.push({ ok: true, json: async () => ({ success: true, comment: { id: 3, author: 'Erika Muster', text: 'Noch ein Hinweis', created: '2026-10-01 10:40:00' }, history: [{ type: 'comment', oldValue: '', newValue: 'Noch ein Hinweis', actor: 'Erika Muster', created: '2026-10-01T10:40:00Z' }] }) });
  await env.commentForm.listeners.submit({ preventDefault() {} });
  assert.equal(env.postCalls[1].url.pathname, '/__masha-feedly-comment');
  assert.equal(env.postCalls[1].options.body.values.SecurityID, 'test-token');
  assert.equal(env.postCalls[1].options.body.values.EntryID, '71');
  assert.equal(env.postCalls[1].options.body.values.CommentText, 'Noch ein Hinweis');
  assert.equal(env.commentCount.textContent, '3');
  assert.equal(env.commentList.children[2].className, 'kw-masha-feedly__comment is-left');
  assert.equal(env.commentStatus.textContent, 'Kommentar gesendet.');
  assert.ok(env.dispatchedEvents.includes('kw-masha-feedly:onboarding-comment-saved'));
  assert.match(env.editHistory.children[0].children[0].textContent, /Kommentar: Noch ein Hinweis/);

  env.postResponses.push({ ok: true, json: async () => ({ success: true, commentID: 2, history: [{ type: 'comment_deleted', oldValue: '<script>zweiter</script>', newValue: '', actor: 'Erika Muster', created: '2026-10-01T10:40:00Z' }] }) });
  const secondActions = env.commentList.children[1].children.find((child) => child.className === 'kw-masha-feedly__comment-actions');
  await secondActions.children[1].listeners.click();
  assert.equal(env.postCalls[2].options.body.values.CommentAction, 'delete');
  assert.equal(env.postCalls[2].options.body.values.CommentID, '2');
  assert.equal(env.commentCount.textContent, '2');
  assert.match(env.editHistory.children[0].children[0].textContent, /Kommentar gelöscht: <script>zweiter<\/script>/);
});

test('sendet eine Kommentarreaktion an den geschützten Kommentar-Endpunkt und aktualisiert die Anzeige', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  const comment = env.commentList.children[0];
  const reactions = comment.children[3];
  const reactionToggle = reactions.children[0].children.at(-1);
  reactionToggle.listeners.click();
  const reactionButton = reactions.children[1].children[0];
  env.postResponses.push({ ok: true, json: async () => ({
    success: true, commentID: 1,
    reactions: [
      { emoji: '👍', count: 1, selected: true },
      { emoji: '❤️', count: 0, selected: false },
      { emoji: '😂', count: 0, selected: false },
      { emoji: '😢', count: 0, selected: false },
      { emoji: '😮', count: 0, selected: false },
    { emoji: '🙏', count: 0, selected: false },
  ],
    history: [{ type: 'comment_reaction', oldValue: '', newValue: '👍', actor: 'Testmitglied', created: '2026-10-04T10:00:00Z' }],
  }) });
  await reactionButton.listeners.click();
  assert.equal(env.postCalls[0].url.pathname, '/__masha-feedly-comment');
  assert.equal(env.postCalls[0].options.body.values.SecurityID, 'test-token');
  assert.equal(env.postCalls[0].options.body.values.EntryID, '71');
  assert.equal(env.postCalls[0].options.body.values.CommentID, '1');
  assert.equal(env.postCalls[0].options.body.values.CommentAction, 'react');
  assert.equal(env.postCalls[0].options.body.values.ReactionEmoji, '👍');
  assert.equal(env.commentList.children[0].children[3].children[0].children[0].attributes['aria-pressed'], 'true');
  assert.equal(env.commentList.children[0].children[3].children[0].children[0].children[1].textContent, '1');
  assert.equal(env.commentList.children[0].children[3].children[1].hidden, true, 'Nach dem Auswählen klappt die Emoji-Auswahl wieder zu.');
  assert.equal(env.commentStatus.textContent, 'Reaktion gespeichert.');
  assert.equal(env.editHistory.children[0].children[0].textContent, 'Reaktion auf Kommentar geändert: Keine Reaktion → 👍');
});

test('markiert neue Kommentaraktivität sichtbar in der rechten Liste und beim Öffnen als gelesen', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  assert.equal(card.children.some((child) => child.dataset.entryUnread === 'true'), false);
  env.setEntryUnread(true);
  await env.intervalCallbacks[0]();
  await new Promise((resolve) => setImmediate(resolve));
  const badge = card.querySelector('[data-entry-unread]');
  assert.equal(badge.attributes['aria-label'], 'Neue Aktivität');
  assert.equal(badge.children[0].tagName, 'svg');
  assert.equal(card.dataset.entryUnread, 'true');

  await env.listContainer.listeners.click({ target: card, preventDefault() {} });
  await new Promise((resolve) => setImmediate(resolve));
  const readCall = env.postCalls.find((call) => call.url.pathname === '/__masha-feedly/markEntryRead');
  assert.ok(readCall);
  assert.equal(readCall.options.body.values.EntryID, '71');
  assert.equal(readCall.options.body.values.SecurityID, 'test-token');
  assert.equal(badge.removed, true);
  assert.equal(card.dataset.entryUnread, undefined);
});

test('erklärt eine HTML-Fehlerantwort des Kommentar-Endpunkts lesbar', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.commentForm.elements.CommentText.value = 'Test';
  env.postResponses.push({ ok: false, json: async () => { throw new SyntaxError(`Unexpected token 'A', "Action 'commentEntry' isn't allowed" is not valid JSON`); } });
  await env.commentForm.listeners.submit({ preventDefault() {} });
  assert.match(env.commentStatus.textContent, /keine gültige JSON-Antwort/);
  assert.equal(env.commentForm.submitButton.disabled, false);
});

test('übernimmt bei einer 403-Antwort weder Kommentar noch Verlauf', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  const previousCommentCount = env.commentCount.textContent;
  const previousHistoryCount = env.editHistory.children.length;
  env.commentForm.elements.CommentText.value = 'Unberechtigter Kommentar';
  env.postResponses.push({ ok: false, json: async () => ({ success: false, message: 'Keine Berechtigung.' }) });
  await env.commentForm.listeners.submit({ preventDefault() {} });
  assert.equal(env.commentStatus.textContent, 'Keine Berechtigung.');
  assert.equal(env.commentCount.textContent, previousCommentCount);
  assert.equal(env.editHistory.children.length, previousHistoryCount);
  assert.equal(env.commentForm.submitButton.disabled, false);
});

test('öffnet und schließt die kontextuelle Hilfe', async () => {
  const env = createWidgetEnvironment();
  env.listeners.DOMContentLoaded();
  env.openHelpButton.listeners.click();
  assert.equal(env.helpModal.hidden, false);
  env.closeHelpButton.listeners.click();
  assert.equal(env.helpModal.hidden, true);
});

test('öffnet den Seitenzähler direkt in der gefilterten Seitenübersicht', async () => {
  const env = createWidgetEnvironment();
  await env.openPageListButton.listeners.click();
  assert.equal(env.requests[0].searchParams.get('mode'), 'page-open');
  assert.equal(env.listModal.hidden, false);
});

test('zeigt beim Seitenzähler keine bereits erledigten Einträge', async () => {
  const env = createWidgetEnvironment();
  env.setEntryClosed(true);
  await env.openPageListButton.listeners.click();
  assert.equal(env.requests[0].searchParams.get('mode'), 'page-open');
  assert.equal(env.listContainer.children[0].className, 'kw-masha-feedly__entries-empty');
});

test('zeigt den Seitenerfolg nur ohne offene Fehler und unterscheidet den globalen Erfolg', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(env.rainbow.hidden, true);
  env.setPageOpenEntryCount(0);
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(env.rainbow.hidden, false);
  assert.equal(env.rainbowTitle.textContent, 'Auf dieser Seite keine Fehler');
  assert.match(env.rainbow.attributes['aria-label'], /Auf dieser Seite keine Fehler/);
  env.setOpenEntryCount(0);
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(env.rainbowTitle.textContent, 'Alles im grünen Bereich!');
  assert.match(env.rainbow.attributes['aria-label'], /Alles im grünen Bereich!/);
  env.setPageEntryCount(0);
  env.setOpenEntryCount(0);
  env.setTotalEntriesCount(0);
  await env.listeners['kw-masha-feedly:opened']();
  assert.match(env.rainbowTitle.textContent, /noch nichts eingetragen/);
  assert.match(env.rainbowMessage.textContent, /vorsichtshalber trotzdem/);
});

test('lädt Einträge für die aktuelle URL und setzt eine Blase am gespeicherten About-us-Bereich', async () => {
  const env = createWidgetEnvironment();
  env.document.querySelector = (selector) => selector === '#about-title' ? env.pageTarget : null;
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(env.requests[0].pathname, '/__masha-feedly/listEntries');
  assert.equal(env.requests[0].searchParams.get('mode'), 'page');
  assert.equal(env.requests[0].searchParams.get('PageURL'), 'https://feedly:8890/about-us?preview=1');
  assert.equal(env.pageCount.textContent, '1');
  assert.equal(env.totalCount.textContent, '2');
  assert.equal(env.document.body.children.length, 1);
  assert.equal(env.document.body.children[0].dataset.entryId, '71');
  assert.equal(env.document.body.children[0].title, 'Backlog · Fehler auf About us');
  assert.equal(env.listContainer.children[0].textContent, 'Backlog');
});

test('öffnet den dritten Bereich direkt hinter dem ersten, wenn eine Seitenblase angeklickt wird', async () => {
  const env = createWidgetEnvironment();
  env.document.querySelector = (selector) => selector === '#about-title' ? env.pageTarget : null;
  await env.listeners['kw-masha-feedly:opened']();
  const marker = env.document.body.children[0];
  marker.listeners.click({ preventDefault() {}, stopPropagation() {} });
  assert.equal(env.listModal.hidden, true);
  assert.equal(env.editModal.hidden, false);
  assert.equal(env.widget.attributes['data-edit-open'], 'true');
  assert.notEqual(env.widget.attributes['data-list-open'], 'true');
});

test('zeigt abgeschlossene Kategorie-Einträge in der Liste, aber nicht als Fehlerblase auf der Seite', async () => {
  const env = createWidgetEnvironment();
  env.setEntryClosed(true);
  env.setPageOpenEntryCount(0);
  env.setOpenEntryCount(0);
  env.document.querySelector = (selector) => selector === '#about-title' ? env.pageTarget : null;
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(env.rainbow.hidden, false);
  assert.equal(env.document.body.children.length, 0);
  assert.equal(env.pageCount.textContent, '0');
  assert.equal(env.closedCountDisplay.textContent, '2');
  assert.equal(env.listContainer.children[0].textContent, 'Done');
});

test('zeigt offene und abgeschlossene Einträge in der Übersicht getrennt an', async () => {
  const openEnv = createWidgetEnvironment();
  await openEnv.listeners['kw-masha-feedly:opened']();
  await openEnv.openListButton.listeners.click();
  assert.equal(openEnv.requests[1].searchParams.get('mode'), 'open');
  assert.equal(openEnv.listCount.textContent, '1 Eintrag offen');

  const closedEnv = createWidgetEnvironment();
  closedEnv.setEntryClosed(true);
  closedEnv.setOpenEntryCount(2);
  closedEnv.setTotalEntriesCount(5);
  closedEnv.setPageOpenEntryCount(0);
  await closedEnv.listeners['kw-masha-feedly:opened']();
  assert.equal(closedEnv.pageCount.textContent, '0');
  assert.equal(closedEnv.totalCount.textContent, '2');
  assert.equal(closedEnv.closedCountDisplay.textContent, '3');
  await closedEnv.openListButton.listeners.click();
  assert.equal(closedEnv.requests[1].searchParams.get('mode'), 'open');
  assert.equal(closedEnv.listCount.textContent, '0 Einträge offen');
  await closedEnv.openClosedButton.listeners.click();
  assert.equal(closedEnv.requests[2].searchParams.get('mode'), 'closed');
  assert.equal(closedEnv.listCount.textContent, '1 Eintrag abgeschlossen');
  assert.ok(closedEnv.listContainer.children.some((child) => child.className === 'kw-masha-feedly__entry-card'));
});

test('lädt persönliche Zuweisungen im eigenen Listenfilter nach', async () => {
  const env = createWidgetEnvironment();
  env.document.querySelector = () => env.pageTarget;
  await env.listeners['kw-masha-feedly:opened']();
  env.modeField.value = 'mine';
  await env.modeField.listeners.change();
  assert.equal(env.requests[1].searchParams.get('mode'), 'mine');
  assert.match(env.listCount.textContent, /für dich/);
});

test('verwendet die konfigurierte Sie-Anrede in persönlichen Listenmeldungen', async () => {
  const env = createWidgetEnvironment('https://feedly:8890/about-us?preview=1', 'sie');
  await env.listeners['kw-masha-feedly:opened']();
  env.modeField.value = 'mine';
  await env.modeField.listeners.change();
  assert.match(env.listCount.textContent, /für Sie/);
});

test('öffnet den Eintrag aus der Kartenliste und springt zum gespeicherten Seitenbereich', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  assert.equal(env.requests[1].searchParams.get('mode'), 'open');
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  assert.equal(card.children[0].children[0].textContent, '#71');
  assert.equal(card.children[0].children[1].textContent, 'Fehler auf About us');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  assert.equal(env.listModal.hidden, false);
  assert.equal(env.widget.attributes['data-list-open'], 'true');
  assert.equal(env.editModal.hidden, false);
  assert.equal(env.widget.attributes['data-edit-open'], 'true');
  assert.equal(env.editForm.elements.EntryID.value, '71');
  assert.equal(env.editPriorityIcon.hidden, false);
  assert.equal(env.editPriorityIcon.dataset.iconType, 'warning');
  assert.equal(env.editPriorityIcon.attributes['aria-label'], 'Normal');
  assert.match(env.editPriorityIcon.innerHTML, /viewBox="0 0 24 24"/);
  assert.equal(env.editPriorityIcon.style['--masha-feedly-priority-color'], '#d7a916');
  assert.equal(env.editForm.elements.CategoryID.value, '1');
  assert.equal(env.editForm.elements.PriorityID.value, '3');
  const cardPriority = card.children.find((child) => child.className === 'kw-masha-feedly__entry-metadata').children[1].children[0];
  assert.equal(cardPriority.textContent, '');
  assert.equal(cardPriority.attributes['aria-label'], 'Normal');
  assert.equal(cardPriority.dataset.iconType, 'warning');
  assert.match(cardPriority.innerHTML, /viewBox="0 0 24 24"/);
  assert.equal(env.editForm.assigneeFields[0].checked, true);
  assert.equal(env.editHeading.textContent, 'Eintrag #71');
  assert.equal(env.editContext.textContent, 'Status: Backlog');
  assert.equal(env.editDescription.textContent, 'Der Inhalt ist verschoben.');
  assert.ok(env.editEnvironment.children.some((field) => field.textContent === 'Mac OS 10.15.7'));
  assert.ok(env.editEnvironment.children.some((field) => field.textContent === '2560 × 1440 px'));
  assert.equal(env.editEnvironmentDetails.hidden, false);
  assert.equal(env.pageTarget.scrolled, true);
});

test('zeigt Info-Prioritäten mit dem i-Symbol und beschriftet es barrierefrei', async () => {
  const env = createWidgetEnvironment(undefined, undefined, 'info');
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  const icon = card.children.find((child) => child.className === 'kw-masha-feedly__entry-metadata').children[1].children[0];
  assert.equal(icon.dataset.iconType, 'info');
  assert.equal(icon.attributes['aria-label'], 'Info');
  assert.match(icon.innerHTML, /viewBox="0 0 512 512"/);
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  assert.equal(env.editPriorityIcon.dataset.iconType, 'info');
  assert.match(env.editPriorityIcon.innerHTML, /viewBox="0 0 512 512"/);
});

test('macht Links in Eintragsbeschreibung anklickbar und zeigt Bildanhänge inline', async () => {
  const env = createWidgetEnvironment();
  env.setEntryContent('Mehr dazu https://example.test/hilfe');
  env.setEntryAttachments([
    { name: 'ansicht.png', mimeType: 'image/png', url: '/assets/private/ansicht.png' },
    { name: 'details.pdf', mimeType: 'application/pdf', url: '/assets/private/details.pdf' },
  ]);
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  assert.equal(card.children.some((child) => child.tagName === 'P' || child.tagName === 'A'), false, 'Die Übersichtskarte bleibt kompakt.');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  assert.equal(env.editDescription.children.find((node) => node.tagName === 'a').href, 'https://example.test/hilfe');
  const imageLink = env.editAttachments.children.find((node) => node.href === '/assets/private/ansicht.png');
  const pdfLink = env.editAttachments.children.find((node) => node.href === '/assets/private/details.pdf');
  assert.equal(imageLink.children[0].tagName, 'img');
  assert.equal(imageLink.children[0].alt, 'ansicht.png');
  assert.equal(pdfLink.children.length, 0);
});

test('navigiert beim Klick auf einen anderen Seiteneintrag zur Seite mit Eintragskennung', async () => {
  const env = createWidgetEnvironment();
  env.setEntryPageURL('https://feedly:8890/contact');
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  const destination = new URL(env.window?.locationAssign || 'https://feedly:8890/about-us');
  assert.equal(destination.pathname, '/contact');
  assert.equal(destination.searchParams.get('masha-feedly-entry'), '71');
});

test('zeigt nach erfolgreichem Speichern die Bestätigung und lädt den aktualisierten Eintrag neu', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.editForm.elements.CategoryID.value = '3';
  env.editForm.elements.PriorityID.value = '5';
  env.editForm.assigneeFields[0].checked = false;
  env.editForm.assigneeFields[1].checked = true;
  env.postResponses.push({ ok: true, json: async () => ({ success: true, message: 'Status und Zuständigkeiten wurden gespeichert.', dueDate: '2026-10-12', priorityID: 5, priorityTitle: 'Info', priorityColor: '#4285c7', priorityIconType: 'info' }) });
  await env.editForm.listeners.submit({ preventDefault() {} });
  assert.equal(env.postCalls[0].url.pathname, '/__masha-feedly/updateEntry');
  assert.equal(env.postCalls[0].options.method, 'POST');
  assert.equal(env.postCalls[0].options.body.values.SecurityID, 'test-token');
  assert.equal(env.postCalls[0].options.body.values.EntryID, '71');
  assert.equal(env.postCalls[0].options.body.values.CategoryID, '3');
  assert.equal(env.postCalls[0].options.body.values.PriorityID, '5');
  assert.equal(env.editForm.elements.DueDate.value, '2026-10-12');
  assert.equal(env.editPriorityIcon.dataset.iconType, 'info');
  assert.equal(env.editPriorityIcon.attributes['aria-label'], 'Info');
  assert.match(env.editPriorityIcon.innerHTML, /viewBox="0 0 512 512"/);
  assert.deepEqual(Array.from(env.postCalls[0].options.body.values.AssignedMemberIDs), ['15']);
  assert.equal('Content' in env.postCalls[0].options.body.values, false);
  assert.match(env.editStatus.textContent, /wurden gespeichert/);
  assert.equal(env.editModal.hidden, false);
  assert.equal(env.requests.length, 3);
});

test('zeigt eine gesetzte Fälligkeit auf der Karte und übernimmt sie ins Bearbeitungsformular', async () => {
  const env = createWidgetEnvironment(undefined, undefined, undefined, '2026-10-10');
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  const metadata = card.children.find((child) => child.className === 'kw-masha-feedly__entry-metadata');
  const dueDate = metadata.children.find((child) => child.className === 'kw-masha-feedly__entry-due-date');
  assert.equal(dueDate.dateTime, '2026-10-10');
  assert.equal(dueDate.textContent, 'Fällig am 10.10.2026');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  assert.equal(env.editForm.elements.DueDate.value, '2026-10-10');
});

test('zeigt die bestätigte automatische Duplikat-Schließung nach dem Speichern an', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.editForm.elements.CategoryID.value = '4';
  env.postResponses.push({ ok: true, json: async () => ({
    success: true,
    message: 'Status gespeichert. 2 Duplikate wurden ebenfalls abgeschlossen.',
    categoryID: 4,
    categoryTitle: 'Done',
    categoryIsClosed: true,
    closedDuplicateCount: 2,
    celebrateCompletion: false,
  }) });
  await env.editForm.listeners.submit({ preventDefault() {} });
  assert.equal(env.editStatus.textContent, 'Status gespeichert. 2 Duplikate wurden ebenfalls abgeschlossen.');
  assert.equal(env.requests.at(-1).searchParams.get('mode'), 'page', 'Die Eintragsliste muss nach der serverseitigen Kaskade neu geladen werden.');
});

test('übernimmt den vom Server erzwungenen Feedback-Status statt Done vorzutäuschen', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.editForm.elements.CategoryID.value = '4';
  env.postResponses.push({ ok: true, json: async () => ({
    success: true,
    message: 'Der Eintrag wartet jetzt auf die Freigabe durch die erstellende Person.',
    categoryID: 6,
    categoryTitle: 'Rückmeldung',
    categoryIsClosed: false,
  }) });

  await env.editForm.listeners.submit({ preventDefault() {} });

  assert.equal(env.editForm.elements.CategoryID.value, '6');
  assert.equal(env.editContext.textContent, 'Status: Rückmeldung');
  assert.equal(env.editStatus.textContent, 'Der Eintrag wartet jetzt auf die Freigabe durch die erstellende Person.');
  assert.equal(env.document.body.children.some((child) => child.className === 'kw-masha-feedly__confetti'), false);
  assert.equal(env.document.body.children.some((child) => child.className === 'kw-masha-feedly__unicorn-runner'), false);
});

test('sendet zusätzliche Dateien beim Bearbeiten und zeigt sie nach dem Speichern an', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  const selectedFile = { name: 'plan.pdf' };
  env.editAttachmentInput.files = [selectedFile];
  env.editAttachmentInput.value = 'C:\\fakepath\\plan.pdf';
  env.postResponses.push({
    ok: true,
    json: async () => ({
      success: true,
      message: 'Anhänge wurden gespeichert.',
      attachments: [{ name: 'plan.pdf', mimeType: 'application/pdf', url: '/assets/private/plan.pdf' }],
    }),
  });
  await env.editForm.listeners.submit({ preventDefault() {} });
  assert.deepEqual(Array.from(env.postCalls[0].options.body.values.Attachments), [selectedFile]);
  assert.equal(env.editAttachmentInput.value, '');
  assert.equal(env.editAttachments.children[0].href, '/assets/private/plan.pdf');
  assert.equal(env.editAttachments.children[0].textContent, 'plan.pdf');
});

test('zeigt serverseitig abgelehnte Anhänge beim Bearbeiten an und hält den Dialog offen', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.editAttachmentInput.files = [{ name: 'bild.pdf' }];
  env.postResponses.push({
    ok: false,
    json: async () => ({ success: false, message: 'Der Dateityp passt nicht zur Dateiendung.' }),
  });

  await env.editForm.listeners.submit({ preventDefault() {} });

  assert.equal(env.postCalls[0].options.body.values.Attachments[0].name, 'bild.pdf');
  assert.equal(env.editStatus.textContent, 'Der Dateityp passt nicht zur Dateiendung.');
  assert.equal(env.editModal.hidden, false);
  assert.equal(env.editAttachments.children.length, 0);
  assert.equal(env.editForm.submitButton.disabled, false);
});

test('startet erst nach bestätigtem Feedback-Abschluss zufällig einen passenden Effekt', async () => {
  const env = createWidgetEnvironment();
  env.setEntryStatus({ id: 6, title: 'Feedback', isClosed: false });
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.editForm.elements.CategoryID.value = '4';
  env.postResponses.push({ ok: true, json: async () => ({
    success: true, message: 'Erledigt.', categoryID: 4, categoryTitle: 'Fertig', categoryIsClosed: true, celebrateCompletion: true,
  }) });
  await env.editForm.listeners.submit({ preventDefault() {} });
  const party = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__confetti');
  const unicorn = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__unicorn-runner');
  const rocket = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__rocket-runner');
  const hearts = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__heart-burst');
  const arcade = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__arcade-effect');
  const retro = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__retro-effect');
  const dino = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__dino-effect');
  const ducks = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__duck-parade');
  const frogs = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__frog-parade');
  const iconShower = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__icon-shower');
  const ghostSwarm = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__ghost-swarm');
  const potion = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__potion-effect');
  const catPaws = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__cat-paw-trail');
  const flowerPower = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__flower-power');
  const pinball = env.document.body.children.find((child) => child.className === 'kw-masha-feedly__pinball-tilt');
  const playfulEffects = [party && unicorn, rocket, hearts, arcade, retro, dino, ducks, frogs, iconShower, ghostSwarm, potion, catPaws, flowerPower, pinball].filter(Boolean);
  assert.equal(playfulEffects.length, 1, 'Es läuft genau ein zufällig gewählter Effekt.');
  if (party && unicorn) {
    assert.equal(party.children.length, 128);
    assert.equal(unicorn.children[0].src, env.widget.dataset.unicornUrl);
  }
  if (retro) assert.equal(retro.children[0].className, 'kw-masha-feedly__retro-dialog');
  if (dino) assert.equal(dino.children[0].className, 'kw-masha-feedly__dino-save-copy');
  assert.equal(env.editContext.textContent, 'Status: Fertig');
});

test('zeigt im seriösen Theme sachliche Erfolgstexte', async () => {
  const env = createWidgetEnvironment();
  env.widget.dataset.theme = 'serious';
  env.setPageOpenEntryCount(0);
  env.setOpenEntryCount(0);
  env.setTotalEntriesCount(0);
  await env.listeners['kw-masha-feedly:opened']();
  assert.equal(env.rainbowTitle.textContent, 'Noch keine Einträge');
  assert.equal(env.rainbowMessage.textContent, 'Für Masha:Feedly liegen noch keine Einträge vor.');
});

test('spielt im seriösen Theme nach bestätigtem Abschluss einen ruhigen Effekt', async () => {
  const env = createWidgetEnvironment();
  env.widget.dataset.theme = 'serious';
  env.setEntryStatus({ id: 6, title: 'Rückmeldung', isClosed: false });
  await env.listeners['kw-masha-feedly:opened']();
  env.setEntryStatus({ id: 6, title: 'Rückmeldung', isClosed: false });
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.editForm.elements.CategoryID.value = '4';
  env.postResponses.push({ ok: true, json: async () => ({
    success: true, message: 'Erledigt.', categoryID: 4, categoryTitle: 'Fertig', categoryIsClosed: true, celebrateCompletion: true,
  }) });
  await env.editForm.listeners.submit({ preventDefault() {} });
  assert.equal(env.document.body.children.some((child) => child.className === 'kw-masha-feedly__confetti'), false);
  assert.equal(env.document.body.children.some((child) => child.className === 'kw-masha-feedly__unicorn-runner'), false);
  const calmEffects = ['kw-masha-feedly__serious-check', 'kw-masha-feedly__serious-glow', 'kw-masha-feedly__serious-rings', 'kw-masha-feedly__serious-confirmation'];
  assert.equal(env.document.body.children.filter((child) => calmEffects.includes(child.className)).length, 1);
});

test('startet keine Erfolgsanimation ohne serverseitig bestätigten Abschluss', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.editForm.elements.CategoryID.value = '4';
  env.postResponses.push({ ok: true, json: async () => ({
    success: true, message: 'Gespeichert.', categoryID: 4, categoryTitle: 'Done', categoryIsClosed: true, celebrateCompletion: false,
  }) });

  await env.editForm.listeners.submit({ preventDefault() {} });

  assert.equal(env.document.body.children.some((child) => child.className === 'kw-masha-feedly__confetti'), false);
  assert.equal(env.document.body.children.some((child) => child.className === 'kw-masha-feedly__unicorn-runner'), false);
});

test('startet keine Erfolgsanimation beim Wechsel in die Archivkategorie', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.editForm.elements.CategoryID.value = '5';
  env.postResponses.push({ ok: true, json: async () => ({
    success: true, message: 'Gespeichert.', categoryID: 5, categoryTitle: 'Archiv', categoryIsClosed: true, celebrateCompletion: false,
  }) });
  await env.editForm.listeners.submit({ preventDefault() {} });
  assert.equal(env.document.body.children.some((child) => child.className === 'kw-masha-feedly__confetti'), false);
  assert.equal(env.document.body.children.some((child) => child.className === 'kw-masha-feedly__unicorn-runner'), false);
  assert.equal(env.editContext.textContent, 'Status: Archiv');
});

test('lässt den Dialog offen und zeigt den Fehler, wenn das Speichern fehlschlägt', async () => {
  const env = createWidgetEnvironment();
  await env.listeners['kw-masha-feedly:opened']();
  await env.openListButton.listeners.click();
  const card = env.listContainer.children.find((child) => child.className === 'kw-masha-feedly__entry-card');
  env.listContainer.listeners.click({ target: card, preventDefault() {} });
  env.postResponses.push({ ok: false, json: async () => ({ success: false, message: 'Änderungen konnten nicht gespeichert werden.' }) });
  await env.editForm.listeners.submit({ preventDefault() {} });
  assert.equal(env.editModal.hidden, false);
  assert.match(env.editStatus.textContent, /konnten nicht gespeichert werden/);
  assert.equal(env.editForm.submitButton.disabled, false);
  assert.equal(env.requests.length, 2);
});

test('öffnet die angeforderte Eintragsbearbeitung nach Navigation zur Zielseite automatisch', async () => {
  const env = createWidgetEnvironment('https://feedly:8890/about-us?preview=1&masha-feedly-entry=71');
  env.listeners.DOMContentLoaded();
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(env.editModal.hidden, false);
  assert.equal(env.editForm.elements.EntryID.value, '71');
  assert.equal(env.panel.hidden, false);
  assert.equal(env.listModal.hidden, false);
  assert.equal(env.cleanedURL, '/about-us?preview=1');
  assert.equal(env.pageTarget.scrolled, true);
});
