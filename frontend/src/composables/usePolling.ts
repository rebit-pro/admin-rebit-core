import { onUnmounted, ref, type Ref } from 'vue';

interface UsePollingReturn {
  isActive: Ref<boolean>;
  start: () => void;
  stop: () => void;
}

export function usePolling(callback: () => Promise<void> | void, intervalMs = 10000): UsePollingReturn {
  const isActive = ref(false);
  let timer: ReturnType<typeof setTimeout> | null = null;
  let generation = 0;

  async function tick(currentGeneration: number): Promise<void> {
    if (!isActive.value || currentGeneration !== generation) return;

    try {
      await callback();
    } finally {
      if (isActive.value && currentGeneration === generation) {
        timer = setTimeout(() => void tick(currentGeneration), intervalMs);
      }
    }
  }

  function start(): void {
    if (null !== timer) {
      clearTimeout(timer);
      timer = null;
    }

    generation += 1;
    isActive.value = true;
    timer = setTimeout(() => void tick(generation), intervalMs);
  }

  function stop(): void {
    generation += 1;

    if (null !== timer) {
      clearTimeout(timer);
      timer = null;
    }
    isActive.value = false;
  }

  onUnmounted(stop);

  return { isActive, start, stop };
}
