const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');

const source = fs.readFileSync(path.resolve(__dirname, '../../client/dist/js/masha-feedly.js'), 'utf8');

/** Prüft native Scrollbewegung im Shadow-DOM trotz globaler Scrollsteuerung des Themes. */
test('Meldungstext scrollt im Widget, während eine Lenis-artige Theme-Steuerung aktiv bleibt', async () => {
  const {chromium, expect} = require('@playwright/test');
  const browser = await chromium.launch({headless: true});
  try {
    const page = await browser.newPage({viewport: {width: 1200, height: 800}});
    await page.setContent('<style>body { height: 4000px; margin: 0; }</style><div style="width: 300px; height: 300px">Website</div>');
    await page.evaluate(() => {
      // Entspricht der dokumentierten Lenis-Ausnahme, ohne eine Website oder API aufzurufen.
      document.addEventListener('wheel', (event) => {
        if (event.composedPath().some(node => node.hasAttribute?.('data-lenis-prevent'))) return;
        event.preventDefault();
        window.scrollBy(0, event.deltaY);
      }, {passive: false});
      window.KWMashaFeedlyWidgetStylesheet = 'data:text/css,' + encodeURIComponent(
        '[data-masha-feedly-host] { display: block; } .kw-masha-feedly__edit-dialog { position: fixed; top: 20px; right: 20px; width: 400px; height: 500px; overflow: auto; background: white; } .kw-masha-feedly__entry-description { height: 200px; overflow: auto; }'
      );
      window.KWMashaFeedlyWidgetMarkup = '<div data-kw-masha-feedly><button class="kw-masha-feedly__toggle"></button><div class="kw-masha-feedly__panel" hidden></div><button class="kw-masha-feedly__close"></button><section class="kw-masha-feedly__edit-dialog"><div class="kw-masha-feedly__entry-description"><div style="height: 1800px">Lange Meldung</div></div><div style="height: 1400px">Formular und Kommentare</div></section></div>';
    });
    await page.addScriptTag({content: source});
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    const dialog = page.locator('.kw-masha-feedly__edit-dialog');
    const description = page.locator('.kw-masha-feedly__entry-description');
    await expect(dialog).toBeVisible();
    await description.hover();
    await page.mouse.wheel(0, 250);
    await expect.poll(() => description.evaluate(element => element.scrollTop)).toBeGreaterThan(0);
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
    const bounds = await dialog.boundingBox();
    await page.mouse.move(bounds.x + 30, bounds.y + 350);
    await page.mouse.wheel(0, 250);
    await expect.poll(() => dialog.evaluate(element => element.scrollTop)).toBeGreaterThan(0);
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
    await page.mouse.move(100, 100);
    await page.mouse.wheel(0, 250);
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
  } finally {
    await browser.close();
  }
});
