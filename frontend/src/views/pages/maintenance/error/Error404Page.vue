<script setup lang="ts"></script>

<template>
  <v-row no-gutters class="h-screen">
    <v-col class="d-flex align-center justify-center">
      <div class="text-center">
        <v-icon size="120" color="primary" class="mb-6">mdi-alert-circle-outline</v-icon>
        <h1 class="text-h1 text-primary mb-4">404</h1>
        <h2 class="text-h4 mb-2">Страница не найдена</h2>
        <p class="text-lightText mb-6">
          Запрашиваемая страница была перемещена, удалена<br />
          или никогда не существовала.
        </p>
        <v-btn variant="flat" color="primary" size="large" to="/" prepend-icon="$home"> На главную </v-btn>
      </div>
    </v-col>
  </v-row>
</template>
