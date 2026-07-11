<script setup lang="ts"></script>

<template>
  <v-footer class="px-0 footer mt-2">
    <v-row justify="center" align="center" no-gutters>
      <v-col cols="12" class="text-center">
        <p class="text-body-2 mb-0 text-lightText">© {{ new Date().getFullYear() }} ReBit Admin Core. Все права защищены.</p>
      </v-col>
    </v-row>
  </v-footer>
</template>

<style lang="scss">
.footer {
  position: unset;
}
</style>
