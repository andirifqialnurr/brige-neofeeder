import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

type ButtonVariant = 'primary' | 'secondary' | 'ghost';
type Tone = 'success' | 'warning' | 'muted' | 'info';

type ButtonProps = {
  children: ReactNode;
  icon?: LucideIcon;
  type?: 'button' | 'submit' | 'reset';
  variant?: ButtonVariant;
};

export function AppButton({ children, icon: Icon, type = 'button', variant = 'primary' }: ButtonProps) {
  return (
    <button className={`app-button app-button-${variant}`} type={type}>
      {Icon ? <Icon size={18} /> : null}
      {children}
    </button>
  );
}

export function AppShell({ sidebar, children }: { sidebar: ReactNode; children: ReactNode }) {
  return (
    <main className="app-shell">
      {sidebar}
      <section className="content">{children}</section>
    </main>
  );
}

export function Sidebar({ children }: { children: ReactNode }) {
  return <aside className="sidebar">{children}</aside>;
}

export function Brand({ icon: Icon, title, subtitle }: { icon: LucideIcon; title: string; subtitle: string }) {
  return (
    <div className="brand">
      <span className="brand-icon">
        <Icon size={24} />
      </span>
      <div>
        <strong>{title}</strong>
        <span>{subtitle}</span>
      </div>
    </div>
  );
}

export function SidebarNav({ items, activeItem }: { items: string[]; activeItem: string }) {
  return (
    <nav className="nav-list" aria-label="Navigasi utama">
      {items.map((item) => (
        <a className={item === activeItem ? 'active' : undefined} href={`#${item.toLowerCase().replaceAll(' ', '-')}`} key={item}>
          {item}
        </a>
      ))}
    </nav>
  );
}

export function Topbar({
  eyebrow,
  title,
  action,
}: {
  eyebrow: string;
  title: string;
  action?: ReactNode;
}) {
  return (
    <header className="topbar">
      <div>
        <p className="eyebrow">{eyebrow}</p>
        <h1>{title}</h1>
      </div>
      {action}
    </header>
  );
}

export function SectionHeader({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="section-heading">
      <div>
        <h2>{title}</h2>
        {description ? <p>{description}</p> : null}
      </div>
      {action}
    </div>
  );
}

export function MetricCard({ label, value, tone = 'muted' }: { label: string; value: string; tone?: Tone }) {
  return (
    <article className="status-card">
      <span>{label}</span>
      <strong className={tone}>{value}</strong>
    </article>
  );
}

export function WorkspacePanel({ children }: { children: ReactNode }) {
  return <section className="workspace">{children}</section>;
}

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

export function Notice({ icon: Icon, children }: { icon: LucideIcon; children: ReactNode }) {
  return (
    <div className="notice">
      <Icon size={18} />
      <p>{children}</p>
    </div>
  );
}
