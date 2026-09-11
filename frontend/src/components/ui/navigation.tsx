import type { LucideIcon } from 'lucide-react';

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
      {items.map((item) => {
        const Icon = item.icon;
        const isActive = item.id === activeItem;

        return (
          <button
            aria-current={isActive ? 'page' : undefined}
            className={isActive ? 'active' : undefined}
            key={item.id}
            onClick={() => onItemSelect?.(item)}
            type="button"
          >
            <Icon size={18} />
            {item.label}
          </button>
        );
      })}
    </nav>
  );
}
