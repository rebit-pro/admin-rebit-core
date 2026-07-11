import api from './http';

export interface Balance {
  id: number;
  userId: number;
  currencyId: number;
  currency: string;
  available: number | string;
  locked: number | string;
  total: number | string;
  rubRate?: number | null;
  rubEquivalent?: number | null;
  syncedAt: string | null;
}

export interface BalanceListResponse {
  balances: Balance[];
  totalRubEquivalent: number | null;
}

export interface Transaction {
  id: string;
  type: 'deposit' | 'withdrawal' | 'trade_buy' | 'trade_sell' | 'lock' | 'unlock' | 'fee';
  amount: string;
  currency: string;
  tradeId: string | null;
  createdAt: string;
}

export interface TransactionListResponse {
  transactions: Transaction[];
  total: number;
}

export interface TransactionFilters {
  type?: string;
  currencyId?: number;
  dateFrom?: string;
  dateTo?: string;
  limit?: number;
  offset?: number;
}

export interface CashFlowFilters {
  dateFrom?: string;
  dateTo?: string;
  currencyId?: number;
}

export interface CashFlowItem {
  currencyId: number;
  currency: string;
  openingBalance: number;
  incoming: number;
  outgoing: number;
  closingBalance: number;
}

export interface CashFlowTotals {
  totalIncoming: number;
  totalOutgoing: number;
  totalOpeningBalance: number;
  totalClosingBalance: number;
}

export interface CashFlowReport {
  items: CashFlowItem[];
  totals: CashFlowTotals | null;
}

export const walletApi = {
  getBalances(): Promise<BalanceListResponse> {
    return api.get('/api/v1/wallet/balances').then((r) => ({
      balances: r.data?.balances ?? r.data ?? [],
      totalRubEquivalent: r.data?.totalRubEquivalent ?? null
    }));
  },

  syncBalances(): Promise<BalanceListResponse> {
    return api.post('/api/v1/wallet/balances/sync').then((r) => ({
      balances: r.data?.balances ?? r.data ?? [],
      totalRubEquivalent: r.data?.totalRubEquivalent ?? null
    }));
  },

  getTransactions(params?: TransactionFilters): Promise<TransactionListResponse> {
    return api.get('/api/v1/wallet/transactions', { params }).then((r) => ({
      transactions: r.data?.transactions ?? r.data ?? [],
      total: r.data?.total ?? 0
    }));
  },

  exportTransactions(params?: Omit<TransactionFilters, 'limit' | 'offset'>): Promise<TransactionListResponse> {
    return api.get('/api/v1/wallet/transactions/export', { params }).then((r) => ({
      transactions: r.data?.transactions ?? r.data ?? [],
      total: r.data?.total ?? 0
    }));
  },

  getCashFlowReport(params?: CashFlowFilters): Promise<CashFlowReport> {
    return api.get('/api/v1/wallet/reports/cash-flow', { params }).then((r) => r.data);
  }
};
