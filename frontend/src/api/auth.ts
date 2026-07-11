import api from './http';

export interface SmartCaptchaPayload {
  token: string;
}

export interface LoginRequest {
  email: string;
  password: string;
  captcha?: SmartCaptchaPayload;
}

export interface RegisterRequest {
  email: string;
  password: string;
}

export interface RequestRegistrationCodeResponse {
  email: string;
  codeExpiresAt: string;
  resendAvailableAt: string;
}

export interface ConfirmRegistrationRequest {
  email: string;
  code: string;
}

export interface AuthUser {
  id: number;
  email: string;
  name: string;
  login?: string;
  role?: string;
  phone?: string | null;
  address?: string | null;
}

export interface LoginResponse {
  token: string;
  expiresAt: string;
  user: AuthUser;
}

export const authApi = {
  login(data: LoginRequest): Promise<LoginResponse> {
    return api.post('/api/v1/auth/login', data).then((r) => r.data);
  },

  getUser(): Promise<AuthUser> {
    return api.get('/api/v1/auth/user').then((r) => r.data);
  },

  requestRegistrationCode(data: RegisterRequest): Promise<RequestRegistrationCodeResponse> {
    return api.post('/api/v1/auth/register/request-code', data).then((r) => r.data);
  },

  confirmRegistration(data: ConfirmRegistrationRequest): Promise<LoginResponse> {
    return api.post('/api/v1/auth/register/confirm', data).then((r) => r.data);
  },

  logout(): Promise<void> {
    return api.post('/api/v1/auth/logout');
  }
};
