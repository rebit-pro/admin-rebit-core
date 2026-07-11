import { Given, When, Then } from '@cucumber/cucumber';
import { expect } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';

const selectors = {
  emailInput: '[data-testid="login-email"] input',
  passwordInput: '[data-testid="login-password"] input',
  submitButton: '[data-testid="login-submit"]',
  apiError: '[data-testid="login-api-error"]',
  dashboardGreeting: 'h1'
} as const;

Given('пользователь открывает страницу входа', async function (this: CustomWorld) {
  if (null === this.page) {
    throw new Error('Страница Playwright не инициализирована');
  }

  await this.page.goto(`${this.baseUrl}/login`, { waitUntil: 'networkidle' });
  await expect(this.page).toHaveURL(/\/login$/);
  await expect(this.page.locator(selectors.submitButton)).toContainText('Войти');
});

When('пользователь авторизуется с валидными mock-данными', async function (this: CustomWorld) {
  if (null === this.page) {
    throw new Error('Страница Playwright не инициализирована');
  }

  await this.page.locator(selectors.emailInput).fill('owner@rebit.test');
  await this.page.locator(selectors.passwordInput).fill('secret123');
  await this.page.locator(selectors.submitButton).click();
});

When('пользователь вводит email {string} и пароль {string}', async function (this: CustomWorld, email: string, password: string) {
  if (null === this.page) {
    throw new Error('Страница Playwright не инициализирована');
  }

  await this.page.locator(selectors.emailInput).fill(email);
  await this.page.locator(selectors.passwordInput).fill(password);
  await this.page.locator(selectors.submitButton).click();
});

Then('пользователь должен быть перенаправлен на дашборд', async function (this: CustomWorld) {
  if (null === this.page) {
    throw new Error('Страница Playwright не инициализирована');
  }

  await expect(this.page).toHaveURL(/\/dashboard$/);
});

Then('пользователь должен увидеть приветствие на дашборде', async function (this: CustomWorld) {
  if (null === this.page) {
    throw new Error('Страница Playwright не инициализирована');
  }

  await expect(this.page.locator(selectors.dashboardGreeting)).toContainText(/Добро пожаловать,\s+(Владелец аккаунта|owner@rebit\.test)/);
});

Then('пользователь должен увидеть ошибку авторизации {string}', async function (this: CustomWorld, errorText: string) {
  if (null === this.page) {
    throw new Error('Страница Playwright не инициализирована');
  }

  await expect(this.page.locator(selectors.apiError)).toContainText(errorText);
  await expect(this.page).toHaveURL(/\/login$/);
});
