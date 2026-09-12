import { useEffect, useMemo, useState, useSyncExternalStore, type ReactNode } from 'react';
import { ThemeProviderContext, type Theme } from './theme-context';

const storageKey = 'bridge-neofeeder-theme';

const subscribeToSystemTheme = (callback: () => void) => {
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  media.addEventListener('change', callback);
  return () => media.removeEventListener('change', callback);
};

function applyTheme(theme: Theme) {
  const root = window.document.documentElement;
  const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  const resolvedTheme = theme === 'system' ? systemTheme : theme;

  root.classList.remove('light', 'dark');
  root.classList.add(resolvedTheme);
}

export function ThemeProvider({
  children,
  defaultTheme = 'system',
}: {
  children: ReactNode;
  defaultTheme?: Theme;
}) {
  const systemDark = useSyncExternalStore(
    subscribeToSystemTheme,
    () => window.matchMedia('(prefers-color-scheme: dark)').matches,
    () => false,
  );
  const [theme, setThemeState] = useState<Theme>(() => {
    if (typeof window === 'undefined') {
      return defaultTheme;
    }

    return (window.localStorage.getItem(storageKey) as Theme | null) ?? defaultTheme;
  });

  useEffect(() => {
    applyTheme(theme);

    if (theme !== 'system') {
      window.localStorage.setItem(storageKey, theme);
      return;
    }

    window.localStorage.removeItem(storageKey);
  }, [theme]);

  useEffect(() => {
    if (theme !== 'system') {
      return;
    }

    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const listener = () => applyTheme('system');

    media.addEventListener('change', listener);

    return () => media.removeEventListener('change', listener);
  }, [theme]);

  const resolvedTheme = theme === 'system' ? (systemDark ? 'dark' : 'light') : theme;
  const value = useMemo(
    () => ({
      theme,
      resolvedTheme,
      setTheme: setThemeState,
    }),
    [theme, resolvedTheme],
  );

  return <ThemeProviderContext.Provider value={value}>{children}</ThemeProviderContext.Provider>;
}
