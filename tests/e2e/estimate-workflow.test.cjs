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
  memberEmail: process.env.MASHA_FEEDLY_E2E_CREATOR_EMAIL || localEnv.MASHA_FEEDLY_E2E_CREATOR_EMAIL,
  memberPassword: process.env.MASHA_FEEDLY_E2E_CREATOR_PASSWORD || localEnv.MASHA_FEEDLY_E2E_CREATOR_PASSWORD,
  // Der Kommentator übernimmt zugleich die Freigaberolle; so bleibt ein viertes Konto ausreichend.
  managerEmail: process.env.MASHA_FEEDLY_E2E_COMMENTER_EMAIL || localEnv.MASHA_FEEDLY_E2E_COMMENTER_EMAIL,
  managerPassword: process.env.MASHA_FEEDLY_E2E_COMMENTER_PASSWORD || localEnv.MASHA_FEEDLY_E2E_COMMENTER_PASSWORD,
  superadminEmail: process.env.MASHA_FEEDLY_E2E_SUPERADMIN_EMAIL || localEnv.MASHA_FEEDLY_E2E_SUPERADMIN_EMAIL,
  superadminPassword: process.env.MASHA_FEEDLY_E2E_SUPERADMIN_PASSWORD || localEnv.MASHA_FEEDLY_E2E_SUPERADMIN_PASSWORD,
};
const missingConfig = Object.entries(config).filter(([, value]) => !value).map(([name]) => name);

/** Meldet ein Mitglied an und wartet darauf, dass das Feedly-Widget bereit ist. */
const signIn = async (page, email, password) => {
  await page.goto(new URL('/Security/login', config.baseURL).toString());
  await page.locator('input[type="email"], input[name$="Email"], input[id$="Email"]').first().fill(email);
  await page.locator('input[type="password"]').first().fill(password);
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.goto(config.baseURL);
  const widget = page.locator('[data-kw-masha-feedly]');
  await widget.waitFor({ state: 'attached' });
  return widget;
};

const openEntryList = async (page, widget) => {
  const toggle = widget.locator('.kw-masha-feedly__toggle');
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  await widget.locator('[data-masha-feedly-open-page-list]').click();
};

test('Kostenschätzung: Berechtigung, Preisberechnung, Freigabe und Statuswechsel', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const memberContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const managerContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const superadminContext = await browser.newContext({ ignoreHTTPSErrors: true });
  const memberPage = await memberContext.newPage();
  const managerPage = await managerContext.newPage();
  const superadminPage = await superadminContext.newPage();
  const unique = `E2E Kostenschätzung ${new Date().toISOString()} ${Math.random().toString(36).slice(2, 7)}`;

  try {
    const memberWidget = await signIn(memberPage, config.memberEmail, config.memberPassword);
    assert.equal(await memberWidget.getAttribute('data-can-manage-estimate'), '0', 'Das normale Testmitglied darf keine Kostenschätzung verwalten.');
    const memberToggle = memberWidget.locator('.kw-masha-feedly__toggle');
    if ((await memberToggle.getAttribute('aria-expanded')) !== 'true') await memberToggle.click();
    await memberWidget.locator('[data-masha-feedly-start-selection]').click();
    await memberPage.locator('[role="main"]').first().click();

    const createForm = memberWidget.locator('[data-masha-feedly-entry-form]');
    await expect(createForm).toBeVisible();
    await expect(createForm.locator('[data-masha-feedly-create-estimate]')).toHaveCount(0);
    await createForm.locator('[name="Content"]').fill(unique);
    const saveEntry = memberWidget.locator('[data-masha-feedly-modal] button[type="submit"][form="kw-masha-feedly-create-form"]');
    const createResponsePromise = memberPage.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/createEntry'));
    await saveEntry.click();
    const createResponse = await createResponsePromise;
    const created = await createResponse.json();
    assert.equal(createResponse.ok(), true, `Eintrag anlegen: ${created.message || createResponse.status()}`);
    assert.equal(created.success, true);
    const entryID = Number(created.entryID);
    assert.ok(entryID > 0, 'Der Eintrag muss eine gültige ID erhalten.');

    const superadminWidget = await signIn(superadminPage, config.superadminEmail, config.superadminPassword);
    assert.equal(await superadminWidget.getAttribute('data-can-manage-estimate'), '1', 'Nur der konfigurierte Superadmin darf Kostenschätzungen bearbeiten.');
    const hourlyRateValue = (await superadminWidget.getAttribute('data-estimate-hourly-rate')) || '';
    const hourlyRate = Number(hourlyRateValue.replace(',', '.'));
    assert.ok(hourlyRate > 0, `Ein positiver Stundensatz muss hinterlegt und nur an den Superadmin ausgegeben werden (Wert: ${hourlyRateValue || 'leer'}).`);
    const managerWidget = await signIn(managerPage, config.managerEmail, config.managerPassword);
    assert.equal(await managerWidget.getAttribute('data-can-manage-estimate'), '0', 'Die Freigabeperson darf die Kostenschätzung nicht bearbeiten.');
    assert.equal(await managerWidget.getAttribute('data-can-approve-estimate'), '1', 'Das Commenter-Konto muss im Mitgliederprofil für Kostenschätzungsfreigaben freigeschaltet sein.');
    assert.equal(await managerWidget.getAttribute('data-estimate-hourly-rate'), '0', 'Der Stundensatz bleibt für die Freigabeperson verborgen.');
    await openEntryList(superadminPage, superadminWidget);
    const superadminCard = superadminWidget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${entryID}"]`);
    await expect(superadminCard).toBeVisible();
    await superadminCard.click();

    const editForm = superadminWidget.locator('[data-masha-feedly-edit-form]');
    await expect(editForm).toBeVisible();
    const estimate = editForm.locator('[data-masha-feedly-estimate]');
    await expect(estimate).toBeHidden();
    const categorySelect = editForm.locator('[name="CategoryID"]');
    const pendingOption = categorySelect.locator('option[data-system-key="estimate_pending"]');
    await categorySelect.selectOption(await pendingOption.getAttribute('value'));
    await expect(estimate).toBeVisible();
    const duration = editForm.locator('[name="EstimatedCostDuration"]');
    const note = editForm.locator('[name="EstimatedCostNote"]');
    await expect(duration).toBeEnabled();
    await duration.fill('2–4 Stunden');
    await note.fill('Durchgängiger Browser-Test der Preisberechnung');
    const preview = estimate.locator('[data-masha-feedly-estimate-price]');
    await expect(preview).toContainText(String(hourlyRate * 2));
    await expect(preview).toContainText(String(hourlyRate * 4));

    const pendingSavePromise = superadminPage.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/updateEntry'));
    await editForm.locator('[type="submit"]').click();
    const pendingResponse = await pendingSavePromise;
    const pendingResult = await pendingResponse.json();
    assert.equal(pendingResponse.ok(), true, `Kostenschätzung speichern: ${pendingResult.message || pendingResponse.status()}`);
    assert.equal(pendingResult.success, true);
    assert.equal(pendingResult.categoryRole, 'estimate_pending');
    assert.equal(pendingResult.estimateDuration, '2–4 Stunden');
    assert.equal(Number(pendingResult.estimateAmount), hourlyRate * 2);
    assert.equal(Number(pendingResult.estimateAmountMax), hourlyRate * 4);
    assert.equal(pendingResult.estimateNote, 'Durchgängiger Browser-Test der Preisberechnung');
    assert.ok(Array.isArray(pendingResult.history), 'Die gespeicherte Schätzung muss im Verlauf zurückkommen.');
    const estimateEvent = pendingResult.history.find((item) => item.type === 'estimate');
    assert.ok(estimateEvent, 'Die Änderung der Kostenschätzung muss protokolliert werden.');
    assert.match(estimateEvent.newValue, /2–4 Stunden/u);
    assert.match(estimateEvent.newValue, /Durchgängiger Browser-Test der Preisberechnung/u);
    const expectedPrice = `${(hourlyRate * 2).toFixed(2).replace('.', ',')} €`;
    assert.ok(estimateEvent.newValue.includes(expectedPrice), `Der Verlauf muss den berechneten Mindestpreis ${expectedPrice} enthalten.`);
    const pendingTitle = await pendingOption.textContent();
    assert.ok(pendingResult.history.some((item) => item.type === 'status' && item.newValue === pendingTitle), 'Das Anfordern der Kostenschätzung muss als Statuswechsel protokolliert sein.');

    await openEntryList(managerPage, managerWidget);
    const reviewerCard = managerWidget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${entryID}"]`);
    await expect(reviewerCard).toBeVisible();
    await expect(reviewerCard.locator('.kw-masha-feedly__entry-estimate-status')).toHaveAttribute('aria-label', /Kostenschätzung wartet auf Freigabe/u);
    await reviewerCard.click();
    const reviewerForm = managerWidget.locator('[data-masha-feedly-edit-form]');
    const reviewerEstimate = reviewerForm.locator('[data-masha-feedly-estimate]');
    await expect(reviewerEstimate).toBeVisible();
    await expect(reviewerForm.locator('[name="EstimatedCostDuration"], [name="EstimatedCostNote"]')).toHaveCount(0, 'Freigabeperson sieht keinen Bearbeitungsdialog.');
    const estimateReadonly = reviewerEstimate.locator('[data-masha-feedly-estimate-readonly]');
    await expect(estimateReadonly).toBeVisible();
    await expect(estimateReadonly.locator('[data-masha-feedly-estimate-duration]')).toHaveText('2–4 Stunden');
    await expect(estimateReadonly.locator('[data-masha-feedly-estimate-note]')).toHaveText('Durchgängiger Browser-Test der Preisberechnung');
    await expect(estimateReadonly.locator('[data-masha-feedly-estimate-amount]')).toContainText(`${hourlyRate * 2}`);
    const reviewerCategory = reviewerForm.locator('[name="CategoryID"]');
    const approvedOption = reviewerCategory.locator('option[data-system-key="estimate_approved"]');
    await reviewerCategory.selectOption(await approvedOption.getAttribute('value'));
    const approvePromise = managerPage.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/updateEntry'));
    await reviewerForm.locator('[type="submit"]').click();
    const approvalResponse = await approvePromise;
    const approvalResult = await approvalResponse.json();
    assert.equal(approvalResponse.ok(), true, `Kostenschätzung freigeben: ${approvalResult.message || approvalResponse.status()}`);
    assert.equal(approvalResult.success, true);
    assert.equal(approvalResult.categoryRole, 'estimate_approved');
    assert.equal(approvalResult.canManageEstimate, false);
    assert.equal(approvalResult.estimateNote, 'Durchgängiger Browser-Test der Preisberechnung');
    const approvedTitle = await approvedOption.textContent();
    assert.ok(approvalResult.history.some((item) => item.type === 'status' && item.newValue === approvedTitle), 'Die Freigabe muss im Verlauf sichtbar sein.');

    await memberPage.reload();
    const refreshedMemberWidget = memberPage.locator('[data-kw-masha-feedly]');
    await refreshedMemberWidget.waitFor({ state: 'attached' });
    await openEntryList(memberPage, refreshedMemberWidget);
    const listResponsePromise = memberPage.waitForResponse((response) =>
      new URL(response.url()).pathname.endsWith('/listEntries') && response.request().method() === 'GET');
    await refreshedMemberWidget.locator('[data-masha-feedly-open-page-list]').click();
    const listResponse = await listResponsePromise;
    const listResult = await listResponse.json();
    assert.equal(listResult.canManageEstimate, false);
    const protectedEntry = listResult.entries.find((entry) => Number(entry.id) === entryID);
    assert.ok(protectedEntry, 'Das normale Mitglied sieht seinen Eintrag weiterhin.');
    assert.equal(protectedEntry.categoryRole, 'restricted_estimate');
    assert.equal(protectedEntry.categoryTitle, approvedTitle, 'Das normale Mitglied sieht den tatsächlichen Status am Eintrag.');
    for (const field of ['estimateAmount', 'estimateAmountMax', 'estimateDuration', 'estimateCurrency', 'estimateNote']) {
      assert.equal(Object.hasOwn(protectedEntry, field), false, `Das normale Mitglied darf ${field} nicht aus dem Server-Payload erhalten.`);
    }
    const memberCard = refreshedMemberWidget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${entryID}"]`);
    await expect(memberCard).toBeVisible();
    await expect(memberCard).toContainText(approvedTitle);
    await memberCard.click();
    const memberEditForm = refreshedMemberWidget.locator('[data-masha-feedly-edit-form]');
    await expect(memberEditForm.locator('[data-masha-feedly-estimate]')).toHaveCount(0);
    const memberCategorySelect = memberEditForm.locator('[name="CategoryID"]');
    await expect(memberCategorySelect.locator('option[data-system-key="estimate_pending"]')).toHaveCount(0);
    await expect(memberCategorySelect.locator('option[data-system-key="estimate_approved"]')).toHaveCount(0);
    await expect(memberCategorySelect.locator('option').filter({ hasText: approvedTitle })).toHaveCount(0, 'Der vertrauliche Statusname erscheint nicht als auswählbare Kategorie.');

    // Der Superadmin hat die Freigabe in einer anderen Sitzung erhalten. Lade den
    // Eintrag neu, damit die folgende Statusänderung auf dem bestätigten Stand basiert.
    await superadminPage.reload();
    const refreshedSuperadminWidget = superadminPage.locator('[data-kw-masha-feedly]');
    await refreshedSuperadminWidget.waitFor({ state: 'attached' });
    await openEntryList(superadminPage, refreshedSuperadminWidget);
    const refreshedSuperadminCard = refreshedSuperadminWidget.locator(`[data-masha-feedly-entries-list] [data-entry-id="${entryID}"]`);
    await expect(refreshedSuperadminCard).toBeVisible();
    await refreshedSuperadminCard.click();

    const doingOption = categorySelect.locator('option[data-system-key="doing"]');
    await categorySelect.selectOption(await doingOption.getAttribute('value'));
    const statusSavePromise = superadminPage.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/updateEntry'));
    await editForm.locator('[type="submit"]').click();
    const statusResponse = await statusSavePromise;
    const statusResult = await statusResponse.json();
    assert.equal(statusResponse.ok(), true, `Status nach Freigabe ändern: ${statusResult.message || statusResponse.status()}`);
    assert.equal(statusResult.success, true);
    assert.equal(statusResult.categoryRole, 'doing');
    const doingTitle = await doingOption.textContent();
    assert.ok(statusResult.history.some((item) => item.type === 'status' && item.newValue === doingTitle), 'Der Statuswechsel nach Freigabe muss ebenfalls protokolliert werden.');
  } finally {
    await memberContext.close();
    await managerContext.close();
    await superadminContext.close();
    await browser.close();
  }
});
