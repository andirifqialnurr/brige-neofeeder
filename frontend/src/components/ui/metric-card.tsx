type MetricTone = 'success' | 'warning' | 'muted' | 'info';

export function MetricCard({ label, value, tone = 'muted' }: { label: string; value: string; tone?: MetricTone }) {
  return (
    <article className="status-card">
      <span>{label}</span>
      <strong className={tone}>{value}</strong>
    </article>
  );
}
