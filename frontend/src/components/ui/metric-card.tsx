import type { LucideIcon } from 'lucide-react';

type MetricTone = 'success' | 'warning' | 'muted' | 'info';

export function MetricCard({
  icon: Icon,
  label,
  value,
  helper,
  tone = 'muted',
}: {
  icon: LucideIcon;
  label: string;
  value: string;
  helper?: string;
  tone?: MetricTone;
}) {
  return (
    <article className="status-card">
      <div className={`metric-icon ${tone}`}>
        <Icon size={20} />
      </div>
      <span>{label}</span>
      <strong>{value}</strong>
      {helper ? <small>{helper}</small> : null}
    </article>
  );
}
