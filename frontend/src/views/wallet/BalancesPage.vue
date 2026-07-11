<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { PlugConnectedIcon, RefreshIcon, WalletIcon } from 'vue-tabler-icons';
import { useWalletStore } from '@/stores/wallet';
import { useCurrencyFormat } from '@/composables/useCurrencyFormat';
import { useTransactionLabels } from '@/composables/useTransactionLabels';
import AppEmptyState from '@/components/shared/AppEmptyState.vue';
import UiParentCard from '@/components/shared/UiParentCard.vue';
import CurrencyIcon from '@/components/shared/CurrencyIcon.vue';

const wallet = useWalletStore();
const { formatAmount, formatRub, formatDate } = useCurrencyFormat();
const { txLabel, txColor, txIcon } = useTransactionLabels();

const lastSyncedAt = computed(() => {
  const maxDate = wallet.balances.reduce((latest: string | null, b) => {
    if (null === b.syncedAt) return latest;
    if (null === latest) return b.syncedAt;
    return new Date(b.syncedAt) > new Date(latest) ? b.syncedAt : latest;
  }, null);
  if (null === maxDate) return null;
  return new Date(maxDate).toLocaleString('ru-RU');
});

const latestTransactions = computed(() => wallet.transactions.slice(0, 5));

onMounted(async () => {
  await Promise.allSettled([wallet.fetchBalances(), wallet.fetchTransactions({ limit: 5, offset: 0 })]);
});

async function handleSync(): Promise<void> {
  await wallet.syncBalances();
}
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3">
      <h2 class="text-h4 font-weight-bold">Балансы</h2>
      <div class="d-flex align-center ga-3">
        <span v-if="lastSyncedAt" class="text-caption text-medium-emphasis"> Синхронизировано: {{ lastSyncedAt }} </span>
        <v-btn color="secondary" variant="outlined" size="small" rounded="lg" :loading="wallet.syncing" @click="handleSync">
          <template #prepend>
            <RefreshIcon :size="18" stroke-width="1.75" />
          </template>
          Синхронизировать
        </v-btn>
      </div>
    </div>

    <!-- Общий баланс в рублях -->
    <v-card v-if="null !== wallet.totalRubEquivalent" class="balances-total-card mb-6" rounded="lg">
      <v-card-text class="pa-5 d-flex align-center ga-4">
        <v-avatar size="52" color="primary" variant="tonal">
          <v-icon size="28">mdi-wallet-outline</v-icon>
        </v-avatar>
        <div>
          <div class="text-body-2 text-medium-emphasis">Общий баланс (приблизительно)</div>
          <div class="text-h4 font-weight-bold">{{ formatRub(wallet.totalRubEquivalent) }}</div>
        </div>
      </v-card-text>
    </v-card>

    <v-row v-if="wallet.loading" justify="center" class="mt-8">
      <v-progress-circular indeterminate color="primary" />
    </v-row>

    <v-alert v-if="wallet.error" type="error" variant="tonal" class="mb-4">{{ wallet.error }}</v-alert>

    <v-row v-if="!wallet.loading">
      <v-col v-for="balance in wallet.balances" :key="balance.currency" cols="12" sm="6" md="4">
        <v-card class="balance-card h-100" rounded="lg">
          <v-card-text class="pa-5">
            <div class="d-flex align-center mb-4">
              <CurrencyIcon :code="balance.currency" :size="44" show-label />
            </div>
            <div class="mb-3">
              <p class="text-caption text-medium-emphasis mb-1">Доступно</p>
              <p class="text-h5 font-weight-bold">{{ formatAmount(balance.available, balance.currency) }}</p>
            </div>
            <v-divider class="mb-3" />
            <div class="d-flex justify-space-between text-body-2 text-medium-emphasis mb-2">
              <span>Заблокировано: {{ formatAmount(balance.locked, balance.currency) }}</span>
              <span>Всего: {{ formatAmount(balance.total, balance.currency) }}</span>
            </div>
            <div v-if="null != balance.rubEquivalent" class="text-body-2 text-primary font-weight-medium">
              ≈ {{ formatRub(balance.rubEquivalent ?? 0) }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col v-if="0 === wallet.balances.length" cols="12">
        <AppEmptyState
          :icon="WalletIcon"
          tone="primary"
          title="Балансы пока пусты"
          description="Подключите Bybit API и запустите синхронизацию, чтобы увидеть доступные и заблокированные средства по валютам."
        >
          <template #actions>
            <div class="d-flex justify-center">
              <v-btn color="secondary" variant="outlined" rounded="lg" to="/profile/api-connection">
                <template #prepend>
                  <PlugConnectedIcon :size="18" stroke-width="1.75" />
                </template>
                Подключить Bybit API
              </v-btn>
            </div>
          </template>
        </AppEmptyState>
      </v-col>
    </v-row>

    <!-- Последние 5 транзакций -->
    <UiParentCard
      v-if="0 < latestTransactions.length"
      title="Последние транзакции"
      subtitle="Последние 5 операций по вашим балансам"
      icon="mdi-history"
      color="info"
      class="mt-6"
    >
      <template #append>
        <v-btn variant="text" color="primary" to="/wallet/transactions">Вся история</v-btn>
      </template>

      <v-list lines="two">
        <v-list-item v-for="tx in latestTransactions" :key="tx.id" class="px-5 py-3">
          <template #prepend>
            <v-avatar size="40" :color="txColor(tx.type)" variant="tonal">
              <v-icon>{{ txIcon(tx.type) }}</v-icon>
            </v-avatar>
          </template>

          <v-list-item-title class="d-flex align-center flex-wrap ga-2">
            <span class="font-weight-medium">{{ txLabel(tx.type) }}</span>
            <v-chip size="x-small" variant="tonal" :color="txColor(tx.type)">{{ tx.currency }}</v-chip>
          </v-list-item-title>

          <v-list-item-subtitle>
            {{ formatDate(tx.createdAt) }}
            <span v-if="null !== tx.tradeId"> · Сделка #{{ tx.tradeId }}</span>
          </v-list-item-subtitle>

          <template #append>
            <div class="text-right">
              <div class="font-weight-bold">{{ formatAmount(tx.amount, tx.currency) }}</div>
              <div class="text-caption text-medium-emphasis">{{ tx.currency }}</div>
            </div>
          </template>
        </v-list-item>
      </v-list>
    </UiParentCard>
  </div>
</template>

<style scoped lang="scss">
.balances-total-card {
  border: 1px solid rgba(30, 136, 229, 0.12);
  background:
    radial-gradient(circle at top right, rgba(94, 53, 177, 0.08), transparent 40%),
    linear-gradient(135deg, rgba(30, 136, 229, 0.08), #ffffff);
}

.balance-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 40px rgba(15, 23, 42, 0.1);
  }
}
</style>
