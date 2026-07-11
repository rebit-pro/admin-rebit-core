<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useCustomizerStore } from '@/stores/customizer';
import { BellIcon, Menu2Icon } from 'vue-tabler-icons';
import Logo from '../logo/LogoMain.vue';
import NotificationDD from '../vertical-header/NotificationDD.vue';
import ProfileDD from '../vertical-header/ProfileDD.vue';
import { useTradesStore } from '@/stores/trades';
import { usePolling } from '@/composables/usePolling';

const customizer = useCustomizerStore();
const trades = useTradesStore();
const route = useRoute();
const newTradesCount = computed(() => trades.trades.filter((trade) => true === trade.isNew).length);
const shouldPollTrades = computed(() => !route.path.startsWith('/exchange/trades'));
const polling = usePolling(async () => {
  await trades.fetchTrades();
}, 10000);

async function syncHeaderPollingState(): Promise<void> {
  if (!shouldPollTrades.value) {
    polling.stop();

    return;
  }

  if (0 === trades.trades.length) {
    await trades.fetchTrades();
  }

  polling.start();
}

onMounted(async () => {
  await syncHeaderPollingState();
});

watch(shouldPollTrades, async () => {
  await syncHeaderPollingState();
});

onUnmounted(() => {
  polling.stop();
});
</script>

<template>
  <v-app-bar elevation="0" height="80">
    <div class="pa-5 hidden-md-and-down">
      <Logo />
    </div>
    <v-btn
      class="hidden-lg-and-up text-secondary ms-3"
      color="lightsecondary"
      icon
      rounded="sm"
      variant="flat"
      size="small"
      @click.stop="customizer.SET_SIDEBAR_DRAWER"
    >
      <Menu2Icon size="20" stroke-width="1.5" />
    </v-btn>

    <v-spacer />

    <!-- Notification -->
    <v-menu :close-on-content-click="false">
      <template #activator="{ props }">
        <v-badge :model-value="0 < newTradesCount" :content="newTradesCount" color="error" offset-x="8" offset-y="8">
          <v-btn icon class="text-secondary mx-3" color="lightsecondary" rounded="sm" size="small" variant="flat" v-bind="props">
            <BellIcon stroke-width="1.5" size="22" />
          </v-btn>
        </v-badge>
      </template>
      <v-sheet rounded="md" width="330" elevation="12">
        <NotificationDD />
      </v-sheet>
    </v-menu>

    <!-- User Profile -->
    <v-menu :close-on-content-click="false">
      <template #activator="{ props }">
        <v-btn class="profileBtn" variant="text" rounded="pill" v-bind="props">
          <v-avatar size="30" color="primary" variant="tonal">
            <v-icon size="18">mdi-account</v-icon>
          </v-avatar>
        </v-btn>
      </template>
      <v-sheet rounded="md" width="330" elevation="12">
        <ProfileDD />
      </v-sheet>
    </v-menu>
  </v-app-bar>
</template>
