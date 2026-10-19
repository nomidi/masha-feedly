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

/** Prüft Rückmeldungen und feste Aktionen mit abgefangenen Antworten, ohne eine echte E-Mail zu senden. */
test('Konfiguration: Testmail bleibt im Reiter und erhält ungespeicherte Werte', { skip: missing.length ? 'E2E-Konfiguration fehlt' : false }, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await page.goto(new URL('/Security/login', config.BASE_URL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.SUPERADMIN_EMAIL);
    await page.locator('input[type="password"]').first().fill(config.SUPERADMIN_PASSWORD);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.goto(new URL('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig', config.BASE_URL).href);
    const form = page.locator('form.masha-feedly-configuration');
    const button = form.locator('[name="action_sendTestEmail"]');
    const toolbar = form.locator(':scope > .btn-toolbar');
    const address = form.locator('select[name="MashaFeedlyAddress"]');
    const unsavedAddress = await address.inputValue() === 'du' ? 'sie' : 'du';
    await address.selectOption(unsavedAddress);
    const url = page.url();
    let requests = 0;
    let success = true;
    let releaseResponse;
    await page.route('**/SilverStripe-SiteConfig-SiteConfig/EditForm', async route => {
      requests++;
      const request = route.request();
      assert.equal(request.method(), 'POST');
      assert.equal(request.headers().accept, 'application/json');
      assert.match(request.postData() || '', /action_sendTestEmail/);
      assert.match(request.postData() || '', /SecurityID/);
      assert.doesNotMatch(request.postData() || '', /name="MashaFeedlyAddress"/);
      await new Promise(resolve => { releaseResponse = resolve; });
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success, message: success ? 'Simulierter Versand erfolgreich.' : 'Simulierter Versandfehler.' }) });
    });
    await button.click();
    await expect(button).toBeDisabled();
    await expect(form.locator('[data-email-test-feedback]')).toHaveAttribute('data-state', 'pending');
    await expect.poll(() => typeof releaseResponse).toBe('function');
    releaseResponse();
    await expect(form.locator('[data-email-test-feedback]')).toHaveAttribute('data-state', 'success');
    await expect(button).toBeEnabled();
    assert.equal(page.url(), url);
    assert.equal(await address.inputValue(), unsavedAddress);
    success = false;
    releaseResponse = null;
    await button.click();
    await expect.poll(() => typeof releaseResponse).toBe('function');
    releaseResponse();
    await expect(form.locator('[data-email-test-feedback]')).toHaveAttribute('data-state', 'error');
    await expect(form.locator('[role="alert"][data-email-test-feedback]')).toContainText('Simulierter Versandfehler.');
    assert.equal(page.url(), url);
    assert.equal(await address.inputValue(), unsavedAddress);
    assert.equal(requests, 2);
    await form.locator('fieldset').evaluate(element => { element.scrollTop = element.scrollHeight; });
    const box = await toolbar.boundingBox();
    assert.ok(box && box.y >= 0 && box.y + box.height <= page.viewportSize().height);
  } finally {
    await browser.close();
  }
});
