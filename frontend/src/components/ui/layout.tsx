import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

export function AppShell({ sidebar, children }: { sidebar: ReactNode; children: ReactNode }) {
  return (
    <main className="app-shell">
      {sidebar}
      <section className="content" id="main-content">
        {children}
      </section>
    </main>
  );
}

export function Sidebar({ children }: { children: ReactNode }) {
  return <aside className="sidebar">{children}</aside>;
}

export function Brand({
  icon: Icon,
  title,
  subtitle,
}: {
  icon: LucideIcon;
  title: string;
  subtitle: string;
}) {
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
        <span className="breadcrumb">
          {eyebrow} <span aria-hidden="true">/</span> {title}
        </span>
      </div>
      {action}
    </header>
  );
}

export function PageHeader({
  eyebrow,
  title,
  action,
}: {
  eyebrow?: string;
  title: string;
  action?: ReactNode;
}) {
  return (
    <section className="dashboard-header">
      <div>
        {eyebrow ? <span className="page-eyebrow">{eyebrow}</span> : null}
        <h1>{title}</h1>
      </div>
      {action}
    </section>
  );
}
