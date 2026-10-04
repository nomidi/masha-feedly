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
  onboardingEmail: process.env.MASHA_FEEDLY_E2E_ONBOARDING_EMAIL || localEnv.MASHA_FEEDLY_E2E_ONBOARDING_EMAIL,
  onboardingPassword: process.env.MASHA_FEEDLY_E2E_ONBOARDING_PASSWORD || localEnv.MASHA_FEEDLY_E2E_ONBOARDING_PASSWORD,
};
const missingConfig = Object.entries(config).filter(([, value]) => !value).map(([name]) => name);

test('führt das Onboarding aus der Hilfe durch Eintrag, Kommentar, Bearbeitung und Abschluss', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  const unique = `E2E Onboarding ${new Date().toISOString()} ${Math.random().toString(36).slice(2, 7)}`;
  const comment = `E2E Kommentar aus dem Onboarding zu ${unique}`;

  try {
    await page.goto(new URL('/Security/login', config.baseURL).toString());
    await page.locator('input[type="email"], input[name$="Email"], input[id$="Email"]').first().fill(config.onboardingEmail);
    await page.locator('input[type="password"]').first().fill(config.onboardingPassword);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.goto(config.baseURL);

    const widget = page.locator('[data-kw-masha-feedly]');
    await expect(widget).toBeAttached();
    // Das Testkonto muss in den Masha:Feedly-Einstellungen freigeschaltet sein.
    // Ein noch offener Erststartdialog wird geschlossen, bevor der Hilfeneustart getestet wird.
    const firstWelcome = widget.locator('[data-masha-feedly-onboarding-welcome]');
    if (await firstWelcome.isVisible()) await widget.locator('[data-masha-feedly-tour-skip]').click();
    const toggle = widget.locator('.kw-masha-feedly__toggle');
    if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
    await widget.locator('[data-masha-feedly-open-help]').click();
    const restartResponsePromise = page.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/restartOnboarding'));
    await widget.locator('[data-masha-feedly-restart-onboarding]').click();
    const restartResponse = await restartResponsePromise;
    const restartResult = await restartResponse.json();
    assert.equal(restartResponse.ok(), true, `Onboarding-Neustart: HTTP ${restartResponse.status()} (${restartResult.message || 'keine Servermeldung'})`);
    assert.equal(restartResult.success, true, 'Der Server muss den Onboarding-Neustart bestätigen.');
    await expect(widget.locator('[data-masha-feedly-onboarding-welcome]')).toBeVisible();
    await widget.locator('[data-masha-feedly-tour-start]').click();

    const tip = widget.locator('[data-masha-feedly-onboarding-text]');
    await expect(tip).toContainText('Schritt 1 von 8');
    await widget.locator('.kw-masha-feedly__toggle').click();
    await expect(tip).toContainText('Schritt 2 von 8');
    await widget.locator('[data-masha-feedly-start-selection]').click();
    await page.getByText('Home', { exact: true }).first().click();
    await expect(widget.locator('[data-masha-feedly-modal]')).toBeVisible();
    await expect(tip).toContainText('Schritt 4 von 8');

    const createForm = widget.locator('[data-masha-feedly-entry-form]');
    await createForm.locator('[name="Content"]').fill(unique);
    const saveEntry = widget.locator('[data-masha-feedly-modal] button[type="submit"][form="kw-masha-feedly-create-form"]');
    await expect(saveEntry).toBeEnabled();
    const createResponsePromise = page.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/createEntry'));
    await saveEntry.click();
    const createResponse = await createResponsePromise;
    const created = await createResponse.json();
    assert.equal(createResponse.ok(), true, `Eintrag speichern: ${created.message || createResponse.status()}`);
    assert.equal(created.success, true);
    assert.ok(Number(created.entryID) > 0, 'Der Server liefert eine Eintrags-ID zurück.');
    await expect(tip).toContainText('Schritt 5 von 8');

    await widget.locator('[data-masha-feedly-open-page-list]').click();
    await expect(tip).toContainText('Schritt 6 von 8');
    const card = widget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${created.entryID}"]`);
    await expect(card).toBeVisible();
    await expect(card).toContainText(unique);
    await card.click();
    await expect(widget.locator('[data-masha-feedly-edit-modal]')).toBeVisible();
    await expect(tip).toContainText('Schritt 7 von 8');

    const commentForm = widget.locator('[data-masha-feedly-comment-form]');
    await commentForm.locator('[name="CommentText"]').fill(comment);
    const commentResponsePromise = page.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/__masha-feedly-comment'));
    await commentForm.locator('[type="submit"]').click();
    const commentResponse = await commentResponsePromise;
    const savedComment = await commentResponse.json();
    assert.equal(commentResponse.ok(), true, `Kommentar speichern: ${savedComment.message || commentResponse.status()}`);
    assert.equal(savedComment.success, true);
    await expect(tip).toContainText('Schritt 8 von 8');
    await expect(widget.locator('.kw-masha-feedly__onboarding-escape-hint')).toContainText('Esc beendet die Einführung jederzeit');

    const editForm = widget.locator('[data-masha-feedly-edit-form]');
    const statusSelect = editForm.locator('[name="CategoryID"]');
    await expect(statusSelect).toBeEnabled();
    await expect(editForm.locator('[name="PriorityID"]')).toBeEnabled();
    await expect(editForm.locator('[name="DueDate"]')).toBeEnabled();
    const feedback = statusSelect.locator('option').filter({ hasText: /^Feedback$/ }).first();
    await statusSelect.selectOption(await feedback.getAttribute('value'));
    const assignee = editForm.locator('[name="AssignedMemberIDs[]"]').first();
    if (!(await assignee.isChecked())) await assignee.check();
    const updateResponsePromise = page.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/updateEntry'));
    await editForm.locator('[type="submit"]').click();
    const updateResponse = await updateResponsePromise;
    const updated = await updateResponse.json();
    assert.equal(updateResponse.ok(), true, `Änderungen speichern: ${updated.message || updateResponse.status()}`);
    assert.equal(updated.success, true);
    await expect(widget.locator('[data-masha-feedly-onboarding-thanks]')).toBeVisible();
    await expect(widget.locator('[data-masha-feedly-onboarding-thanks]')).toContainText('Danke fürs Mitmachen');
    await expect(widget.locator('[data-masha-feedly-thanks-close]')).toBeVisible();
  } finally {
    await context.close();
    await browser.close();
  }
});
