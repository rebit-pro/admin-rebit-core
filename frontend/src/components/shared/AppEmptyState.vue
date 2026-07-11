<script setup lang="ts">
import { computed, type Component } from 'vue';

const props = withDefaults(
  defineProps<{
    icon: Component;
    title: string;
    description: string;
    eyebrow?: string;
    tone?: 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info';
    variant?: 'default' | 'gradient';
    align?: 'center' | 'left';
    compact?: boolean;
    iconSize?: number;
  }>(),
  {
    eyebrow: '',
    tone: 'primary',
    variant: 'default',
    align: 'center',
    compact: false,
    iconSize: 32
  }
);

const stateClasses = computed(() => ({
  'app-empty-state--gradient': 'gradient' === props.variant,
  'app-empty-state--left': 'left' === props.align,
  'app-empty-state--compact': props.compact
}));
</script>

<template>
  <div class="app-empty-state" :class="stateClasses">
    <div class="app-empty-state__body">
      <div class="app-empty-state__icon-wrap" :class="`app-empty-state__icon-wrap--${tone}`">
        <component :is="icon" :size="iconSize" stroke-width="1.75" />
      </div>

      <div v-if="'' !== eyebrow" class="app-empty-state__eyebrow">
        {{ eyebrow }}
      </div>

      <h3 class="app-empty-state__title">
        {{ title }}
      </h3>

      <p class="app-empty-state__description">
        {{ description }}
      </p>

      <div v-if="$slots.actions" class="app-empty-state__actions">
        <slot name="actions" />
      </div>

      <div v-if="$slots.default" class="app-empty-state__extra">
        <slot />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.app-empty-state {
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 24px;
  background:
    radial-gradient(circle at top right, rgba(30, 136, 229, 0.1), transparent 38%),
    linear-gradient(180deg, #ffffff 0%, rgba(30, 136, 229, 0.03) 100%);
}

.app-empty-state--gradient {
  border-color: rgba(255, 255, 255, 0.12);
  background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%);
  color: #ffffff;
}

.app-empty-state__body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 32px 40px;
  text-align: center;
}

.app-empty-state--compact .app-empty-state__body {
  gap: 12px;
  padding: 24px;
}

.app-empty-state--left .app-empty-state__body {
  align-items: flex-start;
  text-align: left;
}

.app-empty-state__icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 72px;
  height: 72px;
  border-radius: 20px;
  background: rgba(30, 136, 229, 0.12);
  color: #1e88e5;
}

.app-empty-state--compact .app-empty-state__icon-wrap {
  width: 64px;
  height: 64px;
}

.app-empty-state--gradient .app-empty-state__icon-wrap {
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
}

.app-empty-state__icon-wrap--secondary {
  background: rgba(94, 53, 177, 0.14);
  color: #5e35b1;
}

.app-empty-state__icon-wrap--success {
  background: rgba(0, 200, 83, 0.14);
  color: #00c853;
}

.app-empty-state__icon-wrap--warning {
  background: rgba(255, 193, 7, 0.16);
  color: #ffc107;
}

.app-empty-state__icon-wrap--error {
  background: rgba(244, 67, 54, 0.14);
  color: #f44336;
}

.app-empty-state__icon-wrap--info {
  background: rgba(3, 201, 215, 0.14);
  color: #03c9d7;
}

.app-empty-state--gradient .app-empty-state__icon-wrap--secondary,
.app-empty-state--gradient .app-empty-state__icon-wrap--success,
.app-empty-state--gradient .app-empty-state__icon-wrap--warning,
.app-empty-state--gradient .app-empty-state__icon-wrap--error,
.app-empty-state--gradient .app-empty-state__icon-wrap--info {
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
}

.app-empty-state__eyebrow {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.8;
}

.app-empty-state__title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.25;
}

.app-empty-state__description {
  max-width: 720px;
  margin: 0;
  font-size: 1rem;
  line-height: 1.6;
  color: rgba(15, 23, 42, 0.72);
}

.app-empty-state--gradient .app-empty-state__description {
  color: rgba(255, 255, 255, 0.86);
}

.app-empty-state__actions,
.app-empty-state__extra {
  width: 100%;
}
</style>
