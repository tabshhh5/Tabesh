const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto('http://localhost:8080/test-order-creator-modal.html');
    
    // Open modal
    await page.click('#open-modal-btn');
    await page.waitForTimeout(500);
    
    // Scroll within modal body
    await page.evaluate(() => {
        const modalBody = document.querySelector('.tabesh-modal-body');
        modalBody.scrollTop = modalBody.scrollHeight / 2;
    });
    await page.waitForTimeout(300);
    
    // Take screenshot
    await page.screenshot({ 
        path: '/tmp/modal-scrolled.png',
        fullPage: true 
    });
    
    await browser.close();
})();
