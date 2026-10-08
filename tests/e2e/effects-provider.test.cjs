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

/** Prüft die vollständige Verbindung vom CMS-Katalog bis zur Animation im isolierten Widget. */
test('Effekt-Anbieter: CMS-Katalog lädt versionierte Dateien und spielt im Shadow Root', { skip: missing.length ? `E2E-Konfiguration fehlt: ${missing.join(', ')}` : false }, async () => {
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
      return { id: effect.id, played: !!playback, cancelled: count, styles, css: effect.files.css, url: effect.files.js };
    });
    assert.ok(result.played, `Effekt ${result.id} muss laufen.`);
    assert.ok(result.cancelled > 0);
    if (result.css) assert.ok(result.styles.includes(result.css));
    assert.match(result.url, /\/__masha-feedly-effects\/file\/\d+\/[a-f0-9]{64}\/js$/);
    assert.equal(new URL(result.url).origin, new URL(config.BASE_URL).origin);
    const guest = await browser.newContext({ ignoreHTTPSErrors: true });
    try {
      for (const url of ['/__masha-feedly-effects/manifest', result.url, '/__masha-effects/manifest', result.url.replace('__masha-feedly-effects', '__masha-effects')]) {
        const response = await guest.request.get(new URL(url, config.BASE_URL).href);
        assert.equal(response.status(), 403, `Anonymer Zugriff muss gesperrt sein: ${url}`);
      }
      for (const raw of ['/masha-effects/private/resources/js/ducks.js', '/masha-effects/client/src/js/ducks.js', '/_resources/masha-effects/client/dist/js/ducks.js', '/_resources/masha-effects/private/resources/js/ducks.js', '/_resources/vendor/kooperativeweb/masha-effects/client/dist/js/ducks.js']) {
        const response = await guest.request.get(new URL(raw, config.BASE_URL).href);
        assert.ok([403, 404].includes(response.status()), `Direkte Datei muss gesperrt sein: ${raw}`);
      }
    } finally { await guest.close(); }
    await page.goto(new URL('/admin/masha-feedly/SilverStripe-SiteConfig-SiteConfig', config.BASE_URL).href);
    await expect(page.locator('[data-masha-feedly-effect-catalog] [data-masha-feedly-animation-preview-card]').first()).toBeAttached();
    const recordID = new URL(result.url).pathname.match(/\/file\/(\d+)\//)[1];
    await page.goto(new URL(`/admin/masha-effects/KW-MashaEffects-Model-Effect/EditForm/field/KW-MashaEffects-Model-Effect/item/${recordID}/edit`, config.BASE_URL).href);
    await expect(page.locator('input[name="Title"]')).toBeVisible();
    await expect(page.locator('select[name="Theme"]')).toBeAttached();
    await expect(page.locator('input[type="checkbox"][name^="Months["]')).toHaveCount(12);
    await expect(page.locator('select[name="JavaScriptFile"]')).toHaveValue(/js\/.+\.js/);
    assert.deepEqual(errors, []);
  } finally { await browser.close(); }
});

/** Erzeugt echte CMS-Zugänge, prüft Rotation und Widerruf und entfernt den eigenen Testdatensatz. */
test('Effekt-Zugänge: CMS erzeugt einmalige Website-Schlüssel und sperrt deaktivierte Zugänge', { skip: missing.length ? `E2E-Konfiguration fehlt: ${missing.join(', ')}` : false }, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  const route = '/admin/masha-effects/KW-MashaEffects-Model-WebsiteAccess/EditForm/field/KW-MashaEffects-Model-WebsiteAccess/item/';
  let editURL;
  try {
    await page.goto(new URL('/Security/login', config.BASE_URL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.SUPERADMIN_EMAIL);
    await page.locator('input[type="password"]').first().fill(config.SUPERADMIN_PASSWORD);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.goto(new URL('/?flush=1', config.BASE_URL).href);
    await page.goto(new URL(route + 'new', config.BASE_URL).href);
    await page.locator('input[name="Title"]').fill(`E2E Effekt-Zugang ${Date.now()}`);
    await page.locator('input[name="WebsiteURL"]').fill('https://effects-e2e.example.test');
    await Promise.all([page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/admin/masha-effects/')), page.locator('button[name="action_doSave"]').click()]);
    await expect(page.locator('button[name="action_doGenerateKey"]')).toBeVisible();
    editURL = page.url();
    const generate = async () => {
      const [response] = await Promise.all([page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/admin/masha-effects/')), page.locator('button[name="action_doGenerateKey"]').click()]);
      assert.equal(response.status(), 200);
      assert.ok((response.headers()['cache-control'] || '').includes('no-store'));
      await page.locator('[data-masha-effects-generated-key]').waitFor({ state: 'visible' });
      const text = await page.locator('[data-masha-effects-generated-key]').textContent();
      const key = text.match(/MASHA_FEEDLY_EFFECTS_API_KEY="([a-f0-9]{64})"/)[1];
      // Geheimnisse bleiben ausschließlich im Testprozess, niemals in Assertions oder Logs.
      assert.equal(key.length, 64);
      return key;
    };
    const status = async key => (await context.request.get(new URL('/__masha-effects/manifest', config.BASE_URL).href, { headers: { Authorization: `Bearer ${key}` } })).status();
    const first = await generate();
    assert.equal(await status(first), 200);
    await page.goto(editURL);
    await expect(page.locator('[data-masha-effects-generated-key]')).toHaveCount(0);
    const second = await generate();
    assert.ok(first !== second);
    assert.equal(await status(first), 403);
    assert.equal(await status(second), 200);
    const action = await page.locator('button[name="action_doGenerateKey"]').locator('xpath=ancestor::form').getAttribute('action');
    const rejected = await context.request.post(new URL(action, config.BASE_URL).href, { form: { action_doGenerateKey: '1' } });
    assert.equal(rejected.status(), 400);
    assert.equal(await status(second), 200);
    await page.goto(editURL);
    await page.locator('input[name="Active"]').uncheck();
    await Promise.all([page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/admin/masha-effects/')), page.locator('button[name="action_doSave"]').click()]);
    await page.goto(editURL);
    await expect(page.locator('input[name="Active"]')).not.toBeChecked();
    assert.equal(await status(second), 403);
  } finally {
    if (editURL) {
      await page.goto(editURL);
      page.on('dialog', dialog => dialog.accept());
      await Promise.all([page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/admin/masha-effects/')), page.locator('button[name="action_doDelete"]').click()]);
    }
    await browser.close();
  }
});
