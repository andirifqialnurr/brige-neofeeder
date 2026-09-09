import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

type ButtonVariant = 'primary' | 'secondary' | 'ghost';

type AppButtonProps = {
  children: ReactNode;
  icon?: LucideIcon;
  type?: 'button' | 'submit' | 'reset';
  variant?: ButtonVariant;
};

export function AppButton({ children, icon: Icon, type = 'button', variant = 'primary' }: AppButtonProps) {
  return (
    <button className={`app-button app-button-${variant}`} type={type}>
      {Icon ? <Icon size={18} /> : null}
      {children}
    </button>
  );
}
