import { DatabaseZap, Pencil, PlugZap, Plus, RefreshCcw } from 'lucide-react';

import {
  AppButton,
  DataTable,
  EmptyState,
  ErrorState,
  LoadingState,
  IconButton,
  ViewTabs,
  PageHeader,
  StatusBadge,
  WorkspacePanel,
} from '@/components/ui';

import { useWorkspace } from '@/hooks/workspace-context';
import {
  connectionStatusLabels,
  connectionStatusTones,
  formatDateTime,
} from '@/hooks/use-workspace-controller';
import { ReferenceActions, ReferencesPage } from './references';
export function NeoFeederPage() {
  const {
    setDialog,
    connectionTab,
    setConnectionTab,
    tenants,
    connections,
    connectionState,
    connectionError,
    connectionTestingId,
    connectionTestResult,
    connectionTestError,
    loadConnections,
    tenantNameById,
    applyConnectionToForm,
    handleTestConnection,
  } = useWorkspace();
  return (
    <>
      <PageHeader
        title="Neo Feeder"
        action={
          connectionTab === 'connections' ? (
            <>
              <IconButton
                label="Muat ulang koneksi"
                icon={RefreshCcw}
                onClick={loadConnections}
                disabled={connectionState === 'loading'}
              />
              <AppButton
                icon={Plus}
                onClick={() => {
                  applyConnectionToForm(tenants[0]?.id ?? '');
                  setDialog('connection');
                }}
              >
                Tambah koneksi
              </AppButton>
            </>
          ) : (
            <ReferenceActions />
          )
        }
      />
      <ViewTabs
        label="Data Neo Feeder"
        value={connectionTab}
        onChange={setConnectionTab}
        items={[
          { id: 'connections', label: 'Koneksi' },
          { id: 'references', label: 'Referensi' },
        ]}
      />
      <div role="tabpanel" id={`panel-${connectionTab}`} aria-labelledby={`tab-${connectionTab}`}>
        {connectionTab === 'references' ? (
          <ReferencesPage />
        ) : (
          <WorkspacePanel>
            {connectionTestError ? (
              <ErrorState title="Test koneksi gagal" description={connectionTestError} />
            ) : null}
            {connectionTestResult ? (
              <div
                className={connectionTestResult.ok ? 'success-state' : 'error-state'}
                role="status"
              >
                <strong>
                  {tenantNameById.get(connectionTestResult.connection.tenant_id)}:{' '}
                  {connectionTestResult.ok ? 'Koneksi berhasil' : 'Koneksi gagal'}
                </strong>
                {connectionTestResult.error_desc ? (
                  <span>{connectionTestResult.error_desc}</span>
                ) : null}
              </div>
            ) : null}
            <DataTable
              columns={['Kampus', 'Endpoint', 'Status', 'Diperiksa', 'Aksi']}
              rows={
                connectionState === 'loaded'
                  ? connections.map((connection) => [
                      <span className="table-primary" key={connection.id}>
                        {tenantNameById.get(connection.tenant_id) ?? connection.tenant_id}
                      </span>,
                      <span
                        className="mono table-url"
                        title={connection.base_url}
                        key={connection.id}
                      >
                        {connection.base_url}
                      </span>,
                      <StatusBadge
                        key={connection.id}
                        tone={connectionStatusTones[connection.status]}
                      >
                        {connectionStatusLabels[connection.status]}
                      </StatusBadge>,
                      formatDateTime(connection.last_checked_at),
                      <div className="row-actions" key={connection.id}>
                        <IconButton
                          label="Edit koneksi"
                          icon={Pencil}
                          onClick={() => {
                            applyConnectionToForm(connection.tenant_id);
                            setDialog('connection');
                          }}
                        />
                        <IconButton
                          label={
                            connectionTestingId === connection.id
                              ? 'Menguji koneksi'
                              : 'Test koneksi'
                          }
                          icon={PlugZap}
                          disabled={
                            !!connectionTestingId ||
                            !connection.password_configured ||
                            !connection.username
                          }
                          onClick={() => handleTestConnection(connection.id)}
                        />
                      </div>,
                    ])
                  : []
              }
              emptyState={
                connectionState === 'loading' || connectionState === 'idle' ? (
                  <LoadingState label="Memuat koneksi" />
                ) : connectionState === 'error' ? (
                  <ErrorState title="Koneksi gagal dimuat" description={connectionError} />
                ) : (
                  <EmptyState icon={DatabaseZap} title="Belum ada koneksi" />
                )
              }
            />
          </WorkspacePanel>
        )}
      </div>
    </>
  );
}
