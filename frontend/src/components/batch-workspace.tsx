import { Select } from '@/components/ui/select';
import { BatchSyncActions, BatchSyncDialogs, BatchSyncPanel } from './batch-sync';
import { useBatchSync } from '@/hooks/use-batch-sync';
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
  requestApi,
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
          <>
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

            <AppButton icon={Upload} onClick={onUpload}>
              Upload Excel
            </AppButton>
          </>
        }
      />
      <WorkspacePanel>
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
  const [tab, setTab] = useState<'validation' | 'delivery'>('validation');
  const sync = useBatchSync(id, tab === 'delivery', () => {
    setRefresh((value) => value + 1);
    onUpdated();
  });
  const batchStatus = batch?.status;
  useEffect(() => {
    if (
      tab === 'delivery' &&
      sync.progress?.status &&
      batchStatus &&
      sync.progress.status !== batchStatus
    ) {
      setRefresh((value) => value + 1);
    }
  }, [tab, sync.progress?.status, batchStatus]);
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
        parents={[{ label: 'Import Batch', href: '/import-batch' }]}
        action={
          <>
            {tab === 'delivery' ? (
              <BatchSyncActions batch={batch} sync={sync} />
            ) : (
              <>
                <label className="inline-field">
                  <span className="sr-only">Sheet</span>
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
                  <span className="sr-only">Status</span>
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

                <IconButton
                  label="Muat ulang detail"
                  icon={RefreshCcw}
                  disabled={loading || !!busy}
                  onClick={() => setRefresh((value) => value + 1)}
                />
                <IconButton
                  label={busy === 'report' ? 'Mengunduh laporan' : 'Laporan Excel'}
                  icon={Download}
                  disabled={
                    !batch || loading || !!busy || ['uploaded', 'parsing'].includes(batch.status)
                  }
                  onClick={() => act('report')}
                />
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
              </>
            )}
            <IconButton label="Daftar batch" icon={ArrowLeft} onClick={onBack} />
          </>
        }
      />
      <div className="table-toolbar batch-actions">
        <div className="batch-caption">
          {batch?.tenant_name} {batch && <RowStatus status={batch.status} />}
          {batch?.summary.demo === true && <StatusBadge tone="info">Demo</StatusBadge>}
        </div>
      </div>
      {error && <ErrorState title="Permintaan gagal" description={error} />}
      <ViewTabs
        value={tab}
        onChange={setTab}
        label="Detail batch"
        items={[
          { id: 'validation', label: 'Validasi' },
          { id: 'delivery', label: 'Pengiriman' },
        ]}
      />
      <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
        {tab === 'delivery' ? (
          <BatchSyncPanel batch={batch} sync={sync} />
        ) : (
          <WorkspacePanel>
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
        )}
      </div>
      <BatchSyncDialogs sync={sync} />
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
  const [revealing, setRevealing] = useState(false);
  const [purpose, setPurpose] = useState('verification');
  const revealLock = useRef(false);
  const revealController = useRef<AbortController | null>(null);
  useEffect(() => () => revealController.current?.abort(), []);
  async function reveal() {
    if (revealLock.current) return;
    revealLock.current = true;
    setRevealing(true);
    const controller = new AbortController();
    revealController.current = controller;
    try {
      const result = await requestApi<{ data: RowDetail }>(
        `import-batches/${batchId}/rows/${rowId}/reveal`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ purpose }),
          signal: controller.signal,
        },
      );
      if (!controller.signal.aborted) setRow(result.data);
    } catch (err) {
      if (!controller.signal.aborted) setError(message(err));
    } finally {
      revealLock.current = false;
      if (!controller.signal.aborted) setRevealing(false);
    }
  }
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
              ...(row.raw_row ? [{ id: 'raw' as const, label: 'Asli' }] : []),
              { id: 'payload', label: 'Payload' },
              { id: 'issues', label: 'Temuan' },
            ]}
          />
          {row.source_lineage && (
            <p className="muted">
              Sumber: {row.source_lineage.source_name}
              {row.source_lineage.source_sheet ? ` / ${row.source_lineage.source_sheet}` : ''} ·
              baris {row.source_lineage.source_row} · mapping versi{' '}
              {row.source_lineage.mapping_version}
            </p>
          )}
          {!row.sensitive_revealed && (
            <p className="muted">NIK, NPWP, dan nomor telepon disamarkan.</p>
          )}
          {row.can_reveal_sensitive && !row.sensitive_revealed && (
            <div className="table-toolbar">
              <label className="inline-field">
                <span className="sr-only">Tujuan membuka data</span>
                <Select
                  value={purpose}
                  disabled={revealing}
                  onChange={(event) => setPurpose(event.target.value)}
                >
                  <option value="verification">Verifikasi data</option>
                  <option value="correction">Koreksi data</option>
                </Select>
              </label>
              <AppButton disabled={revealing} onClick={reveal}>
                {revealing ? 'Membuka...' : 'Buka data lengkap'}
              </AppButton>
            </div>
          )}
          {row.sensitive_revealed && (
            <p role="status" className="muted">
              Akses data lengkap telah dicatat di audit.
            </p>
          )}
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
