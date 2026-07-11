<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { Form } from 'vee-validate';
import type { GeeTestCaptchaPayload } from '@/api/auth';
import { isMockApiEnabled } from '@/mocks/config';

const show1 = ref(false);
const password = ref('');
const email = ref('');
const captchaLoading = ref(false);
const captchaReady = ref(false);
const captchaProcessing = ref(false);
const captchaError = ref('');
const apiError = ref('');

const authStore = useAuthStore();
const geetestCaptchaId = isMockApiEnabled ? '' : (import.meta.env.VITE_GEETEST_CAPTCHA_ID?.trim() ?? '');
const geeTestScriptSrc = 'https://static.geetest.com/v4/gt4.js';

let captchaInstance: GeeTestCaptchaInstance | null = null;
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

function normalizeCaptchaResult(result: GeeTestCaptchaValidateResult): GeeTestCaptchaPayload {
  return {
    lot_number: result.lot_number,
    captcha_output: result.captcha_output,
    pass_token: result.pass_token,
    gen_time: result.gen_time
  };
}

function handleLoginError(error: any): void {
  const message = error?.response?.data?.message ?? 'Ошибка авторизации';
  setApiError(message);
}

async function submitLogin(captcha?: GeeTestCaptchaPayload): Promise<void> {
  captchaProcessing.value = true;

  try {
    await authStore.login(email.value.trim(), password.value, captcha);
  } catch (error) {
    handleLoginError(error);
  } finally {
    captchaProcessing.value = false;
  }
}

function handleCaptchaScriptLoad(): void {
  initCaptcha();
}

function handleCaptchaScriptError(): void {
  captchaLoading.value = false;
  captchaProcessing.value = false;
  captchaError.value = 'Не удалось загрузить GeeTest CAPTCHA';
}

function initCaptcha(): void {
  if ('' === geetestCaptchaId) {
    captchaReady.value = true;
    return;
  }

  if (undefined === window.initGeetest4) {
    captchaError.value = 'Не удалось инициализировать GeeTest CAPTCHA';
    captchaLoading.value = false;
    return;
  }

  captchaLoading.value = true;

  window.initGeetest4(
    {
      captchaId: geetestCaptchaId,
      product: 'bind'
    },
    (instance) => {
      captchaInstance = instance;

      captchaInstance
        .onReady(() => {
          captchaReady.value = true;
          captchaLoading.value = false;
          captchaError.value = '';
        })
        .onSuccess(() => {
          if (null === captchaInstance) {
            captchaProcessing.value = false;
            setApiError('GeeTest CAPTCHA не инициализирована');
            return;
          }

          const result = captchaInstance.getValidate();

          if (false === result || null === result) {
            captchaProcessing.value = false;
            setApiError('Не удалось получить данные CAPTCHA');
            return;
          }

          void submitLogin(normalizeCaptchaResult(result));
        })
        .onError(() => {
          captchaProcessing.value = false;
          captchaLoading.value = false;
          captchaError.value = 'GeeTest CAPTCHA временно недоступна. Попробуйте ещё раз.';
        });

      captchaInstance.onClose?.(() => {
        captchaLoading.value = false;
        captchaProcessing.value = false;
      });
    }
  );
}

function loadCaptcha(): void {
  if ('' === geetestCaptchaId) {
    captchaReady.value = true;
    captchaLoading.value = false;
    return;
  }

  if (undefined !== window.initGeetest4) {
    initCaptcha();
    return;
  }

  captchaLoading.value = true;
  captchaError.value = '';

  const existingCaptchaScript = document.querySelector<HTMLScriptElement>(`script[src="${geeTestScriptSrc}"]`);

  if (null !== existingCaptchaScript) {
    captchaScript = existingCaptchaScript;
    captchaScript.addEventListener('load', handleCaptchaScriptLoad, { once: true });
    captchaScript.addEventListener('error', handleCaptchaScriptError, { once: true });

    return;
  }

  captchaScript = document.createElement('script');
  captchaScript.src = geeTestScriptSrc;
  captchaScript.async = true;
  captchaScript.addEventListener('load', handleCaptchaScriptLoad, { once: true });
  captchaScript.addEventListener('error', handleCaptchaScriptError, { once: true });

  document.head.appendChild(captchaScript);
}

function validate(_values: any, { setErrors }: any) {
  activeSetErrors = setErrors;
  captchaError.value = '';
  apiError.value = '';
  setErrors({ apiError: '' });

  if ('' === geetestCaptchaId) {
    return submitLogin();
  }

  if (false === captchaReady.value || null === captchaInstance) {
    setApiError('CAPTCHA ещё загружается, попробуйте через пару секунд', setErrors);
    return Promise.resolve();
  }

  captchaProcessing.value = true;
  captchaInstance.showCaptcha();

  return Promise.resolve();
}

onMounted(() => {
  loadCaptcha();
});

onUnmounted(() => {
  if (null !== captchaScript) {
    captchaScript.removeEventListener('load', handleCaptchaScriptLoad);
    captchaScript.removeEventListener('error', handleCaptchaScriptError);
    captchaScript = null;
  }

  captchaInstance?.destroy?.();
  captchaInstance = null;
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
      :disabled="captchaLoading || captchaProcessing || ('' !== geetestCaptchaId && false === captchaReady)"
      block
      class="mt-6"
      variant="flat"
      size="large"
      type="submit"
    >
      Войти
    </v-btn>

    <div v-if="captchaLoading" class="text-caption text-lightText mt-3">Загружаем GeeTest CAPTCHA...</div>

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
