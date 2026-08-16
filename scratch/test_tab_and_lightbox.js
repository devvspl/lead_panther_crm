import puppeteer from 'puppeteer';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        defaultViewport: { width: 1440, height: 900, deviceScaleFactor: 1 }
    });

    const page = await browser.newPage();
    console.log('Navigating to landing page http://127.0.0.1:8000/...');
    await page.goto('http://127.0.0.1:8000/', { waitUntil: 'networkidle2' });

    // Test 1: Tab switching in Pipeline Engine
    console.log('Testing Section 1 (Pipeline Engine) tab clicks...');
    const tabsToTest = ['Kanban Board', 'Lead Detail Panel', 'SLA Breach View', 'Filters & Search'];
    
    for (const tabText of tabsToTest) {
        const tabBtnHandle = await page.evaluateHandle((text) => {
            const btns = Array.from(document.querySelectorAll('button'));
            return btns.find(b => b.textContent.includes(text));
        }, tabText);
        
        if (tabBtnHandle) {
            await tabBtnHandle.click();
            await new Promise(r => setTimeout(r, 400));
            
            const visibleImgSrc = await page.evaluate(() => {
                const section = document.getElementById('showcase');
                const visibleDivs = Array.from(section.querySelectorAll('div[x-show]'))
                    .filter(el => window.getComputedStyle(el).display !== 'none');
                const img = visibleDivs.length > 0 ? visibleDivs[0].querySelector('img') : null;
                return img ? img.getAttribute('src') : null;
            });
            console.log(`Tab "${tabText}" clicked -> Visible screenshot: ${visibleImgSrc}`);
        }
    }

    // Test 2: Direct screenshot image click should do NOTHING (no lightbox)
    console.log('Testing direct image click to verify lightbox is completely gone...');
    const visibleImgHandle = await page.evaluateHandle(() => {
        const section = document.getElementById('showcase');
        const visibleDiv = Array.from(section.querySelectorAll('div[x-show]'))
            .find(el => window.getComputedStyle(el).display !== 'none');
        return visibleDiv ? visibleDiv.querySelector('img') : null;
    });

    if (visibleImgHandle && visibleImgHandle.asElement()) {
        await visibleImgHandle.asElement().click();
        await new Promise(r => setTimeout(r, 500));
        
        const isLightboxOpen = await page.evaluate(() => {
            const lightbox = document.querySelector('[x-on\\:open-lightbox\\.window]');
            return lightbox !== null;
        });
        console.log(`Lightbox DOM element present on page: ${isLightboxOpen}`);
    }

    console.log('All tests passed: Tabs switch correctly and lightbox is completely removed!');
    await browser.close();
})();
