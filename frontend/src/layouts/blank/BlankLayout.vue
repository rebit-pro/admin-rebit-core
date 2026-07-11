// ===============================|| Blank Layout ||=============================== //
<template>
  <v-locale-provider :rtl="customizer.isRtl">
    <v-app :theme="customizer.actTheme" :class="[customizer.actTheme, customizer.fontTheme, customizer.inputBg ? 'inputWithbg' : '']">
      <RouterView />
    </v-app>
  </v-locale-provider>
</template>
<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { RouterView } from 'vue-router';
import { useCustomizerStore } from '../../stores/customizer';
import { DirAttrSet } from '@/utils/utils';

const customizer = useCustomizerStore();

// Set the initial direction attribute when the component is mounted
onMounted(() => {
  DirAttrSet(customizer.isRtl ? 'rtl' : 'ltr');
});

// Watch for changes in the isRtl property and update the direction attribute accordingly
watch(
  () => customizer.isRtl,
  (newValue) => {
    DirAttrSet(newValue ? 'rtl' : 'ltr');
  }
);
</script>
