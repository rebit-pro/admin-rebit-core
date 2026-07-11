<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted, computed } from 'vue';
import { exchangeApi, type ChatContentType, type ChatMessage } from '@/api/exchange';
import { useTradesStore } from '@/stores/trades';
import { usePolling } from '@/composables/usePolling';

interface SelectedAttachment {
  file: File;
  fileName: string;
  contentType: ChatContentType;
  previewUrl: string | null;
}

const props = defineProps<{
  tradeId: number;
  readonly: boolean;
}>();

const trades = useTradesStore();
const messageText = ref('');
const sending = ref(false);
const chatContainer = ref<HTMLDivElement | null>(null);
const selectedAttachment = ref<SelectedAttachment | null>(null);

const senderLabels: Record<string, string> = {
  user: 'Вы',
  system: 'Система',
  script: 'Автосообщение'
};

const canSendMessage = computed(() => {
  return '' !== messageText.value.trim() || null !== selectedAttachment.value;
});

const hasSelectedAttachment = computed(() => null !== selectedAttachment.value);

function getSelectedAttachmentName(): string {
  if (null === selectedAttachment.value) {
    return '';
  }

  return selectedAttachment.value['fileName'];
}

function getSelectedAttachmentUrl(): string {
  if (null === selectedAttachment.value || null === selectedAttachment.value['previewUrl']) {
    return '';
  }

  return selectedAttachment.value['previewUrl'];
}

function hasSelectedImageAttachment(): boolean {
  if (null === selectedAttachment.value) {
    return false;
  }

  return 'pic' === selectedAttachment.value['contentType'] && null !== selectedAttachment.value['previewUrl'];
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function isMediaMessage(contentType: ChatContentType): boolean {
  return 'pic' === contentType || 'pdf' === contentType || 'video' === contentType;
}

function isImageMessage(message: ChatMessage): boolean {
  return 'pic' === message.contentType && 'string' === typeof message.fileUrl && '' !== message.fileUrl;
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

function clearAttachment(): void {
  const previewUrl = selectedAttachment.value?.['previewUrl'] ?? null;

  if (null !== previewUrl) {
    URL.revokeObjectURL(previewUrl);
  }

  selectedAttachment.value = null;
}

async function handleFileSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];

  if (undefined === file) {
    return;
  }

  clearAttachment();

  const contentType = resolveFileContentType(file);
  selectedAttachment.value = {
    file,
    fileName: file.name,
    contentType,
    previewUrl: 'pic' === contentType ? URL.createObjectURL(file) : null
  };
  input.value = '';
}

async function scrollToBottom(): Promise<void> {
  await nextTick();
  const container = chatContainer.value;

  if (null !== container) {
    const element = container as HTMLElement;
    element.scrollTop = element.scrollHeight;
  }
}

async function sendMessage(): Promise<void> {
  const text = messageText.value.trim();
  const attachment = selectedAttachment.value;

  if (!canSendMessage.value || sending.value) {
    return;
  }

  sending.value = true;

  try {
    if ('' !== text) {
      await trades.sendMessage(props.tradeId, {
        tradeId: props.tradeId,
        message: text,
        contentType: 'str',
        fileName: null
      });

      messageText.value = '';
    }

    if (null !== attachment) {
      const uploadedFile = await exchangeApi.uploadTradeChatFile(props.tradeId, attachment['file']);

      await trades.sendMessage(props.tradeId, {
        tradeId: props.tradeId,
        message: uploadedFile.fileUrl,
        contentType: uploadedFile.contentType,
        fileName: uploadedFile.fileName,
        fileUrl: uploadedFile.fileUrl
      });

      clearAttachment();
    }

    await scrollToBottom();
  } finally {
    sending.value = false;
  }
}

async function loadChat(): Promise<void> {
  await trades.fetchChatHistory(props.tradeId);
}

const polling = usePolling(loadChat, 5000);

watch(
  () => trades.chatMessages.length,
  () => {
    void scrollToBottom();
  }
);

onMounted(async () => {
  await loadChat();
  await scrollToBottom();
  polling.start();
});

onUnmounted(() => {
  polling.stop();
  clearAttachment();
});
</script>

<template>
  <v-card class="trade-chat" rounded="lg">
    <v-card-item class="trade-chat__header px-5 py-4">
      <template #prepend>
        <v-avatar size="42" color="secondary" variant="tonal">
          <v-icon>mdi-chat-outline</v-icon>
        </v-avatar>
      </template>
      <v-card-title class="text-h6 font-weight-bold">Чат сделки</v-card-title>
      <v-card-subtitle>Общение с контрагентом</v-card-subtitle>
    </v-card-item>

    <v-divider />

    <!-- Область сообщений -->
    <div ref="chatContainer" class="trade-chat__messages pa-4">
      <v-row v-if="trades.chatLoading && 0 === trades.chatMessages.length" justify="center" class="py-6">
        <v-progress-circular indeterminate color="secondary" size="24" />
      </v-row>

      <div v-if="0 === trades.chatMessages.length && !trades.chatLoading" class="text-center text-medium-emphasis pa-8">
        <v-icon size="40" color="grey" class="mb-2">mdi-chat-sleep-outline</v-icon>
        <div class="text-body-1">Сообщений пока нет</div>
      </div>

      <div v-for="msg in trades.chatMessages" :key="msg.id" class="trade-chat__row mb-3">
        <!-- Системные сообщения — по центру -->
        <div v-if="'system' === msg.senderType" class="d-flex justify-center">
          <div class="trade-chat__bubble trade-chat__bubble--system pa-2 px-4">
            <div class="text-caption text-center" style="white-space: pre-wrap">{{ msg.message }}</div>
            <div class="trade-chat__time text-caption text-center mt-1">{{ formatTime(msg.createdAt) }}</div>
          </div>
        </div>

        <!-- Мои сообщения — справа -->
        <div v-else-if="'user' === msg.senderType || 'script' === msg.senderType" class="d-flex justify-end">
          <div
            class="trade-chat__bubble trade-chat__bubble--user pa-3"
            :class="{ 'trade-chat__bubble--script': 'script' === msg.senderType }"
          >
            <div class="text-caption font-weight-bold mb-1 trade-chat__sender">
              {{ senderLabels[msg.senderType] ?? msg.senderType }}
            </div>

            <template v-if="isMediaMessage(msg.contentType)">
              <img
                v-if="isImageMessage(msg)"
                :src="msg.fileUrl ?? undefined"
                :alt="msg.fileName ?? 'attachment'"
                class="trade-chat__image mb-2"
              />
              <a
                v-if="msg.fileUrl"
                :href="msg.fileUrl"
                :download="msg.fileName ?? 'file'"
                target="_blank"
                rel="noopener noreferrer"
                class="trade-chat__attachment-link d-block"
              >
                <v-chip size="small" variant="outlined" color="white" prepend-icon="mdi-download">
                  {{ msg.fileName ?? msg.contentType }}
                </v-chip>
              </a>
              <div v-if="'' !== msg.message && msg.message !== msg.fileName" class="text-body-2 mt-1" style="white-space: pre-wrap">
                {{ msg.message }}
              </div>
            </template>
            <template v-else>
              <div class="text-body-2" style="white-space: pre-wrap">{{ msg.message }}</div>
            </template>

            <div class="trade-chat__time text-caption mt-1">{{ formatTime(msg.createdAt) }}</div>
          </div>

          <v-avatar size="32" color="secondary" variant="tonal" class="ml-2 mt-1 flex-shrink-0">
            <v-icon size="16">{{ 'script' === msg.senderType ? 'mdi-script-text-outline' : 'mdi-account' }}</v-icon>
          </v-avatar>
        </div>

        <!-- Сообщения контрагента — слева -->
        <div v-else class="d-flex justify-start">
          <v-avatar size="32" color="grey-lighten-1" variant="tonal" class="mr-2 mt-1 flex-shrink-0">
            <v-icon size="16">mdi-account</v-icon>
          </v-avatar>

          <div class="trade-chat__bubble trade-chat__bubble--counterparty pa-3">
            <div class="text-caption font-weight-bold mb-1">Контрагент</div>

            <template v-if="isMediaMessage(msg.contentType)">
              <img
                v-if="isImageMessage(msg)"
                :src="msg.fileUrl ?? undefined"
                :alt="msg.fileName ?? 'attachment'"
                class="trade-chat__image mb-2"
              />
              <a
                v-if="msg.fileUrl"
                :href="msg.fileUrl"
                :download="msg.fileName ?? 'file'"
                target="_blank"
                rel="noopener noreferrer"
                class="trade-chat__attachment-link d-block"
              >
                <v-chip size="small" variant="tonal" color="secondary" prepend-icon="mdi-download">
                  {{ msg.fileName ?? msg.contentType }}
                </v-chip>
              </a>
              <div v-if="'' !== msg.message && msg.message !== msg.fileName" class="text-body-2 mt-1" style="white-space: pre-wrap">
                {{ msg.message }}
              </div>
            </template>
            <template v-else>
              <div class="text-body-2" style="white-space: pre-wrap">{{ msg.message }}</div>
            </template>

            <div class="trade-chat__time text-caption mt-1">{{ formatTime(msg.createdAt) }}</div>
          </div>
        </div>
      </div>
    </div>

    <v-divider />

    <v-alert v-if="readonly" type="info" variant="tonal" class="ma-3" density="compact"> Сделка завершена / отменена. Чат закрыт. </v-alert>

    <!-- Область ввода -->
    <div v-if="!readonly" class="trade-chat__input pa-4">
      <!-- Превью вложения -->
      <div v-if="hasSelectedAttachment" class="d-flex align-center ga-2 mb-3 pa-3 trade-chat__attachment-bar rounded-lg">
        <v-icon size="20" color="secondary">mdi-paperclip</v-icon>
        <img
          v-if="hasSelectedImageAttachment()"
          :src="getSelectedAttachmentUrl()"
          :alt="getSelectedAttachmentName()"
          class="trade-chat__image-mini"
        />
        <span class="text-body-2 font-weight-medium flex-grow-1 text-truncate">{{ getSelectedAttachmentName() }}</span>
        <v-btn icon="mdi-close" size="x-small" variant="text" color="error" @click="clearAttachment" />
      </div>

      <div class="d-flex align-center ga-2">
        <label class="d-inline-flex">
          <input class="d-none" type="file" accept="image/*,application/pdf,video/*" @change="handleFileSelected" />
          <v-btn
            icon="mdi-paperclip"
            size="small"
            variant="text"
            color="secondary"
            tag="span"
            aria-label="Прикрепить файл"
            title="Прикрепить файл"
          />
        </label>

        <v-text-field
          v-model="messageText"
          placeholder="Введите сообщение..."
          variant="outlined"
          density="compact"
          hide-details
          rounded="lg"
          :disabled="sending"
          @keydown.enter.exact.prevent="sendMessage"
        />

        <v-btn
          icon="mdi-send"
          color="secondary"
          variant="flat"
          size="small"
          rounded="lg"
          :loading="sending"
          :disabled="!canSendMessage"
          aria-label="Отправить сообщение"
          title="Отправить сообщение"
          @click="sendMessage"
        />
      </div>
    </div>
  </v-card>
</template>

<style scoped lang="scss">
.trade-chat {
  border: 1px solid rgba(15, 23, 42, 0.12);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  min-height: 100%;
}

.trade-chat__header {
  min-height: 72px;
}

.trade-chat__messages {
  flex: 1 1 auto;
  min-height: 420px;
  overflow-y: auto;
  scroll-behavior: smooth;
  background: #f5f7fb;
}

.trade-chat__bubble {
  max-width: 70%;
  border-radius: 12px;
  word-break: break-word;
  position: relative;
}

/* Мои сообщения — справа, secondary */
.trade-chat__bubble--user {
  background: #5e35b1;
  color: #fff;
  border-bottom-right-radius: 2px;

  .trade-chat__sender {
    color: rgba(255, 255, 255, 0.75);
  }

  .trade-chat__time {
    color: rgba(255, 255, 255, 0.6);
  }
}

/* Скрипт — чуть отличающийся оттенок */
.trade-chat__bubble--script {
  background: #03c9d7;
}

/* Контрагент — слева, светлый */
.trade-chat__bubble--counterparty {
  background: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-bottom-left-radius: 2px;
  color: #0f172a;

  .trade-chat__time {
    color: rgba(15, 23, 42, 0.45);
  }
}

/* Системные — по центру, нейтральные */
.trade-chat__bubble--system {
  background: rgba(0, 0, 0, 0.04);
  border-radius: 20px;
  max-width: 85%;

  .trade-chat__time {
    color: rgba(0, 0, 0, 0.35);
  }
}

.trade-chat__image {
  display: block;
  max-width: 220px;
  max-height: 220px;
  border-radius: 8px;
  object-fit: cover;
  cursor: pointer;
}

.trade-chat__image-mini {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  object-fit: cover;
}

.trade-chat__attachment-link {
  text-decoration: none;
}

.trade-chat__attachment-bar {
  background: rgba(94, 53, 177, 0.06);
  border: 1px solid rgba(94, 53, 177, 0.12);
}

.trade-chat__input {
  background: #ffffff;
}
</style>
