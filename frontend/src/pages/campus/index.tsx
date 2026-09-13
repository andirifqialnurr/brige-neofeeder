import { Building2, Plus, RefreshCcw } from 'lucide-react';

import {
  AppButton,
  DataTable,
  EmptyState,
  ErrorState,
  LoadingState,
  IconButton,
  PageHeader,
  StatusBadge,
  WorkspacePanel,
} from '@/components/ui';

import { useWorkspace } from '@/hooks/workspace-context';
import {
  campusColumns,
  tenantStatusTones,
  tenantStatusLabels,
  formatDateTime,
} from '@/hooks/use-workspace-controller';
export function CampusPage() {
  const { setDialog, tenants, tenantState, tenantError, loadTenants } = useWorkspace();
  return (
    <>
      <PageHeader
        title="Kampus"
        action={
          <>
            <IconButton
              label="Muat ulang kampus"
              icon={RefreshCcw}
              onClick={loadTenants}
              disabled={tenantState === 'loading'}
            />
            <AppButton icon={Plus} onClick={() => setDialog('campus')}>
              Tambah kampus
            </AppButton>
          </>
        }
      />
      <WorkspacePanel>
        <DataTable
          columns={campusColumns}
          rows={
            tenantState === 'loaded'
              ? tenants.map((tenant) => [
                  <span className="table-primary" key={tenant.id}>
                    {tenant.name}
                  </span>,
                  <span className="mono" key={tenant.id}>
                    {tenant.code}
                  </span>,
                  <StatusBadge key={tenant.id} tone={tenantStatusTones[tenant.status]}>
                    {tenantStatusLabels[tenant.status]}
                  </StatusBadge>,
                  formatDateTime(tenant.updated_at),
                ])
              : []
          }
          emptyState={
            tenantState === 'loading' || tenantState === 'idle' ? (
              <LoadingState label="Memuat kampus" />
            ) : tenantState === 'error' ? (
              <ErrorState title="Kampus gagal dimuat" description={tenantError} />
            ) : (
              <EmptyState icon={Building2} title="Belum ada kampus" />
            )
          }
        />
      </WorkspacePanel>
    </>
  );
}
