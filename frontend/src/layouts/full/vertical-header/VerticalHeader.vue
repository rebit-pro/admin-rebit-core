<script setup lang="ts">
import { useCustomizerStore } from '@/stores/customizer';
import { Menu2Icon } from 'vue-tabler-icons';
import ProfileDD from './ProfileDD.vue';
import Logo from '../logo/LogoMain.vue';

const customizer = useCustomizerStore();
</script>

<template>
  <v-app-bar elevation="0" height="80">
    <v-btn
      class="hidden-md-and-down text-secondary"
      color="lightsecondary"
      icon
      rounded="sm"
      variant="flat"
      size="small"
      aria-label="Свернуть меню"
      @click.stop="customizer.SET_MINI_SIDEBAR(!customizer.mini_sidebar)"
    >
      <Menu2Icon size="20" stroke-width="1.5" />
    </v-btn>
    <v-btn
      class="hidden-lg-and-up text-secondary ms-3"
      color="lightsecondary"
      icon
      rounded="sm"
      variant="flat"
      size="small"
      aria-label="Открыть меню"
      @click.stop="customizer.SET_SIDEBAR_DRAWER"
    >
      <Menu2Icon size="20" stroke-width="1.5" />
    </v-btn>

    <v-spacer />
    <Logo />
    <v-spacer />

    <v-menu :close-on-content-click="false">
      <template #activator="{ props }">
        <v-btn class="profileBtn" variant="text" rounded="pill" v-bind="props" aria-label="Меню пользователя">
          <v-avatar size="30" color="primary" variant="tonal">
            <v-icon size="18">mdi-account</v-icon>
          </v-avatar>
        </v-btn>
      </template>
      <v-sheet rounded="md" width="320" elevation="12">
        <ProfileDD />
      </v-sheet>
    </v-menu>
  </v-app-bar>
</template>
