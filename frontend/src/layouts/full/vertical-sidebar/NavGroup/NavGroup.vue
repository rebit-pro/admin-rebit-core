<script setup>
import { useI18n } from 'vue-i18n';
const { t, te } = useI18n();

const props = defineProps({ item: Object });

function translateLabel(value) {
  if ('string' !== typeof value || '' === value) {
    return '';
  }

  return te(value) ? t(value) : value;
}
</script>

<template>
  <v-list-subheader color="darkText" class="smallCap">
    {{ translateLabel(props.item.header) }}
  </v-list-subheader>
</template>
