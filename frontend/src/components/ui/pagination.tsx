import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { PageMeta } from '@/lib/api';
import { IconButton } from './button';

export function Pagination({ meta, onChange, disabled = false }: {
  meta: PageMeta; onChange: (page: number) => void; disabled?: boolean;
}) {
  return <nav className="pagination" aria-label="Pagination">
    <span>{meta.total} data</span>
    <div>
      <IconButton label="Halaman sebelumnya" icon={ChevronLeft} disabled={disabled || meta.current_page <= 1} onClick={() => onChange(meta.current_page - 1)} />
      <span aria-live="polite">{meta.current_page} / {meta.last_page}</span>
      <IconButton label="Halaman berikutnya" icon={ChevronRight} disabled={disabled || meta.current_page >= meta.last_page} onClick={() => onChange(meta.current_page + 1)} />
    </div>
  </nav>;
}
