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


/** Größenwechsel ohne Navigation müssen Dialoge, Tour-Sperren und Formularinhalt erhalten. */
for (const browserName of ['chromium', 'firefox', 'webkit']) {
  test(`Fensterwechsel (${browserName}): Hilfe, Einführung und Formular Desktop → mobil → Desktop`, {
    skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
  }, async () => {
    const playwright = require('@playwright/test');
    const { expect } = playwright;
    const browser = await playwright[browserName].launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
      await page.addInitScript(() => {
        const originalFetch = window.fetch.bind(window);
        window.fetch = (input, init) => {
          return /restartOnboarding|completeOnboarding/.test(String(input))
            ? Promise.resolve(new Response(JSON.stringify({ success: true }), { status: 200 }))
            : originalFetch(input, init);
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
      const toggle = widget.locator('.kw-masha-feedly__toggle');
      if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
      await widget.locator('[data-masha-feedly-open-help]').click();
      const help = widget.locator('[data-masha-feedly-help-modal]');
      // Innerhalb einer Variante bleibt die Hilfe offen; am Breakpoint schließen alle Fenster.
      await page.setViewportSize({ width: 1100, height: 700 });
      await expect(help).toBeVisible();
      await page.setViewportSize({ width: 390, height: 844 });
      await expect(help).toBeHidden();
      await expect(widget.locator('.kw-masha-feedly__panel')).toBeHidden();
      await toggle.click();
      await widget.locator('[data-masha-feedly-open-help]').click();
      await page.setViewportSize({ width: 320, height: 400 });
      await expect(help).toBeVisible();
      await page.setViewportSize({ width: 1280, height: 900 });
      await expect(help).toBeHidden();
      await expect(widget.locator('.kw-masha-feedly__panel')).toBeHidden();
      await toggle.click();
      await widget.locator('[data-masha-feedly-open-help]').click();
      await widget.locator('[data-masha-feedly-restart-onboarding]').click();
      await welcome.locator('[data-masha-feedly-tour-start]').click();
      await toggle.click();
      const tip = widget.locator('[data-masha-feedly-onboarding-text]');
      await expect(tip).toContainText('Schritt 2 von 8');
      await page.setViewportSize({ width: 390, height: 844 });
      await expect(welcome).toBeVisible();
      await expect(welcome.locator('[data-masha-feedly-tour-mobile-close]')).toBeEnabled();
      await expect(widget.locator('[data-masha-feedly-onboarding-tip]')).toBeHidden();
      await expect(page.locator('.kw-masha-feedly__onboarding-shade')).toBeHidden();
      await expect(widget.locator('[role="dialog"]:visible')).toHaveCount(1);
      await page.setViewportSize({ width: 1280, height: 900 });
      await expect(welcome).toBeVisible();
      await expect(welcome.locator('[data-masha-feedly-tour-start]')).toBeVisible();
      await welcome.locator('[data-masha-feedly-tour-start]').click();
      await expect(tip).toContainText('Schritt 1 von 8');
      await toggle.click();
      await widget.locator('[data-masha-feedly-start-selection]').click();
      // Bereichsauswahl beendet weder die Tour noch sperrt sie nach dem Wechsel die Website.
      await page.setViewportSize({ width: 390, height: 844 });
      await expect(welcome).toBeVisible();
      await expect(page.locator('body')).not.toHaveClass(/kw-masha-feedly-is-selecting/);
      await page.setViewportSize({ width: 1280, height: 900 });
      await welcome.locator('[data-masha-feedly-tour-start]').click();
      await toggle.click();
      await widget.locator('[data-masha-feedly-start-selection]').click();
      await page.locator('[role="main"]').first().click();
      const formModal = widget.locator('[data-masha-feedly-modal]');
      const description = formModal.locator('[name="Content"]');
      await description.fill('Dieser Entwurf bleibt beim Größenwechsel erhalten.');
      await page.setViewportSize({ width: 390, height: 844 });
      await expect(welcome).toBeVisible();
      await expect(formModal).toBeHidden();
      await expect(description).toHaveValue('Dieser Entwurf bleibt beim Größenwechsel erhalten.');
      await expect(widget.locator('[role="dialog"]:visible')).toHaveCount(1);
      await welcome.locator('[data-masha-feedly-tour-mobile-close]').click();
      await expect(welcome).toBeHidden();
      await page.setViewportSize({ width: 1280, height: 900 });
      await expect(widget.locator('[role="dialog"]:visible')).toHaveCount(0);
      await toggle.click();
      await widget.locator('[data-masha-feedly-open-help]').click();
      await expect(help).toBeVisible();
      await widget.locator('[data-masha-feedly-restart-onboarding]').click();
      await welcome.locator('[data-masha-feedly-tour-start]').click();
      await page.setViewportSize({ width: 390, height: 844 });
      await welcome.locator('[data-masha-feedly-tour-end]').click();
      await page.setViewportSize({ width: 1280, height: 900 });
      await expect(welcome).toBeHidden();
      await expect(widget.locator('[data-masha-feedly-onboarding-tip]')).toBeHidden();
      await toggle.click();
      await widget.locator('[data-masha-feedly-open-help]').click();
      await expect(help).toBeVisible();
      assert.deepEqual(errors, []);
    } finally { await context.close(); await browser.close(); }
  });
}
