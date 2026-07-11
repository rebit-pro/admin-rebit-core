import { Before, After, Status, setDefaultTimeout, type ITestCaseHookParameter } from '@cucumber/cucumber';
import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { CustomWorld } from './world.js';

setDefaultTimeout(60_000);

const isHeadless = 'false' !== (process.env.PLAYWRIGHT_HEADLESS?.trim().toLowerCase() ?? 'true');

Before(async function (this: CustomWorld) {
  this.browser = await chromium.launch({ headless: isHeadless });
  this.context = await this.browser.newContext();
  this.page = await this.context.newPage();

  await this.page.goto(this.baseUrl, { waitUntil: 'networkidle' });
  await this.page.evaluate(() => {
    localStorage.clear();
    const mocks = (window as Window & { __REBIT_MOCKS__?: { reset?: () => void } }).__REBIT_MOCKS__;
    mocks?.reset?.();
  });
});

After(async function (this: CustomWorld, { result, pickle }: ITestCaseHookParameter) {
  if (null !== this.page && undefined !== result && Status.FAILED === result.status) {
    const screenshotsDirectory = join('reports', 'e2e', 'screenshots');
    await mkdir(screenshotsDirectory, { recursive: true });

    const screenshotPath = join(
      screenshotsDirectory,
      `${pickle.name.replace(/[^a-zA-Z0-9-_]+/g, '_').replace(/^_+|_+$/g, '') || 'scenario'}.png`
    );

    await this.page.screenshot({ path: screenshotPath, fullPage: true });
    const screenshot = await this.page.screenshot();
    this.attach(screenshot, 'image/png');
  }

  await this.page?.close();
  await this.context?.close();
  await this.browser?.close();

  this.page = null;
  this.context = null;
  this.browser = null;
});
