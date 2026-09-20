/**
 * Renders assets/social/card.html to the PNGs the site serves.
 *
 *   node assets/social/render.js
 *
 * Run from a machine with Playwright, not from the shared host — the output
 * is committed, so the server only ever serves the finished files.
 */
const path = require('path');
const { chromium } = require('playwright');

const SOURCE = 'file://' + path.join(__dirname, 'card.html');
const OUT    = path.join(__dirname, '..', '..', 'public', 'assets', 'social');

const CARDS = [
  { id: 'og', file: 'og.png',     width: 1200, height: 630,  label: 'Open Graph / Twitter / LinkedIn' },
  { id: 'sq', file: 'square.png', width: 1080, height: 1080, label: 'Instagram / square previews' },
];

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || undefined,
  });
  // deviceScaleFactor 1: these are already the exact pixel sizes the
  // platforms want, and a 2x render would be downscaled by them anyway.
  const page = await browser.newPage({ viewport: { width: 1280, height: 1200 } });
  await page.goto(SOURCE, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);

  for (const card of CARDS) {
    const el = await page.$('#' + card.id);
    const box = await el.boundingBox();
    if (Math.round(box.width) !== card.width || Math.round(box.height) !== card.height) {
      throw new Error(
        `${card.file} rendered at ${Math.round(box.width)}x${Math.round(box.height)}, ` +
        `expected ${card.width}x${card.height}`
      );
    }
    await el.screenshot({ path: path.join(OUT, card.file) });
    console.log(`${card.file.padEnd(12)} ${card.width}x${card.height}  ${card.label}`);
  }

  await browser.close();
})();
