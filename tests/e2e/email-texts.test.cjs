const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const local = {};
const envFile = path.join(__dirname, '.env.e2e');
if (fs.existsSync(envFile)) fs.readFileSync(envFile, 'utf8').split(/\r?\n/).forEach(line => {
  const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
  if (match && !match[2].startsWith('#')) local[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
});
const value = key => process.env[key] || local[key];
const config = Object.fromEntries(['BASE_URL', 'SUPERADMIN_EMAIL', 'SUPERADMIN_PASSWORD'].map(key => [key, value(`MASHA_FEEDLY_E2E_${key}`)]));
const missing = Object.entries(config).filter(([, value]) => !value).map(([key]) => key);

/** Prüft den echten CMS-Klick einschließlich AJAX und sichtbarer Versandbestätigung. */
test('E-Mail-Texte: Testmail-Button sendet per AJAX und zeigt eine Bestätigung', { skip: missing.length ? 'E2E-Konfiguration fehlt' : false }, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await page.addInitScript(() => {
      const removeDebugBar = () => document.querySelectorAll('.phpdebugbar').forEach(bar => bar.remove());
      new MutationObserver(removeDebugBar).observe(document, { childList: true, subtree: true });
      removeDebugBar();
    });
    await page.goto(new URL('/Security/login', config.BASE_URL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.SUPERADMIN_EMAIL);
    await page.locator('input[type="password"]').first().fill(config.SUPERADMIN_PASSWORD);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.goto(new URL('/admin/masha-feedly/email-texts', config.BASE_URL).href);
    const button = page.locator('#Form_EditForm_TestEmail_MashaFeedlyWelcomeTextDu');
    await expect(button).toBeVisible();
    const [response] = await Promise.all([
      page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/email-texts/EditForm'), { timeout: 10000 }),
      button.click(),
    ]);
    const submitted = new URLSearchParams(response.request().postData());
    assert.equal(response.status(), 200, JSON.stringify({action: submitted.get('action_sendEmailTextTest?EmailTextField=MashaFeedlyWelcomeTextDu'), hasText: submitted.has('MashaFeedlyWelcomeTextDu'), hasToken: !!submitted.get('SecurityID'), body: (await response.text()).slice(0, 300)}));
    assert.equal(submitted.get('action_sendEmailTextTest?EmailTextField=MashaFeedlyWelcomeTextDu'), '1');
    assert.ok(submitted.get('SecurityID'));
    await expect(page.locator('#Form_EditForm')).toContainText('Die Test-E-Mail wurde an');
  } finally {
    await browser.close();
  }
});
