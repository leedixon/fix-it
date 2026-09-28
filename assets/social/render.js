/**
 * Renders assets/social/card.html to the PNGs the site serves.
 *
 *   node assets/social/render.js
 *
 * Run from a machine with Playwright, not from the shared host — the output
 * is committed, so the server only ever serves the finished files.
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const SOURCE = 'file://' + path.join(__dirname, 'card.html');
const OUT    = path.join(__dirname, '..', '..', 'public', 'assets', 'social');

const CARDS = [
  { id: 'og',  file: 'og.png',              width: 1200, height: 630,  label: 'Open Graph / Twitter / LinkedIn' },
  { id: 'sq',  file: 'square.png',          width: 1080, height: 1080, label: 'Instagram / square previews' },
  // Facebook. The profile is rendered square and cropped to a circle by
  // Facebook itself, so the guide ring drawn in card.html is hidden here —
  // it exists to check the render against, not to ship.
  { id: 'fbp', file: 'facebook-profile.png', width: 1080, height: 1080, label: 'Facebook / Page profile picture', hide: '.ring' },
  { id: 'fbc', file: 'facebook-cover.png',   width: 1640, height: 624,  label: 'Facebook / Page cover photo' },
  // App icons. These land in public/assets/icons rather than social/ —
  // the manifest points at them and they are not social cards.
  { id: 'i192', file: 'icon-192.png',        width: 192,  height: 192,  label: 'PWA / maskable', dir: 'icons' },
  { id: 'i512', file: 'icon-512.png',        width: 512,  height: 512,  label: 'PWA / maskable', dir: 'icons' },
  { id: 'iOS',  file: 'apple-touch-icon.png', width: 180, height: 180,  label: 'iOS / home screen', dir: 'icons' },
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
    if (card.hide) {
      await page.evaluate(
        ([id, sel]) => {
          const node = document.querySelector('#' + id + ' ' + sel);
          if (node) { node.style.display = 'none'; }
        },
        [card.id, card.hide],
      );
    }
    const box = await el.boundingBox();
    if (Math.round(box.width) !== card.width || Math.round(box.height) !== card.height) {
      throw new Error(
        `${card.file} rendered at ${Math.round(box.width)}x${Math.round(box.height)}, ` +
        `expected ${card.width}x${card.height}`
      );
    }
    const out = card.dir
      ? path.join(__dirname, '..', '..', 'public', 'assets', card.dir)
      : OUT;
    fs.mkdirSync(out, { recursive: true });
    await el.screenshot({ path: path.join(out, card.file) });
    console.log(`${card.file.padEnd(12)} ${card.width}x${card.height}  ${card.label}`);
  }

  await browser.close();
})();
