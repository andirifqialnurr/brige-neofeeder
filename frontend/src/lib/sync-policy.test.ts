import { describe, expect, it } from 'vitest';
import type { BatchDetail, SyncProgress } from './api';
import { syncEligibility } from './sync-policy';

const batch: BatchDetail = {
  id: 'batch',
  tenant_id: 'tenant',
  tenant_name: 'Kampus',
  source_type: 'excel',
  template_version: '1',
  status: 'dry_run_ready',
  summary: {},
  staging_records_count: 1,
  created_at: '',
  updated_at: '',
  sheets: [],
  dependency_order: [],
  is_demo: false,
  approval: {
    dry_run_hash: 'a'.repeat(64),
    approved: false,
    approved_at: null,
    approved_by_name: null,
  },
};
const progress: SyncProgress = {
  import_batch_id: 'batch',
  status: 'dry_run_ready',
  started_at: null,
  total_attempts: 0,
  queued: 0,
  syncing: 0,
  retrying: 0,
  success: 0,
  failed: 0,
  unknown: 0,
  has_credentials: false,
  is_demo: false,
  records: { total: 0, success: 0, failed: 0, unknown: 0, active: 0 },
};
const approved = { ...batch, approval: { ...batch.approval, approved: true } };

describe('batch sync controls', () => {
  it('waits for current batch and delivery state', () => {
    expect(syncEligibility(null, null).canStart).toBe(false);
    expect(syncEligibility(batch, null).canApprove).toBe(false);
  });
  it('allows offline approval but not sending before credentials and approval', () => {
    expect(syncEligibility(batch, progress).canApprove).toBe(true);
    expect(syncEligibility(batch, { ...progress, has_credentials: true }).canStart).toBe(false);
    expect(syncEligibility(approved, progress).canStart).toBe(false);
    expect(syncEligibility(approved, { ...progress, has_credentials: true }).canStart).toBe(true);
  });
  it('never enables sending or resuming a demo tenant', () => {
    const demo = { ...approved, is_demo: true };
    expect(syncEligibility(demo, { ...progress, has_credentials: true }).canStart).toBe(false);
    expect(
      syncEligibility(demo, { ...progress, has_credentials: true, started_at: 'now', queued: 1 })
        .canResume,
    ).toBe(false);
  });
  it('blocks approval for invalid, empty, approved, or already started batches', () => {
    expect(syncEligibility({ ...batch, status: 'invalid' }, progress).canApprove).toBe(false);
    expect(syncEligibility({ ...batch, staging_records_count: 0 }, progress).canApprove).toBe(
      false,
    );
    expect(syncEligibility(approved, progress).canApprove).toBe(false);
    expect(syncEligibility(batch, { ...progress, started_at: 'now' }).canApprove).toBe(false);
  });
  it('resumes only queued intents and never unknown deliveries', () => {
    const started = { ...progress, has_credentials: true, started_at: 'now' };
    expect(syncEligibility(approved, started).canStart).toBe(false);
    expect(syncEligibility(approved, { ...started, queued: 1 }).canResume).toBe(true);
    expect(syncEligibility(approved, { ...started, unknown: 1 }).canResume).toBe(false);
  });
});
