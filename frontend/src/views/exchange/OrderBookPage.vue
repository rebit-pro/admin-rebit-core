<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useExchangeStore } from '@/stores/exchange';
import { useIdentityStore } from '@/stores/identity';
import OrderBookTable from './components/OrderBookTable.vue';
import OrderBookAccessState from './components/OrderBookAccessState.vue';
import CurrencyPairSelector from './components/CurrencyPairSelector.vue';

type OrderBookFilters = {
  selectedMethods: string[];
  limitMin: string;
  limitMax: string;
};

const exchange = useExchangeStore();
const auth = useAuthStore();
const identity = useIdentityStore();
const filters = ref<OrderBookFilters>({
  selectedMethods: [],
  limitMin: '',
  limitMax: ''
});

const hasOrderBookAccess = computed(() => identity.hasActiveConnection);
const isResolvingOrderBookAccess = computed(() => identity.loading && null === identity.connectionStatus);
const orderBookConnectionStatus = computed(() => identity.connectionStatus?.['status'] ?? null);

function onFiltersUpdate(nextFilters: OrderBookFilters): void {
  filters.value = nextFilters;
}

watch(
  hasOrderBookAccess,
  async (value) => {
    exchange.setOrderBookAccess(value);

    if (!value) {
      exchange.stopAutoRefresh();

      return;
    }

    await exchange.fetchOrderBook();
    exchange.startAutoRefresh();
  },
  { immediate: true }
);

const bestBuyPrice = computed(() => {
  const prices = exchange.buyOrders.map((o) => parseFloat(o.price)).filter((n) => !isNaN(n));
  return 0 === prices.length ? null : Math.max(...prices);
});

const bestSellPrice = computed(() => {
  const prices = exchange.sellOrders.map((o) => parseFloat(o.price)).filter((n) => !isNaN(n));
  return 0 === prices.length ? null : Math.min(...prices);
});

const spread = computed(() => {
  if (null === bestBuyPrice.value || null === bestSellPrice.value) return null;
  return bestSellPrice.value - bestBuyPrice.value;
});

const spreadPercent = computed(() => {
  if (null === spread.value || null === bestSellPrice.value || 0 === bestSellPrice.value) return null;
  return (spread.value / bestSellPrice.value) * 100;
});

const orderBookMetrics = computed(() => [
  {
    title: 'Активная пара',
    value: exchange.selectedPair.label,
    description: 'Выберите валютную пару и настройте фильтры под ваш сценарий.',
    color: 'secondary',
    icon: 'mdi-swap-horizontal'
  },
  {
    title: 'Лучший buy',
    value: null === bestBuyPrice.value ? '—' : `${formatPrice(bestBuyPrice.value)} ₽`,
    description: `${exchange.buyOrders.length} предложений в стакане на покупку`,
    color: 'success',
    icon: 'mdi-arrow-down-bold'
  },
  {
    title: 'Лучший sell',
    value: null === bestSellPrice.value ? '—' : `${formatPrice(bestSellPrice.value)} ₽`,
    description: `${exchange.sellOrders.length} предложений в стакане на продажу`,
    color: 'error',
    icon: 'mdi-arrow-up-bold'
  },
  {
    title: 'Спрэд',
    value: null === spread.value ? '—' : `${formatPrice(spread.value)} ₽`,
    description:
      null === spreadPercent.value ? 'Недостаточно данных для расчёта' : `${formatPrice(spreadPercent.value)}% от лучшей продажи`,
    color: 'info',
    icon: 'mdi-chart-line'
  }
]);

function formatPrice(value: number): string {
  return value.toLocaleString('ru-RU', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

async function refreshOrderBook(): Promise<void> {
  if (!hasOrderBookAccess.value) {
    return;
  }

  await exchange.fetchOrderBook();
}

onMounted(async () => {
  await Promise.all([exchange.fetchCurrencyPairs(), exchange.fetchPaymentMethods(), identity.fetchStatus()]);
});

onUnmounted(() => {
  exchange.stopAutoRefresh();
});
</script>

<template>
  <div class="orderbook-page">
    <v-card class="orderbook-page__hero mb-6" rounded="lg">
      <v-card-text class="pa-6 pa-md-8">
        <v-row align="center">
          <v-col cols="12" lg="8">
            <div class="d-flex align-center flex-wrap ga-3 mb-4">
              <v-chip :color="hasOrderBookAccess ? 'success' : 'warning'" variant="tonal" size="small" class="font-weight-bold">
                {{ hasOrderBookAccess ? 'Bybit API активен' : 'Доступ к стакану ограничен' }}
              </v-chip>
              <v-chip color="secondary" variant="tonal" size="small" class="font-weight-bold"
                >Пара: {{ exchange.selectedPair.label }}</v-chip
              >
              <v-chip color="info" variant="tonal" size="small" class="font-weight-bold">Автообновление раз в 15 секунд</v-chip>
            </div>

            <h2 class="text-h4 text-md-h3 font-weight-bold mb-2">P2P стакан</h2>
            <p class="text-body-1 text-medium-emphasis mb-0 orderbook-page__hero-subtitle">
              Смотрите лучшие предложения на покупку и продажу, сравнивайте лимиты и быстро находите подходящие сценарии торговли.
            </p>
          </v-col>

          <v-col cols="12" lg="4">
            <div class="d-flex flex-column ga-3 orderbook-page__hero-actions">
              <v-btn
                class="orderbook-page__hero-button text-secondary"
                color="white"
                size="large"
                prepend-icon="mdi-refresh"
                :loading="exchange.loading"
                @click="refreshOrderBook"
              >
                Обновить стакан
              </v-btn>
              <v-btn
                class="orderbook-page__hero-button text-secondary"
                color="white"
                variant="outlined"
                size="large"
                prepend-icon="mdi-link-variant"
                to="/profile/api-connection"
              >
                Настроить Bybit API
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <template v-if="isResolvingOrderBookAccess">
      <v-row justify="center" class="mt-4">
        <v-progress-circular indeterminate color="primary" />
      </v-row>
    </template>

    <template v-else-if="hasOrderBookAccess">
      <v-row class="mb-2">
        <v-col v-for="metric in orderBookMetrics" :key="metric.title" cols="12" sm="6" xl="3">
          <v-card class="orderbook-page__metric-card" rounded="lg">
            <v-card-item class="orderbook-page__card-header orderbook-page__card-header--compact px-5 py-4">
              <template #prepend>
                <v-avatar size="42" :color="metric.color" variant="tonal">
                  <v-icon>{{ metric.icon }}</v-icon>
                </v-avatar>
              </template>
              <v-card-title class="text-body-2 font-weight-medium text-medium-emphasis">{{ metric.title }}</v-card-title>
            </v-card-item>

            <v-divider class="orderbook-page__card-divider" />

            <v-card-text class="orderbook-page__card-body pa-5">
              <div class="text-h6 font-weight-bold mb-1">{{ metric.value }}</div>
              <div class="text-body-2 text-medium-emphasis">{{ metric.description }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <CurrencyPairSelector class="mb-6 orderbook-page__filters" @update:filters="onFiltersUpdate" />

      <v-row>
        <v-col cols="12" md="6">
          <v-card class="orderbook-page__table-card orderbook-page__table-card--buy" rounded="lg">
            <v-card-item class="orderbook-page__card-header px-5 py-4">
              <template #prepend>
                <v-avatar size="42" color="success" variant="tonal">
                  <v-icon>mdi-arrow-down-bold</v-icon>
                </v-avatar>
              </template>
              <v-card-title class="text-h6 font-weight-bold">Покупка (Buy)</v-card-title>
              <v-card-subtitle>Лучшие предложения продавцов, у которых можно купить актив</v-card-subtitle>
            </v-card-item>

            <v-divider class="orderbook-page__card-divider" />

            <v-card-text class="pa-0">
              <OrderBookTable
                class="orderbook-page__table"
                :orders="exchange.buyOrders"
                :filter-methods="filters.selectedMethods"
                :limit-min="filters.limitMin"
                :limit-max="filters.limitMax"
                side="buy"
              />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card class="orderbook-page__table-card orderbook-page__table-card--sell" rounded="lg">
            <v-card-item class="orderbook-page__card-header px-5 py-4">
              <template #prepend>
                <v-avatar size="42" color="error" variant="tonal">
                  <v-icon>mdi-arrow-up-bold</v-icon>
                </v-avatar>
              </template>
              <v-card-title class="text-h6 font-weight-bold">Продажа (Sell)</v-card-title>
              <v-card-subtitle>Лучшие предложения покупателей, которым можно продать актив</v-card-subtitle>
            </v-card-item>

            <v-divider class="orderbook-page__card-divider" />

            <v-card-text class="pa-0">
              <OrderBookTable
                class="orderbook-page__table"
                :orders="exchange.sellOrders"
                :filter-methods="filters.selectedMethods"
                :limit-min="filters.limitMin"
                :limit-max="filters.limitMax"
                side="sell"
              />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-sheet
        v-if="null !== spread && null !== bestBuyPrice && null !== bestSellPrice && null !== spreadPercent"
        class="orderbook-page__spread mt-4 pa-4"
        rounded="lg"
      >
        <div class="orderbook-page__spread-layout">
          <v-avatar size="48" color="info" variant="tonal" class="flex-shrink-0">
            <v-icon>mdi-chart-timeline-variant</v-icon>
          </v-avatar>

          <div class="orderbook-page__spread-content">
            <div class="text-subtitle-1 font-weight-bold mb-1">Ситуация по спрэду</div>
            <p class="text-body-2 text-medium-emphasis mb-4 orderbook-page__spread-text">
              Разница между лучшей покупкой и лучшей продажей сейчас составляет <strong>{{ formatPrice(spread) }} ₽</strong> ({{
                formatPrice(spreadPercent)
              }}%). Это помогает быстро понять текущую плотность рынка.
            </p>

            <div class="d-flex flex-wrap ga-3">
              <v-chip color="success" variant="tonal">Buy: {{ formatPrice(bestBuyPrice) }} ₽</v-chip>
              <v-chip color="error" variant="tonal">Sell: {{ formatPrice(bestSellPrice) }} ₽</v-chip>
              <v-chip color="info" variant="tonal">Спрэд: {{ formatPrice(spread) }} ₽</v-chip>
            </div>
          </div>
        </div>
      </v-sheet>
    </template>

    <OrderBookAccessState v-else :is-authenticated="auth.isAuthenticated" :connection-status="orderBookConnectionStatus" />

    <v-row v-if="exchange.loading" justify="center" class="mt-4">
      <v-progress-circular indeterminate color="primary" />
    </v-row>
    <v-alert v-if="exchange.error" type="error" variant="tonal" class="mt-4">{{ exchange.error }}</v-alert>
  </div>
</template>

<style scoped lang="scss">
.orderbook-page {
  display: flex;
  flex-direction: column;
}

.orderbook-page__hero {
  border: 1px solid rgba(30, 136, 229, 0.12);
  background:
    radial-gradient(circle at top right, rgba(94, 53, 177, 0.12), transparent 35%),
    linear-gradient(135deg, rgba(30, 136, 229, 0.12), #ffffff);
  overflow: hidden;
}

.orderbook-page__hero-subtitle {
  max-width: 700px;
}

.orderbook-page__hero-actions {
  max-width: 320px;
  margin-left: auto;
}

.orderbook-page__hero-button :deep(.v-btn__content) {
  white-space: nowrap;
}

.orderbook-page__metric-card,
.orderbook-page__table-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.orderbook-page__table-card {
  height: 100%;
}

.orderbook-page__table-card--buy {
  background: linear-gradient(180deg, rgba(0, 200, 83, 0.03), #ffffff 18%);
}

.orderbook-page__table-card--sell {
  background: linear-gradient(180deg, rgba(244, 67, 54, 0.03), #ffffff 18%);
}

.orderbook-page__card-header {
  min-height: 96px;
}

.orderbook-page__card-header--compact {
  min-height: 76px;
}

.orderbook-page__card-divider {
  opacity: 1;
}

.orderbook-page__card-body {
  padding-top: 20px;
}

.orderbook-page__spread {
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: rgba(3, 201, 215, 0.07);
}

.orderbook-page__spread-layout {
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

.orderbook-page__spread-content {
  flex: 1 1 auto;
  min-width: 0;
}

.orderbook-page__spread-text {
  margin: 0;
  line-height: 1.6;
}

@media (max-width: 1279px) {
  .orderbook-page__hero-actions {
    max-width: 100%;
    margin-left: 0;
  }
}

@media (max-width: 959px) {
  .orderbook-page__spread-layout {
    gap: 12px;
  }
}
</style>
