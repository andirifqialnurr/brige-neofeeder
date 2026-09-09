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
