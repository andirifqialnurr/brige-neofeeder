import {
  Activity,
  Bell,
  Building2,
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
import { useTheme } from '@/hooks/use-theme';
import {
  AppButton,
  AppShell,
  Brand,
  MetricCard,
  PageHeader,
  SectionHeader,
  Sidebar,
  SidebarNav,
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

function App() {
  const { theme, setTheme } = useTheme();
  const nextTheme = theme === 'dark' ? 'light' : 'dark';
  const ThemeIcon = theme === 'dark' ? Sun : Moon;

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

          <div className="table-shell">
            <table>
              <thead>
                <tr>
                  {batchColumns.map((column) => (
                    <th key={column}>{column}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colSpan={batchColumns.length}>
                    <div className="empty-state">
                      <FileSpreadsheet size={22} />
                      <strong>Belum ada batch</strong>
                      <span>Upload template Excel untuk mulai validasi.</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </WorkspacePanel>

        <aside className="side-panel">
          <SectionHeader title="Neo Feeder" />
          <dl className="connection-list">
            <div>
              <dt>Status</dt>
              <dd>
                <span className="badge warning">Draft</span>
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
    </AppShell>
  );
}

export default App;
