const playwright = require('playwright');
const { config } = require('../fixtures/config');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(config.redcapUrl);
    await page.screenshot({ path: 'screenshots/screenshot0.png' });
    console.log('Page loaded.');

    await page.locator('input[name="dl-option"][value="upload"]').waitFor({ state: 'visible' });
    await page.locator('input[name="dl-option"][value="upload"]').check();
    await page.screenshot({ path: 'screenshots/screenshot1.png' });
    console.log('Checked manual upload.');

    await page.locator('input#installer-upload').waitFor({ state: 'visible' });
    await page.locator('input#installer-upload').setInputFiles('ZIPFILE');
    await page.screenshot({ path: 'screenshots/screenshot2.png' });
    console.log('Added file.');

    await page.locator('input[name="init-table"]').check();
    await page.screenshot({ path: 'screenshots/screenshot3.png' });
    console.log('Checked option to add users.');

    await page.locator('input[name="init-table-email"]').waitFor({ state: 'visible' });
    await page.locator('input[name="init-table-email"]').fill('andrew.poppe@yale.edu');
    await page.screenshot({ path: 'screenshots/screenshot4.png' });
    console.log('Filled email.');

    await page.locator('button.initiate-installation').click();
    await page.screenshot({ path: 'screenshots/screenshot5.png' });
    console.log('Clicked the button.');

    //await page.locator('div', { hasText: 'Building your REDCap Server' }).waitFor({ state: 'visible' });

    await page.locator('div.alert-success').first().waitFor({ state: 'visible', timeout: 300000 });
    console.log('Success!');
    await page.waitForTimeout(10000);
    await page.screenshot({ path: 'screenshots/screenshot6.png' });

    await context.close();
    await browser.close();
})();
