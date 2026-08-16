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
    
    // Test 1: Login page
    console.log('Navigating to http://127.0.0.1:8000/login...');
    const loginRes = await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle2' });
    console.log(`Login page HTTP status: ${loginRes.status()}`);
    await new Promise(r => setTimeout(r, 600));
    await page.screenshot({ path: path.join(__dirname, '../public/images/screenshots/auth-login-split.png') });
    console.log('Login split screenshot saved.');

    // Test 2: Register page
    console.log('Navigating to http://127.0.0.1:8000/register...');
    const regRes = await page.goto('http://127.0.0.1:8000/register', { waitUntil: 'networkidle2' });
    console.log(`Register page HTTP status: ${regRes.status()}`);
    await new Promise(r => setTimeout(r, 600));
    await page.screenshot({ path: path.join(__dirname, '../public/images/screenshots/auth-register-split.png') });
    console.log('Register split screenshot saved.');

    await browser.close();
})();
