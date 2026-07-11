<script setup lang="ts">
/**
 * UiTableCard — карточка-обёртка для таблиц с градиентом (как OrderBook).
 * Поддерживает варианты buy/sell/neutral градиента.
 */
defineProps<{
  title: string;
  subtitle?: string;
  icon?: string;
  color?: string;
  gradient?: 'buy' | 'sell' | 'neutral' | 'none';
}>();
</script>

<template>
  <v-card
    class="ui-table-card"
    :class="{
      'ui-table-card--buy': 'buy' === gradient,
      'ui-table-card--sell': 'sell' === gradient,
      'ui-table-card--neutral': 'neutral' === gradient
    }"
    rounded="lg"
  >
    <v-card-item class="ui-table-card__header px-5 py-4">
      <template v-if="icon" #prepend>
        <v-avatar size="42" :color="color ?? 'primary'" variant="tonal">
          <v-icon>{{ icon }}</v-icon>
        </v-avatar>
      </template>

      <v-card-title class="text-h6 font-weight-bold">{{ title }}</v-card-title>
      <v-card-subtitle v-if="subtitle">{{ subtitle }}</v-card-subtitle>

      <template v-if="$slots.append" #append>
        <slot name="append" />
      </template>
    </v-card-item>

    <v-divider class="ui-table-card__divider" />

    <slot name="toolbar" />

    <slot />
  </v-card>
</template>

<style scoped lang="scss">
.ui-table-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.ui-table-card--buy {
  background: linear-gradient(180deg, rgba(0, 200, 83, 0.03), #ffffff 18%);
}

.ui-table-card--sell {
  background: linear-gradient(180deg, rgba(244, 67, 54, 0.03), #ffffff 18%);
}

.ui-table-card--neutral {
  background: linear-gradient(180deg, rgba(30, 136, 229, 0.03), #ffffff 18%);
}

.ui-table-card__header {
  min-height: 96px;
}

.ui-table-card__divider {
  opacity: 1;
}
</style>
