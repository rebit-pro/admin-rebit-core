<template>
  <div class="logo">
    <RouterLink to="/" aria-label="ReBit Admin Core" class="text-decoration-none d-flex align-center">
      <img src="/favicon.svg" alt="ReBit Admin Core" width="32" height="32" class="mr-2" />
      <span class="text-h5 font-weight-bold">ReBit Admin Core</span>
    </RouterLink>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>
