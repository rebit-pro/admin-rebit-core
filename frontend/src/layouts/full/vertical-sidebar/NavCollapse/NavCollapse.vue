<script setup>
import { useI18n } from 'vue-i18n';
const { t, te } = useI18n();

import NavItem from '../NavItem/NavItem.vue';
import Icon from '../IconSet.vue';

const props = defineProps({ item: Object, level: Number });

function translateLabel(value) {
  if ('string' !== typeof value || '' === value) {
    return '';
  }

  return te(value) ? t(value) : value;
}
</script>

<template>
  <!-- ---------------------------------------------- -->
  <!---Item Childern -->
  <!-- ---------------------------------------------- -->
  <v-list-group no-action>
    <!-- ---------------------------------------------- -->
    <!---Dropdown  -->
    <!-- ---------------------------------------------- -->
    <template #activator="{ props }">
      <v-list-item v-bind="props" :value="item.title" aria-labelledby="listbox" rounded class="mb-1" color="secondary">
        <!---Icon  -->
        <template #prepend>
          <Icon :item="item.icon" :level="level" />
        </template>
        <!---Title  -->
        <v-list-item-title class="me-auto">
          {{ translateLabel(item.title) }}
          <!---If any chip or label-->
          <v-badge :color="item.chipColor" v-if="item.chip" :aria-label="item.chip" inline dot></v-badge>
        </v-list-item-title>
        <!---If Caption-->
        <v-list-item-subtitle v-if="item.subCaption" class="text-caption mt-n1 hide-menu">
          {{ translateLabel(item.subCaption) }}
        </v-list-item-subtitle>
      </v-list-item>
    </template>
    <!-- ---------------------------------------------- -->
    <!---Sub Item-->
    <!-- ---------------------------------------------- -->
    <template v-for="(subitem, i) in item.children" :key="i">
      <NavCollapse v-if="subitem.children" :item="subitem" :level="props.level + 1" />
      <NavItem v-else :item="subitem" :level="props.level + 1" />
    </template>
  </v-list-group>

  <!-- ---------------------------------------------- -->
  <!---End Item Sub Header -->
  <!-- ---------------------------------------------- -->
</template>
