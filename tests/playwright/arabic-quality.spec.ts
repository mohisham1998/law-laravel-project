/**
 * Arabic Output Quality — End-to-End Playwright Test
 *
 * Creates a case from the sample case/, runs all 13 agents, and asserts
 * that the final brief (13_final_brief_v3.md) meets Arabic quality criteria:
 *   1. Starts with "بسم الله الرحمن الرحيم"
 *   2. Contains ordinal Arabic section headings (أولاً → سادساً)
 *   3. Contains appendix section (ملحق)
 *   4. Contains three-tier requests (أصلية / احتياطية / تبعية)
 *   5. Zero consecutive English words in body prose
 *
 * Prerequisites:
 *   - Application running at APP_URL (default: http://localhost:80)
 *   - Sample case laws loaded in RAG database
 *   - User account exists (PLAYWRIGHT_EMAIL / PLAYWRIGHT_PASSWORD)
 *   - Phase 3 trigger is manual POST to /cases/{id}/start-phase3
 */

import { test, expect, Page } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const APP_URL = process.env.APP_URL ?? 'http://localhost:80';
const SAMPLE_CASE_DIR = path.resolve(__dirname, '../../sample case');
const USER_EMAIL = process.env.PLAYWRIGHT_EMAIL ?? 'admin@example.com';
const USER_PASSWORD = process.env.PLAYWRIGHT_PASSWORD ?? 'password';

// Pipeline timeouts — LLM calls are slow
const PHASE_TIMEOUT_MS = 10 * 60 * 1000;  // 10 min per phase
const AGENT_POLL_INTERVAL_MS = 5_000;      // 5s between status polls

// ────────────────────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────────────────────

/** Login and navigate to the dashboard. */
async function login(page: Page): Promise<void> {
    await page.goto(`${APP_URL}/login`);
    await page.fill('input[name="email"]', USER_EMAIL);
    await page.fill('input[name="password"]', USER_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL(`${APP_URL}/dashboard`, { timeout: 15_000 });
}

/** Wait for case status to equal one of the expected values (polls API). */
async function waitForStatus(
    page: Page,
    caseId: string,
    expectedStatuses: string[],
    timeoutMs: number
): Promise<string> {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
        const response = await page.request.get(`${APP_URL}/api/cases/${caseId}/status`);
        if (response.ok()) {
            const data = await response.json();
            const status = data.status ?? data.data?.status ?? '';
            if (expectedStatuses.includes(status)) {
                return status;
            }
            // Fail fast on terminal error states
            if (['failed', 'timed_out'].includes(status)) {
                throw new Error(`Pipeline reached error status: ${status}`);
            }
        }
        await page.waitForTimeout(AGENT_POLL_INTERVAL_MS);
    }
    throw new Error(`Timed out waiting for status ${expectedStatuses.join('|')} after ${timeoutMs}ms`);
}

/** Get the final brief content via API. */
async function getFinalBriefContent(page: Page, caseId: string): Promise<string> {
    // Try the outputs API first
    const response = await page.request.get(`${APP_URL}/api/cases/${caseId}/outputs/13_final_brief_v3.md`);
    if (response.ok()) {
        const data = await response.json();
        return data.content ?? data.data?.content ?? '';
    }
    // Fallback: read via case show page outputs
    throw new Error('Could not retrieve 13_final_brief_v3.md content via API');
}

// ────────────────────────────────────────────────────────────────────────────
// Test: Full pipeline → Arabic quality assertions
// ────────────────────────────────────────────────────────────────────────────

test('Sample case produces a court-ready Arabic brief', async ({ page }) => {
    test.setTimeout(PHASE_TIMEOUT_MS * 3 + 60_000);

    // ── Step 1: Login ──────────────────────────────────────────────────────
    await login(page);

    // ── Step 2: Navigate to case creation form ─────────────────────────────
    await page.goto(`${APP_URL}/cases/create`);
    await expect(page.locator('form#createCaseForm')).toBeVisible({ timeout: 10_000 });

    // ── Step 3: Fill in case details ───────────────────────────────────────
    await page.fill('input[name="title"]', 'مذكرة تعقيبية ولائحة تجريح شهود — قضية التشهير');
    const clientNameInput = page.locator('input[name="client_name"]');
    if (await clientNameInput.isVisible()) {
        await clientNameInput.fill('نورة بنت صالح وحصة بنت محمد');
    }

    // ── Step 4: Upload all documents (intake + documents) ──────────────────
    const documentFiles = [
        path.join(SAMPLE_CASE_DIR, 'intake.txt'),
        path.join(SAMPLE_CASE_DIR, 'documents', '1- صحيفة الدعوى الابتدائية (مقدمة من وكيل المدعية).txt'),
        path.join(SAMPLE_CASE_DIR, 'documents', '2- مذكرة الرد الجوابي الأولى (مقدمة من وكيل المدعى عليهما).txt'),
        path.join(SAMPLE_CASE_DIR, 'documents', '3- محضر ضبط الجلسة (سماع البينة والشهود).txt'),
        path.join(SAMPLE_CASE_DIR, 'documents', 'المستند رقم (١) مستخرج إلكتروني من نظام (ناجز).txt'),
        path.join(SAMPLE_CASE_DIR, 'documents', 'المستند رقم (٢) مستخرج رسمي من الأحوال المدنية (شريحة بيانات).txt'),
    ].filter(f => fs.existsSync(f));

    const fileInput = page.locator('input[name="attachments[]"]');
    await fileInput.setInputFiles(documentFiles);

    // ── Step 5: Submit form and get case ID ────────────────────────────────
    const [response] = await Promise.all([
        page.waitForResponse(resp => resp.url().includes('/cases') && resp.request().method() === 'POST', { timeout: 30_000 }),
        page.click('button#submitCaseBtn'),
    ]);

    // Extract case ID from redirect URL (e.g. /cases/{uuid})
    const finalUrl = page.url();
    const caseIdMatch = finalUrl.match(/\/cases\/([a-f0-9-]{36})/);
    expect(caseIdMatch, 'Expected to be redirected to /cases/{uuid} after creation').toBeTruthy();
    const caseId = caseIdMatch![1];

    // ── Step 6: Wait for Phase 1 to complete ──────────────────────────────
    await waitForStatus(page, caseId, ['phase1_completed', 'phase2_pending', 'phase2_processing', 'phase2_completed', 'awaiting_laws'], PHASE_TIMEOUT_MS);

    // ── Step 7: Wait for Phase 2 to complete ──────────────────────────────
    const phase2FinalStatus = await waitForStatus(page, caseId, ['phase2_completed', 'awaiting_laws', 'completed_with_warnings'], PHASE_TIMEOUT_MS);

    // ── Step 8: Trigger Phase 3 via POST ──────────────────────────────────
    const triggerResponse = await page.request.post(
        `${APP_URL}/cases/${caseId}/start-phase3`,
        { headers: { 'X-CSRF-TOKEN': await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''), 'Accept': 'application/json' } }
    );
    expect(triggerResponse.ok(), 'Phase 3 trigger POST should succeed').toBeTruthy();

    // ── Step 9: Wait for Phase 3 to complete ──────────────────────────────
    await waitForStatus(page, caseId, ['phase3_completed', 'completed_with_warnings'], PHASE_TIMEOUT_MS);

    // ── Step 10: Retrieve the final brief ─────────────────────────────────
    const briefContent = await getFinalBriefContent(page, caseId);
    expect(briefContent.trim().length, 'Final brief must not be empty').toBeGreaterThan(100);

    // ── Step 11: Assert "بسم الله الرحمن الرحيم" is the first content ──────
    const firstLine = briefContent.split('\n').find(l => l.trim().length > 0) ?? '';
    expect(firstLine).toContain('بسم الله الرحمن الرحيم');

    // ── Step 12: Assert ordinal Arabic section headings ────────────────────
    expect(briefContent).toContain('أولاً');
    expect(briefContent).toContain('ثانياً');
    expect(briefContent).toContain('ثالثاً');

    // ── Step 13: Assert appendix section is present ────────────────────────
    const hasAppendix = briefContent.includes('ملحق') || briefContent.includes('الملاحق');
    expect(hasAppendix, 'Final brief must contain an appendix section (ملحق)').toBeTruthy();

    // ── Step 14: Assert three-tier requests ────────────────────────────────
    expect(briefContent).toContain('الطلبات الأصلية');
    expect(briefContent).toContain('الطلبات الاحتياطية');
    expect(briefContent).toContain('الطلبات التبعية');

    // ── Step 15: Assert zero consecutive English words in body prose ────────
    // Allow isolated English characters (abbreviations, proper nouns) but
    // flag 3+ consecutive ASCII words — indicates English sentence leakage.
    const englishSentencePattern = /\b[a-zA-Z]{2,}\s+[a-zA-Z]{2,}\s+[a-zA-Z]{2,}\b/;
    const englishMatch = briefContent.match(englishSentencePattern);
    expect(
        englishMatch,
        `Final brief must not contain consecutive English words. Found: "${englishMatch?.[0]}"`
    ).toBeNull();
});

// ────────────────────────────────────────────────────────────────────────────
// Test: Portal system message editor shows full Arabic spec
// ────────────────────────────────────────────────────────────────────────────

test('Portal shows full Arabic behavioral spec for Agent 8', async ({ page }) => {
    test.setTimeout(30_000);

    await login(page);

    // Call the agent system message API directly
    const response = await page.request.get(`${APP_URL}/api/agent-system-messages/8`);
    expect(response.ok()).toBeTruthy();

    const data = await response.json();
    const systemMessage: string = data.system_message ?? data.data?.system_message ?? '';

    // Must contain the full citation format instruction — not a 2-3 sentence stub
    expect(systemMessage.length, 'System message must be more than a 2-3 sentence stub').toBeGreaterThan(200);
    // Check for the mandatory citation format instruction that appears in Agent 8's system prompt
    expect(systemMessage).toContain('الاستشهاد الإلزامي');

    // Must be pure Arabic — no English section headers
    const englishHeaders = /^##\s+[a-zA-Z]/m;
    expect(systemMessage.match(englishHeaders), 'System message must not contain English section headers').toBeNull();
});

// ────────────────────────────────────────────────────────────────────────────
// Test: RAG context contains no English labels
// ────────────────────────────────────────────────────────────────────────────

test('Agent 3 RAG context contains no English labels', async ({ page }) => {
    test.setTimeout(30_000);

    await login(page);

    // Navigate to an existing completed case and check Agent 3 context
    // This test uses the most recent completed case if available
    const casesResponse = await page.request.get(`${APP_URL}/api/cases?status=phase2_completed&per_page=1`);
    if (!casesResponse.ok()) {
        test.skip(true, 'No completed cases available for RAG context check');
        return;
    }

    const casesData = await casesResponse.json();
    const cases = casesData.data ?? casesData.cases ?? [];
    if (cases.length === 0) {
        test.skip(true, 'No completed cases available for RAG context check');
        return;
    }

    const caseId = cases[0].id;
    const outputResponse = await page.request.get(`${APP_URL}/api/cases/${caseId}/outputs/03_chain_of_custody_summary.md`);
    if (!outputResponse.ok()) {
        test.skip(true, 'Agent 3 output not available');
        return;
    }

    const data = await outputResponse.json();
    const content: string = data.content ?? data.data?.content ?? '';

    // Check that English law_registry_id labels don't appear in the context
    expect(content).not.toMatch(/law_registry_id/i);
    expect(content).not.toMatch(/LAW_\d+/);
});
