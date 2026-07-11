import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { identityApi, type ApiConnectionStatus } from '@/api/identity';

function createEmptyConnectionStatus(): ApiConnectionStatus {
  return {
    connected: false,
    mode: null,
    modeLabel: null,
    status: null,
    statusLabel: null,
    id: null,
    userId: null,
    maskedApiKey: null,
    createdAt: null,
    verifiedAt: null
  };
}

export const useIdentityStore = defineStore('identity', () => {
  const connectionStatus = ref<ApiConnectionStatus | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const isConnected = computed(() => {
    const status = connectionStatus.value as ApiConnectionStatus | null;

    if (null === status) {
      return false;
    }

    return true === status['connected'];
  });

  const hasActiveConnection = computed(() => {
    const status = connectionStatus.value as ApiConnectionStatus | null;

    if (null === status) {
      return false;
    }

    return true === status['connected'] && 'active' === status['status'];
  });

  const modeLabel = computed(() => {
    const status = connectionStatus.value as ApiConnectionStatus | null;

    if (null === status) {
      return null;
    }

    if (null !== status['modeLabel']) {
      return status['modeLabel'];
    }

    return 'mainnet' === status['mode'] ? 'Mainnet' : 'testnet' === status['mode'] ? 'Testnet' : null;
  });

  const statusLabel = computed(() => {
    const status = connectionStatus.value as ApiConnectionStatus | null;

    if (null === status) {
      return null;
    }

    if (null !== status['statusLabel']) {
      return status['statusLabel'];
    }

    switch (status['status']) {
      case 'active':
        return 'Активен';
      case 'invalid':
        return 'Недействителен';
      case 'revoked':
        return 'Отозван';
      case 'pending_verification':
        return 'Ожидает проверки';
      default:
        return null;
    }
  });

  async function fetchStatus(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      connectionStatus.value = await identityApi.status();
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка получения статуса';
    } finally {
      loading.value = false;
    }
  }

  async function connect(apiKey: string, secretKey: string, mode: 'testnet' | 'mainnet'): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      connectionStatus.value = await identityApi.connect({ apiKey, secretKey, mode });
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка подключения API';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function disconnect(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      await identityApi.disconnect();
      connectionStatus.value = createEmptyConnectionStatus();
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка отключения';
    } finally {
      loading.value = false;
    }
  }

  async function verify(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      connectionStatus.value = await identityApi.verify();
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка верификации';
    } finally {
      loading.value = false;
    }
  }

  return {
    connectionStatus,
    loading,
    error,
    isConnected,
    hasActiveConnection,
    modeLabel,
    statusLabel,
    fetchStatus,
    connect,
    disconnect,
    verify
  };
});
