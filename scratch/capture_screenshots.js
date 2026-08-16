const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

(async () => {
    const dir = path.join(__dirname, '../public/images/screenshots');
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    const browser = await puppeteer.launch({
        headless: 'new',
        defaultViewport: { width: 1440, height: 900 }
    });

    const page = await browser.newPage();

    console.log('Navigating to login...');
    await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle2' });

    console.log('Logging in as admin@leadpanther.com...');
    await page.type('#email', 'admin@leadpanther.com');
    await page.type('#password', 'password');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.click('button[type="submit"]')
    ]);

    console.log('Capturing Dashboard...');
    await page.goto('http://127.0.0.1:8000/dashboard', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(dir, 'dashboard.png') });

    console.log('Capturing Leads Kanban...');
    await page.goto('http://127.0.0.1:8000/leads', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(dir, 'leads-kanban.png') });

    console.log('Capturing Reports...');
    await page.goto('http://127.0.0.1:8000/reports', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(dir, 'reports.png') });

    console.log('Capturing Credits & Wallet...');
    await page.goto('http://127.0.0.1:8000/credits', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 2000));
    await page.screenshot({ path: path.join(dir, 'credits.png') });

    console.log('Screenshots captured successfully!');
    await browser.close();
})();
