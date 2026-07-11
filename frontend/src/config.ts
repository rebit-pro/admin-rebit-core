import { ThemeMode } from '@/types/themeTypes/ThemeMode';

export type ConfigProps = {
  Sidebar_drawer: boolean;
  Customizer_drawer: boolean;
  mini_sidebar: boolean;
  setHorizontalLayout: boolean;
  actTheme: string;
  themeMode: ThemeMode;
  fontTheme: string;
  inputBg: boolean;
  boxed: boolean;
  isRtl: boolean;
};

const config: ConfigProps = {
  Sidebar_drawer: true,
  Customizer_drawer: false,
  mini_sidebar: false,
  setHorizontalLayout: false, // Horizontal layout
  actTheme: 'PurpleTheme',
  themeMode: ThemeMode.Light,
  fontTheme: 'Roboto',
  inputBg: false,
  boxed: false,
  isRtl: false
};

export default config;
