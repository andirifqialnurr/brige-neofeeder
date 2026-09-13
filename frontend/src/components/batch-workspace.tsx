import { Select } from '@/components/ui/select';
import { useEffect, useRef, useState } from 'react';
import {
  ArrowLeft,
  Download,
  FileSpreadsheet,
  RefreshCcw,
  Search,
  ShieldCheck,
  Upload,
} from 'lucide-react';
import {
  downloadBatchReport,
  getBatchDetail,
  getBatchPage,
  getBatchRow,
  getBatchRows,
  runImportBatchDryRun,
  type BatchDetail,
  type BatchRow,
  type ImportBatch,
  type PageResult,
  type RowDetail,
} from '@/lib/api';
import {
  AppButton,
  DataTable,
  EmptyState,
  ErrorState,
  FormDialog,
  HelpTip,
  IconButton,
  LoadingState,
  PageHeader,
  Pagination,
  StatusBadge,
  ViewTabs,
  WorkspacePanel,
} from './ui';

const labels: Record<string, string> = {
  valid: 'Valid',
  invalid: 'Perlu perbaikan',
  validated: 'Valid',
  uploaded: 'Diupload',
  parsing: 'Diproses',
  pending: 'Menunggu',
  ready: 'Siap validasi',
  dry_run_ready: 'Dry-run',
  syncing: 'Mengirim',
  success: 'Berhasil',
  synced: 'Tersinkron',
  failed: 'Gagal',
  skipped: 'Dilewati',
};
function RowStatus({ status }: { status: string }) {
  return (
    <StatusBadge
      tone={
        ['invalid', 'failed'].includes(status)
          ? 'destructive'
          : ['valid', 'validated', 'success', 'synced'].includes(status)
            ? 'success'
            : 'neutral'
      }
    >
      {labels[status] ?? status}
    </StatusBadge>
  );
}
const message = (error: unknown) => (error instanceof Error ? error.message : 'Permintaan gagal.');

export function BatchList({
  onSelect,
  onUpload,
  revision,
}: {
  onSelect: (id: string) => void;
  onUpload: () => void;
  revision: unknown;
}) {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [refresh, setRefresh] = useState(0);
  const [result, setResult] = useState<PageResult<ImportBatch> | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      setError('');
      getBatchPage(page, search, controller.signal)
        .then(setResult)
        .catch((error) => {
          if (!controller.signal.aborted) setError(message(error));
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 200);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [page, search, refresh, revision]);
  return (
    <>
      <PageHeader
        title="Import Batch"
        action={
          <AppButton icon={Upload} onClick={onUpload}>
            Upload Excel
          </AppButton>
        }
      />
      <WorkspacePanel>
        <div className="table-toolbar">
          <label className="search-field">
            <Search size={16} />
            <input
              aria-label="Cari batch"
              type="search"
              value={search}
              placeholder="Cari file atau kampus..."
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
                setLoading(true);
              }}
            />
          </label>
          <IconButton
            label="Muat ulang batch"
            icon={RefreshCcw}
            onClick={() => setRefresh((value) => value + 1)}
            disabled={loading}
          />
        </div>
        {error ? (
          <ErrorState title="Batch gagal dimuat" description={error} />
        ) : (
          <DataTable
            columns={['File', 'Kampus', 'Status', 'Baris', 'Error']}
            rows={
              loading
                ? []
                : (result?.data ?? []).map((batch) => [
                    <button className="table-link" onClick={() => onSelect(batch.id)}>
                      {batch.summary.original_name ?? batch.id}
                    </button>,
                    batch.tenant_name ?? '-',
                    <RowStatus status={batch.status} />,
                    batch.staging_records_count,
                    batch.summary.invalid_rows ?? '-',
                  ])
            }
            emptyState={
              loading ? (
                <LoadingState label="Memuat batch" />
              ) : (
                <EmptyState
                  icon={FileSpreadsheet}
                  title={search ? 'Tidak ada hasil' : 'Belum ada batch'}
                />
              )
            }
          />
        )}
        {result && !error ? (
          <Pagination
            meta={result.meta}
            disabled={loading}
            onChange={(value) => {
              setPage(value);
              setLoading(true);
            }}
          />
        ) : null}
      </WorkspacePanel>
    </>
  );
}

export function BatchInspection({
  id,
  onBack,
  onUpdated,
}: {
  id: string;
  onBack: () => void;
  onUpdated: () => void;
}) {
  const [batch, setBatch] = useState<BatchDetail | null>(null);
  const [result, setResult] = useState<PageResult<BatchRow> | null>(null);
  const [sheet, setSheet] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [refresh, setRefresh] = useState(0);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<'report' | 'dry-run' | null>(null);
  const lock = useRef(false);
  const [rowId, setRowId] = useState('');
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setError('');
    (async () => {
      const detail = await getBatchDetail(id, controller.signal);
      const rows = await getBatchRows(id, page, sheet, status, controller.signal);
      if (!controller.signal.aborted) {
        setBatch(detail);
        setResult(rows);
      }
    })()
      .catch((error) => {
        if (!controller.signal.aborted) setError(message(error));
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [id, page, sheet, status, refresh]);
  async function act(action: 'report' | 'dry-run') {
    if (lock.current) return;
    lock.current = true;
    setBusy(action);
    setError('');
    try {
      if (action === 'report') await downloadBatchReport(id);
      else {
        await runImportBatchDryRun(id);
        setRefresh((value) => value + 1);
        onUpdated();
      }
    } catch (error) {
      setError(message(error));
    } finally {
      lock.current = false;
      setBusy(null);
    }
  }
  return (
    <>
      <PageHeader
        title={batch?.summary.original_name ?? 'Detail batch'}
        action={
          <AppButton variant="ghost" icon={ArrowLeft} onClick={onBack}>
            Daftar batch
          </AppButton>
        }
      />
      <WorkspacePanel>
        <div className="table-toolbar batch-actions">
          <div className="batch-caption">
            {batch?.tenant_name} {batch && <RowStatus status={batch.status} />}
            {batch?.summary.demo === true && <StatusBadge tone="info">Demo</StatusBadge>}
          </div>
          <div>
            <IconButton
              label="Muat ulang detail"
              icon={RefreshCcw}
              disabled={loading || !!busy}
              onClick={() => setRefresh((value) => value + 1)}
            />
            <AppButton
              variant="secondary"
              icon={Download}
              disabled={
                !batch || loading || !!busy || ['uploaded', 'parsing'].includes(batch.status)
              }
              onClick={() => act('report')}
            >
              {busy === 'report' ? 'Mengunduh...' : 'Laporan Excel'}
            </AppButton>
            <AppButton
              icon={ShieldCheck}
              disabled={
                !batch ||
                loading ||
                !!busy ||
                !['validated', 'invalid', 'dry_run_ready'].includes(batch.status)
              }
              onClick={() => act('dry-run')}
            >
              {busy === 'dry-run' ? 'Memproses...' : 'Dry-run'}
            </AppButton>
            <HelpTip
              label="Tentang dry-run"
              text="Menyiapkan payload tanpa mengirim ke Neo Feeder. Baris invalid dilewati."
            />
          </div>
        </div>
        {error && <ErrorState title="Permintaan gagal" description={error} />}
        {batch && (
          <>
            <dl className="summary-strip">
              {[
                ['Baris', batch.staging_records_count],
                ['Valid', batch.summary.valid_rows ?? '-'],
                ['Error', batch.summary.invalid_rows ?? '-'],
                ['Peringatan', batch.summary.warning_rows ?? '-'],
              ].map(([label, value]) => (
                <div key={label}>
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
            {!!batch.summary.missing_sheets?.length && (
              <ErrorState
                title="Sheet tidak lengkap"
                description={batch.summary.missing_sheets.join(', ')}
              />
            )}
            {typeof batch.summary.error === 'string' && (
              <ErrorState title="Parsing gagal" description={batch.summary.error} />
            )}
            <details className="dependency-order">
              <summary>Urutan dependensi</summary>
              <ol>
                {batch.dependency_order.map((channel) => (
                  <li key={channel}>{channel.replaceAll('_', ' ')}</li>
                ))}
              </ol>
            </details>
          </>
        )}
        <div className="table-toolbar row-filters">
          <label className="inline-field">
            Sheet
            <Select
              value={sheet}
              disabled={!!busy}
              onChange={(event) => {
                setSheet(event.target.value);
                setPage(1);
              }}
            >
              <option value="">Semua sheet</option>
              {batch?.sheets.map((item) => (
                <option key={item.sheet_name} value={item.sheet_name}>
                  {item.sheet_name} ({item.total_rows})
                </option>
              ))}
            </Select>
          </label>
          <label className="inline-field">
            Status
            <Select
              value={status}
              disabled={!!busy}
              onChange={(event) => {
                setStatus(event.target.value);
                setPage(1);
              }}
            >
              <option value="">Semua status</option>
              {[
                'pending',
                'valid',
                'invalid',
                'ready',
                'syncing',
                'success',
                'failed',
                'skipped',
              ].map((item) => (
                <option key={item} value={item}>
                  {labels[item]}
                </option>
              ))}
            </Select>
          </label>
        </div>
        <DataTable
          columns={['Baris Excel', 'Sheet', 'Status', 'Error', 'Peringatan']}
          rows={
            loading || error
              ? []
              : (result?.data ?? []).map((row) => [
                  <button className="table-link" onClick={() => setRowId(row.id)}>
                    Baris {row.row_number}
                  </button>,
                  row.sheet_name,
                  <RowStatus status={row.status} />,
                  row.errors,
                  row.warnings,
                ])
          }
          emptyState={
            loading ? (
              <LoadingState label="Memuat baris" />
            ) : (
              <EmptyState
                icon={FileSpreadsheet}
                title={error ? 'Data belum tersedia' : 'Tidak ada baris'}
              />
            )
          }
        />
        {result && !error && (
          <Pagination meta={result.meta} disabled={loading} onChange={setPage} />
        )}
      </WorkspacePanel>
      {rowId && <RowDialog key={rowId} batchId={id} rowId={rowId} onClose={() => setRowId('')} />}
    </>
  );
}

function RowDialog({
  batchId,
  rowId,
  onClose,
}: {
  batchId: string;
  rowId: string;
  onClose: () => void;
}) {
  const [row, setRow] = useState<RowDetail | null>(null);
  const [error, setError] = useState('');
  const [tab, setTab] = useState<'data' | 'raw' | 'payload' | 'issues'>('data');
  useEffect(() => {
    const controller = new AbortController();
    getBatchRow(batchId, rowId, controller.signal)
      .then(setRow)
      .catch((error) => {
        if (!controller.signal.aborted) setError(message(error));
      });
    return () => controller.abort();
  }, [batchId, rowId]);
  return (
    <FormDialog
      open
      title={row ? `Baris ${row.row_number} / ${row.sheet_name}` : 'Detail baris'}
      onClose={onClose}
    >
      {error ? (
        <ErrorState title="Baris gagal dimuat" description={error} />
      ) : !row ? (
        <LoadingState label="Memuat baris" />
      ) : (
        <>
          <ViewTabs
            value={tab}
            onChange={setTab}
            label="Detail baris"
            items={[
              { id: 'data', label: 'Data' },
              { id: 'raw', label: 'Asli' },
              { id: 'payload', label: 'Payload' },
              { id: 'issues', label: 'Temuan' },
            ]}
          />
          <div
            role="tabpanel"
            id={`panel-${tab}`}
            aria-labelledby={`tab-${tab}`}
            className="row-detail-panel"
          >
            {tab === 'data' || tab === 'raw' ? (
              <dl className="row-data">
                {Object.entries((tab === 'data' ? row.normalized_row : row.raw_row) ?? {}).map(
                  ([field, value]) => (
                    <div key={field}>
                      <dt>{field}</dt>
                      <dd>
                        {value == null || value === ''
                          ? '-'
                          : typeof value === 'object'
                            ? JSON.stringify(value)
                            : String(value)}
                      </dd>
                    </div>
                  ),
                )}
              </dl>
            ) : null}
            {tab === 'payload' &&
              (row.payload ? (
                <>
                  <p className="payload-action">{row.action}</p>
                  <pre className="payload-code">{JSON.stringify(row.payload, null, 2)}</pre>
                </>
              ) : (
                <EmptyState icon={ShieldCheck} title="Tidak ada payload untuk baris ini" />
              ))}
            {tab === 'issues' &&
              (['errors', 'warnings', 'info'].every(
                (level) => !row.validation_result[level as 'errors']?.length,
              ) ? (
                <EmptyState icon={ShieldCheck} title="Tidak ada temuan" />
              ) : (
                <ul className="row-issues">
                  {(['errors', 'warnings', 'info'] as const).flatMap((level) =>
                    (row.validation_result[level] ?? []).map((issue, index) => (
                      <li key={`${level}-${index}`}>
                        <div>
                          <StatusBadge
                            tone={
                              level === 'errors'
                                ? 'destructive'
                                : level === 'warnings'
                                  ? 'warning'
                                  : 'info'
                            }
                          >
                            {level === 'errors'
                              ? 'Error'
                              : level === 'warnings'
                                ? 'Peringatan'
                                : 'Info'}
                          </StatusBadge>
                          <strong>{issue.field ?? 'Baris'}</strong>
                        </div>
                        <p>{issue.message ?? issue.rule ?? '-'}</p>
                      </li>
                    )),
                  )}
                </ul>
              ))}
          </div>
        </>
      )}
    </FormDialog>
  );
}
