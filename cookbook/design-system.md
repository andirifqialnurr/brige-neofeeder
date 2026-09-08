# Design System - Bridge Neo Feeder

## 1. Arah Visual

Referensi visual utama: dashboard Talent pada `https://talent.univeral.ac.id/apps/`.

Arah desain Bridge Neo Feeder:

- Lucu, modern, dan terstruktur.
- Dashboard-first, bukan landing page.
- Base warna biru, dengan aksen status hijau, kuning, merah, cyan, dan ungu secukupnya.
- Mendukung light mode dan dark mode sejak awal.
- Kartu besar, rounded, icon-rich, dan punya micro-interaction halus.
- Tetap operasional: data, validasi, batch, dan error harus lebih penting daripada dekorasi.

Karakter UI:

- Friendly seperti aplikasi kampus modern.
- Rapi seperti admin dashboard.
- Tidak kaku seperti sistem pelaporan lama.
- Tidak terlalu ramai sampai mengganggu pekerjaan import/sync data.

## 2. Prinsip Desain

- First screen langsung dashboard operasional.
- Sidebar permanen untuk desktop.
- Header atas fixed dengan search, command shortcut, theme toggle, notification, dan user menu.
- Kartu ringkasan memakai icon tile, angka besar, dan status kecil.
- Gunakan rounded besar pada panel utama, tetapi tabel dan form tetap padat.
- Gunakan efek glass/blur secara terbatas pada sidebar/header.
- Hindari dekorasi orb/bokeh terpisah. Efek visual cukup dari surface, border, shadow, dan gradient halus di panel.
- Semua proses berisiko seperti `Start Sync`, `Retry`, credential update, dan delete harus eksplisit.
- Error validasi harus terlihat per sheet, row, dan field.

## 3. Layout Shell

Desktop layout:

```text
Fixed sidebar 256px
Fixed topbar 72-80px
Main content container
Dashboard cards and work panels
Right drawer for detail
```

Sidebar:

- Width: 256px.
- Background: translucent surface dengan backdrop blur.
- Border kanan dashed/subtle.
- Brand area berisi logo app, nama app, dan badge environment.
- Menu dikelompokkan dengan label kecil.
- Active menu memakai blue tinted background dan icon biru.
- Disabled/coming soon menu memakai opacity 50%.

Topbar:

- Fixed di atas.
- Background translucent dan backdrop blur.
- Search command button di kiri.
- Action cluster di kanan: queue status, notification, theme toggle, user menu.
- Tinggi 72-80px.

Main content:

- Desktop padding: 24-32px.
- Mobile padding: 16px.
- Ada jarak atas mengikuti fixed topbar.
- Content width boleh lebar untuk tabel dan monitoring.

## 4. Navigasi

Menu utama:

- Dashboard
- Kampus
- Neo Feeder
- Referensi
- Template Excel
- Import Batch
- Validasi
- Sinkronisasi
- Mapping
- Otomatisasi
- Audit Log
- Pengaturan

Kelompok sidebar:

```text
Menu Utama
  Dashboard
  Kampus

Neo Feeder
  Koneksi
  Referensi
  Contract

Import Excel
  Template
  Import Batch
  Validasi
  Sinkronisasi

Otomatisasi
  Source SIAKAD
  Mapping Profile
  Jadwal Sync

Aplikasi
  Audit Log
  Pengaturan
```

## 5. Color System

Base brand wajib biru.

### Light Mode

```text
app-bg: #f8fafc
surface: #ffffff
surface-soft: #f1f5f9
surface-glass: rgba(255, 255, 255, 0.72)
border: #e2e8f0
border-soft: rgba(226, 232, 240, 0.72)
text-primary: #0f172a
text-secondary: #475569
text-muted: #94a3b8

primary-50: #eff6ff
primary-100: #dbeafe
primary-500: #3b82f6
primary-600: #2563eb
primary-700: #1d4ed8
primary-800: #1e40af

info-500: #06b6d4
success-500: #22c55e
warning-500: #f59e0b
danger-500: #ef4444
purple-500: #8b5cf6
```

### Dark Mode

```text
app-bg: #0b1220
surface: #111827
surface-soft: #1f2937
surface-glass: rgba(17, 24, 39, 0.72)
border: #334155
border-soft: rgba(51, 65, 85, 0.72)
text-primary: #f8fafc
text-secondary: #cbd5e1
text-muted: #64748b

primary-50: rgba(59, 130, 246, 0.12)
primary-100: rgba(59, 130, 246, 0.18)
primary-500: #60a5fa
primary-600: #3b82f6
primary-700: #2563eb
primary-800: #1d4ed8

info-500: #22d3ee
success-500: #4ade80
warning-500: #fbbf24
danger-500: #f87171
purple-500: #a78bfa
```

### Usage Rules

- Primary action selalu biru.
- Active navigation selalu biru.
- Status memakai warna semantik, bukan biru semua.
- Chart boleh memakai blue line sebagai default, lalu cyan/green/orange untuk seri pembanding.
- Gradient boleh dipakai pada highlight card, progress, dan virtual-card style panel.
- Jangan membuat seluruh dashboard hanya biru. Biru adalah base, bukan satu-satunya warna.

## 6. Typography

Font:

- Gunakan `Inter` jika tersedia.
- Fallback: system UI stack.
- Monospace untuk field Neo Feeder, UUID, endpoint, payload JSON, dan kode referensi.

Skala:

```text
display-title: 32-36px / 40px / 800-900
page-title: 24-28px / 34px / 800
section-title: 20px / 30px / 800
card-title: 16px / 24px / 700
body: 14px / 22px / 400
table: 13px / 20px / 400
caption: 12px / 18px / 500
badge: 11px / 16px / 700
code: 12px / 18px / 500
```

Rules:

- Judul dashboard boleh bold dan playful.
- Label tabel dan form harus ringkas.
- Letter spacing tetap 0 untuk body.
- Badge boleh uppercase dengan spacing kecil, maksimal `0.04em`.

## 7. Shape, Border, Shadow

Radius:

```text
button: 12px
input: 12px
small-card: 16px
metric-card: 24px
hero-dashboard-panel: 28-32px
modal/drawer: 20-24px
table-container: 16px
icon-tile: 12-16px
avatar: 999px
```

Border:

- Light: `1px solid #e2e8f0`.
- Dark: `1px solid #334155`.
- Header/sidebar boleh memakai border dashed/subtle.

Shadow:

```text
sm: 0 1px 3px rgba(15, 23, 42, 0.08)
md: 0 8px 24px rgba(15, 23, 42, 0.10)
lg: 0 16px 40px rgba(15, 23, 42, 0.14)
blue-glow: 0 10px 24px rgba(37, 99, 235, 0.22)
```

Hover:

- Card hover boleh naik `translateY(-2px)`.
- Shadow naik satu level.
- Border berubah ke primary soft.
- Icon tile boleh scale kecil `1.04`, jangan berlebihan.

## 8. Komponen Utama

### App Sidebar

Isi:

- logo,
- app name,
- environment badge seperti `BETA`, `TRIAL`, atau `PROD`,
- grouped menu,
- collapsed toggle desktop.

Menu item:

```text
height: 40px
radius: 12px
icon: 18px
gap: 12px
font: 14px / 600
```

State:

- default: text muted,
- hover: blue text dan soft background,
- active: blue text, blue soft background, icon filled/tinted,
- disabled: opacity 50%, pointer disabled.

### Topbar

Komponen:

- command search button: `Cari data, batch, kanal...`
- shortcut pill: `CTRL K`
- sync/queue indicator,
- notification icon,
- theme toggle,
- user avatar.

Search button:

```text
height: 44px
min-width desktop: 260px
radius: 14px
background: transparent/soft surface
border: transparent, visible on hover
```

### Dashboard Welcome Panel

Panel pembuka dashboard mengikuti pola referensi Talent:

- rounded 28-32px,
- surface card,
- border subtle,
- shadow small,
- breadcrumb kecil di atas,
- title besar dan ramah,
- subtitle pendek,
- right-side compact status card.

Untuk Bridge Neo Feeder:

```text
Breadcrumb: Dashboard | 08 Sep 2026
Title: Halo, Operator
Subtitle: Pantau import, validasi, dan sinkronisasi Neo Feeder hari ini.
Right status card: Koneksi Neo Feeder / Online / Trial
```

### Metric Card

Dipakai untuk:

- Import valid,
- Error validasi,
- Queue aktif,
- Record terkirim,
- Referensi terakhir sync,
- SIAKAD source aktif.

Anatomi:

```text
icon tile
small uppercase label
large value
small helper/status
optional progress bar
```

Style:

- radius 24px,
- padding 20-24px,
- icon tile 48-56px,
- hover shadow,
- border neutral,
- accent color per metric.

### Work Panel

Dipakai untuk chart, table, wizard, mapping, dan validation workspace.

Style:

- radius 24px atau 28px,
- padding 20-24px,
- background surface,
- border subtle,
- no nested card unless item berulang.

Header panel:

```text
title + small badge
subtitle
right action
```

### Status Badge

Batch status:

```text
draft
uploaded
parsing
parsed
validating
valid
invalid
ready
syncing
partial_success
success
failed
cancelled
```

Row status:

```text
pending
valid
invalid
warning
ready
syncing
success
failed
skipped
ambiguous
```

Badge style:

```text
height: 22-24px
padding: 0 8px
radius: 999px
font: 11px / 700
```

Color:

- success: green,
- warning: amber,
- danger: red,
- info: cyan,
- primary/active: blue,
- neutral: slate.

### Button

Jenis:

- Primary: `Upload Excel`, `Generate Template`, `Start Sync`.
- Secondary: `Refresh`, `Download`, `Preview`.
- Soft: aksi non-dominan dengan background blue-soft.
- Danger: `Delete`, `Cancel Batch`.
- Icon button: notification, theme, retry, copy, view detail.

Rules:

- Pakai icon lucide jika tersedia.
- Button utama punya icon kiri.
- Icon-only wajib tooltip dan aria-label.
- Disabled tetap jelas dan tidak terlihat clickable.

### Data Table

Tabel tetap padat walaupun visual dashboard playful.

Wajib:

- sticky header,
- status column,
- row number,
- natural key,
- operation,
- validation count,
- sync status,
- last response,
- action column icon-only,
- horizontal scroll jelas.

Style:

```text
container radius: 16px
row height: 40-44px
header background: surface-soft
hover row: primary-50/light or primary alpha/dark
font: 13px
```

### Validation Panel

Tampilan:

- group by severity,
- group by sheet,
- group by field,
- quick filter `Error`, `Warning`, `Info`,
- jump to row,
- copy error.

Error card kecil:

```text
icon severity
Sheet + row
field name monospace
message
suggested fix jika tersedia
```

### Upload Wizard

Step:

1. Pilih tenant/prodi/periode.
2. Upload Excel.
3. Parse workbook.
4. Validasi.
5. Dry-run.
6. Approve sync.
7. Hasil.

Visual:

- stepper horizontal desktop,
- stepper vertical mobile,
- current step biru,
- completed step hijau,
- failed step merah,
- warning count amber.

### Payload Viewer

Untuk dry-run dan sync attempts.

Rules:

- JSON split view request/response.
- Dark code block default, tetap tersedia di light mode.
- Masking default untuk credential/token/NIK/NPWP/phone.
- Toggle reveal hanya untuk role teknis.
- Copy button icon-only.

### Mapping Workspace Phase 2

Pola layout:

```text
Left: source schema/table/field list
Center: mapping grid
Right: target Neo Feeder contract and validation preview
Bottom drawer: sample rows and transformed output
```

Visual:

- source fields sebagai pill/list item,
- required target fields diberi dot merah jika belum mapped,
- resolved reference diberi check hijau,
- ambiguous match diberi warning amber,
- transform rule tampil sebagai chips.

## 9. Page Inventory

### Dashboard

Isi:

- welcome panel,
- metric cards,
- chart sync/import trend,
- queue health,
- recent import batches,
- reference freshness,
- Neo Feeder connection status.

### Kampus

Isi:

- tenant list,
- tenant status,
- prodi count,
- connection status,
- user/operator count.

### Neo Feeder Connection

Isi:

- endpoint URL,
- username/password form,
- test connection,
- token status masked,
- result card dari `GetProfilPT`,
- timeout config.

### Referensi

Isi:

- endpoint reference list,
- last sync,
- row count,
- failed reason,
- refresh button,
- preview records.

### Template Excel

Isi:

- pilih template version,
- pilih kanal,
- pilih prodi/periode,
- generate,
- download,
- daftar template generated.

### Import Batch

Isi:

- upload workbook,
- batch list,
- status,
- owner,
- created date,
- total rows,
- valid/error/warning counts.

### Batch Detail

Tabs:

- Overview
- Sheets
- Validation
- Dry Run
- Sync Attempts
- Audit

### Automation Source

Phase 2:

- source connection list,
- source type,
- status,
- schema discovery,
- sample data.

### Mapping Profile

Phase 2:

- source profile,
- target channel,
- mapping version,
- field mapping,
- transform rules,
- sample validation.

## 10. Light And Dark Mode

Implementation rule:

- Gunakan CSS variables atau Tailwind dark class strategy.
- Theme toggle berada di topbar.
- Pilihan theme: `light`, `dark`, `system`.
- Simpan preferensi di local storage.
- Dark mode bukan hanya invert warna. Shadow, border, dan surface harus disesuaikan.

Light mode:

- background terang,
- panel putih,
- border slate-200,
- blue soft hover.

Dark mode:

- background navy-slate gelap,
- panel slate-900/800,
- border slate-700,
- blue glow secukupnya,
- text utama putih lembut.

## 11. Icon System

Gunakan lucide-react di frontend.

Mapping icon:

```text
Dashboard: LayoutDashboard
Kampus: Building2
Neo Feeder: DatabaseZap
Referensi: BookOpen
Template Excel: FileSpreadsheet
Import Batch: UploadCloud
Validasi: ShieldCheck
Sinkronisasi: RefreshCcw
Mapping: Waypoints
Otomatisasi: Workflow
Audit Log: ClipboardList
Pengaturan: Settings
Search: Search
Theme: Moon / Sun
Notification: Bell
User: CircleUserRound
```

Icon tile colors:

- primary: blue,
- sync/info: cyan,
- success: green,
- warning: amber,
- danger: red,
- automation: purple.

## 12. Motion

Gunakan motion kecil:

- hover card `translateY(-2px)`,
- icon scale `1.04`,
- button active press `scale(0.98)`,
- drawer slide 160-220ms,
- toast fade/slide 160-220ms.

Hindari:

- animasi looping yang mengganggu,
- chart terlalu ramai,
- transisi lambat untuk workflow import.

## 13. Forms

Rules:

- Required label pakai `*`.
- Field help singkat di bawah input.
- Tanggal selalu `yyyy-mm-dd`.
- UUID dan kode pakai monospace.
- Dropdown referensi harus searchable.
- Referensi besar seperti wilayah wajib searchable dan lazy-loaded.
- Numeric menampilkan min/max/precision.
- Password/token default masked.

Input style:

```text
height: 40-44px
radius: 12px
border: neutral
focus: blue ring
error: red border + message
```

## 14. Empty, Loading, Error States

Empty state:

```text
Belum ada batch import.
[Upload Excel]
```

Loading:

- skeleton table,
- progress bar untuk parse/validation/sync,
- spinner kecil untuk button,
- jangan blocking seluruh halaman jika hanya satu panel loading.

Error:

- pesan ringkas,
- field/sheet/row jika tersedia,
- link detail log,
- raw Neo Feeder error tetap tersedia untuk role teknis.

## 15. Copywriting

Gunakan istilah konsisten:

- `Kampus`
- `Tenant`
- `Neo Feeder`
- `Referensi`
- `Template Excel`
- `Batch Import`
- `Validasi`
- `Dry-run`
- `Sinkronisasi`
- `Mapping`
- `Otomatisasi`

Nada:

- ramah,
- singkat,
- tidak menyalahkan user,
- tetap teknis saat error.

Contoh:

```text
NIK wajib 16 digit.
Prodi tidak ditemukan di referensi Neo Feeder.
Baris ini belum dikirim karena masih ada error validasi.
Sinkronisasi selesai dengan 12 sukses dan 3 perlu diperiksa.
```

## 16. Responsive

Desktop adalah target utama.

Desktop:

- sidebar fixed,
- topbar fixed,
- metric cards 3-4 kolom,
- mapping workspace 3 panel.

Tablet:

- sidebar bisa collapsed,
- metric cards 2 kolom,
- drawer detail full height.

Mobile:

- sidebar menjadi drawer,
- topbar ringkas,
- metric cards 1 kolom,
- tabel punya horizontal scroll,
- mapping workspace boleh read-only/terbatas.

## 17. Accessibility

- Semua icon button punya tooltip dan aria-label.
- Kontras warna status harus cukup.
- Jangan mengandalkan warna saja untuk status.
- Error form harus terhubung dengan field.
- Focus ring terlihat di light dan dark mode.
- Keyboard navigation untuk sidebar, search, modal, drawer, dan table action.

## 18. Data Density

Default:

- dashboard card visual boleh lapang,
- table dan validation workspace tetap compact,
- row height tabel 40-44px,
- action column icon-only,
- detail panjang masuk drawer atau full page.

Jangan:

- menaruh JSON panjang di kartu kecil,
- membuat card di dalam card berulang,
- membuat wizard terlalu banyak teks instruksional.

## 19. Design QA Checklist

Sebelum implementasi UI dianggap selesai:

- Light mode dan dark mode dicek.
- Sidebar active/hover/disabled dicek.
- Header fixed tidak menutup konten.
- Text tidak overlap di mobile.
- Tabel besar bisa discroll horizontal.
- Status badge terbaca di kedua mode.
- Payload viewer masking aktif.
- Error validation jelas per sheet, row, dan field.
- Warna base biru konsisten, tetapi status tidak semuanya biru.
