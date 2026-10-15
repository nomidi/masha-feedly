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
  email: process.env.MASHA_FEEDLY_E2E_CREATOR_EMAIL || localEnv.MASHA_FEEDLY_E2E_CREATOR_EMAIL,
  password: process.env.MASHA_FEEDLY_E2E_CREATOR_PASSWORD || localEnv.MASHA_FEEDLY_E2E_CREATOR_PASSWORD,
};
const missingConfig = Object.entries(config).filter(([, value]) => !value).map(([name]) => name);

test('Helply: Feedly-Kurzhilfe öffnet die Helply-Sammlung und übergibt Fragen', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    await page.goto(new URL('/Security/login', config.baseURL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.email);
    await page.locator('input[type="password"]').first().fill(config.password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.route('**/__masha-feedly/listEntries*', route => route.fulfill({
      contentType: 'application/json', body: JSON.stringify({
        success: true, mode: 'page', entries: [], categories: [], priorities: [],
        unreadCount: 0, unreadCommentCount: 0, totalCount: 0, openCount: 0, pageOpenCount: 0,
      }),
    }));
    await page.goto(config.baseURL);
    const widget = page.locator('[data-kw-masha-feedly]');
    await widget.locator('.kw-masha-feedly__toggle').click();
    const faqButton = widget.locator('.kw-masha-feedly__support-buttons .kw-masha-feedly__faq-button');
    await expect(faqButton).toBeVisible();
    await faqButton.click();
    const directDialog = page.getByRole('dialog', { name: /Schritt für Schritt|Make sense of every step/ });
    await expect(directDialog).toBeVisible();
    await directDialog.locator('[data-help-close], [data-faq-close]').click();
    await widget.locator('[data-masha-feedly-open-help]').click();
    const quickHelp = widget.locator('[data-masha-feedly-help-modal]');
    await quickHelp.locator('[data-masha-feedly-open-helply]').click();

    const collection = page.locator('[data-helply][data-help-collection="feedly"], [data-helply][data-faq-collection="feedly"]');
    const modalID = await collection.locator('[data-help-open], [data-faq-open]').getAttribute('aria-controls');
    const modal = page.locator(`#${modalID}`);
    await expect(modal).toBeVisible();
    await expect(modal.locator('h2')).toContainText(/Schritt für Schritt|Make sense of every step/);
    await modal.locator('[data-help-search], [data-faq-search]').fill('kein Treffer für eine Frage');
    await expect(modal.locator('[data-help-feedly], [data-faq-feedly]')).toBeVisible();
    await modal.locator('[data-help-feedly-open], [data-faq-feedly-open]').click();
    await expect(modal).toBeHidden();
    await expect(widget.locator('[data-masha-feedly-selection-banner]')).toBeVisible();
    assert.deepEqual(errors, []);
  } finally {
    await context.close();
    await browser.close();
  }
});
