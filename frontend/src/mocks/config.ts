const mockFlag = import.meta.env.VITE_API_MOCKS_ENABLED?.trim().toLowerCase() ?? '';

export const isMockApiEnabled = '1' === mockFlag || 'true' === mockFlag || 'yes' === mockFlag || 'on' === mockFlag;

export const mockNetworkDelayMs = 250;
