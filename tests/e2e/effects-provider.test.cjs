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

/** Prüft die CMS-Farbänderung neben dem gleichnamigen Widget-Feld und stellt die ursprüngliche Farbe wieder her. */
test('Profil: Avatarfarbe und stumme CMS-Vorschau bleiben nach dem Speichern erhalten', { skip: missing.length ? `E2E-Konfiguration fehlt: ${missing.join(', ')}` : false }, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  const profileURL = new URL('/admin/myprofile#Root_MashaFeedly', config.BASE_URL).href;
  let originalColor;
  let originalSoundChoice;
  let changed = false;
  const saveColor = async (color) => {
    const appearance = page.locator('.masha-feedly-profile-appearance');
    await appearance.locator(`[data-masha-feedly-color-option][data-color="${color}"]`).click();
    await expect(appearance.locator('[name="MashaFeedlyColor"]')).toHaveValue(color);
    await Promise.all([
      page.waitForResponse(response => response.request().method() === 'POST', { timeout: 10000 }),
      page.locator('[name="action_save"]').click({ timeout: 10000 }),
    ]);
    await page.goto(profileURL);
    await expect(page.locator('.masha-feedly-profile-appearance [name="MashaFeedlyColor"]')).toHaveValue(color);
    const preview = page.locator('.masha-feedly-profile-settings [data-masha-feedly-avatar-preview]');
    const channels = color.slice(1).match(/.{2}/g).map(channel => parseInt(channel, 16));
    await expect(preview).toHaveCSS('background-color', `rgb(${channels.join(', ')})`);
    const iconID = await preview.getAttribute('data-icon-id');
    if (iconID) {
      const luminance = (0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]) / 255;
      const variant = ['#35A98F', '#69B85A'].includes(color.toUpperCase()) || luminance <= 0.52 ? 'white' : 'black';
      await expect(preview.locator('img')).toHaveAttribute('src', new RegExp(`/avatar/${iconID}/${variant}$`));
      await expect.poll(() => preview.locator('img').evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
    }
  };
  try {
    await page.addInitScript(() => {
      window.effectAudioStarts = 0;
      const audioContext = window.AudioContext || window.webkitAudioContext;
      if (audioContext) {
        const createOscillator = audioContext.prototype.createOscillator;
        audioContext.prototype.createOscillator = function (...args) {
          window.effectAudioStarts++;
          return createOscillator.apply(this, args);
        };
      }
    });
    await page.goto(new URL('/Security/login', config.BASE_URL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.SUPERADMIN_EMAIL);
    await page.locator('input[type="password"]').first().fill(config.SUPERADMIN_PASSWORD);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.goto(profileURL);
    const welcomeDialog = page.locator('[data-masha-feedly-onboarding-welcome]');
    if (await welcomeDialog.isVisible()) {
      await welcomeDialog.locator('[data-masha-feedly-tour-skip]').click();
      await expect(welcomeDialog).toBeHidden();
    }
    const appearance = page.locator('.masha-feedly-profile-appearance');
    await expect(appearance).toBeVisible();
    const lockedColor = appearance.locator('[data-masha-feedly-color-option]').first();
    await expect(lockedColor).toBeDisabled();
    await lockedColor.hover({ force: true });
    await expect(lockedColor).toHaveCSS('transform', 'none');
    const profileIcons = page.locator('.masha-feedly-profile-settings [data-masha-feedly-avatar-icons]');
    await expect(profileIcons).toHaveAttribute('inert', '');
    await expect(profileIcons.locator('[data-masha-feedly-avatar-icon-open]')).toBeDisabled();
    const verification = page.locator('.sudo-mode-password-field__notice-button');
    await expect(verification).toBeVisible();
    await verification.click();
    await page.locator('[name="SudoModePassword"]').fill(config.SUPERADMIN_PASSWORD);
    await page.locator('.sudo-mode-password-field__verify-button').click();
    await expect(page.locator('[name="action_save"]')).toBeEnabled();
    await expect(appearance.locator('[data-masha-feedly-color-option]').first()).toBeEnabled();
    await expect(profileIcons).not.toHaveAttribute('inert', '');
    const soundChoice = appearance.locator('input[name="MashaFeedlyDisableSoundEffects"]');
    await expect(soundChoice).toBeVisible();
    originalSoundChoice = await soundChoice.isChecked();
    await soundChoice.check();
    assert.equal(await page.evaluate(() => window.KWMashaFeedlyEffects.soundDisabled()), true);
    await soundChoice.uncheck();
    assert.equal(await page.evaluate(() => window.KWMashaFeedlyEffects.soundDisabled()), false);
    await soundChoice.check();
    originalColor = await appearance.locator('[name="MashaFeedlyColor"]').inputValue();
    const selectedColor = originalColor === '#6383D8' ? '#35A98F' : '#6383D8';
    changed = true;
    await saveColor(selectedColor);
    await expect(appearance.locator('input[name="MashaFeedlyDisableSoundEffects"]')).toBeChecked();
    await expect(page.locator(`.masha-feedly-profile-appearance [data-color="${selectedColor}"]`)).toHaveAttribute('aria-pressed', 'true');
    // Regression: Auch die Konfigurationsvorschau muss nach dem Profil-Speichern stumm bleiben.
    await page.goto(new URL('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig', config.BASE_URL).href);
    assert.equal(await page.evaluate(() => window.KWMashaFeedlyDisableSoundEffects), true);
    assert.equal(await page.evaluate(() => window.KWMashaFeedlyEffects.soundDisabled()), true);
    await page.locator('[data-masha-feedly-animation-preview="arcade"]').click();
    await expect(page.locator('.kw-masha-feedly__arcade-effect')).toBeVisible();
    assert.equal(await page.evaluate(() => window.effectAudioStarts), 0, 'Die Animation erscheint ohne Tonerzeugung. Anbieter beachtet muted: ' + await page.evaluate(() => window.KWMashaFeedlyEffectModules.arcade.play.toString().includes('muted')));
    await page.evaluate(() => window.KWMashaFeedlyEffects.cancelActive());
  } finally {
    try { if (changed) { await page.goto(profileURL); await page.locator('.masha-feedly-profile-appearance input[name="MashaFeedlyDisableSoundEffects"]').setChecked(originalSoundChoice); await saveColor(originalColor); } }
    finally { await context.close(); await browser.close(); }
  }
});
/** Prüft den Feedly-Proxy zu einem externen Effekt-Anbieter und die Wiedergabe im Widget. */
test('Effekt-Anbieter: Feedly-Proxy lädt den externen Katalog und spielt im Shadow Root', { skip: missing.length ? `E2E-Konfiguration fehlt: ${missing.join(', ')}` : false }, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    await page.goto(new URL('/Security/login', config.BASE_URL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.SUPERADMIN_EMAIL);
    await page.locator('input[type="password"]').first().fill(config.SUPERADMIN_PASSWORD);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.goto(config.BASE_URL);
    await page.locator('[data-kw-masha-feedly]').waitFor({ state: 'attached' });
    const proxyURL = await page.evaluate(() => window.KWMashaFeedlyEffectsManifestURL);
    const proxyResponse = await context.request.get(proxyURL);
    assert.equal(proxyResponse.status(), 200, `Proxy-Status ${proxyResponse.status()} für ${proxyURL}`);
    const iconResponse = await context.request.get(new URL('/__masha-feedly-effects/icons', config.BASE_URL).href);
    const iconCatalog = await iconResponse.json();
    assert.equal(iconResponse.status(), 200, `Icon-Proxy-Status ${iconResponse.status()}: ${JSON.stringify(iconCatalog)}`);
    assert.equal(iconCatalog.version, 1);
    assert.ok(iconCatalog.categories.length > 0, 'Der Icon-Katalog muss Kategorien enthalten.');
    assert.ok(iconCatalog.icons.length > 0, 'Der Icon-Katalog muss Icons enthalten.');
    const result = await page.evaluate(async () => {
      const effects = await window.KWMashaFeedlyEffects.refresh();
      const widget = window.KWMashaFeedlyDOM.widget();
      const theme = widget.dataset.theme || 'playful';
      const suitable = effects.filter(effect => effect.categories.includes(theme));
      if (!suitable.length) throw new Error('Für das aktive Theme muss mindestens ein Test-Effekt freigegeben sein.');
      await window.KWMashaFeedlyEffects.preload(document, theme);
      const effect = suitable[0];
      const playback = await window.KWMashaFeedlyEffects.preview(effect.id, document);
      const styles = [...window.KWMashaFeedlyDOM.root().querySelectorAll('link[rel="stylesheet"]')].map(link => link.href);
      const count = window.KWMashaFeedlyEffects.cancelActive();
      const recordID = new URL(effect.files.js).pathname.match(/\/file\/(\d+)\//)?.[1];
      return { id: effect.id, name: effect.name, recordID, played: !!playback, cancelled: count, styles, css: effect.files.css, url: effect.files.js };
    });
    assert.ok(result.played, `Effekt ${result.id} muss laufen.`);
    assert.ok(result.cancelled > 0);
    if (result.css) assert.ok(result.styles.includes(result.css));
    assert.match(result.url, /\/__masha-feedly-effects\/file\/\d+\/[a-f0-9]{64}\/js$/);
    assert.equal(new URL(result.url).origin, new URL(config.BASE_URL).origin);
    const guest = await browser.newContext({ ignoreHTTPSErrors: true });
    try {
      for (const url of ['/__masha-feedly-effects/manifest', result.url]) {
        const response = await guest.request.get(new URL(url, config.BASE_URL).href);
        assert.equal(response.status(), 403, `Anonymer Zugriff muss gesperrt sein: ${url}`);
      }
    } finally { await guest.close(); }
    await page.goto(new URL('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig', config.BASE_URL).href);
    await expect(page.locator('[data-masha-feedly-effect-catalog] [data-masha-feedly-animation-preview-card]').first()).toBeAttached();
    assert.deepEqual(errors, []);
  } finally { await browser.close(); }
});

/** Prüft den separat gestreamten Profil-Icon-Katalog über den zugriffsgeschützten Feedly-Proxy. */
test('Effekt-Anbieter: Profil-Icon-Katalog wird vollständig über Feedly geladen', { skip: missing.length ? `E2E-Konfiguration fehlt: ${missing.join(', ')}` : false }, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  try {
    await page.goto(new URL('/Security/login', config.BASE_URL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.SUPERADMIN_EMAIL);
    await page.locator('input[type="password"]').first().fill(config.SUPERADMIN_PASSWORD);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    const response = await page.request.get(new URL('/__masha-feedly-effects/icons', config.BASE_URL).href);
    const catalogue = await response.json();
    assert.equal(response.status(), 200, `Icon-Proxy-Status ${response.status()}: ${JSON.stringify(catalogue)}`);
    assert.equal(catalogue.version, 1);
    assert.equal(catalogue.categories.length, 11);
    assert.equal(catalogue.icons.length, 116);
    await page.goto(new URL('/admin/myprofile#Root_MashaFeedly', config.BASE_URL).href);
    const iconPicker = page.locator('.masha-feedly-profile-settings [data-masha-feedly-avatar-icons]').last();
    await expect(iconPicker).toBeVisible();
    await expect(page.locator('.masha-feedly-profile-settings .masha-feedly-avatar-icons__unavailable')).toHaveCount(0);
    const iconTrigger = iconPicker.locator('[data-masha-feedly-avatar-icon-open]');
    if (await iconTrigger.isDisabled()) {
      await page.locator('.sudo-mode-password-field__notice-button').click({ force: true });
      await page.locator('[name="SudoModePassword"]').fill(config.SUPERADMIN_PASSWORD);
      await page.locator('.sudo-mode-password-field__verify-button').click();
    }
    await expect(iconTrigger).toBeEnabled();
    await iconTrigger.click();
    const iconDialog = iconPicker.locator('[data-masha-feedly-avatar-icon-dialog]');
    await expect(iconDialog).toBeVisible();
    const tabs = iconDialog.locator('[data-masha-feedly-avatar-icon-tab]');
    await expect(tabs).toHaveCount(11);
    const activePanel = iconDialog.locator('[role="tabpanel"]:not([hidden])');
    await expect(activePanel).toHaveCount(1);
    await expect(activePanel.locator('img[src]')).not.toHaveCount(0);
    await expect(iconDialog.locator('[role="tabpanel"][hidden] img[src]')).toHaveCount(0);
    await tabs.nth(1).click();
    await expect(iconDialog.locator('[role="tabpanel"]:not([hidden]) img[src]')).not.toHaveCount(0);
    const iconChoice = iconDialog.locator('[role="tabpanel"]:not([hidden]) [data-masha-feedly-avatar-icon-choice]').first();
    await expect(iconChoice).toBeVisible();
    const iconID = await iconChoice.getAttribute('data-icon-id');
    await iconChoice.click();
    await expect(page.locator('.masha-feedly-profile-settings [name="MashaFeedlyAvatarIcon"]')).toHaveValue(iconID);
    await expect(iconDialog).toBeHidden();
  } finally { await context.close(); await browser.close(); }
});
