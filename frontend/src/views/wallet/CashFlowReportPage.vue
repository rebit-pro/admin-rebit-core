<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { walletApi, type CashFlowReport, type CashFlowFilters } from '@/api/wallet';
import { exchangeApi, type Currency } from '@/api/exchange';
import UiParentCard from '@/components/shared/UiParentCard.vue';
import UiTableCard from '@/components/shared/UiTableCard.vue';

const dateFrom = ref('');
const dateTo = ref('');
const currencyId = ref<number | undefined>(undefined);
const currencies = ref<Currency[]>([]);
const report = ref<CashFlowReport | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const currencyOptions = ref<{ title: string; value: number | undefined }[]>([{ title: 'Все валюты', value: undefined }]);

function fmt(value: number): string {
  return new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 8
  }).format(value);
}

async function loadCurrencies(): Promise<void> {
  try {
    currencies.value = await exchangeApi.getCurrencies();
    currencyOptions.value = [
      { title: 'Все валюты', value: undefined },
      ...currencies.value.map((c) => ({ title: `${c.code} — ${c.name}`, value: c.id }))
    ];
  } catch {
    // fallback
  }
}

async function loadReport(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    const params: CashFlowFilters = {};
    if ('' !== dateFrom.value) params.dateFrom = dateFrom.value;
    if ('' !== dateTo.value) params.dateTo = dateTo.value;
    if (undefined !== currencyId.value) params.currencyId = currencyId.value;

    report.value = await walletApi.getCashFlowReport(params);
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки отчёта';
  } finally {
    loading.value = false;
  }
}

function handleApply(): void {
  void loadReport();
}

function handleReset(): void {
  dateFrom.value = '';
  dateTo.value = '';
  currencyId.value = undefined;
  void loadReport();
}

onMounted(async () => {
  await loadCurrencies();
  await loadReport();
});
</script>

<template>
  <div>
    <h2 class="text-h4 font-weight-bold mb-6">Обороты денежных средств</h2>

    <!-- Фильтры -->
    <UiParentCard title="Фильтры" icon="mdi-filter-outline" color="secondary" header-compact class="mb-4">
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="3">
            <v-text-field v-model="dateFrom" label="Период с" type="date" variant="outlined" density="compact" hide-details />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model="dateTo" label="Период по" type="date" variant="outlined" density="compact" hide-details />
          </v-col>
          <v-col cols="12" sm="3">
            <v-select
              v-model="currencyId"
              :items="currencyOptions"
              item-title="title"
              item-value="value"
              label="Валюта"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" sm="3" class="d-flex align-center ga-2">
            <v-btn color="secondary" size="small" prepend-icon="mdi-filter" @click="handleApply"> Применить </v-btn>
            <v-btn variant="text" size="small" @click="handleReset"> Сбросить </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </UiParentCard>

    <v-row v-if="loading" justify="center" class="mt-8">
      <v-progress-circular indeterminate color="primary" />
    </v-row>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

    <!-- Таблица отчёта -->
    <UiTableCard
      v-if="!loading && report"
      title="Отчёт по оборотам"
      subtitle="Движение средств по валютам за выбранный период"
      icon="mdi-chart-bar"
      color="primary"
      gradient="neutral"
    >
      <v-table density="comfortable">
        <thead>
          <tr>
            <th>Валюта</th>
            <th class="text-right">Остаток на начало</th>
            <th class="text-right">Приход</th>
            <th class="text-right">Расход</th>
            <th class="text-right">Остаток на конец</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="0 === report.items.length">
            <td colspan="5" class="text-center text-lightText pa-6">Нет данных за выбранный период</td>
          </tr>
          <tr v-for="item in report.items" :key="item.currencyId">
            <td class="font-weight-medium">{{ item.currency }}</td>
            <td class="text-right">{{ fmt(item.openingBalance) }}</td>
            <td class="text-right text-success">+{{ fmt(item.incoming) }}</td>
            <td class="text-right text-error">−{{ fmt(item.outgoing) }}</td>
            <td class="text-right font-weight-bold">{{ fmt(item.closingBalance) }}</td>
          </tr>
        </tbody>
        <tfoot v-if="0 < report.items.length && null !== report.totals">
          <tr class="font-weight-bold bg-grey-lighten-4">
            <td>Итого</td>
            <td class="text-right">{{ fmt(report.totals.totalOpeningBalance) }}</td>
            <td class="text-right text-success">+{{ fmt(report.totals.totalIncoming) }}</td>
            <td class="text-right text-error">−{{ fmt(report.totals.totalOutgoing) }}</td>
            <td class="text-right">{{ fmt(report.totals.totalClosingBalance) }}</td>
          </tr>
        </tfoot>
      </v-table>
    </UiTableCard>

    <!-- Информационное сообщение -->
    <v-alert type="info" variant="tonal" class="mt-4" density="compact">
      <strong>Классификация:</strong> Приход = депозит, продажа, разблокировка. Расход = вывод, покупка, блокировка, комиссия. Остатки
      рассчитываются по данным транзакций за выбранный период.
    </v-alert>
  </div>
</template>
