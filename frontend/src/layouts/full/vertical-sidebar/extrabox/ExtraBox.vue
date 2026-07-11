<script setup lang="ts">
import { useIdentityStore } from '@/stores/identity';
import { computed } from 'vue';

const identity = useIdentityStore();

const statusText = computed(() => {
  if (!identity.isConnected) {
    return 'Не подключён';
  }

  return identity.statusLabel ?? 'Требует проверки';
});

const statusColor = computed(() => {
  const status = identity.connectionStatus?.status;

  if (!identity.isConnected) {
    return 'warning';
  }

  switch (status) {
    case 'active':
      return 'success';
    case 'invalid':
      return 'error';
    case 'pending_verification':
      return 'info';
    case 'revoked':
      return 'warning';
    default:
      return 'info';
  }
});
</script>

<template>
  <v-sheet rounded="md" :color="`light${statusColor}`" class="pa-4 ExtraBox hide-menu">
    <div class="d-flex align-center">
      <v-avatar :color="statusColor" variant="tonal" size="40" rounded="md" class="mr-3">
        <v-icon size="20">{{ identity.isConnected ? 'mdi-link-variant' : 'mdi-link-variant-off' }}</v-icon>
      </v-avatar>
      <div>
        <h5 class="text-subtitle-2 font-weight-bold mb-0">Bybit API</h5>
        <small :class="`text-${statusColor}`">{{ statusText }}</small>
      </div>
    </div>
    <v-btn v-if="!identity.isConnected" color="primary" variant="tonal" size="small" block class="mt-3" to="/profile/api-connection">
      Подключить
    </v-btn>
    <div v-else class="mt-3 text-caption text-lightText">Режим: {{ identity.modeLabel }}</div>
  </v-sheet>
</template>

<style lang="scss">
.ExtraBox {
  position: relative;
  overflow: hidden;
}
</style>
