<script setup lang="ts">
import { computed, ref } from 'vue';
import { useCustomizerStore } from '@/stores/customizer';
import { ThemeMode } from '@/types/themeTypes/ThemeMode';
import { ChecksIcon, ColorSwatchIcon, TextSizeIcon, MoonIcon, SunIcon, CpuIcon } from 'vue-tabler-icons';

const customizer = useCustomizerStore();

const themeColors = ref([
  { name: 'PurpleTheme', bg: 'themeBluePurple' },
  { name: 'GreenTheme', bg: 'themeGreenDark' },
  { name: 'PinkTheme', bg: 'themePink' },
  { name: 'YellowTheme', bg: 'themeYellow' },
  { name: 'SeaGreenTheme', bg: 'themeSeaGreen' },
  { name: 'OliveGreenTheme', bg: 'themeOliveGreen' },
  { name: 'SpeechBlueTheme', bg: 'themeSpeechBlue' }
]);

const currentThemeColors = computed(() => themeColors.value);
const fontFamily = ref(['Roboto', 'Poppins', 'Inter']);
const tab = ref(null);

function handleThemeSelect(themeName: string) {
  customizer.SET_THEME(themeName);
}

function clearOptions() {
  customizer.SET_THEME('PurpleTheme');
  customizer.SET_THEME_MODE(ThemeMode.Light);
  customizer.inputBg = false;
  customizer.boxed = false;
  customizer.fontTheme = 'Roboto';
}
</script>

<template>
  <v-navigation-drawer v-model="customizer.Customizer_drawer" app temporary elevation="10" location="end" width="350">
    <PerfectScrollbar style="height: 100%" :options="{ suppressScrollX: true }">
      <div>
        <v-row class="ma-0">
          <v-col cols="12" class="pa-0">
            <div class="pa-5 d-flex justify-space-between align-center">
              <div class="text-subtitle-1 font-weight-medium">Настройки темы</div>
              <div>
                <v-btn color="error" variant="outlined" size="small" class="me-2" @click="clearOptions">Сброс</v-btn>
                <v-btn
                  variant="text"
                  icon="$close"
                  color="lightText"
                  density="compact"
                  aria-label="закрыть"
                  @click.stop="customizer.SET_CUSTOMIZER_DRAWER(!customizer.Customizer_drawer)"
                />
              </div>
            </div>
            <v-divider />
          </v-col>
          <v-col cols="12" class="pa-0">
            <v-tabs v-model="tab" color="primary" bg-color="lightprimary" grow height="55">
              <v-tab value="one" max-height="55" aria-label="цвета">
                <ColorSwatchIcon />
              </v-tab>
              <v-tab value="two" max-height="55" aria-label="шрифты">
                <TextSizeIcon />
              </v-tab>
            </v-tabs>
            <v-window v-model="tab">
              <v-window-item value="one">
                <v-divider />
                <!-- Режим темы -->
                <div class="d-flex justify-space-between align-center pa-5">
                  <div class="text-subtitle-1 font-weight-medium">Режим</div>
                  <div>
                    <v-radio-group v-model="customizer.themeMode" class="custom-radio-icon custom-radio ma-n2" hide-details>
                      <v-radio class="ma-2 text-center" color="primary" label="Светлая" :value="ThemeMode.Light">
                        <SunIcon class="text-warning" size="20" />
                      </v-radio>
                      <v-radio class="ma-2 text-center" color="primary" label="Тёмная" :value="ThemeMode.Dark">
                        <MoonIcon class="text-dark" size="20" />
                      </v-radio>
                      <v-radio class="ma-2 text-center" color="primary" label="Система" :value="ThemeMode.System">
                        <CpuIcon class="text-dark" size="20" />
                      </v-radio>
                    </v-radio-group>
                  </div>
                </div>
                <v-divider />
                <!-- Цвета -->
                <v-card-item class="py-5">
                  <v-card-title class="text-subtitle-1 font-weight-medium mb-2">Цветовая палитра</v-card-title>
                  <v-card-text class="pa-0">
                    <v-item-group v-model="customizer.actTheme" mandatory>
                      <div class="d-flex flex-wrap">
                        <v-avatar
                          v-for="theme in currentThemeColors"
                          :key="theme.name"
                          :class="[theme.bg, 'me-2 cursor-pointer ma-2', { 'selected-theme': customizer.baseTheme === theme.name }]"
                          rounded="circle"
                          size="40"
                          @click="handleThemeSelect(theme.name)"
                        >
                          <ChecksIcon v-if="customizer.baseTheme === theme.name" color="white" />
                        </v-avatar>
                      </div>
                    </v-item-group>
                  </v-card-text>
                </v-card-item>
                <v-divider />
                <!-- Фон полей ввода -->
                <div class="d-flex justify-space-between align-center pa-5">
                  <div class="text-subtitle-1 font-weight-medium">Фон полей ввода</div>
                  <v-switch v-model="customizer.inputBg" color="primary" hide-details density="compact" />
                </div>
                <v-divider />
                <!-- Ширина контента -->
                <div class="d-flex justify-space-between align-center pa-5">
                  <div class="text-subtitle-1 font-weight-medium">Компактная ширина</div>
                  <v-switch v-model="customizer.boxed" color="primary" hide-details density="compact" />
                </div>
                <v-divider />
              </v-window-item>
              <v-window-item value="two">
                <!-- Шрифт -->
                <v-card-item class="py-5">
                  <v-card-title class="text-subtitle-1 font-weight-medium mb-4">Шрифт</v-card-title>
                  <v-card-text class="pa-0">
                    <v-radio-group v-model="customizer.fontTheme" hide-details class="custom-font">
                      <v-radio v-for="font in fontFamily" :key="font" :label="font" :value="font" color="primary" class="mb-5" />
                    </v-radio-group>
                  </v-card-text>
                </v-card-item>
              </v-window-item>
            </v-window>
          </v-col>
        </v-row>
      </div>
    </PerfectScrollbar>
  </v-navigation-drawer>
</template>

<style lang="scss">
.custom-radio {
  .v-selection-control-group {
    flex-direction: row;
    .v-selection-control {
      width: 48px;
      height: 48px;
      align-items: center;
      justify-content: center;
      flex: unset;
      border: 2px solid rgba(var(--v-theme-borderLight), 0.36);
      border-radius: 4px;
      &.v-selection-control--dirty {
        border: 2px solid rgba(var(--v-theme-primary), 1);
      }
      .v-selection-control__wrapper {
        .v-selection-control__input {
          opacity: 0;
        }
        img,
        .icon-tabler {
          position: absolute;
        }
      }
      .v-label {
        width: unset;
        height: unset;
        font-size: 0;
      }
    }
  }
}
.input-bg {
  background-color: rgb(var(--v-theme-gray100)) !important;
}
.custom-font {
  .v-selection-control-group {
    .v-selection-control__wrapper {
      display: none;
    }
    .v-selection-control {
      border: 2px solid rgba(var(--v-theme-borderLight), 0.36);
      outline: 6px solid rgba(var(--v-theme-borderLight), 0.1);
      border-radius: 4px;
      margin: 6px;
      padding: 12px 16px;
      &.v-selection-control--dirty {
        border: 1px solid rgba(var(--v-theme-primary), 1);
        outline: 6px solid rgba(var(--v-theme-primary), 0.1);
      }
    }
  }
}

.selected-theme {
  border: 3px solid rgba(var(--v-theme-primary), 1) !important;
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.2);
}
</style>
