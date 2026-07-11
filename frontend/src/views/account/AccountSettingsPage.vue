<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const snackbar = reactive({ show: false, color: 'success', text: '' });

function notify(text: string, color: 'success' | 'error' = 'success'): void {
  snackbar.text = text;
  snackbar.color = color;
  snackbar.show = true;
}

function apiMessage(error: unknown, fallback: string): string {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { message?: string } } }).response;
    if (typeof response?.data?.message === 'string') {
      return response.data.message;
    }
  }
  return fallback;
}

// --- Пароль ---
const password = reactive({ current: '', next: '', confirm: '' });
const passwordLoading = ref(false);

async function submitPassword(): Promise<void> {
  if (password.next.length < 8) {
    notify('Новый пароль должен быть не короче 8 символов.', 'error');
    return;
  }
  if (password.next !== password.confirm) {
    notify('Подтверждение пароля не совпадает.', 'error');
    return;
  }
  passwordLoading.value = true;
  try {
    await auth.changePassword(password.current, password.next, password.confirm);
    password.current = password.next = password.confirm = '';
    notify('Пароль изменён. Сессия обновлена.');
  } catch (error) {
    notify(apiMessage(error, 'Не удалось изменить пароль.'), 'error');
  } finally {
    passwordLoading.value = false;
  }
}

// --- Логин ---
const login = ref(auth.user?.login ?? '');
const loginLoading = ref(false);

async function submitLogin(): Promise<void> {
  loginLoading.value = true;
  try {
    await auth.changeLogin(login.value.trim());
    notify('Логин изменён.');
  } catch (error) {
    notify(apiMessage(error, 'Не удалось изменить логин.'), 'error');
  } finally {
    loginLoading.value = false;
  }
}

// --- Email ---
const emailForm = reactive({ newEmail: auth.user?.email ?? '', currentPassword: '' });
const emailLoading = ref(false);

async function submitEmail(): Promise<void> {
  emailLoading.value = true;
  try {
    await auth.changeEmail(emailForm.newEmail.trim(), emailForm.currentPassword);
    emailForm.currentPassword = '';
    notify('Email изменён.');
  } catch (error) {
    notify(apiMessage(error, 'Не удалось изменить email.'), 'error');
  } finally {
    emailLoading.value = false;
  }
}
</script>

<template>
  <main class="account-settings">
    <h1 class="text-h5 font-weight-bold mb-6">Настройки аккаунта</h1>

    <v-row>
      <v-col cols="12" md="6">
        <v-card rounded="lg" class="h-100">
          <v-card-title class="text-subtitle-1 font-weight-bold">Смена пароля</v-card-title>
          <v-card-text>
            <v-form @submit.prevent="submitPassword">
              <v-text-field v-model="password.current" label="Текущий пароль" type="password" variant="outlined" density="comfortable" class="mb-2" />
              <v-text-field v-model="password.next" label="Новый пароль" type="password" variant="outlined" density="comfortable" hint="Минимум 8 символов" persistent-hint class="mb-2" />
              <v-text-field v-model="password.confirm" label="Повторите новый пароль" type="password" variant="outlined" density="comfortable" class="mb-4" />
              <v-btn type="submit" color="primary" :loading="passwordLoading" block>Изменить пароль</v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card rounded="lg" class="mb-6">
          <v-card-title class="text-subtitle-1 font-weight-bold">Смена логина</v-card-title>
          <v-card-text>
            <v-form @submit.prevent="submitLogin">
              <v-text-field v-model="login" label="Логин" variant="outlined" density="comfortable" hint="3–32 символа: латиница, цифры, . _ -" persistent-hint class="mb-4" />
              <v-btn type="submit" color="primary" :loading="loginLoading" block>Сохранить логин</v-btn>
            </v-form>
          </v-card-text>
        </v-card>

        <v-card rounded="lg">
          <v-card-title class="text-subtitle-1 font-weight-bold">Смена email</v-card-title>
          <v-card-text>
            <v-form @submit.prevent="submitEmail">
              <v-text-field v-model="emailForm.newEmail" label="Новый email" type="email" variant="outlined" density="comfortable" class="mb-2" />
              <v-text-field v-model="emailForm.currentPassword" label="Текущий пароль (подтверждение)" type="password" variant="outlined" density="comfortable" class="mb-4" />
              <v-btn type="submit" color="primary" :loading="emailLoading" block>Изменить email</v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" location="top right" timeout="3500">
      {{ snackbar.text }}
    </v-snackbar>
  </main>
</template>
