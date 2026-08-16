import puppeteer from 'puppeteer';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        defaultViewport: { width: 1440, height: 2800, deviceScaleFactor: 1.5 }
    });

    const page = await browser.newPage();
    console.log('Navigating to landing page...');
    await page.goto('http://127.0.0.1:8000/', { waitUntil: 'networkidle2' });

    // Scroll to showcase section
    await page.evaluate(() => {
        const el = document.getElementById('showcase');
        if (el) el.scrollIntoView();
    });
    await new Promise(r => setTimeout(r, 1000));

    console.log('Capturing showcase section...');
    const showcaseEl = await page.$('#showcase');
    if (showcaseEl) {
        await showcaseEl.screenshot({ path: path.join(__dirname, '../public/images/screenshots/landing-showcase-redesign.png') });
        console.log('Showcase section screenshot saved to public/images/screenshots/landing-showcase-redesign.png');
    }

    await browser.close();
})();
