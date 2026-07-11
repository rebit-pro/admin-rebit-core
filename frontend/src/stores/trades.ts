import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  exchangeApi,
  type Trade,
  type TradeStatus,
  type ConfirmPaymentPayload,
  type ChatMessage,
  type SendMessagePayload,
  type CounterpartyInfo
} from '@/api/exchange';

export const useTradesStore = defineStore('trades', () => {
  const trades = ref<Trade[]>([]);
  const currentTrade = ref<Trade | null>(null);
  const counterpartyInfo = ref<CounterpartyInfo | null>(null);
  const chatMessages = ref<ChatMessage[]>([]);
  const loading = ref(false);
  const chatLoading = ref(false);
  const actionLoading = ref(false);
  const error = ref<string | null>(null);
  let tradeDetailRequestGeneration = 0;
  let counterpartyInfoRequestGeneration = 0;

  function updateTradeInCollections(trade: Trade): void {
    const index = trades.value.findIndex((item) => item.id === trade.id);
    const currentTradeValue = currentTrade.value;

    if (-1 !== index) {
      trades.value[index] = {
        ...trades.value[index],
        ...trade
      };
    }

    if (null !== currentTradeValue && currentTradeValue['id'] === trade.id) {
      currentTrade.value = {
        ...currentTradeValue,
        ...trade
      };
    }
  }

  async function fetchTrades(status?: TradeStatus): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      trades.value = await exchangeApi.getTrades(status);
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки сделок';
    } finally {
      loading.value = false;
    }
  }

  async function fetchTradeDetail(id: number): Promise<void> {
    tradeDetailRequestGeneration += 1;
    const requestGeneration = tradeDetailRequestGeneration;

    loading.value = true;
    error.value = null;

    try {
      const trade = await exchangeApi.getTradeDetail(id);

      if (requestGeneration !== tradeDetailRequestGeneration) {
        return;
      }

      currentTrade.value = {
        ...trade,
        isNew: false
      };
      updateTradeInCollections({
        ...trade,
        isNew: false
      });
    } catch (e: unknown) {
      if (requestGeneration !== tradeDetailRequestGeneration) {
        return;
      }

      error.value = e instanceof Error ? e.message : 'Ошибка загрузки сделки';
    } finally {
      if (requestGeneration === tradeDetailRequestGeneration) {
        loading.value = false;
      }
    }
  }

  async function confirmPayment(id: number, payload: ConfirmPaymentPayload): Promise<void> {
    actionLoading.value = true;
    error.value = null;
    try {
      const trade = await exchangeApi.confirmPayment(id, payload);
      updateTradeInCollections(trade);
      currentTrade.value = trade;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка подтверждения оплаты';
      throw e;
    } finally {
      actionLoading.value = false;
    }
  }

  async function releaseAssets(id: number): Promise<void> {
    actionLoading.value = true;
    error.value = null;
    try {
      const trade = await exchangeApi.releaseAssets(id);
      updateTradeInCollections(trade);
      currentTrade.value = trade;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка подтверждения получения';
      throw e;
    } finally {
      actionLoading.value = false;
    }
  }

  async function fetchChatHistory(tradeId: number): Promise<void> {
    chatLoading.value = true;
    try {
      chatMessages.value = await exchangeApi.getChatHistory(tradeId);
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки чата';
    } finally {
      chatLoading.value = false;
    }
  }

  async function sendMessage(tradeId: number, payload: SendMessagePayload): Promise<void> {
    try {
      const message = await exchangeApi.sendMessage(tradeId, payload);
      chatMessages.value.push(message);
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка отправки сообщения';
      throw e;
    }
  }

  async function fetchCounterpartyInfo(tradeId: number): Promise<void> {
    const currentGeneration = ++counterpartyInfoRequestGeneration;

    try {
      const info = await exchangeApi.getCounterpartyInfo(tradeId);

      if (currentGeneration !== counterpartyInfoRequestGeneration) {
        return;
      }

      counterpartyInfo.value = info;
    } catch {
      if (currentGeneration !== counterpartyInfoRequestGeneration) {
        return;
      }

      counterpartyInfo.value = null;
    }
  }

  function clearCurrentTrade(): void {
    tradeDetailRequestGeneration += 1;
    counterpartyInfoRequestGeneration += 1;
    currentTrade.value = null;
    counterpartyInfo.value = null;
    chatMessages.value = [];
  }

  return {
    trades,
    currentTrade,
    counterpartyInfo,
    chatMessages,
    loading,
    chatLoading,
    actionLoading,
    error,
    fetchTrades,
    fetchTradeDetail,
    fetchCounterpartyInfo,
    confirmPayment,
    releaseAssets,
    fetchChatHistory,
    sendMessage,
    clearCurrentTrade
  };
});
