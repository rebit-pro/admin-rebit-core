<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { Form } from 'vee-validate';
import type { SmartCaptchaPayload } from '@/api/auth';
import { isMockApiEnabled } from '@/mocks/config';

const show1 = ref(false);
const password = ref('');
const email = ref('');
const captchaLoading = ref(false);
const captchaReady = ref(false);
const captchaProcessing = ref(false);
const captchaError = ref('');
const apiError = ref('');
const captchaContainer = ref<HTMLElement | null>(null);

const authStore = useAuthStore();
const smartCaptchaClientKey = isMockApiEnabled ? '' : (import.meta.env.VITE_SMARTCAPTCHA_CLIENT_KEY?.trim() ?? '');
const smartCaptchaScriptSrc = 'https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&onload=__onSmartCaptchaLoad';

let captchaWidgetId: number | null = null;
let unsubscribeCallbacks: Array<() => void> = [];
let activeSetErrors: ((errors: Record<string, string>) => void) | null = null;
let captchaScript: HTMLScriptElement | null = null;

// Email validation rules
const emailRules = ref([
  (v: string) => '' !== v.trim() || 'Введите email',
  (v: string) => /.+@.+\..+/.test(v.trim()) || 'Некорректный email'
]);
// Password validation rules
const passwordRules = ref([(v: string) => '' !== v || 'Введите пароль', (v: string) => v.length >= 6 || 'Минимум 6 символов']);

/* eslint-disable @typescript-eslint/no-explicit-any */
function setApiError(message: string, setErrors?: (errors: Record<string, string>) => void): void {
  apiError.value = message;

  const applyErrors = setErrors ?? activeSetErrors;
  if (undefined !== applyErrors && null !== applyErrors) {
    applyErrors({ apiError: message });
  }
}

function handleLoginError(error: any): void {
  const message = error?.response?.data?.message ?? 'Ошибка авторизации';
  setApiError(message);
}

async function submitLogin(captcha?: SmartCaptchaPayload): Promise<void> {
  captchaProcessing.value = true;

  try {
    await authStore.login(email.value.trim(), password.value, captcha);
  } catch (error) {
    handleLoginError(error);
    // Токен SmartCaptcha одноразовый — сбрасываем виджет, чтобы повторная попытка получила новый.
    if (null !== captchaWidgetId) {
      window.smartCaptcha?.reset(captchaWidgetId);
    }
  } finally {
    captchaProcessing.value = false;
  }
}

function handleCaptchaScriptError(): void {
  captchaLoading.value = false;
  captchaProcessing.value = false;
  captchaError.value = 'Не удалось загрузить SmartCaptcha';
}

function initCaptcha(): void {
  if ('' === smartCaptchaClientKey) {
    captchaReady.value = true;
    return;
  }

  if (undefined === window.smartCaptcha || null === captchaContainer.value) {
    captchaError.value = 'Не удалось инициализировать SmartCaptcha';
    captchaLoading.value = false;
    return;
  }

  captchaWidgetId = window.smartCaptcha.render(captchaContainer.value, {
    sitekey: smartCaptchaClientKey,
    invisible: true,
    hl: 'ru',
    shieldPosition: 'bottom-right',
    callback: (token: string) => {
      if ('' === token) {
        captchaProcessing.value = false;
        setApiError('Не удалось пройти проверку CAPTCHA');
        return;
      }

      void submitLogin({ token });
    }
  });

  unsubscribeCallbacks = [
    window.smartCaptcha.subscribe(captchaWidgetId, 'challenge-hidden', () => {
      captchaProcessing.value = false;
    }),
    window.smartCaptcha.subscribe(captchaWidgetId, 'network-error', () => {
      captchaProcessing.value = false;
      captchaError.value = 'SmartCaptcha временно недоступна. Попробуйте ещё раз.';
    })
  ];

  captchaReady.value = true;
  captchaLoading.value = false;
  captchaError.value = '';
}

function loadCaptcha(): void {
  if ('' === smartCaptchaClientKey) {
    captchaReady.value = true;
    captchaLoading.value = false;
    return;
  }

  if (undefined !== window.smartCaptcha) {
    initCaptcha();
    return;
  }

  captchaLoading.value = true;
  captchaError.value = '';
  window.__onSmartCaptchaLoad = initCaptcha;

  const existingCaptchaScript = document.querySelector<HTMLScriptElement>(`script[src="${smartCaptchaScriptSrc}"]`);

  if (null !== existingCaptchaScript) {
    captchaScript = existingCaptchaScript;
    captchaScript.addEventListener('error', handleCaptchaScriptError, { once: true });

    return;
  }

  captchaScript = document.createElement('script');
  captchaScript.src = smartCaptchaScriptSrc;
  captchaScript.async = true;
  captchaScript.addEventListener('error', handleCaptchaScriptError, { once: true });

  document.head.appendChild(captchaScript);
}

function validate(_values: any, { setErrors }: any) {
  activeSetErrors = setErrors;
  captchaError.value = '';
  apiError.value = '';
  setErrors({ apiError: '' });

  if ('' === smartCaptchaClientKey) {
    return submitLogin();
  }

  if (false === captchaReady.value || null === captchaWidgetId || undefined === window.smartCaptcha) {
    setApiError('CAPTCHA ещё загружается, попробуйте через пару секунд', setErrors);
    return Promise.resolve();
  }

  captchaProcessing.value = true;
  window.smartCaptcha.execute(captchaWidgetId);

  return Promise.resolve();
}

onMounted(() => {
  loadCaptcha();
});

onUnmounted(() => {
  if (null !== captchaScript) {
    captchaScript.removeEventListener('error', handleCaptchaScriptError);
    captchaScript = null;
  }

  window.__onSmartCaptchaLoad = undefined;
  unsubscribeCallbacks.forEach((unsubscribe) => unsubscribe());
  unsubscribeCallbacks = [];

  if (null !== captchaWidgetId) {
    window.smartCaptcha?.destroy(captchaWidgetId);
    captchaWidgetId = null;
  }

  activeSetErrors = null;
  captchaProcessing.value = false;
});
</script>

<template>
  <Form @submit="validate" class="mt-5 loginForm" v-slot="{ isSubmitting }">
    <v-alert v-if="isMockApiEnabled" color="info" variant="tonal" class="mb-4">
      Mock-режим активен. Для быстрого входа используйте <strong>owner@rebit.test</strong> / <strong>secret123</strong>.
    </v-alert>

    <v-text-field
      v-model="email"
      :rules="emailRules"
      data-testid="login-email"
      label="Email"
      class="mb-4"
      required
      density="comfortable"
      hide-details="auto"
      variant="outlined"
      color="primary"
    />
    <v-text-field
      v-model="password"
      :rules="passwordRules"
      data-testid="login-password"
      label="Пароль"
      required
      density="comfortable"
      variant="outlined"
      color="primary"
      hide-details="auto"
      :append-inner-icon="show1 ? '$eye' : '$eyeOff'"
      :type="show1 ? 'text' : 'password'"
      @click:append-inner="show1 = !show1"
      class="pwdInput"
    />

    <v-btn
      color="secondary"
      data-testid="login-submit"
      :loading="isSubmitting || captchaProcessing"
      :disabled="captchaLoading || captchaProcessing || ('' !== smartCaptchaClientKey && false === captchaReady)"
      block
      class="mt-6"
      variant="flat"
      size="large"
      type="submit"
    >
      Войти
    </v-btn>

    <div ref="captchaContainer" class="smart-captcha-container"></div>

    <div v-if="captchaLoading" class="text-caption text-lightText mt-3">Загружаем SmartCaptcha...</div>

    <v-alert v-if="captchaError" color="warning" class="mt-4" variant="tonal">
      {{ captchaError }}
    </v-alert>

    <v-alert v-if="apiError" color="error" class="mt-4" variant="tonal" data-testid="login-api-error">
      {{ apiError }}
    </v-alert>
  </Form>

  <div class="mt-5 text-center">
    <v-divider class="mb-3" />
    <span class="text-lightText">Нет аккаунта?</span>
    <router-link to="/register" class="text-primary text-decoration-none ml-1 font-weight-medium"> Зарегистрироваться </router-link>
  </div>
</template>

<style lang="scss">
.loginForm {
  .v-text-field .v-field--active input {
    font-weight: 500;
  }
}
</style>
