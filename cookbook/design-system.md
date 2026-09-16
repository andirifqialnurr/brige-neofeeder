# Portable Design System - NeoBridge

Dokumen ini adalah kontrak visual dan interaksi untuk membuat aplikasi baru
dengan tampilan NeoBridge: modern, tenang, ringkas, dan berpusat pada pekerjaan.
Dokumen ini sengaja tidak memuat aturan bisnis Neo Feeder, nama menu yang wajib,
endpoint, atau data contoh.

## Cara Menggunakan

File ini adalah spesifikasi, bukan package UI. Mengambil file Markdown saja tidak
akan membuat website baru otomatis memiliki tampilan yang sama. Untuk hasil yang
konsisten, aplikasi baru perlu membawa enam bagian berikut:

1. Token CSS pada bagian Warna.
2. Global reset, typography, layout shell, dan responsive rule.
3. Shared component dengan kontrak pada bagian Komponen.
4. Theme provider yang menambahkan class `light` atau `dark` pada elemen root.
5. Dependency icon, select, dan chart bila fitur tersebut dipakai.
6. QA visual pada viewport desktop, mobile, light, dan dark.

Implementasi saat ini berada di `frontend/src/components/ui/` dan
`frontend/src/styles.css`. Folder tersebut adalah reference implementation,
sedangkan aturan di dokumen ini adalah kontrak yang harus dipertahankan ketika
komponen dipindahkan ke aplikasi baru.

### Batas Portable Dan Adapter

Bagian berikut portable: token, ukuran, radius, warna status, pola layout,
perilaku keyboard, accessibility, dan kontrak shared component.

Bagian berikut adalah adapter aplikasi dan harus diganti pada aplikasi baru:

- nama produk, logo, label sidebar, dan footer;
- route breadcrumb, misalnya `/dashboard` atau `/import-batch`;
- sumber data, state management, autentikasi, dan API;
- isi tabel, label status domain, grafik, serta copy publik;
- aturan akses, permission, dan workflow bisnis.

Jangan menyalin route NeoBridge ke aplikasi baru hanya karena menyalin
`PageHeader`. `PageHeader` harus menerima home/parent breadcrumb dari adapter.

## Arah Visual

Gunakan antarmuka operasional yang tenang dan mudah dipindai. Data utama berada
di ruang kerja dan tabel; aksi berada di toolbar; detail dibuka ketika diperlukan.

- Satu page header untuk satu halaman.
- Satu boundary tabel untuk satu kumpulan data.
- Section memakai whitespace dan divider, bukan floating card.
- Card hanya untuk item berulang, dialog, popover, atau tool yang memang perlu
  dibingkai.
- Hindari card di dalam card, gradient hero, glow, blob, bokeh, dan dekorasi yang
  tidak membantu pekerjaan.
- Hindari deskripsi yang mengulang label. Pindahkan keterangan sekunder ke
  tooltip atau state bantuan yang muncul saat diperlukan.
- Jangan menampilkan data yang sama dua kali dengan bahasa berbeda.
- Status harus memakai teks; warna saja tidak cukup.
- Aksi yang belum tersedia harus berstatus rencana atau disabled dengan alasan,
  bukan tombol dummy.

Referensi proporsi dan pola interaksi:

- [shadcn/ui Dashboard](https://ui.shadcn.com/examples/dashboard)
- [IBM Carbon Data Table](https://carbondesignsystem.com/components/data-table/usage/)
- [IBM Carbon Typography](https://carbondesignsystem.com/elements/typography/type-sets/)

Referensi tersebut bukan template yang harus disalin. Framework dapat React,
Vite, Next.js, atau framework lain selama kontrak visual dan interaksinya sama.

## Dependency Reference

Dependency di bawah dipakai oleh implementasi React saat ini. Versi boleh
diperbarui mengikuti aplikasi baru; perilaku komponen harus tetap sama.

| Kebutuhan             | Reference                         |
| --------------------- | --------------------------------- |
| UI framework          | React + TypeScript                |
| Build                 | Vite                              |
| CSS utility, opsional | Tailwind CSS 4                    |
| Select custom         | `@radix-ui/react-select`          |
| Icon                  | `lucide-react`                    |
| Chart, opsional       | `apexcharts` + `react-apexcharts` |
| Dialog                | Native HTML `<dialog>`            |

Tidak wajib menggunakan shadcn CLI. Komponen lokal mengikuti pola shadcn:
primitive kecil, token terpusat, variant terbatas, dan halaman hanya menyusun
komponen tanpa mengulang styling kontrol.

### Reference Implementation Map

Saat mengambil implementasi React yang sudah ada, gunakan file berikut sebagai
paket visual. File di luar daftar ini adalah adapter atau fitur domain.

```text
src/components/ui/
  button.tsx       data-table.tsx    form-dialog.tsx  help-tip.tsx
  layout.tsx       pagination.tsx    report-chart.tsx select.tsx
  section.tsx      state.tsx         status-badge.tsx tabs.tsx
  theme-toggle.tsx
src/components/theme-context.ts
src/components/theme-provider.tsx
src/hooks/use-theme.ts
src/styles.css
```

`styles.css` saat ini juga memuat style halaman domain dan publik. Saat diekstrak,
pertahankan blok token, global CSS, layout shell, dan primitive; pindahkan style
khusus domain ke stylesheet aplikasi baru agar package tetap portable.

## Design Tokens

Token memakai HSL tanpa fungsi `hsl()` di nilai variable. Gunakan dengan
`hsl(var(--token))` atau `hsl(var(--token) / alpha)`.

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
  --radius: 0.5rem;
}

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

### Semantic Color

| Token              | Penggunaan                                      |
| ------------------ | ----------------------------------------------- |
| `primary`          | Aksi utama, link, selected state, focus ring    |
| `background`       | Latar halaman                                   |
| `card`             | Surface sidebar, input, dialog, item terbingkai |
| `popover`          | Dropdown dan tooltip                            |
| `muted`            | Header tabel, surface sekunder, hover ringan    |
| `muted-foreground` | Caption, label sekunder, breadcrumb parent      |
| `border` / `input` | Garis pemisah dan border form                   |
| `success`          | Berhasil, tersimpan, valid                      |
| `warning`          | Peringatan atau perlu pemeriksaan               |
| `destructive`      | Error, gagal, aksi berisiko                     |
| `info`             | Informasi kontekstual                           |

Biru adalah warna identitas dan aksi, bukan warna untuk semua hal. Status sukses,
warning, error, dan info tetap memakai warna semantic masing-masing.

## Global CSS Contract

```css
*,
*::before,
*::after {
  box-sizing: border-box;
}

html {
  scroll-behavior: smooth;
  scroll-padding-top: 24px;
}

body {
  margin: 0;
  min-width: 0;
  color: hsl(var(--foreground));
  background: hsl(var(--background));
  font-family:
    ui-sans-serif,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
  font-size: 14px;
  line-height: 1.6;
  font-synthesis: none;
  text-rendering: optimizeLegibility;
}

h1,
h2,
h3,
p {
  margin: 0;
  letter-spacing: 0;
}

:focus-visible {
  outline: 2px solid hsl(var(--ring));
  outline-offset: 3px;
}
```

## Typography And Spacing

| Elemen                      | Size / line-height | Weight          |
| --------------------------- | ------------------ | --------------- |
| Page heading semantic `h1`  | 24 / 32px          | 600             |
| Section/dialog heading `h2` | 16 / 24px          | 600             |
| Subheading `h3`             | 15 / 24px          | 600             |
| Breadcrumb                  | 14 / 22px          | 400; active 500 |
| Body, input, table          | 14 / 22px          | 400             |
| Button dan label            | 14 / 20px          | 500             |
| Caption dan table header    | 12 / 18px          | 500             |
| Metric number               | 28 / 36px          | 600             |
| Landing heading desktop     | 48 / 56px          | 600             |
| Landing heading mobile      | 36 / 44px          | 600             |
| Public section heading      | 28 / 36px          | 600             |

- Gunakan system sans. Inter hanya jika asset benar-benar dimuat.
- Letter spacing selalu `0`; jangan menggunakan ukuran font berbasis viewport.
- Weight maksimum 600. Bold bukan default.
- Spacing scale: 4, 8, 12, 16, 24, 32, 48, 64px.
- Padding area kerja: 32px desktop, 24px tablet, 16px mobile.
- Angka memakai tabular numerals.
- Input dan button tinggi 40px; target sentuh mobile minimal 44px.

## Radius And Surface

| Elemen                               | Radius | Catatan                   |
| ------------------------------------ | ------ | ------------------------- |
| Button, input, nav item              | 6px    | Kontrol compact           |
| Table, dialog, repeated item         | 8px    | Satu permukaan terbingkai |
| Section, page header, summary inline | 0px    | Tidak menjadi card        |
| Dropdown dan tooltip                 | 6px    | Shadow ringan             |

Shadow hanya dipakai untuk dialog dan popover. Jangan menambahkan radius
asimetris, shadow pada setiap section, atau hover yang menggeser layout.

## Layout Shell

```text
Desktop
app-shell: 224px sidebar | minmax(0, 1fr) content
content: mobile-topbar? -> topbar -> page-content

Page content
page-header: breadcrumb kiri | page-actions kanan
section: heading/toolbar -> content -> pagination
```

- Sidebar desktop lebar 224px, sticky, dan tinggi viewport.
- Header grup sidebar tetap tampil. Gunakan grup yang relevan dengan aplikasi baru.
- Topbar hanya berisi identitas, theme toggle, dan aksi akun. Jangan menaruh
  breadcrumb atau judul halaman di topbar.
- Page header memiliki satu breadcrumb dan satu grup aksi.
- Page content memiliki `max-width: 1680px`, `margin: 0 auto`, dan `min-width: 0`
  pada setiap child langsung agar tabel tidak memaksa halaman melebar.
- Section data tidak dibungkus card tambahan.
- Aksi per baris dan pagination tetap dekat dengan tabel, bukan dipindah ke
  page header.

### Breadcrumb Dan Page Actions

Breadcrumb menggantikan judul yang terlihat. Tetap render satu `h1` aksesibel
dengan class `sr-only`.

Kontrak portable:

```ts
type BreadcrumbItem = { label: string; href?: string };

type PageHeaderProps = {
  title: string;
  breadcrumb: BreadcrumbItem[];
  action?: React.ReactNode;
};
```

Aturan:

- Parent breadcrumb berupa link; item aktif memakai `aria-current="page"`.
- Nama aktif memakai ellipsis dan `title`, bukan mendorong toolbar keluar layar.
- Filter, search, dan aksi berada di kanan breadcrumb dalam satu flex group.
- Gap antar kontrol 8px.
- Select toolbar lebar 152px; search toolbar lebar 180px.
- Pada mobile breadcrumb berada di baris pertama; aksi boleh wrap ke baris berikutnya.
- Label visual yang sudah jelas tidak perlu deskripsi tambahan. Label aksesibel
  tetap wajib untuk kontrol icon-only.

## Shared Components

Semua komponen berulang berada di satu folder, misalnya
`src/components/ui/`, dan diekspor melalui satu barrel `index.ts`.

### `AppButton`

```ts
type AppButtonProps = {
  children: React.ReactNode;
  variant?: "primary" | "secondary" | "ghost";
  icon?: LucideIcon;
  type?: "button" | "submit" | "reset";
  disabled?: boolean;
  onClick?: () => void;
};
```

Primary untuk aksi utama. Secondary untuk aksi alternatif. Ghost hanya untuk
aksi ringan. Icon memakai `lucide-react`, bukan SVG manual. State loading harus
langsung men-disable button dan mengganti label bila operasi asynchronous.

### `IconButton`

Button icon-only selalu memiliki `aria-label` dan tooltip. Tooltip mendukung
hover, focus, dan touch; tooltip bukan satu-satunya cara untuk memahami aksi.
Target desktop 36px, target mobile minimal 44px.

### `Select`

Select wajib custom. Jangan memakai popup default browser untuk filter atau form.

- Gunakan Radix Select atau primitive setara.
- Trigger tinggi 40px, radius 6px, padding 8px 12px.
- Ikon trigger adalah `ChevronDown`; item terpilih memakai `Check`.
- Popup memiliki border, background popover, radius 6px, padding 4px, dan shadow.
- Item minimal tinggi 36px, padding 8px 10px.
- Dukung keyboard, typeahead, focus, selected state, disabled state, dan
  collision viewport.
- Di dalam native dialog, portal popup harus ditempatkan di bawah dialog agar
  tidak tertutup top layer.
- Label form tetap disediakan; label yang tidak perlu terlihat boleh memakai
  `sr-only`.

### `DataTable`

- Satu border luar dengan radius 8px; jangan masukkan tabel ke card lain.
- Lebar tabel 100% dan scroll horizontal hanya di `.table-shell`.
- Header 12px/18px weight 500, background muted.
- Cell padding 12px 16px dan tinggi minimum baris 48px.
- Header tabel boleh sticky terhadap area scroll.
- Gunakan `scope="col"`, region label, dan tabular numerals.
- Empty state, loading, dan error harus dibedakan. Jangan menyamakan data belum
  tersedia dengan angka nol.

### `StatusBadge`

Badge ringkas: padding 2px 8px, radius 4px, font 12px/20px weight 500.
Variant: `neutral`, `info`, `success`, `warning`, `destructive`. Teks status
harus tetap terlihat dalam light dan dark mode.

### `FormDialog`

Gunakan native `<dialog>` atau primitive setara dengan:

- satu surface dialog, lebar normal 480px dan detail maksimal 760px;
- radius 8px, shadow hanya pada dialog;
- judul aksesibel, tombol tutup icon dengan tooltip, Escape, dan backdrop;
- focus containment serta focus kembali ke trigger;
- button submit disabled selama request;
- field dan error tetap terlihat tanpa window `alert` atau `confirm`.

### `ViewTabs`

Tab dipakai untuk dua sampai lima view yang masih memiliki konteks halaman sama.
Tab tidak otomatis menjadi menu sidebar.

- Gunakan `role="tablist"`, `role="tab"`, `aria-selected`, dan `tabpanel`.
- Hanya tab aktif berada dalam tab order.
- Arrow Left/Right, Home, dan End memindahkan fokus.
- Tab aktif memakai warna primary dan underline 2px.

### `HelpTip`

Gunakan untuk penjelasan sekunder yang memang dibutuhkan. Help tip harus dapat
dibuka dengan click/touch dan keyboard, memiliki `aria-expanded`, dan ditutup
dengan Escape atau saat fokus keluar. Jangan menaruh paragraf deskripsi panjang
di setiap section.

### `ReportChart`

Chart adalah opsional dan hanya untuk angka agregat. Reference menggunakan
ApexCharts dengan tinggi stabil 280px, tooltip nilai, warna semantic, toolbar
tersembunyi, dan reduced motion. Gunakan chart untuk perbandingan/tren; jangan
memakai chart sebagai pengganti tabel operasional atau mengulang angka yang sama
di banyak panel.

## Responsive Contract

| Breakpoint | Aturan                                                                 |
| ---------- | ---------------------------------------------------------------------- |
| > 1024px   | Padding content 32px, desktop sidebar, chart dashboard dapat dua kolom |
| 801-1024px | Padding content 24px, chart mulai menyempit                            |
| <= 800px   | Chart satu kolom, metric grid dua kolom                                |
| <= 760px   | Sidebar menjadi menu, topbar mobile, content 16px, kontrol 44px        |

Pada mobile:

- Sidebar tidak selalu menutupi content; gunakan tombol buka/tutup.
- User identity boleh diringkas, tetapi theme dan logout tetap tersedia.
- Page actions boleh wrap dan tidak boleh keluar viewport.
- Table scroll terjadi di area table, bukan pada seluruh halaman.
- Dialog memiliki lebar `calc(100% - 32px)` dan tinggi maksimal viewport.
- Text panjang wrap atau ellipsis dengan `title`; tidak boleh overlap.
- Jangan mengecilkan font hingga target sentuh atau keterbacaan rusak.

## Theme Contract

Theme memiliki tiga nilai: `light`, `dark`, dan `system`. Theme provider:

1. Menentukan resolved theme dari pilihan user atau system preference.
2. Menambahkan class `light` atau `dark` pada `document.documentElement`.
3. Menyimpan pilihan eksplisit di local storage.
4. Mendengarkan perubahan `prefers-color-scheme` ketika mode `system` aktif.
5. Menyediakan toggle dengan icon Moon/Sun dan label aksesibel.

Tidak boleh ada warna hardcoded yang mengalahkan token untuk background, text,
border, popup, atau status. Warna chart boleh berupa hex semantic yang dipetakan
ke token chart, selama light/dark tetap terbaca.

## Page Patterns

| Pattern          | Isi                                                              |
| ---------------- | ---------------------------------------------------------------- |
| Dashboard        | KPI agregat dan chart; tidak ada daftar yang mengulang KPI       |
| List page        | Page header, search/filter, satu tabel, pagination               |
| Detail page      | Breadcrumb, summary seperlunya, tabs atau section data           |
| Form page/dialog | Field terkelompok, error dekat field, submit/cancel jelas        |
| Public landing   | Nama produk, konteks singkat, satu CTA, workflow, FAQ seperlunya |
| Login            | Email/password, reveal password, theme, error, kembali ke publik |

Data page harus memiliki URL yang dapat dibuka langsung. Sidebar memakai link
nyata, bukan button yang hanya mengubah state. Browser back, forward, dan refresh
harus mempertahankan konteks route.

### Konten Publik

- Hero memakai nama produk sebagai H1 dan media produk sebagai sinyal first viewport.
- Satu kalimat menjelaskan konteks; CTA utama terlihat tanpa membaca seluruh halaman.
- Section publik memakai layout unframed atau satu frame yang benar-benar
  diperlukan, bukan kumpulan card bertingkat.
- Jelaskan workflow satu kali. Jangan mengulangnya sebagai feature cards,
  process rail, dan strip statistik.
- Hindari istilah internal seperti worker, raw row, dependency graph, atau
  deployment pada halaman publik.

## Accessibility And QA

Checklist minimum sebelum design system dipakai di aplikasi baru:

- [ ] Semua icon button memiliki `aria-label` dan tooltip.
- [ ] Focus ring terlihat pada keyboard.
- [ ] Semua input memiliki label; label visual boleh `sr-only`.
- [ ] Tab, select, dialog, dan tooltip dapat dipakai tanpa mouse.
- [ ] Dialog mengembalikan fokus dan menutup dengan Escape.
- [ ] Loading, empty, error, success, disabled, dan permission state tersedia.
- [ ] Tidak ada overflow horizontal pada halaman; hanya tabel yang boleh scroll.
- [ ] Test pada 1280px, 1024px, 390px, dan 320px.
- [ ] Test light dan dark.
- [ ] Test nama file/text panjang, zero state, error state, dan dialog.
- [ ] Build, lint, unit test, dan screenshot browser dicatat terpisah.

## Extraction Checklist

Untuk membuat aplikasi baru dengan visual yang sama, ambil urutan berikut:

1. Salin token dan global CSS, lalu pastikan class `.light`/`.dark` bekerja.
2. Salin primitive `button`, `select`, `data-table`, `dialog`, `tabs`, `badge`,
   `tooltip`, `layout`, `state`, dan `pagination`.
3. Sesuaikan adapter `Brand`, breadcrumb, sidebar route, dan theme storage key.
4. Buat satu halaman list dan satu halaman form sebagai reference screen.
5. Jalankan QA responsive dan accessibility sebelum menambah fitur domain.
6. Jangan menyalin komponen domain NeoBridge jika aplikasi baru tidak
   membutuhkan workflow tersebut.

Dengan checklist ini, aplikasi baru akan memakai design system yang sama secara
visual dan interaktif. Kesamaan pixel-perfect tetap memerlukan asset logo, font,
isi, dan implementasi component yang sama.
