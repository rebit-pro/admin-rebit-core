<script setup lang="ts">
import { computed } from 'vue';
import LogoLight from './LogoLight.vue';
import LogoDark from './LogoDark.vue';
import { useCustomizerStore } from '@/stores/customizer';

const customizer = useCustomizerStore();
const isDark = computed(() => customizer.isDarkMode);
</script>

<template>
  <LogoLight v-if="isDark" />
  <LogoDark v-else />
</template>
