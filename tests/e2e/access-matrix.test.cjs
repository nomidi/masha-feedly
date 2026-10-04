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
  allowedEmail: process.env.MASHA_FEEDLY_E2E_CREATOR_EMAIL || localEnv.MASHA_FEEDLY_E2E_CREATOR_EMAIL,
  allowedPassword: process.env.MASHA_FEEDLY_E2E_CREATOR_PASSWORD || localEnv.MASHA_FEEDLY_E2E_CREATOR_PASSWORD,
  deniedEmail: process.env.MASHA_FEEDLY_E2E_DENIED_EMAIL || localEnv.MASHA_FEEDLY_E2E_DENIED_EMAIL,
  deniedPassword: process.env.MASHA_FEEDLY_E2E_DENIED_PASSWORD || localEnv.MASHA_FEEDLY_E2E_DENIED_PASSWORD,
};
const missingAllowedConfig = ['baseURL', 'allowedEmail', 'allowedPassword'].filter((key) => !config[key]);
const missingDeniedConfig = ['deniedEmail', 'deniedPassword'].filter((key) => !config[key]);

const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/7WQAAAAASUVORK5CYII=',
  'base64'
);

const signIn = async (page, email, password) => {
  await page.goto(new URL('/Security/login', config.baseURL).toString());
  await page.locator('input[type="email"], input[name$="Email"], input[id$="Email"]').first().fill(email);
  await page.locator('input[type="password"]').first().fill(password);
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.goto(config.baseURL);
};

/** Legt einen eindeutigen Eintrag mit Kommentar und privatem Bild über die echte Oberfläche an. */
const createProtectedContent = async (page, expect) => {
  await signIn(page, config.allowedEmail, config.allowedPassword);
  const widget = page.locator('[data-kw-masha-feedly]');
  await expect(widget).toBeAttached();
  const unique = `E2E Zugriffsmatrix ${new Date().toISOString()} ${Math.random().toString(36).slice(2, 8)}`;
  const commentText = `Privater Kommentar zu ${unique}`;
  const toggle = widget.locator('.kw-masha-feedly__toggle');
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  await widget.locator('[data-masha-feedly-start-selection]').click();
  await page.locator('main').first().click();

  const createForm = widget.locator('[data-masha-feedly-entry-form]');
  await expect(createForm).toBeVisible();
  await createForm.locator('[name="Content"]').fill(unique);
  await createForm.locator('[name="Attachments[]"]').setInputFiles({
    name: 'e2e-private-access.png',
    mimeType: 'image/png',
    buffer: png,
  });
  const createResponsePromise = page.waitForResponse((response) =>
    response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/createEntry'));
  await widget.locator('[data-masha-feedly-modal] button[type="submit"][form="kw-masha-feedly-create-form"]').click();
  const createResponse = await createResponsePromise;
  const created = await createResponse.json();
  assert.equal(createResponse.ok(), true, `Eintrag mit Anhang anlegen: ${created.message || createResponse.status()}`);
  assert.equal(created.success, true);
  assert.equal(created.attachments.length, 1);
  assert.match(created.attachments[0].url, /\S/);

  await widget.locator('[data-masha-feedly-open-list]').click();
  const card = widget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${created.entryID}"]`);
  await expect(card).toBeVisible();
  await card.click();
  const commentForm = widget.locator('[data-masha-feedly-comment-form]');
  await commentForm.locator('[name="CommentText"]').fill(commentText);
  const commentResponsePromise = page.waitForResponse((response) =>
    response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/__masha-feedly-comment'));
  await commentForm.locator('[type="submit"]').click();
  const commentResponse = await commentResponsePromise;
  const comment = await commentResponse.json();
  assert.equal(commentResponse.ok(), true, `Kommentar anlegen: ${comment.message || commentResponse.status()}`);
  assert.equal(comment.success, true);
  await expect(widget.locator('[data-masha-feedly-comments]')).toContainText(commentText);
  await expect(widget.locator('[data-masha-feedly-edit-attachments] img')).toBeVisible();

  return { widget, unique, commentText, attachmentURL: new URL(created.attachments[0].url, config.baseURL).toString() };
};

const assertPrivateAssetDenied = async (requestContext, attachmentURL) => {
  const response = await requestContext.get(attachmentURL);
  const body = await response.body();
  const contentType = response.headers()['content-type'] || '';
  assert.notEqual(body.toString('base64'), png.toString('base64'), 'Die geschützte Bilddatei darf nicht ausgeliefert werden.');
  assert.ok(response.status() !== 200 || !contentType.toLowerCase().startsWith('image/'), 'Ein geschützter Anhang darf nicht als Bildantwort erreichbar sein.');
};

test('Zugriff im Browser: Gast sieht keine Feedly-Daten und kann private Anhänge nicht abrufen', {
  skip: missingAllowedConfig.length ? `E2E-Konfiguration fehlt: ${missingAllowedConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const allowedContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const guestContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const allowedPage = await allowedContext.newPage();
  const guestPage = await guestContext.newPage();

  try {
    const protectedContent = await createProtectedContent(allowedPage, expect);
    const allowedList = await allowedPage.request.get(new URL('/__masha-feedly/listEntries?mode=all', config.baseURL).toString());
    const allowedData = await allowedList.json();
    assert.equal(allowedList.status(), 200);
    const entry = allowedData.entries.find((item) => item.content === protectedContent.unique);
    assert.ok(entry, 'Das freigegebene Mitglied kann den Eintrag abrufen.');
    assert.ok(entry.comments.some((item) => item.text === protectedContent.commentText), 'Das freigegebene Mitglied kann den Kommentar abrufen.');
    assert.equal(entry.attachments.length, 1, 'Das freigegebene Mitglied kann Anhangsdaten abrufen.');
    const allowedAsset = await allowedPage.request.get(protectedContent.attachmentURL);
    assert.equal(allowedAsset.status(), 200);
    assert.equal((await allowedAsset.body()).toString('base64'), png.toString('base64'));

    await guestPage.goto(config.baseURL);
    await expect(guestPage.locator('[data-kw-masha-feedly]')).toHaveCount(0, 'Gäste erhalten kein Feedly-Widget.');
    for (const route of [
      '/__masha-feedly',
      '/__masha-feedly/listEntries?mode=all',
      '/__masha-feedly/savedViews',
      '/__masha-feedly-comment',
    ]) {
      const response = await guestPage.request.get(new URL(route, config.baseURL).toString());
      assert.equal(response.status(), 403, `Gäste dürfen ${route} nicht lesen.`);
      const body = await response.text();
      assert.equal(body.includes(protectedContent.unique), false, 'Die Antwort enthält keinen privaten Eintragstitel.');
      assert.equal(body.includes(protectedContent.commentText), false, 'Die Antwort enthält keine privaten Kommentardaten.');
    }
    await assertPrivateAssetDenied(guestPage.request, protectedContent.attachmentURL);
  } finally {
    await allowedContext.close();
    await guestContext.close();
    await browser.close();
  }
});

test('Zugriff im Browser: angemeldetes Mitglied ohne Feedly-Freigabe sieht keine Einträge oder Anhänge', {
  skip: missingAllowedConfig.length || missingDeniedConfig.length
    ? `E2E-Konfiguration fehlt: ${[...missingAllowedConfig, ...missingDeniedConfig].join(', ')}`
    : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const allowedContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const deniedContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const allowedPage = await allowedContext.newPage();
  const deniedPage = await deniedContext.newPage();

  try {
    const protectedContent = await createProtectedContent(allowedPage, expect);
    await signIn(deniedPage, config.deniedEmail, config.deniedPassword);
    await expect(deniedPage.locator('[data-kw-masha-feedly]')).toHaveCount(0, 'Ein angemeldetes, nicht freigegebenes Mitglied erhält kein Feedly-Widget.');

    for (const route of [
      '/__masha-feedly',
      '/__masha-feedly/listEntries?mode=all',
      '/__masha-feedly/savedViews',
      '/__masha-feedly-comment',
    ]) {
      const response = await deniedPage.request.get(new URL(route, config.baseURL).toString());
      assert.equal(response.status(), 403, `Nicht freigegebene Mitglieder dürfen ${route} nicht lesen.`);
      const body = await response.text();
      assert.equal(body.includes(protectedContent.unique), false, 'Die Antwort enthält keine privaten Eintragsdaten.');
      assert.equal(body.includes(protectedContent.commentText), false, 'Die Antwort enthält keine privaten Kommentardaten.');
    }
    await assertPrivateAssetDenied(deniedPage.request, protectedContent.attachmentURL);
  } finally {
    await allowedContext.close();
    await deniedContext.close();
    await browser.close();
  }
});
