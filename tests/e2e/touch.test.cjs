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


/** Sendet eine Wischgeste an die Browser-Eingabe; der Browser scrollt selbst. */
async function swipeUp(session, box) {
  const x = box.x + box.width / 2;
  const startY = box.y + box.height * 0.85;
  const endY = box.y + box.height * 0.2;
  const point = y => [{ x, y, id: 1 }];
  await session.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: point(startY) });
  for (let step = 1; step <= 12; step++) {
    await session.send('Input.dispatchTouchEvent', {
      type: 'touchMove', touchPoints: point(startY + (endY - startY) * step / 12),
    });
    await new Promise(resolve => setTimeout(resolve, 20));
  }
  await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
}

for (const viewport of [{ width: 320, height: 400 }, { width: 390, height: 280 }]) {
  test(`Touch: Öffnen, Wischen und Schließen bei ${viewport.width} × ${viewport.height}`, {
    skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
  }, async () => {
    const { chromium, expect } = require('@playwright/test');
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true, hasTouch: true, isMobile: true, viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
      // Tourstatus bleibt unverändert; der Test speichert keine Meldungen.
      await page.addInitScript(() => {
        const originalFetch = window.fetch.bind(window);
        window.fetch = (input, init) => /restartOnboarding|completeOnboarding/.test(String(input))
          ? Promise.resolve(new Response(JSON.stringify({ success: true }), { status: 200 }))
          : originalFetch(input, init);
      });
      await page.goto(new URL('/Security/login', config.baseURL).href);
      await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.onboardingEmail);
      await page.locator('input[type="password"]').first().fill(config.onboardingPassword);
      // Der Login richtet das Konto ein; die Widget-Bedienung darunter erfolgt per Touch.
      await page.locator('button[type="submit"], input[type="submit"]').first().click();
      await page.goto(config.baseURL);
      const widget = page.locator('[data-kw-masha-feedly]');
      const welcome = widget.locator('[data-masha-feedly-onboarding-welcome]');
      const toggle = widget.locator('.kw-masha-feedly__toggle');
      const panel = widget.locator('.kw-masha-feedly__panel');
      if (!await welcome.isVisible()) {
        await toggle.tap();
        await widget.locator('[data-masha-feedly-open-help]').tap();
        await widget.locator('[data-masha-feedly-restart-onboarding]').tap();
        await expect(welcome).toBeVisible();
      }
      await page.setViewportSize(viewport);
      await welcome.locator('[data-masha-feedly-tour-mobile-close]').tap();
      await expect(welcome).toBeHidden();
      await toggle.tap();
      await expect(panel).toBeVisible();
      await widget.locator('[data-masha-feedly-open-help]').tap();
      const help = widget.locator('[data-masha-feedly-help-modal]');
      const helpText = widget.locator('.kw-masha-feedly__help-mobile');
      await expect(help).toBeVisible();
      const header = help.locator('.kw-masha-feedly__dialog-header');
      const beforeHeader = await header.boundingBox();
      const beforeScroll = await helpText.evaluate(element => element.scrollTop);
      const session = await context.newCDPSession(page);
      await swipeUp(session, await helpText.boundingBox());
      await expect.poll(() => helpText.evaluate(element => element.scrollTop)).toBeGreaterThan(beforeScroll);
      const afterHeader = await header.boundingBox();
      assert.ok(Math.abs(afterHeader.y - beforeHeader.y) < 1, 'Der Kopf bleibt beim Wischen sichtbar.');
      const close = widget.locator('[data-masha-feedly-close-help]');
      const closeBox = await close.boundingBox();
      assert.ok(closeBox.y >= 0 && closeBox.y + closeBox.height <= viewport.height);
      await close.tap();
      await expect(help).toBeHidden();
      await panel.locator('.kw-masha-feedly__close').tap();
      await expect(panel).toBeHidden();
      await toggle.tap();
      await widget.locator('[data-masha-feedly-open-help]').tap();
      await expect(help).toBeVisible();
      await close.tap();
      await expect(help).toBeHidden();
      await expect(page.locator('body')).not.toHaveClass(/kw-masha-feedly-is-selecting/);
      if (await panel.isVisible()) await panel.locator('.kw-masha-feedly__close').tap();
      await toggle.tap();
      await expect(panel).toBeVisible();
      assert.deepEqual(errors, []);
    } finally {
      await context.close();
      await browser.close();
    }
  });
}
