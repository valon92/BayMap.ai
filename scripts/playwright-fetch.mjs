#!/usr/bin/env node
/**
 * Fetch page HTML via headless Chromium for anti-bot marketplace scraping.
 * Input: JSON on stdin { url, locale?, referer?, timeout?, waitUntil? }
 * Output: JSON on stdout { ok, html?, error? }
 */
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const DEFAULT_UA =
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

async function readInput() {
    if (process.argv[2]) {
        return JSON.parse(process.argv[2]);
    }

    const fd = process.stdin.fd;
    if (fd === 0 && !process.stdin.isTTY) {
        const raw = readFileSync(0, 'utf8').trim();
        if (raw !== '') {
            return JSON.parse(raw);
        }
    }

    return {};
}

function acceptLanguage(locale) {
    return (
        {
            'de-DE': 'de-DE,de;q=0.9,en;q=0.8',
            'de-CH': 'de-CH,de;q=0.9,en;q=0.8',
            'nl-NL': 'nl-NL,nl;q=0.9,en;q=0.8',
            'en-GB': 'en-GB,en;q=0.9',
        }[locale] ?? 'en-US,en;q=0.9'
    );
}

function writeResult(payload) {
    process.stdout.write(`${JSON.stringify(payload)}\n`);
}

const input = await readInput();
const url = String(input.url ?? '').trim();

if (url === '' || !/^https?:\/\//i.test(url)) {
    writeResult({ ok: false, error: 'Invalid or missing url' });
    process.exit(1);
}

const timeout = Math.min(Math.max(Number(input.timeout ?? 30000), 5000), 120000);
const waitUntil = ['load', 'domcontentloaded', 'networkidle', 'commit'].includes(input.waitUntil)
    ? input.waitUntil
    : 'domcontentloaded';
const locale = String(input.locale ?? 'en-US');
const referer = String(input.referer ?? '').trim();
const headless = input.headless !== false;

let browser;

try {
    browser = await chromium.launch({
        headless,
        args: ['--disable-blink-features=AutomationControlled', '--no-sandbox'],
    });

    const context = await browser.newContext({
        userAgent: DEFAULT_UA,
        locale: locale.split('-')[0] || 'en',
        extraHTTPHeaders: {
            'Accept-Language': acceptLanguage(locale),
            Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ...(referer !== '' ? { Referer: referer } : {}),
        },
        viewport: { width: 1366, height: 900 },
    });

    const page = await context.newPage();
    await page.goto(url, { waitUntil, timeout });

    const waitSelector = String(input.waitSelector ?? '').trim();
    if (waitSelector !== '') {
        await page.waitForSelector(waitSelector, { timeout: Math.min(timeout, 15000) }).catch(() => {});
    } else {
        await page.waitForTimeout(800);
    }

    const html = await page.content();
    await context.close();
    await browser.close();

    if (!html || html.length < 200) {
        writeResult({ ok: false, error: 'Empty page content' });
        process.exit(1);
    }

    writeResult({ ok: true, html, bytes: html.length });
} catch (error) {
    if (browser) {
        await browser.close().catch(() => {});
    }

    writeResult({
        ok: false,
        error: error instanceof Error ? error.message : String(error),
    });
    process.exit(1);
}
