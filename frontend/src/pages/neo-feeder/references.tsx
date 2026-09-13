import { Select } from '@/components/ui/select';
import { Database, Download, RefreshCcw } from 'lucide-react';

import {
  AppButton,
  DataTable,
  EmptyState,
  ErrorState,
  LoadingState,
  IconButton,
  StatusBadge,
  WorkspacePanel,
} from '@/components/ui';

import { useWorkspace } from '@/hooks/workspace-context';
import { formatDateTime } from '@/hooks/use-workspace-controller';
export function ReferencesPage() {
  const {
    tenants,
    referenceStatusState,
    referenceTenantId,
    setReferenceTenantId,
    referenceSyncState,
    setReferenceSyncState,
    referenceSyncError,
    referenceQueuedCount,
    loadReferenceStatus,
    referencePreview,
    handleSyncReferences,
  } = useWorkspace();
  return (
    <WorkspacePanel>
      <div className="table-toolbar">
        <label className="inline-field">
          Kampus
          <Select
            disabled={tenants.length === 0}
            onChange={(event) => {
              setReferenceTenantId(event.target.value);
              setReferenceSyncState('idle');
            }}
            value={referenceTenantId}
          >
            {tenants.length === 0 ? <option value="">Belum ada kampus</option> : null}
            {tenants.map((tenant) => (
              <option key={tenant.id} value={tenant.id}>
                {tenant.name}
              </option>
            ))}
          </Select>
        </label>
        <div className="section-actions">
          <IconButton
            label="Muat ulang referensi"
            icon={RefreshCcw}
            disabled={referenceStatusState === 'loading' || !referenceTenantId}
            onClick={() => loadReferenceStatus(referenceTenantId)}
          />
          <AppButton
            disabled={referenceSyncState === 'loading' || !referenceTenantId}
            icon={Download}
            onClick={handleSyncReferences}
          >
            {referenceSyncState === 'loading' ? 'Mengantre...' : 'Sync referensi'}
          </AppButton>
        </div>
      </div>
      {referenceSyncState === 'queued' ? (
        <p role="status" className="success-state">
          {referenceQueuedCount} referensi diantrekan.
        </p>
      ) : null}
      {referenceSyncState === 'error' ? (
        <ErrorState title="Sync referensi gagal" description={referenceSyncError} />
      ) : null}
      <DataTable
        columns={['Referensi', 'Jumlah data', 'Status', 'Diperbarui']}
        rows={
          referenceStatusState === 'loaded'
            ? referencePreview.map((item) => [
                item.name || item.endpoint,
                item.total_rows,
                <StatusBadge
                  key={item.endpoint}
                  tone={item.status === 'synced' ? 'success' : 'neutral'}
                >
                  {item.status === 'synced' ? 'Tersinkron' : 'Belum sync'}
                </StatusBadge>,
                formatDateTime(item.last_synced_at),
              ])
            : []
        }
        emptyState={
          referenceStatusState === 'loading' ? (
            <LoadingState label="Memuat referensi" />
          ) : referenceStatusState === 'error' ? (
            <ErrorState title="Referensi gagal dimuat" />
          ) : (
            <EmptyState icon={Database} title="Referensi belum tersedia" />
          )
        }
      />
    </WorkspacePanel>
  );
}
