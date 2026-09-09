# Design System - Bridge Neo Feeder

## 1. Tujuan

Design system ini menjadi kontrak visual dan komponen untuk aplikasi Bridge Neo Feeder.

Arah utama:

- Dashboard modern, estetik, compact, dan rapi.
- Tidak banyak teks deskriptif di dalam UI.
- Tidak mengulang data yang sama di beberapa komponen.
- Tidak ada card bertumpuk tanpa fungsi.
- Base warna biru, tetapi surface tetap netral agar aplikasi terasa premium.
- Light mode dan dark mode wajib tersedia dari awal.
- Komponen berulang harus memakai shared component, bukan class ad hoc di tiap page.

Bridge Neo Feeder adalah aplikasi operasional, bukan landing page. First screen harus langsung membantu operator melihat koneksi, import, validasi, dan sinkronisasi.

## 2. UI Foundation

Gunakan:

- React + Vite.
- Tailwind CSS.
- shadcn/ui sebagai base component.
- Radix primitive via shadcn untuk dialog, dropdown, popover, tabs, sheet, tooltip.
- lucide-react untuk icon.
- CSS variables untuk color token dan theme.

Tambahkan bertahap saat dibutuhkan:

- TanStack Table untuk tabel import, validasi, dan audit yang kompleks.
- Recharts untuk chart ringkas.
- React Hook Form + Zod untuk form credential, tenant, upload, dan mapping.

Alasan memakai shadcn/ui:

- Komponen bisa dimiliki di repo, bukan black box library.
- Mudah disesuaikan dengan warna biru dan dark mode.
- Cocok untuk dashboard admin modern.
- Mengurangi class duplication karena komponen dasar distandarkan.

## 3. Design Principles

### Compact By Default

UI harus langsung ke pekerjaan:

- Label pendek.
- Helper text hanya saat field berisiko salah.
- Empty state singkat.
- Tidak ada paragraf penjelasan panjang di dashboard.

### One Data, One Place

Data tidak boleh tampil berulang tanpa alasan.

Contoh salah:

- Total error tampil di metric card, chart, panel kanan, dan table header sekaligus.

Contoh benar:

- Total error tampil di metric card.
- Table menampilkan detail error per row.
- Panel kanan hanya menampilkan aksi atau insight yang belum ada di card/table.

### Clear Hierarchy

Urutan layar:

1. Header page: judul pendek dan action utama.
2. KPI ringkas: 3 sampai 4 card maksimal.
3. Primary workspace: table, wizard, atau mapping.
4. Secondary panel: hanya jika ada status/action tambahan.

### No Nested Card Clutter

Dilarang:

- Card di dalam card hanya untuk spacing.
- Section besar dibungkus card lalu item di dalamnya card lagi tanpa kebutuhan.
- Dekorasi visual yang tidak membantu workflow.

Boleh:

- Card untuk item berulang.
- Card untuk metric.
- Modal/sheet sebagai container action.
- Panel utama untuk table atau wizard.

## 4. Layout System

### App Shell

Desktop:

```text
Sidebar 264px
Topbar 64px
Main content
Optional right panel/drawer
```

Rules:

- Sidebar fixed di desktop.
- Sidebar menjadi drawer di mobile.
- Topbar sticky, tidak menutup konten.
- Main content max-width tidak dipaksa kecil untuk tabel.
- Padding desktop 24px.
- Padding mobile 16px.

### Dashboard Grid

Gunakan grid yang stabil:

```text
Page header
Metric grid: 4 columns desktop, 2 tablet, 1 mobile
Main area: 12-column grid
Primary workspace: 8 columns
Side panel: 4 columns
```

Jika data utama adalah tabel besar, table mengambil full width dan side panel pindah menjadi sheet/drawer.

### Page Header

Isi:

- Title singkat.
- Optional subtitle maksimal 1 baris.
- Primary action kanan.

Jangan taruh banyak deskripsi. Detail bantuan masuk ke tooltip, docs, atau empty state.

## 5. Color System

Base brand: blue.

Gunakan token semantic, bukan hex langsung di komponen.

### Light Mode

```css
:root {
  --background: 210 40% 98%;
  --foreground: 222 47% 11%;

  --card: 0 0% 100%;
  --card-foreground: 222 47% 11%;

  --popover: 0 0% 100%;
  --popover-foreground: 222 47% 11%;

  --primary: 221 83% 53%;
  --primary-foreground: 210 40% 98%;

  --secondary: 214 32% 91%;
  --secondary-foreground: 222 47% 11%;

  --muted: 210 40% 96%;
  --muted-foreground: 215 16% 47%;

  --accent: 213 94% 96%;
  --accent-foreground: 221 83% 34%;

  --destructive: 0 84% 60%;
  --destructive-foreground: 210 40% 98%;

  --border: 214 32% 91%;
  --input: 214 32% 91%;
  --ring: 221 83% 53%;

  --success: 142 71% 45%;
  --success-foreground: 144 70% 96%;
  --warning: 38 92% 50%;
  --warning-foreground: 48 96% 89%;
  --info: 199 89% 48%;
  --info-foreground: 204 100% 97%;
}
```

### Dark Mode

```css
.dark {
  --background: 222 47% 7%;
  --foreground: 210 40% 98%;

  --card: 222 47% 10%;
  --card-foreground: 210 40% 98%;

  --popover: 222 47% 10%;
  --popover-foreground: 210 40% 98%;

  --primary: 217 91% 60%;
  --primary-foreground: 222 47% 7%;

  --secondary: 217 33% 18%;
  --secondary-foreground: 210 40% 98%;

  --muted: 217 33% 16%;
  --muted-foreground: 215 20% 65%;

  --accent: 217 33% 18%;
  --accent-foreground: 213 94% 88%;

  --destructive: 0 63% 50%;
  --destructive-foreground: 210 40% 98%;

  --border: 217 33% 18%;
  --input: 217 33% 18%;
  --ring: 217 91% 60%;

  --success: 142 69% 45%;
  --success-foreground: 144 70% 96%;
  --warning: 43 96% 56%;
  --warning-foreground: 38 92% 12%;
  --info: 199 89% 55%;
  --info-foreground: 204 100% 97%;
}
```

### Usage

- Primary action: blue.
- Active menu: blue soft background + blue text.
- Success: sync sukses, valid row, reference fresh.
- Warning: partial sync, ambiguous reference, stale reference.
- Destructive: failed sync, invalid payload, delete/cancel.
- Info: queue, parsing, neutral process state.

Jangan membuat dashboard semuanya biru. Gunakan neutral slate sebagai dasar, biru untuk arah perhatian.

## 6. Typography

Font:

- Default: Inter.
- Fallback: `ui-sans-serif`, `system-ui`, `Segoe UI`, `Arial`.
- Monospace: `ui-monospace`, `SFMono-Regular`, `Consolas`.

Scale:

```text
page-title: 24px / 32px / 700
section-title: 16px / 24px / 600
card-title: 14px / 20px / 600
body: 14px / 22px / 400
table: 13px / 20px / 400
caption: 12px / 18px / 500
badge: 11px / 16px / 600
```

Rules:

- Jangan pakai hero-size typography di dashboard.
- Letter spacing 0 untuk body dan heading.
- Uppercase hanya untuk label kecil atau badge.
- Angka KPI boleh 26 sampai 32px, tetapi maksimal satu angka utama per card.

## 7. Radius, Border, Shadow

Base radius:

```text
--radius: 0.875rem
button: 12px
input: 12px
card: 16px
metric-card: 18px
modal/sheet: 18px
table-wrapper: 14px
```

Shadow:

- Default card: subtle atau tanpa shadow.
- Hover card: shadow-sm.
- Modal/sheet: shadow-lg.
- Dark mode: gunakan border dan surface layering, jangan shadow berat.

Border:

- Semua panel utama punya border.
- Border warna `border`.
- Focus ring selalu `ring`.

## 8. Component Standard

Semua komponen dasar disimpan dalam satu area:

```text
frontend/src/components/ui/
```

Untuk fase awal yang masih sederhana, boleh memakai file agregasi:

```text
frontend/src/components/ui.tsx
```

Saat shadcn mulai dipasang, struktur akhir:

```text
frontend/src/components/ui/button.tsx
frontend/src/components/ui/card.tsx
frontend/src/components/ui/badge.tsx
frontend/src/components/ui/table.tsx
frontend/src/components/ui/dialog.tsx
frontend/src/components/ui/sheet.tsx
frontend/src/components/ui/dropdown-menu.tsx
frontend/src/components/ui/tabs.tsx
frontend/src/components/ui/input.tsx
frontend/src/components/ui/select.tsx
frontend/src/components/ui/tooltip.tsx
frontend/src/components/ui/skeleton.tsx
```

App-specific wrapper boleh dibuat di:

```text
frontend/src/components/layout/
frontend/src/components/dashboard/
frontend/src/components/forms/
frontend/src/components/data/
```

Rules:

- Page tidak boleh mendefinisikan button/card/table styling sendiri.
- Page hanya menyusun data dan memilih komponen.
- Variants dikelola di component file, bukan copy-paste class.
- Icon-only action wajib punya tooltip dan aria-label.

## 9. shadcn Components To Use

Prioritas pemasangan:

```text
button
card
badge
input
label
textarea
select
separator
table
tabs
dialog
sheet
dropdown-menu
tooltip
toast/sonner
skeleton
command
popover
calendar
```

Untuk form:

```text
form
input
select/combobox
calendar
textarea
checkbox
switch
```

Untuk data kompleks:

```text
table + TanStack Table
badge
dropdown-menu
sheet
skeleton
```

## 10. Core Layout Components

### AppSidebar

Isi:

- Brand.
- Environment badge.
- Grouped navigation.
- User/org switcher jika diperlukan.

Navigation group:

```text
Dashboard
Kampus
Neo Feeder
Template Excel
Import Batch
Validasi
Sinkronisasi
Mapping
Audit Log
Pengaturan
```

Rules:

- Icon 18px.
- Item height 40px.
- Active state hanya satu.
- Label group pendek.
- Jangan menampilkan count di sidebar jika count sudah ada di dashboard/table.

### AppTopbar

Isi:

- Breadcrumb pendek.
- Search command.
- Theme toggle.
- Notification.
- User menu.

Rules:

- Tinggi 64px.
- Tidak perlu subtitle panjang.
- Action utama page tetap di page header, bukan selalu di topbar.

### PageHeader

Props minimal:

```ts
type PageHeaderProps = {
  title: string;
  description?: string;
  action?: ReactNode;
};
```

Description maksimal 90 karakter. Jika lebih panjang, pindahkan ke docs atau empty state.

### MetricCard

Dipakai hanya untuk KPI ringkas.

Props:

```ts
type MetricCardProps = {
  label: string;
  value: string | number;
  icon: LucideIcon;
  tone?: 'blue' | 'green' | 'amber' | 'red' | 'cyan';
  trend?: string;
};
```

Rules:

- Maksimal 4 metric card di dashboard.
- Jangan menampilkan metric yang sama di side panel.
- Helper text maksimal 1 baris.

### WorkspacePanel

Container untuk table, wizard, validation, mapping.

Rules:

- Tidak boleh nested card untuk layout biasa.
- Header panel singkat.
- Action kanan maksimal 3.
- Jika action lebih banyak, gunakan dropdown menu.

### DataTable

Rules:

- Sticky header.
- Row height 40 sampai 44px.
- Horizontal scroll untuk banyak kolom.
- Action column paling kanan, icon-only.
- Empty state tetap di dalam table wrapper.

Default columns untuk import:

```text
No
Sheet
Natural Key
Status
Error
Warning
Operation
Last Response
Action
```

### StatusBadge

Status batch:

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

Tone:

```text
neutral: draft, uploaded, parsed
info: parsing, validating, syncing
success: valid, ready, success
warning: partial_success
destructive: invalid, failed, cancelled
```

### EmptyState

Format:

```text
Icon
Title pendek
Description 1 kalimat jika perlu
Primary action optional
```

Contoh:

```text
Belum ada batch import
Upload template Excel untuk mulai validasi.
```

### Confirmation Dialog

Dipakai untuk:

- Start sync.
- Retry failed rows.
- Delete/cancel batch.
- Update credential.

Rules:

- Tidak memakai native `alert`, `confirm`, atau `prompt`.
- Jelaskan dampak aksi dalam 1 sampai 2 kalimat.
- Tombol danger hanya untuk aksi irreversible.

## 11. Page Patterns

### Dashboard

Susunan:

```text
PageHeader
MetricGrid
MainGrid
  RecentImportBatches
  NeoFeederStatusPanel
```

Metric maksimal:

- Koneksi Neo Feeder.
- Batch aktif.
- Error validasi.
- Queue sync.

Jangan tampilkan ulang metric yang sama di chart atau side panel.

### Kampus

Susunan:

```text
PageHeader + Tambah Kampus
TenantTable
TenantDetailSheet
```

Jangan pakai card per kampus jika jumlah data bisa banyak. Gunakan table.

### Neo Feeder Connection

Susunan:

```text
ConnectionForm
TestConnectionResult
AuditRecentTable
```

Rules:

- Password selalu masked.
- Test result menampilkan `ok`, `error_code`, `error_desc`, dan waktu test.
- Token tidak pernah ditampilkan.

### Template Excel

Susunan:

```text
TemplateFilterBar
GeneratedTemplateTable
DownloadAction
```

Rules:

- Jangan membuat wizard panjang sebelum template generator benar-benar kompleks.
- Jika pilihan sedikit, gunakan select/searchable select.

### Import Batch

Susunan:

```text
UploadDropzone
BatchTable
BatchDetailSheet
```

Rules:

- Upload area hanya satu.
- Progress dan status muncul di batch table.
- Detail row muncul di sheet/page detail, bukan card tambahan di dashboard.

### Batch Detail

Tabs:

```text
Overview
Rows
Validation
Payload Preview
Sync Attempts
Audit
```

Rules:

- Overview tidak mengulang semua isi tabs.
- Overview hanya berisi summary dan next action.

### Mapping Workspace

Phase 2 layout:

```text
Source fields
Mapping table
Target contract
Preview drawer
```

Rules:

- Mapping utama memakai table/grid, bukan card per field.
- Required target yang belum mapped diberi marker.
- Transform rule tampil sebagai chip kecil.

## 12. Forms

Form layout:

- 1 column untuk modal kecil.
- 2 columns untuk page form desktop.
- Mobile selalu 1 column.

Rules:

- Required marker pakai `*`.
- Error message langsung di bawah field.
- Help text hanya untuk format yang rawan salah.
- Date format: `yyyy-mm-dd`.
- Kode, UUID, endpoint, dan field Neo Feeder memakai monospace.
- Dropdown referensi besar wajib searchable.

Credential rules:

- Password/token masked.
- Reveal hanya sementara.
- Tidak pernah mengirim password ke frontend setelah tersimpan.

## 13. Tables

Table adalah komponen utama aplikasi ini.

Rules:

- Gunakan table untuk data list, bukan card grid.
- Toolbar table berisi search, filter, dan action export/import.
- Pagination di footer.
- Filter aktif tampil sebagai chip.
- Kolom status selalu memakai badge.
- Long text dipotong dengan tooltip/detail sheet.

Density:

```text
compact row: 40px
normal row: 44px
header: 40px
cell padding x: 12px
```

## 14. Motion

Gunakan motion ringan:

- Button press: scale 0.98.
- Card hover: translateY(-1px) untuk clickable card saja.
- Sheet/dialog transition: 160 sampai 220ms.
- Skeleton untuk loading.

Hindari:

- Animasi looping di dashboard.
- Hover yang menggeser layout.
- Transisi lambat pada table dan form.

## 15. Responsive

Desktop:

- Sidebar fixed.
- Metric 4 columns.
- Main grid 8/4 atau full width table.

Tablet:

- Sidebar collapsible.
- Metric 2 columns.
- Detail via sheet.

Mobile:

- Sidebar drawer.
- Metric 1 column.
- Table horizontal scroll.
- Mapping workspace read-only atau step-by-step.

## 16. Accessibility

Wajib:

- Focus ring terlihat.
- Icon-only button punya aria-label dan tooltip.
- Status tidak hanya bergantung pada warna.
- Dialog trap focus.
- Table action bisa diakses keyboard.
- Form error terhubung ke input.

## 17. Copywriting

Gaya:

- Singkat.
- Jelas.
- Tidak menyalahkan operator.
- Tidak menjelaskan fitur yang sudah jelas dari UI.

Istilah konsisten:

```text
Kampus
Tenant
Neo Feeder
Referensi
Template Excel
Batch Import
Validasi
Dry-run
Sinkronisasi
Mapping
Otomatisasi
Audit Log
```

Contoh:

```text
NIK wajib 16 digit.
Prodi tidak ditemukan.
Batch siap disinkronkan.
Koneksi Neo Feeder gagal.
```

Hindari:

```text
Silakan menggunakan fitur ini untuk melakukan proses validasi data yang berasal dari file Excel yang sebelumnya sudah Anda upload.
```

Gunakan:

```text
Validasi data Excel sebelum sinkronisasi.
```

## 18. Implementation Rules

Saat implementasi UI:

1. Pasang Tailwind CSS.
2. Pasang shadcn/ui.
3. Buat theme token light/dark.
4. Pindahkan primitive UI ke struktur shadcn.
5. Buat layout components.
6. Baru reslice halaman dashboard.

Urutan komponen:

```text
Button
Card
Badge
Input
Label
Table
Dialog
Sheet
DropdownMenu
Tooltip
Skeleton
Tabs
```

Setiap page baru harus menjawab:

- Data utama apa yang harus terlihat?
- Apakah data itu sudah tampil di tempat lain?
- Action utama apa?
- Apakah table lebih tepat daripada card?
- Apakah detail perlu sheet, modal, atau page?

## 19. Design QA Checklist

Sebelum UI dianggap selesai:

- Tidak ada data yang tampil dobel tanpa alasan.
- Tidak ada card di dalam card untuk layout biasa.
- Tidak ada teks deskripsi panjang di dashboard.
- Table tetap rapi di 1280px dan mobile horizontal scroll.
- Light mode dan dark mode terbaca.
- Action utama jelas.
- Disabled state jelas.
- Error state jelas.
- Empty state singkat.
- Focus ring terlihat.
- Warna biru konsisten sebagai primary, bukan memenuhi seluruh layar.
