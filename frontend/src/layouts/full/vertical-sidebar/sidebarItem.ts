import { DashboardIcon, UsersIcon, SettingsIcon } from 'vue-tabler-icons';

export interface menu {
  id?: string;
  header?: string;
  title?: string;
  icon?: object;
  to?: string;
  getURL?: boolean;
  divider?: boolean;
  chip?: string;
  chipColor?: string;
  chipVariant?: string;
  chipIcon?: string;
  children?: menu[];
  disabled?: boolean;
  type?: string;
  subCaption?: string;
}

const sidebarItem: menu[] = [
  {
    id: 'dashboard',
    title: 'Дашборд',
    icon: DashboardIcon,
    to: '/dashboard'
  },
  {
    id: 'users',
    title: 'Пользователи',
    icon: UsersIcon,
    to: '/users'
  },
  {
    id: 'account',
    title: 'Настройки аккаунта',
    icon: SettingsIcon,
    to: '/account/settings'
  }
];

export default sidebarItem;