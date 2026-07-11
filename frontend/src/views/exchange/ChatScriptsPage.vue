<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue';
import { useChatScriptsStore } from '@/stores/chatScripts';
import { useCurrencyFormat } from '@/composables/useCurrencyFormat';
import type { ChatScriptStep, ChatScriptPayload, ChatContentType } from '@/api/exchange';
import { isMockApiEnabled } from '@/mocks/config';
import UiTableCard from '@/components/shared/UiTableCard.vue';

interface FormStep extends ChatScriptStep {
  _uid: number;
}

let stepUidCounter = 0;

function createFormStep(step?: Partial<ChatScriptStep>): FormStep {
  const contentType = isMockApiEnabled ? (step?.contentType ?? 'str') : 'str';

  return {
    _uid: ++stepUidCounter,
    sort: step?.sort ?? 1,
    message: step?.message ?? '',
    delaySeconds: step?.delaySeconds ?? 0,
    contentType,
    fileName: isMockApiEnabled ? (step?.fileName ?? null) : null,
    fileUrl: isMockApiEnabled ? (step?.fileUrl ?? null) : null
  };
}

const contentTypeOptions: { title: string; value: ChatContentType }[] = [
  { title: 'Текст', value: 'str' },
  { title: 'Изображение / QR', value: 'pic' },
  { title: 'PDF', value: 'pdf' },
  { title: 'Видео', value: 'video' }
];

const scripts = useChatScriptsStore();
const { formatDate } = useCurrencyFormat();

const formDialog = ref(false);
const deleteDialog = ref(false);
const deleteTargetId = ref<number | null>(null);
const editingId = ref<number | null>(null);
const previewDialog = ref(false);
const previewSteps = ref<ChatScriptStep[]>([]);

const form = reactive<{
  name: string;
  isActive: boolean;
  steps: FormStep[];
}>({
  name: '',
  isActive: true,
  steps: [createFormStep({ sort: 1 })]
});

const placeholders = [
  { tag: '{counterparty}', desc: 'Имя контрагента' },
  { tag: '{amount}', desc: 'Сумма сделки' },
  { tag: '{currency}', desc: 'Код валюты (USDT, BTC)' },
  { tag: '{fiat_amount}', desc: 'Сумма в фиате' },
  { tag: '{fiat_currency}', desc: 'Фиатная валюта (RUB)' },
  { tag: '{trade_id}', desc: 'Номер сделки' }
];

const canSaveScript = computed(() => {
  if ('' === form.name.trim()) {
    return false;
  }

  if (!isMockApiEnabled) {
    return !form.steps.some((step) => '' === step.message.trim());
  }

  return !form.steps.some((step) => {
    const hasMessage = '' !== step.message.trim();
    const hasFile = null !== step.fileUrl;

    if ('str' === step.contentType) {
      return !hasMessage;
    }

    return !hasMessage && !hasFile;
  });
});

function openCreate(): void {
  editingId.value = null;
  form.name = '';
  form.isActive = true;
  form.steps = [createFormStep({ sort: 1 })];
  formDialog.value = true;
}

function openEdit(id: number): void {
  const script = scripts.scripts.find((s) => s.id === id);
  if (!script) return;

  editingId.value = id;
  form.name = script.name;
  form.isActive = script.isActive;
  form.steps = script.steps.map((s) => createFormStep(s));
  formDialog.value = true;
}

function addStep(): void {
  form.steps.push(createFormStep({ sort: form.steps.length + 1 }));
}

function removeStep(index: number): void {
  form.steps.splice(index, 1);
  form.steps.forEach((step, i) => {
    step.sort = i + 1;
  });
}

function moveStep(index: number, direction: -1 | 1): void {
  const target = index + direction;
  if (target < 0 || target >= form.steps.length) return;
  const current = form.steps[index];
  const neighbor = form.steps[target];
  if (undefined === current || undefined === neighbor) return;
  form.steps[index] = neighbor;
  form.steps[target] = current;
  form.steps.forEach((step, i) => {
    step.sort = i + 1;
  });
}

function isImageStep(step: Pick<FormStep, 'contentType'>): boolean {
  return 'pic' === step.contentType;
}

function resolveFileContentType(file: File): ChatContentType {
  if (file.type.startsWith('image/')) {
    return 'pic';
  }

  if ('application/pdf' === file.type) {
    return 'pdf';
  }

  if (file.type.startsWith('video/')) {
    return 'video';
  }

  return 'str';
}

function clearStepFile(step: FormStep): void {
  step.fileName = null;
  step.fileUrl = null;

  if ('str' !== step.contentType) {
    step.contentType = 'str';
  }
}

function onContentTypeChange(step: FormStep): void {
  if (!isMockApiEnabled) {
    step.contentType = 'str';
    step.fileName = null;
    step.fileUrl = null;

    return;
  }

  if ('str' === step.contentType) {
    step.fileName = null;
    step.fileUrl = null;
  }
}

async function handleStepFileSelected(event: Event, step: FormStep): Promise<void> {
  if (!isMockApiEnabled) {
    return;
  }

  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];

  if (undefined === file) {
    return;
  }

  const reader = new FileReader();

  await new Promise<void>((resolve, reject) => {
    reader.onload = () => {
      step.fileName = file.name;
      step.fileUrl = 'string' === typeof reader.result ? reader.result : null;
      step.contentType = resolveFileContentType(file);
      resolve();
    };

    reader.onerror = () => {
      reject(new Error('Не удалось прочитать файл сценария.'));
    };

    reader.readAsDataURL(file);
  });

  input.value = '';
}

async function handleSave(): Promise<void> {
  const payload: ChatScriptPayload = {
    name: form.name,
    isActive: form.isActive,
    steps: form.steps.map((s, i) => {
      if (!isMockApiEnabled) {
        return {
          sort: i + 1,
          message: s.message,
          delaySeconds: s.delaySeconds
        };
      }

      return {
        sort: i + 1,
        message: s.message,
        delaySeconds: s.delaySeconds,
        contentType: s.contentType,
        fileName: s.fileName ?? null,
        fileUrl: s.fileUrl ?? null
      };
    })
  };

  try {
    if (null !== editingId.value) {
      await scripts.updateScript(editingId.value, payload);
    } else {
      await scripts.createScript(payload);
    }
    formDialog.value = false;
  } catch {
    // ошибка обрабатывается в сторе
  }
}

function confirmDelete(id: number): void {
  deleteTargetId.value = id;
  deleteDialog.value = true;
}

async function handleDelete(): Promise<void> {
  if (null === deleteTargetId.value) return;
  try {
    await scripts.deleteScript(deleteTargetId.value);
    deleteDialog.value = false;
    deleteTargetId.value = null;
  } catch {
    // ошибка обрабатывается в сторе
  }
}

function openPreview(steps: ChatScriptStep[]): void {
  previewSteps.value = steps;
  previewDialog.value = true;
}

function renderPreview(msg: string): string {
  return msg
    .replace(/{counterparty}/g, 'Иван')
    .replace(/{amount}/g, '100')
    .replace(/{currency}/g, 'USDT')
    .replace(/{fiat_amount}/g, '9 400')
    .replace(/{fiat_currency}/g, 'RUB')
    .replace(/{trade_id}/g, '12345');
}

onMounted(async () => {
  await scripts.fetchScripts();
});
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3">
      <h2 class="text-h4 font-weight-bold">Скрипты чата</h2>
      <v-btn color="secondary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="openCreate"> Создать скрипт </v-btn>
    </div>

    <v-alert v-if="isMockApiEnabled" type="info" variant="tonal" class="mb-4">
      В mock-режиме шаг сценария можно сохранить как текст, банковские реквизиты, QR-код, PDF или видео. Вложение хранится локально в
      браузере и автоматически отправится первым сообщением новой сделки.
    </v-alert>

    <v-alert v-else type="info" variant="tonal" class="mb-4">
      В реальном API чат-скриптов сейчас поддерживаются только текстовые шаги. Вложения и media-типы доступны только в mock-режиме.
    </v-alert>

    <v-row v-if="scripts.loading" justify="center" class="mt-8">
      <v-progress-circular indeterminate color="primary" />
    </v-row>

    <v-alert v-if="scripts.error" type="error" variant="tonal" class="mb-4">{{ scripts.error }}</v-alert>

    <UiTableCard
      v-if="!scripts.loading"
      title="Скрипты автосообщений"
      subtitle="Автоматические сценарии для чатов сделок"
      icon="mdi-script-text-outline"
      color="secondary"
      gradient="neutral"
    >
      <v-table density="comfortable" hover>
        <thead>
          <tr>
            <th>Название</th>
            <th class="text-center">Шагов</th>
            <th>Статус</th>
            <th>Дата</th>
            <th class="text-right">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="0 === scripts.scripts.length">
            <td colspan="5" class="text-center text-lightText pa-6">
              Нет скриптов.
              <v-btn variant="text" color="primary" size="small" @click="openCreate"> Создать первый </v-btn>
            </td>
          </tr>
          <tr v-for="script in scripts.scripts" :key="script.id">
            <td class="font-weight-medium">{{ script.name }}</td>
            <td class="text-center">{{ script.steps.length }}</td>
            <td>
              <v-chip size="small" variant="tonal" :color="script.isActive ? 'success' : 'grey'">
                {{ script.isActive ? 'Активен' : 'Неактивен' }}
              </v-chip>
            </td>
            <td class="text-lightText text-body-2">{{ formatDate(script.createdAt) }}</td>
            <td class="text-right">
              <v-btn icon="mdi-eye-outline" size="small" variant="text" @click="openPreview(script.steps)" />
              <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(script.id)" />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="confirmDelete(script.id)" />
            </td>
          </tr>
        </tbody>
      </v-table>
    </UiTableCard>

    <!-- Диалог создания/редактирования -->
    <v-dialog v-model="formDialog" max-width="700">
      <v-card rounded="lg">
        <v-card-item class="px-6 py-5">
          <template #prepend>
            <v-avatar size="48" color="primary" variant="tonal">
              <v-icon>{{ null !== editingId ? 'mdi-pencil' : 'mdi-plus-circle-outline' }}</v-icon>
            </v-avatar>
          </template>
          <v-card-title class="text-h6 font-weight-bold">
            {{ null !== editingId ? 'Редактировать скрипт' : 'Создать скрипт' }}
          </v-card-title>
          <v-card-subtitle class="text-wrap">
            {{ null !== editingId ? 'Измените настройки и шаги сценария' : 'Настройте автоматические сообщения для чата сделки' }}
          </v-card-subtitle>
        </v-card-item>
        <v-divider />
        <v-card-text>
          <v-text-field v-model="form.name" label="Название скрипта" variant="outlined" density="compact" class="mb-2" />
          <v-switch v-model="form.isActive" label="Активен" color="primary" density="compact" hide-details class="mb-4" />

          <div class="text-subtitle-2 mb-2">Шаги скрипта</div>

          <div v-for="(step, index) in form.steps" :key="step._uid" class="mb-3 pa-3 rounded border">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-caption font-weight-bold">Шаг {{ index + 1 }}</span>
              <div class="d-flex ga-1">
                <v-btn icon="mdi-arrow-up" size="x-small" variant="text" :disabled="0 === index" @click="moveStep(index, -1)" />
                <v-btn
                  icon="mdi-arrow-down"
                  size="x-small"
                  variant="text"
                  :disabled="index === form.steps.length - 1"
                  @click="moveStep(index, 1)"
                />
                <v-btn
                  icon="mdi-close"
                  size="x-small"
                  variant="text"
                  color="error"
                  :disabled="1 === form.steps.length"
                  @click="removeStep(index)"
                />
              </div>
            </div>
            <v-textarea
              v-model="step.message"
              :label="!isMockApiEnabled || 'str' === step.contentType ? 'Текст сообщения' : 'Комментарий к вложению'"
              variant="outlined"
              density="compact"
              rows="2"
              class="mb-2"
            />
            <template v-if="isMockApiEnabled">
              <v-row dense class="mb-2">
                <v-col cols="7">
                  <v-select
                    v-model="step.contentType"
                    :items="contentTypeOptions"
                    item-title="title"
                    item-value="value"
                    label="Тип шага"
                    variant="outlined"
                    density="compact"
                    hide-details
                    @update:model-value="onContentTypeChange(step)"
                  />
                </v-col>
                <v-col cols="5">
                  <v-text-field
                    v-model.number="step.delaySeconds"
                    label="Задержка (сек)"
                    variant="outlined"
                    density="compact"
                    type="number"
                    min="0"
                    max="300"
                    hide-details
                  />
                </v-col>
              </v-row>
              <div class="d-flex flex-wrap align-center ga-3 mb-2">
                <label class="d-inline-flex">
                  <input
                    class="d-none"
                    type="file"
                    accept="image/*,application/pdf,video/*"
                    @change="handleStepFileSelected($event, step)"
                  />
                  <v-btn variant="outlined" size="small" prepend-icon="mdi-paperclip" tag="span">
                    {{ null === step.fileUrl ? 'Загрузить файл' : 'Заменить файл' }}
                  </v-btn>
                </label>
                <v-chip v-if="null !== step.fileName" size="small" color="primary" variant="tonal">
                  {{ step.fileName }}
                </v-chip>
                <v-btn
                  v-if="null !== step.fileUrl"
                  variant="text"
                  size="small"
                  color="error"
                  prepend-icon="mdi-close"
                  @click="clearStepFile(step)"
                >
                  Удалить файл
                </v-btn>
              </div>
              <div v-if="null !== step.fileUrl && isImageStep(step)" class="mb-2">
                <img :src="step.fileUrl" :alt="step.fileName ?? 'preview'" class="chat-scripts-page__preview-image" />
              </div>
            </template>
            <v-text-field
              v-if="!isMockApiEnabled"
              v-model.number="step.delaySeconds"
              label="Задержка (секунд)"
              variant="outlined"
              density="compact"
              type="number"
              min="0"
              max="300"
              hide-details
            />
          </div>

          <v-btn variant="outlined" size="small" prepend-icon="mdi-plus" @click="addStep" class="mb-4"> Добавить шаг </v-btn>

          <!-- Подсказка по плейсхолдерам -->
          <v-expansion-panels variant="accordion" class="mt-2">
            <v-expansion-panel title="Доступные плейсхолдеры">
              <v-expansion-panel-text>
                <v-list density="compact" class="pa-0">
                  <v-list-item v-for="p in placeholders" :key="p.tag" class="px-0">
                    <template #prepend>
                      <code class="text-primary mr-2">{{ p.tag }}</code>
                    </template>
                    <v-list-item-title class="text-body-2">{{ p.desc }}</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card-text>
        <v-divider />
        <v-card-actions class="px-6 py-4">
          <v-spacer />
          <v-btn variant="outlined" rounded="lg" @click="formDialog = false">Отмена</v-btn>
          <v-btn
            color="secondary"
            variant="flat"
            rounded="lg"
            :loading="scripts.actionLoading"
            :disabled="!canSaveScript"
            @click="handleSave"
          >
            Сохранить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Диалог предпросмотра -->
    <v-dialog v-model="previewDialog" max-width="500">
      <v-card rounded="lg">
        <v-card-item class="px-6 py-5">
          <template #prepend>
            <v-avatar size="48" color="info" variant="tonal">
              <v-icon>mdi-eye-outline</v-icon>
            </v-avatar>
          </template>
          <v-card-title class="text-h6 font-weight-bold">Предпросмотр скрипта</v-card-title>
          <v-card-subtitle>Так будут выглядеть автосообщения в чате</v-card-subtitle>
        </v-card-item>
        <v-divider />
        <v-card-text>
          <div class="pa-3 bg-grey-lighten-4 rounded" style="max-height: 400px; overflow-y: auto">
            <div v-for="(step, index) in previewSteps" :key="index" class="mb-3">
              <div class="d-flex justify-end">
                <div class="pa-3 rounded-lg bg-primary text-white" style="max-width: 80%">
                  <img
                    v-if="step.fileUrl && 'pic' === step.contentType"
                    :src="step.fileUrl"
                    :alt="step.fileName ?? 'preview'"
                    class="chat-scripts-page__preview-image mb-2"
                  />
                  <v-chip v-else-if="step.fileUrl && step.fileName" size="small" variant="tonal" prepend-icon="mdi-paperclip" class="mb-2">
                    {{ step.fileName }}
                  </v-chip>
                  <div class="text-body-2" style="white-space: pre-wrap">{{ renderPreview(step.message) }}</div>
                  <div class="text-caption mt-1" style="opacity: 0.7">
                    {{ 0 < step.delaySeconds ? `+${step.delaySeconds} сек` : 'Сразу' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="previewDialog = false">Закрыть</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Диалог удаления -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="lg">
        <v-card-item class="px-6 py-5">
          <template #prepend>
            <v-avatar size="48" color="error" variant="tonal">
              <v-icon>mdi-delete-outline</v-icon>
            </v-avatar>
          </template>
          <v-card-title class="text-h6 font-weight-bold">Удалить скрипт?</v-card-title>
          <v-card-subtitle class="text-wrap"
            >Скрипт будет удалён и отвязан от всех объявлений. Уже запущенные сделки не затрагиваются.</v-card-subtitle
          >
        </v-card-item>
        <v-divider />
        <v-card-actions class="px-6 py-4">
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Отмена</v-btn>
          <v-btn color="error" :loading="scripts.actionLoading" @click="handleDelete">Удалить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.border {
  border: 1px solid rgba(0, 0, 0, 0.12);
}

.chat-scripts-page__preview-image {
  display: block;
  max-width: 240px;
  max-height: 180px;
  border-radius: 12px;
  object-fit: cover;
}
</style>
