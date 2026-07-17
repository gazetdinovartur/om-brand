import { chromium } from 'playwright';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, '..', 'public/uploads/cases');
fs.mkdirSync(outDir, { recursive: true });
fs.mkdirSync(path.join(outDir, 'gallery'), { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  deviceScaleFactor: 1,
  locale: 'ru-RU',
});
const page = await context.newPage();

const shots = [
  { url: 'https://lab.arturlun.ru/', file: 'sgl-cover.png' },
  { url: 'https://lab.arturlun.ru/about', file: 'gallery/sgl-about.png' },
  { url: 'https://music.arturlun.ru/', file: 'om-cover.png' },
  { url: 'https://music.arturlun.ru/about', file: 'gallery/om-about.png' },
];

for (const shot of shots) {
  try {
    await page.goto(shot.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(2500);
    await page.screenshot({ path: path.join(outDir, shot.file), fullPage: false });
    console.log('OK', shot.file);
  } catch (e) {
    console.error('FAIL', shot.file, e.message);
  }
}

await browser.close();
