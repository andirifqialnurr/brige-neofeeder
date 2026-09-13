import {
  Building2,
  DatabaseZap,
  FileSpreadsheet,
  LayoutDashboard,
  Moon,
  Sun,
  Upload,
  Waypoints,
} from 'lucide-react';
import { type ChangeEvent, type FormEvent, useCallback, useEffect, useMemo, useState } from 'react';

import { useTheme } from '@/hooks/use-theme';
import {
  clearAuthSession,
  createNeoFeederConnection,
  createTenant,
  downloadNeoFeederTemplate,
  getCurrentUser,
  getNeoFeederConnections,
  getReferenceStatus,
  getStoredAuthUser,
  getTenants,
  hasStoredApiToken,
  login,
  logout,
  syncReferences,
  testNeoFeederConnection,
  updateNeoFeederConnection,
  uploadImportBatch,
  type AuthUser,
  type NeoFeederConnection,
  type NeoFeederConnectionTestResult,
  type NeoFeederConnectionStatus,
  type ReferenceStatus,
  type Tenant,
  type TenantStatus,
} from '@/lib/api';
import { type SidebarNavItem } from '@/components/ui';

import { goTo, pagePaths, useRoute, loginDestination, type PageId } from '@/lib/router';
type AppNavItem = SidebarNavItem & {
  id: PageId;
};

export const navItems: AppNavItem[] = [
  { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { id: 'campus', label: 'Kampus', icon: Building2 },
  { id: 'neo-feeder', label: 'Neo Feeder', icon: DatabaseZap },
  { id: 'template-excel', label: 'Template Excel', icon: FileSpreadsheet },
  { id: 'import-batch', label: 'Import Batch', icon: Upload },
  { id: 'mapping', label: 'Mapping', icon: Waypoints },
];

export const campusColumns = ['Kampus', 'Kode PT', 'Status', 'Diperbarui'];
export const templateColumns = ['Sheet', 'Isi'];

export const pageMeta: Record<PageId, { eyebrow: string; title: string }> = {
  dashboard: { eyebrow: 'Dashboard', title: 'Operasional Neo Feeder' },
  campus: { eyebrow: 'Master Data', title: 'Kampus' },
  'neo-feeder': { eyebrow: 'Integrasi', title: 'Neo Feeder' },
  'template-excel': { eyebrow: 'Template', title: 'Template Excel' },
  'import-batch': { eyebrow: 'Import', title: 'Import Batch' },
  mapping: { eyebrow: 'Otomatisasi', title: 'Mapping SIAKAD' },
};

export const tenantStatusLabels: Record<TenantStatus, string> = {
  active: 'Aktif',
  inactive: 'Nonaktif',
  draft: 'Draft',
};

export const tenantStatusTones: Record<TenantStatus, 'success' | 'neutral' | 'warning'> = {
  active: 'success',
  inactive: 'neutral',
  draft: 'warning',
};

export const connectionStatusLabels: Record<NeoFeederConnectionStatus, string> = {
  active: 'Aktif',
  inactive: 'Nonaktif',
  draft: 'Draft',
  error: 'Error',
};

export const connectionStatusTones: Record<
  NeoFeederConnectionStatus,
  'success' | 'neutral' | 'warning' | 'destructive'
> = {
  active: 'success',
  inactive: 'neutral',
  draft: 'warning',
  error: 'destructive',
};

export function formatDateTime(value: string | null) {
  if (!value) {
    return '-';
  }

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

export function useWorkspaceController() {
  const { resolvedTheme: theme, setTheme } = useTheme();
  const nextTheme: 'light' | 'dark' = theme === 'dark' ? 'light' : 'dark';
  const ThemeIcon = theme === 'dark' ? Sun : Moon;
  const [authState, setAuthState] = useState<'checking' | 'ready'>(() =>
    hasStoredApiToken() ? 'checking' : 'ready',
  );
  const [authUser, setAuthUser] = useState<AuthUser | null>(() => getStoredAuthUser());
  const [loginEmail, setLoginEmail] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [loginState, setLoginState] = useState<'idle' | 'loading' | 'error'>('idle');
  const [loginError, setLoginError] = useState('');
  const route = useRoute();
  const activePage = route.page;
  const appScreen = route.screen;
  const [dialog, setDialog] = useState<'campus' | 'connection' | 'upload' | null>(null);
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [connectionTab, setConnectionTab] = useState<'connections' | 'references'>('connections');
  const [feedback, setFeedback] = useState('');
  const navigate = (page: PageId) => {
    goTo(pagePaths[page]);
    setMobileNavOpen(false);
    setFeedback('');
  };
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [tenantState, setTenantState] = useState<'idle' | 'loading' | 'loaded' | 'error'>('idle');
  const [tenantError, setTenantError] = useState('');
  const [tenantForm, setTenantForm] = useState<{
    name: string;
    code: string;
    status: TenantStatus;
  }>({
    name: '',
    code: '',
    status: 'draft',
  });
  const [tenantFormState, setTenantFormState] = useState<'idle' | 'saving' | 'error'>('idle');
  const [tenantFormError, setTenantFormError] = useState('');
  const [connections, setConnections] = useState<NeoFeederConnection[]>([]);
  const [connectionState, setConnectionState] = useState<'idle' | 'loading' | 'loaded' | 'error'>(
    'idle',
  );
  const [connectionError, setConnectionError] = useState('');
  const [connectionForm, setConnectionForm] = useState<{
    tenantId: string;
    baseUrl: string;
    username: string;
    password: string;
    status: NeoFeederConnectionStatus;
  }>({
    tenantId: '',
    baseUrl: '',
    username: '',
    password: '',
    status: 'draft',
  });
  const [connectionFormState, setConnectionFormState] = useState<'idle' | 'saving' | 'error'>(
    'idle',
  );
  const [connectionFormError, setConnectionFormError] = useState('');
  const [connectionTestingId, setConnectionTestingId] = useState('');
  const [connectionTestResult, setConnectionTestResult] =
    useState<NeoFeederConnectionTestResult | null>(null);
  const [connectionTestError, setConnectionTestError] = useState('');
  const [templateDownloadState, setTemplateDownloadState] = useState<'idle' | 'loading' | 'error'>(
    'idle',
  );
  const [templateDownloadError, setTemplateDownloadError] = useState('');
  const [referenceStatus, setReferenceStatus] = useState<ReferenceStatus | null>(null);
  const [referenceStatusState, setReferenceStatusState] = useState<
    'idle' | 'loading' | 'loaded' | 'error'
  >('idle');
  const [referenceTenantId, setReferenceTenantId] = useState('');
  const [referenceSyncState, setReferenceSyncState] = useState<
    'idle' | 'loading' | 'queued' | 'error'
  >('idle');
  const [referenceSyncError, setReferenceSyncError] = useState('');
  const [referenceQueuedCount, setReferenceQueuedCount] = useState(0);
  const [importUploadTenantId, setImportUploadTenantId] = useState('');
  const [importUploadFile, setImportUploadFile] = useState<File | null>(null);
  const [importUploadInputKey, setImportUploadInputKey] = useState(0);
  const [importUploadState, setImportUploadState] = useState<'idle' | 'saving' | 'error'>('idle');
  const [importUploadError, setImportUploadError] = useState('');
  const [importRevision, setImportRevision] = useState(0);
  const currentPage = pageMeta[activePage];

  const loadTenants = useCallback(async () => {
    setTenantState('loading');

    try {
      const items = await getTenants();
      setTenants(items);
      setTenantState('loaded');
      setTenantError('');
    } catch (error) {
      setTenantState('error');
      setTenantError(error instanceof Error ? error.message : 'Daftar kampus belum bisa dimuat.');
    }
  }, []);

  const loadConnections = useCallback(async () => {
    setConnectionState('loading');

    try {
      const items = await getNeoFeederConnections();
      setConnections(items);
      setConnectionState('loaded');
      setConnectionError('');
    } catch (error) {
      setConnectionState('error');
      setConnectionError(
        error instanceof Error ? error.message : 'Daftar koneksi Neo Feeder belum bisa dimuat.',
      );
    }
  }, []);

  const loadReferenceStatus = useCallback(async (tenantId?: string) => {
    setReferenceStatusState('loading');

    try {
      const status = await getReferenceStatus(tenantId);
      setReferenceStatus(status);
      setReferenceStatusState(status === null ? 'idle' : 'loaded');
    } catch {
      setReferenceStatusState('error');
    }
  }, []);

  useEffect(() => {
    let mounted = true;

    if (!hasStoredApiToken()) {
      setAuthState('ready');
      return undefined;
    }

    setAuthState('checking');
    getCurrentUser()
      .then((user) => {
        if (!mounted) {
          return;
        }

        setAuthUser(user);
      })
      .catch(() => {
        if (!mounted) {
          return;
        }

        clearAuthSession();
        setAuthUser(null);
      })
      .finally(() => {
        if (mounted) {
          setAuthState('ready');
        }
      });

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (appScreen !== 'app' || !authUser || authState !== 'ready') {
      return undefined;
    }

    loadReferenceStatus(referenceTenantId || undefined);

    return undefined;
  }, [appScreen, authUser, authState, loadReferenceStatus, referenceTenantId]);

  useEffect(() => {
    if (appScreen !== 'app' || !authUser || authState !== 'ready') {
      return undefined;
    }

    loadTenants();

    return undefined;
  }, [appScreen, authUser, authState, loadTenants]);

  useEffect(() => {
    if (appScreen !== 'app' || !authUser || authState !== 'ready') {
      return undefined;
    }

    loadConnections();

    return undefined;
  }, [appScreen, authUser, authState, loadConnections]);

  useEffect(() => {
    if (connectionForm.tenantId !== '' || tenants.length === 0) {
      return;
    }

    setConnectionForm((current) => ({
      ...current,
      tenantId: tenants[0].id,
    }));
  }, [connectionForm.tenantId, tenants]);

  useEffect(() => {
    if (importUploadTenantId !== '' || tenants.length === 0) {
      return;
    }

    setImportUploadTenantId(tenants[0].id);
  }, [importUploadTenantId, tenants]);

  useEffect(() => {
    if (referenceTenantId !== '' || tenants.length === 0) {
      return;
    }

    setReferenceTenantId(tenants[0].id);
  }, [referenceTenantId, tenants]);

  const referencePreview = useMemo(() => referenceStatus?.endpoints ?? [], [referenceStatus]);
  const tenantNameById = useMemo(
    () => new Map(tenants.map((tenant) => [tenant.id, tenant.name])),
    [tenants],
  );
  const handleLogin = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setLoginState('loading');
    setLoginError('');

    try {
      const user = await login(loginEmail, loginPassword);
      setAuthUser(user);
      goTo(loginDestination(location.search), true);
      setLoginPassword('');
      setLoginState('idle');
    } catch (error) {
      setLoginState('error');
      setLoginError(error instanceof Error ? error.message : 'Login gagal.');
    }
  };

  const handleLogout = async () => {
    await logout();
    setAuthUser(null);
    setDialog(null);
    setTenants([]);
    setConnections([]);
    setTenantState('idle');
    setConnectionState('idle');
    setConnectionForm({ tenantId: '', baseUrl: '', username: '', password: '', status: 'draft' });
    setConnectionTestResult(null);
    setConnectionTestError('');
    setReferenceTenantId('');
    setReferenceSyncState('idle');
    setImportUploadTenantId('');
    setImportUploadFile(null);
    setImportUploadInputKey((value) => value + 1);
    setFeedback('');
    setMobileNavOpen(false);
    setReferenceStatus(null);
    setReferenceStatusState('idle');
    goTo('/login', true);
  };

  const handleTenantFormChange = (field: keyof typeof tenantForm, value: string) => {
    setTenantForm((current) => ({
      ...current,
      [field]: value,
    }));
  };

  const handleCreateTenant = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setTenantFormState('saving');
    setTenantFormError('');

    try {
      const tenant = await createTenant({
        name: tenantForm.name,
        code: tenantForm.code,
        status: tenantForm.status,
      });

      setTenants((current) => [tenant, ...current]);
      setTenantForm({ name: '', code: '', status: 'draft' });
      setTenantFormState('idle');
      setTenantState('loaded');
      setDialog(null);
      setFeedback('Kampus ditambahkan.');
    } catch (error) {
      setTenantFormState('error');
      setTenantFormError(error instanceof Error ? error.message : 'Kampus belum bisa disimpan.');
    }
  };

  const handleConnectionFormChange = (field: keyof typeof connectionForm, value: string) => {
    setConnectionForm((current) => ({
      ...current,
      [field]: value,
    }));
  };

  const applyConnectionToForm = (tenantId: string) => {
    setConnectionFormState('idle');
    setConnectionFormError('');
    const connection = connections.find((item) => item.tenant_id === tenantId);

    setConnectionForm({
      tenantId,
      baseUrl: connection?.base_url ?? '',
      username: connection?.username ?? '',
      password: '',
      status: connection?.status ?? 'draft',
    });
  };

  const handleSaveConnection = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setConnectionFormState('saving');
    setConnectionFormError('');

    try {
      const existingConnection = connections.find(
        (item) => item.tenant_id === connectionForm.tenantId,
      );
      const payload = {
        base_url: connectionForm.baseUrl,
        username: connectionForm.username,
        status: connectionForm.status,
        ...(connectionForm.password ? { password: connectionForm.password } : {}),
      };

      const savedConnection = existingConnection
        ? await updateNeoFeederConnection(existingConnection.id, payload)
        : await createNeoFeederConnection({
            tenant_id: connectionForm.tenantId,
            ...payload,
          });

      setConnections((current) => {
        const otherConnections = current.filter((item) => item.id !== savedConnection.id);
        return [savedConnection, ...otherConnections];
      });
      setConnectionForm((current) => ({ ...current, password: '' }));
      setConnectionFormState('idle');
      setConnectionState('loaded');
      setDialog(null);
      setFeedback('Koneksi disimpan.');
    } catch (error) {
      setConnectionFormState('error');
      setConnectionFormError(
        error instanceof Error ? error.message : 'Koneksi Neo Feeder belum bisa disimpan.',
      );
    }
  };

  const handleTestConnection = async (connectionId: string) => {
    setConnectionTestingId(connectionId);
    setConnectionTestError('');
    setConnectionTestResult(null);

    try {
      const result = await testNeoFeederConnection(connectionId);

      setConnectionTestResult(result);
      setConnections((current) =>
        current.map((item) => (item.id === result.connection.id ? result.connection : item)),
      );
    } catch (error) {
      setConnectionTestError(
        error instanceof Error ? error.message : 'Test koneksi Neo Feeder gagal.',
      );
    } finally {
      setConnectionTestingId('');
    }
  };

  const handleDownloadTemplate = async () => {
    setTemplateDownloadState('loading');
    setTemplateDownloadError('');

    try {
      await downloadNeoFeederTemplate();
      setTemplateDownloadState('idle');
    } catch (error) {
      setTemplateDownloadState('error');
      setTemplateDownloadError(
        error instanceof Error ? error.message : 'Template belum bisa didownload.',
      );
    }
  };

  const handleSyncReferences = async () => {
    setReferenceSyncState('loading');
    setReferenceSyncError('');
    setReferenceQueuedCount(0);

    if (!referenceTenantId) {
      setReferenceSyncState('error');
      setReferenceSyncError('Pilih tenant kampus untuk sync referensi.');
      return;
    }

    try {
      const result = await syncReferences({ tenantId: referenceTenantId });
      setReferenceQueuedCount(result.queued_endpoint_count);
      setReferenceSyncState('queued');
      await loadReferenceStatus(referenceTenantId);
    } catch (error) {
      setReferenceSyncState('error');
      setReferenceSyncError(
        error instanceof Error ? error.message : 'Sync referensi belum bisa diantrekan.',
      );
    }
  };

  const handleImportFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    setImportUploadFile(event.target.files?.[0] ?? null);
  };

  const handleUploadImportBatch = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setImportUploadState('saving');
    setImportUploadError('');

    if (!importUploadTenantId || !importUploadFile) {
      setImportUploadState('error');
      setImportUploadError('Pilih kampus dan file Excel terlebih dahulu.');
      return;
    }

    try {
      const batch = await uploadImportBatch({
        tenantId: importUploadTenantId,
        file: importUploadFile,
      });

      setImportRevision((value) => value + 1);
      setImportUploadFile(null);
      setImportUploadInputKey((current) => current + 1);
      setImportUploadState('idle');
      setDialog(null);
      goTo(`/import-batch/${batch.id}`);
      setFeedback('Workbook diupload.');
    } catch (error) {
      setImportUploadState('error');
      setImportUploadError(
        error instanceof Error ? error.message : 'Workbook belum bisa diupload.',
      );
    }
  };

  return {
    theme,
    setTheme,
    nextTheme,
    ThemeIcon,
    authState,
    setAuthState,
    authUser,
    setAuthUser,
    loginEmail,
    setLoginEmail,
    loginPassword,
    setLoginPassword,
    loginState,
    setLoginState,
    loginError,
    setLoginError,
    route,
    activePage,
    appScreen,
    dialog,
    setDialog,
    mobileNavOpen,
    setMobileNavOpen,
    connectionTab,
    setConnectionTab,
    feedback,
    setFeedback,
    navigate,
    tenants,
    setTenants,
    tenantState,
    setTenantState,
    tenantError,
    setTenantError,
    tenantForm,
    setTenantForm,
    tenantFormState,
    setTenantFormState,
    tenantFormError,
    setTenantFormError,
    connections,
    setConnections,
    connectionState,
    setConnectionState,
    connectionError,
    setConnectionError,
    connectionForm,
    setConnectionForm,
    connectionFormState,
    setConnectionFormState,
    connectionFormError,
    setConnectionFormError,
    connectionTestingId,
    setConnectionTestingId,
    connectionTestResult,
    setConnectionTestResult,
    connectionTestError,
    setConnectionTestError,
    templateDownloadState,
    setTemplateDownloadState,
    templateDownloadError,
    setTemplateDownloadError,
    referenceStatus,
    setReferenceStatus,
    referenceStatusState,
    setReferenceStatusState,
    referenceTenantId,
    setReferenceTenantId,
    referenceSyncState,
    setReferenceSyncState,
    referenceSyncError,
    setReferenceSyncError,
    referenceQueuedCount,
    setReferenceQueuedCount,
    importUploadTenantId,
    setImportUploadTenantId,
    importUploadFile,
    setImportUploadFile,
    importUploadInputKey,
    setImportUploadInputKey,
    importUploadState,
    setImportUploadState,
    importUploadError,
    setImportUploadError,
    importRevision,
    setImportRevision,
    currentPage,
    loadTenants,
    loadConnections,
    loadReferenceStatus,
    referencePreview,
    tenantNameById,
    handleLogin,
    handleLogout,
    handleTenantFormChange,
    handleCreateTenant,
    handleConnectionFormChange,
    applyConnectionToForm,
    handleSaveConnection,
    handleTestConnection,
    handleDownloadTemplate,
    handleSyncReferences,
    handleImportFileChange,
    handleUploadImportBatch,
  };
}
