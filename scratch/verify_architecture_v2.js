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
    console.log('Navigating directly to http://127.0.0.1:8000/#architecture...');
    await page.goto('http://127.0.0.1:8000/#architecture', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 600));

    // Verify topbar does not obscure heading
    const isHeadingVisible = await page.evaluate(() => {
        const header = document.querySelector('header');
        const headerRect = header.getBoundingClientRect();
        const heading = document.querySelector('#architecture h2');
        const headingRect = heading.getBoundingClientRect();
        return headingRect.top >= headerRect.bottom;
    });

    console.log(`Heading visible below header without clipping: ${isHeadingVisible}`);

    // Verify level 2 icon exists
    const hasLevel2Icon = await page.evaluate(() => {
        const cards = document.querySelectorAll('#architecture .grid > div');
        if (cards.length < 2) return false;
        const lvl2Card = cards[1];
        const svg = lvl2Card.querySelector('svg');
        return svg !== null;
    });

    console.log(`Level 2 (Projects & Media) card has icon: ${hasLevel2Icon}`);

    // Capture screenshot of architecture section
    const archSection = await page.$('#architecture');
    if (archSection) {
        await archSection.screenshot({ path: path.join(__dirname, '../public/images/screenshots/architecture-stepper-redesign.png') });
        console.log('Saved screenshot to public/images/screenshots/architecture-stepper-redesign.png');
    }

    await browser.close();
})();
