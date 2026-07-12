/// <reference types="vite/client" />

import 'vue-router';

declare global {
  type SmartCaptchaSubscribeEvent = 'challenge-visible' | 'challenge-hidden' | 'network-error' | 'success' | 'token-expired';

  interface SmartCaptchaRenderParams {
    sitekey: string;
    invisible?: boolean;
    hl?: string;
    test?: boolean;
    hideShield?: boolean;
    shieldPosition?: 'top-left' | 'center-left' | 'bottom-left' | 'top-right' | 'center-right' | 'bottom-right';
    callback?: (token: string) => void;
  }

  interface SmartCaptcha {
    render(container: HTMLElement | string, params: SmartCaptchaRenderParams): number;
    execute(widgetId?: number): void;
    reset(widgetId?: number): void;
    destroy(widgetId?: number): void;
    getResponse(widgetId?: number): string;
    subscribe(widgetId: number, event: SmartCaptchaSubscribeEvent, callback: () => void): () => void;
  }

  interface ImportMetaEnv {
    readonly VITE_API_URL: string;
    readonly VITE_APP_VERSION?: string;
    readonly VITE_SMARTCAPTCHA_CLIENT_KEY?: string;
    readonly VITE_API_MOCKS_ENABLED?: string;
  }

  interface Window {
    smartCaptcha?: SmartCaptcha;
    __onSmartCaptchaLoad?: () => void;
  }
}

declare module 'vue-router' {
  interface RouteMeta {
    title?: string;
    description?: string;
    requiresAuth?: boolean;
  }
}
