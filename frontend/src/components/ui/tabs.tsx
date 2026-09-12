import { useRef } from 'react';

export function ViewTabs<T extends string>({
  value,
  onChange,
  items,
  label,
}: {
  value: T;
  onChange: (value: T) => void;
  items: ReadonlyArray<{ id: T; label: string }>;
  label: string;
}) {
  const refs = useRef<Array<HTMLButtonElement | null>>([]);
  return (
    <div className="view-tabs" role="tablist" aria-label={label}>
      {items.map((item, index) => (
        <button
          key={item.id}
          id={`tab-${item.id}`}
          ref={(element) => {
            refs.current[index] = element;
          }}
          role="tab"
          aria-selected={value === item.id}
          aria-controls={`panel-${item.id}`}
          tabIndex={value === item.id ? 0 : -1}
          type="button"
          onClick={() => onChange(item.id)}
          onKeyDown={(event) => {
            let next = index;
            if (event.key === 'ArrowRight') next = (index + 1) % items.length;
            else if (event.key === 'ArrowLeft') next = (index - 1 + items.length) % items.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = items.length - 1;
            else return;
            event.preventDefault();
            onChange(items[next].id);
            refs.current[next]?.focus();
          }}
        >
          {item.label}
        </button>
      ))}
    </div>
  );
}
