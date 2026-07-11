<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const userDisplayName = computed(() => auth.user?.name ?? auth.user?.email ?? 'пользователь');

const starterItems = [
  {
    title: 'Авторизация подключена',
    description: 'Логин, регистрация, хранение токена и защита маршрутов уже работают.',
    icon: 'mdi-shield-check-outline',
    color: 'success'
  },
  {
    title: 'Дашборд очищен',
    description: 'Лишние проектные разделы убраны, оставлен чистый стартовый экран.',
    icon: 'mdi-view-dashboard-outline',
    color: 'primary'
  },
  {
    title: 'Готов к модулям админки',
    description: 'Следующим шагом можно добавлять пользователей, роли, настройки и аудит.',
    icon: 'mdi-puzzle-outline',
    color: 'secondary'
  }
];
</script>

<template>
  <main class="dashboard-page">
    <v-card class="dashboard-hero mb-6" rounded="lg">
      <v-card-text class="pa-6 pa-md-8">
        <v-row align="center">
          <v-col cols="12" md="8">
            <v-chip color="primary" variant="tonal" size="small" class="font-weight-bold mb-4">ReBit Admin Core</v-chip>
            <h1 class="text-h4 text-md-h3 font-weight-bold mb-2">Добро пожаловать, {{ userDisplayName }}</h1>
            <p class="text-body-1 text-medium-emphasis mb-0 dashboard-hero__subtitle">
              Это стартовый экран универсальной админ-панели. Оставлен только базовый контур:
              авторизация, регистрация, защищённый кабинет и меню пользователя.
            </p>
          </v-col>

          <v-col cols="12" md="4">
            <v-sheet class="dashboard-status pa-5" rounded="lg">
              <div class="text-caption text-medium-emphasis mb-1">Текущий статус</div>
              <div class="text-h6 font-weight-bold mb-3">База интерфейса готова</div>
              <v-progress-linear model-value="35" color="primary" height="8" rounded />
              <div class="text-caption text-medium-emphasis mt-3">Следующий этап: спроектировать модули админки.</div>
            </v-sheet>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-row>
      <v-col v-for="item in starterItems" :key="item.title" cols="12" md="4">
        <v-card class="dashboard-card h-100" rounded="lg">
          <v-card-text class="pa-5">
            <v-avatar :color="item.color" variant="tonal" size="46" class="mb-4">
              <v-icon>{{ item.icon }}</v-icon>
            </v-avatar>
            <h2 class="text-h6 font-weight-bold mb-2">{{ item.title }}</h2>
            <p class="text-body-2 text-medium-emphasis mb-0">{{ item.description }}</p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </main>
</template>

<style scoped lang="scss">
.dashboard-page {
  min-height: calc(100vh - 170px);
}

.dashboard-hero {
  background:
    linear-gradient(135deg, rgba(var(--v-theme-primary), 0.14), rgba(var(--v-theme-secondary), 0.08)),
    rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-theme-primary), 0.14);
}

.dashboard-hero__subtitle {
  max-width: 760px;
  line-height: 1.7;
}

.dashboard-status,
.dashboard-card {
  border: 1px solid rgba(var(--v-theme-borderColor), 0.7);
}
</style>