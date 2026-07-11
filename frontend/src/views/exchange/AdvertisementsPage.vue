<script setup lang="ts">
import { ref, onMounted, computed, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdvertisementsStore } from '@/stores/advertisements';
import type { AdvertisementStatus } from '@/api/exchange';
import { isMockApiEnabled } from '@/mocks/config';
import { createMockTradeScenario } from '@/mocks/runtime';

const router = useRouter();
const route = useRoute();
const ads = useAdvertisementsStore();

const statusFilter = ref<AdvertisementStatus | ''>('');
const deleteDialog = ref(false);
const deleteTargetId = ref<number | null>(null);
const highlightedAdvertisementId = computed(() => {
  const value = route.query.highlight;
  const normalizedValue = 'string' === typeof value ? Number(value) : Number.NaN;

  return Number.isNaN(normalizedValue) ? null : normalizedValue;
});

const statusOptions: { title: string; value: AdvertisementStatus | '' }[] = [
  { title: 'Все', value: '' },
  { title: 'Активные', value: 'active' },
  { title: 'Приостановлены', value: 'paused' },
  { title: 'Завершены', value: 'completed' },
  { title: 'Отменены', value: 'cancelled' }
];

const statusLabels: Record<string, string> = {
  active: 'Активно',
  paused: 'Приостановлено',
  completed: 'Завершено',
  cancelled: 'Отменено'
};

const statusColors: Record<string, string> = {
  active: 'success',
  paused: 'warning',
  completed: 'grey',
  cancelled: 'error'
};

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('ru-RU');
}

async function loadAds(): Promise<void> {
  const status = '' !== statusFilter.value ? statusFilter.value : undefined;
  await ads.fetchAdvertisements(status);
  await scrollToHighlightedAdvertisement();
}

async function scrollToHighlightedAdvertisement(): Promise<void> {
  if (null === highlightedAdvertisementId.value) {
    return;
  }

  await nextTick();
  document
    .querySelector(`[data-advertisement-id="${highlightedAdvertisementId.value}"]`)
    ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function confirmDelete(id: number): void {
  deleteTargetId.value = id;
  deleteDialog.value = true;
}

async function handleDelete(): Promise<void> {
  if (null === deleteTargetId.value) return;
  try {
    await ads.deleteAdvertisement(deleteTargetId.value);
    deleteDialog.value = false;
    deleteTargetId.value = null;
  } catch {
    // ошибка обрабатывается в сторе
  }
}

async function handleToggle(id: number, status: 'active' | 'paused'): Promise<void> {
  try {
    await ads.toggleAdvertisement(id, status);
  } catch {
    // ошибка обрабатывается в сторе
  }
}

function handleCreateMockTrade(id: number): void {
  const trade = createMockTradeScenario(id);

  if (null !== trade) {
    void router.push(`/exchange/trades/${trade.id}`);
  }
}

onMounted(async () => {
  await loadAds();
});

watch(highlightedAdvertisementId, async () => {
  await scrollToHighlightedAdvertisement();
});
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3">
      <h2 class="text-h4">Мои объявления</h2>
      <div class="d-flex align-center ga-3">
        <v-select
          v-model="statusFilter"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          label="Статус"
          variant="outlined"
          density="compact"
          hide-details
          style="max-width: 200px"
          @update:model-value="loadAds"
        />
        <v-btn color="secondary" variant="tonal" prepend-icon="mdi-swap-horizontal-bold" to="/orderbook"> Стакан заявок </v-btn>
        <v-btn color="secondary" prepend-icon="mdi-plus" @click="router.push('/exchange/advertisements/create')"> Создать </v-btn>
      </div>
    </div>

    <v-alert v-if="isMockApiEnabled" type="info" variant="tonal" class="mb-4">
      Для активного объявления можно вручную создать mock-сделку. Если ничего не нажимать, новая сделка тоже появится автоматически по
      polling.
    </v-alert>

    <v-row v-if="ads.loading" justify="center" class="mt-8">
      <v-progress-circular indeterminate color="primary" />
    </v-row>

    <v-alert v-if="ads.error" type="error" variant="tonal" class="mb-4">{{ ads.error }}</v-alert>

    <v-card v-if="!ads.loading" rounded="md">
      <v-table density="comfortable" hover>
        <thead>
          <tr>
            <th>Направление</th>
            <th>Тип цены</th>
            <th class="text-right">Цена</th>
            <th class="text-right">Количество</th>
            <th class="text-right">Лимиты</th>
            <th>Статус</th>
            <th>Дата</th>
            <th class="text-right">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="0 === ads.advertisements.length">
            <td colspan="8" class="text-center text-lightText pa-6">
              Нет объявлений.
              <v-btn variant="text" color="primary" size="small" @click="router.push('/exchange/advertisements/create')">
                Создать первое
              </v-btn>
            </td>
          </tr>
          <tr
            v-for="ad in ads.advertisements"
            :key="ad.id"
            :data-advertisement-id="ad.id"
            :class="{ 'advertisements-page__row--highlighted': ad.id === highlightedAdvertisementId }"
          >
            <td>
              <v-chip size="small" variant="tonal" :color="'buy' === ad.side ? 'success' : 'error'">
                {{ 'buy' === ad.side ? 'Покупка' : 'Продажа' }}
              </v-chip>
            </td>
            <td class="text-body-2">{{ 'fixed' === ad.priceType ? 'Фикс.' : 'Плав.' }}</td>
            <td class="text-right font-weight-medium">{{ ad.price }} ₽</td>
            <td class="text-right">{{ ad.quantity }}</td>
            <td class="text-right text-body-2">{{ ad.minAmount }} – {{ ad.maxAmount }} ₽</td>
            <td>
              <v-chip size="small" variant="tonal" :color="statusColors[ad.status] ?? 'default'">
                {{ statusLabels[ad.status] ?? ad.status }}
              </v-chip>
            </td>
            <td class="text-lightText text-body-2">{{ formatDate(ad.createdAt) }}</td>
            <td class="text-right">
              <div class="d-flex justify-end flex-wrap ga-1">
                <v-btn
                  v-if="isMockApiEnabled && 'active' === ad.status"
                  icon="mdi-chat-plus-outline"
                  size="small"
                  variant="text"
                  color="primary"
                  :disabled="ads.actionLoading"
                  aria-label="Создать тестовую сделку"
                  title="Создать тестовую сделку"
                  @click.stop="handleCreateMockTrade(ad.id)"
                />
                <v-btn
                  v-if="'active' === ad.status"
                  icon="mdi-pause"
                  size="small"
                  variant="text"
                  color="warning"
                  :disabled="ads.actionLoading"
                  aria-label="Приостановить объявление"
                  title="Приостановить объявление"
                  @click="handleToggle(ad.id, 'paused')"
                />
                <v-btn
                  v-if="'paused' === ad.status"
                  icon="mdi-play"
                  size="small"
                  variant="text"
                  color="success"
                  :disabled="ads.actionLoading"
                  aria-label="Включить объявление"
                  title="Включить объявление"
                  @click="handleToggle(ad.id, 'active')"
                />
                <v-btn
                  icon="mdi-delete-outline"
                  size="small"
                  variant="text"
                  color="error"
                  :disabled="'cancelled' === ad.status || 'completed' === ad.status"
                  aria-label="Удалить объявление"
                  title="Удалить объявление"
                  @click="confirmDelete(ad.id)"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <!-- Диалог удаления -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить объявление?</v-card-title>
        <v-card-text> Объявление будет деактивировано и отменено на Bybit. Это действие нельзя отменить. </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Отмена</v-btn>
          <v-btn color="error" :loading="ads.actionLoading" @click="handleDelete">Удалить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.advertisements-page__row--highlighted {
  background: rgba(33, 150, 243, 0.08);
}
</style>
