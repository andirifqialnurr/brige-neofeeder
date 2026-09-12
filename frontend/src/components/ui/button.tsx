import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

type ButtonVariant = 'primary' | 'secondary' | 'ghost';

type AppButtonProps = {
  children: ReactNode;
  disabled?: boolean;
  icon?: LucideIcon;
  onClick?: () => void;
  type?: 'button' | 'submit' | 'reset';
  variant?: ButtonVariant;
};

export function AppButton({
  children,
  disabled = false,
  icon: Icon,
  onClick,
  type = 'button',
  variant = 'primary',
}: AppButtonProps) {
  return (
    <button className={`app-button app-button-${variant}`} disabled={disabled} onClick={onClick} type={type}>
      {Icon ? <Icon size={18} /> : null}
      {children}
    </button>
  );
}
