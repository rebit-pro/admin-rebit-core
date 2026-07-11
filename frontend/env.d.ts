/// <reference types="vite/client" />

import 'vue-router';

declare global {
  interface GeeTestCaptchaValidateResult {
    lot_number: string;
    captcha_output: string;
    pass_token: string;
    gen_time: string;
  }

  interface GeeTestCaptchaInstance {
    showCaptcha(): void;
    getValidate(): GeeTestCaptchaValidateResult | false;
    onReady(callback: () => void): GeeTestCaptchaInstance;
    onSuccess(callback: () => void): GeeTestCaptchaInstance;
    onError(callback: (error: unknown) => void): GeeTestCaptchaInstance;
    onClose?(callback: () => void): GeeTestCaptchaInstance;
    destroy?(): void;
  }

  interface GeeTestInitConfig {
    captchaId: string;
    product?: 'bind' | 'popup' | 'float';
    language?: string;
  }

  interface ImportMetaEnv {
    readonly VITE_API_URL: string;
    readonly VITE_APP_VERSION?: string;
    readonly VITE_GEETEST_CAPTCHA_ID?: string;
    readonly VITE_API_MOCKS_ENABLED?: string;
  }

  interface Window {
    initGeetest4?: (config: GeeTestInitConfig, callback: (captcha: GeeTestCaptchaInstance) => void) => void;
  }
}

declare module 'vue-router' {
  interface RouteMeta {
    title?: string;
    description?: string;
    requiresAuth?: boolean;
  }
}
