import { defineStore } from 'pinia';
import { ref } from 'vue';
import { exchangeApi, type ChatScript, type ChatScriptPayload } from '@/api/exchange';

export const useChatScriptsStore = defineStore('chatScripts', () => {
  const scripts = ref<ChatScript[]>([]);
  const loading = ref(false);
  const actionLoading = ref(false);
  const error = ref<string | null>(null);

  async function fetchScripts(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      scripts.value = await exchangeApi.getChatScripts();
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки скриптов';
    } finally {
      loading.value = false;
    }
  }

  async function createScript(payload: ChatScriptPayload): Promise<ChatScript> {
    actionLoading.value = true;
    error.value = null;
    try {
      const script = await exchangeApi.createChatScript(payload);
      scripts.value.unshift(script);
      return script;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка создания скрипта';
      throw e;
    } finally {
      actionLoading.value = false;
    }
  }

  async function updateScript(id: number, payload: ChatScriptPayload): Promise<void> {
    actionLoading.value = true;
    error.value = null;
    try {
      const updated = await exchangeApi.updateChatScript(id, payload);
      const index = scripts.value.findIndex((s) => s.id === id);
      if (-1 !== index) {
        scripts.value[index] = updated;
      }
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка обновления скрипта';
      throw e;
    } finally {
      actionLoading.value = false;
    }
  }

  async function deleteScript(id: number): Promise<void> {
    actionLoading.value = true;
    error.value = null;
    try {
      await exchangeApi.deleteChatScript(id);
      scripts.value = scripts.value.filter((s) => s.id !== id);
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка удаления скрипта';
      throw e;
    } finally {
      actionLoading.value = false;
    }
  }

  return {
    scripts,
    loading,
    actionLoading,
    error,
    fetchScripts,
    createScript,
    updateScript,
    deleteScript
  };
});
