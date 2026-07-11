import { createRouter, createWebHistory } from 'vue-router';
import MainRoutes from './MainRoutes';
import PublicRoutes from './PublicRoutes';
import { useAuthStore } from '@/stores/auth';

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/:pathMatch(.*)*',
      redirect: '/error',
      meta: {
        title: 'Страница не найдена',
        description: 'Запрашиваемая страница не найдена на ReBit Admin Core.'
      }
    },
    MainRoutes,
    PublicRoutes
  ]
});

const publicPages = ['/', '/login', '/register', '/error'];

router.beforeEach(async (to, _from, next) => {
  const auth = useAuthStore();
  auth.restoreSession();

  const isPublicPage = publicPages.includes(to.path);
  const authRequired = !isPublicPage && to.matched.some((record) => true === record.meta['requiresAuth']);

  if (authRequired && !auth.isAuthenticated) {
    auth.returnUrl = to.fullPath;
    return next('/login');
  }

  if (auth.isAuthenticated && (to.path === '/' || to.path === '/login' || to.path === '/register')) {
    return next('/dashboard');
  }

  next();
});