import api from './http';

export interface ManagedUser {
  id: number;
  email: string;
  login: string;
  name: string;
  role: string;
  status: string;
  phone: string | null;
  address: string | null;
  createdAt: string;
}

export interface UsersPage {
  items: ManagedUser[];
  total: number;
  page: number;
  perPage: number;
}

export interface CreateUserRequest {
  email: string;
  password: string;
  name: string;
  login: string;
  role: string;
  phone?: string;
  address?: string;
}

export interface ListUsersParams {
  page?: number;
  perPage?: number;
  search?: string;
}

export const usersApi = {
  list(params: ListUsersParams): Promise<UsersPage> {
    return api.get('/api/v1/users', { params }).then((r) => r.data);
  },

  create(data: CreateUserRequest): Promise<ManagedUser> {
    return api.post('/api/v1/users', data).then((r) => r.data);
  },

  changeRole(id: number, role: string): Promise<ManagedUser> {
    return api.patch(`/api/v1/users/${id}`, { role }).then((r) => r.data);
  },

  block(id: number): Promise<ManagedUser> {
    return api.post(`/api/v1/users/${id}/block`).then((r) => r.data);
  },

  unblock(id: number): Promise<ManagedUser> {
    return api.post(`/api/v1/users/${id}/unblock`).then((r) => r.data);
  },

  remove(id: number): Promise<void> {
    return api.delete(`/api/v1/users/${id}`);
  }
};
