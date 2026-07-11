<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { Form } from 'vee-validate';

const showPassword = ref(false);
const password = ref('');
const email = ref('');
const code = ref('');
const step = ref<'request-code' | 'confirm-code'>('request-code');
const apiErrorMessage = ref('');
const successMessage = ref('');
const codeExpiresAt = ref<string | null>(null);
const resendSecondsLeft = ref(0);

const authStore = useAuthStore();
let resendTimerId: number | null = null;

const emailRules = ref([
  (v: string) => '' !== v.trim() || 'Введите email',
  (v: string) => /.+@.+\..+/.test(v.trim()) || 'Некорректный email'
]);

const passwordRules = ref([(v: string) => '' !== v || 'Введите пароль', (v: string) => v.length >= 6 || 'Минимум 6 символов']);

const canResendCode = computed(() => 0 === resendSecondsLeft.value);

function normalizeErrorMessage(error: unknown, fallbackMessage: string): string {
  const maybeError = error as { response?: { data?: { message?: string } } };

  return maybeError.response?.data?.message ?? fallbackMessage;
}

function stopResendTimer(): void {
  if (null !== resendTimerId) {
    window.clearInterval(resendTimerId);
    resendTimerId = null;
  }
}

function startResendTimer(targetIsoDate: string): void {
  stopResendTimer();

  const tick = (): void => {
    const secondsLeft = Math.max(0, Math.ceil((new Date(targetIsoDate).getTime() - Date.now()) / 1000));
    resendSecondsLeft.value = secondsLeft;

    if (0 === secondsLeft) {
      stopResendTimer();
    }
  };

  tick();
  resendTimerId = window.setInterval(tick, 1000);
}

/* eslint-disable @typescript-eslint/no-explicit-any */
async function submitRequestCode(setErrors: (errors: Record<string, string>) => void): Promise<void> {
  apiErrorMessage.value = '';
  successMessage.value = '';

  try {
    const response = await authStore.requestRegistrationCode(email.value.trim(), password.value);

    step.value = 'confirm-code';
    code.value = '';
    codeExpiresAt.value = response.codeExpiresAt;
    successMessage.value = 'Мы отправили код подтверждения на указанный e-mail.';
    startResendTimer(response.resendAvailableAt);
  } catch (error) {
    const message = normalizeErrorMessage(error, 'Ошибка отправки кода подтверждения');
    apiErrorMessage.value = message;
    setErrors({ apiError: message });
  }
}

async function submitConfirmCode(setErrors: (errors: Record<string, string>) => void): Promise<void> {
  apiErrorMessage.value = '';
  successMessage.value = '';

  if (!/^\d{6}$/.test(code.value.trim())) {
    const message = 'Код должен состоять из 6 цифр';
    apiErrorMessage.value = message;
    setErrors({ apiError: message });

    return;
  }

  try {
    await authStore.confirmRegistration(email.value.trim(), code.value.trim());
  } catch (error) {
    const message = normalizeErrorMessage(error, 'Ошибка подтверждения кода');
    apiErrorMessage.value = message;
    setErrors({ apiError: message });
  }
}

function validate(_values: any, { setErrors }: any) {
  setErrors({ apiError: '' });

  if ('request-code' === step.value) {
    return submitRequestCode(setErrors);
  }

  return submitConfirmCode(setErrors);
}

async function resendCode(): Promise<void> {
  if (!canResendCode.value) {
    return;
  }

  apiErrorMessage.value = '';
  successMessage.value = '';

  try {
    const response = await authStore.requestRegistrationCode(email.value.trim(), password.value);

    codeExpiresAt.value = response.codeExpiresAt;
    successMessage.value = 'Новый код подтверждения отправлен.';
    startResendTimer(response.resendAvailableAt);
  } catch (error) {
    apiErrorMessage.value = normalizeErrorMessage(error, 'Ошибка повторной отправки кода');
  }
}

function goBackToRequestCode(): void {
  stopResendTimer();
  resendSecondsLeft.value = 0;
  codeExpiresAt.value = null;
  step.value = 'request-code';
}

onUnmounted(() => {
  stopResendTimer();
});
</script>

<template>
  <Form @submit="validate" class="mt-5 loginForm" v-slot="{ isSubmitting }">
    <v-text-field
      v-model="email"
      :rules="emailRules"
      label="Email"
      class="mb-4"
      required
      density="comfortable"
      hide-details="auto"
      variant="outlined"
      color="primary"
      :disabled="'confirm-code' === step"
    />
    <v-text-field
      v-if="'request-code' === step"
      v-model="password"
      :rules="passwordRules"
      label="Пароль"
      required
      density="comfortable"
      variant="outlined"
      color="primary"
      hide-details="auto"
      :append-inner-icon="showPassword ? '$eye' : '$eyeOff'"
      :type="showPassword ? 'text' : 'password'"
      @click:append-inner="showPassword = !showPassword"
      class="pwdInput"
    />

    <div v-if="'confirm-code' === step" class="mb-2">
      <v-otp-input v-model="code" :length="6" variant="outlined" density="comfortable" class="mb-2" />
      <div class="text-caption text-lightText">
        Введите 6-значный код из письма.
        <span v-if="null !== codeExpiresAt"> Код действует до {{ new Date(codeExpiresAt).toLocaleString('ru-RU') }}. </span>
      </div>
    </div>

    <v-btn color="secondary" :loading="isSubmitting" block class="mt-6" variant="flat" size="large" type="submit">
      {{ 'request-code' === step ? 'Получить код' : 'Подтвердить регистрацию' }}
    </v-btn>

    <v-btn v-if="'confirm-code' === step" :disabled="!canResendCode" block class="mt-3" variant="text" color="primary" @click="resendCode">
      {{ canResendCode ? 'Отправить код повторно' : `Повторная отправка через ${resendSecondsLeft} сек.` }}
    </v-btn>

    <v-btn v-if="'confirm-code' === step" block class="mt-1" variant="text" color="secondary" @click="goBackToRequestCode">
      Изменить e-mail или пароль
    </v-btn>

    <v-alert v-if="successMessage" color="success" class="mt-4" variant="tonal">
      {{ successMessage }}
    </v-alert>

    <v-alert v-if="apiErrorMessage" color="error" class="mt-4" variant="tonal">
      {{ apiErrorMessage }}
    </v-alert>
  </Form>

  <div class="mt-5 text-center">
    <v-divider class="mb-3" />
    <span class="text-lightText">Уже есть аккаунт?</span>
    <router-link to="/login" class="text-primary text-decoration-none ml-1 font-weight-medium"> Войти </router-link>
  </div>
</template>

<style lang="scss">
.loginForm {
  .v-text-field .v-field--active input {
    font-weight: 500;
  }
}
</style>
