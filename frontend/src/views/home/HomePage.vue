<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import PublicTopBar from '@/components/public/PublicTopBar.vue';

const auth = useAuthStore();

const primaryActionLabel = computed(() => (auth.isAuthenticated ? 'Перейти в кабинет' : 'Регистрация'));
const primaryActionLink = computed(() => (auth.isAuthenticated ? '/dashboard' : '/register'));

const featureCards = [
  {
    icon: 'mdi-wallet-bifold-outline',
    title: 'Контроль балансов без ручной рутины',
    description: 'Следите за доступными и заблокированными средствами, чтобы быстрее принимать решения по сделкам и управлять ликвидностью.'
  },
  {
    icon: 'mdi-swap-horizontal-bold',
    title: 'Централизованное управление сделками',
    description: 'Карточка сделки, контрагент, чат и действия собраны в одном окне без переключения между вкладками и сервисами.'
  },
  {
    icon: 'mdi-chart-line',
    title: 'Контроль прибыли через отчёты',
    description: 'Аналитика по оборотам и движению средств помогает видеть реальную эффективность торговых сценариев.'
  },
  {
    icon: 'mdi-shield-check-outline',
    title: 'Проверка безопасности контрагента',
    description: 'Сценарии и алгоритмы помогают сразу увидеть рискованные сигналы: KYC, активность, скорость и качество истории сделок.'
  },
  {
    icon: 'mdi-script-text-outline',
    title: 'Скрипты для типовых действий',
    description: 'Автоматизируйте коммуникацию и повторяемые шаги, чтобы команда работала по единым правилам и не теряла темп.'
  },
  {
    icon: 'mdi-bell-badge-outline',
    title: 'Уведомления в удобный канал',
    description: 'Получайте сигналы о новых сделках, смене статусов и важных событиях туда, где вам действительно удобно реагировать.'
  }
];

const insightCards = [
  {
    value: '1',
    title: 'единый центр управления',
    label: 'Сделки, чат, контрагент и действия собраны в одном рабочем окне.',
    icon: 'mdi-view-dashboard-outline',
    color: 'secondary'
  },
  {
    value: '24/7',
    title: 'контроль статусов и рисков',
    label: 'Вы видите балансы, сигналы безопасности и статусы без ручного мониторинга.',
    icon: 'mdi-radar',
    color: 'info'
  },
  {
    value: '0',
    title: 'лишних переключений между сервисами',
    label: 'Команда работает быстрее, когда процесс не разваливается по вкладкам и чатам.',
    icon: 'mdi-cursor-default-click-outline',
    color: 'success'
  }
];

const infographicRows = [
  {
    title: 'Контроль баланса',
    value: 86,
    caption: 'Остатки, блокировки и доступный объём видны в одном месте.',
    tone: 'secondary'
  },
  {
    title: 'Обработка сделок',
    value: 92,
    caption: 'Карточка сделки, чат и действия синхронизированы без ручной рутины.',
    tone: 'info'
  },
  {
    title: 'Сигналы безопасности',
    value: 78,
    caption: 'История контрагента, KYC и риск-факторы доступны сразу.',
    tone: 'warning'
  }
];

const infographicFlow = [
  {
    title: 'Сделка',
    icon: 'mdi-swap-horizontal'
  },
  {
    title: 'Проверка',
    icon: 'mdi-shield-check-outline'
  },
  {
    title: 'Оповещение',
    icon: 'mdi-bell-ring-outline'
  },
  {
    title: 'Отчёт',
    icon: 'mdi-chart-box-outline'
  }
];

const workflowSteps = [
  {
    step: '01',
    title: 'Подключаете API-ключи Bybit',
    description: 'Указываете testnet или mainnet ключ с нужными правами и получаете доступ к данным прямо внутри приложения.'
  },
  {
    step: '02',
    title: 'Видите сделки и балансы в одном окне',
    description: 'Не нужно собирать картину вручную: карточки, статусы, чат и кошелёк доступны централизованно.'
  },
  {
    step: '03',
    title: 'Подключаете сценарии и контроль рисков',
    description: 'Скрипты, шаблоны и алгоритмы помогают ускорить коммуникацию и своевременно отсеивать подозрительные кейсы.'
  },
  {
    step: '04',
    title: 'Получаете отчёты и уведомления',
    description: 'Результат торговли и важные события остаются под контролем за счёт отчётности и оповещений.'
  }
];

const channelChips = ['Telegram', 'Email', 'Внутренние уведомления', 'Рабочий браузер', 'Операционный журнал'];
</script>

<template>
  <div class="landing-page">
    <section class="landing-page__hero-section">
      <v-container class="landing-page__hero-container">
        <PublicTopBar />

        <v-row align="center" class="landing-page__hero-row">
          <v-col cols="12" md="7" lg="6" class="landing-page__hero-col landing-page__hero-col--main">
            <div class="landing-page__hero-copy">
              <v-chip color="white" variant="outlined" size="small" class="mb-4 landing-page__hero-chip">
                Платформа для системной P2P-работы
              </v-chip>

              <h1 class="landing-page__hero-title">Начните с ReBit Admin Core — ведите сделки быстрее и безопаснее</h1>
              <p class="landing-page__hero-text">
                Подключите Bybit API один раз и управляйте P2P-операциями из единого кабинета: контролируйте балансы, ведите сделки,
                проверяйте контрагентов, получайте уведомления и отслеживайте прибыль по отчётам без лишних переключений.
              </p>
            </div>

            <div class="landing-page__hero-actions-wrap">
              <div class="landing-page__hero-actions">
                <v-btn
                  class="landing-page__responsive-button"
                  color="secondary"
                  variant="flat"
                  size="x-large"
                  rounded="xl"
                  :to="primaryActionLink"
                >
                  {{ primaryActionLabel }}
                </v-btn>
              </div>
            </div>
          </v-col>

          <v-col cols="12" md="5" lg="6" class="landing-page__hero-col landing-page__hero-col--aside">
            <v-card class="landing-page__hero-panel" rounded="lg" elevation="0">
              <div class="landing-page__hero-panel-grid">
                <v-card v-for="card in insightCards" :key="card.title" class="landing-page__metric-card" rounded="lg" elevation="0">
                  <v-card-text class="pa-5">
                    <div class="landing-page__metric-header">
                      <v-avatar size="44" :color="card.color" variant="tonal">
                        <v-icon>{{ card.icon }}</v-icon>
                      </v-avatar>
                      <div class="landing-page__metric-value">{{ card.value }}</div>
                    </div>
                    <div class="landing-page__metric-title">{{ card.title }}</div>
                    <div class="landing-page__metric-label">{{ card.label }}</div>
                  </v-card-text>
                </v-card>
              </div>

              <v-card class="landing-page__infographic" rounded="lg" elevation="0">
                <div class="landing-page__infographic-header">
                  <div>
                    <div class="text-overline text-medium-emphasis">Инфографика пользы</div>
                    <div class="text-h6 font-weight-bold">Как ReBit Admin Core усиливает процесс</div>
                  </div>
                  <v-chip color="secondary" variant="tonal">Единый контур контроля</v-chip>
                </div>

                <div class="landing-page__infographic-layout">
                  <div class="landing-page__infographic-list">
                    <div v-for="item in infographicRows" :key="item.title" class="landing-page__infographic-item">
                      <div class="d-flex align-center justify-space-between ga-3 mb-2">
                        <div class="font-weight-bold">{{ item.title }}</div>
                        <v-chip :color="item.tone" size="small" variant="tonal">{{ item.value }}%</v-chip>
                      </div>
                      <v-progress-linear :model-value="item.value" :color="item.tone" bg-color="grey-lighten-3" rounded height="10" />
                      <div class="text-body-2 text-medium-emphasis mt-2">{{ item.caption }}</div>
                    </div>
                  </div>

                  <div class="landing-page__flow-card">
                    <div class="text-overline text-medium-emphasis mb-2">Схема обработки</div>
                    <div class="landing-page__flow-list">
                      <template v-for="(step, index) in infographicFlow" :key="step.title">
                        <div class="landing-page__flow-item">
                          <v-avatar size="48" color="secondary" variant="tonal">
                            <v-icon>{{ step.icon }}</v-icon>
                          </v-avatar>
                          <span>{{ step.title }}</span>
                        </div>
                        <div v-if="index < infographicFlow.length - 1" class="landing-page__flow-connector" />
                      </template>
                    </div>

                    <v-sheet class="landing-page__flow-note" rounded="lg">
                      Система связывает сделку, проверку, уведомление и итоговую аналитику в один непрерывный сценарий.
                    </v-sheet>
                  </div>
                </div>
              </v-card>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </section>

    <section id="benefits" class="landing-page__section landing-page__section--light landing-page__section--benefits">
      <v-container>
        <div class="landing-page__section-heading">
          <div class="text-overline text-secondary font-weight-bold">Почему это полезно</div>
          <h2 class="landing-page__section-title">Преимущества, которые закрывают ежедневные задачи P2P-команды</h2>
          <p class="landing-page__section-description">
            ReBit Admin Core помогает не просто смотреть данные, а выстраивать управляемый процесс: контролировать операции, ускорять
            реакцию на события и уменьшать количество ручных ошибок.
          </p>
        </div>

        <v-row>
          <v-col v-for="feature in featureCards" :key="feature.title" cols="12" sm="6" lg="4">
            <v-card class="landing-page__feature-card" rounded="lg" elevation="0">
              <v-card-item class="landing-page__feature-card-header px-5 py-4">
                <template #prepend>
                  <v-avatar size="42" color="secondary" variant="tonal">
                    <v-icon>{{ feature.icon }}</v-icon>
                  </v-avatar>
                </template>
                <v-card-title class="text-subtitle-1 font-weight-bold">{{ feature.title }}</v-card-title>
              </v-card-item>
              <v-divider />
              <v-card-text class="pa-5">
                <p class="landing-page__feature-text">{{ feature.description }}</p>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </section>

    <section class="landing-page__section landing-page__section--dark">
      <v-container>
        <v-row align="stretch">
          <v-col cols="12" lg="7">
            <div class="landing-page__section-heading landing-page__section-heading--light">
              <div class="text-overline font-weight-bold landing-page__section-overline--light">Как это работает</div>
              <h2 class="landing-page__section-title text-white">Сценарий работы построен вокруг скорости, прозрачности и контроля</h2>
              <p class="landing-page__section-description landing-page__section-description--light">
                Платформа помогает стандартизировать процесс — от подключения данных до реакции на сделку и анализа финансового результата.
              </p>
            </div>

            <div class="landing-page__workflow-list">
              <v-card v-for="step in workflowSteps" :key="step.step" class="landing-page__workflow-card" rounded="lg" elevation="0">
                <v-card-text class="pa-5">
                  <div class="landing-page__workflow-card-layout">
                    <div class="landing-page__workflow-step">{{ step.step }}</div>
                    <div>
                      <div class="text-subtitle-1 font-weight-bold mb-2">{{ step.title }}</div>
                      <p class="landing-page__workflow-text">{{ step.description }}</p>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </div>
          </v-col>

          <v-col cols="12" lg="5">
            <v-card class="landing-page__channels-card" rounded="lg" elevation="0">
              <v-card-item class="landing-page__channels-card-header px-5 py-4">
                <template #prepend>
                  <v-avatar size="42" color="secondary" variant="tonal">
                    <v-icon>mdi-bell-ring-outline</v-icon>
                  </v-avatar>
                </template>
                <v-card-title class="text-h6 font-weight-bold">Оповещения и реакция</v-card-title>
                <v-card-subtitle>Критичные события приходят туда, где их увидят вовремя</v-card-subtitle>
              </v-card-item>
              <v-divider />
              <v-card-text class="pa-5">
                <p class="landing-page__channels-text mb-5">
                  Сделки, смена статусов, сигналы безопасности и контрольные точки процесса можно отправлять в удобные каналы, чтобы команда
                  реагировала быстрее и не держала всё на ручном мониторинге.
                </p>

                <div class="d-flex flex-wrap ga-2 mb-6">
                  <v-chip v-for="channel in channelChips" :key="channel" color="secondary" variant="tonal">
                    {{ channel }}
                  </v-chip>
                </div>

                <v-sheet class="landing-page__quote" rounded="lg">
                  <div class="text-subtitle-1 font-weight-bold mb-2">Дополнительное преимущество</div>
                  <p class="landing-page__quote-text">
                    ReBit Admin Core полезен не только трейдеру, но и операционной команде: он помогает стандартизировать процесс, ускоряет
                    онбординг и упрощает масштабирование без хаоса в коммуникациях.
                  </p>
                </v-sheet>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </section>

    <footer class="landing-page__footer">
      <v-container>
        <div class="landing-page__footer-card">
          <div class="landing-page__footer-title">© 2026 ReBit Admin Core</div>
          <p class="landing-page__footer-text mb-0">
            Все права на проект защищены. Единое рабочее пространство для P2P-сделок, контроля балансов и операционных сценариев.
          </p>
        </div>
      </v-container>
    </footer>
  </div>
</template>

<style scoped lang="scss">
.landing-page {
  background:
    radial-gradient(circle at top left, rgba(94, 53, 177, 0.18), transparent 28%),
    radial-gradient(circle at top right, rgba(30, 136, 229, 0.18), transparent 34%),
    linear-gradient(180deg, #081224 0%, #0f172a 24%, #f8fafc 24%, #f8fafc 100%);
}

.landing-page__hero-section {
  position: relative;
  overflow: hidden;
  color: #ffffff;
}

.landing-page__hero-container {
  position: relative;
  z-index: 1;
  padding-top: 0;
  padding-bottom: 40px;
}

.landing-page__hero-row {
  min-height: min(700px, calc(100vh - 48px));
  padding-bottom: 32px;
}

.landing-page__hero-col {
  display: flex;
  flex-direction: column;
}

.landing-page__hero-copy {
  max-width: 640px;
}

.landing-page__hero-copy {
  padding-top: 8px;
}

.landing-page__hero-actions-wrap {
  margin-top: auto;
  padding-top: 24px;
}

.landing-page__hero-chip {
  border-color: rgba(255, 255, 255, 0.32);
  color: #ffffff;
}

.landing-page__hero-title {
  margin: 0 0 18px;
  font-size: clamp(2.15rem, 3.2vw, 3.3rem);
  line-height: 1.12;
  font-weight: 800;
}

.landing-page__hero-text {
  max-width: 620px;
  margin: 0 0 24px;
  font-size: 1rem;
  line-height: 1.72;
  color: rgba(255, 255, 255, 0.8);
}

.landing-page__hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 12px;
}

.landing-page__hero-panel {
  padding: 20px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  margin-bottom: 16px;
}

.landing-page__hero-panel-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.landing-page__metric-card {
  height: 100%;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: linear-gradient(180deg, #ffffff 0%, rgba(30, 136, 229, 0.03) 100%);
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
}

.landing-page__metric-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.landing-page__metric-value {
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1;
  color: #0f172a;
}

.landing-page__metric-title {
  margin-bottom: 8px;
  font-size: 0.98rem;
  font-weight: 700;
  line-height: 1.4;
  color: #0f172a;
}

.landing-page__metric-label {
  font-size: 0.875rem;
  line-height: 1.5;
  color: rgba(15, 23, 42, 0.68);
}

.landing-page__infographic {
  padding: 24px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  color: #0f172a;
}

.landing-page__infographic-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.landing-page__infographic-list {
  display: grid;
  gap: 20px;
}

.landing-page__infographic-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr);
  gap: 20px;
}

.landing-page__flow-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 18px;
  background: #f8fafc;
  padding: 18px;
}

.landing-page__flow-list {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 18px;
}

.landing-page__flow-item {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  min-width: 72px;
  font-size: 0.82rem;
  font-weight: 700;
  text-align: center;
}

.landing-page__flow-connector {
  flex: 1 1 22px;
  min-width: 22px;
  height: 2px;
  background: linear-gradient(90deg, rgba(94, 53, 177, 0.28) 0%, rgba(30, 136, 229, 0.28) 100%);
}

.landing-page__flow-note {
  padding: 14px 16px;
  border: 1px solid rgba(30, 136, 229, 0.12);
  background:
    radial-gradient(circle at top right, rgba(94, 53, 177, 0.06), transparent 40%),
    linear-gradient(135deg, rgba(30, 136, 229, 0.06), #ffffff);
  line-height: 1.6;
  color: rgba(15, 23, 42, 0.72);
}

.landing-page__section {
  padding: 72px 0;
}

.landing-page__section--benefits {
  padding-top: 40px;
}

.landing-page__section--light {
  background: #f8fafc;
}

.landing-page__section--dark {
  background: linear-gradient(135deg, #0f172a 0%, #172554 100%);
}

.landing-page__section-heading {
  max-width: 760px;
  margin-bottom: 32px;
}

.landing-page__section-heading--light {
  margin-bottom: 28px;
}

.landing-page__section-overline--light {
  color: rgba(255, 255, 255, 0.72);
}

.landing-page__section-title {
  margin: 0 0 16px;
  font-size: clamp(2rem, 3vw, 3rem);
  line-height: 1.15;
}

.landing-page__section-description {
  margin: 0;
  font-size: 1rem;
  line-height: 1.7;
  color: rgba(15, 23, 42, 0.7);
}

.landing-page__section-description--light {
  color: rgba(255, 255, 255, 0.74);
}

.landing-page__feature-card {
  height: 100%;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.landing-page__feature-card-header {
  min-height: 92px;
}

.landing-page__feature-text,
.landing-page__workflow-text,
.landing-page__channels-text,
.landing-page__quote-text {
  margin: 0;
  line-height: 1.7;
  color: rgba(15, 23, 42, 0.7);
}

.landing-page__workflow-list {
  display: grid;
  gap: 16px;
}

.landing-page__workflow-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.landing-page__workflow-card-layout {
  display: grid;
  grid-template-columns: 80px minmax(0, 1fr);
  gap: 20px;
}

.landing-page__workflow-step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 80px;
  height: 80px;
  border-radius: 24px;
  background: linear-gradient(135deg, rgba(94, 53, 177, 0.14) 0%, rgba(30, 136, 229, 0.14) 100%);
  font-size: 1.5rem;
  font-weight: 800;
  color: #5e35b1;
}

.landing-page__workflow-text {
  color: rgba(15, 23, 42, 0.72);
}

.landing-page__channels-card {
  padding: 32px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.landing-page__channels-card-header {
  min-height: 96px;
}

.landing-page__quote {
  padding: 20px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: rgba(255, 255, 255, 0.9);
}

.landing-page__footer {
  background: #f8fafc;
  padding: 0 0 88px;
}

.landing-page__footer-card {
  padding: 24px 28px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 18px;
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.landing-page__footer-title {
  margin-bottom: 8px;
  font-size: 1rem;
  font-weight: 800;
  color: #0f172a;
}

.landing-page__footer-text {
  margin: 0;
  line-height: 1.65;
  color: rgba(15, 23, 42, 0.68);
}

@media (max-width: 1279px) {
  .landing-page {
    background:
      radial-gradient(circle at top left, rgba(94, 53, 177, 0.18), transparent 28%),
      radial-gradient(circle at top right, rgba(30, 136, 229, 0.18), transparent 34%),
      linear-gradient(180deg, #081224 0%, #0f172a 30%, #f8fafc 30%, #f8fafc 100%);
  }

  .landing-page__hero-row {
    min-height: auto;
  }
}

@media (min-width: 960px) and (max-width: 1279px) {
  .landing-page__hero-col--aside {
    display: none;
  }

  .landing-page__hero-col--main {
    flex: 0 0 100%;
    max-width: 100%;
  }

  .landing-page__hero-copy {
    max-width: 760px;
  }
}

@media (max-width: 959px) {
  .landing-page__hero-row {
    min-height: auto;
    padding-top: 24px;
    padding-bottom: 24px;
  }

  .landing-page__hero-actions-wrap {
    margin-top: 0;
    padding-top: 16px;
  }

  .landing-page__hero-panel-grid {
    grid-template-columns: 1fr;
  }

  .landing-page__infographic-layout {
    grid-template-columns: 1fr;
  }

  .landing-page__infographic-header,
  .landing-page__footer-card {
    flex-direction: column;
    align-items: stretch;
  }
}

@media (max-width: 767px) {
  .landing-page__hero-container {
    padding-bottom: 40px;
  }

  .landing-page__hero-title {
    font-size: 2rem;
  }

  .landing-page__responsive-button {
    width: 100%;
  }

  .landing-page__workflow-card-layout {
    grid-template-columns: 1fr;
  }

  .landing-page__flow-list {
    align-items: stretch;
    flex-direction: column;
  }

  .landing-page__flow-connector {
    width: 2px;
    min-width: 2px;
    min-height: 18px;
    margin: 0 auto;
  }
}
</style>
