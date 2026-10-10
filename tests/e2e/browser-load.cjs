#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { performance } = require('node:perf_hooks');

const envPath = path.join(__dirname, '.env.e2e');
const fileEnv = {};
if (fs.existsSync(envPath)) {
  fs.readFileSync(envPath, 'utf8').split(/\r?\n/).forEach((line) => {
    const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
    if (!match || match[2].startsWith('#')) return;
    fileEnv[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
  });
}

const setting = (name, fallback = '') => process.env[name] || fileEnv[name] || fallback;
const baseURL = setting('MASHA_FEEDLY_E2E_BASE_URL');
const email = setting('MASHA_FEEDLY_E2E_CREATOR_EMAIL');
const password = setting('MASHA_FEEDLY_E2E_CREATOR_PASSWORD');
const users = Number.parseInt(process.env.MASHA_FEEDLY_LOAD_USERS || '5', 10);
const durationSeconds = Number.parseInt(process.env.MASHA_FEEDLY_LOAD_SECONDS || '60', 10);
const rampSeconds = Number.parseInt(process.env.MASHA_FEEDLY_LOAD_RAMP_SECONDS || '10', 10);

const assertSafeTarget = () => {
  if (setting('MASHA_FEEDLY_LOAD_TEST') !== 'LOCAL_ONLY') {
    throw new Error('Sicherheitsstopp: erst MASHA_FEEDLY_LOAD_TEST=LOCAL_ONLY setzen. Nur lokale Testinstanzen verwenden.');
  }
  if (!baseURL || !email || !password) throw new Error('E2E-Basisadresse und Creator-Login fehlen in .env.e2e.');
  const url = new URL(baseURL);
  const localHost = ['localhost', '127.0.0.1', '::1'].includes(url.hostname)
    || url.hostname.endsWith('.test')
    || url.hostname.endsWith('.localhost')
    || (url.hostname === 'feedly' && url.port === '8890');
  if (!localHost) throw new Error('Sicherheitsstopp: Browserlast ist nur für localhost, *.test oder den lokalen Host feedly erlaubt.');
  if (!Number.isInteger(users) || users < 1 || users > 20) throw new Error('MASHA_FEEDLY_LOAD_USERS muss zwischen 1 und 20 liegen.');
  if (!Number.isInteger(durationSeconds) || durationSeconds < 10 || durationSeconds > 300) throw new Error('MASHA_FEEDLY_LOAD_SECONDS muss zwischen 10 und 300 liegen.');
  if (!Number.isInteger(rampSeconds) || rampSeconds < 0 || rampSeconds > 120) throw new Error('MASHA_FEEDLY_LOAD_RAMP_SECONDS muss zwischen 0 und 120 liegen.');
};

const percentile = (values, fraction) => {
  if (!values.length) return null;
  const sorted = [...values].sort((left, right) => left - right);
  return Math.round(sorted[Math.min(sorted.length - 1, Math.ceil(sorted.length * fraction) - 1)]);
};

const signIn = async (page) => {
  await page.goto(new URL('/Security/login', baseURL).href, { waitUntil: 'domcontentloaded' });
  await page.locator('input[type="email"], input[name$="Email"], input[id$="Email"]').first().fill(email);
  await page.locator('input[type="password"]').first().fill(password);
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.goto(baseURL, { waitUntil: 'domcontentloaded' });
  const widget = page.locator('[data-kw-masha-feedly]');
  await widget.waitFor({ state: 'attached', timeout: 20000 });
  if (await widget.getAttribute('data-onboarding-enabled') === '1') {
    throw new Error('Das Creator-Testkonto muss das Onboarding bereits abgeschlossen haben.');
  }
  return widget;
};

const runUser = async (browser, userID, startedAt, stopAt, measurements, failures) => {
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const requestStarts = new WeakMap();
  let listResponses = 0;
  page.on('request', (request) => requestStarts.set(request, performance.now()));
  page.on('response', (response) => {
    if (!new URL(response.url()).pathname.endsWith('/__masha-feedly/listEntries')) return;
    const started = requestStarts.get(response.request());
    if (started !== undefined) {
      const serverTiming = response.headers()['server-timing'] || '';
      const serverDuration = serverTiming.match(/(?:^|,)\s*masha_list;dur=([\d.]+)/);
      measurements.push({
        ms: Math.round(performance.now() - started),
        serverMs: serverDuration ? Number(serverDuration[1]) : null,
        status: response.status(),
      });
      listResponses++;
    }
  });
  page.on('requestfailed', (request) => {
    if (request.url().includes('/__masha-feedly/')) failures.push({ userID, reason: request.failure()?.errorText || 'Netzwerkfehler' });
  });

  try {
    const rampDelay = users > 1 ? (userID / (users - 1)) * rampSeconds * 1000 : 0;
    if (rampDelay) await page.waitForTimeout(rampDelay);
    const widget = await signIn(page);
    await widget.locator('.kw-masha-feedly__toggle').click();
    const listButton = widget.locator('[data-masha-feedly-open-list]');
    await listButton.waitFor({ state: 'visible' });
    let iteration = 0;
    while (performance.now() < stopAt) {
      const button = widget.locator('[data-masha-feedly-open-list]');
      const visible = await button.isVisible().catch(() => false);
      if (!visible) throw new Error('Liste öffnen wurde im Widget nicht sichtbar.');
      const before = listResponses;
      await button.click();
      await page.locator('[data-masha-feedly-entries-modal]').waitFor({ state: 'visible', timeout: 10000 });
      const deadline = performance.now() + 30000;
      while (listResponses === before && performance.now() < deadline) await page.waitForTimeout(50);
      if (listResponses === before) throw new Error('Listenabruf wurde nicht innerhalb von 30 Sekunden abgeschlossen.');
      iteration++;
      await widget.locator('[data-masha-feedly-close-list]').click();
      const wait = 1500 + Math.floor(Math.random() * 1000);
      await page.waitForTimeout(wait);
    }
  } catch (error) {
    failures.push({ userID, reason: error.message });
  } finally {
    await context.close();
  }
};

const main = async () => {
  assertSafeTarget();
  const { chromium } = require('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  const measurements = [];
  const failures = [];
  const startedAt = performance.now();
  const stopAt = startedAt + (durationSeconds + rampSeconds) * 1000;
  console.log(`Lokaler Browserlasttest: ${users} Sitzungen, ${durationSeconds}s Laufzeit, ${rampSeconds}s Anlauf.`);
  console.log('Ablauf: Anmeldung, Widget öffnen, Meldungsliste wiederholt laden. Es werden keine Meldungen verändert.');
  try {
    await Promise.all(Array.from({ length: users }, (_, index) => runUser(browser, index, startedAt, stopAt, measurements, failures)));
  } finally {
    await browser.close();
  }
  const elapsed = Math.round((performance.now() - startedAt) / 1000);
  const durations = measurements.map((item) => item.ms);
  const badResponses = measurements.filter((item) => item.status >= 400).length;
  console.log(`\nDauer: ${elapsed}s · Listenabrufe: ${measurements.length} · HTTP-Fehler: ${badResponses} · Sitzungs-/Netzwerkfehler: ${failures.length}`);
  if (durations.length) {
    console.log(`Listenantworten: p50 ${percentile(durations, 0.50)}ms · p95 ${percentile(durations, 0.95)}ms · langsamste ${Math.max(...durations)}ms`);
    const serverDurations = measurements.map((item) => item.serverMs).filter(Number.isFinite);
    if (serverDurations.length) {
      console.log(`PHP-Listenhandler: p50 ${percentile(serverDurations, 0.50)}ms · p95 ${percentile(serverDurations, 0.95)}ms · langsamste ${Math.max(...serverDurations).toFixed(2)}ms (${serverDurations.length}/${measurements.length} Antworten)`);
      console.log('Hinweis: Browserzeit minus Handlerzeit enthält unter anderem Webserver-Wartezeit, Middleware und Netzwerk.');
    } else {
      console.log('PHP-Listenhandler: kein Server-Timing-Header (nur im Silverstripe-Development-Modus verfügbar).');
    }
    const statusCounts = measurements.reduce((counts, item) => {
      counts[item.status] = (counts[item.status] || 0) + 1;
      return counts;
    }, {});
    console.log(`HTTP-Status: ${JSON.stringify(statusCounts)}`);
  }
  failures.slice(0, 10).forEach((failure) => console.error(`Sitzung ${failure.userID + 1}: ${failure.reason}`));
  if (failures.length > 10) console.error(`… ${failures.length - 10} weitere Fehler`);
  if (badResponses || failures.length) process.exitCode = 1;
};

main().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});
