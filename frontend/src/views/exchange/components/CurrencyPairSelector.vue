<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useExchangeStore } from '@/stores/exchange';
import type { CurrencyPair } from '@/api/exchange';

type OrderBookFilters = {
  selectedMethods: string[];
  limitMin: string;
  limitMax: string;
};

const emit = defineEmits<{
  (event: 'update:filters', filters: OrderBookFilters): void;
}>();

const exchange = useExchangeStore();
const selectedMethods = ref<string[]>([]);
const limitMin = ref<string>('');
const limitMax = ref<string>('');

const hasActiveFilters = computed(() => 0 < selectedMethods.value.length || '' !== limitMin.value || '' !== limitMax.value);
const uniqueCurrencyPairs = computed(() => {
  const pairMap = new Map<string, CurrencyPair>();

  exchange.currencyPairs.forEach((pair) => {
    const key = `${pair.token}-${pair.fiat}`;

    if (!pairMap.has(key)) {
      pairMap.set(key, pair);
    }
  });

  return [...pairMap.values()];
});

function onSelectPair(pair: CurrencyPair): void {
  exchange.selectPair(pair);
}

function toggleMethod(method: string): void {
  const idx = selectedMethods.value.indexOf(method);
  if (-1 === idx) {
    selectedMethods.value.push(method);
  } else {
    selectedMethods.value.splice(idx, 1);
  }
}

function clearFilters(): void {
  selectedMethods.value = [];
  limitMin.value = '';
  limitMax.value = '';
}

const isActivePair = computed(
  () => (pair: CurrencyPair) => pair.token === exchange.selectedPair.token && pair.fiat === exchange.selectedPair.fiat
);

watch(
  () => [selectedMethods.value, limitMin.value, limitMax.value],
  () => {
    emit('update:filters', {
      selectedMethods: [...selectedMethods.value],
      limitMin: limitMin.value,
      limitMax: limitMax.value
    });
  },
  { immediate: true, deep: true }
);
</script>

<template>
  <v-card class="currency-pair-selector" rounded="lg">
    <v-card-item class="currency-pair-selector__header px-5 py-4">
      <template #prepend>
        <v-avatar size="42" color="secondary" variant="tonal">
          <v-icon>mdi-tune-variant</v-icon>
        </v-avatar>
      </template>
      <v-card-title class="text-h6 font-weight-bold">Пара и фильтры</v-card-title>
      <v-card-subtitle>Выберите направление поиска, методы оплаты и рабочие лимиты сделки</v-card-subtitle>
    </v-card-item>

    <v-divider class="currency-pair-selector__divider" />

    <v-card-text class="currency-pair-selector__body pa-5">
      <div class="currency-pair-selector__section">
        <div class="text-subtitle-2 font-weight-bold mb-3">Валютные пары</div>
        <v-chip-group mandatory>
          <v-chip
            v-for="pair in uniqueCurrencyPairs"
            :key="`${pair.token}-${pair.fiat}`"
            class="currency-pair-selector__chip"
            :color="isActivePair(pair) ? 'secondary' : undefined"
            :variant="isActivePair(pair) ? 'flat' : 'outlined'"
            rounded="lg"
            size="default"
            @click="onSelectPair(pair)"
          >
            {{ pair.label }}
          </v-chip>
        </v-chip-group>
      </div>

      <div v-if="0 < exchange.paymentMethods.length" class="currency-pair-selector__section">
        <div class="text-subtitle-2 font-weight-bold mb-3">Методы оплаты</div>
        <div class="d-flex align-center ga-2 flex-wrap">
          <v-chip
            v-for="method in exchange.paymentMethods"
            :key="method.id"
            class="currency-pair-selector__chip"
            :color="selectedMethods.includes(method.code) ? 'primary' : undefined"
            :variant="selectedMethods.includes(method.code) ? 'flat' : 'outlined'"
            rounded="lg"
            size="small"
            @click="toggleMethod(method.code)"
          >
            {{ method.name }}
          </v-chip>
        </div>
      </div>

      <div class="currency-pair-selector__section">
        <div class="text-subtitle-2 font-weight-bold mb-3">Фильтр по лимитам</div>
        <div class="d-flex align-center ga-3 flex-wrap">
          <v-text-field
            v-model="limitMin"
            label="От"
            type="number"
            min="0"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            class="currency-pair-selector__field"
            :suffix="exchange.selectedPair.fiat"
          />
          <v-text-field
            v-model="limitMax"
            label="До"
            type="number"
            min="0"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            class="currency-pair-selector__field"
            :suffix="exchange.selectedPair.fiat"
          />
        </div>
      </div>

      <div v-if="hasActiveFilters" class="currency-pair-selector__actions">
        <v-btn
          class="currency-pair-selector__reset-btn"
          color="error"
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-filter-remove-outline"
          @click="clearFilters"
        >
          Сбросить все фильтры
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<style scoped lang="scss">
.currency-pair-selector {
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.currency-pair-selector__header {
  min-height: 96px;
}

.currency-pair-selector__divider {
  opacity: 1;
}

.currency-pair-selector__body {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.currency-pair-selector__section {
  display: flex;
  flex-direction: column;
}

.currency-pair-selector__field {
  max-width: 160px;
}

.currency-pair-selector__actions {
  display: flex;
  justify-content: flex-start;
}

.currency-pair-selector__reset-btn {
  font-weight: 600;
}

.currency-pair-selector__chip {
  border-color: rgba(15, 23, 42, 0.12);
  color: rgba(15, 23, 42, 0.82);
}

.currency-pair-selector__chip:hover {
  border-color: rgba(30, 136, 229, 0.24);
}
</style>
