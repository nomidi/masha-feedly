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

test('zeigt alle freigegebenen Personen mit ihrem Avatar in der Zuständigkeitsauswahl', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  try {
    await page.goto(new URL('/Security/login', config.baseURL).href);
    await page.locator('input[type="email"], input[name$="Email"], input[id$="Email"]').first().fill(config.email);
    await page.locator('input[type="password"]').first().fill(config.password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.goto(config.baseURL);

    const widget = page.locator('[data-kw-masha-feedly]');
    await expect(widget).toBeAttached();
    const widgetDataPromise = page.waitForResponse((response) =>
      new URL(response.url()).pathname.endsWith('/__masha-feedly/widgetData'));
    await widget.locator('.kw-masha-feedly__toggle').click();
    const widgetDataResponse = await widgetDataPromise;
    assert.equal(widgetDataResponse.ok(), true, 'Die geschützten Widgetdaten müssen erfolgreich geladen werden.');
    const widgetData = await widgetDataResponse.json();
    assert.equal(widgetData.success, true);
    assert.ok(widgetData.members.length >= 3, 'Creator, Commenter und Superadmin müssen freigegeben sein.');

    await widget.locator('[data-masha-feedly-start-selection]').click();
    await page.locator('[role="main"]').first().click();
    const createForm = widget.locator('[data-masha-feedly-entry-form]');
    await expect(createForm).toBeVisible();
    const choices = createForm.locator('[data-masha-feedly-assignees] > div > label');
    await expect(choices).toHaveCount(widgetData.members.length);

    for (const member of widgetData.members) {
      const choice = createForm.locator(`[data-masha-feedly-assignees] input[value="${member.id}"]`).locator('xpath=..');
      await expect(choice).toBeVisible();
      const avatar = choice.locator('.kw-masha-feedly__assignee-avatar');
      await expect(avatar).toBeVisible();
      await expect(choice.locator('.kw-masha-feedly__assignee-name')).toHaveText(member.name);
      if (member.imageURL) {
        const image = avatar.locator('img');
        await expect(image).toBeVisible();
        await expect.poll(() => image.evaluate((element) => element.complete && element.naturalWidth > 0)).toBe(true);
      } else {
        assert.ok(await avatar.textContent(), `Für ${member.name} muss ein sichtbarer Initialen-Avatar erscheinen, wenn kein Bild hinterlegt ist.`);
      }
    }
  } finally {
    await context.close();
    await browser.close();
  }
});
