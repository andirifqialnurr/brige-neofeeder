import {
  ArrowLeft,
  ArrowRight,
  Building2,
  Database,
  Eye,
  EyeOff,
  FileSpreadsheet,
  ShieldCheck,
  Waypoints,
} from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { AppButton, Brand, IconButton } from './ui';
import { useTheme } from '@/hooks/use-theme';

export function LandingPage({
  onLogin,
  themeControl,
}: {
  onLogin: () => void;
  themeControl: ReactNode;
}) {
  const { resolvedTheme } = useTheme();
  return (
    <main className="public-shell">
      <header className="public-nav">
        <Brand icon={Database} title="NeoBridge" subtitle="Bridge Neo Feeder" />
        <nav aria-label="Navigasi publik">
          <a href="#workflow">Alur kerja</a>
          <a href="#automation">Otomatisasi</a>
          <a href="#questions">FAQ</a>
        </nav>
        <div className="public-actions">
          {themeControl}
          <AppButton variant="secondary" onClick={onLogin}>
            Masuk
          </AppButton>
        </div>
      </header>

      <section className="landing-intro" aria-labelledby="product-title">
        <span className="section-kicker">SIAKAD / PDDIKTI</span>
        <h1 id="product-title">Bridge Neo Feeder</h1>
        <p>
          Siapkan data kampus, periksa isinya, dan kelola pengiriman ke Neo Feeder dalam satu
          workspace.
        </p>
        <AppButton icon={ArrowRight} onClick={onLogin}>
          Buka workspace
        </AppButton>
      </section>
      <figure className="product-media">
        <img
          src={
            resolvedTheme === 'dark'
              ? '/images/workspace-preview-dark.png'
              : '/images/workspace-preview.png'
          }
          alt="Tampilan Import Batch NeoBridge dengan pencarian file dan daftar status validasi"
          width="1440"
          height="900"
          fetchPriority="high"
        />
        <figcaption>Workspace NeoBridge. Data pada gambar adalah contoh.</figcaption>
      </figure>

      <section className="public-section" id="workflow">
        <div className="public-section-heading">
          <span className="section-kicker">Alur kerja</span>
          <h2>Dari Excel ke data yang siap dikirim.</h2>
        </div>
        <ol className="workflow-list">
          {[
            ['Isi template', 'Gunakan workbook dengan kolom sesuai kebutuhan Neo Feeder.'],
            ['Periksa data', 'Upload file dan telusuri kesalahan sebelum melanjutkan.'],
            ['Tinjau hasil', 'Jalankan dry-run sebelum pengiriman ke Neo Feeder.'],
          ].map(([title, description], index) => (
            <li key={title}>
              <span className="step-number">0{index + 1}</span>
              <h3>{title}</h3>
              <p>{description}</p>
            </li>
          ))}
        </ol>
      </section>

      <section className="public-band">
        <div className="public-section">
          <div className="public-section-heading">
            <span className="section-kicker">Pengelolaan kampus</span>
            <h2>Terpisah per kampus. Teratur dalam satu tempat.</h2>
          </div>
          <div className="benefit-list">
            {[
              {
                icon: Building2,
                title: 'Ruang kerja per kampus',
                text: 'Koneksi dan batch terhubung ke kampus masing-masing.',
              },
              {
                icon: ShieldCheck,
                title: 'Kredensial terlindungi',
                text: 'Password koneksi tersimpan terenkripsi.',
              },
              {
                icon: FileSpreadsheet,
                title: 'Riwayat import',
                text: 'Telusuri kembali file dan hasil pemeriksaan setiap batch.',
              },
            ].map(({ icon: Icon, title, text }) => (
              <article key={title}>
                <Icon size={22} aria-hidden="true" />
                <h3>{title}</h3>
                <p>{text}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="public-section roadmap-section" id="automation">
        <div className="public-section-heading">
          <span className="section-kicker">Berikutnya / Fase 2</span>
          <h2>Terhubung langsung ke SIAKAD.</h2>
          <p>Otomatisasi dikembangkan setelah struktur data kampus dipelajari.</p>
        </div>
        <div className="roadmap-list">
          <Waypoints size={24} aria-hidden="true" />
          <dl>
            <div>
              <dt>Sumber data</dt>
              <dd>MySQL, MariaDB, file, atau API kampus</dd>
            </div>
            <div>
              <dt>Mapping</dt>
              <dd>Penyesuaian kolom dan format data</dd>
            </div>
            <div>
              <dt>Penjadwalan</dt>
              <dd>Pengambilan data berkala</dd>
            </div>
          </dl>
        </div>
      </section>

      <section className="public-section faq-section" id="questions">
        <div className="public-section-heading">
          <span className="section-kicker">FAQ</span>
          <h2>Sebelum mulai.</h2>
        </div>
        <div className="faq-list">
          <details>
            <summary>Apakah kampus harus memiliki API SIAKAD?</summary>
            <p>Tidak untuk import Excel. Anda dapat mengisi template lalu menguploadnya.</p>
          </details>
          <details>
            <summary>Apakah upload langsung mengirim data ke Neo Feeder?</summary>
            <p>
              Tidak. Upload dan dry-run menyiapkan serta memeriksa data. Pengiriman memerlukan
              koneksi Neo Feeder dan langkah sinkronisasi terpisah.
            </p>
          </details>
          <details>
            <summary>Apa yang dibutuhkan untuk koneksi Neo Feeder?</summary>
            <p>
              Alamat web service yang dapat dijangkau server, username, dan password dari pengelola
              Neo Feeder kampus.
            </p>
          </details>
        </div>
      </section>
      <footer className="public-footer">
        <span>NeoBridge</span>
        <span>Bridge Neo Feeder</span>
        <a href="#product-title">Kembali ke atas</a>
      </footer>
    </main>
  );
}

export function LoginPage({
  themeControl,
  onBack,
  email,
  password,
  onEmailChange,
  onPasswordChange,
  onSubmit,
  loading,
  error,
}: {
  themeControl: ReactNode;
  onBack: () => void;
  email: string;
  password: string;
  onEmailChange: (value: string) => void;
  onPasswordChange: (value: string) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  loading: boolean;
  error: string;
}) {
  const [showPassword, setShowPassword] = useState(false);
  return (
    <main className="auth-shell">
      <header className="public-nav">
        <button type="button" className="back-button" onClick={onBack}>
          <ArrowLeft size={16} />
          Kembali
        </button>
        {themeControl}
      </header>
      <section className="auth-panel">
        <Brand icon={Database} title="NeoBridge" subtitle="Bridge Neo Feeder" />
        <div className="auth-heading">
          <h1>Masuk ke workspace</h1>
        </div>
        <form className="auth-form" onSubmit={onSubmit}>
          <label>
            Email
            <input
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(event) => onEmailChange(event.target.value)}
              placeholder="nama@kampus.ac.id"
            />
          </label>
          <label>
            Password
            <span className="password-field">
              <input
                aria-label="Password"
                type={showPassword ? 'text' : 'password'}
                autoComplete="current-password"
                required
                value={password}
                onChange={(event) => onPasswordChange(event.target.value)}
                aria-describedby={error ? 'login-error' : undefined}
              />
              <IconButton
                label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                icon={showPassword ? EyeOff : Eye}
                onClick={() => setShowPassword(!showPassword)}
              />
            </span>
          </label>
          {error ? (
            <p role="alert" id="login-error" className="auth-error">
              {error}
            </p>
          ) : null}
          <AppButton type="submit" disabled={loading} icon={ArrowRight}>
            {loading ? 'Memproses...' : 'Masuk'}
          </AppButton>
        </form>
      </section>
      <footer className="auth-footer">Bridge Neo Feeder</footer>
    </main>
  );
}
