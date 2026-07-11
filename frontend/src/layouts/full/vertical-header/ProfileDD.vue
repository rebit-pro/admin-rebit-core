<script setup lang="ts">
import { LogoutIcon } from 'vue-tabler-icons';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
</script>

<template>
  <div class="pa-4">
    <h4 class="mb-n1">
      {{ authStore.user?.name ?? authStore.user?.email ?? 'Пользователь' }}
    </h4>
    <span class="text-subtitle-2 text-medium-emphasis">{{ authStore.user?.email }}</span>

    <v-divider class="my-3" />

    <v-list density="compact">
      <v-list-item color="secondary" rounded="md" @click="authStore.logout()">
        <template #prepend>
          <LogoutIcon size="20" class="me-2" />
        </template>
        <v-list-item-title class="text-subtitle-2">Выйти</v-list-item-title>
      </v-list-item>
    </v-list>
  </div>
</template>
