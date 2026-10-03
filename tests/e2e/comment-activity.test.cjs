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
  creatorEmail: process.env.MASHA_FEEDLY_E2E_CREATOR_EMAIL || localEnv.MASHA_FEEDLY_E2E_CREATOR_EMAIL,
  creatorPassword: process.env.MASHA_FEEDLY_E2E_CREATOR_PASSWORD || localEnv.MASHA_FEEDLY_E2E_CREATOR_PASSWORD,
  commenterEmail: process.env.MASHA_FEEDLY_E2E_COMMENTER_EMAIL || localEnv.MASHA_FEEDLY_E2E_COMMENTER_EMAIL,
  commenterPassword: process.env.MASHA_FEEDLY_E2E_COMMENTER_PASSWORD || localEnv.MASHA_FEEDLY_E2E_COMMENTER_PASSWORD,
};
const missingConfig = Object.entries(config).filter(([, value]) => !value).map(([name]) => name);

/** Meldet ein Mitglied in einer unabhängigen Browser-Sitzung an. */
const signIn = async (page, baseURL, email, password) => {
  await page.goto(new URL('/Security/login', baseURL).toString());
  const emailField = page.locator('input[type="email"], input[name$="Email"], input[id$="Email"]').first();
  const passwordField = page.locator('input[type="password"]').first();
  await emailField.fill(email);
  await passwordField.fill(password);
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.goto(baseURL);
  await page.locator('[data-kw-masha-feedly]').waitFor({ state: 'attached' });
  const widget = page.locator('[data-kw-masha-feedly]');
  assert.notEqual(await widget.getAttribute('data-onboarding-enabled'), '1', 'Für die E2E-Testkonten muss die Einführung abgeschlossen sein.');
};

test('Kommentar eines zweiten Benutzers erscheint beim Ersteller in Neuigkeiten und wird beim Öffnen gelesen', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const creatorContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const commenterContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const creatorPage = await creatorContext.newPage();
  const commenterPage = await commenterContext.newPage();
  const unique = `E2E Kommentaraktivität ${new Date().toISOString()} ${Math.random().toString(36).slice(2, 8)}`;
  const commentText = `E2E Rückmeldung zu ${unique}`;

  try {
    await signIn(creatorPage, config.baseURL, config.creatorEmail, config.creatorPassword);
    await signIn(commenterPage, config.baseURL, config.commenterEmail, config.commenterPassword);

    const creatorToggle = creatorPage.locator('[data-kw-masha-feedly] .kw-masha-feedly__toggle');
    await creatorToggle.click();
    const creatorWidget = creatorPage.locator('[data-kw-masha-feedly]');
    await expect(creatorWidget.locator('[data-masha-feedly-open-news]')).toBeVisible();
    await creatorWidget.locator('[data-masha-feedly-start-selection]').click();
    await creatorPage.locator('main').first().click();

    const createForm = creatorWidget.locator('[data-masha-feedly-entry-form]');
    await expect(createForm).toBeVisible();
    await createForm.locator('[name="Content"]').fill(unique);
    await createForm.locator('[data-masha-feedly-emoji-toggle]').click();
    await createForm.locator('[data-emoji="😎"]').click();
    const createResponsePromise = creatorPage.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/createEntry'));
    await createForm.locator('[type="submit"]').click();
    const createResponse = await createResponsePromise;
    const created = await createResponse.json();
    assert.equal(createResponse.ok(), true, `Eintrag anlegen: ${created.message || createResponse.status()}`);
    assert.equal(created.success, true);
    assert.match(created.title, /😎/u, 'Das Emoji aus dem Eintragsformular muss gespeichert werden.');
    const entryID = Number(created.entryID);
    assert.ok(entryID > 0, 'Der Server muss die neue Eintrags-ID zurückgeben.');

    await creatorWidget.locator('[data-masha-feedly-open-list]').click();
    const ownCard = creatorWidget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${entryID}"]`);
    await expect(ownCard).toBeVisible();
    const baselineRead = await creatorPage.evaluate(async (id) => {
      const widget = document.querySelector('[data-kw-masha-feedly]');
      const body = new FormData();
      body.set('SecurityID', widget.dataset.securityId);
      body.set('EntryID', String(id));
      const response = await fetch(widget.dataset.markEntryReadUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, body,
      });
      return { ok: response.ok, result: await response.json() };
    }, entryID);
    assert.equal(baselineRead.ok, true, 'Der neu erstellte Eintrag wird vor dem Kommentar als gelesen markiert.');
    assert.equal(baselineRead.result.success, true);

    await commenterPage.goto(config.baseURL);
    const commenterWidget = commenterPage.locator('[data-kw-masha-feedly]');
    await commenterWidget.locator('.kw-masha-feedly__toggle').click();
    await commenterWidget.locator('[data-masha-feedly-open-list]').click();
    const commenterCard = commenterWidget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${entryID}"]`);
    await expect(commenterCard).toBeVisible();
    await expect(commenterCard).toHaveAttribute('data-entry-unread', 'true');
    const commenterUnreadCount = Number(await commenterWidget.locator('[data-masha-feedly-unread-count]').textContent());
    assert.ok(commenterUnreadCount > 0, 'Der neue Eintrag muss im Neuigkeiten-Zähler erscheinen.');
    await commenterCard.click();
    await expect(commenterCard).not.toHaveAttribute('data-entry-unread', 'true');
    await expect(commenterWidget.locator('[data-masha-feedly-unread-count]'))
      .toHaveText(String(commenterUnreadCount - 1));
    const commentForm = commenterWidget.locator('[data-masha-feedly-comment-form]');
    await expect(commentForm).toBeVisible();
    await commentForm.locator('[name="CommentText"]').fill(commentText);
    await commentForm.locator('[data-masha-feedly-emoji-toggle]').click();
    await commentForm.locator('[data-category="PEOPLE"]').click();
    await commentForm.locator('[data-emoji="👍"]').click();
    const commentResponsePromise = commenterPage.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/__masha-feedly-comment'));
    await commentForm.locator('[type="submit"]').click();
    const commentResponse = await commentResponsePromise;
    const savedComment = await commentResponse.json();
    assert.equal(commentResponse.ok(), true, `Kommentar speichern: ${savedComment.message || commentResponse.status()}`);
    assert.equal(savedComment.success, true);
    assert.match(savedComment.comment.text, /👍/u, 'Das Emoji aus dem Kommentarformular muss gespeichert werden.');

    await creatorPage.reload();
    const reloadedCreatorWidget = creatorPage.locator('[data-kw-masha-feedly]');
    await reloadedCreatorWidget.locator('.kw-masha-feedly__toggle').click();
    const newsButton = reloadedCreatorWidget.locator('[data-masha-feedly-open-news]');
    const unreadCount = newsButton.locator('[data-masha-feedly-unread-count]');
    await expect(unreadCount).not.toHaveText('0');
    const unreadBeforeOpen = Number(await unreadCount.textContent());
    await newsButton.click();
    const unreadCard = reloadedCreatorWidget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${entryID}"]`);
    await expect(unreadCard).toBeVisible();
    await expect(unreadCard.locator('[data-entry-unread]')).toBeVisible();
    await unreadCard.click();
    await expect(reloadedCreatorWidget.locator('[data-masha-feedly-comments]')).toContainText(commentText);
    await expect(unreadCount).toHaveText(String(unreadBeforeOpen - 1));
  } finally {
    await creatorContext.close();
    await commenterContext.close();
    await browser.close();
  }
});
