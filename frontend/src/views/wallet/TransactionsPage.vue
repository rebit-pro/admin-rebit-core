<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { DownloadIcon, HistoryIcon } from 'vue-tabler-icons';
import { useWalletStore } from '@/stores/wallet';
import { useCurrencyFormat } from '@/composables/useCurrencyFormat';
import { useTransactionLabels } from '@/composables/useTransactionLabels';
import type { TransactionFilters } from '@/api/wallet';
import AppEmptyState from '@/components/shared/AppEmptyState.vue';
import UiTableCard from '@/components/shared/UiTableCard.vue';
import UiParentCard from '@/components/shared/UiParentCard.vue';

const wallet = useWalletStore();
const { formatAmount, formatDate } = useCurrencyFormat();
const { txLabel, txColor } = useTransactionLabels();

const typeFilter = ref<string | undefined>(undefined);
const dateFrom = ref('');
const dateTo = ref('');
const currentPage = ref(1);
const itemsPerPage = 50;

const typeOptions = [
  { title: 'Все', value: undefined },
  { title: 'Депозит', value: 'deposit' },
  { title: 'Вывод', value: 'withdrawal' },
  { title: 'Покупка', value: 'trade_buy' },
  { title: 'Продажа', value: 'trade_sell' },
  { title: 'Блокировка', value: 'lock' },
  { title: 'Разблокировка', value: 'unlock' },
  { title: 'Комиссия', value: 'fee' }
];

const totalPages = computed(() => Math.max(1, Math.ceil(wallet.transactionsTotal / itemsPerPage)));

function buildParams(): TransactionFilters {
  const params: TransactionFilters = {
    limit: itemsPerPage,
    offset: (currentPage.value - 1) * itemsPerPage
  };
  if (typeFilter.value) params.type = typeFilter.value;
  if ('' !== dateFrom.value) params.dateFrom = dateFrom.value;
  if ('' !== dateTo.value) params.dateTo = dateTo.value;
  return params;
}

async function loadTransactions(): Promise<void> {
  await wallet.fetchTransactions(buildParams());
}

function onFilterChange(): void {
  if (1 === currentPage.value) {
    void loadTransactions();
  } else {
    currentPage.value = 1;
  }
}

async function handleExport(): Promise<void> {
  const params: TransactionFilters = {};
  if (typeFilter.value) params.type = typeFilter.value;
  if ('' !== dateFrom.value) params.dateFrom = dateFrom.value;
  if ('' !== dateTo.value) params.dateTo = dateTo.value;
  await wallet.exportTransactions(params);
}

watch(currentPage, () => {
  void loadTransactions();
});

onMounted(async () => {
  await loadTransactions();
});
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3">
      <h2 class="text-h4 font-weight-bold">Транзакции</h2>
      <v-btn color="secondary" variant="outlined" size="small" rounded="lg" @click="handleExport">
        <template #prepend>
          <DownloadIcon :size="18" stroke-width="1.75" />
        </template>
        Экспорт
      </v-btn>
    </div>

    <!-- Фильтры -->
    <UiParentCard title="Фильтры" icon="mdi-filter-outline" color="secondary" header-compact class="mb-4">
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="3">
            <v-select
              v-model="typeFilter"
              :items="typeOptions"
              item-title="title"
              item-value="value"
              label="Тип"
              variant="outlined"
              density="compact"
              hide-details
              @update:model-value="onFilterChange"
            />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field
              v-model="dateFrom"
              label="Дата с"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
              @change="onFilterChange"
            />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field
              v-model="dateTo"
              label="Дата по"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
              @change="onFilterChange"
            />
          </v-col>
          <v-col cols="12" sm="3" class="d-flex align-center">
            <v-btn
              variant="text"
              size="small"
              @click="
                typeFilter = undefined;
                dateFrom = '';
                dateTo = '';
                onFilterChange();
              "
            >
              Сбросить
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </UiParentCard>

    <v-row v-if="wallet.loading" justify="center" class="mt-8">
      <v-progress-circular indeterminate color="primary" />
    </v-row>

    <v-alert v-if="wallet.error" type="error" variant="tonal" class="mb-4">{{ wallet.error }}</v-alert>

    <AppEmptyState
      v-if="!wallet.loading && 0 === wallet.transactions.length"
      :icon="HistoryIcon"
      tone="info"
      title="Нет транзакций"
      description="История операций пока пуста. Когда появятся депозиты, выводы или торговые движения, они отобразятся здесь и будут доступны для экспорта."
    />

    <UiTableCard
      v-else-if="!wallet.loading"
      title="История операций"
      subtitle="Все движения средств по вашему аккаунту"
      icon="mdi-history"
      color="info"
      gradient="neutral"
    >
      <v-table density="comfortable" hover>
        <thead>
          <tr>
            <th>Тип</th>
            <th class="text-right">Сумма</th>
            <th>Валюта</th>
            <th>Сделка</th>
            <th>Дата</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tx in wallet.transactions" :key="tx.id">
            <td>
              <v-chip size="small" variant="tonal" :color="txColor(tx.type)">{{ txLabel(tx.type) }}</v-chip>
            </td>
            <td class="text-right font-weight-medium">{{ formatAmount(tx.amount, tx.currency) }}</td>
            <td>{{ tx.currency }}</td>
            <td class="text-lightText">{{ tx.tradeId ?? '—' }}</td>
            <td class="text-lightText">{{ formatDate(tx.createdAt) }}</td>
          </tr>
        </tbody>
      </v-table>

      <v-card-actions v-if="totalPages > 1" class="justify-center">
        <v-pagination v-model="currentPage" :length="totalPages" :total-visible="7" density="compact" />
      </v-card-actions>
    </UiTableCard>
  </div>
</template>
