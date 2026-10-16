const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');

const stylesheet = fs.readFileSync(path.resolve(__dirname, '../../client/dist/css/masha-feedly.css'), 'utf8');

/** Prüft echte CSS-Kanten ohne Anmeldung, Netzwerkzugriffe oder Änderungen an Website-Daten. */
for (const browserName of ['chromium', 'firefox', 'webkit']) {
  test(`Panelkanten (${browserName}): Erfolgskarte erzeugt keine Lücke vor dem Bearbeitungsfach`, async () => {
    const playwright = require('@playwright/test');
    const browser = await playwright[browserName].launch({headless: true});
    try {
      const page = await browser.newPage();
      for (const width of [1920, 1440, 1024, 641, 640, 390]) {
        await page.setViewportSize({width, height: 900});
        await page.setContent(`<style>${stylesheet}</style><div data-masha-feedly-surface><div id="widget" class="kw-masha-feedly" data-theme="playful" data-panel-open="true" data-edit-open="true" data-list-open="true" data-success-visible="false"><div class="kw-masha-feedly__panel"></div><div class="kw-masha-feedly__entries-modal"><section class="kw-masha-feedly__entries-dialog"></section></div><div class="kw-masha-feedly__edit-modal"><section class="kw-masha-feedly__edit-dialog"></section></div></div></div>`);
        const measure = () => page.evaluate(() => {
          const rect = (selector) => {
            const bounds = document.querySelector(selector).getBoundingClientRect();
            return {left: bounds.left, right: bounds.right, width: bounds.width};
          };
          return {list: rect('.kw-masha-feedly__entries-modal'), edit: rect('.kw-masha-feedly__edit-modal')};
        });
        const normal = await measure();
        await page.locator('#widget').evaluate((element) => { element.dataset.successVisible = 'true'; });
        const success = await measure();
        assert.ok(Math.abs(normal.edit.right - success.edit.right) < 1, `${width}px: gleiche rechte Kante in beiden Zuständen`);
        assert.ok(Math.abs(normal.edit.width - success.edit.width) < 1, `${width}px: gleiche Breite in beiden Zuständen`);
        if (width > 640) {
          assert.ok(Math.abs(success.edit.right - success.list.left - 30) < 1, `${width}px: vorgesehene Überdeckung von 30px statt einer Lücke`);
        } else {
          assert.ok(Math.abs(success.edit.left) < 1 && Math.abs(success.edit.width - width) < 1, `${width}px: Bearbeitung nutzt die volle Bildschirmbreite`);
        }
        await page.locator('#widget').evaluate((element) => { element.dataset.listOpen = 'false'; });
        const direct = await measure();
        assert.ok(direct.edit.right <= width && direct.edit.left >= -1, `${width}px: auch ohne Liste innerhalb des Fensters`);
      }
    } finally { await browser.close(); }
  });
}
