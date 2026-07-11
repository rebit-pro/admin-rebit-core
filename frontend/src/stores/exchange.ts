import { defineStore } from 'pinia';
import { ref } from 'vue';
import { exchangeApi, type OrderBookEntry, type CurrencyPair, type PaymentMethod, type Currency } from '@/api/exchange';

export const useExchangeStore = defineStore('exchange', () => {
  const buyOrders = ref<OrderBookEntry[]>([]);
  const sellOrders = ref<OrderBookEntry[]>([]);
  const currencies = ref<Currency[]>([]);
  const currencyPairs = ref<CurrencyPair[]>([]);
  const paymentMethods = ref<PaymentMethod[]>([]);
  const selectedPair = ref<CurrencyPair>({ id: 0, token: 'USDT', fiat: 'RUB', label: 'USDT / RUB' });
  const loading = ref(false);
  const error = ref<string | null>(null);
  const hasOrderBookAccess = ref(false);

  let refreshTimer: ReturnType<typeof setInterval> | null = null;
  let orderBookRequestId = 0;

  function clearOrderBook(): void {
    buyOrders.value = [];
    sellOrders.value = [];
    loading.value = false;
    error.value = null;
  }

  function setOrderBookAccess(value: boolean): void {
    hasOrderBookAccess.value = value;

    if (!value) {
      stopAutoRefresh();
      clearOrderBook();
    }
  }

  async function fetchOrderBook(): Promise<void> {
    if (!hasOrderBookAccess.value) {
      clearOrderBook();

      return;
    }

    const currentRequestId = ++orderBookRequestId;

    loading.value = true;
    error.value = null;
    try {
      const data = await exchangeApi.getOrderBook(selectedPair.value.token, selectedPair.value.fiat);

      if (!hasOrderBookAccess.value || currentRequestId !== orderBookRequestId) {
        return;
      }

      buyOrders.value = data.buy;
      sellOrders.value = data.sell;
    } catch (e: unknown) {
      if (!hasOrderBookAccess.value || currentRequestId !== orderBookRequestId) {
        return;
      }

      error.value = e instanceof Error ? e.message : 'Ошибка загрузки стакана';
    } finally {
      if (currentRequestId === orderBookRequestId) {
        loading.value = false;
      }
    }
  }

  async function fetchCurrencies(): Promise<void> {
    try {
      currencies.value = await exchangeApi.getCurrencies();
    } catch {
      currencies.value = [];
    }
  }

  async function fetchCurrencyPairs(): Promise<void> {
    try {
      currencyPairs.value = await exchangeApi.getCurrencyPairs();
    } catch {
      currencyPairs.value = [];
    }
  }

  async function fetchPaymentMethods(): Promise<void> {
    try {
      paymentMethods.value = await exchangeApi.getPaymentMethods();
    } catch {
      paymentMethods.value = [];
    }
  }

  function selectPair(pair: CurrencyPair): void {
    selectedPair.value = pair;

    if (hasOrderBookAccess.value) {
      void fetchOrderBook();
    }
  }

  function startAutoRefresh(intervalMs = 10000): void {
    stopAutoRefresh();

    if (!hasOrderBookAccess.value) {
      return;
    }

    refreshTimer = setInterval(() => {
      void fetchOrderBook();
    }, intervalMs);
  }

  function stopAutoRefresh(): void {
    if (null !== refreshTimer) {
      clearInterval(refreshTimer);
      refreshTimer = null;
    }
  }

  return {
    buyOrders,
    sellOrders,
    currencies,
    currencyPairs,
    paymentMethods,
    selectedPair,
    loading,
    error,
    hasOrderBookAccess,
    clearOrderBook,
    setOrderBookAccess,
    fetchOrderBook,
    fetchCurrencies,
    fetchCurrencyPairs,
    fetchPaymentMethods,
    selectPair,
    startAutoRefresh,
    stopAutoRefresh
  };
});
