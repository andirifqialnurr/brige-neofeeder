import { useState, type ReactNode } from 'react';
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
    <button
      className={`app-button app-button-${variant}`}
      disabled={disabled}
      onClick={onClick}
      type={type}
    >
      {Icon ? <Icon size={18} /> : null}
      {children}
    </button>
  );
}

export function IconButton({
  label,
  icon: Icon,
  onClick,
  disabled = false,
}: {
  label: string;
  icon: LucideIcon;
  onClick: () => void;
  disabled?: boolean;
}) {
  const [dismissed, setDismissed] = useState(false);
  return (
    <span className={`icon-control${dismissed ? ' tooltip-dismissed' : ''}`}>
      <button
        aria-label={label}
        className="icon-button"
        disabled={disabled}
        onClick={onClick}
        onFocus={() => setDismissed(false)}
        onMouseEnter={() => setDismissed(false)}
        onKeyDown={(event) => {
          if (event.key === 'Escape') setDismissed(true);
        }}
        type="button"
      >
        <Icon size={18} aria-hidden="true" />
      </button>
      <span className="icon-tooltip" aria-hidden="true">
        {label}
      </span>
    </span>
  );
}
