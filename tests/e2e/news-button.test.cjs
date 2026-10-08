const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');

const localEnvPath = path.join(__dirname, '.env.e2e');
const localEnv = {};
if (fs.existsSync(localEnvPath)) {
  fs.readFileSync(localEnvPath, 'utf8').split(/\r?\n/).forEach((line) => {
    const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
    if (!match || match[2].startsWith('#')) return;
    localEnv[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
  });
}
const config = {
  baseURL: process.env.MASHA_FEEDLY_E2E_BASE_URL || localEnv.MASHA_FEEDLY_E2E_BASE_URL,
  onboardingEmail: process.env.MASHA_FEEDLY_E2E_CREATOR_EMAIL || localEnv.MASHA_FEEDLY_E2E_CREATOR_EMAIL,
  onboardingPassword: process.env.MASHA_FEEDLY_E2E_CREATOR_PASSWORD || localEnv.MASHA_FEEDLY_E2E_CREATOR_PASSWORD,
};
const missingConfig = Object.entries(config).filter(([, value]) => !value).map(([name]) => name);


/** Der Neuigkeitenknopf behält in allen Engines seine eigene helle Darstellung. */
for (const browserName of ['chromium', 'firefox', 'webkit']) {
  for (const colorScheme of ['light', 'dark']) {
    test(`Neuigkeiten (${browserName}, ${colorScheme}): heller Hintergrund ohne native Button-Darstellung`, {
      skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
    }, async () => {
      const playwright = require('@playwright/test');
      const browser = await playwright[browserName].launch({ headless: true });
      const context = await browser.newContext({ ignoreHTTPSErrors: true, colorScheme, viewport: { width: 1280, height: 900 } });
      const page = await context.newPage();
      try {
        await page.goto(new URL('/Security/login', config.baseURL).href);
        await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.onboardingEmail);
        await page.locator('input[type="password"]').first().fill(config.onboardingPassword);
        await page.locator('button[type="submit"], input[type="submit"]').first().click();
        // Die Darstellung benötigt keine neuen Meldungen oder Änderungen an echten Benutzerdaten.
        await page.route('**/__masha-feedly/listEntries*', route => route.fulfill({
          contentType: 'application/json', body: JSON.stringify({
            success: true, mode: 'page', entries: [], categories: [], priorities: [],
            unreadCount: 1, unreadCommentCount: 0, totalCount: 1, openCount: 1, pageOpenCount: 1,
          }),
        }));
        await page.goto(config.baseURL);
        const widget = page.locator('[data-kw-masha-feedly]');
        await widget.locator('.kw-masha-feedly__toggle').click();
        const button = widget.locator('[data-masha-feedly-open-news]');
        await playwright.expect(button).toBeVisible();
        for (const theme of ['playful', 'serious']) {
          // Prüft beide Themes ohne die gespeicherte Profilwahl zu verändern.
          await widget.evaluate((element, selectedTheme) => element.setAttribute('data-theme', selectedTheme), theme);
          const styles = await button.evaluate(element => {
            const style = getComputedStyle(element);
            const iconStyle = getComputedStyle(element.querySelector('.kw-masha-feedly__news-icon'));
            return { appearance: style.appearance, background: style.backgroundColor, image: style.backgroundImage, iconBackground: iconStyle.backgroundColor };
          });
          assert.equal(styles.appearance, 'none');
          assert.equal(styles.background, 'rgb(255, 255, 255)');
          assert.match(styles.image, /linear-gradient/);
          assert.equal(styles.iconBackground, 'rgba(0, 0, 0, 0)', `${theme}: keine dunkle Fläche hinter NEW`);
          const add = widget.locator('[data-masha-feedly-start-selection]');
          const addStyles = await add.evaluate(element => ({
            appearance: getComputedStyle(element).appearance,
            background: getComputedStyle(element).backgroundColor,
            color: getComputedStyle(element).color,
          }));
          assert.equal(addStyles.appearance, 'none');
          assert.equal(addStyles.color, 'rgb(255, 255, 255)');
          assert.notEqual(addStyles.background, 'rgb(51, 65, 85)');
          const submitStyles = await widget.locator('[data-masha-feedly-modal] .kw-masha-feedly__submit').evaluate(element => ({
            background: getComputedStyle(element).backgroundColor,
            color: getComputedStyle(element).color,
          }));
          assert.equal(submitStyles.background, 'rgb(230, 0, 126)');
          assert.equal(submitStyles.color, 'rgb(255, 255, 255)');
        }
        if (browserName === 'webkit' && colorScheme === 'dark') {
          await button.screenshot({ path: '/tmp/feedly-news-webkit.png' });
        }
      } finally {
        await context.close();
        await browser.close();
      }
    });
  }
}
