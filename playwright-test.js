const { chromium } = require('playwright');

const BASE_URL = 'http://localhost:8000';
const EMAIL = 'test@example.com';
const PASSWORD = 'password123';

// Test case data - a realistic Saudi legal case
const TEST_CASE = {
  title: 'قضية نزاع عقاري - اختبار النظام',
  client_name: 'محمد أحمد العمري',
  intake_text: `
موكلي محمد أحمد العمري يطلب تقديم دعوى قضائية ضد شركة التطوير العقاري لمدينة الرياض بسبب:

1. الإخلال بعقد البيع المبرم بتاريخ 01/01/1445هـ لشراء وحدة سكنية في مشروع "أبراج النخيل".
2. عدم تسليم الوحدة في الموعد المحدد (01/07/1445هـ) مع مرور أكثر من 8 أشهر على موعد التسليم.
3. رفض الشركة رد مبلغ التأمين البالغ 50,000 ريال سعودي.
4. وجود عيوب إنشائية ظاهرة في الوحدات المسلمة للجيران.

الأدلة المتوفرة:
- عقد البيع الأصلي موثق من كتابة العدل.
- إيصالات الدفع الكاملة بمبلغ 750,000 ريال.
- مراسلات بريد إلكتروني مع الشركة تفيد بالتأخير.
- شهادات من جيران يعانون من نفس المشكلة.

المطلوب: مطالبة الشركة بتسليم الوحدة السكنية فوراً أو استرداد كامل المبلغ المدفوع مع التعويض عن الأضرار الناجمة عن التأخير وفق نظام العقارات السعودي.
  `.trim(),
};

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  const browser = await chromium.launch({
    headless: false,
    slowMo: 300,
  });

  const context = await browser.newContext({
    viewport: { width: 1400, height: 900 },
  });

  const page = await context.newPage();

  // Capture console errors
  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
      console.log('[BROWSER ERROR]', msg.text());
    }
  });

  try {
    console.log('\n=== STEP 1: Login ===');
    await page.goto(`${BASE_URL}/login`);
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    console.log('✅ Logged in successfully');

    console.log('\n=== STEP 2: Navigate to Create Case ===');
    await page.goto(`${BASE_URL}/cases/create`);
    await page.waitForSelector('input[name="title"]', { timeout: 10000 });
    console.log('✅ Create case page loaded');

    console.log('\n=== STEP 3: Fill case form ===');
    await page.fill('input[name="title"]', TEST_CASE.title);
    await page.fill('input[name="client_name"]', TEST_CASE.client_name);
    await page.fill('textarea[name="intake_text"]', TEST_CASE.intake_text);

    // Take a screenshot before submit
    await page.screenshot({ path: 'screenshot-01-create-form.png' });
    console.log('✅ Form filled');

    console.log('\n=== STEP 4: Submit Case ===');
    await page.click('button[type="submit"]');

    // Wait for redirect to show page
    await page.waitForURL('**/cases/**', { timeout: 15000 });
    const caseUrl = page.url();
    console.log('✅ Case created, URL:', caseUrl);

    await page.screenshot({ path: 'screenshot-02-case-show-initial.png' });

    console.log('\n=== STEP 5: Waiting for Phase 1 to complete (modal should appear without refresh) ===');
    console.log('   Waiting up to 3 minutes for the approval modal to appear...');

    // Wait for Phase 1 approval modal to appear in real-time (no refresh needed)
    let modalAppeared = false;
    const modalSelector = '#phase2ApprovalModal';
    const startWait = Date.now();
    const maxWait = 3 * 60 * 1000; // 3 minutes

    while (!modalAppeared && (Date.now() - startWait) < maxWait) {
      try {
        const modal = await page.$(modalSelector);
        if (modal) {
          const display = await modal.evaluate(el => window.getComputedStyle(el).display);
          if (display !== 'none') {
            modalAppeared = true;
            console.log(`✅ Approval modal appeared in real-time after ${Math.round((Date.now() - startWait) / 1000)}s`);
            break;
          }
        }
      } catch (e) {}
      await sleep(1000);
    }

    if (!modalAppeared) {
      console.log('⚠️  Modal did not appear in real-time. Checking if page refresh shows it...');
      await page.reload();
      await page.waitForLoadState('networkidle', { timeout: 10000 });

      const modal = await page.$(modalSelector);
      if (modal) {
        const display = await modal.evaluate(el => window.getComputedStyle(el).display);
        if (display !== 'none') {
          console.log('  Modal appeared after refresh - real-time delivery still has issue');
        } else {
          // Check case status
          const status = await page.evaluate(() => {
            return document.querySelector('[data-status]')?.dataset?.status || 'unknown';
          });
          console.log('  Modal not visible. Current page status indicators:', status);
        }
      } else {
        console.log('  Modal element not found. Page might have different status.');
      }
    }

    await page.screenshot({ path: 'screenshot-03-approval-modal.png' });

    console.log('\n=== STEP 6: Approving Phase 2 ===');

    // Wait for the proceed button to be enabled
    try {
      await page.waitForSelector('#proceedButton:not([disabled])', { timeout: 45000 });
      console.log('✅ Proceed button is enabled');
    } catch (e) {
      console.log('⚠️  Proceed button timeout - trying to proceed anyway');
    }

    // Click the proceed/approve button
    const proceedBtn = await page.$('#proceedButton');
    if (proceedBtn) {
      const isDisabled = await proceedBtn.isDisabled();
      if (isDisabled) {
        console.log('   Button still disabled, waiting 10s more...');
        await sleep(10000);
      }
      await page.screenshot({ path: 'screenshot-04-before-approve.png' });
      await proceedBtn.click({ force: true });
      console.log('✅ Clicked approve button');
    } else {
      // Try form submit directly
      const form = await page.$('#startPhase2Form');
      if (form) {
        await form.evaluate(f => f.submit());
        console.log('✅ Form submitted directly');
      }
    }

    // Wait for redirect back to show page
    await page.waitForLoadState('networkidle', { timeout: 15000 });
    console.log('✅ Phase 2 started, page:', page.url());

    await page.screenshot({ path: 'screenshot-05-phase2-started.png' });

    console.log('\n=== STEP 7: Waiting for Phase 2 agents to run ===');
    console.log('   Watching for agents to start (up to 5 minutes)...');

    let agentsStarted = false;
    const agentStartTime = Date.now();
    const agentMaxWait = 5 * 60 * 1000; // 5 minutes

    while (!agentsStarted && (Date.now() - agentStartTime) < agentMaxWait) {
      // Check if any agent card shows "in_progress" or "completed" state
      const agentInProgress = await page.evaluate(() => {
        const icons = document.querySelectorAll('[id^="agent-icon-"]');
        for (const icon of icons) {
          const style = icon.getAttribute('style') || '';
          const classes = icon.className;
          if (classes.includes('amber') || classes.includes('green') || classes.includes('emerald')) {
            return true;
          }
        }
        // Also check for streaming content
        const streams = document.querySelectorAll('[id^="agent-stream-"]');
        for (const stream of streams) {
          if (stream.textContent.trim().length > 20) {
            return true;
          }
        }
        return false;
      });

      if (agentInProgress) {
        agentsStarted = true;
        console.log(`✅ Agents are running! (${Math.round((Date.now() - agentStartTime) / 1000)}s after approval)`);
      } else {
        await sleep(2000);
      }
    }

    if (!agentsStarted) {
      console.log('❌ Agents did not start within 5 minutes');

      // Check for errors
      const errorText = await page.evaluate(() => {
        const errorEls = document.querySelectorAll('.text-red-600, .text-red-700, .text-red-800');
        return Array.from(errorEls).map(e => e.textContent.trim()).filter(t => t.length > 0).join(' | ');
      });
      if (errorText) {
        console.log('   Error messages found:', errorText.substring(0, 200));
      }
    }

    await page.screenshot({ path: 'screenshot-06-agents-running.png' });

    // Wait a bit more to see more agent progress
    console.log('\n=== STEP 8: Waiting for Phase 2 to make progress (watching for 2 min) ===');
    await sleep(120000);

    await page.screenshot({ path: 'screenshot-07-phase2-progress.png' });

    const finalStatus = await page.evaluate(() => {
      // Get case status from the page
      const badge = document.querySelector('.text-amber-700, .text-green-700, .text-emerald-700, .text-red-700');
      return badge ? badge.textContent.trim() : 'unknown';
    });
    console.log('Current status indicator:', finalStatus);

    console.log('\n=== TEST COMPLETE ===');
    console.log('Browser errors found:', consoleErrors.length);
    if (consoleErrors.length > 0) {
      consoleErrors.slice(0, 5).forEach(e => console.log(' -', e));
    }

  } catch (error) {
    console.error('\n❌ Test failed:', error.message);
    await page.screenshot({ path: 'screenshot-error.png' }).catch(() => {});
  } finally {
    console.log('\nTest screenshots saved to current directory.');
    await browser.close();
  }
}

run().catch(console.error);
