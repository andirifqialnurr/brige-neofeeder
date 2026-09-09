type StatusBadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'destructive';

export function StatusBadge({ children, tone = 'neutral' }: { children: string; tone?: StatusBadgeTone }) {
  return <span className={`badge badge-${tone}`}>{children}</span>;
}
