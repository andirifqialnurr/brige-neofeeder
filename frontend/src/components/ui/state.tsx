import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { AlertCircle, Loader2 } from 'lucide-react';

export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
}: {
  icon: LucideIcon;
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="empty-state">
      <Icon size={22} />
      <strong>{title}</strong>
      {description ? <span>{description}</span> : null}
      {action}
    </div>
  );
}

export function LoadingState({ label = 'Memuat data' }: { label?: string }) {
  return (
    <div className="loading-state">
      <Loader2 size={18} />
      <span>{label}</span>
    </div>
  );
}

export function ErrorState({ title, description }: { title: string; description?: string }) {
  return (
    <div className="error-state">
      <AlertCircle size={18} />
      <div>
        <strong>{title}</strong>
        {description ? <span>{description}</span> : null}
      </div>
    </div>
  );
}
