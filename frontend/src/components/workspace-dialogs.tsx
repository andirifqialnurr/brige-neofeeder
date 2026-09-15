import { Select } from '@/components/ui/select';
import { Building2, DatabaseZap, Plus, Upload } from 'lucide-react';

import { type NeoFeederConnectionStatus, type TenantStatus } from '@/lib/api';
import { AppButton, EmptyState, FormDialog } from '@/components/ui';

import { useWorkspace } from '@/hooks/workspace-context';
export function WorkspaceDialogs() {
  const {
    dialog,
    setDialog,
    navigate,
    tenants,
    tenantForm,
    tenantFormState,
    tenantFormError,
    connectionForm,
    connectionFormState,
    connectionFormError,
    importUploadTenantId,
    setImportUploadTenantId,
    importUploadFile,
    importUploadInputKey,
    importUploadState,
    importUploadError,
    handleTenantFormChange,
    handleCreateTenant,
    handleConnectionFormChange,
    applyConnectionToForm,
    handleSaveConnection,
    handleImportFileChange,
    handleUploadImportBatch,
  } = useWorkspace();
  return (
    <>
      <FormDialog
        title="Tambah kampus"
        open={dialog === 'campus'}
        onClose={() => setDialog(null)}
        busy={tenantFormState === 'saving'}
      >
        <form className="stack-form" onSubmit={handleCreateTenant}>
          <label>
            Nama Kampus
            <input
              onChange={(event) => handleTenantFormChange('name', event.target.value)}
              placeholder="Universitas Contoh"
              required
              type="text"
              value={tenantForm.name}
            />
          </label>

          <label>
            Kode PT
            <input
              onChange={(event) => handleTenantFormChange('code', event.target.value)}
              placeholder="001001"
              required
              type="text"
              value={tenantForm.code}
            />
          </label>

          <label>
            Status
            <Select
              onChange={(event) =>
                handleTenantFormChange('status', event.target.value as TenantStatus)
              }
              value={tenantForm.status}
            >
              <option value="draft">Draft</option>
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </Select>
          </label>

          {tenantFormState === 'error' ? <p className="auth-error">{tenantFormError}</p> : null}

          <button
            className="app-button app-button-primary"
            disabled={tenantFormState === 'saving'}
            type="submit"
          >
            <Plus size={18} />
            {tenantFormState === 'saving' ? 'Menyimpan...' : 'Tambah Kampus'}
          </button>
        </form>
      </FormDialog>
      <FormDialog
        title="Koneksi Neo Feeder"
        open={dialog === 'connection'}
        onClose={() => setDialog(null)}
        busy={connectionFormState === 'saving'}
      >
        {tenants.length === 0 ? (
          <EmptyState
            icon={Building2}
            title="Tambahkan kampus terlebih dahulu"
            action={
              <AppButton
                icon={Plus}
                onClick={() => {
                  setDialog('campus');
                  navigate('campus');
                }}
              >
                Tambah kampus
              </AppButton>
            }
          />
        ) : (
          <>
            <form className="stack-form" onSubmit={handleSaveConnection}>
              <label>
                Kampus
                <Select
                  disabled={tenants.length === 0}
                  onChange={(event) => applyConnectionToForm(event.target.value)}
                  required
                  value={connectionForm.tenantId}
                >
                  {tenants.length === 0 ? <option value="">Buat kampus dulu</option> : null}
                  {tenants.map((tenant) => (
                    <option key={tenant.id} value={tenant.id}>
                      {tenant.name}
                    </option>
                  ))}
                </Select>
              </label>

              <label>
                Endpoint WS
                <input
                  onChange={(event) => handleConnectionFormChange('baseUrl', event.target.value)}
                  placeholder="https://.../ws/live2.php"
                  required
                  type="url"
                  value={connectionForm.baseUrl}
                />
              </label>

              <label>
                Username
                <input
                  autoComplete="username"
                  onChange={(event) => handleConnectionFormChange('username', event.target.value)}
                  placeholder="Username Neo Feeder"
                  type="text"
                  value={connectionForm.username}
                />
              </label>

              <label>
                Batas waktu request (detik)
                <input
                  type="number"
                  min={1}
                  max={30}
                  step={1}
                  required
                  value={connectionForm.timeoutSeconds}
                  disabled={connectionFormState === 'saving'}
                  onChange={(event) =>
                    handleConnectionFormChange('timeoutSeconds', event.target.value)
                  }
                />
                <small>
                  1–30 detik. Timeout setelah pengiriman tetap memerlukan pemeriksaan hasil.
                </small>
              </label>

              <label>
                Password
                <input
                  autoComplete="new-password"
                  onChange={(event) => handleConnectionFormChange('password', event.target.value)}
                  placeholder="Kosongkan jika tidak ingin ubah"
                  type="password"
                  value={connectionForm.password}
                />
              </label>

              <label>
                Status
                <Select
                  onChange={(event) =>
                    handleConnectionFormChange(
                      'status',
                      event.target.value as NeoFeederConnectionStatus,
                    )
                  }
                  value={connectionForm.status}
                >
                  <option value="draft">Draft</option>
                  <option value="active">Aktif</option>
                  <option value="inactive">Nonaktif</option>
                  <option value="error">Error</option>
                </Select>
              </label>

              {connectionFormState === 'error' ? (
                <p className="auth-error">{connectionFormError}</p>
              ) : null}
              <button
                className="app-button app-button-primary"
                disabled={connectionFormState === 'saving' || tenants.length === 0}
                type="submit"
              >
                <DatabaseZap size={18} />
                {connectionFormState === 'saving' ? 'Menyimpan...' : 'Simpan Credential'}
              </button>
            </form>
          </>
        )}
      </FormDialog>
      <FormDialog
        title="Upload Excel"
        open={dialog === 'upload'}
        onClose={() => setDialog(null)}
        busy={importUploadState === 'saving'}
      >
        {tenants.length === 0 ? (
          <EmptyState
            icon={Building2}
            title="Tambahkan kampus terlebih dahulu"
            action={
              <AppButton
                icon={Plus}
                onClick={() => {
                  setDialog('campus');
                  navigate('campus');
                }}
              >
                Tambah kampus
              </AppButton>
            }
          />
        ) : (
          <>
            <form className="stack-form" onSubmit={handleUploadImportBatch}>
              <label>
                Kampus
                <Select
                  disabled={tenants.length === 0}
                  onChange={(event) => setImportUploadTenantId(event.target.value)}
                  required
                  value={importUploadTenantId}
                >
                  {tenants.length === 0 ? <option value="">Buat kampus dulu</option> : null}
                  {tenants.map((tenant) => (
                    <option key={tenant.id} value={tenant.id}>
                      {tenant.name}
                    </option>
                  ))}
                </Select>
              </label>

              <label>
                File Excel (.xlsx, .xls)
                <input
                  accept=".xlsx,.xls"
                  className="file-input"
                  key={importUploadInputKey}
                  onChange={handleImportFileChange}
                  required
                  type="file"
                />
              </label>

              {importUploadState === 'error' ? (
                <p className="auth-error">{importUploadError}</p>
              ) : null}

              <button
                className="app-button app-button-primary"
                disabled={
                  importUploadState === 'saving' || tenants.length === 0 || !importUploadFile
                }
                type="submit"
              >
                <Upload size={18} />
                {importUploadState === 'saving' ? 'Mengupload...' : 'Upload Workbook'}
              </button>
            </form>
          </>
        )}
      </FormDialog>
    </>
  );
}
