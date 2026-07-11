<script setup lang="ts">
/**
 * UiParentCard — единая карточка-обёртка для всех блоков.
 * Повторяет паттерн dashboard-card из DashboardPage / OrderBookPage.
 */
defineProps<{
  title: string;
  subtitle?: string;
  icon?: string;
  color?: string;
  headerCompact?: boolean;
}>();
</script>

<template>
  <v-card class="ui-parent-card" rounded="lg">
    <v-card-item class="ui-parent-card__header px-5 py-4" :class="{ 'ui-parent-card__header--compact': headerCompact }">
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

    <v-divider class="ui-parent-card__divider" />

    <slot />
  </v-card>
</template>

<style scoped lang="scss">
.ui-parent-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.ui-parent-card__header {
  min-height: 96px;
}

.ui-parent-card__header--compact {
  min-height: 76px;
}

.ui-parent-card__divider {
  opacity: 1;
}
</style>
