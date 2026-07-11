<script setup lang="ts">
import { computed, type Component } from 'vue';
import {
  AlertCircleIcon,
  ClockExclamationIcon,
  KeyIcon,
  LinkOffIcon,
  LoginIcon,
  PlugConnectedIcon,
  ShieldLockIcon,
  UserPlusIcon
} from 'vue-tabler-icons';
import type { ApiConnectionStatus } from '@/api/identity';
import AppEmptyState from '@/components/shared/AppEmptyState.vue';

type ActionButton = {
  text: string;
  to: string;
  color: string;
  variant: 'flat' | 'outlined';
  icon: Component;
};

type AccessStateContent = {
  icon: Component;
  tone: 'secondary' | 'warning' | 'error' | 'info' | 'primary';
  title: string;
  description: string;
  actions: ActionButton[];
  steps: string[];
};

const props = defineProps<{
  isAuthenticated: boolean;
  connectionStatus?: ApiConnectionStatus['status'];
}>();

const guestActions: ActionButton[] = [
  {
    text: 'Войти',
    to: '/login',
    color: 'secondary',
    variant: 'flat',
    icon: LoginIcon
  },
  {
    text: 'Зарегистрироваться',
    to: '/register',
    color: 'primary',
    variant: 'outlined',
    icon: UserPlusIcon
  }
];

const bybitActions: ActionButton[] = [
  {
    text: 'Подключить Bybit API',
    to: '/profile/api-connection',
    color: 'secondary',
    variant: 'flat',
    icon: PlugConnectedIcon
  }
];

const content = computed<AccessStateContent>(() => {
  if (!props.isAuthenticated) {
    return {
      icon: ShieldLockIcon,
      tone: 'secondary',
      title: 'Авторизуйтесь, чтобы смотреть стаканы',
      description:
        'Стаканы P2P доступны только после входа в аккаунт и подключения Bybit API. Авторизуйтесь или зарегистрируйтесь, затем добавьте ключи в профиле.',
      actions: guestActions,
      steps: ['Войти или зарегистрироваться', 'Подключить API-ключи Bybit', 'Вернуться к стаканам и выбрать торговую пару']
    };
  }

  switch (props.connectionStatus) {
    case 'invalid':
      return {
        icon: AlertCircleIcon,
        tone: 'warning',
        title: 'Проверьте Bybit API-ключи',
        description:
          'Подключение к Bybit найдено, но ключи не прошли проверку. Обновите API Key и Secret Key в профиле, чтобы снова открыть доступ к стаканам.',
        actions: bybitActions,
        steps: ['Открыть настройки Bybit API', 'Проверить права ключа и Secret Key', 'Сохранить новые ключи и повторить проверку']
      };
    case 'revoked':
      return {
        icon: LinkOffIcon,
        tone: 'error',
        title: 'Доступ к Bybit API отозван',
        description:
          'Bybit больше не принимает сохранённые ключи. Подключите API повторно, чтобы загрузить стаканы и включить автообновление.',
        actions: bybitActions,
        steps: [
          'Подключить новый Bybit API-ключ',
          'Проверить режим mainnet или testnet',
          'Вернуться к стаканам после успешного подключения'
        ]
      };
    case 'pending_verification':
      return {
        icon: ClockExclamationIcon,
        tone: 'info',
        title: 'Подключение Bybit API ожидает проверки',
        description:
          'Ключи сохранены, но подключение ещё не подтверждено. Завершите проверку в профиле, после чего стаканы станут доступны.',
        actions: bybitActions,
        steps: [
          'Открыть страницу подключения API',
          'Запустить или повторить проверку ключей',
          'Дождаться статуса active и вернуться к стаканам'
        ]
      };
    default:
      return {
        icon: KeyIcon,
        tone: 'primary',
        title: 'Подключите API-ключи Bybit',
        description:
          'Для вывода стаканов P2P необходимо подключить Bybit API в профиле. После подключения ключей таблица ордеров и автообновление станут доступны.',
        actions: bybitActions,
        steps: [
          'Открыть профиль и подключить Bybit API',
          'Убедиться, что статус подключения active',
          'Вернуться к стаканам и выбрать торговую пару'
        ]
      };
  }
});
</script>

<template>
  <AppEmptyState
    class="orderbook-access-state"
    :icon="content.icon"
    :tone="content.tone"
    :title="content.title"
    :description="content.description"
    eyebrow="P2P доступ"
  >
    <template #actions>
      <div class="d-flex flex-wrap justify-center ga-3 mb-2">
        <v-btn
          v-for="action in content.actions"
          :key="action.text"
          :to="action.to"
          :color="action.color"
          :variant="action.variant"
          rounded="lg"
          size="large"
        >
          <template #prepend>
            <component :is="action.icon" :size="18" stroke-width="1.75" />
          </template>
          {{ action.text }}
        </v-btn>
      </div>
    </template>

    <v-alert color="info" variant="tonal" class="text-left">
      <div class="font-weight-medium mb-1">Что нужно сделать</div>
      <ul class="pl-5 mb-0">
        <li v-for="step in content.steps" :key="step">{{ step }}</li>
      </ul>
    </v-alert>
  </AppEmptyState>
</template>
