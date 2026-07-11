import { defineStore } from 'pinia';
import { ref } from 'vue';
import { exchangeApi, type Advertisement, type AdvertisementStatus, type CreateAdvertisementPayload } from '@/api/exchange';

export const useAdvertisementsStore = defineStore('advertisements', () => {
  const advertisements = ref<Advertisement[]>([]);
  const loading = ref(false);
  const actionLoading = ref(false);
  const error = ref<string | null>(null);

  async function fetchAdvertisements(status?: AdvertisementStatus): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
      advertisements.value = await exchangeApi.getAdvertisements(status);
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки объявлений';
    } finally {
      loading.value = false;
    }
  }

  async function createAdvertisement(payload: CreateAdvertisementPayload): Promise<Advertisement> {
    actionLoading.value = true;
    error.value = null;
    try {
      const ad = await exchangeApi.createAdvertisement(payload);
      advertisements.value.unshift(ad);
      return ad;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка создания объявления';
      throw e;
    } finally {
      actionLoading.value = false;
    }
  }

  async function deleteAdvertisement(id: number): Promise<void> {
    actionLoading.value = true;
    error.value = null;
    try {
      await exchangeApi.deleteAdvertisement(id);
      advertisements.value = advertisements.value.filter((ad) => ad.id !== id);
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка удаления объявления';
      throw e;
    } finally {
      actionLoading.value = false;
    }
  }

  async function toggleAdvertisement(id: number, status: 'active' | 'paused'): Promise<void> {
    actionLoading.value = true;
    error.value = null;
    try {
      const updated = await exchangeApi.toggleAdvertisement(id, status);
      const index = advertisements.value.findIndex((ad) => ad.id === id);
      if (-1 !== index) {
        advertisements.value[index] = updated;
      }
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Ошибка переключения статуса';
      throw e;
    } finally {
      actionLoading.value = false;
    }
  }

  return {
    advertisements,
    loading,
    actionLoading,
    error,
    fetchAdvertisements,
    createAdvertisement,
    deleteAdvertisement,
    toggleAdvertisement
  };
});
