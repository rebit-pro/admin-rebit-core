import { defineStore } from 'pinia';
import { ref } from 'vue';
import { walletApi, type Balance, type Transaction, type TransactionFilters } from '@/api/wallet';

export const useWalletStore = defineStore('wallet', () => {
  const balances = ref<Balance[]>([]);
  const totalRubEquivalent = ref<number | null>(null);
  const transactions = ref<Transaction[]>([]);
  const transactionsTotal = ref(0);
  const loading = ref(false);
  const syncing = ref(false);
  const error = ref<string | null>(null);

  async function fetchBalances(): Promise<void> {
    loading.value = true;
    error.value = null;
    totalRubEquivalent.value = null;

    try {
      const result = await walletApi.getBalances();
      balances.value = result.balances;
      totalRubEquivalent.value = result.totalRubEquivalent;
    } catch (e: unknown) {
      totalRubEquivalent.value = null;
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки балансов';
    } finally {
      loading.value = false;
    }
  }

  async function syncBalances(): Promise<void> {
    syncing.value = true;
    error.value = null;
    totalRubEquivalent.value = null;

    try {
      const result = await walletApi.syncBalances();
      balances.value = result.balances;
      totalRubEquivalent.value = result.totalRubEquivalent;
    } catch (e: unknown) {
      totalRubEquivalent.value = null;
      error.value = e instanceof Error ? e.message : 'Ошибка синхронизации балансов';
    } finally {
      syncing.value = false;
    }
  }

  async function fetchTransactions(params?: TransactionFilters): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      const result = await walletApi.getTransactions(params);
      transactions.value = result.transactions;
      transactionsTotal.value = result.total;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки транзакций';
    } finally {
      loading.value = false;
    }
  }

  async function exportTransactions(params?: Omit<TransactionFilters, 'limit' | 'offset'>): Promise<void> {
    error.value = null;

    try {
      const result = await walletApi.exportTransactions(params);
      const rows = result.transactions;

      if (0 === rows.length) {
        error.value = 'Нет транзакций для экспорта';
        return;
      }

      const header = 'ID;Тип;Сумма;Валюта;Дата;ID сделки';
      const csvRows = rows.map((tx) => [tx.id, tx.type, tx.amount, tx.currency || '', tx.createdAt, tx.tradeId ?? ''].join(';'));
      const csv = [header, ...csvRows].join('\n');
      const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });

      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `transactions_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout((): void => {
        URL.revokeObjectURL(url);
      }, 0);

      error.value = null;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка экспорта транзакций';
    }
  }

  return {
    balances,
    totalRubEquivalent,
    transactions,
    transactionsTotal,
    loading,
    syncing,
    error,
    fetchBalances,
    syncBalances,
    fetchTransactions,
    exportTransactions
  };
});
