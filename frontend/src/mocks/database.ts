import type { AuthUser, LoginRequest, LoginResponse, RegisterRequest, RequestRegistrationCodeResponse } from '@/api/auth';

const STORAGE_KEY = 'rebit:admin-core:mock-auth:v1';
const DEFAULT_USER_EMAIL = 'owner@rebit.test';
const DEFAULT_USER_PASSWORD = 'secret123';
const MOCK_TOKEN_TTL_MINUTES = 60 * 24;

type MockUser = AuthUser & {
  password: string;
};

type MockRegistration = RequestRegistrationCodeResponse &
  RegisterRequest & {
    code: string;
  };

interface MockState {
  version: number;
  users: MockUser[];
  authTokens: Record<string, number>;
  registration: MockRegistration | null;
  nextUserId: number;
}

let mockState: MockState | null = null;

function clone<TValue>(value: TValue): TValue {
  if ('function' === typeof globalThis.structuredClone) {
    return globalThis.structuredClone(value);
  }

  return JSON.parse(JSON.stringify(value)) as TValue;
}

function shiftIso(minutes: number): string {
  return new Date(Date.now() + minutes * 60_000).toISOString();
}

function createToken(userId: number): string {
  return `mock-token-${userId}-${Date.now()}`;
}

function createInitialState(): MockState {
  return {
    version: 1,
    users: [
      {
        id: 1,
        email: DEFAULT_USER_EMAIL,
        password: DEFAULT_USER_PASSWORD,
        name: 'Администратор'
      }
    ],
    authTokens: {},
    registration: null,
    nextUserId: 2
  };
}

function saveState(state: MockState): void {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function getState(): MockState {
  if (null !== mockState) {
    return mockState;
  }

  const rawState = localStorage.getItem(STORAGE_KEY);

  if (null === rawState) {
    mockState = createInitialState();
    saveState(mockState);
    return mockState;
  }

  try {
    mockState = JSON.parse(rawState) as MockState;
  } catch {
    mockState = createInitialState();
    saveState(mockState);
  }

  return mockState;
}

function publicUser(user: MockUser): AuthUser {
  return {
    id: user.id,
    email: user.email,
    name: user.name
  };
}

function findUserByEmail(state: MockState, email: string): MockUser | undefined {
  return state.users.find((user) => email.toLowerCase() === user.email.toLowerCase());
}

function createLoginResponse(state: MockState, user: MockUser): LoginResponse {
  const token = createToken(user.id);
  const expiresAt = shiftIso(MOCK_TOKEN_TTL_MINUTES);

  state.authTokens[token] = user.id;
  saveState(state);

  return {
    token,
    expiresAt,
    user: publicUser(user)
  };
}

export function loginWithMock(request: LoginRequest): LoginResponse {
  const state = getState();
  const user = findUserByEmail(state, request.email);

  if (undefined === user || user.password !== request.password) {
    throw new Error('Неверный email или пароль.');
  }

  return createLoginResponse(state, user);
}

export function requestRegistrationCodeWithMock(request: RegisterRequest): RequestRegistrationCodeResponse {
  const state = getState();

  state.registration = {
    email: request.email,
    password: request.password,
    code: '123456',
    codeExpiresAt: shiftIso(15),
    resendAvailableAt: shiftIso(1)
  };
  saveState(state);

  return clone({
    email: state.registration.email,
    codeExpiresAt: state.registration.codeExpiresAt,
    resendAvailableAt: state.registration.resendAvailableAt
  });
}

export function confirmRegistrationWithMock(email: string, code: string): LoginResponse {
  const state = getState();
  const registration = state.registration;

  if (null === registration || registration.email.toLowerCase() !== email.toLowerCase()) {
    throw new Error('Код подтверждения не запрошен.');
  }

  if (registration.code !== code) {
    throw new Error('Неверный код подтверждения.');
  }

  let user = findUserByEmail(state, email);

  if (undefined === user) {
    user = {
      id: state.nextUserId++,
      email,
      password: registration.password,
      name: email.split('@')[0] ?? email
    };
    state.users.push(user);
  } else {
    user.password = registration.password;
  }

  state.registration = null;
  return createLoginResponse(state, user);
}

export function logoutWithMock(token: string | null): void {
  if (null === token) {
    return;
  }

  const state = getState();
  delete state.authTokens[token];
  saveState(state);
}

export function resolveUserIdByToken(token: string | null): number | null {
  if (null === token) {
    return null;
  }

  const state = getState();
  return state.authTokens[token] ?? null;
}

export function resetMockAuthState(): MockState {
  mockState = createInitialState();
  saveState(mockState);
  return clone(mockState);
}

export function snapshotMockAuthState(): MockState {
  return clone(getState());
}
