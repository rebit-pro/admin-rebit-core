<template>
  <RouterView />
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router';
import { useThemeHandler } from '@/composables/useThemeHandler';
import { useRouteSeo } from '@/composables/useSeoMeta';

// Initialize theme handling
useThemeHandler();

// Initialize reactive SEO meta tags based on route meta
useRouteSeo();
</script>
