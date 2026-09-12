import {
  Activity,
  ArrowRight,
  Bell,
  Building2,
  CheckCircle2,
  Clock3,
  CircleUserRound,
  Database,
  DatabaseZap,
  Download,
  FileSpreadsheet,
  LayoutDashboard,
  LockKeyhole,
  LogIn,
  LogOut,
  Moon,
  Plus,
  RefreshCcw,
  Search,
  ShieldCheck,
  Sun,
  Upload,
  Waypoints,
} from 'lucide-react';
import { type FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { useTheme } from '@/hooks/use-theme';
import {
  clearAuthSession,
  createNeoFeederConnection,
  createTenant,
  getCurrentUser,
  getNeoFeederConnections,
  getReferenceStatus,
  getStoredAuthUser,
  getTenants,
  hasStoredApiToken,
  login,
  logout,
  updateNeoFeederConnection,
  type AuthUser,
  type NeoFeederConnection,
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
  MetricCard,
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

const metrics = [
  { label: 'Koneksi', value: 'Draft', helper: 'Belum test', tone: 'warning', icon: DatabaseZap },
  { label: 'Batch Aktif', value: '0', helper: 'Menunggu upload', tone: 'muted', icon: FileSpreadsheet },
  { label: 'Error Validasi', value: '0', helper: 'Belum ada data', tone: 'success', icon: ShieldCheck },
  { label: 'Queue Sync', value: '0', helper: 'Idle', tone: 'info', icon: Activity },
] as const;

const batchColumns = ['Batch', 'Kampus', 'Status', 'Valid', 'Error', 'Update'];
const campusColumns = ['Kampus', 'Kode PT', 'Status', 'Batch', 'Update'];
const templateColumns = ['Sheet', 'Endpoint', 'Wajib', 'Referensi', 'Status'];
const validationColumns = ['Batch', 'Kategori', 'Field', 'Error', 'Status'];
const mappingColumns = ['Sumber SIAKAD', 'Target Neo Feeder', 'Confidence', 'Status'];

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

const connectionStatusTones: Record<NeoFeederConnectionStatus, 'success' | 'neutral' | 'warning' | 'destructive'> = {
  active: 'success',
  inactive: 'neutral',
  draft: 'warning',
  error: 'destructive',
};

function formatDateTime(value: string | null) {
  if (!value) {
    return 'Belum sync';
  }

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function App() {
  const { theme, setTheme } = useTheme();
  const nextTheme = theme === 'dark' ? 'light' : 'dark';
  const ThemeIcon = theme === 'dark' ? Sun : Moon;
  const [appScreen, setAppScreen] = useState<AppScreen>(() => (hasStoredApiToken() ? 'app' : 'landing'));
  const [authState, setAuthState] = useState<'checking' | 'ready'>(() => (hasStoredApiToken() ? 'checking' : 'ready'));
  const [authUser, setAuthUser] = useState<AuthUser | null>(() => getStoredAuthUser());
  const [loginEmail, setLoginEmail] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [loginState, setLoginState] = useState<'idle' | 'loading' | 'error'>('idle');
  const [loginError, setLoginError] = useState('');
  const [activePage, setActivePage] = useState<PageId>('dashboard');
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [tenantState, setTenantState] = useState<'idle' | 'loading' | 'loaded' | 'error'>('idle');
  const [tenantError, setTenantError] = useState('');
  const [tenantForm, setTenantForm] = useState<{ name: string; code: string; status: TenantStatus }>({
    name: '',
    code: '',
    status: 'draft',
  });
  const [tenantFormState, setTenantFormState] = useState<'idle' | 'saving' | 'error'>('idle');
  const [tenantFormError, setTenantFormError] = useState('');
  const [connections, setConnections] = useState<NeoFeederConnection[]>([]);
  const [connectionState, setConnectionState] = useState<'idle' | 'loading' | 'loaded' | 'error'>('idle');
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
  const [connectionFormState, setConnectionFormState] = useState<'idle' | 'saving' | 'error'>('idle');
  const [connectionFormError, setConnectionFormError] = useState('');
  const [referenceStatus, setReferenceStatus] = useState<ReferenceStatus | null>(null);
  const [referenceStatusState, setReferenceStatusState] = useState<'idle' | 'loading' | 'loaded' | 'error'>('idle');
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
      setConnectionError(error instanceof Error ? error.message : 'Daftar koneksi Neo Feeder belum bisa dimuat.');
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
    let mounted = true;

    if (appScreen !== 'app') {
      return undefined;
    }

    setReferenceStatusState('loading');
    getReferenceStatus()
      .then((status) => {
        if (!mounted) {
          return;
        }

        setReferenceStatus(status);
        setReferenceStatusState(status === null ? 'idle' : 'loaded');
      })
      .catch(() => {
        if (mounted) {
          setReferenceStatusState('error');
        }
      });

    return () => {
      mounted = false;
    };
  }, [appScreen]);

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
    if (connectionForm.tenantId !== '' || tenants.length === 0) {
      return;
    }

    setConnectionForm((current) => ({
      ...current,
      tenantId: tenants[0].id,
    }));
  }, [connectionForm.tenantId, tenants]);

  const referencePreview = useMemo(
    () => referenceStatus?.endpoints.slice(0, 5) ?? [],
    [referenceStatus],
  );
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
      const existingConnection = connections.find((item) => item.tenant_id === connectionForm.tenantId);
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
    } catch (error) {
      setConnectionFormState('error');
      setConnectionFormError(error instanceof Error ? error.message : 'Koneksi Neo Feeder belum bisa disimpan.');
    }
  };

  const renderAuthChecking = () => (
    <main className="auth-loading-shell">
      <div className="auth-loading-card">
        <Brand icon={Database} title="Bridge Neo Feeder" subtitle="PDDIKTI sync" />
        <div className="loading-state">
          <RefreshCcw size={18} />
          Memeriksa sesi login
        </div>
      </div>
    </main>
  );

  const renderProductPreview = (variant: 'compact' | 'hero' = 'compact') => (
    <div className={`product-preview ${variant === 'hero' ? 'product-preview-hero' : ''}`} aria-hidden="true">
      <div className="preview-topbar">
        <span />
        <span />
        <span />
      </div>
      <div className="preview-grid">
        <div className="preview-card preview-card-strong">
          <small>Koneksi</small>
          <strong>Draft</strong>
          <span>Neo Feeder WS</span>
        </div>
        <div className="preview-card">
          <small>Batch</small>
          <strong>0</strong>
          <span>Siap import</span>
        </div>
        <div className="preview-card">
          <small>Validasi</small>
          <strong>0</strong>
          <span>Error aktif</span>
        </div>
      </div>
      <div className="preview-table">
        {['mahasiswa_biodata', 'riwayat_pendidikan', 'kelas_kuliah', 'nilai_perkuliahan'].map((item) => (
          <div key={item}>
            <span>{item}</span>
            <CheckCircle2 size={16} />
          </div>
        ))}
      </div>
    </div>
  );

  const renderLandingShowcase = () => (
    <div className="landing-showcase" aria-hidden="true">
      <div className="showcase-sidebar">
        <span className="brand-icon">
          <Database size={22} />
        </span>
        <div />
        <div />
        <div />
      </div>

      <div className="showcase-main">
        <div className="showcase-toolbar">
          <span>Operasional Neo Feeder</span>
          <strong>Trial VPS</strong>
        </div>

        <div className="showcase-metrics">
          {metrics.map((item) => (
            <div key={item.label}>
              <small>{item.label}</small>
              <strong>{item.value}</strong>
              <span>{item.helper}</span>
            </div>
          ))}
        </div>

        <div className="showcase-flow">
          {[
            ['01', 'Template Excel'],
            ['02', 'Validasi Data'],
            ['03', 'Dry-run Payload'],
            ['04', 'Sync Bertahap'],
          ].map(([step, label]) => (
            <div key={step}>
              <strong>{step}</strong>
              <span>{label}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );

  const renderPublicNav = () => (
    <header className="public-nav">
      <Brand icon={Database} title="Bridge Neo Feeder" subtitle="PDDIKTI sync" />
      <div className="public-actions">
        <button aria-label="Ganti tema" className="icon-button" onClick={() => setTheme(nextTheme)} type="button">
          <ThemeIcon size={18} />
        </button>
        <button className="public-login-button" onClick={() => setAppScreen('login')} type="button">
          <LogIn size={17} />
          Masuk
        </button>
      </div>
    </header>
  );

  const renderLanding = () => (
    <main className="public-shell">
      {renderPublicNav()}

      <section className="landing-hero">
        {renderLandingShowcase()}

        <div className="landing-copy">
          <span className="status-pill">Trial VPS aktif</span>
          <h1>Bridge Neo Feeder</h1>
          <p>
            Kanal kerja untuk menyiapkan template Excel, validasi data kampus, dan sinkronisasi bertahap ke Neo Feeder.
          </p>
          <div className="landing-actions">
            <button className="hero-button" onClick={() => setAppScreen('login')} type="button">
              Masuk Dashboard
              <ArrowRight size={18} />
            </button>
            <button className="hero-button secondary" onClick={() => setAppScreen('login')} type="button">
              Login Admin
            </button>
          </div>
        </div>
      </section>

      <section className="landing-strip" aria-label="Alur aplikasi">
        {[
          ['Template', 'Workbook sesuai kanal Neo Feeder'],
          ['Validasi', 'Cek field, referensi, dan dependency'],
          ['Sync', 'Post bertahap dengan audit response'],
        ].map(([title, text]) => (
          <div key={title}>
            <strong>{title}</strong>
            <span>{text}</span>
          </div>
        ))}
      </section>

      <section className="landing-section" aria-labelledby="phase-one-title">
        <div className="landing-section-heading">
          <span className="section-kicker">Phase 1</span>
          <h2 id="phase-one-title">Import Excel yang siap diaudit.</h2>
          <p>Operator kampus bekerja dari template yang sama, sementara sistem menjaga validasi, dependency, dan response Neo Feeder tetap tercatat.</p>
        </div>

        <div className="feature-grid">
          {[
            ['Template Builder', 'Sheet mengikuti kanal Neo Feeder, kolom wajib, dropdown referensi, dan versi template.'],
            ['Upload & Staging', 'File Excel masuk ke batch, diparse worker, lalu disimpan sebagai raw row dan normalized row.'],
            ['Validation Gate', 'Cek format tanggal, numeric, enum, referensi, duplikasi, dan relasi antar sheet.'],
            ['Dry-run Preview', 'Operator melihat payload, dependency order, dan calon insert/update sebelum approve sync.'],
            ['Sync Audit', 'Request, response, error_code, error_desc, retry, dan ID hasil Neo Feeder tersimpan per attempt.'],
            ['Tenant Ready', 'Credential Neo Feeder tersimpan terenkripsi dan dipisah per kampus.'],
          ].map(([title, text], index) => (
            <article className="feature-card" key={title}>
              <span>{String(index + 1).padStart(2, '0')}</span>
              <h3>{title}</h3>
              <p>{text}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="process-band" aria-labelledby="process-title">
        <div className="landing-section-heading compact">
          <span className="section-kicker">Workflow</span>
          <h2 id="process-title">Dari file kampus sampai post bertahap.</h2>
        </div>

        <div className="process-rail">
          {[
            ['01', 'Admin buat tenant'],
            ['02', 'Operator download template'],
            ['03', 'Upload data terisi'],
            ['04', 'Validasi dan dry-run'],
            ['05', 'Approve sync'],
            ['06', 'Audit hasil'],
          ].map(([step, label]) => (
            <div className="process-step" key={step}>
              <strong>{step}</strong>
              <span>{label}</span>
            </div>
          ))}
        </div>
      </section>

      <section className="automation-section" aria-labelledby="automation-title">
        <div className="automation-copy">
          <span className="section-kicker">Phase 2</span>
          <h2 id="automation-title">Siap naik ke otomatisasi SIAKAD.</h2>
          <p>
            Setelah format Neo Feeder stabil, jalur otomatisasi bisa membaca struktur SIAKAD, membuat mapping profile, lalu memakai validator Phase 1 sebelum sync.
          </p>
          <div className="connector-chips" aria-label="Sumber data rencana otomatisasi">
            {['MySQL/MariaDB', 'CSV/Excel', 'Source API', 'Mapping Profile'].map((item) => (
              <span key={item}>{item}</span>
            ))}
          </div>
        </div>

        <div className="automation-panel">
          {[
            ['Discovery', 'Baca table, field, sample value, tipe data, dan kandidat relasi.'],
            ['Mapping', 'Petakan field SIAKAD ke kontrak Neo Feeder dengan transform rule.'],
            ['Run Control', 'Manual run, scheduled run, retry, lock, dan reconciliation report.'],
          ].map(([title, text]) => (
            <div key={title}>
              <CheckCircle2 size={18} />
              <strong>{title}</strong>
              <span>{text}</span>
            </div>
          ))}
        </div>
      </section>

      <section className="landing-cta">
        <div>
          <span className="section-kicker">Trial Kampus</span>
          <h2>Mulai dari tenant pertama dan koneksi Neo Feeder trial.</h2>
        </div>
        <button className="hero-button" onClick={() => setAppScreen('login')} type="button">
          Masuk Dashboard
          <ArrowRight size={18} />
        </button>
      </section>
    </main>
  );

  const renderLogin = () => (
    <main className="auth-shell">
      <section className="auth-visual">
        <Brand icon={Database} title="Bridge Neo Feeder" subtitle="PDDIKTI sync" />
        {renderProductPreview()}
      </section>

      <section className="auth-panel">
        <div className="auth-card">
          <div className="auth-heading">
            <span className="brand-icon">
              <LockKeyhole size={22} />
            </span>
            <div>
              <p className="eyebrow">Admin Area</p>
              <h1>Masuk Dashboard</h1>
            </div>
          </div>

          <form className="auth-form" onSubmit={handleLogin}>
            <label>
              Email
              <input
                autoComplete="email"
                onChange={(event) => setLoginEmail(event.target.value)}
                placeholder="admin@example.com"
                required
                type="email"
                value={loginEmail}
              />
            </label>

            <label>
              Password
              <input
                autoComplete="current-password"
                onChange={(event) => setLoginPassword(event.target.value)}
                placeholder="Password admin"
                required
                type="password"
                value={loginPassword}
              />
            </label>

            {loginState === 'error' ? <p className="auth-error">{loginError}</p> : null}

            <button className="hero-button" disabled={loginState === 'loading'} type="submit">
              {loginState === 'loading' ? 'Memproses...' : 'Masuk'}
              <ArrowRight size={18} />
            </button>
          </form>

          <button className="back-button" onClick={() => setAppScreen('landing')} type="button">
            Kembali ke landing page
          </button>
        </div>
      </section>
    </main>
  );

  const renderReferencePanel = () => (
    <WorkspacePanel>
      <SectionHeader
        action={
          <AppButton icon={RefreshCcw} variant="secondary">
            Sync Referensi
          </AppButton>
        }
        description="Status cache lookup Neo Feeder untuk template dan validasi."
        title="Referensi Neo Feeder"
      />

      <div className="reference-summary">
        <div>
          <span>Total Rows</span>
          <strong>{referenceStatus?.total_rows ?? 0}</strong>
        </div>
        <div>
          <span>Endpoint Sync</span>
          <strong>
            {referenceStatus?.synced_endpoint_count ?? 0}/{referenceStatus?.endpoint_count ?? 0}
          </strong>
        </div>
        <div>
          <span>Belum Sync</span>
          <strong>{referenceStatus?.failed_endpoint_count ?? 0}</strong>
        </div>
        <div>
          <span>Last Refresh</span>
          <strong>{formatDateTime(referenceStatus?.last_synced_at ?? null)}</strong>
        </div>
      </div>

      <div className="reference-list">
        {referencePreview.length > 0 ? (
          referencePreview.map((item) => (
            <div className="reference-row" key={item.endpoint}>
              <div>
                <strong>{item.endpoint}</strong>
                <span>{item.total_rows} rows</span>
              </div>
              <StatusBadge tone={item.status === 'synced' ? 'success' : 'warning'}>
                {item.status === 'synced' ? 'Synced' : 'Belum sync'}
              </StatusBadge>
            </div>
          ))
        ) : (
          <EmptyState
            description={
              referenceStatusState === 'error'
                ? 'Status referensi belum bisa dimuat dari API.'
                : 'Tambahkan API token untuk melihat status referensi.'
            }
            icon={Clock3}
            title="Status referensi belum tersedia"
          />
        )}
      </div>
    </WorkspacePanel>
  );

  const renderNeoFeederConnection = () => (
    <aside className="side-panel">
      <SectionHeader title="Neo Feeder" />
      <dl className="connection-list">
        <div>
          <dt>Status</dt>
          <dd>
            <StatusBadge tone="warning">Draft</StatusBadge>
          </dd>
        </div>
        <div>
          <dt>Endpoint</dt>
          <dd className="mono">Belum diset</dd>
        </div>
        <div>
          <dt>Referensi</dt>
          <dd>Belum sync</dd>
        </div>
      </dl>
      <AppButton icon={DatabaseZap} variant="secondary">
        Test Koneksi
      </AppButton>
    </aside>
  );

  const renderDashboard = () => (
    <>
      <PageHeader
        action={<AppButton icon={Upload}>Upload Excel</AppButton>}
        eyebrow="Local Dev"
        title="Siapkan data kampus untuk sinkronisasi."
      />

      <section className="status-grid" aria-label="Status ringkas">
        {metrics.map((item) => (
          <MetricCard
            helper={item.helper}
            icon={item.icon}
            key={item.label}
            label={item.label}
            tone={item.tone}
            value={item.value}
          />
        ))}
      </section>

      <section className="dashboard-grid">
        <WorkspacePanel>
          <SectionHeader
            action={
              <AppButton icon={RefreshCcw} onClick={loadTenants} variant="secondary">
                Refresh
              </AppButton>
            }
            title="Batch Import"
          />

          <DataTable
            columns={batchColumns}
            emptyState={<EmptyState description="Upload template Excel untuk mulai validasi." icon={FileSpreadsheet} title="Belum ada batch" />}
          />
        </WorkspacePanel>

        {renderNeoFeederConnection()}
      </section>

      {renderReferencePanel()}
    </>
  );

  const renderCampus = () => (
    <>
      <PageHeader
        action={<AppButton icon={Building2}>Tambah Kampus</AppButton>}
        eyebrow="Master Data"
        title="Kelola tenant kampus dan koneksi sumber data."
      />

      <section className="page-grid">
        <WorkspacePanel>
          <SectionHeader
            action={
              <AppButton icon={RefreshCcw} variant="secondary">
                Refresh
              </AppButton>
            }
            title="Daftar Kampus"
          />
          <DataTable
            columns={campusColumns}
            rows={tenants.map((tenant) => [
              <strong className="table-primary" key={`${tenant.id}-name`}>
                {tenant.name}
              </strong>,
              <span className="mono" key={`${tenant.id}-code`}>
                {tenant.code}
              </span>,
              <StatusBadge key={`${tenant.id}-status`} tone={tenantStatusTones[tenant.status]}>
                {tenantStatusLabels[tenant.status]}
              </StatusBadge>,
              <span key={`${tenant.id}-batch`}>0 batch</span>,
              <span key={`${tenant.id}-updated`}>{formatDateTime(tenant.updated_at)}</span>,
            ])}
            emptyState={
              tenantState === 'loading' ? (
                <div className="loading-state">
                  <RefreshCcw size={18} />
                  Memuat kampus
                </div>
              ) : tenantState === 'error' ? (
                <div className="error-state">
                  <strong>Daftar kampus gagal dimuat.</strong>
                  <span>{tenantError}</span>
                </div>
              ) : (
                <EmptyState description="Kampus pertama akan dipakai untuk uji template Excel." icon={Building2} title="Belum ada kampus" />
              )
            }
          />
        </WorkspacePanel>

        <aside className="side-panel">
          <SectionHeader title="Tambah Kampus" />
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
                onChange={(event) => handleTenantFormChange('status', event.target.value as TenantStatus)}
                value={tenantForm.status}
              >
                <option value="draft">Draft</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
              </select>
            </label>

            {tenantFormState === 'error' ? <p className="auth-error">{tenantFormError}</p> : null}

            <button className="app-button app-button-primary" disabled={tenantFormState === 'saving'} type="submit">
              <Plus size={18} />
              {tenantFormState === 'saving' ? 'Menyimpan...' : 'Tambah Kampus'}
            </button>
          </form>

          <div className="task-list">
            <span>Profil kampus</span>
            <span>Credential Neo Feeder</span>
            <span>Format template Excel</span>
            <span>Rule validasi awal</span>
          </div>
        </aside>
      </section>
    </>
  );

  const renderNeoFeeder = () => (
    <>
      <PageHeader
        action={
          <AppButton disabled icon={DatabaseZap} variant="secondary">
            Test butuh credential
          </AppButton>
        }
        eyebrow="Integrasi"
        title="Simpan credential WS Neo Feeder per kampus."
      />

      <section className="page-grid">
        <WorkspacePanel>
          <SectionHeader
            action={
              <AppButton icon={RefreshCcw} onClick={loadConnections} variant="secondary">
                Refresh
              </AppButton>
            }
            title="Koneksi Neo Feeder"
          />
          <DataTable
            columns={['Kampus', 'Endpoint', 'Status', 'Password', 'Last Check', 'Aksi']}
            rows={connections.map((connection) => [
              <strong className="table-primary" key={`${connection.id}-tenant`}>
                {tenantNameById.get(connection.tenant_id) ?? connection.tenant_id}
              </strong>,
              <span className="mono table-url" key={`${connection.id}-endpoint`}>
                {connection.base_url}
              </span>,
              <StatusBadge key={`${connection.id}-status`} tone={connectionStatusTones[connection.status]}>
                {connectionStatusLabels[connection.status]}
              </StatusBadge>,
              <span key={`${connection.id}-password`}>{connection.password_configured ? 'Tersimpan' : 'Belum'}</span>,
              <span key={`${connection.id}-checked`}>{formatDateTime(connection.last_checked_at)}</span>,
              <button className="row-action" key={`${connection.id}-action`} onClick={() => applyConnectionToForm(connection.tenant_id)} type="button">
                Edit
              </button>,
            ])}
            emptyState={
              connectionState === 'loading' ? (
                <div className="loading-state">
                  <RefreshCcw size={18} />
                  Memuat koneksi
                </div>
              ) : connectionState === 'error' ? (
                <div className="error-state">
                  <strong>Daftar koneksi gagal dimuat.</strong>
                  <span>{connectionError}</span>
                </div>
              ) : (
                <EmptyState description="Tambahkan credential setelah tenant kampus tersedia." icon={DatabaseZap} title="Belum ada koneksi" />
              )
            }
          />
        </WorkspacePanel>

        <aside className="side-panel">
          <SectionHeader title="Credential WS" />
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
                onChange={(event) => handleConnectionFormChange('status', event.target.value as NeoFeederConnectionStatus)}
                value={connectionForm.status}
              >
                <option value="draft">Draft</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="error">Error</option>
              </select>
            </label>

            {connectionFormState === 'error' ? <p className="auth-error">{connectionFormError}</p> : null}

            <button className="app-button app-button-primary" disabled={connectionFormState === 'saving' || tenants.length === 0} type="submit">
              <DatabaseZap size={18} />
              {connectionFormState === 'saving' ? 'Menyimpan...' : 'Simpan Credential'}
            </button>
          </form>

          <div className="notice compact-notice">
            <Clock3 size={18} />
            <p>Test koneksi dan sync referensi menunggu credential Neo Feeder resmi.</p>
          </div>
        </aside>
      </section>
    </>
  );

  const renderTemplateExcel = () => (
    <>
      <PageHeader
        action={<AppButton icon={Download}>Download Template</AppButton>}
        eyebrow="Phase 1"
        title="Template mengikuti kontrak field Neo Feeder."
      />

      <WorkspacePanel>
        <SectionHeader title="Workbook Template" />
        <DataTable
          columns={templateColumns}
          emptyState={<EmptyState description="Generator template akan membaca kanal data yang aktif." icon={FileSpreadsheet} title="Template belum digenerate" />}
        />
      </WorkspacePanel>
    </>
  );

  const renderImportBatch = () => (
    <>
      <PageHeader
        action={<AppButton icon={Upload}>Upload Excel</AppButton>}
        eyebrow="Import"
        title="Pantau upload, validasi, dan kesiapan sync."
      />

      <WorkspacePanel>
        <SectionHeader
          action={
            <AppButton icon={RefreshCcw} variant="secondary">
              Refresh
            </AppButton>
          }
          title="Batch Import"
        />
        <DataTable
          columns={batchColumns}
          emptyState={<EmptyState description="Belum ada file yang diupload." icon={Upload} title="Batch kosong" />}
        />
      </WorkspacePanel>
    </>
  );

  const renderValidation = () => (
    <>
      <PageHeader
        action={
          <AppButton icon={ShieldCheck} variant="secondary">
            Jalankan Validasi
          </AppButton>
        }
        eyebrow="Quality Gate"
        title="Cek field wajib, referensi, dan relasi data."
      />

      <WorkspacePanel>
        <SectionHeader title="Temuan Validasi" />
        <DataTable
          columns={validationColumns}
          emptyState={<EmptyState description="Upload batch untuk melihat hasil validasi." icon={ShieldCheck} title="Belum ada temuan" />}
        />
      </WorkspacePanel>
    </>
  );

  const renderMapping = () => (
    <>
      <PageHeader
        action={
          <AppButton icon={Waypoints} variant="secondary">
            Buat Draft Mapping
          </AppButton>
        }
        eyebrow="Phase 2"
        title="Siapkan mapping otomatis dari struktur SIAKAD."
      />

      <WorkspacePanel>
        <SectionHeader title="Draft Mapping" />
        <DataTable
          columns={mappingColumns}
          emptyState={<EmptyState description="Mapping dibuat setelah koneksi database SIAKAD dipelajari." icon={Waypoints} title="Belum ada mapping" />}
        />
      </WorkspacePanel>
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
      case 'dashboard':
      default:
        return renderDashboard();
    }
  };

  if (authState === 'checking') {
    return renderAuthChecking();
  }

  if (appScreen === 'landing') {
    return renderLanding();
  }

  if (appScreen === 'login') {
    return renderLogin();
  }

  return (
    <AppShell
      sidebar={
        <Sidebar>
          <Brand icon={Database} title="Bridge Neo Feeder" subtitle="PDDIKTI sync" />
          <SidebarNav activeItem={activePage} items={navItems} onItemSelect={(item) => setActivePage(item.id as PageId)} />
        </Sidebar>
      }
    >
      <Topbar
        action={
          <div className="topbar-actions">
            <button className="search-trigger" type="button">
              <Search size={17} />
              <span>Cari batch</span>
              <kbd>Ctrl K</kbd>
            </button>
            <button aria-label="Ganti tema" className="icon-button" onClick={() => setTheme(nextTheme)} type="button">
              <ThemeIcon size={18} />
            </button>
            <button aria-label="Notifikasi" className="icon-button" type="button">
              <Bell size={18} />
            </button>
            <button aria-label={authUser ? `User ${authUser.name}` : 'Menu pengguna'} className="avatar-button" type="button">
              <CircleUserRound size={20} />
            </button>
            <button aria-label="Keluar" className="icon-button" onClick={handleLogout} type="button">
              <LogOut size={18} />
            </button>
          </div>
        }
        eyebrow={currentPage.eyebrow}
        title={currentPage.title}
      />

      {renderPage()}
    </AppShell>
  );
}

export default App;
