import {
  Activity,
  Bell,
  Building2,
  Clock3,
  CircleUserRound,
  Database,
  DatabaseZap,
  FileSpreadsheet,
  LayoutDashboard,
  Moon,
  RefreshCcw,
  Search,
  ShieldCheck,
  Sun,
  Upload,
  Waypoints,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTheme } from '@/hooks/use-theme';
import { getReferenceStatus, type ReferenceStatus } from '@/lib/api';
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
  StatusBadge,
  Topbar,
  WorkspacePanel,
} from './components/ui';

const navItems = [
  { label: 'Dashboard', icon: LayoutDashboard },
  { label: 'Kampus', icon: Building2 },
  { label: 'Neo Feeder', icon: DatabaseZap },
  { label: 'Template Excel', icon: FileSpreadsheet },
  { label: 'Import Batch', icon: Upload },
  { label: 'Validasi', icon: ShieldCheck },
  { label: 'Mapping', icon: Waypoints },
];

const metrics = [
  { label: 'Koneksi', value: 'Draft', helper: 'Belum test', tone: 'warning', icon: DatabaseZap },
  { label: 'Batch Aktif', value: '0', helper: 'Menunggu upload', tone: 'muted', icon: FileSpreadsheet },
  { label: 'Error Validasi', value: '0', helper: 'Belum ada data', tone: 'success', icon: ShieldCheck },
  { label: 'Queue Sync', value: '0', helper: 'Idle', tone: 'info', icon: Activity },
] as const;

const batchColumns = ['Batch', 'Kampus', 'Status', 'Valid', 'Error', 'Update'];

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
  const [referenceStatus, setReferenceStatus] = useState<ReferenceStatus | null>(null);
  const [referenceStatusState, setReferenceStatusState] = useState<'idle' | 'loading' | 'loaded' | 'error'>('idle');

  useEffect(() => {
    let mounted = true;

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
  }, []);

  const referencePreview = useMemo(
    () => referenceStatus?.endpoints.slice(0, 5) ?? [],
    [referenceStatus],
  );

  return (
    <AppShell
      sidebar={
        <Sidebar>
          <Brand icon={Database} title="Bridge Neo Feeder" subtitle="PDDIKTI sync" />
          <SidebarNav activeItem="Dashboard" items={navItems} />
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
            <button aria-label="Menu pengguna" className="avatar-button" type="button">
              <CircleUserRound size={20} />
            </button>
          </div>
        }
        eyebrow="Dashboard"
        title="Operasional Neo Feeder"
      />

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
              <AppButton icon={RefreshCcw} variant="secondary">
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
      </section>

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
    </AppShell>
  );
}

export default App;
