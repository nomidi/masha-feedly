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


/** Prüft die sichtbare Desktop-FAQ mit echter Tastatur, Suche und Variantenwechsel. */
test('FAQ: Desktop-Einstieg, Antworten, Suche, Fokus und mobile Ausblendung', {
  skip: missingConfig.length ? `E2E-Konfiguration fehlt: ${missingConfig.join(', ')}` : false,
}, async () => {
  const { chromium, expect } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    // Die Debugbar wird ausschließlich im Test entfernt, damit sie keine Klicks verdeckt.
    await page.addInitScript(() => {
      const remove = () => document.querySelectorAll('.phpdebugbar').forEach(bar => bar.remove());
      document.addEventListener('DOMContentLoaded', () => {
        remove();
        new MutationObserver(remove).observe(document.body, { childList: true, subtree: true });
      });
    });
    await page.goto(new URL('/Security/login', config.baseURL).href);
    await page.locator('input[type="email"], input[name$="Email"]').first().fill(config.email);
    await page.locator('input[type="password"]').first().fill(config.password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.route('**/__masha-feedly/listEntries*', route => route.fulfill({
      contentType: 'application/json', body: JSON.stringify({
        success: true, mode: 'page', entries: [], categories: [], priorities: [],
        unreadCount: 0, unreadCommentCount: 0, totalCount: 0, openCount: 0, pageOpenCount: 0,
      }),
    }));
    await page.goto(config.baseURL);
    const widget = page.locator('[data-kw-masha-feedly]');
    await widget.evaluate(element => element.dataset.theme = 'playful');
    await widget.locator('.kw-masha-feedly__toggle').click();
    const button = widget.locator('.kw-masha-feedly__panel [data-masha-feedly-open-faq]');
    const modal = widget.locator('[data-masha-feedly-faq-modal]');
    await expect(button).toBeVisible();
    const layout = await widget.evaluate(element => {
      const faq = element.querySelector('.kw-masha-feedly__panel [data-masha-feedly-open-faq]').getBoundingClientRect();
      const help = element.querySelector('[data-masha-feedly-open-help]').getBoundingClientRect();
      const group = element.querySelector('.kw-masha-feedly__support-buttons');
      const previous = Array.from(group.parentElement.children).filter(child => child !== group && child.getClientRects().length).at(-1).getBoundingClientRect();
      return { right: faq.right, gap: faq.top - help.bottom, separation: help.top - previous.bottom };
    });
    assert.ok(layout.right > 1380, 'FAQ sitzt rechts im ersten Panel');
    assert.ok(layout.gap >= 0 && layout.gap < 30, 'FAQ sitzt direkt unter der Kurzhilfe');
    assert.ok(layout.separation >= 40, 'Hilfe und FAQ haben Abstand zu den Arbeitsaktionen');
    const help = widget.locator('[data-masha-feedly-open-help]');
    await help.click();
    const helpModal = widget.locator('[data-masha-feedly-help-modal]');
    await expect(helpModal.locator('.kw-masha-feedly__help-faq-note')).toBeVisible();
    const helpFaqButton = helpModal.locator('[data-masha-feedly-open-faq]');
    await helpFaqButton.click();
    await expect(modal).toBeVisible();
    await modal.locator('[data-masha-feedly-close-faq]').click();
    await expect(helpFaqButton).toBeFocused();
    await helpModal.locator('[data-masha-feedly-close-help]').click();
    await page.screenshot({ path: "/tmp/feedly-faq-before.png" });
    await button.click();
    await expect(modal).toBeVisible();
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await expect(modal.locator('h2')).toBeFocused();
    const questions = modal.locator('[data-masha-feedly-faq-item]');
    assert.ok(await questions.count() >= 35, 'FAQ deckt alle Benutzerfunktionen ab');
    const first = questions.first();
    await first.locator('summary').click();
    await expect(first).toHaveAttribute('open', '');
    await expect(first.locator('p')).toBeVisible();
    await expect(first.locator('p')).not.toBeEmpty();
    assert.ok(await first.locator('p').evaluate(element => parseFloat(getComputedStyle(element).fontSize)) >= 15, 'FAQ-Antworten bleiben lesbar');
    await expect(page.locator('[data-masha-feedly-host]').locator('.kw-masha-feedly__hover-tooltip')).toBeHidden();
    await modal.screenshot({ path: '/tmp/feedly-faq-desktop.png' });

    const search = modal.locator('[data-masha-feedly-faq-search]');
    await search.fill('screenshot');
    const matches = modal.locator('[data-masha-feedly-faq-item]:visible');
    assert.ok(await matches.count() >= 2);
    for (const item of await matches.all()) assert.match((await item.textContent()).toLowerCase(), /screenshot/);
    await search.fill('xyz-keine-antwort-123');
    await expect(modal.locator('[data-masha-feedly-faq-empty]')).toBeVisible();
    await expect(matches).toHaveCount(0);
    const createFromFaq = modal.locator('[data-masha-feedly-faq-create-entry]');
    await expect(createFromFaq).toBeVisible();
    await createFromFaq.click();
    await expect(modal).toBeHidden();
    await expect(widget.locator('[data-masha-feedly-selection-banner]')).toBeVisible();
    await page.evaluate(() => {
      const target = Array.from(document.querySelectorAll('main, h1, h2, p'))
        .find(element => !element.closest('[data-kw-masha-feedly]') && element.getClientRects().length && element.textContent.trim());
      if (!target) throw new Error('Keine sichtbare Seitenstelle für die FAQ-Meldung gefunden.');
      const rect = target.getBoundingClientRect();
      target.dispatchEvent(new MouseEvent('click', {
        bubbles: true, cancelable: true,
        clientX: rect.left + Math.min(12, rect.width / 2),
        clientY: rect.top + Math.min(12, rect.height / 2),
      }));
    });
    await expect(widget.locator('[data-masha-feedly-modal]')).toBeVisible();
    await expect(widget.locator('[data-masha-feedly-entry-form] [name="Content"]')).toHaveValue('Ich habe in der FAQ nach „xyz-keine-antwort-123“ gesucht. Meine Frage dazu: ');
    await widget.locator('[data-masha-feedly-close-modal]').first().click();
    if (!(await button.isVisible())) await widget.locator('.kw-masha-feedly__toggle').click();
    await button.click();
    await expect(modal).toBeVisible();
    await search.fill('xyz-keine-antwort-123');
    await modal.locator('[data-masha-feedly-faq-reset]').click();
    await expect(search).toHaveValue('');
    await expect(search).toBeFocused();
    await modal.locator('[data-masha-feedly-faq-topic="FILES"]').click();
    await expect(modal.locator('[data-masha-feedly-faq-group]:visible')).toHaveCount(1);
    await expect(modal.locator('[data-masha-feedly-faq-group="FILES"]')).toBeVisible();

    const dialog = modal.locator('[role="dialog"]');
    const initialBox = await dialog.boundingBox();
    const fileQuestion = modal.locator('[data-masha-feedly-faq-group="FILES"] summary').first();
    for (let i = 0; i < 4; i++) {
      await fileQuestion.click();
      const currentBox = await dialog.boundingBox();
      for (const dimension of ['x', 'y', 'width', 'height']) {
        assert.ok(Math.abs(currentBox[dimension] - initialBox[dimension]) < 1, 'FAQ-Kasten bleibt beim Auf- und Zuklappen unverändert: ' + dimension);
      }
    }


    const close = modal.locator('[data-masha-feedly-close-faq]');
    await close.focus();
    await page.keyboard.press('Shift+Tab');
    await expect(modal.locator('[data-masha-feedly-faq-item]:visible').last().locator('summary')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(close).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await expect(button).toBeFocused();
    await expect(button).toHaveAttribute('aria-expanded', 'false');

    await button.click();
    await modal.locator('[data-masha-feedly-faq-topic="all"]').click();
    await widget.evaluate(element => element.dataset.theme = 'serious');
    await expect(page.locator('[data-masha-feedly-host]').locator('.kw-masha-feedly__hover-tooltip')).toBeHidden();
    await modal.screenshot({ path: '/tmp/feedly-faq-serious.png' });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(modal).toHaveAttribute('hidden', '');
    await expect(modal).toBeHidden();
    await expect(button).toBeHidden();
    await page.setViewportSize({ width: 1280, height: 720 });
    // Beim Variantenwechsel schließt die bestehende Widget-Logik das erste Panel.
    if (!(await button.isVisible())) await widget.locator('.kw-masha-feedly__toggle').click();
    await expect(button).toBeVisible();
    await button.click();
    await expect(modal).toBeVisible();
    await close.click();
    await expect(button).toBeFocused();
    assert.deepEqual(errors, [], 'Die FAQ erzeugt keine Browserfehler');
  } finally {
    await context.close();
    await browser.close();
  }
});
