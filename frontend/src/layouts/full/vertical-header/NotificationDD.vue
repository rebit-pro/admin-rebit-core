<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useTradesStore } from '@/stores/trades';

const router = useRouter();
const trades = useTradesStore();

const notificationItems = computed(() => {
  return trades.trades
    .filter((trade) => true === trade.isNew)
    .slice(0, 5)
    .map((trade) => ({
      id: trade.id,
      color: 'warning',
      icon: 'swap-horizontal',
      title: `Новая сделка #${trade.id}`,
      desc: `${trade.counterpartyName} · ${'buy' === trade.side ? 'Покупка' : 'Продажа'} · ${trade.fiatAmount.toFixed(2)} ₽`
    }));
});

function openTrade(tradeId: number): void {
  void router.push(`/exchange/trades/${tradeId}`);
}
</script>

<template>
  <div class="pa-4">
    <div class="d-flex align-center justify-space-between mb-3">
      <h6 class="text-subtitle-1">Уведомления</h6>
      <v-chip size="small" variant="tonal" color="primary">
        {{ notificationItems.length }}
      </v-chip>
    </div>
  </div>
  <v-divider />
  <v-list class="py-0" lines="two">
    <template v-for="(item, i) in notificationItems" :key="item.id">
      <v-list-item color="secondary" class="no-spacer" @click="openTrade(item.id)">
        <template #prepend>
          <v-avatar size="40" variant="flat" :color="`light${item.color}`" :class="`me-3 py-2 text-${item.color}`">
            <v-icon size="20">mdi-{{ item.icon }}</v-icon>
          </v-avatar>
        </template>
        <h6 class="text-subtitle-1">{{ item.title }}</h6>
        <p class="text-subtitle-2 text-medium-emphasis mt-1">{{ item.desc }}</p>
      </v-list-item>
      <v-divider v-if="i < notificationItems.length - 1" />
    </template>

    <v-list-item v-if="0 === notificationItems.length" class="text-center text-lightText pa-6"> Новых сделок пока нет </v-list-item>
  </v-list>
</template>
