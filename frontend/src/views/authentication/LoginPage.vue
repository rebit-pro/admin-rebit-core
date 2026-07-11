<script setup lang="ts">
import AuthLogin from './authForms/AuthLogin.vue';
</script>

<template>
  <v-row class="h-screen" no-gutters>
    <v-col cols="12" class="d-flex align-center justify-center bg-lightprimary">
      <v-container>
        <v-row justify="center">
          <v-col cols="12" sm="8" md="6" lg="4" xl="3">
            <v-card elevation="0" class="loginBox">
              <v-card variant="outlined">
                <v-card-text class="pa-9">
                  <div class="text-center mb-6">
                    <img src="/favicon.svg" alt="ReBit Admin Core" width="64" height="64" class="mb-3" />
                    <h2 class="text-h4 text-secondary mt-4">Вход в ReBit Admin Core</h2>
                    <p class="text-lightText mt-2">Введите email и пароль</p>
                  </div>
                  <AuthLogin />
                </v-card-text>
              </v-card>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </v-col>
  </v-row>
</template>

<style lang="scss">
.loginBox {
  max-width: 475px;
  margin: 0 auto;
}
</style>
