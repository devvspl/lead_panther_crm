import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

(async () => {
    const dir = path.join(__dirname, '../public/images/screenshots');
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    const browser = await puppeteer.launch({
        headless: 'new',
        defaultViewport: { width: 1440, height: 900, deviceScaleFactor: 1.5 }
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

    // 1. PIPELINE SECTION SCREENSHOTS
    console.log('Capturing Pipeline Kanban...');
    await page.goto('http://127.0.0.1:8000/leads', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: path.join(dir, 'pipeline-kanban.png') });

    console.log('Capturing Pipeline Detail...');
    const leadCard = await page.$('[data-lead-id]');
    if (leadCard) {
        await leadCard.click();
        await new Promise(r => setTimeout(r, 1500));
        await page.screenshot({ path: path.join(dir, 'pipeline-detail.png') });
    } else {
        console.log('No lead card found for detail view screenshot');
    }

    console.log('Capturing Pipeline SLA Breached filter...');
    await page.goto('http://127.0.0.1:8000/leads', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1000));
    const checkbox = await page.$('input[wire\\:model\\.live="sla_breached"]');
    if (checkbox) {
        await checkbox.click();
        await new Promise(r => setTimeout(r, 1500));
    }
    await page.screenshot({ path: path.join(dir, 'pipeline-sla.png') });

    console.log('Capturing Pipeline Filters & Export...');
    await page.goto('http://127.0.0.1:8000/leads', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1000));
    const searchInput = await page.$('input[placeholder*="Search"]');
    if (searchInput) {
        await searchInput.type('Skyline');
        await new Promise(r => setTimeout(r, 1500));
    }
    await page.screenshot({ path: path.join(dir, 'pipeline-export.png') });

    // 2. CREDIT WALLET SECTION SCREENSHOTS
    console.log('Capturing Credits Overview...');
    await page.goto('http://127.0.0.1:8000/credits', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: path.join(dir, 'credits-overview.png') });

    console.log('Capturing Credits Recharge Modal...');
    const rechargeBtn = await page.$('button[wire\\:click="openRechargeModal"]');
    if (rechargeBtn) {
        await rechargeBtn.click();
        await new Promise(r => setTimeout(r, 1500));
        await page.screenshot({ path: path.join(dir, 'credits-recharge.png') });
    } else {
        await page.screenshot({ path: path.join(dir, 'credits-recharge.png') });
    }

    console.log('Capturing Credits Transaction History...');
    await page.goto('http://127.0.0.1:8000/credits', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1000));
    await page.evaluate(() => window.scrollBy(0, 350));
    await new Promise(r => setTimeout(r, 1000));
    await page.screenshot({ path: path.join(dir, 'credits-history.png') });

    // 3. REPORTS SECTION SCREENSHOTS
    console.log('Capturing Reports Source Performance...');
    await page.goto('http://127.0.0.1:8000/reports', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: path.join(dir, 'reports-source.png') });

    console.log('Capturing Reports SLA Dashboard...');
    const slaTabBtn = await page.$('button[wire\\:click*="sla"]');
    if (slaTabBtn) {
        await slaTabBtn.click();
        await new Promise(r => setTimeout(r, 1500));
    }
    await page.screenshot({ path: path.join(dir, 'reports-sla.png') });

    console.log('Capturing Reports Revenue...');
    const revenueTabBtn = await page.$('button[wire\\:click*="revenue"]');
    if (revenueTabBtn) {
        await revenueTabBtn.click();
        await new Promise(r => setTimeout(r, 1500));
    }
    await page.screenshot({ path: path.join(dir, 'reports-revenue.png') });

    console.log('Capturing Reports Leaderboard/Audit...');
    const execTabBtn = await page.$('button[wire\\:click*="executive"]');
    if (execTabBtn) {
        await execTabBtn.click();
        await new Promise(r => setTimeout(r, 1500));
    }
    await page.screenshot({ path: path.join(dir, 'reports-leaderboard.png') });

    console.log('All screenshots captured successfully!');
    await browser.close();
})();
