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

/** Ersetzt Bildschirmfreigabe und Meldungsspeichern durch kontrollierte Browserantworten. */
const installScreenshotBrowserFakes = async (page) => {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getDisplayMedia: async () => new MediaStream() },
    });
    Object.defineProperty(HTMLVideoElement.prototype, 'videoWidth', { configurable: true, get: () => 800 });
    Object.defineProperty(HTMLVideoElement.prototype, 'videoHeight', { configurable: true, get: () => 500 });
    HTMLMediaElement.prototype.play = () => Promise.resolve();

    const originalDrawImage = CanvasRenderingContext2D.prototype.drawImage;
    CanvasRenderingContext2D.prototype.drawImage = function drawImage(source, ...coordinates) {
      if (source instanceof HTMLVideoElement) return;
      if (source instanceof HTMLImageElement && source.hasAttribute('data-masha-feedly-screenshot-image')) {
        window.__mashaScreenshotCropDraw = coordinates;
      }
      return originalDrawImage.call(this, source, ...coordinates);
    };

    const originalFetch = window.fetch.bind(window);
    window.fetch = async (input, init = {}) => {
      const requestURL = new URL(typeof input === 'string' ? input : input.url, location.href);
      if (requestURL.pathname.endsWith('/createEntry') && init.body instanceof FormData) {
        const screenshot = init.body.getAll('Attachments[]').find((file) => file.name === 'seiten-ausschnitt.png');
        window.__mashaScreenshotSubmission = screenshot
          ? { name: screenshot.name, type: screenshot.type, size: screenshot.size }
          : null;
        return new Response(JSON.stringify({ success: true, entryID: 987654, title: 'E2E Screenshot-Meldung', message: 'Gespeichert' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        });
      }
      return originalFetch(input, init);
    };
  });
};

/** Meldet das E2E-Mitglied an und öffnet das Formular für eine neue Meldung. */
const openCreateForm = async (page, baseURL, email, password) => {
  await page.goto(new URL('/Security/login', baseURL).href);
  await page.locator('input[type="email"], input[name$="Email"], input[id$="Email"]').first().fill(email);
  await page.locator('input[type="password"]').first().fill(password);
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.goto(baseURL);

  const widget = page.locator('[data-kw-masha-feedly]');
  await widget.waitFor({ state: 'attached' });
  const welcome = widget.locator('[data-masha-feedly-onboarding-welcome]');
  if (await welcome.isVisible()) {
    await welcome.locator('[data-masha-feedly-tour-skip]').click();
    await welcome.waitFor({ state: 'hidden' });
  }
  await widget.locator('.kw-masha-feedly__toggle').click();
  await widget.locator('[data-masha-feedly-start-selection]').click();
  await page.locator('[role="main"]').first().click();

  const form = widget.locator('[data-masha-feedly-entry-form]');
  await form.waitFor({ state: 'visible' });
  await form.locator('[name="Content"]').fill(`E2E Screenshot ${new Date().toISOString()}`);
  await form.locator('[data-masha-feedly-screenshot-capture]').click();
  await form.locator('[data-masha-feedly-screenshot-preview]').waitFor({ state: 'visible' });
  const image = form.locator('[data-masha-feedly-screenshot-image]');
  await page.waitForFunction(() => {
    const image = window.KWMashaFeedlyDOM?.root()?.querySelector('[data-masha-feedly-screenshot-image]');
    return image?.complete && image.naturalWidth === 800 && image.naturalHeight === 500;
  });
  return { widget, form, image };
};

/** Prüft, dass exakt der markierte Ausschnitt als PNG im Formularupload landet. */
const assertCroppedScreenshotWasSubmitted = async (page, widget, form, bounds, start, end) => {
  const x1 = bounds.x + bounds.width * start.x;
  const y1 = bounds.y + bounds.height * start.y;
  const x2 = bounds.x + bounds.width * end.x;
  const y2 = bounds.y + bounds.height * end.y;
  const expected = [
    Math.round(Math.min(start.x, end.x) * 800),
    Math.round(Math.min(start.y, end.y) * 500),
    Math.round(Math.abs(end.x - start.x) * 800),
    Math.round(Math.abs(end.y - start.y) * 500),
    0,
    0,
    Math.round(Math.abs(end.x - start.x) * 800),
    Math.round(Math.abs(end.y - start.y) * 500),
  ];
  await page.mouse.click(x1, y1);
  await page.mouse.move(x2, y2);
  const cropButton = form.locator('[data-masha-feedly-screenshot-apply]');
  assert.equal(await cropButton.isDisabled(), true, 'Der Ausschnitt wird erst mit dem zweiten Klick bestätigt.');
  const selection = form.locator('[data-masha-feedly-screenshot-crop]');
  const selectionBox = await selection.boundingBox();
  assert.ok(selectionBox?.width > 0 && selectionBox?.height > 0, `Der Auswahlrahmen folgt dem Zeiger vor dem zweiten Klick: ${JSON.stringify(await form.locator('[data-masha-feedly-screenshot-cropper]').evaluate((element) => ({ image: element.querySelector('img').getBoundingClientRect().toJSON(), cropHidden: element.querySelector('[data-masha-feedly-screenshot-crop]').hidden, cropStyle: element.querySelector('[data-masha-feedly-screenshot-crop]').getAttribute('style') })))}`);
  await page.mouse.click(x2, y2);
  await assertCroppedImageReady(page, form, cropButton);
  assert.deepEqual(await page.evaluate(() => window.__mashaScreenshotCropDraw), expected, 'Canvas übernimmt die Koordinaten des markierten Bildausschnitts.');

  await widget.locator('[data-masha-feedly-modal] button[type="submit"][form="kw-masha-feedly-create-form"]').click();
  await page.waitForFunction(() => window.__mashaScreenshotSubmission?.name === 'seiten-ausschnitt.png');
  const submittedFile = await page.evaluate(() => window.__mashaScreenshotSubmission);
  assert.equal(submittedFile.type, 'image/png');
  assert.ok(submittedFile.size > 0, 'Der zugeschnittene Screenshot enthält Bilddaten.');
};

const assertCroppedImageReady = async (page, form, cropButton) => {
  await cropButton.click();
  await page.waitForFunction(() => {
    const image = window.KWMashaFeedlyDOM?.root()?.querySelector('[data-masha-feedly-screenshot-image]');
    return image?.complete && image.naturalWidth > 0 && image.naturalWidth < 800;
  });
  const status = form.locator('[data-masha-feedly-screenshot-status]');
  await status.waitFor({ state: 'visible' });
  await require('@playwright/test').expect(status).not.toHaveText('');
};

test('Screenshot lässt sich per zwei Mausklicks zuschneiden und wird als PNG angehängt', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1200, height: 900 } });
  const page = await context.newPage();
  await installScreenshotBrowserFakes(page);
  try {
    const { widget, form, image } = await openCreateForm(page, config.baseURL, config.email, config.password);
    await image.scrollIntoViewIfNeeded();
    const bounds = await image.boundingBox();
    await assertCroppedScreenshotWasSubmitted(page, widget, form, bounds, { x: 0.2, y: 0.2 }, { x: 0.7, y: 0.7 });
  } finally {
    await context.close();
    await browser.close();
  }
});

test('Screenshotbereich lässt sich auf Touchscreens mit zwei Fingertipps festlegen', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    hasTouch: true,
    isMobile: true,
    viewport: { width: 1200, height: 900 },
  });
  const page = await context.newPage();
  await installScreenshotBrowserFakes(page);
  try {
    const { form, image } = await openCreateForm(page, config.baseURL, config.email, config.password);
    await image.scrollIntoViewIfNeeded();
    const bounds = await image.boundingBox();
    await page.touchscreen.tap(bounds.x + bounds.width * 0.18, bounds.y + bounds.height * 0.18);
    await page.touchscreen.tap(bounds.x + bounds.width * 0.75, bounds.y + bounds.height * 0.75);
    const cropButton = form.locator('[data-masha-feedly-screenshot-apply]');
    await expect(cropButton).toBeEnabled();
    await cropButton.click();
    await page.waitForFunction(() => {
      const image = window.KWMashaFeedlyDOM?.root()?.querySelector('[data-masha-feedly-screenshot-image]');
      return image?.complete && image.naturalWidth > 0 && image.naturalWidth < 800;
    });
  } finally {
    await context.close();
    await browser.close();
  }
});
