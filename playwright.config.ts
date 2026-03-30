import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for Arabic output quality E2E tests.
 * See https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
    testDir: './tests/playwright',
    timeout: 15 * 60 * 1000, // 15 min global timeout (LLM pipeline is slow)
    expect: { timeout: 10_000 },
    fullyParallel: false, // Pipeline tests must run sequentially (shared DB)
    retries: 0,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],

    use: {
        baseURL: process.env.APP_URL ?? 'http://localhost:80',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
