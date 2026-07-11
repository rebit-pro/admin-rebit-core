/**
 * Composable для единых лейблов и цветов типов транзакций.
 * Убирает дублирование из DashboardPage, ProfilePage, TransactionsPage.
 */

const TX_LABELS: Record<string, string> = {
  deposit: 'Депозит',
  withdrawal: 'Вывод',
  trade_buy: 'Покупка',
  trade_sell: 'Продажа',
  lock: 'Блокировка',
  unlock: 'Разблокировка',
  fee: 'Комиссия'
};

const TX_COLORS: Record<string, string> = {
  deposit: 'success',
  withdrawal: 'error',
  trade_buy: 'info',
  trade_sell: 'warning',
  lock: 'grey',
  unlock: 'grey',
  fee: 'error'
};

const TX_ICONS: Record<string, string> = {
  deposit: 'mdi-arrow-bottom-left',
  withdrawal: 'mdi-arrow-top-right',
  trade_buy: 'mdi-arrow-bottom-left',
  trade_sell: 'mdi-arrow-top-right',
  lock: 'mdi-lock-outline',
  unlock: 'mdi-lock-open-outline',
  fee: 'mdi-percent-outline'
};

export function useTransactionLabels() {
  function txLabel(type: string): string {
    return TX_LABELS[type] ?? type;
  }

  function txColor(type: string): string {
    return TX_COLORS[type] ?? 'default';
  }

  function txIcon(type: string): string {
    return TX_ICONS[type] ?? 'mdi-swap-horizontal';
  }

  return { txLabel, txColor, txIcon };
}
