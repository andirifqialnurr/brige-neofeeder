import type { LucideIcon } from 'lucide-react';
import { pagePaths, type PageId } from '@/lib/router';

export type SidebarNavItem = {
  id: string;
  label: string;
  icon: LucideIcon;
};

export function SidebarNav({
  items,
  activeItem,
  onItemSelect,
}: {
  items: ReadonlyArray<SidebarNavItem>;
  activeItem: string;
  onItemSelect?: (item: SidebarNavItem) => void;
}) {
  return (
    <nav className="nav-list" aria-label="Navigasi utama">
      {['Operasional', 'Pengaturan', 'Otomatisasi'].map((group) => (
        <div className="nav-group" key={group}>
          <span className="nav-group-label">{group}</span>
          {items
            .filter(
              (item) =>
                (item.id === 'mapping'
                  ? 'Otomatisasi'
                  : ['campus', 'neo-feeder'].includes(item.id)
                    ? 'Pengaturan'
                    : 'Operasional') === group,
            )
            .map((item) => {
              const Icon = item.icon;
              const isActive = item.id === activeItem;

              return (
                <a
                  aria-current={isActive ? 'page' : undefined}
                  className={isActive ? 'active' : undefined}
                  key={item.id}
                  href={pagePaths[item.id as PageId]}
                  onClick={(event) => {
                    if (
                      event.button !== 0 ||
                      event.ctrlKey ||
                      event.metaKey ||
                      event.shiftKey ||
                      event.altKey
                    )
                      return;
                    event.preventDefault();
                    onItemSelect?.(item);
                  }}
                >
                  <Icon size={18} />
                  {item.label}
                </a>
              );
            })}
        </div>
      ))}
    </nav>
  );
}
