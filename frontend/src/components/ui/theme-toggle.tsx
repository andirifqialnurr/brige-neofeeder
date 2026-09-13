import { Moon, Sun } from 'lucide-react';
import { useTheme } from '@/hooks/use-theme';
import { IconButton } from './button';
export function ThemeToggle() {
  const { resolvedTheme, setTheme } = useTheme();
  return (
    <IconButton
      label={resolvedTheme === 'dark' ? 'Gunakan tema terang' : 'Gunakan tema gelap'}
      icon={resolvedTheme === 'dark' ? Sun : Moon}
      onClick={() => setTheme(resolvedTheme === 'dark' ? 'light' : 'dark')}
    />
  );
}
