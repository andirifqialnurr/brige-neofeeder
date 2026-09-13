import type { ReactNode } from 'react';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import { goTo } from '@/lib/router';

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

export function Topbar({ action }: { action?: ReactNode }) {
  return <header className="topbar">{action}</header>;
}

export function PageHeader({
  title,
  action,
  parents = [],
}: {
  title: string;
  action?: ReactNode;
  parents?: { label: string; href: string }[];
}) {
  return (
    <header className="page-header">
      <h1 className="sr-only">{title}</h1>
      <nav className="page-breadcrumb" aria-label="Breadcrumb">
        <ol>
          {[{ label: 'Workspace', href: '/dashboard' }, ...parents].map((item) => (
            <li key={item.href}>
              <a
                href={item.href}
                onClick={(event) => {
                  if (
                    event.button !== 0 ||
                    event.metaKey ||
                    event.ctrlKey ||
                    event.shiftKey ||
                    event.altKey
                  )
                    return;
                  event.preventDefault();
                  goTo(item.href);
                }}
              >
                {item.label}
              </a>
              <ChevronRight size={14} aria-hidden="true" />
            </li>
          ))}
          <li className="breadcrumb-current">
            <span aria-current="page" title={title}>
              {title}
            </span>
          </li>
        </ol>
      </nav>
      {action ? <div className="page-actions">{action}</div> : null}
    </header>
  );
}
