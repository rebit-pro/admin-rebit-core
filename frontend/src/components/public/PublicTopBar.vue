<template>
  <div class="public-top-bar">
    <RouterLink class="public-top-bar__brand" to="/">
      <img :src="brandLogoUrl" alt="ReBit Admin Core" width="48" height="48" class="public-top-bar__logo" />
      <div>
        <div class="public-top-bar__title">ReBit Admin Core</div>
        <div class="public-top-bar__subtitle">Контроль сделок, балансов и рисков в одном интерфейсе</div>
      </div>
    </RouterLink>

    <div class="public-top-bar__actions">
      <v-btn
        class="public-top-bar__button public-top-bar__button--docs"
        :class="{ 'public-top-bar__button--active': isDocumentationPage }"
        variant="text"
        color="white"
        rounded="lg"
        to="/documentation"
      >
        Документация
      </v-btn>
      <v-btn class="public-top-bar__button" variant="text" color="white" rounded="lg" to="/login">Вход</v-btn>
      <v-btn v-if="!auth.isAuthenticated" class="public-top-bar__button" color="secondary" variant="flat" rounded="lg" to="/register"
        >Регистрация</v-btn
      >
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const auth = useAuthStore();
const brandLogoUrl = `${import.meta.env.BASE_URL}favicon.svg`;
const isDocumentationPage = computed(() => '/documentation' === route.path);
</script>

<style scoped lang="scss">
.public-top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 24px 0;
}

.public-top-bar__brand {
  display: inline-flex;
  align-items: center;
  gap: 14px;
  color: inherit;
  text-decoration: none;
}

.public-top-bar__logo {
  display: block;
  width: 48px;
  height: 48px;
  border-radius: 16px;
  object-fit: contain;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.2);
}

.public-top-bar__title {
  font-size: 1.1rem;
  font-weight: 800;
  line-height: 1.2;
}

.public-top-bar__subtitle {
  font-size: 0.875rem;
  line-height: 1.4;
  color: rgba(255, 255, 255, 0.72);
}

.public-top-bar__actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}

.public-top-bar__button--docs {
  opacity: 0.86;
}

.public-top-bar__button--active {
  background: rgba(255, 255, 255, 0.1);
  opacity: 1;
}

@media (max-width: 767px) {
  .public-top-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .public-top-bar__brand {
    justify-content: center;
  }

  .public-top-bar__actions {
    width: 100%;
    justify-content: stretch;
  }

  .public-top-bar__button {
    flex: 1 1 0;
  }
}
</style>
