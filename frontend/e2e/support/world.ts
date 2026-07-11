import { setWorldConstructor, World, type IWorldOptions } from '@cucumber/cucumber';
import type { Browser, BrowserContext, Page } from '@playwright/test';

interface WorldParameters {
  baseUrl: string;
}

export class CustomWorld extends World {
  declare public parameters: WorldParameters;
  public browser: Browser | null = null;
  public context: BrowserContext | null = null;
  public page: Page | null = null;

  public constructor(options: IWorldOptions) {
    super(options);
  }

  public get baseUrl(): string {
    return this.parameters.baseUrl;
  }
}

setWorldConstructor(CustomWorld);
