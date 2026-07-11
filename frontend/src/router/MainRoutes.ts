const MainRoutes = {
  path: '/main',
  meta: {
    requiresAuth: true
  },
  redirect: '/dashboard',
  component: () => import('@/layouts/full/FullLayout.vue'),
  children: [
    {
      name: 'Dashboard',
      path: '/dashboard',
      component: () => import('@/views/dashboard/DashboardPage.vue'),
      meta: {
        title: 'Дашборд',
        description: 'Главный экран ReBit Admin Core.'
      }
    },
    {
      name: 'Users',
      path: '/users',
      component: () => import('@/views/users/UsersPage.vue'),
      meta: {
        title: 'Пользователи',
        description: 'Управление пользователями и ролями.'
      }
    },
    {
      name: 'AccountSettings',
      path: '/account/settings',
      component: () => import('@/views/account/AccountSettingsPage.vue'),
      meta: {
        title: 'Настройки аккаунта',
        description: 'Смена пароля, логина и email.'
      }
    }
  ]
};

export default MainRoutes;