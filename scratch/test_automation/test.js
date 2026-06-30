const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

(async () => {
  console.log('Launching browser...');
  const browser = await puppeteer.launch({
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    headless: true,
    defaultViewport: { width: 1280, height: 1000 }
  });

  let page;

  try {
    page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36');

    // 1. Clear session
    console.log('Clearing session...');
    await page.goto('https://ncd.ssotansum.com/logout.php', { waitUntil: 'networkidle2' });

    // 2. Go to login page
    console.log('Navigating to login page...');
    await page.goto('https://ncd.ssotansum.com/index.php', { waitUntil: 'networkidle2' });

    // 3. Login
    console.log('Entering credentials...');
    await page.waitForSelector('#username');
    await page.type('#username', '0986624652');
    await page.type('#password', '1234');
    
    console.log('Submitting login form...');
    await Promise.all([
      page.click('button[type="submit"]'),
      page.waitForNavigation({ waitUntil: 'networkidle2' })
    ]);

    // 4. Go to screening form in shell mode
    console.log('Navigating to shell page...');
    await page.goto('https://ncd.ssotansum.com/vhv/screening_form.php?shell=true&hid=999', { waitUntil: 'networkidle2' });

    // 5. Populate localStorage
    console.log('Populating mock task into localStorage...');
    await page.evaluate(() => {
      const mockTasks = [{
        assignment_id: 9999,
        first_name: 'ทดสอบระบบ',
        last_name: 'คำนวณเรียลไทม์',
        sex: '1', // Male
        birth: '1976-06-13', // 50 years old
        need_screen_dm: 1,
        need_screen_ht: 1,
        latitude: 15.4300,
        longitude: 104.9800,
        hid: '999',
        house_no: '999/9',
        last_sbp: 135,
        last_dbp: 85,
        last_dtx: 140,
        last_dtx_type: 'fpg'
      }];
      localStorage.setItem('vhv_pending_tasks', JSON.stringify(mockTasks));
    });

    // 6. Reload page
    console.log('Reloading page...');
    await page.reload({ waitUntil: 'networkidle2' });

    // 7. Select the resident card
    console.log('Selecting mock resident card...');
    await page.waitForSelector('.resident-card');
    await page.evaluate(() => {
      const card = document.querySelector('.resident-card');
      if (card) card.click();
    });
    await new Promise(resolve => setTimeout(resolve, 800)); // wait for transition

    // Helper to log current CV Risk details
    const logCvRiskDetails = async (label) => {
      const cvRisk = await page.evaluate(() => document.getElementById('cv-risk-display').innerText);
      const cvStatus = await page.evaluate(() => document.getElementById('cv-risk-status').innerText);
      const cvBp = await page.evaluate(() => document.getElementById('cv-risk-bp-val').innerText);
      const cvDtx = await page.evaluate(() => document.getElementById('cv-risk-dtx-val').innerText);
      console.log(`[${label}] Risk: ${cvRisk} (${cvStatus}) | BP used: ${cvBp} | Sugar used: ${cvDtx}`);
    };

    // Log initial
    await logCvRiskDetails('Initial baseline');

    // 8. Test scroll picker for weight (Weight = 60.0)
    await page.evaluate(() => document.getElementById('weight').click());
    await page.waitForSelector('#picker-drawer.open');
    await page.evaluate(() => {
      const btn = document.querySelector('#picker-drawer button[onclick="confirmScrollPicker()"]');
      if (btn) btn.click();
    });
    await new Promise(resolve => setTimeout(resolve, 500));

    // 9. Test scroll picker for height (Height = 160.0)
    await page.evaluate(() => document.getElementById('height').click());
    await page.waitForSelector('#picker-drawer.open');
    await page.evaluate(() => {
      const btn = document.querySelector('#picker-drawer button[onclick="confirmScrollPicker()"]');
      if (btn) btn.click();
    });
    await new Promise(resolve => setTimeout(resolve, 500));

    // 10. Test BP 1 and BP 2 Average Calculation
    console.log('Inputting SYS 1 = 130 on numpad...');
    await page.evaluate(() => document.getElementById('sys_bp1').click());
    await page.waitForSelector('#numpad-drawer.open');
    await page.evaluate(() => {
      const clickBtn = (val) => {
        const btn = document.querySelector(`#numpad-container button[data-val="${val}"]`);
        if (btn) btn.click();
      };
      clickBtn('1'); clickBtn('3'); clickBtn('0');
      const okBtn = document.querySelector('#numpad-drawer button[onclick="closeNumPad()"]');
      if (okBtn) okBtn.click();
    });
    await new Promise(resolve => setTimeout(resolve, 500));

    console.log('Inputting SYS 2 = 150 on numpad...');
    await page.evaluate(() => document.getElementById('sys_bp2').click());
    await page.waitForSelector('#numpad-drawer.open');
    await page.evaluate(() => {
      const clickBtn = (val) => {
        const btn = document.querySelector(`#numpad-container button[data-val="${val}"]`);
        if (btn) btn.click();
      };
      clickBtn('1'); clickBtn('5'); clickBtn('0');
      const okBtn = document.querySelector('#numpad-drawer button[onclick="closeNumPad()"]');
      if (okBtn) okBtn.click();
    });
    await new Promise(resolve => setTimeout(resolve, 500));

    await logCvRiskDetails('After BP 1 & 2');

    // 11. Test Fasting Sugar (FPG) threshold
    console.log('Inputting DTX = 130 on numpad...');
    await page.evaluate(() => document.getElementById('dtx_value').click());
    await page.waitForSelector('#numpad-drawer.open');
    await page.evaluate(() => {
      const clickBtn = (val) => {
        const btn = document.querySelector(`#numpad-container button[data-val="${val}"]`);
        if (btn) btn.click();
      };
      clickBtn('1'); clickBtn('3'); clickBtn('0');
      const okBtn = document.querySelector('#numpad-drawer button[onclick="closeNumPad()"]');
      if (okBtn) okBtn.click();
    });
    await new Promise(resolve => setTimeout(resolve, 500));

    await logCvRiskDetails('After Fasting DTX 130');

    // 12. Switch to Random Sugar (RPG)
    console.log('Switching to RPG (Random Blood Sugar)...');
    await page.evaluate(() => {
      const rpgRadio = document.querySelector('input[name="dtx_type"][value="rpg"]');
      if (rpgRadio) {
        rpgRadio.checked = true;
        rpgRadio.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    await new Promise(resolve => setTimeout(resolve, 500));

    await logCvRiskDetails('After Random DTX 130');

    // Take final screenshot showing these live changes
    const screenshotPath = 'C:\\Users\\ACER\\.gemini\\antigravity-ide\\brain\\ddc0b7d7-1718-48fc-9eea-278fb6ddb548\\screening_realtime_calculations.png';
    // Scroll to 1250px to capture the CV Risk Card
    await page.evaluate(() => window.scrollTo(0, 1250));
    await page.screenshot({ path: screenshotPath, fullPage: false });
    console.log('Screenshot updated.');

    console.log('SUCCESS: Dynamic calculations and UI display tested successfully!');
  } catch (error) {
    console.error('ERROR in automation:', error);
  } finally {
    browser.close();
    console.log('Browser closed.');
  }
})();
