import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createHead } from '@unhead/vue/client';
import App from './App.vue';
import { router } from './router';
import { useAuthStore } from '@/stores/auth';
import vuetify, { i18n } from './plugins/vuetify';
import '@/scss/style.scss';
import { PerfectScrollbarPlugin } from 'vue3-perfect-scrollbar';
import VueTablerIcons from 'vue-tabler-icons';
import { initializeMockRuntime } from '@/mocks/runtime';

initializeMockRuntime();

const app = createApp(App);
const head = createHead();
const pinia = createPinia();

app.use(head);
app.use(pinia);

useAuthStore(pinia).restoreSession();

app.use(router);
app.use(PerfectScrollbarPlugin);
app.use(VueTablerIcons);
app.use(i18n);
app.use(vuetify).mount('#app');
