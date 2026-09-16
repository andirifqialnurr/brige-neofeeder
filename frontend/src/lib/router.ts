import { useSyncExternalStore } from 'react';

export const pagePaths = {
  dashboard: '/dashboard',
  campus: '/campus',
  'neo-feeder': '/neo-feeder',
  'template-excel': '/template-excel',
  'import-batch': '/import-batch',
  mapping: '/mapping',
  automation: '/automation',
  operations: '/operations',
  audit: '/audit',
} as const;
export type PageId = keyof typeof pagePaths;
export function resolveRoute(url: string) {
  const path = new URL(url, 'http://localhost').pathname.replace(/\/$/, '') || '/';
  if (path === '/') return { screen: 'landing' as const, page: 'dashboard' as PageId };
  if (path === '/login') return { screen: 'login' as const, page: 'dashboard' as PageId };
  if (path === '/validation')
    return { screen: 'redirect' as const, page: 'import-batch' as PageId };
  const batch = path.match(
    /^\/import-batch\/([a-f\d]{8}-[a-f\d]{4}-[a-f\d]{4}-[a-f\d]{4}-[a-f\d]{12})$/i,
  );
  if (batch) return { screen: 'app' as const, page: 'import-batch' as PageId, batchId: batch[1] };
  const page = (Object.keys(pagePaths) as PageId[]).find((key) => pagePaths[key] === path);
  return { screen: page ? ('app' as const) : ('not-found' as const), page: page ?? 'dashboard' };
}
export function goTo(path: string, replace = false) {
  if (replace) history.replaceState(null, '', path);
  else if (location.pathname + location.search !== path) history.pushState(null, '', path);
  window.dispatchEvent(new PopStateEvent('popstate'));
  window.scrollTo(0, 0);
}
export function loginDestination(search: string) {
  const next = new URLSearchParams(search).get('next') ?? '/dashboard';
  return next.startsWith('/') &&
    !next.startsWith('//') &&
    !next.includes('\\') &&
    resolveRoute(next).screen === 'app'
    ? next
    : '/dashboard';
}
const subscribe = (callback: () => void) => {
  window.addEventListener('popstate', callback);
  return () => window.removeEventListener('popstate', callback);
};
export function useRoute() {
  const url = useSyncExternalStore(subscribe, () => location.pathname + location.search);
  return { ...resolveRoute(url), url };
}
