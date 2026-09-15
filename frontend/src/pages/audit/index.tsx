import { useEffect, useState } from 'react';
import { Download, ScrollText } from 'lucide-react';
import {
  AppButton,
  DataTable,
  EmptyState,
  ErrorState,
  LoadingState,
  PageHeader,
  Pagination,
  WorkspacePanel,
} from '@/components/ui';
import { Select } from '@/components/ui/select';
import { useWorkspace } from '@/hooks/workspace-context';
import { formatDateTime } from '@/hooks/use-workspace-controller';
import { requestApi, downloadApiFile, type AuditEntry, type PageResult } from '@/lib/api';

export function AuditPage() {
  const { tenants, authUser } = useWorkspace();
  const [search, setSearch] = useState('');
  const [tenant, setTenant] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [page, setPage] = useState(1);
  const [data, setData] = useState<PageResult<AuditEntry> | null>(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState('');
  const query = new URLSearchParams({
    search,
    ...(tenant ? { tenant_id: tenant } : {}),
    ...(from ? { from } : {}),
    ...(to ? { to } : {}),
  }).toString();
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');
    const timer = setTimeout(() => {
      requestApi<PageResult<AuditEntry>>(`audit-logs?${query}&page=${page}`, {
        signal: controller.signal,
      })
        .then((value) => {
          if (!controller.signal.aborted) setData(value);
        })
        .catch((err) => {
          if (!controller.signal.aborted) setError(err.message);
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 200);
    return () => {
      controller.abort();
      clearTimeout(timer);
    };
  }, [query, page]);
  async function download() {
    if (exporting) return;
    setExporting(true);
    setError('');
    try {
      await downloadApiFile(`audit-logs/export?${query}`, 'audit-log.csv');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Ekspor gagal.');
    } finally {
      setExporting(false);
    }
  }
  return (
    <>
      <PageHeader
        title="Audit"
        action={
          <>
            {authUser?.role === 'admin' && (
              <label className="inline-field">
                <span className="sr-only">Kampus audit</span>
                <Select
                  value={tenant}
                  onChange={(event) => {
                    setTenant(event.target.value);
                    setPage(1);
                  }}
                >
                  <option value="">Semua kampus</option>
                  {tenants.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </Select>
              </label>
            )}
            <input
              aria-label="Cari peristiwa atau ID objek"
              placeholder="Cari peristiwa / ID"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
            />
            <input
              type="date"
              aria-label="Dari tanggal"
              value={from}
              onChange={(event) => {
                setFrom(event.target.value);
                setPage(1);
              }}
            />
            <input
              type="date"
              aria-label="Sampai tanggal"
              value={to}
              onChange={(event) => {
                setTo(event.target.value);
                setPage(1);
              }}
            />
            <AppButton icon={Download} disabled={exporting || loading} onClick={download}>
              {exporting ? 'Mengekspor...' : 'Ekspor CSV'}
            </AppButton>
          </>
        }
      />
      {!from && (
        <p className="muted">
          Menampilkan 30 hari terakhir. Pilih tanggal awal untuk riwayat lebih lama.
        </p>
      )}
      {error && <ErrorState title="Permintaan gagal" description={error} />}
      <WorkspacePanel>
        {loading ? (
          <LoadingState label="Memuat audit" />
        ) : (
          <DataTable
            columns={['Waktu', 'Kampus', 'Aktor', 'Peristiwa', 'ID objek']}
            rows={data?.data.map((entry) => [
              formatDateTime(entry.created_at),
              entry.tenant_name,
              entry.actor_name,
              entry.event,
              entry.subject_id ?? '—',
            ])}
            emptyState={
              <EmptyState icon={ScrollText} title="Tidak ada peristiwa pada filter ini" />
            }
          />
        )}
        {data && <Pagination meta={data.meta} disabled={loading} onChange={setPage} />}
      </WorkspacePanel>
    </>
  );
}
