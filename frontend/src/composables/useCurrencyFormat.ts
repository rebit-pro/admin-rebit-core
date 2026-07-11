/**
 * Composable для форматирования денежных сумм.
 * Фиат (RUB и т.п.) — формат ru-RU с округлением до 2 знаков.
 * Крипта (USDT, BTC и т.п.) — формат ru-RU, до 8 знаков.
 */

const FIAT_CODES = new Set(['RUB', 'USD', 'EUR', 'UAH', 'KZT', 'BYN', 'GEL', 'TRY', 'CNY', 'AED']);

const fiatFormatter = new Intl.NumberFormat('ru-RU', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
});

const cryptoFormatter = new Intl.NumberFormat('ru-RU', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 8
});

const rubSuffixFormatter = new Intl.NumberFormat('ru-RU', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
});

const numberFormatterCache = new Map<number, Intl.NumberFormat>();

function getNumberFormatter(maximumFractionDigits: number): Intl.NumberFormat {
  let formatter = numberFormatterCache.get(maximumFractionDigits);

  if (undefined === formatter) {
    formatter = new Intl.NumberFormat('ru-RU', {
      minimumFractionDigits: 0,
      maximumFractionDigits
    });
    numberFormatterCache.set(maximumFractionDigits, formatter);
  }

  return formatter;
}

export function useCurrencyFormat() {
  function parseAmount(value: string | number): number {
    const parsed = 'number' === typeof value ? value : Number.parseFloat(value);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  /**
   * Форматирует сумму в зависимости от валюты.
   * Фиатные — ru-RU, 2 знака. Крипта — до 8 знаков.
   */
  function formatAmount(value: string | number, currency?: string): string {
    const num = parseAmount(value);
    const isFiat = undefined !== currency && FIAT_CODES.has(currency.toUpperCase());

    return isFiat ? fiatFormatter.format(num) : cryptoFormatter.format(num);
  }

  /** Форматирует фиатную сумму с символом ₽ */
  function formatRub(value: string | number): string {
    return `${rubSuffixFormatter.format(parseAmount(value))} ₽`;
  }

  /** Форматирует число в стиле ru-RU с указанным кол-вом знаков */
  function formatNumber(value: string | number, maximumFractionDigits = 2): string {
    return getNumberFormatter(maximumFractionDigits).format(parseAmount(value));
  }

  function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('ru-RU');
  }

  return {
    parseAmount,
    formatAmount,
    formatRub,
    formatNumber,
    formatDate
  };
}
