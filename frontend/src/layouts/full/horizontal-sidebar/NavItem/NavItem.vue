<script setup>
import Icon from '../../vertical-sidebar/IconSet.vue';
const props = defineProps({ item: Object, level: Number });
</script>

<template>
  <!---Single Item-->
  <router-link :to="`${item.to}`" class="navItemLink rounded-md" :disabled="item.disabled">
    <!---If icon-->
    <i class="navIcon"> <Icon :item="props.item.icon" :level="props.level" /></i>
    <span>
      {{ item.title }}
      <v-badge :color="props.item.chipColor" v-if="props.item.chipColor === 'success'" :aria-label="props.item.chip" inline dot></v-badge>
    </span>
    <!---If Caption-->
    <small v-if="item.subCaption" class="text-caption mt-n1 hide-menu">
      {{ item.subCaption }}
    </small>
    <!---If any chip or label-->
    <template v-if="props.item.chip && props.item.chipColor !== 'success'">
      <v-chip
        :color="item.chipColor"
        class="sidebarchip hide-menu ms-auto"
        size="x-small"
        :variant="item.chipVariant"
        :prepend-icon="item.chipIcon"
      >
        {{ item.chip }}
      </v-chip>
    </template>
  </router-link>
</template>
