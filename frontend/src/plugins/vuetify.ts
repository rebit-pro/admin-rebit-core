import { createVuetify } from 'vuetify';
import '@mdi/font/css/materialdesignicons.css';
import { aliases, mdi } from 'vuetify/iconsets/mdi';
import { icons } from './mdi-icon';
import { PurpleTheme, GreenTheme, PinkTheme, YellowTheme, SeaGreenTheme, OliveGreenTheme, SpeechBlueTheme } from '@/theme/LightTheme';
import {
  DarkPurpleTheme,
  DarkGreenTheme,
  DarkSpeechBlueTheme,
  DarkOliveGreenTheme,
  DarkPinkTheme,
  DarkYellowTheme,
  DarkSeaGreenTheme
} from '@/theme/DarkTheme';
import { createVueI18nAdapter } from 'vuetify/locale/adapters/vue-i18n';
import { createI18n, useI18n } from 'vue-i18n';
import type { I18n } from 'vue-i18n';
import { messages } from '@/utils/locales/messages';

export const i18n = createI18n({
  legacy: false as const,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages
});

// vue-i18n возвращает union I18n<..., true> | I18n<..., false> несмотря на legacy: false as const.
// Точечный каст i18n для совместимости с VueI18nAdapterParams.
const i18nInstance = i18n as unknown as I18n<Record<string, unknown>, Record<string, never>, Record<string, never>, string, false>;

export default createVuetify({
  locale: {
    adapter: createVueI18nAdapter({ i18n: i18nInstance, useI18n })
  },
  icons: {
    defaultSet: 'mdi',
    aliases: {
      ...aliases,
      ...icons
    },
    sets: {
      mdi
    }
  },
  theme: {
    defaultTheme: 'PurpleTheme',
    themes: {
      PurpleTheme,
      GreenTheme,
      PinkTheme,
      YellowTheme,
      SeaGreenTheme,
      OliveGreenTheme,
      SpeechBlueTheme,
      DarkPurpleTheme,
      DarkGreenTheme,
      DarkSpeechBlueTheme,
      DarkOliveGreenTheme,
      DarkPinkTheme,
      DarkYellowTheme,
      DarkSeaGreenTheme
    }
  },
  defaults: {
    VBtn: {
      color: 'secondary',
      variant: 'flat',
      rounded: 'lg'
    },
    VCard: {
      rounded: 'md'
    },
    VTextField: {
      rounded: 'lg'
    },
    VTooltip: {
      location: 'top'
    }
  }
});
