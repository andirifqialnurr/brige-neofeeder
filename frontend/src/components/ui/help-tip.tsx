import { Info } from 'lucide-react';
import { useId, useState } from 'react';

export function HelpTip({ text, label = 'Informasi' }: { text: string; label?: string }) {
  const [open, setOpen] = useState(false);
  const id = useId();
  return (
    <span
      className="help-tip"
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false);
      }}
    >
      <button
        aria-label={label}
        aria-expanded={open}
        aria-controls={id}
        type="button"
        className="help-trigger"
        onClick={() => setOpen(!open)}
        onKeyDown={(event) => {
          if (event.key === 'Escape') setOpen(false);
        }}
      >
        <Info size={16} aria-hidden="true" />
      </button>
      {open ? (
        <span className="help-content" id={id}>
          {text}
        </span>
      ) : null}
    </span>
  );
}
