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

test('Tooltip: data-tooltip wird beim Darüberfahren als sichtbarer Tooltip angezeigt', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const playwright = require('@playwright/test');
  const browser = await playwright.chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  try {
    await page.goto(new URL('/Security/login', config.baseURL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.email);
    await page.locator('input[type="password"]').first().fill(config.password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();

    await page.goto(config.baseURL);
    const host = page.locator('[data-masha-feedly-host]');
    const widget = host.locator('[data-kw-masha-feedly]');
    await widget.locator('.kw-masha-feedly__toggle').click();

    const button = widget.locator('[data-masha-feedly-start-selection]');
    // Dieser Button war nicht in der früheren Positivliste; er muss allein über data-tooltip funktionieren.
    await button.evaluate(element => {
      element.classList.remove('kw-masha-feedly__add');
      element.dataset.tooltip = 'Tooltip aus data-tooltip';
      element.setAttribute('aria-label', 'Anderer aria-label');
      element.title = 'Anderer title';
    });
    await button.hover();
    const tooltip = host.locator('.kw-masha-feedly__hover-tooltip');
    assert.equal(await tooltip.count(), 1, 'Hover muss das Tooltip-Element erzeugen');
    await playwright.expect(tooltip).toBeVisible();
    await playwright.expect(tooltip).toHaveText('Tooltip aus data-tooltip');
    const fontSizes = await tooltip.evaluate(element => ({
      tooltip: Number.parseFloat(getComputedStyle(element).fontSize),
      root: Number.parseFloat(getComputedStyle(document.documentElement).fontSize),
    }));
    assert.ok(fontSizes.tooltip < fontSizes.root * 0.7, `Tooltip-Schrift soll kleiner als 0,7rem sein: ${fontSizes.tooltip}px bei ${fontSizes.root}px root`);
    await playwright.expect(button).toHaveAttribute('aria-describedby', 'kw-masha-feedly-hover-tooltip');
  } finally {
    await context.close();
    await browser.close();
  }
});
