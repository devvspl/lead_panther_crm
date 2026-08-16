import puppeteer from 'puppeteer';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        defaultViewport: { width: 1440, height: 900, deviceScaleFactor: 1.5 }
    });

    const page = await browser.newPage();
    console.log('Navigating to landing page...');
    await page.goto('http://127.0.0.1:8000/', { waitUntil: 'networkidle2' });

    // Scroll to architecture section
    await page.evaluate(() => {
        const el = document.getElementById('architecture');
        if (el) el.scrollIntoView();
    });
    await new Promise(r => setTimeout(r, 1000));

    console.log('Capturing architecture flow section...');
    const archEl = await page.$('#architecture');
    if (archEl) {
        await archEl.screenshot({ path: path.join(__dirname, '../public/images/screenshots/hierarchy-flow-redesign.png') });
        console.log('Hierarchy flow section screenshot saved to public/images/screenshots/hierarchy-flow-redesign.png');
    }

    await browser.close();
})();
