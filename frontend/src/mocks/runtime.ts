import { isMockApiEnabled } from './config';
import { resetMockAuthState, snapshotMockAuthState } from './database';

declare global {
  interface Window {
    __REBIT_MOCKS__?: {
      reset: typeof resetMockAuthState;
      snapshot: typeof snapshotMockAuthState;
    };
  }
}

export function initializeMockRuntime(): void {
  if (!isMockApiEnabled || 'undefined' === typeof window) {
    return;
  }

  window.__REBIT_MOCKS__ = {
    reset: resetMockAuthState,
    snapshot: snapshotMockAuthState
  };
}
