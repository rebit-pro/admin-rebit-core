<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps<{
  deadline: string;
  totalSeconds?: number;
}>();

const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | null = null;

const deadlineMs = computed(() => {
  const ms = new Date(props.deadline).getTime();
  return Number.isFinite(ms) ? ms : 0;
});
const total = computed(() => (props.totalSeconds ?? 900) * 1000);
const remaining = computed(() => Math.max(0, deadlineMs.value - now.value));
const isExpired = computed(() => 0 === remaining.value);
const progress = computed(() => {
  if (0 === total.value) return 0;
  return Math.min(100, (remaining.value / total.value) * 100);
});

const minutes = computed(() => Math.floor(remaining.value / 60000));
const seconds = computed(() => Math.floor((remaining.value % 60000) / 1000));
const timeText = computed(() => {
  if (isExpired.value) return 'Время вышло';
  return `${minutes.value}:${String(seconds.value).padStart(2, '0')}`;
});

const progressColor = computed(() => {
  if (isExpired.value) return 'error';
  if (progress.value < 15) return 'error';
  if (progress.value < 40) return 'warning';
  return 'success';
});

const isPulsing = computed(() => !isExpired.value && remaining.value < 60000);

function tick(): void {
  now.value = Date.now();
}

onMounted(() => {
  timer = setInterval(tick, 1000);
});

onUnmounted(() => {
  if (null !== timer) clearInterval(timer);
});

watch(
  () => props.deadline,
  () => {
    now.value = Date.now();
  }
);
</script>

<template>
  <v-card class="trade-countdown" rounded="lg" variant="tonal" :color="progressColor">
    <v-card-text class="d-flex align-center justify-center pa-5">
      <div class="trade-countdown__ring mr-5" :class="{ 'trade-countdown__ring--pulse': isPulsing }">
        <v-progress-circular :model-value="progress" :size="88" :width="6" :color="progressColor" bg-color="rgba(0,0,0,0.06)">
          <div class="text-center">
            <v-icon :color="progressColor" size="18" class="mb-1">mdi-timer-outline</v-icon>
            <div class="font-weight-bold trade-countdown__time" :class="`text-${progressColor}`">
              {{ timeText }}
            </div>
          </div>
        </v-progress-circular>
      </div>

      <div>
        <div class="text-subtitle-1 font-weight-bold mb-1">
          {{ isExpired ? 'Время на оплату истекло' : 'Время на оплату' }}
        </div>
        <div class="text-body-2 text-medium-emphasis">
          {{
            isExpired ? 'Срок оплаты прошёл. Сделка может быть отменена.' : 'Оплатите до истечения таймера, чтобы сделка не была отменена.'
          }}
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>

<style scoped lang="scss">
.trade-countdown {
  border: 1px solid rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.trade-countdown__time {
  font-size: 0.85rem;
  line-height: 1.2;
  white-space: nowrap;
}

.trade-countdown__ring--pulse {
  animation: countdown-pulse 1.2s ease-in-out infinite;
}

@keyframes countdown-pulse {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.06);
  }
}
</style>
