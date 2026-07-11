<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useTradesStore } from '@/stores/trades';
import { usePolling } from '@/composables/usePolling';
import { useCurrencyFormat } from '@/composables/useCurrencyFormat';
import type { TradeStatus } from '@/api/exchange';
import { isMockApiEnabled } from '@/mocks/config';
import UiTableCard from '@/components/shared/UiTableCard.vue';

const router = useRouter();
const trades = useTradesStore();
const { formatRub, formatDate } = useCurrencyFormat();

const statusFilter = ref<TradeStatus | ''>('');

const statusOptions: { title: string; value: TradeStatus | '' }[] = [
  { title: 'Все', value: '' },
  { title: 'Ожидание оплаты', value: 'pending_payment' },
  { title: 'Оплата отправлена', value: 'payment_sent' },
  { title: 'Оплата подтверждена', value: 'payment_confirmed' },
  { title: 'Завершена', value: 'completed' },
  { title: 'Отменена', value: 'cancelled' },
  { title: 'Спор', value: 'disputed' }
];

const statusLabels: Record<string, string> = {
  pending_payment: 'Ожидание оплаты',
  payment_sent: 'Оплата отправлена',
  payment_confirmed: 'Оплата подтверждена',
  completed: 'Завершена',
  cancelled: 'Отменена',
  disputed: 'Спор'
};

const statusColors: Record<string, string> = {
  pending_payment: 'warning',
  payment_sent: 'info',
  payment_confirmed: 'primary',
  completed: 'success',
  cancelled: 'error',
  disputed: 'error'
};

async function loadTrades(): Promise<void> {
  const status = '' !== statusFilter.value ? statusFilter.value : undefined;
  await trades.fetchTrades(status);
}

function openTrade(id: number): void {
  void router.push(`/exchange/trades/${id}`);
}

const polling = usePolling(loadTrades, 10000);

onMounted(async () => {
  await loadTrades();
  polling.start();
});

onUnmounted(() => {
  polling.stop();
});
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3">
      <h2 class="text-h4 font-weight-bold">Сделки</h2>
      <v-select
        v-model="statusFilter"
        :items="statusOptions"
        item-title="title"
        item-value="value"
        label="Статус"
        variant="outlined"
        density="compact"
        hide-details
        style="max-width: 240px"
        @update:model-value="loadTrades"
      />
    </div>

    <v-alert v-if="isMockApiEnabled" type="info" variant="tonal" class="mb-4">
      Новая сделка подсвечивается, пока вы её не откроете. Первый шаг одношагового сценария уже отправлен в чат, статус — «Ожидание оплаты».
    </v-alert>

    <v-row v-if="trades.loading && 0 === trades.trades.length" justify="center" class="mt-8">
      <v-progress-circular indeterminate color="primary" />
    </v-row>

    <v-alert v-if="trades.error" type="error" variant="tonal" class="mb-4">{{ trades.error }}</v-alert>

    <UiTableCard
      v-if="!trades.loading || 0 < trades.trades.length"
      title="Список сделок"
      subtitle="Все ваши P2P сделки"
      icon="mdi-swap-horizontal-bold"
      color="primary"
      gradient="neutral"
    >
      <v-table density="comfortable" hover>
        <thead>
          <tr>
            <th>Контрагент</th>
            <th>Тип</th>
            <th class="text-right">Цена</th>
            <th class="text-right">Сумма (фиат)</th>
            <th>Статус</th>
            <th>Дата</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="0 === trades.trades.length">
            <td colspan="6" class="text-center text-medium-emphasis pa-6">Нет сделок</td>
          </tr>
          <tr
            v-for="trade in trades.trades"
            :key="trade.id"
            class="cursor-pointer"
            :class="{ 'trades-page__row--new': true === trade.isNew }"
            @click="openTrade(trade.id)"
          >
            <td>
              <div class="d-flex align-center">
                <v-avatar size="28" color="lightsecondary" class="mr-2">
                  <span class="text-caption">{{ trade.counterpartyName.charAt(0).toUpperCase() }}</span>
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-medium">{{ trade.counterpartyName }}</div>
                  <v-chip v-if="true === trade.isNew" size="x-small" color="warning" variant="tonal" class="mt-1">
                    Новый сценарий отправлен
                  </v-chip>
                </div>
              </div>
            </td>
            <td>
              <v-chip size="small" variant="tonal" :color="'buy' === trade.side ? 'success' : 'error'">
                {{ 'buy' === trade.side ? 'Покупка' : 'Продажа' }}
              </v-chip>
            </td>
            <td class="text-right font-weight-medium">{{ formatRub(trade.price) }}</td>
            <td class="text-right font-weight-bold">{{ formatRub(trade.fiatAmount) }}</td>
            <td>
              <v-chip size="small" variant="tonal" :color="statusColors[trade.status] ?? 'default'">
                {{ statusLabels[trade.status] ?? trade.status }}
              </v-chip>
            </td>
            <td class="text-medium-emphasis text-body-2">{{ formatDate(trade.createdAt) }}</td>
          </tr>
        </tbody>
      </v-table>
    </UiTableCard>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}

.trades-page__row--new {
  background: rgba(255, 193, 7, 0.08);
}
</style>
