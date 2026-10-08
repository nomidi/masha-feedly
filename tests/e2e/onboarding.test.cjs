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
    await expect(firstWelcome.locator('[data-masha-feedly-profile-link]')).toHaveCount(0);
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
    await page.locator('[role="main"]').first().click();
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
    const assigneeChoice = editForm.locator('.kw-masha-feedly__assignee-choice').first();
    const assigneeName = assigneeChoice.locator('.kw-masha-feedly__assignee-name');
    await assigneeChoice.hover();
    await expect(assigneeName).toHaveCSS('clip-path', 'none');
    await expect(assigneeName).toHaveCSS('font-size', '12px');
    await expect(assigneeName).toHaveCSS('color', 'rgb(255, 255, 255)');
    const assignee = editForm.locator('[name="AssignedMemberIDs[]"]').first();
    await assignee.focus();
    await page.mouse.move(0, 0);
    await expect(assigneeName).toHaveCSS('clip-path', 'none');
    if (!(await assignee.isChecked())) await assignee.locator('xpath=..').click();
    await expect(assignee).toBeChecked();
    const updateResponsePromise = page.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/updateEntry'));
    await editForm.locator('[type="submit"]').click();
    const updateResponse = await updateResponsePromise;
    const updated = await updateResponse.json();
    assert.equal(updateResponse.ok(), true, `Änderungen speichern: ${updated.message || updateResponse.status()}`);
    assert.equal(updated.success, true);
    await expect(widget.locator('[data-masha-feedly-onboarding-thanks]')).toBeVisible();
    await expect(widget.locator('[data-masha-feedly-onboarding-thanks]')).toContainText('Danke fürs Mitmachen');
    const preferences = widget.locator('[data-masha-feedly-profile-preferences]');
    await expect(preferences).toBeVisible();
    const preferenceSectionHeights = await preferences.locator('details').evaluateAll((sections) => sections.map((section) => ({
      name: section.querySelector('summary')?.textContent.trim(),
      sectionHeight: section.getBoundingClientRect().height,
      summaryHeight: section.querySelector('summary')?.getBoundingClientRect().height || 0,
    })));
    for (const section of preferenceSectionHeights) {
      assert.ok(section.sectionHeight > section.summaryHeight + 2, `Der geöffnete Einstellungsbereich „${section.name}“ muss seinen Inhalt vollständig anzeigen.`);
    }
    const preferenceLayout = await preferences.evaluate((form) => {
      const sections = [...form.children].filter((element) => element.matches('section, details, label'));
      return {
        sections: sections.map((section) => ({
          name: section.querySelector('h3, summary')?.textContent.trim() || section.textContent.trim(),
          top: section.getBoundingClientRect().top,
          bottom: section.getBoundingClientRect().bottom,
        })),
        addressHeight: form.querySelector('[name="MashaFeedlyAddress"]').getBoundingClientRect().height,
        addressWidth: form.querySelector('[name="MashaFeedlyAddress"]').getBoundingClientRect().width,
      };
    });
    for (let index = 1; index < preferenceLayout.sections.length; index += 1) {
      const previous = preferenceLayout.sections[index - 1];
      const current = preferenceLayout.sections[index];
      assert.ok(current.top >= previous.bottom + 8, `„${current.name}“ darf den vorherigen Einstellungsbereich nicht überlagern.`);
    }
    assert.ok(preferenceLayout.addressHeight <= 44, 'Die Anrede-Auswahl muss im Abschlussdialog kompakt bleiben.');
    assert.ok(preferenceLayout.addressWidth <= 190, 'Die Anrede-Auswahl darf nicht die gesamte Formularbreite einnehmen.');
    const avatarPreview = preferences.locator('[data-masha-feedly-avatar-preview]');
    await expect(avatarPreview).toBeVisible();
    const themeSelect = preferences.locator('[name="MashaFeedlyTheme"]');
    const currentTheme = await themeSelect.inputValue();
    const availableThemes = await themeSelect.locator('option').evaluateAll((options) => options.map((option) => option.value));
    const nextTheme = availableThemes.find((theme) => theme !== '' && theme !== currentTheme);
    assert.ok(availableThemes.length > 1, 'Auch nach Neustart aus der Hilfe müssen persönliche Effekt-Kategorien auswählbar sein.');
    if (nextTheme !== undefined) await themeSelect.selectOption(nextTheme);

    const colorField = preferences.locator('[name="MashaFeedlyColor"]');
    const currentColor = await colorField.inputValue();
    const colorDetails = preferences.locator('[data-masha-feedly-onboarding-colors]');
    await expect(colorDetails).toHaveAttribute('open', '');
    const colorChoices = await preferences.locator('[data-masha-feedly-color-option]').evaluateAll((options) => options.map((option, index) => ({ color: option.dataset.color, index })));
    assert.equal(colorChoices.filter(choice => choice.color).length, 18, 'Der Abschlussdialog muss auch beim Neustart die vollständige Farbpalette enthalten.');
    const nextColor = colorChoices.find((choice) => choice.color && choice.color !== currentColor);
    if (nextColor) {
      const colorOption = preferences.locator('[data-masha-feedly-color-option]').nth(nextColor.index);
      await colorOption.evaluate((element) => element.click());
    }
    if (nextColor) {
      const channels = nextColor.color.match(/[\da-f]{2}/gi).map((channel) => Number.parseInt(channel, 16));
      await expect(avatarPreview).toHaveCSS('background-color', `rgb(${channels.join(', ')})`);
      await expect(preferences.locator('[data-masha-feedly-color-preview]')).toHaveCSS('background-color', `rgb(${channels.join(', ')})`);
    }
    const emailMaster = preferences.locator('[data-masha-feedly-email-master]');
    if (await emailMaster.count()) {
      await expect(emailMaster).toBeEnabled();
      await expect(preferences.locator('[data-masha-feedly-email-option]')).toHaveCount(6);
    }
    const iconResponse = await context.request.get(new URL('/__masha-feedly-effects/icons', config.baseURL).href);
    if (iconResponse.ok()) {
      const iconCatalogue = await iconResponse.json();
      if (iconCatalogue.icons?.length) {
        await expect(preferences.locator('[data-masha-feedly-avatar-icon-open]')).toBeVisible();
        await preferences.locator('[data-masha-feedly-avatar-icon-open]').click();
        await expect(preferences.locator('[data-masha-feedly-avatar-icon-dialog]')).toBeVisible();
        const icon = preferences.locator('[data-masha-feedly-avatar-icons] [role="tabpanel"]:visible img').first();
        await expect.poll(() => icon.evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
        const channels = colorField.inputValue().then((value) => value.match(/[\da-f]{2}/gi).map((channel) => Number.parseInt(channel, 16)));
        const [red, green, blue] = await channels;
        const expectedIconVariant = ['#35A98F', '#69B85A'].includes((await colorField.inputValue()).toUpperCase())
          || 0.2126 * red / 255 + 0.7152 * green / 255 + 0.0722 * blue / 255 <= 0.52
          ? 'white' : 'black';
        await expect(icon).toHaveAttribute('src', new RegExp(`/${expectedIconVariant}$`));
        await expect(preferences.locator('[data-masha-feedly-avatar-icons]')).toHaveCSS('--masha-avatar-color', await colorField.inputValue());
        const selectedIcon = preferences.locator('[data-masha-feedly-avatar-icon-choice][aria-pressed="false"]').first();
        const selectedIconID = await selectedIcon.getAttribute('data-icon-id');
        await selectedIcon.click();
        await expect(avatarPreview.locator('img')).toHaveAttribute('src', new RegExp(`/icon/${selectedIconID}/[^/]+/${expectedIconVariant}$`));
        await expect.poll(() => avatarPreview.locator('img').evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
        await expect(avatarPreview.locator('img')).toHaveCSS('padding', '12px');
        const liveIconSize = await avatarPreview.locator('img').boundingBox();
        assert.ok(liveIconSize.width <= 48 && liveIconSize.height <= 48, 'Ein neu gewähltes Symbol muss in der kompakten Vorschau bleiben.');
        await expect(preferences.locator('[name="MashaFeedlyAvatarIcon"]')).toHaveValue(selectedIconID);
      }
    }

    const soundChoice = preferences.locator('[name="MashaFeedlyDisableSoundEffects"]');
    await expect(soundChoice).toBeVisible();
    const originalSoundChoice = await soundChoice.isChecked();
    await soundChoice.check();
    assert.equal(await page.evaluate(() => window.KWMashaFeedlyEffects.soundDisabled()), true);
    await soundChoice.uncheck();
    assert.equal(await page.evaluate(() => window.KWMashaFeedlyEffects.soundDisabled()), false);
    await soundChoice.setChecked(originalSoundChoice);

    const profileResponsePromise = page.waitForResponse((response) =>
      response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/saveProfilePreferences'));
    await widget.locator('[data-masha-feedly-onboarding-save]').click();
    const profileResponse = await profileResponsePromise;
    const profileBody = await profileResponse.text();
    let profileResult;
    try {
      profileResult = JSON.parse(profileBody);
    } catch {
      assert.fail(`Profileinstellungen speichern: HTTP ${profileResponse.status()} (${profileBody.slice(0, 500)})`);
    }
    assert.equal(profileResponse.ok(), true, `Profileinstellungen speichern: ${profileResult.message || profileResponse.status()}`);
    assert.equal(profileResult.success, true, 'Der Server muss die Profileinstellungen bestätigen.');
    assert.equal(profileResult.disableSoundEffects, originalSoundChoice);
    assert.equal(profileResult.theme, nextTheme || currentTheme || 'playful');
    if (nextColor) assert.equal(await colorField.inputValue(), nextColor.color);
    await expect(preferences.locator('[data-masha-feedly-profile-preferences-status]')).toContainText('gespeichert');
    await expect(widget.locator('[data-masha-feedly-thanks-close]')).toBeVisible();
    const profileLink = widget.locator('[data-masha-feedly-onboarding-thanks] [data-masha-feedly-profile-link]');
    await expect(profileLink).toContainText('Profileinstellungen öffnen');
    await profileLink.click();
    await page.waitForURL((url) => /myprofile\/?$/.test(url.pathname) && url.hash === '#Root_MashaFeedly');
    await expect(page).toHaveURL(/myprofile\/?#Root_MashaFeedly$/);
    const profileForm = page.locator('form').filter({ has: page.locator('[name="MashaFeedlyColor"]') }).first();
    const profilePreview = profileForm.locator('[data-masha-feedly-avatar-preview]');
    await expect(profilePreview).toBeVisible();
    const profileColorOption = profileForm.locator('[data-masha-feedly-color-option][data-color]:not([data-color=""])').first();
    const profileNextColor = await profileColorOption.getAttribute('data-color');
    if (await profileColorOption.isEnabled()) {
      await profileColorOption.click();
      const profileColorChannels = profileNextColor.match(/[\da-f]{2}/gi).map((channel) => Number.parseInt(channel, 16));
      await expect(profilePreview).toHaveCSS('background-color', `rgb(${profileColorChannels.join(', ')})`);
    }
    const profileIconOpen = profileForm.locator('[data-masha-feedly-avatar-icon-open]');
    if (await profileIconOpen.count() && await profileIconOpen.isEnabled()) {
      await profileIconOpen.click();
      const profileIconChoice = profileForm.locator('[data-masha-feedly-avatar-icon-choice][aria-pressed="false"]').first();
      const profileIconID = await profileIconChoice.getAttribute('data-icon-id');
      const profileIconVariant = await profileForm.locator('[data-masha-feedly-avatar-icons]').getAttribute('data-icon-color');
      await profileIconChoice.click();
      await expect(profilePreview.locator('img')).toHaveAttribute('src', new RegExp(`/icon/${profileIconID}/[^/]+/${profileIconVariant}$`));
      await expect(profilePreview.locator('img')).toHaveCSS('padding', '12px');
      const profileIconSize = await profilePreview.locator('img').boundingBox();
      assert.ok(profileIconSize.width <= 76 && profileIconSize.height <= 76, 'Die Live-Vorschau im Profil darf das Symbol nicht vergrößern.');
      await expect(profileForm.locator('[name="MashaFeedlyAvatarIcon"]')).toHaveValue(profileIconID);
    }

  } finally {
    await context.close();
    await browser.close();
  }
});

/** Prüft dieselben mobilen Abläufe in Chromium, Firefox und der Safari-Engine WebKit. */
for (const browserName of ['chromium', 'firefox', 'webkit']) {
  test(`mobile Startansicht (${browserName}) zeigt Feedly-Lasche, Seitenpanel, Hilfe und beide Onboarding-Auswege`, {
    skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
  }, async () => {
    const playwright = require('@playwright/test');
    const { expect } = playwright;
    const browser = await playwright[browserName].launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    try {
      // Die Einführung wird nur im Browser aktiviert; das echte Benutzerprofil bleibt erhalten.
      await page.addInitScript(() => {
        window.mobileTourRequests = [];

        const originalFetch = window.fetch.bind(window);
        window.fetch = (input, init) => {
          if (String(input).includes('restartOnboarding')) return Promise.resolve(new Response(JSON.stringify({ success: true }), { status: 200 }));
          if (String(input).includes('completeOnboarding')) {
            window.mobileTourRequests.push(Object.fromEntries(init.body.entries()));
            return Promise.resolve(new Response(JSON.stringify({ success: true }), { status: 200 }));
          }
          return originalFetch(input, init);
        };
      });
      await page.goto(new URL('/Security/login', config.baseURL).href);
      await page.locator('input[type="email"], input[name$="Email"], input[id$="Email"]').first().fill(config.onboardingEmail);
      await page.locator('input[type="password"]').first().fill(config.onboardingPassword);
      await page.locator('button[type="submit"], input[type="submit"]').first().click();
      await page.goto(config.baseURL);
      const widget = page.locator('[data-kw-masha-feedly]');
      const welcome = widget.locator('[data-masha-feedly-onboarding-welcome]');
      const openTour = async (width, height) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await widget.locator('.kw-masha-feedly__toggle').click();
        await widget.locator('[data-masha-feedly-open-help]').click();
        await widget.locator('[data-masha-feedly-restart-onboarding]').click();
        await expect(welcome).toBeVisible();
        await page.setViewportSize({ width, height });
      };
      await openTour(390, 844);
      await expect(welcome).toBeVisible();
      await expect(welcome.locator('.kw-masha-feedly__onboarding-mobile-notice')).toContainText('größeren Bildschirm');
      await expect(welcome.locator('[data-masha-feedly-tour-start]')).toBeHidden();
      await expect(welcome.locator('[data-masha-feedly-tour-skip]')).toBeHidden();
      await expect(welcome.locator('button:visible')).toHaveCount(2);
      await expect(welcome.locator('[data-masha-feedly-tour-end]')).toHaveText('Einführung abbrechen', { useInnerText: true });
      await expect(welcome.locator('[data-masha-feedly-tour-mobile-close]')).toHaveText('OK');
      await welcome.locator('[data-masha-feedly-tour-mobile-close]').click();
      await expect(welcome).toBeHidden();
      assert.equal((await page.evaluate(() => window.mobileTourRequests))[0].Deferred, '1');

      const toggle = widget.locator('.kw-masha-feedly__toggle');
      await expect(toggle.locator('img')).toBeVisible();
      const toggleBox = await toggle.boundingBox();
      assert.ok(toggleBox.y > 700, 'Feedly-Lasche sitzt unten, nicht in der Bildschirmmitte.');
      assert.equal(Math.round(toggleBox.x + toggleBox.width), 390);
      const panel = widget.locator('.kw-masha-feedly__panel');
      if (await panel.isVisible()) await panel.locator('.kw-masha-feedly__close').click();
      await toggle.click();
      await expect(panel).toBeVisible();
      const panelBox = await panel.boundingBox();
      assert.equal(Math.round(panelBox.y), 0);
      assert.equal(Math.round(panelBox.height), 844);
      assert.equal(Math.round(panelBox.x + panelBox.width), 390);
      assert.ok(panelBox.width < 100, 'Das erste Panel bleibt dieselbe schmale Seitenleiste.');
      const content = panel.locator('.kw-masha-feedly__content');
      await expect(content.locator('button:visible')).toHaveCount(2);
      await expect(content.locator('[data-masha-feedly-start-selection]')).toBeVisible();
      await content.locator('[data-masha-feedly-open-help]').click();
      await expect(widget.locator('.kw-masha-feedly__help-mobile')).toBeVisible();
      await expect(widget.locator('.kw-masha-feedly__help-mobile')).toContainText('unten rechts');
      await expect(widget.locator('.kw-masha-feedly__help-mobile')).toContainText('Alle Werkzeuge');
      await expect(widget.locator('.kw-masha-feedly__help-mobile')).toContainText('Laptop oder Desktop-Computer');
      await expect(widget.locator('.kw-masha-feedly__help-content')).toBeHidden();
      // Kleine Hoch- und Querformatfenster müssen bis zum letzten Hinweis scrollen können.
      for (const viewport of [{ width: 320, height: 400 }, { width: 390, height: 280 }]) {
        await page.setViewportSize(viewport);
        const mobileHelp = widget.locator('.kw-masha-feedly__help-mobile');
        const helpHeader = widget.locator('[data-masha-feedly-help-modal] .kw-masha-feedly__dialog-header');
        const beforeScroll = await helpHeader.boundingBox();
        const scrollState = await mobileHelp.evaluate((element) => {
          element.scrollTop = element.scrollHeight;
          return { scrollTop: element.scrollTop, clientHeight: element.clientHeight, scrollHeight: element.scrollHeight };
        });
        assert.ok(scrollState.clientHeight > 0 && scrollState.scrollHeight > scrollState.clientHeight);
        assert.ok(scrollState.scrollTop > 0, 'Der Hilfetext lässt sich bis zum Ende scrollen.');
        const afterScroll = await helpHeader.boundingBox();
        assert.equal(afterScroll.y, beforeScroll.y, 'Der Kopf bleibt beim Scrollen stehen.');
        const closeBox = await widget.locator('[data-masha-feedly-close-help]').boundingBox();
        assert.ok(closeBox.y >= 0 && closeBox.y + closeBox.height <= viewport.height, 'Schließen bleibt im Fenster erreichbar.');
      }
      await widget.locator('[data-masha-feedly-close-help]').click();
      await page.setViewportSize({ width: 390, height: 844 });
      await content.locator('[data-masha-feedly-start-selection]').click();
      await page.locator('[role="main"]').first().click();
      const form = widget.locator('[data-masha-feedly-entry-form]');
      await expect(form).toBeVisible();
      const hintSize = await form.locator('.kw-masha-feedly__screenshot small').evaluate((element) => parseFloat(getComputedStyle(element).fontSize));
      assert.ok(hintSize >= 16, 'Screenshot-Hinweis bleibt mindestens 16 px groß.');

      await page.reload();
      await openTour(390, 844);
      await expect(welcome).toBeVisible();
      await welcome.locator('[data-masha-feedly-tour-end]').click();
      await expect(welcome).toBeHidden();
      assert.equal((await page.evaluate(() => window.mobileTourRequests))[0].Deferred, undefined);

      await page.setViewportSize({ width: 1280, height: 900 });
      await page.reload();
      await openTour(1280, 900);
      await expect(welcome.locator('[data-masha-feedly-tour-start]')).toBeVisible();
      await expect(welcome.locator('[data-masha-feedly-tour-mobile-close]')).toBeHidden();
      await expect(welcome.locator('.kw-masha-feedly__onboarding-mobile-notice')).toBeHidden();
    } finally {
      await context.close();
      await browser.close();
    }
  });
}
