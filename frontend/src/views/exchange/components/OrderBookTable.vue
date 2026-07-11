<script setup lang="ts">
import { computed, ref } from 'vue';
import type { OrderBookEntry } from '@/api/exchange';

type SortKey = 'price' | 'amount' | 'minLimit';
type SortDir = 'asc' | 'desc';

const props = defineProps<{
  orders: OrderBookEntry[];
  side: 'buy' | 'sell';
  filterMethods?: string[];
  limitMin?: string;
  limitMax?: string;
}>();

const sortKey = ref<SortKey>('price');
const sortDir = ref<SortDir>('buy' === props.side ? 'desc' : 'asc');
const expandedPaymentMethods = ref<Record<string, boolean>>({});

function toggleSort(key: SortKey): void {
  if (sortKey.value === key) {
    sortDir.value = 'asc' === sortDir.value ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = 'asc';
  }
}

function sortIcon(key: SortKey): string {
  if (sortKey.value !== key) return 'mdi-unfold-more-horizontal';
  return 'asc' === sortDir.value ? 'mdi-arrow-up' : 'mdi-arrow-down';
}

function fmt(value: string | number): string {
  const num = parseFloat(String(value));
  return isNaN(num) ? String(value) : num.toFixed(2);
}

function visiblePaymentMethods(paymentMethods: string[]): string[] {
  return paymentMethods.slice(0, 2);
}

function paymentMethodsKey(orderId: string | number): string {
  return String(orderId);
}

function isPaymentMethodsExpanded(orderId: string): boolean {
  return expandedPaymentMethods.value[orderId] ?? false;
}

function togglePaymentMethods(orderId: string): void {
  expandedPaymentMethods.value = {
    ...expandedPaymentMethods.value,
    [orderId]: !isPaymentMethodsExpanded(orderId)
  };
}

function displayedPaymentMethods(order: OrderBookEntry): string[] {
  if (isPaymentMethodsExpanded(paymentMethodsKey(order.id))) {
    return order.paymentMethods;
  }

  return visiblePaymentMethods(order.paymentMethods);
}

const filteredOrders = computed(() => {
  let list = props.orders;

  if (props.filterMethods && 0 < props.filterMethods.length) {
    list = list.filter((order) => props.filterMethods!.some((m) => order.paymentMethods.includes(m)));
  }

  const minVal = props.limitMin && '' !== props.limitMin ? parseFloat(props.limitMin) : null;
  const maxVal = props.limitMax && '' !== props.limitMax ? parseFloat(props.limitMax) : null;

  if (null !== minVal && !isNaN(minVal)) {
    list = list.filter((order) => parseFloat(order.maxLimit) >= minVal);
  }

  if (null !== maxVal && !isNaN(maxVal)) {
    list = list.filter((order) => parseFloat(order.minLimit) <= maxVal);
  }

  return list;
});

const sortedOrders = computed(() => {
  const list = [...filteredOrders.value];
  const dir = 'asc' === sortDir.value ? 1 : -1;
  return list.sort((a, b) => {
    const aVal = parseFloat(a[sortKey.value]);
    const bVal = parseFloat(b[sortKey.value]);
    return (aVal - bVal) * dir;
  });
});
</script>

<template>
  <div class="order-book-table">
    <v-table density="comfortable" hover class="order-book-table__table">
      <colgroup>
        <col class="order-book-table__col order-book-table__col--trader" />
        <col class="order-book-table__col order-book-table__col--price" />
        <col class="order-book-table__col order-book-table__col--amount" />
        <col class="order-book-table__col order-book-table__col--limits" />
        <col class="order-book-table__col order-book-table__col--payment" />
      </colgroup>
      <thead class="order-book-table__head">
        <tr>
          <th>Трейдер</th>
          <th class="text-right sortable-col" @click="toggleSort('price')">
            Цена, ₽
            <v-icon size="14" class="ml-1">{{ sortIcon('price') }}</v-icon>
          </th>
          <th class="text-right sortable-col" @click="toggleSort('amount')">
            Доступно
            <v-icon size="14" class="ml-1">{{ sortIcon('amount') }}</v-icon>
          </th>
          <th class="text-right sortable-col" @click="toggleSort('minLimit')">
            Лимиты, ₽
            <v-icon size="14" class="ml-1">{{ sortIcon('minLimit') }}</v-icon>
          </th>
          <th>Оплата</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="0 === sortedOrders.length">
          <td colspan="5" class="pa-0">
            <div class="order-book-table__empty-state">
              <div class="text-subtitle-2 font-weight-bold mb-1">Нет предложений</div>
              <div class="text-body-2 text-medium-emphasis">Попробуйте изменить фильтры, лимиты или выбрать другую валютную пару.</div>
            </div>
          </td>
        </tr>
        <tr
          v-for="order in sortedOrders"
          :key="order.id"
          class="order-book-table__row"
          :class="{
            'order-book-table__row--expanded': isPaymentMethodsExpanded(paymentMethodsKey(order.id))
          }"
          @click="togglePaymentMethods(paymentMethodsKey(order.id))"
        >
          <td>
            <div class="d-flex align-center ga-3 py-1">
              <v-avatar size="32" color="secondary" variant="tonal">
                <span class="text-caption">{{ order.username.charAt(0).toUpperCase() }}</span>
              </v-avatar>
              <div>
                <div class="text-body-2 font-weight-medium">{{ order.username }}</div>
                <div class="text-caption text-medium-emphasis">{{ order.completedTrades }} сделок · {{ order.completionRate }}%</div>
              </div>
            </div>
          </td>
          <td class="text-right">
            <span
              class="font-weight-bold order-book-table__price"
              :class="{
                'order-book-table__price--buy': 'buy' === side,
                'order-book-table__price--sell': 'sell' === side
              }"
            >
              {{ fmt(order.price) }}
            </span>
          </td>
          <td class="text-right">
            <div class="text-body-2 font-weight-medium">{{ fmt(order.amount) }}</div>
          </td>
          <td class="text-right">
            <div class="text-body-2 font-weight-medium">{{ fmt(order.minLimit) }} – {{ fmt(order.maxLimit) }}</div>
          </td>
          <td class="order-book-table__payment-cell">
            <div class="order-book-table__payment-list d-flex flex-wrap justify-center ga-1">
              <v-chip
                v-for="method in displayedPaymentMethods(order)"
                :key="method"
                size="x-small"
                variant="tonal"
                color="primary"
                class="order-book-table__payment-chip"
              >
                {{ method }}
              </v-chip>
            </div>
          </td>
        </tr>
      </tbody>
    </v-table>
  </div>
</template>

<style scoped lang="scss">
.order-book-table {
  background: transparent;
}

.order-book-table__table {
  background: transparent;
}

.order-book-table__col--trader {
  width: 33%;
}

.order-book-table__col--price,
.order-book-table__col--amount {
  width: 14%;
}

.order-book-table__col--limits {
  width: 27%;
}

.order-book-table__col--payment {
  width: 12%;
}

.order-book-table__head th {
  height: 52px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgba(15, 23, 42, 0.6);
  background: rgba(15, 23, 42, 0.03);
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.order-book-table__row td {
  padding-top: 14px;
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  transition:
    background-color 0.18s ease,
    border-color 0.18s ease;
}

.order-book-table__row {
  cursor: pointer;
}

.order-book-table__row:hover td {
  background: rgba(30, 136, 229, 0.04);
}

.order-book-table__row--expanded td {
  background: rgba(30, 136, 229, 0.06);
}

.order-book-table__price {
  font-size: 1rem;
}

.order-book-table__price--buy {
  color: #00c853;
}

.order-book-table__price--sell {
  color: #f44336;
}

.order-book-table__empty-state {
  padding: 32px 16px;
  text-align: center;
}

.order-book-table__payment-cell {
  white-space: normal;
  text-align: center;
  padding-top: 16px;
  padding-bottom: 16px;
  vertical-align: middle;
}

.order-book-table__payment-list {
  min-height: 24px;
  align-content: center;
  padding-top: 4px;
  padding-bottom: 4px;
}

.order-book-table__payment-chip {
  max-width: 100%;
}

.order-book-table__payment-chip :deep(.v-chip__content) {
  overflow: hidden;
  text-overflow: ellipsis;
}

.sortable-col {
  cursor: pointer;
  user-select: none;
}

.sortable-col:hover {
  opacity: 0.8;
}
</style>
