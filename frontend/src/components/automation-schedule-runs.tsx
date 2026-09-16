import { FileClock, RefreshCcw } from 'lucide-react';
import { useEffect, useState } from 'react';

import {
  DataTable,
  EmptyState,
  ErrorState,
  FormDialog,
  IconButton,
  LoadingState,
  Pagination,
  Select,
  StatusBadge,
} from '@/components/ui';
import { formatDateTime } from '@/hooks/use-workspace-controller';
import {
  getAutomationScheduleRuns,
  type AutomationSchedule,
  type AutomationScheduleRun,
  type AutomationScheduleRunStatus,
  type PageResult,
} from '@/lib/api';

const runStatusLabels: Record<AutomationScheduleRunStatus, string> = {
  running: 'Berjalan',
  success: 'Berhasil',
  failed: 'Gagal',
};
const runStatusTones: Record<AutomationScheduleRunStatus, 'info' | 'success' | 'destructive'> = {
  running: 'info',
  success: 'success',
  failed: 'destructive',
};

function formatScheduleRunDuration(durationMs: number | null) {
  if (durationMs === null) return '-';
  if (durationMs < 1000) return `${durationMs} ms`;
  return `${(durationMs / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} dtk`;
}

export function AutomationScheduleRunsDialog({
  schedule,
  onClose,
}: {
  schedule: AutomationSchedule | null;
  onClose: () => void;
}) {
  const [status, setStatus] = useState<AutomationScheduleRunStatus | ''>('');
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<PageResult<AutomationScheduleRun> | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [reloadToken, setReloadToken] = useState(0);
  const scheduleId = schedule?.id ?? null;

  useEffect(() => {
    if (!scheduleId) {
      setResult(null);
      setError('');
      return undefined;
    }

    const controller = new AbortController();
    setLoading(true);
    setError('');
    void getAutomationScheduleRuns(scheduleId, {
      page,
      status: status || undefined,
      signal: controller.signal,
    })
      .then(setResult)
      .catch((loadError) => {
        if (!(loadError instanceof DOMException && loadError.name === 'AbortError')) {
          setResult(null);
          setError(loadError instanceof Error ? loadError.message : 'Riwayat belum bisa dimuat.');
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });

    return () => controller.abort();
  }, [page, reloadToken, scheduleId, status]);

  return (
    <FormDialog
      open={schedule !== null}
      title={`Riwayat ${schedule?.name ?? 'schedule'}`}
      onClose={onClose}
    >
      {schedule ? (
        <div className="schedule-runs">
          <div className="schedule-runs-toolbar">
            <label>
              Status
              <Select
                value={status}
                onChange={(event) => {
                  setStatus(event.target.value as AutomationScheduleRunStatus | '');
                  setPage(1);
                }}
                disabled={loading}
              >
                <option value="">Semua status</option>
                {Object.entries(runStatusLabels).map(([value, label]) => (
                  <option value={value} key={value}>
                    {label}
                  </option>
                ))}
              </Select>
            </label>
            <IconButton
              label="Muat ulang riwayat"
              icon={RefreshCcw}
              disabled={loading}
              onClick={() => setReloadToken((value) => value + 1)}
            />
          </div>

          {loading ? <LoadingState label="Memuat riwayat" /> : null}
          {!loading && error ? (
            <ErrorState title="Riwayat gagal dimuat" description={error} />
          ) : null}
          {!loading && !error && result ? (
            <>
              <DataTable
                columns={['Status', 'Mulai', 'Selesai', 'Durasi', 'Batch', 'Pesan']}
                rows={result.data.map((run) => [
                  <StatusBadge key={run.id} tone={runStatusTones[run.status]}>
                    {runStatusLabels[run.status]}
                  </StatusBadge>,
                  formatDateTime(run.started_at),
                  formatDateTime(run.completed_at),
                  formatScheduleRunDuration(run.duration_ms),
                  run.last_batch_id ? (
                    <span className="mono" title={run.last_batch_id}>
                      {run.last_batch_id}
                    </span>
                  ) : (
                    '-'
                  ),
                  run.error_message ? (
                    <span className="schedule-run-error">{run.error_message}</span>
                  ) : (
                    '-'
                  ),
                ])}
                emptyState={
                  <EmptyState
                    icon={FileClock}
                    title={status ? 'Tidak ada hasil' : 'Belum ada riwayat'}
                  />
                }
              />
              {result.meta.total > 0 ? (
                <Pagination meta={result.meta} disabled={loading} onChange={setPage} />
              ) : null}
            </>
          ) : null}
        </div>
      ) : null}
    </FormDialog>
  );
}
