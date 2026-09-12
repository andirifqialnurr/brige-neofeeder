import { useEffect, useId, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { IconButton } from './button';

export function FormDialog({
  open,
  title,
  onClose,
  busy = false,
  children,
}: {
  open: boolean;
  title: string;
  onClose: () => void;
  busy?: boolean;
  children: ReactNode;
}) {
  const dialog = useRef<HTMLDialogElement>(null);
  const titleId = useId();

  useEffect(() => {
    const element = dialog.current;
    if (!element || !open) return;
    const trigger = document.activeElement as HTMLElement | null;
    element.showModal();
    element.querySelector<HTMLElement>('input:not([disabled]), select:not([disabled])')?.focus();
    return () => {
      element.close();
      if (trigger?.isConnected) trigger.focus();
    };
  }, [open]);

  return (
    <dialog
      ref={dialog}
      className="form-dialog"
      aria-labelledby={titleId}
      onCancel={(event) => {
        event.preventDefault();
        if (!busy) onClose();
      }}
    >
      <header className="dialog-header">
        <h2 id={titleId}>{title}</h2>
        <IconButton label="Tutup dialog" icon={X} onClick={onClose} disabled={busy} />
      </header>
      <div className="dialog-body">{open ? children : null}</div>
    </dialog>
  );
}
