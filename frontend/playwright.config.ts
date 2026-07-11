import { defineConfig } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL?.trim() || 'http://127.0.0.1:4173';
const isHeadless = 'false' !== (process.env.PLAYWRIGHT_HEADLESS?.trim().toLowerCase() ?? 'true');

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  use: {
    baseURL,
    browserName: 'chromium',
    headless: isHeadless,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure'
  }
});
