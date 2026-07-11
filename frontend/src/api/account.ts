import api from './http';
import type { AuthUser } from './auth';

export interface ChangePasswordRequest {
  currentPassword: string;
  newPassword: string;
  newPasswordConfirmation: string;
}

export interface ChangePasswordResponse {
  token: string;
  expiresAt: string;
}

export const accountApi = {
  changePassword(data: ChangePasswordRequest): Promise<ChangePasswordResponse> {
    return api.post('/api/v1/account/change-password', data).then((r) => r.data);
  },

  changeLogin(login: string): Promise<AuthUser> {
    return api.post('/api/v1/account/change-login', { login }).then((r) => r.data);
  },

  changeEmail(newEmail: string, currentPassword: string): Promise<AuthUser> {
    return api.post('/api/v1/account/change-email', { newEmail, currentPassword }).then((r) => r.data);
  }
};
