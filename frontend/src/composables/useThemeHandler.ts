import { watch } from 'vue';
import { useTheme } from 'vuetify';
import { useCustomizerStore } from '@/stores/customizer';
import { ThemeMode } from '@/types/themeTypes/ThemeMode';

export function useThemeHandler() {
  const theme = useTheme();
  const customizerStore = useCustomizerStore();

  watch(
    [() => customizerStore.themeMode, () => customizerStore.baseTheme, () => customizerStore.isDarkMode],
    () => {
      let targetTheme = customizerStore.actTheme;

      switch (customizerStore.themeMode) {
        case ThemeMode.Dark: {
          targetTheme = customizerStore.baseTheme.startsWith('Dark') ? customizerStore.baseTheme : `Dark${customizerStore.baseTheme}`;
          break;
        }
        case ThemeMode.Light: {
          targetTheme = customizerStore.baseTheme.replace('Dark', '');
          break;
        }
        case ThemeMode.System: {
          targetTheme = customizerStore.isDarkMode
            ? customizerStore.baseTheme.startsWith('Dark')
              ? customizerStore.baseTheme
              : `Dark${customizerStore.baseTheme}`
            : customizerStore.baseTheme.replace('Dark', '');
          break;
        }
      }

      theme.change(targetTheme);
    },
    { immediate: true }
  );

  return {
    theme
  };
}
