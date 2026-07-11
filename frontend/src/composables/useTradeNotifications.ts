import { watch, ref } from 'vue';
import type { Trade } from '@/api/exchange';

const NOTIFICATION_SOUND_URL = '/sounds/new-trade.wav';
const NOTIFICATION_TITLE = 'ReBit Admin Core';
const MAX_TRACKED_TRADE_IDS = 200;

let audioInstance: HTMLAudioElement | null = null;

function getAudio(): HTMLAudioElement {
  if (null === audioInstance) {
    audioInstance = new Audio(NOTIFICATION_SOUND_URL);
    audioInstance.volume = 0.5;
  }

  return audioInstance;
}

function playSound(): void {
  const audio = getAudio();
  audio.currentTime = 0;
  audio.play().catch(() => {
    // браузер может блокировать autoplay до взаимодействия пользователя
  });
}

function requestNotificationPermission(): void {
  if ('undefined' === typeof Notification) {
    return;
  }

  if ('default' === Notification.permission) {
    void Notification.requestPermission();
  }
}

function showBrowserNotification(title: string, body: string): void {
  if ('undefined' === typeof Notification || 'granted' !== Notification.permission) {
    return;
  }

  new Notification(title, {
    body,
    icon: '/favicon.ico',
    tag: 'rebit-new-trade'
  });
}

function trackTradeId(knownTradeIds: Set<number>, trackedTradeIds: number[], tradeId: number): void {
  if (knownTradeIds.has(tradeId)) {
    return;
  }

  knownTradeIds.add(tradeId);
  trackedTradeIds.push(tradeId);

  if (MAX_TRACKED_TRADE_IDS < trackedTradeIds.length) {
    const removedTradeId = trackedTradeIds.shift();

    if (undefined !== removedTradeId) {
      knownTradeIds.delete(removedTradeId);
    }
  }
}

/**
 * Composable для звуковых и браузерных уведомлений при появлении новых сделок.
 * Принимает getter-функцию, возвращающую массив сделок из store.
 */
export function useTradeNotifications(getTrades: () => Trade[]): void {
  const knownTradeIds = ref<Set<number>>(new Set());
  const trackedTradeIds = ref<number[]>([]);
  let isInitialized = false;

  watch(
    () =>
      getTrades()
        .map((trade) => trade.id + (true === trade.isNew ? ':new' : ''))
        .join(','),
    () => {
      const currentTrades = getTrades();

      if (!isInitialized) {
        for (const trade of currentTrades) {
          trackTradeId(knownTradeIds.value, trackedTradeIds.value, trade.id);
        }
        isInitialized = true;

        return;
      }

      const newTrades = currentTrades.filter((trade) => true === trade.isNew && !knownTradeIds.value.has(trade.id));

      for (const trade of currentTrades) {
        trackTradeId(knownTradeIds.value, trackedTradeIds.value, trade.id);
      }

      if (0 < newTrades.length) {
        requestNotificationPermission();
        playSound();

        const trade = newTrades[0];

        if (undefined === trade) {
          return;
        }

        const side = 'buy' === trade.side ? 'Покупка' : 'Продажа';
        const body =
          1 === newTrades.length
            ? `${trade.counterpartyName} · ${side} · ${trade.fiatAmount.toFixed(2)} ₽`
            : `Получено ${newTrades.length} новых сделок`;

        showBrowserNotification(NOTIFICATION_TITLE, body);
      }
    }
  );
}
