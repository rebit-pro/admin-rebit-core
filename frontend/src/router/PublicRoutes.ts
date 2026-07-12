const PublicRoutes = {
  path: '/',
  component: () => import('@/layouts/blank/BlankLayout.vue'),
  meta: {
    requiresAuth: false
  },
  children: [
    {
      name: 'Root',
      path: '/',
      redirect: '/login'
    },
    {
      name: 'Login',
      path: '/login',
      component: () => import('@/views/authentication/LoginPage.vue'),
      meta: {
        title: 'Вход',
        description: 'Войдите в аккаунт ReBit Admin Core.'
      }
    },
    {
      name: 'Register',
      path: '/register',
      component: () => import('@/views/authentication/RegisterPage.vue'),
      meta: {
        title: 'Регистрация',
        description: 'Создайте аккаунт ReBit Admin Core.'
      }
    },
    {
      name: 'Error 404',
      path: '/error',
      component: () => import('@/views/pages/maintenance/error/Error404Page.vue'),
      meta: {
        title: 'Страница не найдена',
        description: 'Запрашиваемая страница не найдена.'
      }
    }
  ]
};

export default PublicRoutes;
