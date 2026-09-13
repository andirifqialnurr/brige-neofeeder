import type { BatchDetail, SyncProgress } from './api';

export function syncEligibility(batch: BatchDetail | null, progress: SyncProgress | null) {
  const canApprove =
    !!batch &&
    !!progress &&
    batch.status === 'dry_run_ready' &&
    !!batch.approval.dry_run_hash &&
    !batch.approval.approved &&
    batch.staging_records_count > 0 &&
    !progress.started_at;
  const blockedReason =
    !batch || !progress
      ? 'Memuat status pengiriman.'
      : batch.is_demo
        ? 'Pengiriman dinonaktifkan untuk tenant demo.'
        : !batch.approval.approved
          ? 'Persetujuan operator diperlukan.'
          : !progress.has_credentials
            ? 'Koneksi aktif dan credential Neo Feeder belum tersedia.'
            : '';
  return {
    canApprove,
    canStart: !blockedReason && !progress?.started_at && batch?.status === 'dry_run_ready',
    canResume: !blockedReason && !!progress?.started_at && progress.queued > 0,
    blockedReason,
  };
}
