import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import config from '@/config';
import { DirAttrSet } from '@/utils/utils';
import { ThemeMode } from '@/types/themeTypes/ThemeMode';

export const useCustomizerStore = defineStore('customizer', () => {
  const isMobile = 'undefined' !== typeof window && window.innerWidth < 1280;
  const Sidebar_drawer = ref(isMobile ? false : config.Sidebar_drawer);
  const Customizer_drawer = ref(config.Customizer_drawer);
  const mini_sidebar = ref(config.mini_sidebar);
  const setHorizontalLayout = ref(config.setHorizontalLayout);
  const baseTheme = ref<string>(config.actTheme.replace('Dark', ''));
  const themeMode = ref<ThemeMode>(config.themeMode);
  const systemPreference = ref(false);
  const fontTheme = ref(config.fontTheme);
  const inputBg = ref(config.inputBg);
  const boxed = ref(config.boxed);
  const isRtl = ref(config.isRtl);

  const isDarkMode = computed(() => {
    switch (themeMode.value) {
      case ThemeMode.Dark: {
        return true;
      }
      case ThemeMode.System: {
        return systemPreference.value;
      }
      default: {
        return false;
      }
    }
  });

  // Computed actTheme - derives from baseTheme and mode
  const actTheme = computed(() => {
    if (isDarkMode.value) {
      return baseTheme.value.startsWith('Dark') ? baseTheme.value : `Dark${baseTheme.value}`;
    } else {
      return baseTheme.value.replace('Dark', '');
    }
  });

  // Setup system media query listener
  let mediaQueryListener: ((e: MediaQueryListEvent) => void) | null = null;
  if (typeof window !== 'undefined') {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    systemPreference.value = mediaQuery.matches;

    mediaQueryListener = (e: MediaQueryListEvent) => {
      systemPreference.value = e.matches;
    };
    mediaQuery.addEventListener('change', mediaQueryListener);
  }

  function SET_SIDEBAR_DRAWER() {
    Sidebar_drawer.value = !Sidebar_drawer.value;
  }

  function SET_CUSTOMIZER_DRAWER(payload: boolean) {
    Customizer_drawer.value = payload;
  }

  function SET_MINI_SIDEBAR(payload: boolean) {
    mini_sidebar.value = payload;
  }

  function SET_INPUTBG(payload: boolean) {
    inputBg.value = payload;
  }

  function SET_BOXED(payload: boolean) {
    boxed.value = payload;
  }

  function SET_LAYOUT(payload: boolean) {
    setHorizontalLayout.value = payload;
  }

  function SET_THEME(payload: string) {
    // Extract base theme name (remove 'Dark' prefix if present)
    baseTheme.value = payload.replace('Dark', '');
  }

  function SET_THEME_MODE(payload: ThemeMode) {
    themeMode.value = payload;
    // Only reset to default if no theme is set
    if (!baseTheme.value) {
      baseTheme.value = 'PurpleTheme';
    }
  }

  // Cleanup function for store disposal
  function $dispose() {
    if (typeof window !== 'undefined' && mediaQueryListener) {
      window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', mediaQueryListener);
    }
  }

  function SET_FONT(payload: string) {
    fontTheme.value = payload;
  }

  function SET_DIRECTION(dir: 'ltr' | 'rtl') {
    isRtl.value = dir === 'rtl';
    DirAttrSet(dir);
  }

  return {
    Sidebar_drawer,
    Customizer_drawer,
    mini_sidebar,
    setHorizontalLayout,
    actTheme,
    baseTheme,
    themeMode,
    isDarkMode,
    fontTheme,
    inputBg,
    isRtl,
    boxed,
    SET_THEME,
    SET_THEME_MODE,
    $dispose,
    SET_SIDEBAR_DRAWER,
    SET_CUSTOMIZER_DRAWER,
    SET_MINI_SIDEBAR,
    SET_LAYOUT,
    SET_FONT,
    SET_DIRECTION,
    SET_INPUTBG,
    SET_BOXED
  };
});
