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


const yaml = require('js-yaml');
const variants = [
  { name: 'Deutsch Du', language: 'de', address: 'du', notice: /Für die Einführung brauchst du einen größeren Bildschirm/, cancel: 'Einführung abbrechen' },
  { name: 'Deutsch Sie', language: 'de', address: 'sie', notice: /Für die Einführung benötigen Sie einen größeren Bildschirm/, cancel: 'Einführung abbrechen' },
  { name: 'Englisch', language: 'en', address: 'du', notice: /The tour needs a larger screen/, cancel: 'Cancel tour' },
];

for (const browserName of ['chromium', 'firefox', 'webkit']) {
  for (const variant of variants) {
    test(`Mobiler Einführungshinweis (${browserName}): ${variant.name}`, {
      skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
    }, async () => {
      const playwright = require('@playwright/test');
      const { expect } = playwright;
      const browser = await playwright[browserName].launch({ headless: true });
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
      const page = await context.newPage();
      const languageFile = yaml.load(fs.readFileSync(path.join(__dirname, '../../lang', `${variant.language}.yml`), 'utf8'));
      const translations = languageFile[variant.language]['KW\\MashaFeedly\\Translations'];
      assert.ok(translations?.TOUR_MOBILE_NOTICE, 'Die Sprachdatei muss den mobilen Hinweis enthalten.');
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      try {
        // Sprache und Anrede werden nur in dieser Browserantwort vorbereitet; Profile bleiben unverändert.
        await page.route(config.baseURL.replace(/\/$/, '') + '/', async route => {
          const response = await route.fetch();
          const body = await response.text();
          const fixtureScript = `<script>
            Object.assign(window.KWMashaFeedlyTranslations, ${JSON.stringify(translations)});
            const languageFixture = document.createElement('div');
            languageFixture.innerHTML = window.KWMashaFeedlyWidgetMarkup;
            languageFixture.firstElementChild.dataset.address = ${JSON.stringify(variant.address)};
            languageFixture.querySelector('.kw-masha-feedly__onboarding-mobile-label').textContent = ${JSON.stringify(translations.TOUR_MOBILE_CANCEL)};
            languageFixture.querySelector('[data-masha-feedly-tour-mobile-close]').textContent = ${JSON.stringify(translations.TOUR_MOBILE_CLOSE)};
            window.KWMashaFeedlyWidgetMarkup = languageFixture.innerHTML;
          </script>`;
          await route.fulfill({ response, body: body.replace('</body>', fixtureScript + '</body>') });
        });
        await page.addInitScript(() => {
          window.languageTourCompletions = [];
          const originalFetch = window.fetch.bind(window);
          window.fetch = (input, init) => {
            if (String(input).includes('restartOnboarding')) return Promise.resolve(new Response(JSON.stringify({ success: true }), { status: 200 }));
            if (String(input).includes('completeOnboarding')) {
              window.languageTourCompletions.push(init.body.get('Deferred'));
              return Promise.resolve(new Response(JSON.stringify({ success: true }), { status: 200 }));
            }
            return originalFetch(input, init);
          };
        });
        await page.goto(new URL('/Security/login', config.baseURL).href);
        await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.onboardingEmail);
        await page.locator('input[type="password"]').first().fill(config.onboardingPassword);
        await page.locator('button[type="submit"], input[type="submit"]').first().click();
        await page.goto(config.baseURL);
        const widget = page.locator('[data-kw-masha-feedly]');
        const welcome = widget.locator('[data-masha-feedly-onboarding-welcome]');
        if (await welcome.isVisible()) await welcome.locator('[data-masha-feedly-tour-skip]').click();
        const openMobileTour = async () => {
          await page.setViewportSize({ width: 1280, height: 900 });
          await expect(welcome.locator('[data-masha-feedly-tour-mobile-close]')).toBeHidden();
          await widget.locator('.kw-masha-feedly__toggle').click();
          await widget.locator('[data-masha-feedly-open-help]').click();
          await widget.locator('[data-masha-feedly-restart-onboarding]').click();
          await expect(welcome).toBeVisible();
          await page.setViewportSize({ width: 390, height: 844 });
          const notice = welcome.locator('.kw-masha-feedly__onboarding-mobile-notice');
          await expect(notice).toBeVisible();
          await expect(notice).toContainText(variant.notice);
          await expect(notice).toContainText(variant.cancel);
          await expect(welcome.locator('button:visible')).toHaveCount(2);
          await expect(welcome.locator('[data-masha-feedly-tour-mobile-close]')).toHaveText('OK');
          await expect(welcome.locator('[data-masha-feedly-tour-end]')).toHaveText(variant.cancel, { useInnerText: true });
          await expect(welcome.locator('[data-masha-feedly-tour-start]')).toBeHidden();
          await expect(welcome.locator('[data-masha-feedly-tour-skip]')).toBeHidden();
        };
        await openMobileTour();
        await page.evaluate(() => { window.languageTourCompletions = []; });
        await welcome.locator('[data-masha-feedly-tour-mobile-close]').click();
        await expect(welcome).toBeHidden();
        assert.deepEqual(await page.evaluate(() => window.languageTourCompletions), ['1']);
        await openMobileTour();
        await welcome.locator('[data-masha-feedly-tour-end]').click();
        await expect(welcome).toBeHidden();
        assert.deepEqual(await page.evaluate(() => window.languageTourCompletions), ['1', null]);
        assert.deepEqual(errors, []);
      } finally {
        await context.close();
        await browser.close();
      }
    });
  }
}
