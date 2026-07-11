import type { AxiosAdapter, AxiosRequestConfig, AxiosResponse } from 'axios';
import { mockNetworkDelayMs } from './config';
import { confirmRegistrationWithMock, loginWithMock, logoutWithMock, requestRegistrationCodeWithMock } from './database';

class MockHttpError extends Error {
  public readonly status: number;

  public constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

type MockEnvelope<TData> = {
  data: TData | null;
  error?: {
    message: string;
  };
};

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function normalizeMethod(config: AxiosRequestConfig): string {
  return (config.method ?? 'get').toLowerCase();
}

function normalizePath(config: AxiosRequestConfig): string {
  const rawUrl = config.url ?? '/';
  const normalizedUrl = rawUrl.startsWith('http') ? rawUrl : `https://mock.rebit.local${rawUrl}`;

  return new URL(normalizedUrl).pathname;
}

function normalizeToken(config: AxiosRequestConfig): string | null {
  const authorizationHeader =
    (config.headers as Record<string, string | undefined> | undefined)?.Authorization ??
    (config.headers as Record<string, string | undefined> | undefined)?.authorization ??
    null;

  if (null === authorizationHeader || !authorizationHeader.startsWith('Bearer ')) {
    return null;
  }

  return authorizationHeader.slice('Bearer '.length);
}

function normalizeBody<TBody>(config: AxiosRequestConfig): TBody {
  if (undefined === config.data || null === config.data || '' === config.data) {
    return {} as TBody;
  }

  if ('string' === typeof config.data) {
    return JSON.parse(config.data) as TBody;
  }

  return config.data as TBody;
}

function ok<TData>(data: TData): MockEnvelope<TData> {
  return { data };
}

async function handleMockRequest(config: AxiosRequestConfig): Promise<MockEnvelope<unknown>> {
  const method = normalizeMethod(config);
  const path = normalizePath(config);

  if ('post' === method && '/api/v1/auth/login' === path) {
    return ok(loginWithMock(normalizeBody(config)));
  }

  if ('post' === method && '/api/v1/auth/register/request-code' === path) {
    return ok(requestRegistrationCodeWithMock(normalizeBody(config)));
  }

  if ('post' === method && '/api/v1/auth/register/confirm' === path) {
    const body = normalizeBody<{ email: string; code: string }>(config);
    return ok(confirmRegistrationWithMock(body.email, body.code));
  }

  if ('post' === method && '/api/v1/auth/logout' === path) {
    logoutWithMock(normalizeToken(config));
    return ok([]);
  }

  throw new MockHttpError(404, 'Mock API route not found.');
}

export const mockApiAdapter: AxiosAdapter = async (config): Promise<AxiosResponse> => {
  await sleep(mockNetworkDelayMs);

  try {
    const response = await handleMockRequest(config);

    return {
      data: response,
      status: 200,
      statusText: 'OK',
      headers: {},
      config,
      request: undefined
    };
  } catch (error) {
    const status = error instanceof MockHttpError ? error.status : 400;
    const message = error instanceof Error ? error.message : 'Mock API error.';

    return Promise.reject({
      response: {
        data: {
          error: {
            message
          }
        },
        status,
        statusText: 'Error',
        headers: {},
        config,
        request: undefined
      },
      config,
      request: undefined,
      message
    });
  }
};
