import type { ReactNode } from 'react';
import { HelpTip } from './help-tip';

export function SectionHeader({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="section-heading">
      <div className="section-title">
        <h2>{title}</h2>
        {description ? <HelpTip text={description} label={`Tentang ${title}`} /> : null}
      </div>
      {action}
    </div>
  );
}

export function WorkspacePanel({ children }: { children: ReactNode }) {
  return <section className="workspace">{children}</section>;
}
