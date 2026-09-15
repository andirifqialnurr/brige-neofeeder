import { useEffect, useRef, useState } from 'react';
import {
  AppButton,
  DataTable,
  ErrorState,
  LoadingState,
  SectionHeader,
  WorkspacePanel,
} from '@/components/ui';
import { Select } from '@/components/ui/select';
import { downloadApiFile, requestApi } from '@/lib/api';

const labels: Record<string, string> = {
  not_sent: 'Belum dikirim',
  invalid: 'Validasi gagal',
  active: 'Dalam proses',
  success: 'Berhasil',
  failed: 'Gagal',
  unknown: 'Perlu pemeriksaan',
  skipped: 'Dilewati',
};
type Report = {
  data: {
    record_id: string;
    sheet: string;
    source_row: number;
    mapping_version: number | null;
    state: string;
    attempt_id: string | null;
    error_code: string | null;
    result_ids: Record<string, string>;
  }[];
  summary: Record<string, number | null>;
  generated_at: string;
  meta: { current_page: number; last_page: number; total: number };
};
export function BatchReconciliation({ id }: { id: string }) {
  const [state, setState] = useState('');
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [report, setReport] = useState<Report | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const lock = useRef(false);
  useEffect(() => {
    const controller = new AbortController();
    requestApi<Report>(
      `import-batches/${id}/reconciliation?${new URLSearchParams({ state, page: String(page) })}`,
      { signal: controller.signal },
    )
      .then((data) => {
        if (!controller.signal.aborted) {
          setReport(data);
          setError('');
        }
      })
      .catch((err) => {
        if (!controller.signal.aborted) {
          setError(err.message);
          setReport(null);
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [id, state, page, revision]);
  async function exportReport() {
    if (lock.current) return;
    lock.current = true;
    setExporting(true);
    try {
      await downloadApiFile(
        `import-batches/${id}/reconciliation?${new URLSearchParams({ state, format: 'csv' })}`,
        `rekonsiliasi-${id}.csv`,
      );
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Ekspor gagal.');
    } finally {
      lock.current = false;
      setExporting(false);
    }
  }
  return (
    <WorkspacePanel>
      <SectionHeader
        title="Rekonsiliasi batch"
        description="Status attempt terakhir per baris. Berhasil menunjukkan hasil yang dicatat aplikasi; pemeriksaan langsung pada Neo Feeder tetap terpisah."
      />
      <div className="mapping-actions">
        <label className="inline-field">
          <span className="sr-only">Status rekonsiliasi</span>
          <Select
            aria-label="Status rekonsiliasi"
            value={state}
            onChange={(e) => {
              setState(e.target.value);
              setPage(1);
              setLoading(true);
            }}
          >
            <option value="">Semua status</option>
            {Object.entries(labels).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </Select>
        </label>
        <AppButton
          variant="secondary"
          disabled={loading}
          onClick={() => {
            setLoading(true);
            setRevision((value) => value + 1);
          }}
        >
          Muat ulang
        </AppButton>
        <AppButton disabled={loading || exporting || !report} onClick={exportReport}>
          {exporting ? 'Mengekspor...' : 'Ekspor CSV'}
        </AppButton>
      </div>
      {error && <ErrorState title="Laporan gagal" description={error} />}
      {loading ? (
        <LoadingState label="Memuat rekonsiliasi" />
      ) : (
        report && (
          <>
            <p>
              Snapshot: {new Date(report.generated_at).toLocaleString('id-ID')} ·{' '}
              {report.summary.staging_rows} baris staging
              {report.summary.source_rows !== null
                ? ` / ${report.summary.source_rows} baris sumber; selisih ${report.summary.source_difference}`
                : ' · Jumlah sumber asli tidak tersedia untuk workbook.'}
            </p>
            <dl className="summary-strip">
              {Object.entries(labels).map(([key, label]) => (
                <div key={key}>
                  <dt>{label}</dt>
                  <dd>{report.summary[key]}</dd>
                </div>
              ))}
            </dl>
            <DataTable
              columns={[
                'Sumber',
                'Versi mapping',
                'Status',
                'Attempt terakhir',
                'Kode hasil',
                'ID hasil',
              ]}
              rows={report.data.map((row) => [
                `${row.sheet} · baris ${row.source_row}`,
                row.mapping_version ?? '—',
                labels[row.state],
                row.attempt_id ?? '—',
                row.error_code ?? '—',
                Object.values(row.result_ids).join(', ') || '—',
              ])}
            />
            <div className="mapping-actions">
              <AppButton
                disabled={page <= 1}
                onClick={() => {
                  setPage(page - 1);
                  setLoading(true);
                }}
              >
                Sebelumnya
              </AppButton>
              <span>
                {page} / {report.meta.last_page} · {report.meta.total} baris
              </span>
              <AppButton
                disabled={page >= report.meta.last_page}
                onClick={() => {
                  setPage(page + 1);
                  setLoading(true);
                }}
              >
                Berikutnya
              </AppButton>
            </div>
          </>
        )
      )}
    </WorkspacePanel>
  );
}
