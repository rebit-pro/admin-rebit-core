<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useWalletStore } from '@/stores/wallet';
import { useIdentityStore } from '@/stores/identity';
import { useCurrencyFormat } from '@/composables/useCurrencyFormat';
import { useTransactionLabels } from '@/composables/useTransactionLabels';
import UiParentCard from '@/components/shared/UiParentCard.vue';
import UiTableCard from '@/components/shared/UiTableCard.vue';
import CurrencyIcon from '@/components/shared/CurrencyIcon.vue';

const auth = useAuthStore();
const wallet = useWalletStore();
const identity = useIdentityStore();
const { formatAmount, formatDate } = useCurrencyFormat();
const { txLabel, txColor } = useTransactionLabels();

const activeTab = ref('balances');
const userEmail = computed(() => auth.user?.['email'] ?? '');

const identityCardTitle = computed(() => {
  if (!identity.isConnected) {
    return 'API не подключён';
  }

  return identity.hasActiveConnection ? 'API подключён' : 'API требует внимания';
});

const identityCardSubtitle = computed(() => {
  if (!identity.isConnected) {
    return 'Подключите Bybit API для начала торговли';
  }

  return identity.hasActiveConnection
    ? 'Bybit API активен и готов к работе'
    : `Текущий статус: ${identity.statusLabel ?? 'требует проверки'}`;
});

const identityCardIcon = computed(() => {
  if (!identity.isConnected) {
    return 'mdi-link-variant-off';
  }

  return identity.hasActiveConnection ? 'mdi-shield-check-outline' : 'mdi-alert-outline';
});

const identityCardColor = computed(() => {
  if (!identity.isConnected) {
    return 'warning';
  }

  return identity.hasActiveConnection ? 'success' : identityStatusColor();
});

function identityStatusColor(): string {
  const status = identity.connectionStatus;

  if (null === status) {
    return 'warning';
  }

  switch (status['status']) {
    case 'active':
      return 'success';
    case 'pending_verification':
      return 'info';
    case 'invalid':
      return 'error';
    case 'revoked':
      return 'warning';
    default:
      return 'warning';
  }
}

async function refreshBalances(): Promise<void> {
  await wallet.fetchBalances();
}

async function refreshTransactions(): Promise<void> {
  await wallet.fetchTransactions();
}

onMounted(async () => {
  await Promise.all([wallet.fetchBalances(), wallet.fetchTransactions(), identity.fetchStatus()]);
});
</script>

<template>
  <div>
    <div class="d-flex align-center ga-4 mb-2">
      <v-avatar size="56" color="primary" variant="tonal">
        <v-icon size="28">mdi-account-circle-outline</v-icon>
      </v-avatar>
      <div>
        <h2 class="text-h4 font-weight-bold">Мой профиль</h2>
        <p class="text-medium-emphasis">{{ userEmail }}</p>
      </div>
    </div>

    <v-tabs v-model="activeTab" color="secondary" class="mb-6">
      <v-tab value="balances">Балансы</v-tab>
      <v-tab value="transactions">История</v-tab>
      <v-tab value="api">Bybit API</v-tab>
    </v-tabs>

    <v-tabs-window v-model="activeTab">
      <!-- Балансы -->
      <v-tabs-window-item value="balances">
        <div class="d-flex align-center justify-space-between mb-4">
          <span class="text-body-1 text-medium-emphasis">Ваши балансы на Bybit</span>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            rounded="lg"
            :loading="wallet.loading"
            prepend-icon="mdi-refresh"
            @click="refreshBalances"
          >
            Обновить
          </v-btn>
        </div>

        <v-alert v-if="wallet.error" type="error" variant="tonal" class="mb-4">{{ wallet.error }}</v-alert>

        <v-row>
          <v-col v-for="balance in wallet.balances" :key="balance.currency" cols="12" sm="6" md="4">
            <v-card class="profile-balance-card h-100" rounded="lg">
              <v-card-text class="pa-5">
                <div class="d-flex align-center mb-4">
                  <CurrencyIcon :code="balance.currency" :size="44" show-label />
                </div>
                <p class="text-caption text-medium-emphasis mb-1">Доступно</p>
                <p class="text-h5 font-weight-bold mb-2">{{ formatAmount(balance.available, balance.currency) }}</p>
                <v-divider class="my-2" />
                <div class="d-flex justify-space-between text-body-2 text-medium-emphasis">
                  <span>Заблокировано: {{ formatAmount(balance.locked, balance.currency) }}</span>
                  <span>Всего: {{ formatAmount(balance.total, balance.currency) }}</span>
                </div>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col v-if="0 === wallet.balances.length && !wallet.loading" cols="12">
            <v-card class="profile-balance-card" rounded="lg">
              <v-card-text class="text-center pa-8 text-medium-emphasis">
                <v-icon size="48" class="mb-3" color="primary">mdi-wallet-outline</v-icon>
                <p class="text-h6 mb-1">Балансы пока пусты</p>
                <p v-if="!identity.isConnected" class="text-body-2">
                  <router-link to="/profile/api-connection" class="text-primary">Подключите Bybit API</router-link>
                  для отображения балансов
                </p>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-tabs-window-item>

      <!-- Транзакции -->
      <v-tabs-window-item value="transactions">
        <div class="d-flex align-center justify-space-between mb-4">
          <span class="text-body-1 text-medium-emphasis">История операций</span>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            rounded="lg"
            :loading="wallet.loading"
            prepend-icon="mdi-refresh"
            @click="refreshTransactions"
          >
            Обновить
          </v-btn>
        </div>

        <v-alert v-if="wallet.error" type="error" variant="tonal" class="mb-4">{{ wallet.error }}</v-alert>

        <UiTableCard
          title="Последние операции"
          subtitle="Движение средств по вашим балансам"
          icon="mdi-history"
          color="info"
          gradient="neutral"
        >
          <v-table density="comfortable" hover>
            <thead>
              <tr>
                <th>Тип</th>
                <th class="text-right">Сумма</th>
                <th>Валюта</th>
                <th>Дата</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="0 === wallet.transactions.length && !wallet.loading">
                <td colspan="4" class="text-center text-medium-emphasis pa-6">Нет транзакций</td>
              </tr>
              <tr v-for="tx in wallet.transactions" :key="tx.id">
                <td>
                  <v-chip size="small" variant="tonal" :color="txColor(tx.type)">{{ txLabel(tx.type) }}</v-chip>
                </td>
                <td class="text-right font-weight-medium">{{ formatAmount(tx.amount, tx.currency) }}</td>
                <td>{{ tx.currency }}</td>
                <td class="text-medium-emphasis">{{ formatDate(tx.createdAt) }}</td>
              </tr>
            </tbody>
          </v-table>
        </UiTableCard>
      </v-tabs-window-item>

      <!-- Bybit API -->
      <v-tabs-window-item value="api">
        <v-alert v-if="identity.error" type="error" variant="tonal" class="mb-4">{{ identity.error }}</v-alert>

        <UiParentCard :title="identityCardTitle" :subtitle="identityCardSubtitle" :icon="identityCardIcon" :color="identityCardColor">
          <v-card-text class="pa-5">
            <!-- Подключён -->
            <div v-if="identity.isConnected" class="text-center">
              <v-icon size="56" color="success" class="mb-4">mdi-check-circle</v-icon>

              <v-row justify="center" class="mb-5">
                <v-col cols="auto">
                  <v-chip color="info" variant="tonal" size="default"> Режим: {{ identity.modeLabel ?? '—' }} </v-chip>
                </v-col>
                <v-col cols="auto">
                  <v-chip :color="identityStatusColor()" variant="tonal" size="default">
                    {{ identity.statusLabel ?? '—' }}
                  </v-chip>
                </v-col>
              </v-row>

              <div class="d-flex justify-center ga-3">
                <v-btn
                  color="info"
                  variant="outlined"
                  rounded="lg"
                  prepend-icon="mdi-shield-check"
                  :loading="identity.loading"
                  @click="identity.verify()"
                >
                  Проверить
                </v-btn>
                <v-btn
                  color="error"
                  variant="outlined"
                  rounded="lg"
                  prepend-icon="mdi-link-variant-off"
                  :loading="identity.loading"
                  @click="identity.disconnect()"
                >
                  Отключить
                </v-btn>
              </div>
            </div>

            <!-- Не подключён -->
            <div v-else class="text-center">
              <v-icon size="56" color="warning" class="mb-4">mdi-link-variant-off</v-icon>
              <h3 class="text-h5 font-weight-bold mb-2">API не подключён</h3>
              <p class="text-medium-emphasis mb-5">Подключите Bybit API для начала торговли</p>
              <v-btn
                color="secondary"
                variant="flat"
                rounded="lg"
                size="large"
                prepend-icon="mdi-link-variant-plus"
                to="/profile/api-connection"
              >
                Подключить
              </v-btn>
            </div>
          </v-card-text>
        </UiParentCard>
      </v-tabs-window-item>
    </v-tabs-window>
  </div>
</template>

<style scoped lang="scss">
.profile-balance-card {
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
