import api from './http';

// region Interfaces — Order Book

export interface OrderBookEntryDto {
  id: number | string;
  bybitOrderId?: string | null;
  side: 'buy' | 'sell';
  price: number | string;
  amount: number | string;
  minLimit: number | string;
  maxLimit: number | string;
  username: string;
  completedTrades: number;
  completionRate: number;
  paymentMethods: string[];
  paymentTimeLimit?: number | null;
}

export interface OrderBookEntry {
  id: number | string;
  bybitOrderId: string | null;
  side: 'buy' | 'sell';
  price: string;
  amount: string;
  minLimit: string;
  maxLimit: string;
  username: string;
  completedTrades: number;
  completionRate: number;
  paymentMethods: string[];
  paymentTimeLimit: number | null;
}

export interface OrderBookResponseDto {
  buy: OrderBookEntryDto[];
  sell: OrderBookEntryDto[];
}

export interface OrderBookResponse {
  buy: OrderBookEntry[];
  sell: OrderBookEntry[];
}

// endregion

// region Interfaces — Dictionaries

export interface Currency {
  id: number;
  code: string;
  name: string;
  type: 'crypto' | 'fiat';
  decimals: number;
  sort: number;
}

export interface CurrencyPair {
  id: number;
  token: string;
  fiat: string;
  label: string;
}

export interface PaymentMethod {
  id: string;
  code: string;
  name: string;
  sort: number;
}

interface CurrencyPairDto {
  id: number;
  code: string;
  tokenCurrencyId: number;
  fiatCurrencyId: number;
  tokenCode: string;
  fiatCode: string;
  isDefault: boolean;
  sort: number;
}

interface PaymentMethodDto {
  id: number;
  code: string;
  name: string;
  sort: number;
}

function normalizeDecimalString(value: number | string): string {
  return 'number' === typeof value ? String(value) : value;
}

function normalizeOrderBookEntry(item: OrderBookEntryDto): OrderBookEntry {
  return {
    id: item.id,
    bybitOrderId: item.bybitOrderId ?? null,
    side: item.side,
    price: normalizeDecimalString(item.price),
    amount: normalizeDecimalString(item.amount),
    minLimit: normalizeDecimalString(item.minLimit),
    maxLimit: normalizeDecimalString(item.maxLimit),
    username: item.username,
    completedTrades: item.completedTrades,
    completionRate: item.completionRate,
    paymentMethods: item.paymentMethods,
    paymentTimeLimit: item.paymentTimeLimit ?? null
  };
}

interface ChatMessageDto {
  id: number;
  tradeId: number;
  senderType?: 'user' | 'system' | 'script';
  messageType?: 'user' | 'system' | 'script';
  message: string;
  contentType: ChatContentType;
  fileName: string | null;
  fileUrl?: string | null;
  createdAt: string;
}

interface ShareUploadResponseDto {
  id: number;
  name: string;
  size: number;
  type: string;
  src: string;
}

function normalizeChatMessage(item: ChatMessageDto): ChatMessage {
  const senderType = item.senderType ?? item.messageType ?? 'system';
  const fileUrl = item.fileUrl ?? ('str' !== item.contentType ? item.message : null);
  const message = 'str' !== item.contentType && fileUrl === item.message ? '' : item.message;

  return {
    id: item.id,
    tradeId: item.tradeId,
    senderType,
    message,
    contentType: item.contentType,
    fileName: item.fileName,
    fileUrl,
    createdAt: item.createdAt
  };
}

// endregion

// region Interfaces — Advertisements

export type AdvertisementStatus = 'active' | 'paused' | 'completed' | 'cancelled';
export type AdvertisementSide = 'buy' | 'sell';
export type PriceType = 'fixed' | 'floating';

export interface Advertisement {
  id: number;
  bybitAdId?: string | null;
  currencyPairId: number;
  side: AdvertisementSide;
  priceType: PriceType;
  price: string;
  premium: string | null;
  quantity: string;
  minAmount: string;
  maxAmount: string;
  paymentMethodIds: string[];
  paymentPeriod: number;
  conditions: string;
  chatScriptId: number | null;
  status: AdvertisementStatus;
  createdAt: string;
  updatedAt: string;
}

export interface CreateAdvertisementPayload {
  currencyPairId: number;
  side: AdvertisementSide;
  priceType: PriceType;
  price: string;
  premium: string | null;
  quantity: string;
  minAmount: string;
  maxAmount: string;
  paymentMethodIds: string[];
  paymentPeriod: number;
  conditions: string;
  chatScriptId: number | null;
  tradingPreferenceSet: Record<string, unknown>;
}

// endregion

// region Interfaces — Trades

export type TradeStatus = 'pending_payment' | 'payment_sent' | 'payment_confirmed' | 'completed' | 'cancelled' | 'disputed';

export interface CounterpartyInfo {
  nickName: string;
  realName: string;
  realNameEn: string;
  isOnline: boolean;
  kycLevel: number;
  kycCountryCode: string;
  email: string;
  mobile: string;
  totalFinishCount: number;
  totalFinishBuyCount: number;
  totalFinishSellCount: number;
  recentFinishCount: number;
  recentRate: number;
  averageReleaseTime: string;
  averageTransferTime: string;
  accountCreateDays: number;
  firstTradeDays: number;
  recentTradeAmount: string;
  totalTradeAmount: string;
  goodAppraiseRate: string;
  goodAppraiseCount: number;
  badAppraiseCount: number;
  authStatus: number;
  blocked: string;
  vipLevel: number;
}

export interface Trade {
  id: number;
  bybitOrderId: string;
  bybitStatus: number;
  side: 'buy' | 'sell';
  price: number;
  quantity: number;
  fiatAmount: number;
  fee: number;
  status: TradeStatus;
  counterpartyName: string;
  currencyPairId: number;
  advertisementId: number | null;
  paymentDeadline: string | null;
  paidAt: string | null;
  completedAt: string | null;
  cancelledAt: string | null;
  cancelReason: string | null;
  createdAt: string;
  updatedAt: string;
  isNew?: boolean;
}

export interface ConfirmPaymentPayload {
  paymentType: string;
  paymentId: string;
}

// endregion

// region Interfaces — Chat

export type ChatContentType = 'str' | 'pic' | 'pdf' | 'video';

export interface ChatMessage {
  id: number;
  tradeId: number;
  senderType: 'user' | 'system' | 'script';
  message: string;
  contentType: ChatContentType;
  fileName: string | null;
  fileUrl?: string | null;
  createdAt: string;
}

export interface TradeChatUploadedFile {
  fileName: string;
  fileUrl: string;
  contentType: ChatContentType;
  providerType?: string | null;
}

export interface SendMessagePayload {
  tradeId: number;
  message: string;
  contentType: ChatContentType;
  fileName: string | null;
  fileUrl?: string | null;
}

// endregion

// region Interfaces — Chat Scripts

export interface ChatScriptStep {
  id?: number;
  sort: number;
  message: string;
  delaySeconds: number;
  contentType?: ChatContentType;
  fileName?: string | null;
  fileUrl?: string | null;
}

export interface ChatScript {
  id: number;
  name: string;
  isActive: boolean;
  steps: ChatScriptStep[];
  advertisementsCount?: number;
  createdAt: string;
  updatedAt: string;
}

export interface ChatScriptPayload {
  name: string;
  isActive: boolean;
  steps: ChatScriptStep[];
}

// endregion

// region API

export const exchangeApi = {
  // — Dictionaries —

  getCurrencies(): Promise<Currency[]> {
    return api.get('/api/v1/exchange/currencies').then((r) => r.data?.items ?? []);
  },

  getCurrencyPairs(): Promise<CurrencyPair[]> {
    return api.get('/api/v1/exchange/currency-pairs').then((r) => {
      const items: CurrencyPairDto[] = r.data?.items ?? [];
      return items.map((item) => ({
        id: item.id,
        token: item.tokenCode,
        fiat: item.fiatCode,
        label: `${item.tokenCode} / ${item.fiatCode}`
      }));
    });
  },

  getPaymentMethods(): Promise<PaymentMethod[]> {
    return api.get('/api/v1/exchange/payment-methods').then((r) => {
      const items: PaymentMethodDto[] = r.data?.items ?? [];
      return items.map((item) => ({
        id: String(item.id),
        code: item.code,
        name: item.name,
        sort: item.sort
      }));
    });
  },

  getOrderBook(token: string, fiat: string): Promise<OrderBookResponse> {
    return api.get('/api/v1/exchange/orderbook', { params: { token, fiat } }).then((r) => {
      const data = r.data as OrderBookResponseDto;

      return {
        buy: (data.buy ?? []).map(normalizeOrderBookEntry),
        sell: (data.sell ?? []).map(normalizeOrderBookEntry)
      };
    });
  },

  // — Advertisements —

  getAdvertisements(status?: AdvertisementStatus): Promise<Advertisement[]> {
    return api.get('/api/v1/exchange/advertisements', { params: status ? { status } : {} }).then((r) => r.data?.items ?? r.data ?? []);
  },

  createAdvertisement(payload: CreateAdvertisementPayload): Promise<Advertisement> {
    return api.post('/api/v1/exchange/advertisements', payload).then((r) => r.data);
  },

  deleteAdvertisement(id: number): Promise<void> {
    return api.delete(`/api/v1/exchange/advertisements/${id}`);
  },

  toggleAdvertisement(id: number, status: 'active' | 'paused'): Promise<Advertisement> {
    return api.patch(`/api/v1/exchange/advertisements/${id}`, { status }).then((r) => r.data);
  },

  // — Trades —

  getTrades(status?: TradeStatus): Promise<Trade[]> {
    return api.get('/api/v1/exchange/trades', { params: status ? { status } : {} }).then((r) => r.data?.items ?? r.data ?? []);
  },

  getTradeDetail(id: number): Promise<Trade> {
    return api.get(`/api/v1/exchange/trades/${id}`).then((r) => r.data);
  },

  getCounterpartyInfo(tradeId: number): Promise<CounterpartyInfo> {
    return api.get(`/api/v1/exchange/trades/${tradeId}/counterparty`).then((r) => r.data);
  },

  confirmPayment(id: number, payload: ConfirmPaymentPayload): Promise<Trade> {
    return api.post(`/api/v1/exchange/trades/${id}/pay`, payload).then((r) => r.data);
  },

  releaseAssets(id: number): Promise<Trade> {
    return api.post(`/api/v1/exchange/trades/${id}/release`).then((r) => r.data);
  },

  // — Trade Chat —

  getChatHistory(tradeId: number): Promise<ChatMessage[]> {
    return api.get(`/api/v1/exchange/trades/${tradeId}/chat`).then((r) => {
      const items: ChatMessageDto[] = r.data?.messages ?? r.data ?? [];
      return items.map(normalizeChatMessage);
    });
  },

  sendMessage(tradeId: number, payload: SendMessagePayload): Promise<ChatMessage> {
    return api.post(`/api/v1/exchange/trades/${tradeId}/chat`, payload).then((r) => normalizeChatMessage(r.data));
  },

  async uploadTradeChatFile(tradeId: number, file: File): Promise<TradeChatUploadedFile> {
    const formData = new FormData();
    formData.append('moduleId', 'rebit.exchange');
    formData.append('file', file);

    const uploadedFile = await api
      .post<ShareUploadResponseDto>('/api/v1/share/file/upload/', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      .then((r) => r.data);

    return api
      .post(`/api/v1/exchange/trades/${tradeId}/chat/upload`, {
        tradeId,
        fileId: uploadedFile.id
      })
      .then((r) => r.data as TradeChatUploadedFile);
  },

  // — Chat Scripts —

  getChatScripts(): Promise<ChatScript[]> {
    return api.get('/api/v1/exchange/chat-scripts').then((r) => r.data?.items ?? r.data ?? []);
  },

  createChatScript(payload: ChatScriptPayload): Promise<ChatScript> {
    return api.post('/api/v1/exchange/chat-scripts', payload).then((r) => r.data);
  },

  updateChatScript(id: number, payload: ChatScriptPayload): Promise<ChatScript> {
    return api.patch(`/api/v1/exchange/chat-scripts/${id}`, payload).then((r) => r.data);
  },

  deleteChatScript(id: number): Promise<void> {
    return api.delete(`/api/v1/exchange/chat-scripts/${id}`);
  }
};

// endregion
