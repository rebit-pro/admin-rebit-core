<script setup>
import NavItem from '../NavItem/NavItem.vue';
import Icon from '../../vertical-sidebar/IconSet.vue';

const props = defineProps({ item: Object, level: Number });
</script>

<template>
  <!---Dropdown  -->
  <a class="navItemLink rounded-md cursor-pointer">
    <!---Icon  -->
    <i class="navIcon"><Icon :item="props.item.icon" :level="level" /></i>
    <!---Title  -->
    <span class="me-auto">
      {{ item.title }}
      <!---If any chip or label-->
      <v-badge :color="item.chipColor" v-if="item.chip" :aria-label="item.chip" inline dot></v-badge>
    </span>
    <!---If Caption-->
    <small v-if="item.subCaption" class="text-caption mt-n1 hide-menu">
      {{ item.subCaption }}
    </small>
    <i class="ddIcon ms-2"><ChevronRightIcon size="15" /></i>
  </a>
  <!---Sub Item-->
  <ul :class="`ddMenu ddLevel-${level + 1}`">
    <li v-for="(subitem, i) in item.children" :key="i" class="navItem">
      <NavCollapse v-if="subitem.children" :item="subitem" :level="props.level + 1" />
      <NavItem v-else :item="subitem" :level="props.level + 1" />
    </li>
  </ul>
  <!---End Item Sub Header -->
</template>
