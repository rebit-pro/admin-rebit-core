export const DirAttrSet = (dir: 'ltr' | 'rtl') => {
  // Check if document exists for SSR
  if (typeof document !== 'undefined') document.documentElement.setAttribute('dir', dir);
};
