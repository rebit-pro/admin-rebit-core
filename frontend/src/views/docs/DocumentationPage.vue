<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import PublicTopBar from '@/components/public/PublicTopBar.vue';

const isAuthenticated = computed(() => useAuthStore().isAuthenticated);
const heroPrimaryLink = computed(() => (isAuthenticated.value ? '/profile/api-connection' : '/login'));
const heroPrimaryLabel = computed(() => (isAuthenticated.value ? 'Перейти к API-подключению' : 'Войти и подключить API'));
const tipsPrimaryLink = computed(() => (isAuthenticated.value ? '/dashboard' : '/register'));
const tipsPrimaryLabel = computed(() => (isAuthenticated.value ? 'Открыть кабинет' : 'Создать аккаунт'));
const tipsSecondaryLink = computed(() => (isAuthenticated.value ? '/profile/api-connection' : '/login'));
const tipsSecondaryLabel = computed(() => (isAuthenticated.value ? 'Проверить API-подключение' : 'Войти'));

const heroHighlights = [
  {
    value: 'API',
    title: 'Подключение без лишних шагов',
    description: 'Документация сразу ведёт к настройке Bybit API и ключевым рабочим разделам.',
    icon: 'mdi-key-chain-variant'
  },
  {
    value: 'P2P',
    title: 'Ориентир на рабочие сценарии',
    description: 'Только нужные действия: подключение, сделки, отчёты, уведомления и контроль рисков.',
    icon: 'mdi-swap-horizontal-circle-outline'
  },
  {
    value: '24/7',
    title: 'Быстрый вход в процесс',
    description: 'Новые сотрудники и трейдеры быстрее включаются в работу по единому сценарию.',
    icon: 'mdi-radar'
  }
];

const startActions = computed(() => [
  {
    title: 'Создайте аккаунт в приложении',
    description: 'Зарегистрируйтесь, чтобы получить доступ к кабинету, подключению API и рабочим разделам платформы.',
    icon: 'mdi-account-plus-outline'
  },
  {
    title: 'Подготовьте Bybit API-ключ',
    description: 'Сразу определите режим: testnet для безопасной проверки сценариев или mainnet для реальной работы.',
    icon: 'mdi-key-outline'
  },
  {
    title: 'Подключите ключ в профиле',
    description: 'Перейдите в раздел подключения Bybit API, вставьте ключ и секрет, а затем выполните верификацию.',
    icon: 'mdi-connection'
  }
]);

const apiSteps = [
  {
    step: '01',
    title: 'Откройте API Management в Bybit',
    description: 'Авторизуйтесь в Bybit и перейдите в управление API-ключами. Для тестового режима используйте testnet-кабинет.'
  },
  {
    step: '02',
    title: 'Создайте отдельный ключ под ReBit Admin Core',
    description: 'Не используйте общий ключ для других интеграций. Отдельный ключ проще контролировать, отзывать и проверять.'
  },
  {
    step: '03',
    title: 'Выдайте только нужные права',
    description: 'Достаточно чтения и прав для торговых сценариев. Разрешение на вывод средств включать не нужно.'
  },
  {
    step: '04',
    title: 'Сохраните API Key и Secret',
    description: 'Секрет показывается только при создании. Сразу сохраните его и затем вставьте в форму подключения внутри приложения.'
  }
];

const permissions: Array<{
  title: string;
  description: string;
  color: 'info' | 'success' | 'warning';
}> = [
  {
    title: 'Рекомендуемый режим для старта',
    description: 'Начинайте с testnet: так проще проверить статусы, сценарии и интерфейс без риска для боевых сделок.',
    color: 'info'
  },
  {
    title: 'Права доступа',
    description: 'Оставляйте только необходимые разрешения на чтение и торговые операции. Не выдавайте вывод средств.',
    color: 'success'
  },
  {
    title: 'Безопасность ключа',
    description: 'Если используете IP-ограничения, заранее добавьте разрешённые адреса вашей инфраструктуры.',
    color: 'warning'
  }
];

const sections = [
  {
    title: 'Дашборд',
    description: 'Краткий обзор торговой активности, состояния процессов и быстрый переход к важным сценариям.',
    icon: 'mdi-view-dashboard-outline'
  },
  {
    title: 'P2P Стакан',
    description: 'Просмотр актуальных заявок, фильтрация по параметрам и быстрый анализ рынка по выбранной паре.',
    icon: 'mdi-chart-timeline-variant'
  },
  {
    title: 'Сделки',
    description: 'Центральный раздел работы: статусы, детали сделки, чат, действия и информация о контрагенте.',
    icon: 'mdi-swap-horizontal-bold'
  },
  {
    title: 'Объявления',
    description: 'Управление собственными объявлениями и контроль сценариев публикации и обработки спроса.',
    icon: 'mdi-bullhorn-outline'
  },
  {
    title: 'Скрипты чата',
    description: 'Подготовка шаблонов и сценариев, которые помогают стандартизировать коммуникацию в типовых кейсах.',
    icon: 'mdi-script-text-outline'
  },
  {
    title: 'Кошелёк и отчёты',
    description: 'Балансы, история транзакций и отчёты по движению средств для контроля прибыли и оборотов.',
    icon: 'mdi-wallet-outline'
  },
  {
    title: 'Профиль и API',
    description: 'Настройка профиля, подключение Bybit API, верификация и контроль статуса интеграции.',
    icon: 'mdi-account-cog-outline'
  },
  {
    title: 'Уведомления и контроль событий',
    description: 'Сделки и важные изменения можно выносить в удобные каналы, чтобы быстрее реагировать на события.',
    icon: 'mdi-bell-ring-outline'
  }
];

const tips = [
  'Сначала подключайте testnet-ключ и проверяйте основные сценарии на тестовой среде.',
  'Для mainnet используйте отдельный ключ с минимально достаточными правами.',
  'Регулярно проверяйте статус API-подключения в профиле и переподтверждайте доступ при изменениях.',
  'Работайте со сделками из карточки деталки: там уже есть статус, чат, контрагент и нужные действия.',
  'Используйте отчёты и историю кошелька для контроля прибыли, оборотов и повторяющихся отклонений.'
];
</script>

<template>
  <div class="documentation-page">
    <section class="documentation-page__hero">
      <v-container class="documentation-page__hero-container">
        <PublicTopBar />

        <v-row align="center" class="documentation-page__hero-row">
          <v-col cols="12" md="7" lg="6">
            <div class="documentation-page__hero-content">
              <div class="text-overline documentation-page__hero-overline">Руководство пользователя</div>
              <h1 class="documentation-page__hero-title">Документация ReBit Admin Core</h1>
              <p class="documentation-page__hero-text">
                В одном месте собраны базовые шаги для старта: как подготовить API-ключ Bybit, подключить его в приложении и быстро начать
                работать со сделками, балансами, отчётами и уведомлениями.
              </p>
              <div class="documentation-page__hero-actions">
                <v-btn
                  class="documentation-page__responsive-button"
                  color="secondary"
                  variant="flat"
                  rounded="xl"
                  size="large"
                  :to="heroPrimaryLink"
                >
                  {{ heroPrimaryLabel }}
                </v-btn>
                <v-btn class="documentation-page__responsive-button" color="white" variant="outlined" rounded="xl" size="large" to="/">
                  Вернуться на главную
                </v-btn>
              </div>
            </div>
          </v-col>

          <v-col cols="12" md="5" lg="6">
            <v-card class="documentation-page__hero-panel" rounded="lg" elevation="0">
              <div class="documentation-page__hero-panel-grid">
                <v-card
                  v-for="highlight in heroHighlights"
                  :key="highlight.title"
                  class="documentation-page__hero-metric"
                  rounded="lg"
                  elevation="0"
                >
                  <v-card-text class="pa-5">
                    <div class="documentation-page__hero-metric-header">
                      <v-avatar size="42" color="secondary" variant="tonal">
                        <v-icon>{{ highlight.icon }}</v-icon>
                      </v-avatar>
                      <div class="documentation-page__hero-metric-value">{{ highlight.value }}</div>
                    </div>
                    <div class="documentation-page__hero-metric-title">{{ highlight.title }}</div>
                    <p class="documentation-page__text documentation-page__text--dark">{{ highlight.description }}</p>
                  </v-card-text>
                </v-card>
              </div>

              <v-sheet class="documentation-page__hero-note" rounded="lg">
                <div class="text-subtitle-2 font-weight-bold mb-2">Что вы получите на этой странице</div>
                <div class="documentation-page__hero-note-list">
                  <div class="documentation-page__hero-note-item">
                    <v-icon size="18" color="secondary">mdi-check-circle-outline</v-icon>
                    <span>Пошаговую инструкцию по API-ключу Bybit</span>
                  </div>
                  <div class="documentation-page__hero-note-item">
                    <v-icon size="18" color="secondary">mdi-check-circle-outline</v-icon>
                    <span>Краткую карту разделов и рабочих сценариев</span>
                  </div>
                  <div class="documentation-page__hero-note-item">
                    <v-icon size="18" color="secondary">mdi-check-circle-outline</v-icon>
                    <span>Практические рекомендации по безопасной работе</span>
                  </div>
                </div>
              </v-sheet>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </section>

    <section class="documentation-page__section documentation-page__section--light">
      <v-container>
        <div class="documentation-page__section-heading">
          <div class="text-overline text-secondary font-weight-bold">Быстрый старт</div>
          <h2 class="documentation-page__section-title">Что нужно подготовить перед началом работы</h2>
        </div>

        <v-row>
          <v-col v-for="action in startActions" :key="action.title" cols="12" md="4">
            <v-card class="documentation-page__start-card" rounded="lg" elevation="0">
              <v-card-item class="documentation-page__card-header px-5 py-4">
                <template #prepend>
                  <v-avatar size="42" color="secondary" variant="tonal">
                    <v-icon>{{ action.icon }}</v-icon>
                  </v-avatar>
                </template>
                <v-card-title class="text-subtitle-1 font-weight-bold">{{ action.title }}</v-card-title>
              </v-card-item>
              <v-divider />
              <v-card-text class="pa-5">
                <p class="documentation-page__text">{{ action.description }}</p>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </section>

    <section class="documentation-page__section documentation-page__section--white">
      <v-container>
        <div class="documentation-page__section-heading">
          <div class="text-overline text-secondary font-weight-bold">API Bybit</div>
          <h2 class="documentation-page__section-title">Как получить API-ключ</h2>
          <p class="documentation-page__section-description">
            Следуйте этим шагам, чтобы подключение в приложении прошло без лишних вопросов и без избыточных прав доступа.
          </p>
        </div>

        <div class="documentation-page__timeline">
          <v-card v-for="step in apiSteps" :key="step.step" class="documentation-page__timeline-card" rounded="lg" elevation="0">
            <v-card-text class="pa-5">
              <div class="documentation-page__timeline-card-layout">
                <div class="documentation-page__timeline-step">{{ step.step }}</div>
                <div>
                  <div class="text-subtitle-1 font-weight-bold mb-2">{{ step.title }}</div>
                  <p class="documentation-page__text">{{ step.description }}</p>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </div>

        <v-row class="mt-6">
          <v-col v-for="permission in permissions" :key="permission.title" cols="12" md="4">
            <v-card class="documentation-page__notice-card" rounded="lg" elevation="0">
              <v-card-text class="pa-5">
                <div class="documentation-page__notice-card-layout">
                  <v-avatar size="42" :color="permission.color" variant="tonal" class="flex-shrink-0">
                    <v-icon>
                      {{
                        'info' === permission.color
                          ? 'mdi-information-outline'
                          : 'success' === permission.color
                            ? 'mdi-check-circle-outline'
                            : 'mdi-shield-alert-outline'
                      }}
                    </v-icon>
                  </v-avatar>
                  <div>
                    <div class="font-weight-bold mb-2">{{ permission.title }}</div>
                    <div class="text-body-2 text-medium-emphasis">{{ permission.description }}</div>
                  </div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </section>

    <section class="documentation-page__section documentation-page__section--light">
      <v-container>
        <div class="documentation-page__section-heading">
          <div class="text-overline text-secondary font-weight-bold">Карта приложения</div>
          <h2 class="documentation-page__section-title">Как пользоваться разделами</h2>
          <p class="documentation-page__section-description">
            После подключения API можно выстроить рабочий процесс вокруг нескольких ключевых экранов. Ниже — краткая карта разделов.
          </p>
        </div>

        <v-row>
          <v-col v-for="section in sections" :key="section.title" cols="12" sm="6" lg="3">
            <v-card class="documentation-page__section-card" rounded="lg" elevation="0">
              <v-card-item class="documentation-page__card-header px-5 py-4 documentation-page__card-header--compact">
                <template #prepend>
                  <v-avatar size="42" color="secondary" variant="tonal">
                    <v-icon>{{ section.icon }}</v-icon>
                  </v-avatar>
                </template>
                <v-card-title class="text-subtitle-1 font-weight-bold">{{ section.title }}</v-card-title>
              </v-card-item>
              <v-divider />
              <v-card-text class="pa-5">
                <p class="documentation-page__text">{{ section.description }}</p>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </section>

    <section class="documentation-page__section documentation-page__section--dark">
      <v-container>
        <v-card class="documentation-page__tips-card" rounded="lg" elevation="0">
          <v-card-item class="documentation-page__card-header px-5 py-4">
            <template #prepend>
              <v-avatar size="42" color="secondary" variant="tonal">
                <v-icon>mdi-lightbulb-on-outline</v-icon>
              </v-avatar>
            </template>
            <v-card-title class="text-h6 font-weight-bold">Практические советы</v-card-title>
            <v-card-subtitle>Рекомендации для безопасной и стабильной работы</v-card-subtitle>
          </v-card-item>
          <v-divider />
          <v-card-text class="pa-5 pa-md-6">
            <div class="documentation-page__tips-list">
              <div v-for="tip in tips" :key="tip" class="documentation-page__tip-item">
                <v-icon size="20" color="secondary">mdi-check-circle-outline</v-icon>
                <span>{{ tip }}</span>
              </div>
            </div>
            <div class="documentation-page__tips-actions">
              <v-btn
                class="documentation-page__responsive-button"
                color="secondary"
                variant="flat"
                rounded="xl"
                size="large"
                :to="tipsPrimaryLink"
              >
                {{ tipsPrimaryLabel }}
              </v-btn>
              <v-btn
                class="documentation-page__responsive-button"
                color="secondary"
                variant="outlined"
                rounded="xl"
                size="large"
                :to="tipsSecondaryLink"
              >
                {{ tipsSecondaryLabel }}
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-container>
    </section>
  </div>
</template>

<style scoped lang="scss">
.documentation-page {
  background: linear-gradient(180deg, #0f172a 0%, #f8fafc 22%, #f8fafc 100%);
}

.documentation-page__hero {
  color: #ffffff;
}

.documentation-page__hero-container {
  padding-top: 0;
  padding-bottom: 40px;
}

.documentation-page__hero-row {
  min-height: min(680px, calc(100vh - 48px));
  padding-bottom: 32px;
}

.documentation-page__hero-content {
  max-width: 680px;
  padding-top: 24px;
  padding-bottom: 24px;
}

.documentation-page__hero-overline {
  color: rgba(255, 255, 255, 0.74);
  letter-spacing: 0.08em;
}

.documentation-page__hero-title {
  margin: 0 0 18px;
  font-size: clamp(2.15rem, 3.1vw, 3.2rem);
  line-height: 1.12;
}

.documentation-page__hero-text {
  max-width: 640px;
  margin: 0 0 24px;
  font-size: 1rem;
  line-height: 1.72;
  color: rgba(255, 255, 255, 0.8);
}

.documentation-page__hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  padding-bottom: 8px;
}

.documentation-page__hero-panel {
  padding: 20px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  color: #0f172a;
}

.documentation-page__hero-panel-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.documentation-page__hero-metric {
  height: 100%;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: linear-gradient(180deg, #ffffff 0%, rgba(30, 136, 229, 0.03) 100%);
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
}

.documentation-page__hero-metric-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.documentation-page__hero-metric-value {
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1;
  color: #0f172a;
}

.documentation-page__hero-metric-title {
  margin-bottom: 8px;
  font-size: 0.98rem;
  font-weight: 700;
  line-height: 1.4;
  color: #0f172a;
}

.documentation-page__hero-note {
  padding: 16px 18px;
  border: 1px solid rgba(30, 136, 229, 0.12);
  background:
    radial-gradient(circle at top right, rgba(94, 53, 177, 0.06), transparent 40%),
    linear-gradient(135deg, rgba(30, 136, 229, 0.06), #ffffff);
}

.documentation-page__hero-note-list {
  display: grid;
  gap: 10px;
}

.documentation-page__hero-note-item {
  display: inline-flex;
  align-items: flex-start;
  gap: 10px;
  line-height: 1.55;
}

.documentation-page__section {
  padding: 64px 0;
}

.documentation-page__section--light {
  background: #f8fafc;
}

.documentation-page__section--white {
  background: #ffffff;
}

.documentation-page__section--dark {
  background: linear-gradient(135deg, #0f172a 0%, #172554 100%);
  padding-top: 0;
  padding-bottom: 88px;
}

.documentation-page__section-heading {
  max-width: 760px;
  margin-bottom: 32px;
}

.documentation-page__section-title {
  margin: 0 0 16px;
  font-size: clamp(2rem, 3vw, 2.8rem);
  line-height: 1.15;
}

.documentation-page__section-description,
.documentation-page__text {
  margin: 0;
  line-height: 1.7;
  color: rgba(15, 23, 42, 0.7);
}

.documentation-page__text--dark {
  color: rgba(15, 23, 42, 0.68);
}

.documentation-page__start-card,
.documentation-page__section-card,
.documentation-page__notice-card,
.documentation-page__timeline-card,
.documentation-page__tips-card {
  height: 100%;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.documentation-page__card-header {
  min-height: 96px;
}

.documentation-page__card-header--compact {
  min-height: 84px;
}

.documentation-page__timeline {
  display: grid;
  gap: 16px;
}

.documentation-page__timeline-card-layout {
  display: grid;
  grid-template-columns: 88px minmax(0, 1fr);
  gap: 20px;
}

.documentation-page__timeline-step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 88px;
  height: 88px;
  border-radius: 24px;
  background: linear-gradient(135deg, rgba(94, 53, 177, 0.14) 0%, rgba(30, 136, 229, 0.14) 100%);
  font-size: 1.5rem;
  font-weight: 800;
  color: #5e35b1;
}

.documentation-page__alert-card {
  border-radius: 24px;
}

.documentation-page__notice-card-layout {
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

.documentation-page__tips-card {
  color: #0f172a;
}

.documentation-page__tips-list {
  display: grid;
  gap: 14px;
  margin-bottom: 28px;
}

.documentation-page__tip-item {
  display: inline-flex;
  align-items: flex-start;
  gap: 12px;
  color: rgba(15, 23, 42, 0.78);
}

.documentation-page__tips-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

@media (max-width: 1279px) {
  .documentation-page__hero-row {
    min-height: auto;
  }
}

@media (max-width: 959px) {
  .documentation-page__hero-panel-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767px) {
  .documentation-page__hero-container {
    padding-bottom: 48px;
  }

  .documentation-page__hero-title {
    font-size: 2rem;
  }

  .documentation-page__timeline-card-layout {
    grid-template-columns: 1fr;
  }

  .documentation-page__responsive-button {
    width: 100%;
  }
}
</style>
