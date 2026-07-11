<script setup>
import { computed, ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
const { t, te } = useI18n();

import Icon from '../IconSet.vue';

const props = defineProps({ item: Object, level: Number });

function translateLabel(value) {
  if ('string' !== typeof value || '' === value) {
    return '';
  }

  return te(value) ? t(value) : value;
}

const relativeURL = ref('');

// Build absolute URL WITHOUT baseURL → always open from root
const fullHref = computed(() => {
  if (!props.item.getURL) return null;

  const origin = window.location.origin;
  const cleanTo = props.item.to.replace(/^\//, ''); // remove beginning slash

  return `${origin}/${cleanTo}`;
});

// Generate proper link props
const propsForLink = computed(() => {
  if (props.item.getURL) {
    return {
      href: fullHref.value,
      target: '_blank'
    };
  }

  if (props.item.type === 'external') {
    return {
      href: props.item.to,
      target: '_blank'
    };
  }

  return {
    to: props.item.to
  };
});

onMounted(async () => {
  // You probably just want import.meta.env.BASE_URL directly
  relativeURL.value = import.meta.env.BASE_URL;
});
</script>

<template>
  <!---Single Item-->
  <v-list-item v-bind="propsForLink" rounded class="mb-1" color="secondary" :disabled="props.item.disabled">
    <template #prepend>
      <Icon :item="props.item.icon" :level="props.level" />
    </template>
    <v-list-item-title>
      {{ translateLabel(props.item.title) }}
      <v-badge :color="props.item.chipColor" v-if="props.item.chipColor === 'success'" :aria-label="props.item.chip" inline dot></v-badge>
    </v-list-item-title>
    <v-list-item-subtitle v-if="props.item.subCaption" class="text-caption mt-n1 hide-menu">
      {{ translateLabel(props.item.subCaption) }}
    </v-list-item-subtitle>
    <template v-if="props.item.chip && props.item.chipColor !== 'success'" #append>
      <v-chip
        :color="props.item.chipColor"
        class="sidebarchip hide-menu"
        size="x-small"
        :variant="props.item.chipVariant"
        :prepend-icon="props.item.chipIcon"
      >
        {{ props.item.chip }}
      </v-chip>
    </template>
  </v-list-item>
</template>
