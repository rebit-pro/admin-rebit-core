<script setup lang="ts">
/**
 * UiFormCard — обёртка для форм в Berry-стиле.
 * Заголовочная область с иконкой + заголовок + описание, слот для полей, слот для кнопок.
 */
defineProps<{
  title: string;
  description?: string;
  icon?: string;
  color?: string;
}>();
</script>

<template>
  <v-card class="ui-form-card" rounded="lg">
    <v-card-item class="ui-form-card__header px-6 py-5">
      <template v-if="icon" #prepend>
        <v-avatar size="48" :color="color ?? 'primary'" variant="tonal">
          <v-icon size="24">{{ icon }}</v-icon>
        </v-avatar>
      </template>

      <v-card-title class="text-h6 font-weight-bold">{{ title }}</v-card-title>
      <v-card-subtitle v-if="description" class="mt-1 text-wrap">{{ description }}</v-card-subtitle>
    </v-card-item>

    <v-divider class="ui-form-card__divider" />

    <v-card-text class="px-6 py-5">
      <slot />
    </v-card-text>

    <template v-if="$slots.actions">
      <v-divider />
      <v-card-actions class="px-6 py-4">
        <slot name="actions" />
      </v-card-actions>
    </template>
  </v-card>
</template>

<style scoped lang="scss">
.ui-form-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.ui-form-card__header {
  min-height: 84px;
}

.ui-form-card__divider {
  opacity: 1;
}
</style>
