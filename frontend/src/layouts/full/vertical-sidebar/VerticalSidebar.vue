<script setup lang="ts">
import { shallowRef } from 'vue';
import { useCustomizerStore } from '../../../stores/customizer';
import sidebarItems from './sidebarItem';
import NavItem from './NavItem/NavItem.vue';

const customizer = useCustomizerStore();
const sidebarMenu = shallowRef(sidebarItems);
const appVersion = import.meta.env.VITE_APP_VERSION;
</script>

<template>
  <v-navigation-drawer
    v-model="customizer.Sidebar_drawer"
    left
    elevation="0"
    rail-width="75"
    mobile-breakpoint="lg"
    app
    width="256"
    class="leftSidebar"
    :rail="customizer.mini_sidebar"
    expand-on-hover
  >
    <PerfectScrollbar class="scrollnavbar" :options="{ suppressScrollX: true }">
      <v-list class="pa-4">
        <NavItem v-for="item in sidebarMenu" :key="item.id" :item="item" class="leftPadding" />
      </v-list>

      <div class="pa-4 text-center">
        <v-chip color="inputBorder" size="small">{{ appVersion }}</v-chip>
      </div>
    </PerfectScrollbar>
  </v-navigation-drawer>
</template>