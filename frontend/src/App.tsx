import { AlertTriangle, CheckCircle2, Database, FileSpreadsheet, RefreshCcw, Upload } from 'lucide-react';

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
];

function App() {
  return (
    <main className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <Database size={24} />
          <div>
            <strong>Bridge Neo Feeder</strong>
            <span>Data integration</span>
          </div>
        </div>

        <nav className="nav-list" aria-label="Navigasi utama">
          <a className="active" href="#dashboard">Dashboard</a>
          <a href="#tenant">Kampus</a>
          <a href="#template">Template Excel</a>
          <a href="#import">Import Batch</a>
          <a href="#sync">Sinkronisasi</a>
          <a href="#mapping">Mapping</a>
        </nav>
      </aside>

      <section className="content">
        <header className="topbar">
          <div>
            <p className="eyebrow">MVP Setup</p>
            <h1>Dashboard Operasional</h1>
          </div>
          <button className="primary-button" type="button">
            <Upload size={18} />
            Upload Excel
          </button>
        </header>

        <section className="status-grid" aria-label="Status ringkas">
          {stats.map((item) => (
            <article className="status-card" key={item.label}>
              <span>{item.label}</span>
              <strong className={item.tone}>{item.value}</strong>
            </article>
          ))}
        </section>

        <section className="workspace">
          <div className="section-heading">
            <div>
              <h2>Rencana Implementasi</h2>
              <p>Pipeline Excel menjadi fondasi sebelum otomatisasi SIAKAD.</p>
            </div>
            <button className="secondary-button" type="button">
              <RefreshCcw size={16} />
              Refresh
            </button>
          </div>

          <div className="phase-grid">
            {phases.map((phase) => (
              <article className="phase-panel" key={phase.label}>
                <div className="phase-title">
                  <FileSpreadsheet size={20} />
                  <div>
                    <span>{phase.label}</span>
                    <h3>{phase.title}</h3>
                  </div>
                </div>
                <ul>
                  {phase.items.map((item) => (
                    <li key={item}>
                      <CheckCircle2 size={16} />
                      {item}
                    </li>
                  ))}
                </ul>
              </article>
            ))}
          </div>

          <div className="notice">
            <AlertTriangle size={18} />
            <p>Neo Feeder diakses melalui Web Service. Database internal Neo Feeder tidak ditulis langsung.</p>
          </div>
        </section>
      </section>
    </main>
  );
}

export default App;
