import api from './http';

export interface ApiConnectionRequest {
  apiKey: string;
  secretKey: string;
  mode: 'testnet' | 'mainnet';
}

export type ApiConnectionMode = 'testnet' | 'mainnet';

export type ApiConnectionState = 'active' | 'invalid' | 'revoked' | 'pending_verification';

type ApiEnumDto<TValue extends string> = {
  value: TValue;
  name: string;
};

type ApiConnectionStatusResponse = {
  connected: boolean;
  mode: ApiConnectionMode | ApiEnumDto<ApiConnectionMode> | null;
  status: ApiConnectionState | ApiEnumDto<ApiConnectionState> | null;
  id?: number | null;
  userId?: number | null;
  maskedApiKey?: string | null;
  createdAt?: string | null;
  verifiedAt?: string | null;
};

export interface ApiConnectionStatus {
  connected: boolean;
  mode: ApiConnectionMode | null;
  modeLabel: string | null;
  status: ApiConnectionState | null;
  statusLabel: string | null;
  id: number | null;
  userId: number | null;
  maskedApiKey: string | null;
  createdAt: string | null;
  verifiedAt: string | null;
}

function normalizeApiEnum<TValue extends string>(
  value: TValue | ApiEnumDto<TValue> | null | undefined
): { value: TValue | null; label: string | null } {
  if (null === value || undefined === value) {
    return {
      value: null,
      label: null
    };
  }

  if ('string' === typeof value) {
    return {
      value,
      label: null
    };
  }

  return {
    value: value.value,
    label: value.name
  };
}

function normalizeConnectionStatus(response: ApiConnectionStatusResponse): ApiConnectionStatus {
  const normalizedMode = normalizeApiEnum(response.mode);
  const normalizedStatus = normalizeApiEnum(response.status);

  return {
    connected: true === response.connected,
    mode: normalizedMode.value,
    modeLabel: normalizedMode.label,
    status: normalizedStatus.value,
    statusLabel: normalizedStatus.label,
    id: response.id ?? null,
    userId: response.userId ?? null,
    maskedApiKey: response.maskedApiKey ?? null,
    createdAt: response.createdAt ?? null,
    verifiedAt: response.verifiedAt ?? null
  };
}

export const identityApi = {
  connect(data: ApiConnectionRequest): Promise<ApiConnectionStatus> {
    return api.post('/api/v1/identity/connection', data).then((r) => normalizeConnectionStatus(r.data as ApiConnectionStatusResponse));
  },

  disconnect(): Promise<void> {
    return api.delete('/api/v1/identity/connection');
  },

  verify(): Promise<ApiConnectionStatus> {
    return api.post('/api/v1/identity/connection/verify').then((r) => normalizeConnectionStatus(r.data as ApiConnectionStatusResponse));
  },

  status(): Promise<ApiConnectionStatus> {
    return api.get('/api/v1/identity/connection/status').then((r) => normalizeConnectionStatus(r.data as ApiConnectionStatusResponse));
  }
};
