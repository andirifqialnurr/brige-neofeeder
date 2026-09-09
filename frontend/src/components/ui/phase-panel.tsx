import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

export function PhasePanel({
  icon: Icon,
  label,
  title,
  children,
}: {
  icon: LucideIcon;
  label: string;
  title: string;
  children: ReactNode;
}) {
  return (
    <article className="phase-panel">
      <div className="phase-title">
        <span className="phase-icon">
          <Icon size={20} />
        </span>
        <div>
          <span>{label}</span>
          <h3>{title}</h3>
        </div>
      </div>
      {children}
    </article>
  );
}
