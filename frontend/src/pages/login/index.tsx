import { LoginPage } from '@/components/public-pages';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { useWorkspace } from '@/hooks/workspace-context';
import { goTo } from '@/lib/router';
export function LoginScreen() {
  const {
    loginEmail,
    loginPassword,
    setLoginEmail,
    setLoginPassword,
    handleLogin,
    loginState,
    loginError,
  } = useWorkspace();
  return (
    <LoginPage
      themeControl={<ThemeToggle />}
      onBack={() => goTo('/')}
      email={loginEmail}
      password={loginPassword}
      onEmailChange={setLoginEmail}
      onPasswordChange={setLoginPassword}
      onSubmit={handleLogin}
      loading={loginState === 'loading'}
      error={loginState === 'error' ? loginError : ''}
    />
  );
}
