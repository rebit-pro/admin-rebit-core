import { DashboardIcon, ArrowsExchangeIcon, WalletIcon, UserCircleIcon } from 'vue-tabler-icons';

export interface menu {
  header?: string;
  title?: string;
  icon?: object;
  to?: string;
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

const horizontalItems: menu[] = [
  {
    title: 'Дашборд',
    icon: DashboardIcon,
    to: '/dashboard'
  },
  {
    title: 'P2P Стакан',
    icon: ArrowsExchangeIcon,
    to: '/orderbook'
  },
  {
    title: 'Кошелёк',
    icon: WalletIcon,
    children: [
      { title: 'Балансы', to: '/wallet/balances' },
      { title: 'Транзакции', to: '/wallet/transactions' }
    ]
  },
  {
    title: 'Профиль',
    icon: UserCircleIcon,
    children: [
      { title: 'Мой профиль', to: '/profile' },
      { title: 'Подключение Bybit', to: '/profile/api-connection' }
    ]
  }
];

export default horizontalItems;
