const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const read = (file) => fs.readFileSync(path.resolve(__dirname, file), 'utf8');
const source = read('../../client/src/js/masha-feedly-emoji.js');
const compiled = read('../../client/dist/js/masha-feedly-emoji.js');
const scss = read('../../client/src/scss/masha-feedly.scss');

test('Emoji-Auswahl liegt außerhalb des Dialogs und positioniert sich im sichtbaren Fenster', () => {
  assert.match(source, /overlayRoot\(\).*\.append\(picker\)/);
  assert.match(source, /getBoundingClientRect\(\)/);
  assert.match(source, /window\.innerWidth - width - edge/);
  assert.match(source, /window\.innerHeight - height - edge/);
  assert.match(source, /!picker\.contains\(.*eventTarget\(event\)/);
});

test('Kategorienamen verwenden nachgeladene Übersetzungen und zeigen niemals rohe Schlüssel', () => {
  assert.match(source, /translated && translated !== key \? translated : fallback/);
  assert.match(source, /const labels = \{/);
  assert.match(source, /const host = textarea\.closest\('\[data-kw-masha-feedly\]'\);[\s\S]*?const labels = \{/);
  assert.match(source, /EMOJI_CATEGORY_SMILEYS', 'Smileys'/);
  assert.match(source, /EMOJI_CATEGORY_PEOPLE', 'Menschen'/);
});

test('Smiley-Schaltfläche und Emoji-Fenster bleiben kompakt und lesbar', () => {
  assert.match(scss, /\.kw-masha-feedly__emoji-toggle \{[^}]*width: 32px; height: 32px;/);
  assert.match(scss, /\.kw-masha-feedly__emoji-toggle svg \{[^}]*width: 21px; height: 21px;/);
  assert.match(scss, /\.kw-masha-feedly__emoji-picker \{ position: fixed; z-index: 2147483000; width: min\(360px, calc\(100vw - 24px\)\);/);
  assert.match(scss, /\.kw-masha-feedly__emoji-picker\[hidden\] \{ display: none !important; \}/);
});

test('ausgeliefertes Emoji-JavaScript entspricht der getesteten Quelldatei', () => {
  assert.equal(compiled, source);
});
