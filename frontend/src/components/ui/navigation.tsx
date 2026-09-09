import type { LucideIcon } from 'lucide-react';

type SidebarNavItem = {
  label: string;
  icon: LucideIcon;
};

export function SidebarNav({ items, activeItem }: { items: SidebarNavItem[]; activeItem: string }) {
  return (
    <nav className="nav-list" aria-label="Navigasi utama">
      {items.map((item) => {
        const Icon = item.icon;

        return (
          <a
            className={item.label === activeItem ? 'active' : undefined}
            href={`#${item.label.toLowerCase().replaceAll(' ', '-')}`}
            key={item.label}
          >
            <Icon size={18} />
            {item.label}
          </a>
        );
      })}
    </nav>
  );
}
