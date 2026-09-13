import { LandingPage } from '@/components/public-pages';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { goTo } from '@/lib/router';
export function LandingScreen() {
  return <LandingPage onLogin={() => goTo('/login')} themeControl={<ThemeToggle />} />;
}
