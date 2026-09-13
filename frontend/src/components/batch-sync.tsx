import type { useBatchSync } from '@/hooks/use-batch-sync';
import { CheckCheck, FileClock, RefreshCcw, RotateCcw, Send } from 'lucide-react';
import type { BatchDetail } from '@/lib/api';
import { syncEligibility } from '@/lib/sync-policy';
import {
  AppButton,
  DataTable,
  EmptyState,
  ErrorState,
  FormDialog,
  HelpTip,
  IconButton,
  LoadingState,
  Pagination,
  Select,
  StatusBadge,
} from './ui';

const statusLabels: Record<string, string> = {
  queued: 'Antrean',
  retrying: 'Menunggu ulang',
  syncing: 'Mengirim',
  success: 'Berhasil',
  failed: 'Gagal',
  unknown: 'Perlu pemeriksaan',
  skipped: 'Dilewati',
};
const date = (value: string | null) => (value ? new Date(value).toLocaleString('id-ID') : '-');
type SyncState = ReturnType<typeof useBatchSync>;

export function BatchSyncActions({ batch, sync }: { batch: BatchDetail | null; sync: SyncState }) {
  const policy = syncEligibility(batch, sync.progress);
  const disabled = sync.busy || sync.loading;
  return (
    <>
      <label className="inline-field">
        <span className="sr-only">Status pengiriman</span>
        <Select
          value={sync.status}
          onChange={(event) => sync.filter(event.target.value)}
          disabled={sync.busy}
        >
          <option value="">Semua status</option>
          {Object.entries(statusLabels).map(([value, label]) => (
            <option value={value} key={value}>
              {label}
            </option>
          ))}
        </Select>
      </label>
      <IconButton
        label="Muat ulang pengiriman"
        icon={RefreshCcw}
        disabled={disabled}
        onClick={sync.reload}
      />
      {!batch?.approval.approved && (
        <AppButton
          icon={CheckCheck}
          disabled={disabled || !policy.canApprove}
          onClick={() => sync.open({ kind: 'approve', hash: batch!.approval.dry_run_hash! })}
        >
          Setujui
        </AppButton>
      )}
      {policy.canResume ? (
        <AppButton
          icon={RefreshCcw}
          variant="secondary"
          disabled={disabled}
          onClick={() => sync.open({ kind: 'resume' })}
        >
          Antrekan ulang
        </AppButton>
      ) : (
        <AppButton
          icon={Send}
          disabled={disabled || !policy.canStart}
          onClick={() => sync.open({ kind: 'start' })}
        >
          Kirim
        </AppButton>
      )}
      <HelpTip
        label="Syarat pengiriman"
        text={
          policy.blockedReason ||
          'Pengiriman memakai versi data yang disetujui. Hasil ambigu harus diperiksa, bukan dikirim ulang.'
        }
      />
    </>
  );
}

export function BatchSyncPanel({ batch, sync }: { batch: BatchDetail | null; sync: SyncState }) {
  if (sync.loading && !sync.progress) return <LoadingState label="Memuat pengiriman" />;
  const counts = sync.progress?.records;
  return (
    <section className="sync-workspace">
      {sync.error && <ErrorState title="Pengiriman gagal dimuat" description={sync.error} />}
      <div className="sync-approval-status">
        <StatusBadge tone={batch?.approval.approved ? 'success' : 'neutral'}>
          {batch?.approval.approved ? 'Disetujui' : 'Belum disetujui'}
        </StatusBadge>
        {batch?.approval.approved && (
          <span>
            {batch.approval.approved_by_name} / {date(batch.approval.approved_at)}
          </span>
        )}
        {batch?.is_demo ? (
          <span>Demo: pengiriman dinonaktifkan</span>
        ) : sync.progress && !sync.progress.has_credentials ? (
          <span>Menunggu credential Neo Feeder</span>
        ) : null}
      </div>
      {counts && counts.total > 0 ? (
        <>
          <dl className="summary-strip">
            {[
              ['Record', counts.total],
              ['Aktif', counts.active],
              ['Berhasil', counts.success],
              ['Gagal', counts.failed],
              ['Perlu pemeriksaan', counts.unknown],
            ].map(([label, value]) => (
              <div key={label}>
                <dt>{label}</dt>
                <dd>{value}</dd>
              </div>
            ))}
          </dl>
          <progress
            className="sync-progress"
            aria-label="Record selesai diproses"
            max={counts.total}
            value={counts.total - counts.active}
          />
          {counts.unknown > 0 && (
            <p className="sync-review-note">
              Periksa hasil di Neo Feeder untuk record yang belum pasti. Retry diblokir.
            </p>
          )}
        </>
      ) : null}
      <DataTable
        columns={['Baris', 'Sheet', 'Operasi', 'Status', 'Aksi']}
        rows={(sync.loading ? [] : (sync.attempts?.data ?? [])).map((attempt) => [
          <button className="table-link" onClick={() => sync.setDetail(attempt)}>
            Baris {attempt.row_number}
          </button>,
          attempt.sheet_name,
          <span className="mono">{attempt.action}</span>,
          <StatusBadge
            tone={
              attempt.status === 'success'
                ? 'success'
                : attempt.status === 'unknown'
                  ? 'warning'
                  : attempt.status === 'failed'
                    ? 'destructive'
                    : 'neutral'
            }
          >
            {statusLabels[attempt.status] ?? attempt.status}
          </StatusBadge>,
          <div className="row-actions">
            <IconButton
              label="Retry attempt"
              icon={RotateCcw}
              disabled={sync.busy || !attempt.can_retry || !sync.progress?.has_credentials}
              onClick={() => sync.open({ kind: 'retry', attempt })}
            />
          </div>,
        ])}
        emptyState={
          sync.loading ? (
            <LoadingState label="Memuat riwayat" />
          ) : (
            <EmptyState
              icon={FileClock}
              title={sync.status ? 'Tidak ada hasil' : 'Belum ada pengiriman'}
            />
          )
        }
      />
      {sync.attempts && sync.attempts.meta.total > 0 && (
        <Pagination
          meta={sync.attempts.meta}
          disabled={sync.loading || sync.busy}
          onChange={sync.setPage}
        />
      )}
    </section>
  );
}

export function BatchSyncDialogs({ sync }: { sync: SyncState }) {
  const kind = sync.confirmation?.kind;
  return (
    <>
      <FormDialog
        open={!!sync.confirmation}
        title={
          kind === 'approve'
            ? 'Setujui batch'
            : kind === 'retry'
              ? 'Retry pengiriman'
              : kind === 'resume'
                ? 'Antrekan ulang'
                : 'Kirim ke Neo Feeder'
        }
        onClose={sync.close}
        busy={sync.busy}
      >
        <form
          className="stack-form"
          onSubmit={(event) => {
            event.preventDefault();
            void sync.submit();
          }}
        >
          {kind === 'approve' ? (
            <label className="confirmation-check">
              <input
                type="checkbox"
                checked={sync.confirmed}
                onChange={(event) => sync.setConfirmed(event.target.checked)}
                disabled={sync.busy}
              />
              Saya telah memeriksa data, payload, dan peringatan pada hasil dry-run ini.
            </label>
          ) : (
            <p>
              {kind === 'retry'
                ? `Kirim ulang baris ${sync.confirmation?.kind === 'retry' ? sync.confirmation.attempt.row_number : ''} dengan payload yang sama?`
                : kind === 'resume'
                  ? 'Jadwalkan kembali intent yang masih antre. Tidak membuat intent pengiriman baru.'
                  : 'Kirim versi data yang telah disetujui? Perubahan di Neo Feeder tidak dapat dibatalkan dari halaman ini.'}
            </p>
          )}
          {sync.commandError && (
            <p className="auth-error" role="alert">
              {sync.commandError}
            </p>
          )}
          <AppButton
            type="submit"
            icon={kind === 'approve' ? CheckCheck : Send}
            disabled={sync.busy || (kind === 'approve' && !sync.confirmed)}
          >
            {sync.busy ? 'Memproses...' : kind === 'approve' ? 'Setujui batch' : 'Lanjutkan'}
          </AppButton>
        </form>
      </FormDialog>
      <FormDialog open={!!sync.detail} title="Detail attempt" onClose={() => sync.setDetail(null)}>
        {sync.detail && (
          <dl className="row-data">
            {[
              ['Sheet / baris', `${sync.detail.sheet_name} / ${sync.detail.row_number}`],
              ['Operasi', sync.detail.action],
              ['Status', statusLabels[sync.detail.status] ?? sync.detail.status],
              ['Kode', sync.detail.error_code || '-'],
              ['Pesan', sync.detail.error_desc || '-'],
              ['Dibuat', date(sync.detail.created_at)],
              ['Selesai', date(sync.detail.completed_at)],
              ...Object.entries(sync.detail.identity_payload ?? {}).map(([key, value]) => [
                key,
                String(value),
              ]),
            ].map(([label, value]) => (
              <div key={label}>
                <dt>{label}</dt>
                <dd>{value}</dd>
              </div>
            ))}
          </dl>
        )}
      </FormDialog>
    </>
  );
}
