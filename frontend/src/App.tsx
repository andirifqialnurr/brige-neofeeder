import {
  ArrowRight,
  Building2,
  CircleUserRound,
  Database,
  DatabaseZap,
  Download,
  FileSpreadsheet,
  LayoutDashboard,
  LogOut,
  Moon,
  Menu,
  X,
  Pencil,
  PlugZap,
  Plus,
  RefreshCcw,
  Search,
  ShieldCheck,
  Sun,
  Upload,
  Waypoints,
} from 'lucide-react';
import { type ChangeEvent, type FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { LandingPage, LoginPage } from './components/public-pages';
import { useTheme } from '@/hooks/use-theme';
import {
  clearAuthSession,
  createNeoFeederConnection,
  createTenant,
  downloadNeoFeederTemplate,
  getCurrentUser,
  getImportBatches,
  getNeoFeederConnections,
  getReferenceStatus,
  getStoredAuthUser,
  getTenants,
  hasStoredApiToken,
  login,
  logout,
  runImportBatchDryRun,
  syncReferences,
  testNeoFeederConnection,
  updateNeoFeederConnection,
  uploadImportBatch,
  type AuthUser,
  type DryRunPreview,
  type ImportBatch,
  type ImportBatchStatus,
  type NeoFeederConnection,
  type NeoFeederConnectionTestResult,
  type NeoFeederConnectionStatus,
  type ReferenceStatus,
  type Tenant,
  type TenantStatus,
} from '@/lib/api';
import {
  AppButton,
  AppShell,
  Brand,
  DataTable,
  EmptyState,
  ErrorState,
  LoadingState,
  FormDialog,
  HelpTip,
  IconButton,
  ViewTabs,
  PageHeader,
  SectionHeader,
  Sidebar,
  SidebarNav,
  type SidebarNavItem,
  StatusBadge,
  Topbar,
  WorkspacePanel,
} from './components/ui';

type PageId =
  | 'dashboard'
  | 'campus'
  | 'neo-feeder'
  | 'template-excel'
  | 'import-batch'
  | 'validation'
  | 'mapping';

type AppNavItem = SidebarNavItem & {
  id: PageId;
};

type AppScreen = 'landing' | 'login' | 'app';

const navItems: AppNavItem[] = [
  { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { id: 'campus', label: 'Kampus', icon: Building2 },
  { id: 'neo-feeder', label: 'Neo Feeder', icon: DatabaseZap },
  { id: 'template-excel', label: 'Template Excel', icon: FileSpreadsheet },
  { id: 'import-batch', label: 'Import Batch', icon: Upload },
  { id: 'validation', label: 'Validasi', icon: ShieldCheck },
  { id: 'mapping', label: 'Mapping', icon: Waypoints },
];

const batchColumns = ['Batch', 'Kampus', 'Status', 'Valid', 'Error', 'Diperbarui'];
const campusColumns = ['Kampus', 'Kode PT', 'Status', 'Diperbarui'];
const templateColumns = ['Sheet', 'Isi'];
const validationColumns = ['Baris', 'Kanal', 'Operasi', 'Temuan', 'Status'];

const pageMeta: Record<PageId, { eyebrow: string; title: string }> = {
  dashboard: { eyebrow: 'Dashboard', title: 'Operasional Neo Feeder' },
  campus: { eyebrow: 'Master Data', title: 'Kampus' },
  'neo-feeder': { eyebrow: 'Integrasi', title: 'Neo Feeder' },
  'template-excel': { eyebrow: 'Template', title: 'Template Excel' },
  'import-batch': { eyebrow: 'Import', title: 'Import Batch' },
  validation: { eyebrow: 'Validasi', title: 'Validasi Data' },
  mapping: { eyebrow: 'Otomatisasi', title: 'Mapping SIAKAD' },
};

const tenantStatusLabels: Record<TenantStatus, string> = {
  active: 'Aktif',
  inactive: 'Nonaktif',
  draft: 'Draft',
};

const tenantStatusTones: Record<TenantStatus, 'success' | 'neutral' | 'warning'> = {
  active: 'success',
  inactive: 'neutral',
  draft: 'warning',
};

const connectionStatusLabels: Record<NeoFeederConnectionStatus, string> = {
  active: 'Aktif',
  inactive: 'Nonaktif',
  draft: 'Draft',
  error: 'Error',
};

const connectionStatusTones: Record<
  NeoFeederConnectionStatus,
  'success' | 'neutral' | 'warning' | 'destructive'
> = {
  active: 'success',
  inactive: 'neutral',
  draft: 'warning',
  error: 'destructive',
};

const importBatchStatusLabels: Record<ImportBatchStatus, string> = {
  uploaded: 'Diupload',
  parsing: 'Diproses',
  ready: 'Siap validasi',
  validated: 'Valid',
  invalid: 'Perlu perbaikan',
  dry_run_ready: 'Dry-run',
  syncing: 'Sinkronisasi',
  synced: 'Tersinkron',
  failed: 'Gagal',
};

const importBatchStatusTones: Record<
  ImportBatchStatus,
  'success' | 'neutral' | 'warning' | 'destructive' | 'info'
> = {
  uploaded: 'info',
  parsing: 'info',
  ready: 'warning',
  validated: 'success',
  invalid: 'destructive',
  dry_run_ready: 'success',
  syncing: 'info',
  synced: 'success',
  failed: 'destructive',
};

function formatDateTime(value: string | null) {
  if (!value) {
    return '-';
  }

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function formatFileSize(value?: number) {
  if (!value) {
    return '-';
  }

  if (value < 1024 * 1024) {
    return `${Math.ceil(value / 1024)} KB`;
  }

  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function shortId(value: string) {
  return value.slice(0, 8);
}

function App() {
  const { resolvedTheme: theme, setTheme } = useTheme();
  const nextTheme = theme === 'dark' ? 'light' : 'dark';
  const ThemeIcon = theme === 'dark' ? Sun : Moon;
  const [appScreen, setAppScreen] = useState<AppScreen>(() =>
    hasStoredApiToken() ? 'app' : 'landing',
  );
  const [authState, setAuthState] = useState<'checking' | 'ready'>(() =>
    hasStoredApiToken() ? 'checking' : 'ready',
  );
  const [authUser, setAuthUser] = useState<AuthUser | null>(() => getStoredAuthUser());
  const [loginEmail, setLoginEmail] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [loginState, setLoginState] = useState<'idle' | 'loading' | 'error'>('idle');
  const [loginError, setLoginError] = useState('');
  const [activePage, setActivePage] = useState<PageId>('dashboard');
  const [dialog, setDialog] = useState<'campus' | 'connection' | 'upload' | null>(null);
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [connectionTab, setConnectionTab] = useState<'connections' | 'references'>('connections');
  const [batchSearch, setBatchSearch] = useState('');
  const [feedback, setFeedback] = useState('');
  const navigate = (page: PageId) => {
    setActivePage(page);
    setMobileNavOpen(false);
    setFeedback('');
  };
  const selectBatch = (id: string) => {
    setDryRunBatchId(id);
    setDryRunPreview(null);
    setDryRunState('idle');
    setDryRunError('');
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
  const [importBatches, setImportBatches] = useState<ImportBatch[]>([]);
  const [importBatchState, setImportBatchState] = useState<'idle' | 'loading' | 'loaded' | 'error'>(
    'idle',
  );
  const [importBatchError, setImportBatchError] = useState('');
  const [importUploadTenantId, setImportUploadTenantId] = useState('');
  const [importUploadFile, setImportUploadFile] = useState<File | null>(null);
  const [importUploadInputKey, setImportUploadInputKey] = useState(0);
  const [importUploadState, setImportUploadState] = useState<'idle' | 'saving' | 'error'>('idle');
  const [importUploadError, setImportUploadError] = useState('');
  const [dryRunBatchId, setDryRunBatchId] = useState('');
  const [dryRunPreview, setDryRunPreview] = useState<DryRunPreview | null>(null);
  const [dryRunState, setDryRunState] = useState<'idle' | 'loading' | 'loaded' | 'error'>('idle');
  const [dryRunError, setDryRunError] = useState('');
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

  const loadImportBatches = useCallback(async () => {
    setImportBatchState('loading');

    try {
      const items = await getImportBatches();
      setImportBatches(items);
      setImportBatchState('loaded');
      setImportBatchError('');
    } catch (error) {
      setImportBatchState('error');
      setImportBatchError(
        error instanceof Error ? error.message : 'Daftar import batch belum bisa dimuat.',
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
        setAppScreen('app');
      })
      .catch(() => {
        if (!mounted) {
          return;
        }

        clearAuthSession();
        setAuthUser(null);
        setAppScreen('landing');
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
    if (appScreen !== 'app') {
      return undefined;
    }

    loadReferenceStatus(referenceTenantId || undefined);

    return undefined;
  }, [appScreen, loadReferenceStatus, referenceTenantId]);

  useEffect(() => {
    if (appScreen !== 'app') {
      return undefined;
    }

    loadTenants();

    return undefined;
  }, [appScreen, loadTenants]);

  useEffect(() => {
    if (appScreen !== 'app') {
      return undefined;
    }

    loadConnections();

    return undefined;
  }, [appScreen, loadConnections]);

  useEffect(() => {
    if (appScreen !== 'app') {
      return undefined;
    }

    loadImportBatches();

    return undefined;
  }, [appScreen, loadImportBatches]);

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

  useEffect(() => {
    if (dryRunBatchId !== '' || importBatches.length === 0) {
      return;
    }

    setDryRunBatchId(importBatches[0].id);
  }, [dryRunBatchId, importBatches]);

  const referencePreview = useMemo(() => referenceStatus?.endpoints ?? [], [referenceStatus]);
  const tenantNameById = useMemo(
    () => new Map(tenants.map((tenant) => [tenant.id, tenant.name])),
    [tenants],
  );
  const filteredBatches = useMemo(() => {
    const query = batchSearch.trim().toLocaleLowerCase('id');
    return importBatches.filter((batch) =>
      [
        batch.summary.original_name,
        batch.id,
        tenantNameById.get(batch.tenant_id) ?? batch.tenant_name,
      ].some((value) => value?.toLocaleLowerCase('id').includes(query)),
    );
  }, [batchSearch, importBatches, tenantNameById]);
  const importBatchRows = useMemo(
    () =>
      (activePage === 'dashboard' ? importBatches.slice(0, 5) : filteredBatches).map((batch) => [
        <div className="batch-cell" key={`${batch.id}-batch`}>
          <button
            className="text-link"
            onClick={() => {
              selectBatch(batch.id);
              navigate('validation');
            }}
            type="button"
          >
            {batch.summary.original_name ?? `Batch ${shortId(batch.id)}`}
          </button>
          <span>
            {shortId(batch.id)} · {formatFileSize(batch.summary.size)}
          </span>
        </div>,
        <strong className="table-primary" key={`${batch.id}-tenant`}>
          {tenantNameById.get(batch.tenant_id) ?? batch.tenant_name ?? batch.tenant_id}
        </strong>,
        <StatusBadge
          key={`${batch.id}-status`}
          tone={importBatchStatusTones[batch.status] ?? 'neutral'}
        >
          {importBatchStatusLabels[batch.status] ?? batch.status}
        </StatusBadge>,
        <span key={`${batch.id}-valid`}>{batch.summary.valid_rows ?? '-'}</span>,
        <span key={`${batch.id}-invalid`}>{batch.summary.invalid_rows ?? '-'}</span>,
        <span key={`${batch.id}-updated`}>{formatDateTime(batch.updated_at)}</span>,
      ]),
    [activePage, filteredBatches, importBatches, tenantNameById],
  );
  const dryRunIssueRows = useMemo(() => {
    if (!dryRunPreview) {
      return [];
    }

    return dryRunPreview.payload_preview.flatMap((item) => {
      const issues = [
        ...(item.validation_result.errors ?? []).map((issue) => ({
          ...issue,
          level: 'error' as const,
        })),
        ...(item.validation_result.warnings ?? []).map((issue) => ({
          ...issue,
          level: 'warning' as const,
        })),
        ...(item.validation_result.info ?? []).map((issue) => ({
          ...issue,
          level: 'info' as const,
        })),
      ];

      if (issues.length === 0) {
        return [
          [
            <strong className="table-primary" key={`${item.staging_record_id}-row`}>
              {item.row_number}
            </strong>,
            <span className="mono" key={`${item.staging_record_id}-channel`}>
              {item.channel}
            </span>,
            <span key={`${item.staging_record_id}-operation`}>
              {item.action ?? item.candidate_operation}
            </span>,
            <span key={`${item.staging_record_id}-issue`}>-</span>,
            <StatusBadge
              key={`${item.staging_record_id}-status`}
              tone={item.candidate_operation === 'skip' ? 'neutral' : 'success'}
            >
              {item.candidate_operation === 'skip' ? 'Dilewati' : 'Siap'}
            </StatusBadge>,
          ],
        ];
      }

      return issues.map((issue, index) => [
        <strong className="table-primary" key={`${item.staging_record_id}-${index}-row`}>
          {item.row_number}
        </strong>,
        <span className="mono" key={`${item.staging_record_id}-${index}-channel`}>
          {item.channel}
        </span>,
        <span key={`${item.staging_record_id}-${index}-operation`}>
          {item.action ?? item.candidate_operation}
        </span>,
        <span key={`${item.staging_record_id}-${index}-issue`}>
          {issue.field ? `${issue.field}: ` : ''}
          {issue.message ?? issue.rule ?? 'Issue validasi'}
        </span>,
        <StatusBadge
          key={`${item.staging_record_id}-${index}-status`}
          tone={
            issue.level === 'error' ? 'destructive' : issue.level === 'warning' ? 'warning' : 'info'
          }
        >
          {issue.level === 'error' ? 'Error' : issue.level === 'warning' ? 'Warning' : 'Info'}
        </StatusBadge>,
      ]);
    });
  }, [dryRunPreview]);

  const handleLogin = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setLoginState('loading');
    setLoginError('');

    try {
      const user = await login(loginEmail, loginPassword);
      setAuthUser(user);
      setAppScreen('app');
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
    setReferenceStatus(null);
    setReferenceStatusState('idle');
    setAppScreen('landing');
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

      setImportBatches((current) => [batch, ...current.filter((item) => item.id !== batch.id)]);
      setImportUploadFile(null);
      setImportUploadInputKey((current) => current + 1);
      setImportUploadState('idle');
      setImportBatchState('loaded');
      setDialog(null);
      setActivePage('import-batch');
      setFeedback('Workbook diupload.');
      await loadImportBatches();
    } catch (error) {
      setImportUploadState('error');
      setImportUploadError(
        error instanceof Error ? error.message : 'Workbook belum bisa diupload.',
      );
    }
  };

  const handleRunDryRun = async () => {
    setDryRunPreview(null);
    setDryRunState('loading');
    setDryRunError('');

    if (!dryRunBatchId) {
      setDryRunState('error');
      setDryRunError('Pilih import batch terlebih dahulu.');
      return;
    }

    try {
      const preview = await runImportBatchDryRun(dryRunBatchId);
      setDryRunPreview(preview);
      setDryRunState('loaded');
      await loadImportBatches();
    } catch (error) {
      setDryRunState('error');
      setDryRunError(error instanceof Error ? error.message : 'Dry-run belum bisa dijalankan.');
    }
  };

  const renderBatchTable = (dashboard = false) => (
    <DataTable
      columns={batchColumns}
      rows={importBatchState === 'loaded' ? importBatchRows : []}
      emptyState={
        importBatchState === 'loading' || importBatchState === 'idle' ? (
          <LoadingState label="Memuat batch" />
        ) : importBatchState === 'error' ? (
          <ErrorState title="Batch gagal dimuat" description={importBatchError} />
        ) : (
          <EmptyState
            icon={FileSpreadsheet}
            title={!dashboard && batchSearch ? 'Tidak ada hasil' : 'Belum ada batch'}
            description={!dashboard && batchSearch ? 'Coba nama file atau kampus lain.' : undefined}
          />
        )
      }
    />
  );

  const renderDashboard = () => (
    <>
      <PageHeader
        title="Dashboard"
        action={
          <AppButton icon={Upload} onClick={() => setDialog('upload')}>
            Upload Excel
          </AppButton>
        }
      />
      <WorkspacePanel>
        <SectionHeader
          title="Batch terbaru"
          action={
            <AppButton variant="ghost" icon={ArrowRight} onClick={() => navigate('import-batch')}>
              Lihat semua
            </AppButton>
          }
        />
        {renderBatchTable(true)}
      </WorkspacePanel>
      <div className="quick-links">
        <button type="button" onClick={() => navigate('template-excel')}>
          <FileSpreadsheet size={20} />
          <span>Template Excel</span>
          <ArrowRight size={16} />
        </button>
        <button type="button" onClick={() => navigate('neo-feeder')}>
          <DatabaseZap size={20} />
          <span>Koneksi Neo Feeder</span>
          <ArrowRight size={16} />
        </button>
      </div>
    </>
  );

  const renderCampus = () => (
    <>
      <PageHeader
        title="Kampus"
        action={
          <AppButton icon={Plus} onClick={() => setDialog('campus')}>
            Tambah kampus
          </AppButton>
        }
      />
      <WorkspacePanel>
        <div className="table-toolbar">
          <span className="muted">
            {tenantState === 'loaded' ? `${tenants.length} kampus` : 'Daftar kampus'}
          </span>
          <IconButton
            label="Muat ulang kampus"
            icon={RefreshCcw}
            onClick={loadTenants}
            disabled={tenantState === 'loading'}
          />
        </div>
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

  const renderReferences = () => (
    <WorkspacePanel>
      <div className="table-toolbar">
        <label className="inline-field">
          Kampus
          <select
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
          </select>
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

  const renderNeoFeeder = () => (
    <>
      <PageHeader
        title="Neo Feeder"
        action={
          connectionTab === 'connections' ? (
            <AppButton
              icon={Plus}
              onClick={() => {
                applyConnectionToForm(tenants[0]?.id ?? '');
                setDialog('connection');
              }}
            >
              Tambah koneksi
            </AppButton>
          ) : undefined
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
          renderReferences()
        ) : (
          <WorkspacePanel>
            <div className="table-toolbar">
              <span className="muted">
                {connectionState === 'loaded' ? `${connections.length} koneksi` : 'Daftar koneksi'}
              </span>
              <IconButton
                label="Muat ulang koneksi"
                icon={RefreshCcw}
                onClick={loadConnections}
                disabled={connectionState === 'loading'}
              />
            </div>
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

  const renderTemplateExcel = () => (
    <>
      <PageHeader
        title="Template Excel"
        action={
          <AppButton
            disabled={templateDownloadState === 'loading'}
            icon={Download}
            onClick={handleDownloadTemplate}
          >
            {templateDownloadState === 'loading' ? 'Menyiapkan...' : 'Download template'}
          </AppButton>
        }
      />
      {templateDownloadState === 'error' ? (
        <ErrorState title="Download gagal" description={templateDownloadError} />
      ) : null}
      <WorkspacePanel>
        <SectionHeader
          title="Isi workbook"
          description="Isi data pada sheet yang dibutuhkan. Petunjuk pengisian tersedia di sheet README."
        />
        <DataTable
          columns={templateColumns}
          rows={[
            ['README', 'Petunjuk pengisian'],
            ...[
              ['mahasiswa_biodata', 'Biodata mahasiswa'],
              ['mahasiswa_riwayat_pendidikan', 'Riwayat pendidikan'],
              ['mata_kuliah', 'Mata kuliah'],
              ['kurikulum', 'Kurikulum'],
              ['matkul_kurikulum', 'Mata kuliah kurikulum'],
              ['kelas_kuliah', 'Kelas kuliah'],
              ['peserta_kelas', 'Peserta kelas'],
              ['dosen_pengajar_kelas', 'Dosen pengajar kelas'],
              ['nilai_perkuliahan', 'Nilai kelas'],
              ['perkuliahan_mahasiswa_akm', 'Aktivitas kuliah mahasiswa'],
              ['mahasiswa_lulus_do', 'Mahasiswa lulus / DO'],
              ['ref_*', 'Daftar referensi'],
            ],
          ]}
        />
      </WorkspacePanel>
    </>
  );

  const renderImportBatch = () => (
    <>
      <PageHeader
        title="Import Batch"
        action={
          <AppButton icon={Upload} onClick={() => setDialog('upload')}>
            Upload Excel
          </AppButton>
        }
      />
      <WorkspacePanel>
        <div className="table-toolbar">
          <label className="search-field">
            <Search size={16} aria-hidden="true" />
            <input
              aria-label="Cari batch"
              type="search"
              value={batchSearch}
              placeholder="Cari file atau kampus..."
              onChange={(event) => setBatchSearch(event.target.value)}
            />
          </label>
          <IconButton
            label="Muat ulang batch"
            icon={RefreshCcw}
            disabled={importBatchState === 'loading'}
            onClick={loadImportBatches}
          />
        </div>
        {renderBatchTable()}
      </WorkspacePanel>
    </>
  );

  const renderValidation = () => (
    <>
      <PageHeader title="Validasi" />
      <WorkspacePanel>
        <div className="validation-toolbar">
          <label className="inline-field">
            Batch
            <select
              disabled={importBatches.length === 0 || dryRunState === 'loading'}
              onChange={(event) => selectBatch(event.target.value)}
              value={dryRunBatchId}
            >
              {importBatches.length === 0 ? <option value="">Belum ada batch</option> : null}
              {importBatches.map((batch) => (
                <option key={batch.id} value={batch.id}>
                  {batch.summary.original_name ?? `Batch ${shortId(batch.id)}`} -{' '}
                  {tenantNameById.get(batch.tenant_id) ?? batch.tenant_name ?? 'Kampus'}
                </option>
              ))}
            </select>
          </label>
          <AppButton
            disabled={dryRunState === 'loading' || !dryRunBatchId}
            icon={ShieldCheck}
            onClick={handleRunDryRun}
          >
            {dryRunState === 'loading' ? 'Memvalidasi...' : 'Jalankan dry-run'}
          </AppButton>
          <HelpTip
            label="Tentang dry-run"
            text="Memeriksa data dan menyiapkan pratinjau. Data belum dikirim ke Neo Feeder."
          />
        </div>
        {dryRunState === 'error' ? (
          <ErrorState title="Dry-run gagal" description={dryRunError} />
        ) : null}
        {dryRunPreview ? (
          <dl className="summary-strip">
            {[
              ['Total baris', dryRunPreview.summary.total_rows],
              ['Valid', dryRunPreview.summary.valid_rows],
              ['Error', dryRunPreview.summary.invalid_rows],
              ['Peringatan', dryRunPreview.summary.warning_rows],
            ].map(([label, value]) => (
              <div key={label}>
                <dt>{label}</dt>
                <dd>{value}</dd>
              </div>
            ))}
          </dl>
        ) : null}
        <DataTable
          columns={validationColumns}
          rows={dryRunIssueRows}
          emptyState={
            dryRunState === 'loading' ? (
              <LoadingState label="Memvalidasi batch" />
            ) : (
              <EmptyState
                icon={ShieldCheck}
                title={dryRunPreview ? 'Tidak ada baris dalam batch' : 'Belum ada hasil validasi'}
              />
            )
          }
        />
      </WorkspacePanel>
    </>
  );

  const renderMapping = () => (
    <>
      <PageHeader title="Mapping SIAKAD" />
      <EmptyState
        icon={Waypoints}
        title="Otomatisasi dalam rencana"
        description="Mapping sumber SIAKAD akan tersedia pada fase 2."
        action={
          <AppButton
            variant="secondary"
            icon={FileSpreadsheet}
            onClick={() => navigate('template-excel')}
          >
            Template Excel
          </AppButton>
        }
      />
    </>
  );

  const renderPage = () => {
    switch (activePage) {
      case 'campus':
        return renderCampus();
      case 'neo-feeder':
        return renderNeoFeeder();
      case 'template-excel':
        return renderTemplateExcel();
      case 'import-batch':
        return renderImportBatch();
      case 'validation':
        return renderValidation();
      case 'mapping':
        return renderMapping();
      default:
        return renderDashboard();
    }
  };

  const themeControl = (
    <IconButton
      label={theme === 'dark' ? 'Gunakan tema terang' : 'Gunakan tema gelap'}
      icon={ThemeIcon}
      onClick={() => setTheme(nextTheme)}
    />
  );

  if (authState === 'checking') {
    return (
      <main className="auth-loading-shell">
        <LoadingState label="Memeriksa sesi" />
      </main>
    );
  }
  if (appScreen === 'landing') {
    return <LandingPage onLogin={() => setAppScreen('login')} themeControl={themeControl} />;
  }
  if (appScreen === 'login') {
    return (
      <LoginPage
        themeControl={themeControl}
        onBack={() => setAppScreen('landing')}
        email={loginEmail}
        password={loginPassword}
        onEmailChange={setLoginEmail}
        onPasswordChange={setLoginPassword}
        onSubmit={handleLogin}
        loading={loginState === 'loading'}
        error={loginState === 'error' ? loginError : ''}
      />
    );
  }

  return (
    <>
      <a className="skip-link" href="#main-content">
        Ke konten utama
      </a>
      <div className="mobile-topbar">
        <Brand icon={Database} title="NeoBridge" subtitle="Workspace" />
        <button
          className="icon-button"
          aria-label={mobileNavOpen ? 'Tutup navigasi' : 'Buka navigasi'}
          aria-expanded={mobileNavOpen}
          aria-controls="app-navigation"
          onClick={() => setMobileNavOpen(!mobileNavOpen)}
          type="button"
        >
          {mobileNavOpen ? <X size={20} /> : <Menu size={20} />}
        </button>
      </div>
      <div className={mobileNavOpen ? 'app-layout nav-open' : 'app-layout'}>
        <AppShell
          sidebar={
            <Sidebar>
              <Brand icon={Database} title="NeoBridge" subtitle="Bridge Neo Feeder" />
              <div id="app-navigation">
                <SidebarNav
                  activeItem={activePage}
                  items={navItems}
                  onItemSelect={(item) => navigate(item.id as PageId)}
                />
              </div>
              <span className="sidebar-footer">Workspace kampus</span>
            </Sidebar>
          }
        >
          <Topbar
            eyebrow="Workspace"
            title={currentPage.eyebrow}
            action={
              <div className="topbar-actions">
                <span className="user-identity">
                  <CircleUserRound size={18} />
                  <span>{authUser?.name}</span>
                </span>
                {themeControl}
                <IconButton label="Keluar" icon={LogOut} onClick={handleLogout} />
              </div>
            }
          />
          <div className="page-content">
            {feedback ? (
              <p className="success-state" role="status">
                {feedback}
              </p>
            ) : null}
            {renderPage()}
          </div>
        </AppShell>
      </div>
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
            <select
              onChange={(event) =>
                handleTenantFormChange('status', event.target.value as TenantStatus)
              }
              value={tenantForm.status}
            >
              <option value="draft">Draft</option>
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
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
                <select
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
                </select>
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
                <select
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
                </select>
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
                <select
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
                </select>
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

export default App;
