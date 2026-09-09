import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

export function Notice({ icon: Icon, children }: { icon: LucideIcon; children: ReactNode }) {
  return (
    <div className="notice">
      <Icon size={18} />
      <p>{children}</p>
    </div>
  );
}
