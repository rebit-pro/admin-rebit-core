import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { router } from '@/router';
import { authApi, type AuthUser, type SmartCaptchaPayload, type RequestRegistrationCodeResponse } from '@/api/auth';
import { accountApi } from '@/api/account';

const TOKEN_STORAGE_KEY = 'token';
const USER_STORAGE_KEY = 'user';
const TOKEN_EXPIRES_AT_STORAGE_KEY = 'token_expires_at';

function readStoredUser(): AuthUser | null {
  const rawUser = localStorage.getItem(USER_STORAGE_KEY);

  if (null === rawUser) {
    return null;
  }

  try {
    return JSON.parse(rawUser) as AuthUser;
  } catch {
    return null;
  }
}

function resolveExpiresAtTimestamp(expiresAt: string | null): number | null {
  if (null === expiresAt) {
    return null;
  }

  const expiresAtTimestamp = Date.parse(expiresAt);

  return Number.isNaN(expiresAtTimestamp) ? null : expiresAtTimestamp;
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_STORAGE_KEY));
  const user = ref<AuthUser | null>(readStoredUser());
  const expiresAt = ref<string | null>(localStorage.getItem(TOKEN_EXPIRES_AT_STORAGE_KEY));
  const returnUrl = ref<string | null>(null);
  let sessionExpirationTimeoutId: number | null = null;

  const isAuthenticated = computed(() => {
    if (null === token.value || null === user.value) {
      return false;
    }

    // Legacy-сессия без expiresAt — считаем аутентифицированной до первого 401
    if (null === expiresAt.value) {
      return true;
    }

    const expiresAtTimestamp = resolveExpiresAtTimestamp(expiresAt.value);

    if (null === expiresAtTimestamp) {
      return false;
    }

    return expiresAtTimestamp > Date.now();
  });

  function clearSessionExpirationTimer(): void {
    if (null === sessionExpirationTimeoutId) {
      return;
    }

    window.clearTimeout(sessionExpirationTimeoutId);
    sessionExpirationTimeoutId = null;
  }

  function expireSession(): void {
    const currentRoute = router.currentRoute.value;
    const authRequired = currentRoute.matched.some((record) => true === record.meta['requiresAuth']);

    if (authRequired) {
      returnUrl.value = currentRoute.fullPath;
    }

    clearSession();

    if (authRequired) {
      void router.push('/login');
    }
  }

  function scheduleSessionExpiration(): void {
    clearSessionExpirationTimer();

    const expiresAtTimestamp = resolveExpiresAtTimestamp(expiresAt.value);

    if (null === expiresAtTimestamp) {
      return;
    }

    const expiresInMilliseconds = expiresAtTimestamp - Date.now();

    if (0 >= expiresInMilliseconds) {
      expireSession();
      return;
    }

    sessionExpirationTimeoutId = window.setTimeout(() => {
      expireSession();
    }, expiresInMilliseconds);
  }

  function restoreSession(): void {
    if (null === token.value && null === user.value && null === expiresAt.value) {
      clearSessionExpirationTimer();
      return;
    }

    if (null === token.value || null === user.value) {
      clearSession();
      return;
    }

    // Старая сессия без expiresAt — оставляем до первого 401, не разлогиниваем
    if (null === expiresAt.value) {
      return;
    }

    const expiresAtTimestamp = resolveExpiresAtTimestamp(expiresAt.value);

    if (null === expiresAtTimestamp || expiresAtTimestamp <= Date.now()) {
      clearSession();
      return;
    }

    scheduleSessionExpiration();
  }

  function setSession(newToken: string, newUser: AuthUser, newExpiresAt: string): void {
    const expiresAtTimestamp = resolveExpiresAtTimestamp(newExpiresAt);

    if (null === expiresAtTimestamp || expiresAtTimestamp <= Date.now()) {
      clearSession();
      throw new Error('Получен некорректный или истёкший токен.');
    }

    token.value = newToken;
    user.value = newUser;
    expiresAt.value = newExpiresAt;
    localStorage.setItem(TOKEN_STORAGE_KEY, newToken);
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(newUser));
    localStorage.setItem(TOKEN_EXPIRES_AT_STORAGE_KEY, newExpiresAt);
    scheduleSessionExpiration();
  }

  function clearSession(): void {
    clearSessionExpirationTimer();
    token.value = null;
    user.value = null;
    expiresAt.value = null;
    localStorage.removeItem(TOKEN_STORAGE_KEY);
    localStorage.removeItem(USER_STORAGE_KEY);
    localStorage.removeItem(TOKEN_EXPIRES_AT_STORAGE_KEY);
  }

  function getAccessToken(): string | null {
    return isAuthenticated.value ? token.value : null;
  }

  async function login(email: string, password: string, captcha?: SmartCaptchaPayload): Promise<void> {
    const response = await authApi.login({ email, password, captcha });
    setSession(response.token, response.user, response.expiresAt);
    await router.push(returnUrl.value ?? '/dashboard');
    returnUrl.value = null;
  }

  async function requestRegistrationCode(email: string, password: string): Promise<RequestRegistrationCodeResponse> {
    return authApi.requestRegistrationCode({ email, password });
  }

  async function confirmRegistration(email: string, code: string): Promise<void> {
    const response = await authApi.confirmRegistration({ email, code });
    setSession(response.token, response.user, response.expiresAt);
    await router.push('/dashboard');
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout();
    } catch {
      // Даже если запрос не прошёл — чистим сессию
    }
    clearSession();
    await router.push('/login');
  }

  // Подтягивает актуального пользователя с бэкенда (роль/логин/профиль).
  async function fetchUser(): Promise<void> {
    const fresh = await authApi.getUser();
    user.value = fresh;
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(fresh));
  }

  // Смена пароля отзывает все токены и выдаёт новый — обновляем сессию.
  async function changePassword(currentPassword: string, newPassword: string, newPasswordConfirmation: string): Promise<void> {
    const response = await accountApi.changePassword({ currentPassword, newPassword, newPasswordConfirmation });

    if (null === user.value) {
      throw new Error('Нет активной сессии.');
    }

    setSession(response.token, user.value, response.expiresAt);
  }

  async function changeLogin(login: string): Promise<void> {
    const updated = await accountApi.changeLogin(login);
    user.value = updated;
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(updated));
  }

  async function changeEmail(newEmail: string, currentPassword: string): Promise<void> {
    const updated = await accountApi.changeEmail(newEmail, currentPassword);
    user.value = updated;
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(updated));
  }

  restoreSession();

  // Одноразовое обновление профиля при инициализации, если сессия жива.
  if (isAuthenticated.value) {
    void fetchUser().catch(() => {
      // 401 обработает interceptor (очистит сессию и уведёт на /login)
    });
  }

  return {
    token,
    user,
    expiresAt,
    returnUrl,
    isAuthenticated,
    clearSession,
    restoreSession,
    getAccessToken,
    login,
    fetchUser,
    changePassword,
    changeLogin,
    changeEmail,
    requestRegistrationCode,
    confirmRegistration,
    logout
  };
});
