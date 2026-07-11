<script setup lang="ts">
import { computed, onMounted, ref, type Component } from 'vue';
import { ArrowsExchangeIcon, CircleCheckIcon, KeyIcon, ShieldCheckIcon } from 'vue-tabler-icons';
import { useIdentityStore } from '@/stores/identity';
import AppEmptyState from '@/components/shared/AppEmptyState.vue';

const identity = useIdentityStore();

const apiKey = ref('');
const secretKey = ref('');
const mode = ref<'testnet' | 'mainnet'>('testnet');
const showSecret = ref(false);
const submitting = ref(false);
const verifying = ref(false);
const disconnecting = ref(false);
const errorMessage = ref<string | null>(null);
const notification = ref<{
  type: 'success' | 'info' | 'warning' | 'error';
  title: string;
  text: string;
} | null>(null);

const isConnected = computed(() => identity.isConnected);
const connectionMode = computed(() => {
  const status = identity.connectionStatus;

  if (null === status) {
    return null;
  }

  return status['mode'];
});
const connectionStatus = computed(() => {
  const status = identity.connectionStatus;

  if (null === status) {
    return null;
  }

  return status['status'];
});
const connectionModeLabel = computed(() => identity.modeLabel ?? '—');
const connectionStatusLabel = computed(() => identity.statusLabel ?? '—');
const hasConnectionMode = computed(() => null !== connectionMode.value);
const notificationType = computed(() => {
  const value = notification.value;

  if (null === value) {
    return 'info';
  }

  return value['type'];
});

const notificationTitle = computed(() => {
  const value = notification.value;

  if (null === value) {
    return '';
  }

  return value['title'];
});

const notificationText = computed(() => {
  const value = notification.value;

  if (null === value) {
    return '';
  }

  return value['text'];
});
const maskedApiKey = computed(() => {
  const status = identity.connectionStatus;

  if (null === status) {
    return '—';
  }

  return status['maskedApiKey'] ?? '—';
});
const createdAt = computed(() => {
  const status = identity.connectionStatus;

  if (null === status) {
    return null;
  }

  return status['createdAt'];
});
const verifiedAt = computed(() => {
  const status = identity.connectionStatus;

  if (null === status) {
    return null;
  }

  return status['verifiedAt'];
});
const statusChipColor = computed(() => {
  switch (connectionStatus.value) {
    case 'active':
      return 'success';
    case 'pending_verification':
      return 'info';
    case 'invalid':
      return 'warning';
    case 'revoked':
      return 'error';
    default:
      return 'secondary';
  }
});
const connectionTone = computed(() => ('mainnet' === connectionMode.value ? 'warning' : 'info'));
const connectionTitle = computed(() =>
  'mainnet' === connectionMode.value ? 'Подключён боевой Bybit API' : 'Подключён тестовый Bybit API'
);
const connectionDescription = computed(() =>
  'mainnet' === connectionMode.value
    ? 'У вас активирован боевой ключ. Операции будут выполняться в реальной среде Bybit.'
    : 'У вас активирован тестовый ключ. Можно безопасно проверять сценарии без боевых сделок.'
);
const keyRecommendations: Array<{
  title: string;
  subtitle: string;
  icon: Component;
  colorClass: string;
}> = [
  {
    title: 'Давайте только нужные права',
    subtitle: 'Достаточно чтения и торговли, без вывода средств.',
    icon: ShieldCheckIcon,
    colorClass: 'text-secondary'
  },
  {
    title: 'Для тестов используйте Testnet',
    subtitle: 'Подходит для проверки сценариев без риска для боевых средств.',
    icon: CircleCheckIcon,
    colorClass: 'text-info'
  },
  {
    title: 'Mainnet используйте осознанно',
    subtitle: 'Все операции выполняются в реальной среде Bybit.',
    icon: ArrowsExchangeIcon,
    colorClass: 'text-warning'
  }
];
const connectionFacts = computed(() => [
  {
    title: 'Ключ',
    value: maskedApiKey.value
  },
  {
    title: 'Подключён',
    value: formatDate(createdAt.value)
  },
  {
    title: 'Проверен',
    value: formatDate(verifiedAt.value)
  }
]);
const formModeTitle = computed(() => ('mainnet' === mode.value ? 'Будет подключён Mainnet' : 'Будет подключён Testnet'));
const formNoticeTitle = computed(() =>
  'mainnet' === mode.value ? 'Проверьте доступы для боевого режима' : 'Безопасный режим для первичной проверки'
);
const formNoticeText = computed(() =>
  'mainnet' === mode.value
    ? 'Создайте API-ключ только с правами на чтение и торговлю. Не давайте доступ на вывод средств и перепроверьте ограничения ключа.'
    : 'Выбран тестовый режим. Это безопасный вариант для первой проверки интеграции и пользовательских сценариев.'
);

function resetForm(resetMode = true): void {
  apiKey.value = '';
  secretKey.value = '';
  showSecret.value = false;
  errorMessage.value = null;

  if (resetMode) {
    mode.value = 'testnet';
  }
}

function formatDate(value: string | null): string {
  if (null === value || '' === value) {
    return '—';
  }

  return new Date(value).toLocaleString('ru-RU');
}

onMounted(async () => {
  await identity.fetchStatus();

  if (hasConnectionMode.value && null !== connectionMode.value) {
    mode.value = connectionMode.value;
  }
});

async function onSubmit(): Promise<void> {
  if ('' === apiKey.value.trim() || '' === secretKey.value.trim()) {
    errorMessage.value = 'Заполните все поля';
    return;
  }

  submitting.value = true;
  errorMessage.value = null;
  notification.value = null;

  try {
    await identity.connect(apiKey.value.trim(), secretKey.value.trim(), mode.value);
    resetForm(false);
    notification.value = {
      type: 'success',
      title: 'Bybit API подключён',
      text: `Подключен ${identity.modeLabel ?? ('mainnet' === mode.value ? 'Mainnet' : 'Testnet')} ключ. Теперь можно пользоваться стаканами и торговыми сценариями.`
    };
  } catch (e: unknown) {
    if (e instanceof Error) {
      errorMessage.value = e.message;
    }
    if (null !== identity.error) {
      errorMessage.value = identity.error;
    }
  } finally {
    submitting.value = false;
  }
}

async function onVerify(): Promise<void> {
  verifying.value = true;
  errorMessage.value = null;
  notification.value = null;

  try {
    await identity.verify();

    if (null !== identity.error) {
      errorMessage.value = identity.error;

      return;
    }

    notification.value = {
      type: 'info',
      title: 'Проверка выполнена',
      text: `Статус подключения: ${identity.statusLabel ?? '—'}. Режим: ${identity.modeLabel ?? '—'}.`
    };
  } catch (e: unknown) {
    if (e instanceof Error) {
      errorMessage.value = e.message;
    }

    if (null !== identity.error) {
      errorMessage.value = identity.error;
    }
  } finally {
    verifying.value = false;
  }
}

async function onDisconnect(): Promise<void> {
  disconnecting.value = true;
  errorMessage.value = null;
  notification.value = null;

  try {
    await identity.disconnect();

    if (null !== identity.error) {
      errorMessage.value = identity.error;

      return;
    }

    resetForm(true);
    notification.value = {
      type: 'info',
      title: 'Bybit API отключён',
      text: 'Ключ и секрет отвязаны. Можно подключить новый testnet или mainnet ключ.'
    };
  } catch (e: unknown) {
    if (e instanceof Error) {
      errorMessage.value = e.message;
    }

    if (null !== identity.error) {
      errorMessage.value = identity.error;
    }
  } finally {
    disconnecting.value = false;
  }
}
</script>

<template>
  <div class="api-connection-page">
    <div class="api-connection-page__heading mb-6">
      <h2 class="text-h4 mb-2">Подключение Bybit API</h2>
      <p class="text-lightText mb-0">Подключите testnet или mainnet ключ, чтобы видеть стаканы, балансы и работать с P2P.</p>
    </div>

    <v-sheet v-if="null !== notification" class="api-connection-notice mb-6" rounded="lg">
      <div class="api-connection-notice__layout">
        <v-avatar :color="notificationType" size="44" variant="tonal" class="flex-shrink-0">
          <v-icon>
            {{
              'success' === notificationType
                ? 'mdi-check-circle-outline'
                : 'warning' === notificationType
                  ? 'mdi-alert-outline'
                  : 'error' === notificationType
                    ? 'mdi-alert-circle-outline'
                    : 'mdi-information-outline'
            }}
          </v-icon>
        </v-avatar>

        <div class="api-connection-notice__content">
          <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-1">
            <div class="text-subtitle-2 font-weight-bold">{{ notificationTitle }}</div>
            <v-btn size="small" variant="text" color="default" @click="notification = null">Закрыть</v-btn>
          </div>
          <p class="api-connection-notice__text text-body-2 text-medium-emphasis">
            {{ notificationText }}
          </p>
        </div>
      </div>
    </v-sheet>

    <v-row class="mb-6">
      <v-col cols="12" md="6">
        <AppEmptyState
          class="fill-height api-connection-page__hero-card"
          :icon="KeyIcon"
          variant="gradient"
          align="left"
          tone="secondary"
          eyebrow="Bybit integration"
          title="Bybit API"
          description="Поддерживаются testnet и mainnet ключи. Подключите доступ один раз, чтобы видеть статусы подключения, балансы и P2P-стаканы прямо в интерфейсе проекта."
        >
          <div class="d-flex flex-wrap ga-2 mb-4">
            <v-chip color="info" variant="flat">Testnet для безопасной отладки</v-chip>
            <v-chip color="warning" variant="flat">Mainnet для реальных операций</v-chip>
          </div>

          <v-alert :type="isConnected ? connectionTone : 'info'" variant="outlined" class="api-connection-page__hero-alert">
            <div class="font-weight-medium mb-1">
              {{ isConnected ? connectionTitle : 'Ключ пока не подключён' }}
            </div>
            <div>
              {{
                isConnected
                  ? connectionDescription
                  : 'После подключения вы сможете видеть P2P-стаканы, статусы подключения и балансы Bybit прямо в интерфейсе.'
              }}
            </div>
          </v-alert>
        </AppEmptyState>
      </v-col>

      <v-col cols="12" md="6">
        <v-card rounded="lg" class="fill-height api-connection-card">
          <v-card-item class="api-connection-card__header px-6 py-4">
            <template #prepend>
              <v-avatar size="42" color="secondary" variant="tonal">
                <v-icon>mdi-shield-check-outline</v-icon>
              </v-avatar>
            </template>
            <v-card-title class="text-h6 font-weight-bold">Рекомендации по ключам</v-card-title>
            <v-card-subtitle>Что важно проверить перед подключением testnet или mainnet ключа</v-card-subtitle>
          </v-card-item>

          <v-divider class="api-connection-card__divider" />

          <v-card-text class="api-connection-card__body pa-6">
            <div class="d-flex flex-column ga-3">
              <v-sheet
                v-for="recommendation in keyRecommendations"
                :key="recommendation.title"
                class="api-connection-mini-card pa-4"
                rounded="lg"
              >
                <div class="api-connection-mini-card__layout">
                  <v-avatar size="42" color="default" variant="tonal" class="flex-shrink-0">
                    <component :is="recommendation.icon" :size="20" stroke-width="1.75" :class="recommendation.colorClass" />
                  </v-avatar>

                  <div>
                    <div class="text-subtitle-2 font-weight-bold mb-1">{{ recommendation.title }}</div>
                    <p class="api-connection-mini-card__text text-body-2 text-medium-emphasis">
                      {{ recommendation.subtitle }}
                    </p>
                  </div>
                </div>
              </v-sheet>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Если уже подключён -->
    <v-card v-if="isConnected" rounded="lg" class="mb-6 api-connection-card">
      <v-card-item class="api-connection-card__header px-6 py-4">
        <template #prepend>
          <v-avatar size="42" color="success" variant="tonal">
            <v-icon>mdi-check-circle-outline</v-icon>
          </v-avatar>
        </template>
        <v-card-title class="text-h6 font-weight-bold">Активное подключение Bybit API</v-card-title>
        <v-card-subtitle>Текущий режим, статус верификации и сведения по сохранённому ключу</v-card-subtitle>
        <template #append>
          <div class="d-flex flex-wrap ga-2 justify-end">
            <v-chip :color="connectionTone" variant="tonal">{{ connectionModeLabel }}</v-chip>
            <v-chip :color="statusChipColor" variant="tonal">{{ connectionStatusLabel }}</v-chip>
          </div>
        </template>
      </v-card-item>

      <v-divider class="api-connection-card__divider" />

      <v-card-text class="api-connection-card__body pa-6">
        <v-sheet class="api-connection-notice api-connection-notice--success pa-4 mb-5" rounded="lg">
          <div class="api-connection-notice__layout">
            <v-avatar size="44" :color="connectionTone" variant="tonal" class="flex-shrink-0">
              <CircleCheckIcon :size="22" stroke-width="1.75" />
            </v-avatar>

            <div class="api-connection-notice__content">
              <div class="text-subtitle-2 font-weight-bold mb-1">{{ connectionTitle }}</div>
              <p class="api-connection-notice__text text-body-2 text-medium-emphasis">{{ connectionDescription }}</p>
            </div>
          </div>
        </v-sheet>

        <v-row class="mb-5">
          <v-col v-for="fact in connectionFacts" :key="fact.title" cols="12" md="4">
            <v-sheet class="api-connection-mini-card pa-4 h-100" rounded="lg">
              <div class="text-caption text-medium-emphasis mb-1">{{ fact.title }}</div>
              <div class="font-weight-medium">{{ fact.value }}</div>
            </v-sheet>
          </v-col>
        </v-row>

        <div class="d-flex flex-wrap ga-3">
          <v-btn color="info" variant="outlined" @click="onVerify" :loading="verifying || identity.loading"> Проверить </v-btn>
          <v-btn color="error" variant="outlined" @click="onDisconnect" :loading="disconnecting || identity.loading"> Отключить </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <!-- Форма подключения -->
    <v-card v-else rounded="lg" class="api-connection-card">
      <v-card-item class="api-connection-card__header px-6 py-4">
        <template #prepend>
          <v-avatar size="42" color="primary" variant="tonal">
            <v-icon>mdi-key-variant</v-icon>
          </v-avatar>
        </template>
        <v-card-title class="text-h6 font-weight-bold">Подключить Bybit API</v-card-title>
        <v-card-subtitle>Введите ключ и секрет. После подключения форма скроется, а при отключении снова станет доступна.</v-card-subtitle>
        <template #append>
          <v-chip :color="'mainnet' === mode ? 'warning' : 'info'" variant="tonal" size="large">
            {{ formModeTitle }}
          </v-chip>
        </template>
      </v-card-item>

      <v-divider class="api-connection-card__divider" />

      <v-card-text class="api-connection-card__body pa-6">
        <v-form @submit.prevent="onSubmit">
          <v-select
            v-model="mode"
            :items="[
              { title: 'Testnet (тестовая)', value: 'testnet' },
              { title: 'Mainnet (боевая)', value: 'mainnet' }
            ]"
            label="Режим"
            variant="outlined"
            density="comfortable"
            color="primary"
            class="mb-4"
            prepend-inner-icon="mdi-compare"
            hide-details="auto"
          />

          <v-text-field
            v-model="apiKey"
            label="API Key"
            variant="outlined"
            density="comfortable"
            color="primary"
            class="mb-4"
            hide-details="auto"
            prepend-inner-icon="mdi-key-variant"
            placeholder="Введите Bybit API Key"
          />

          <v-text-field
            v-model="secretKey"
            label="Secret Key"
            variant="outlined"
            density="comfortable"
            color="primary"
            class="mb-4"
            hide-details="auto"
            :append-inner-icon="showSecret ? '$eye' : '$eyeOff'"
            :type="showSecret ? 'text' : 'password'"
            prepend-inner-icon="mdi-lock-outline"
            placeholder="Введите Bybit Secret Key"
            @click:append-inner="showSecret = !showSecret"
          />

          <v-sheet v-if="null !== errorMessage" class="api-connection-notice api-connection-notice--error pa-4 mb-4" rounded="lg">
            <div class="api-connection-notice__layout">
              <v-avatar size="44" color="error" variant="tonal" class="flex-shrink-0">
                <v-icon>mdi-alert-circle-outline</v-icon>
              </v-avatar>

              <div class="api-connection-notice__content">
                <div class="text-subtitle-2 font-weight-bold mb-1">Не удалось подключить API</div>
                <p class="api-connection-notice__text text-body-2 text-medium-emphasis">{{ errorMessage }}</p>
              </div>
            </div>
          </v-sheet>

          <v-sheet
            class="api-connection-notice pa-4 mb-6"
            :class="'mainnet' === mode ? 'api-connection-notice--warning' : 'api-connection-notice--info'"
            rounded="lg"
          >
            <div class="api-connection-notice__layout">
              <v-avatar size="44" :color="'mainnet' === mode ? 'warning' : 'info'" variant="tonal" class="flex-shrink-0">
                <v-icon>{{ 'mainnet' === mode ? 'mdi-shield-alert-outline' : 'mdi-information-outline' }}</v-icon>
              </v-avatar>

              <div class="api-connection-notice__content">
                <div class="text-subtitle-2 font-weight-bold mb-1">{{ formNoticeTitle }}</div>
                <p class="api-connection-notice__text text-body-2 text-medium-emphasis">{{ formNoticeText }}</p>
              </div>
            </div>
          </v-sheet>

          <div class="d-flex flex-wrap ga-3">
            <v-btn color="secondary" :loading="submitting" variant="flat" size="large" type="submit"> Подключить API </v-btn>
            <v-btn variant="text" size="large" @click="resetForm(false)"> Очистить поля </v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.api-connection-page {
  display: flex;
  flex-direction: column;
}

.api-connection-page__hero-card :deep(.app-empty-state__body) {
  justify-content: center;
  min-height: 100%;
}

.api-connection-page__hero-card :deep(.v-chip) {
  color: #ffffff;
}

.api-connection-page__hero-alert {
  border-color: rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.08);
}

.api-connection-page__hero-card :deep(.v-alert__content) {
  color: #ffffff;
}

.api-connection-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.api-connection-card__header {
  min-height: 96px;
}

.api-connection-card__divider {
  opacity: 1;
}

.api-connection-card__body {
  padding-top: 20px;
}

.api-connection-mini-card {
  border: 1px solid rgba(15, 23, 42, 0.07);
  background: rgba(255, 255, 255, 0.9);
}

.api-connection-mini-card__layout,
.api-connection-notice__layout {
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

.api-connection-mini-card__text,
.api-connection-notice__text {
  margin: 0;
  line-height: 1.6;
}

.api-connection-notice {
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: rgba(255, 255, 255, 0.9);
}

.api-connection-notice--success {
  background: rgba(0, 200, 83, 0.07);
}

.api-connection-notice--warning {
  background: rgba(255, 193, 7, 0.08);
}

.api-connection-notice--info {
  background: rgba(3, 201, 215, 0.07);
}

.api-connection-notice--error {
  background: rgba(244, 67, 54, 0.07);
}

.api-connection-notice__content {
  flex: 1 1 auto;
  min-width: 0;
}

@media (max-width: 959px) {
  .api-connection-mini-card__layout,
  .api-connection-notice__layout {
    gap: 12px;
  }
}
</style>
