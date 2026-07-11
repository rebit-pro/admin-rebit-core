<script setup lang="ts">
/**
 * CurrencyIcon — отображает иконку валюты с подписью.
 * Использует mdi-иконки как маппинг, fallback — текст кода.
 */
const props = defineProps<{
  code: string;
  size?: number;
  showLabel?: boolean;
}>();

const CURRENCY_MAP: Record<string, { icon: string; color: string; name: string }> = {
  BTC: { icon: 'mdi-bitcoin', color: '#F7931A', name: 'Bitcoin' },
  ETH: { icon: 'mdi-ethereum', color: '#627EEA', name: 'Ethereum' },
  USDT: { icon: 'mdi-currency-usd', color: '#26A17B', name: 'Tether' },
  USDC: { icon: 'mdi-currency-usd', color: '#2775CA', name: 'USD Coin' },
  BNB: { icon: 'mdi-alpha-b-circle', color: '#F0B90B', name: 'BNB' },
  XRP: { icon: 'mdi-alpha-x-circle', color: '#00AAE4', name: 'Ripple' },
  SOL: { icon: 'mdi-white-balance-sunny', color: '#9945FF', name: 'Solana' },
  DOGE: { icon: 'mdi-dog', color: '#C3A634', name: 'Dogecoin' },
  TRX: { icon: 'mdi-triangle-outline', color: '#FF0013', name: 'TRON' },
  LTC: { icon: 'mdi-litecoin', color: '#345D9D', name: 'Litecoin' },
  ADA: { icon: 'mdi-alpha-a-circle', color: '#0033AD', name: 'Cardano' },
  DOT: { icon: 'mdi-circle-multiple', color: '#E6007A', name: 'Polkadot' },
  MATIC: { icon: 'mdi-hexagon-outline', color: '#8247E5', name: 'Polygon' },
  AVAX: { icon: 'mdi-mountain', color: '#E84142', name: 'Avalanche' },
  RUB: { icon: 'mdi-currency-rub', color: '#1e88e5', name: 'Рубль' },
  USD: { icon: 'mdi-currency-usd', color: '#85bb65', name: 'Доллар США' },
  EUR: { icon: 'mdi-currency-eur', color: '#003399', name: 'Евро' },
  KZT: { icon: 'mdi-currency-sign', color: '#00AFCA', name: 'Тенге' },
  UAH: { icon: 'mdi-currency-sign', color: '#005BAC', name: 'Гривна' }
};

function getCurrency() {
  return CURRENCY_MAP[props.code.toUpperCase()] ?? null;
}

function getFallbackColor(): string {
  // Generate a stable color from code
  let hash = 0;
  for (let i = 0; i < props.code.length; i++) {
    hash = props.code.charCodeAt(i) + ((hash << 5) - hash);
  }
  const h = Math.abs(hash) % 360;
  return `hsl(${h}, 55%, 55%)`;
}
</script>

<template>
  <div class="currency-icon d-inline-flex align-center" :class="{ 'ga-2': showLabel }">
    <v-avatar :size="size ?? 40" :color="getCurrency()?.color ?? getFallbackColor()" variant="tonal">
      <v-icon v-if="getCurrency()" :color="getCurrency()!.color">{{ getCurrency()!.icon }}</v-icon>
      <span v-else class="text-body-2 font-weight-bold" :style="{ color: getFallbackColor() }">{{ code.slice(0, 3) }}</span>
    </v-avatar>
    <div v-if="showLabel" class="d-flex flex-column">
      <span class="text-subtitle-1 font-weight-bold">{{ code }}</span>
      <span v-if="getCurrency()?.name" class="text-caption text-medium-emphasis">{{ getCurrency()!.name }}</span>
    </div>
  </div>
</template>
