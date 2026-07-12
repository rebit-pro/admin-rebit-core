import { useHead } from '@unhead/vue';
import { computed, type ComputedRef } from 'vue';
import { useRoute } from 'vue-router';

const SITE_NAME = 'ReBit Admin Core';
const DEFAULT_DESCRIPTION = 'ReBit Admin Core — универсальная админ-панель ReBit Studio.';

export function useRouteSeo(): void {
  const route = useRoute();

  const title: ComputedRef<string> = computed(() => {
    const pageTitle = route.meta['title'];
    return pageTitle ? `${pageTitle} — ${SITE_NAME}` : SITE_NAME;
  });

  const description: ComputedRef<string> = computed(() => {
    return (route.meta['description'] as string | undefined) ?? DEFAULT_DESCRIPTION;
  });

  useHead({
    title,
    meta: [
      { name: 'description', content: description },
      { property: 'og:title', content: title },
      { property: 'og:description', content: description },
      { property: 'og:type', content: 'website' },
      { property: 'og:site_name', content: SITE_NAME },
      { property: 'og:locale', content: 'ru_RU' },
      { property: 'og:image', content: '/favicon.svg' },
      { name: 'twitter:card', content: 'summary' },
      { name: 'twitter:title', content: title },
      { name: 'twitter:description', content: description },
      { name: 'twitter:image', content: '/favicon.svg' }
    ]
  });
}
