import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { router } from '@/router';
import { mockApiAdapter } from '@/mocks/adapter';
import { isMockApiEnabled } from '@/mocks/config';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  adapter: isMockApiEnabled ? mockApiAdapter : undefined,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  },
  timeout: 15000
});

// Request: подставляем JWT-токен
api.interceptors.request.use((config) => {
  const auth = useAuthStore();
  const token = auth.getAccessToken();

  if (null !== token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

// Response: обработка 401
api.interceptors.response.use(
  (response) => {
    // Разворачиваем обёртку API: { data: { ... } } → { ... }
    if (response.data?.data !== undefined) {
      response.data = response.data.data;
    }
    return response;
  },
  async (error) => {
    if (error.response?.data?.error?.message !== undefined) {
      error.response.data.message = error.response.data.error.message;
    }

    if (error.response?.status === 401) {
      const auth = useAuthStore();
      auth.clearSession();
      await router.push('/login');
    }
    return Promise.reject(error);
  }
);

export default api;
