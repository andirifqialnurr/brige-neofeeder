import { useEffect, useState } from 'react';
import { Activity, RefreshCcw } from 'lucide-react';
import {
  DataTable,
  EmptyState,
  ErrorState,
  IconButton,
  LoadingState,
  PageHeader,
  SectionHeader,
  StatusBadge,
  WorkspacePanel,
} from '@/components/ui';
import { useWorkspace } from '@/hooks/workspace-context';
import { formatDateTime } from '@/hooks/use-workspace-controller';
import { requestApi, type OperationalSnapshot } from '@/lib/api';

const labels = {
  ok: 'Normal',
  unavailable: 'Tidak terhubung',
  stale: 'Terlambat',
  missing: 'Belum terdeteksi',
};

export function OperationsPage() {
  const { authUser } = useWorkspace();
  const [data, setData] = useState<OperationalSnapshot | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    if (authUser?.role !== 'admin') return;
    const controller = new AbortController();
    let pending = false;
    const load = async () => {
      if (document.hidden || pending) return;
      pending = true;
      setBusy(true);
      try {
        const response = await requestApi<{ data: OperationalSnapshot }>('operations/health', {
          signal: controller.signal,
        });
        if (!controller.signal.aborted) {
          setData(response.data);
          setError('');
        }
      } catch (err) {
        if (!controller.signal.aborted)
          setError(err instanceof Error ? err.message : 'Status gagal dimuat.');
      } finally {
        pending = false;
        if (!controller.signal.aborted) setBusy(false);
      }
    };
    void load();
    const timer = window.setInterval(() => void load(), 15000);
    document.addEventListener('visibilitychange', load);
    return () => {
      controller.abort();
      clearInterval(timer);
      document.removeEventListener('visibilitychange', load);
    };
  }, [authUser?.role, revision]);
  if (authUser?.role !== 'admin') return <ErrorState title="Halaman ini hanya untuk admin" />;
  return (
    <>
      <PageHeader
        title="Operasional"
        action={
          <IconButton
            label="Muat ulang status"
            icon={RefreshCcw}
            disabled={busy}
            onClick={() => setRevision((value) => value + 1)}
          />
        }
      />
      {error && <ErrorState title="Status gagal diperbarui" description={error} />}
      {!data ? (
        <LoadingState label="Memeriksa layanan" />
      ) : (
        <>
          <WorkspacePanel>
            <SectionHeader
              title="Kesehatan layanan"
              description="Worker terdeteksi setelah memproses tugas pemeriksaan. Status terlambat muncul setelah tiga menit tanpa kabar."
              action={<span>Diperiksa {formatDateTime(data.checked_at)}</span>}
            />
            <DataTable
              columns={['Layanan', 'Status', 'Kabar terakhir', 'Jumlah antrean']}
              rows={data.checks.map((check) => [
                check.label,
                <StatusBadge key={check.key} tone={check.status === 'ok' ? 'success' : 'warning'}>
                  {labels[check.status]}
                </StatusBadge>,
                formatDateTime(check.last_seen_at ?? null),
                check.pending_jobs ?? '—',
              ])}
            />
          </WorkspacePanel>
          <WorkspacePanel>
            <SectionHeader
              title={`Job gagal (${data.failed_jobs_count ?? '—'})`}
              description="Menampilkan 20 kegagalan terakhir. Tinjau hasil pengiriman pada Import Batch sebelum melakukan tindakan pemulihan."
            />
            <DataTable
              columns={['ID job', 'Koneksi', 'Antrean', 'Waktu gagal']}
              rows={data.recent_failed_jobs.map((job) => [
                job.uuid,
                job.connection,
                job.queue,
                formatDateTime(job.failed_at),
              ])}
              emptyState={
                <EmptyState
                  icon={Activity}
                  title={
                    data.failed_jobs_count === null
                      ? 'Riwayat belum dapat diperiksa'
                      : 'Belum ada job gagal'
                  }
                />
              }
            />
          </WorkspacePanel>
        </>
      )}
    </>
  );
}
