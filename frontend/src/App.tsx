import { AlertTriangle, CheckCircle2, Database, FileSpreadsheet, RefreshCcw, Upload } from 'lucide-react';
import {
  AppButton,
  AppShell,
  Brand,
  MetricCard,
  Notice,
  PhasePanel,
  SectionHeader,
  Sidebar,
  SidebarNav,
  Topbar,
  WorkspacePanel,
} from './components/ui';

const phases = [
  {
    label: 'Phase 1',
    title: 'Export/Import Excel',
    items: ['Template Excel Neo Feeder', 'Upload ke staging', 'Validasi dan dry-run', 'Queue sync ke Web Service'],
  },
  {
    label: 'Phase 2',
    title: 'Otomatisasi SIAKAD',
    items: ['Read-only connector', 'Source discovery', 'Mapping profile', 'Scheduled sync'],
  },
];

const stats = [
  { label: 'Koneksi Neo Feeder', value: 'Belum dikonfigurasi', tone: 'warning' },
  { label: 'Referensi', value: 'Menunggu sync', tone: 'muted' },
  { label: 'Batch Import', value: '0 aktif', tone: 'success' },
] as const;

function App() {
  return (
    <AppShell
      sidebar={
        <Sidebar>
          <Brand icon={Database} title="Bridge Neo Feeder" subtitle="Data integration" />
          <SidebarNav
            activeItem="Dashboard"
            items={['Dashboard', 'Kampus', 'Template Excel', 'Import Batch', 'Sinkronisasi', 'Mapping']}
          />
        </Sidebar>
      }
    >
      <Topbar eyebrow="MVP Setup" title="Dashboard Operasional" action={<AppButton icon={Upload}>Upload Excel</AppButton>} />

      <section className="status-grid" aria-label="Status ringkas">
        {stats.map((item) => (
          <MetricCard key={item.label} label={item.label} tone={item.tone} value={item.value} />
        ))}
      </section>

      <WorkspacePanel>
        <SectionHeader
          action={
            <AppButton icon={RefreshCcw} variant="secondary">
              Refresh
            </AppButton>
          }
          description="Pipeline Excel menjadi fondasi sebelum otomatisasi SIAKAD."
          title="Rencana Implementasi"
        />

        <div className="phase-grid">
          {phases.map((phase) => (
            <PhasePanel icon={FileSpreadsheet} key={phase.label} label={phase.label} title={phase.title}>
              <ul>
                {phase.items.map((item) => (
                  <li key={item}>
                    <CheckCircle2 size={16} />
                    {item}
                  </li>
                ))}
              </ul>
            </PhasePanel>
          ))}
        </div>

        <Notice icon={AlertTriangle}>Neo Feeder diakses melalui Web Service. Database internal Neo Feeder tidak ditulis langsung.</Notice>
      </WorkspacePanel>
    </AppShell>
  );
}

export default App;
