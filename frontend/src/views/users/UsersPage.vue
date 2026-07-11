<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { usersApi, type CreateUserRequest, type ManagedUser } from '@/api/users';

const roleOptions = ['owner', 'admin', 'user'];
const roleLabels: Record<string, string> = { owner: 'Владелец', admin: 'Администратор', user: 'Пользователь' };
function roleLabel(role: string): string {
  return roleLabels[role] ?? role;
}

const items = ref<ManagedUser[]>([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const search = ref('');
const loading = ref(false);
const loadError = ref<string | null>(null);

const pageCount = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));

const snackbar = reactive({ show: false, color: 'success', text: '' });
function notify(text: string, color: 'success' | 'error' = 'success'): void {
  snackbar.text = text;
  snackbar.color = color;
  snackbar.show = true;
}

function apiMessage(error: unknown, fallback: string): string {
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const response = (error as { response?: { data?: { message?: string } } }).response;
    if (typeof response?.data?.message === 'string') {
      return response.data.message;
    }
  }
  return fallback;
}

async function load(): Promise<void> {
  loading.value = true;
  loadError.value = null;
  try {
    const result = await usersApi.list({ page: page.value, perPage: perPage.value, search: search.value.trim() || undefined });
    items.value = result.items;
    total.value = result.total;
  } catch (error) {
    loadError.value = apiMessage(error, 'Не удалось загрузить список пользователей.');
    items.value = [];
    total.value = 0;
  } finally {
    loading.value = false;
  }
}

function applySearch(): void {
  page.value = 1;
  void load();
}

async function changeRole(user: ManagedUser, role: string): Promise<void> {
  try {
    await usersApi.changeRole(user.id, role);
    notify(`Роль пользователя обновлена: ${roleLabel(role)}.`);
    await load();
  } catch (error) {
    notify(apiMessage(error, 'Не удалось изменить роль.'), 'error');
  }
}

async function setStatus(user: ManagedUser, active: boolean): Promise<void> {
  try {
    await (active ? usersApi.unblock(user.id) : usersApi.block(user.id));
    notify(active ? 'Пользователь разблокирован.' : 'Пользователь заблокирован.');
    await load();
  } catch (error) {
    notify(apiMessage(error, 'Не удалось изменить статус.'), 'error');
  }
}

// --- Удаление ---
const deleteTarget = ref<ManagedUser | null>(null);
const deleteLoading = ref(false);
async function confirmDelete(): Promise<void> {
  if (null === deleteTarget.value) {
    return;
  }
  deleteLoading.value = true;
  try {
    await usersApi.remove(deleteTarget.value.id);
    notify('Пользователь удалён.');
    deleteTarget.value = null;
    await load();
  } catch (error) {
    notify(apiMessage(error, 'Не удалось удалить пользователя.'), 'error');
  } finally {
    deleteLoading.value = false;
  }
}

// --- Создание ---
const createDialog = ref(false);
const createLoading = ref(false);
const form = reactive<CreateUserRequest>({ email: '', password: '', name: '', login: '', role: 'user', phone: '', address: '' });

function openCreate(): void {
  form.email = form.password = form.name = form.login = form.phone = form.address = '';
  form.role = 'user';
  createDialog.value = true;
}

async function submitCreate(): Promise<void> {
  createLoading.value = true;
  try {
    await usersApi.create({
      email: form.email.trim(),
      password: form.password,
      name: form.name.trim(),
      login: form.login.trim(),
      role: form.role,
      phone: form.phone?.trim() || undefined,
      address: form.address?.trim() || undefined
    });
    notify('Пользователь создан.');
    createDialog.value = false;
    page.value = 1;
    await load();
  } catch (error) {
    notify(apiMessage(error, 'Не удалось создать пользователя.'), 'error');
  } finally {
    createLoading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <main class="users-page">
    <div class="d-flex align-center flex-wrap ga-3 mb-6">
      <h1 class="text-h5 font-weight-bold mr-auto">Пользователи</h1>
      <v-text-field
        v-model="search"
        label="Поиск (email, логин, имя)"
        density="compact"
        variant="outlined"
        hide-details
        prepend-inner-icon="mdi-magnify"
        style="max-width: 320px"
        @keyup.enter="applySearch"
      />
      <v-btn variant="tonal" icon="mdi-refresh" @click="applySearch" />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Создать</v-btn>
    </div>

    <v-alert v-if="loadError" type="error" variant="tonal" class="mb-4">{{ loadError }}</v-alert>

    <v-card rounded="lg">
      <v-progress-linear v-if="loading" indeterminate color="primary" />
      <v-table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Логин</th>
            <th>Имя</th>
            <th>Роль</th>
            <th>Статус</th>
            <th class="text-right">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>{{ item.id }}</td>
            <td>{{ item.email }}</td>
            <td>{{ item.login }}</td>
            <td>{{ item.name }}</td>
            <td><v-chip size="small" variant="tonal" color="primary">{{ roleLabel(item.role) }}</v-chip></td>
            <td>
              <v-chip size="small" variant="tonal" :color="item.status === 'active' ? 'success' : 'error'">
                {{ item.status === 'active' ? 'Активен' : 'Заблокирован' }}
              </v-chip>
            </td>
            <td class="text-right">
              <v-menu location="bottom end">
                <template #activator="{ props }">
                  <v-btn icon="mdi-dots-vertical" variant="text" size="small" v-bind="props" />
                </template>
                <v-list density="compact" min-width="200">
                  <v-list-subheader>Назначить роль</v-list-subheader>
                  <v-list-item
                    v-for="role in roleOptions.filter((r) => r !== item.role)"
                    :key="role"
                    @click="changeRole(item, role)"
                  >
                    <v-list-item-title>{{ roleLabel(role) }}</v-list-item-title>
                  </v-list-item>
                  <v-divider class="my-1" />
                  <v-list-item v-if="item.status === 'active'" @click="setStatus(item, false)">
                    <v-list-item-title>Заблокировать</v-list-item-title>
                  </v-list-item>
                  <v-list-item v-else @click="setStatus(item, true)">
                    <v-list-item-title>Разблокировать</v-list-item-title>
                  </v-list-item>
                  <v-list-item @click="deleteTarget = item">
                    <v-list-item-title class="text-error">Удалить</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
            </td>
          </tr>
          <tr v-if="!loading && items.length === 0">
            <td colspan="7" class="text-center text-medium-emphasis py-6">Ничего не найдено</td>
          </tr>
        </tbody>
      </v-table>

      <div class="d-flex align-center justify-space-between pa-4">
        <span class="text-caption text-medium-emphasis">Всего: {{ total }}</span>
        <v-pagination v-model="page" :length="pageCount" density="comfortable" total-visible="5" @update:model-value="load" />
      </div>
    </v-card>

    <!-- Создание пользователя -->
    <v-dialog v-model="createDialog" max-width="520">
      <v-card rounded="lg">
        <v-card-title class="text-subtitle-1 font-weight-bold">Новый пользователь</v-card-title>
        <v-card-text>
          <v-form @submit.prevent="submitCreate">
            <v-text-field v-model="form.email" label="Email" type="email" variant="outlined" density="comfortable" class="mb-2" />
            <v-text-field v-model="form.login" label="Логин" variant="outlined" density="comfortable" class="mb-2" />
            <v-text-field v-model="form.name" label="Имя" variant="outlined" density="comfortable" class="mb-2" />
            <v-text-field v-model="form.password" label="Пароль" type="password" variant="outlined" density="comfortable" hint="Минимум 8 символов" persistent-hint class="mb-2" />
            <v-select v-model="form.role" :items="roleOptions" label="Роль" variant="outlined" density="comfortable" class="mb-2">
              <template #selection="{ item }">{{ roleLabel(item.value) }}</template>
              <template #item="{ item, props }"><v-list-item v-bind="props" :title="roleLabel(item.value)" /></template>
            </v-select>
            <v-text-field v-model="form.phone" label="Телефон (необязательно)" variant="outlined" density="comfortable" class="mb-2" />
            <v-text-field v-model="form.address" label="Адрес (необязательно)" variant="outlined" density="comfortable" />
          </v-form>
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="createDialog = false">Отмена</v-btn>
          <v-btn color="primary" :loading="createLoading" @click="submitCreate">Создать</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Подтверждение удаления -->
    <v-dialog :model-value="deleteTarget !== null" max-width="420" @update:model-value="deleteTarget = null">
      <v-card rounded="lg">
        <v-card-title class="text-subtitle-1 font-weight-bold">Удалить пользователя?</v-card-title>
        <v-card-text>
          Пользователь <strong>{{ deleteTarget?.email }}</strong> будет удалён без возможности восстановления.
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="deleteTarget = null">Отмена</v-btn>
          <v-btn color="error" :loading="deleteLoading" @click="confirmDelete">Удалить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" location="top right" timeout="3500">
      {{ snackbar.text }}
    </v-snackbar>
  </main>
</template>
