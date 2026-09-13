# Design System - Bridge Neo Feeder

## Arah Visual

Workspace operasional yang tenang dan ringkas. Dashboard berisi statistik;
halaman pengelolaan data berpusat pada tabel. Identitas
biru dan seluruh nilai semantic color light/dark tetap mengikuti `frontend/src/styles.css`.
Revisi September 2026 menggantikan curved canvas dan panel dekoratif sebelumnya.

Referensi:
- [shadcn/ui Dashboard](https://ui.shadcn.com/examples/dashboard): navigasi dan kontrol compact.
- [IBM Carbon Data Table](https://carbondesignsystem.com/components/data-table/usage/):
  tabel mengambil ruang utama, aksi dalam toolbar, detail dibuka saat diperlukan.
- [IBM Carbon Typography](https://carbondesignsystem.com/elements/typography/type-sets/):
  hierarki tipografi produktif untuk antarmuka padat data.

Referensi menjadi acuan proporsi dan interaksi, bukan template beserta seluruh
statistik/dekorasinya. Framework tetap React/Vite. Komponen lokal mengikuti pola
shadcn; Radix/TanStack ditambahkan hanya saat ada kebutuhan nyata.

## Warna Dan Tipografi

Pertahankan token warna yang ada. Biru untuk aksi/link/seleksi; background/card
untuk surface, border untuk pemisah. Status selalu disertai teks. Hindari gradient,
glow, transparansi bertumpuk, dan shadow tombol.

| Elemen | Ukuran / line-height | Weight |
| --- | --- | --- |
| Breadcrumb halaman | 14 / 22px | 400; halaman aktif 500 |
| Judul section/dialog | 16 / 24px | 600 |
| Isi, input, tabel | 14 / 22px | 400 |
| Tombol, label | 14 / 20px | 500 |
| Caption, header tabel | 12 / 18px | 500 |
| Judul landing | 48 / 56px desktop; 36 / 44px mobile | 600 |
| Judul section publik | 28 / 36px | 600 |

- Font system sans; Inter hanya jika asset benar-benar dimuat.
- Letter spacing 0. Tidak memakai font berbasis vw.
- Weight maksimum 600; bold tidak menjadi default seluruh halaman.
- Spacing: 4, 8, 12, 16, 24, 32, 48, 64px.
- Padding area kerja: 32px desktop, 16px mobile.
- Tombol/input 40px; target sentuh mobile minimal 44px.
- Angka tabel menggunakan tabular numerals.

## Surface Dan Radius

| Elemen | Radius |
| --- | --- |
| Tombol, input, navigasi | 6px |
| Tabel, dialog, item berulang | 8px |
| Section, header, ringkasan inline | 0 |

Section tidak menjadi floating card. Gunakan whitespace/divider. Tabel memiliki
satu border tanpa card pembungkus. Dialog adalah satu permukaan tanpa card tambahan.
Shadow hanya untuk dialog/popover. Tidak ada radius asimetris atau hover yang menggeser layout.

## Shared Components

Primitive berulang di `frontend/src/components/ui/`, diekspor melalui `index.ts`.
Halaman menyusun primitive dan data, tanpa mengulang styling kontrol.

- AppButton/IconButton: varian konsisten, loading/disabled, label aksesibel.
- HelpTip: bantuan pointer/keyboard/touch untuk detail tambahan.
- FormDialog: native dialog dengan focus containment, Escape, tombol tutup,
  dan pengembalian fokus. Bukan window.alert/confirm.
- PageHeader: breadcrumb menggantikan judul yang terlihat; satu h1 `sr-only`
  dipertahankan untuk pembaca layar. Filter/search/aksi halaman dalam satu grup kanan.
- SectionHeader: judul area data dan bantuan opsional.
- WorkspacePanel: section tanpa card styling.
- DataTable: lebar penuh, scroll horizontal lokal, empty state di luar tabel
  kosong agar tidak memaksa scroll pada mobile.
- Select: Radix Select dengan trigger/popup custom, ikon Lucide ChevronDown/Check,
  radius 6px, fokus/seleksi/disabled yang jelas. Tidak menggunakan popup select
  bawaan browser. Dukungan keyboard/typeahead dan label form tetap wajib.
  Portal dropdown dalam native dialog harus menjadi anak dialog agar tidak
  tertutup top layer; Escape pertama menutup dropdown, berikutnya dialog.
- ReportChart: renderer ApexCharts bersama untuk area, bar, dan donut; tinggi
  stabil 280px, tooltip nilai, warna status semantik, dan reduced motion.

## Layout Dan Navigasi

- Sidebar desktop 224px; grup Operasional, Pengaturan, dan Otomatisasi.
- Mobile: menu bisa dibuka/tutup, tidak selalu memenuhi layar di atas konten.
- Header grup sidebar tetap tampil. Topbar tetap ada untuk identitas pengguna,
  tema, dan logout, tanpa breadcrumb atau judul halaman.
- Breadcrumb berada pada awal konten halaman, sejajar dengan kontrol yang rata
  kanan. Parent berupa link, halaman aktif memakai `aria-current="page"`.
- Filter dropdown 152px, search 180px, gap kontrol 8px; label filter tetap
  aksesibel melalui `sr-only`. Lebar ini khusus toolbar, bukan form dalam dialog.
- Desktop memakai satu baris ketika ruang cukup. Pada layar sempit breadcrumb
  berada di atas dan kontrol wrap ke kanan, tanpa mengurangi target sentuh 44px.
- Nama file panjang di breadcrumb memakai ellipsis dan title; tidak mendorong
  kontrol keluar layar. Pagination, aksi per baris, dan submit dialog tetap lokal.
- Judul tidak diulang di topbar, banner, dan header tabel.
- Search/notifikasi/menu pengguna tidak boleh tampil sebagai tombol dummy.
- Fitur belum tersedia memakai status rencana, bukan aksi palsu.
- Tabel mengambil lebar utama; tambah kampus, credential, upload melalui dialog.
- Setiap halaman memiliki URL dan file di `frontend/src/pages/`. Sidebar
  memakai link nyata; tombol back/forward dan refresh tidak mengubah konteks halaman.
- Detail batch berada di `/import-batch/:id`; Import Batch tetap aktif di sidebar.
  Validasi bukan menu terpisah. URL lama `/validation` mengarah ke daftar import.

## Pola Halaman

| Halaman | Isi utama | Aksi |
| --- | --- | --- |
| Dashboard | Angka agregat dan grafik, tanpa daftar batch | Filter kampus/periode aktivitas, refresh |
| Kampus | Daftar kampus | Tambah, refresh |
| Neo Feeder | Tab koneksi dan referensi | Tambah/edit, test, sync |
| Template Excel | Sheet workbook | Download |
| Import Batch | Daftar batch dan pencarian nama/kampus | Upload, refresh, buka detail |
| Detail batch | Ringkasan, filter sheet/status, tabel baris dan temuan | Dry-run, laporan Excel, kembali ke daftar |
| Mapping | Status fase 2 | Belum ada aksi hingga tersedia |

- Angka harus berasal dari API. Jangan menampilkan hardcoded Draft/nol/Local Dev.
- Data belum tersedia tidak disamakan dengan nol.
- Hasil dry-run dibersihkan saat pilihan batch berubah.
- Pencarian kosong berbeda dari belum ada batch.
- Feedback sukses/error dekat dengan aksi; tidak diulang di beberapa panel.
- Dashboard memakai endpoint agregat seluruh data yang diizinkan, bukan jumlah
  hasil pagination. Ringkasan total dan grafik komposisi memiliki tujuan berbeda;
  jangan tambahkan daftar terbaru atau angka total yang sama di panel lain.
- KPI dan grafik dipisahkan whitespace/divider, bukan card di dalam card.
- Nol berarti tidak ada data; `-` berarti rasio belum dapat dihitung. Otomatisasi
  belum tersedia tidak boleh ditampilkan seolah sudah menghasilkan aktivitas.
- Definisi statistik, route, bukti QA, dan ketentuan lisensi ApexCharts ada di
  [navigation-reporting.md](navigation-reporting.md).

## Konten Publik Dan Login

- Landing: nama produk, satu kalimat konteks, satu CTA utama, alur kerja,
  pengelolaan kampus, roadmap otomatisasi, FAQ ringkas.
- Jelaskan alur sekali; jangan ulang sebagai strip, fitur, dan process rail.
- Screenshot produk menjadi media; data contoh diberi label, bukan status live.
- Section publik tidak menjadi card; tanpa gradient hero.
- Login fokus pada email/password, reveal password, tema, error, dan kembali.
- Hindari istilah internal worker, raw row, dependency graph, dan deployment.
- Deskripsi yang jelas dari label dihapus; bantuan penting tetap di label/error.

## Accessibility Dan QA

- Focus ring terlihat; icon button memiliki aria-label dan tooltip.
- Bantuan tambahan bisa diakses keyboard/touch, bukan hover saja.
- Dialog memiliki judul aksesibel, Escape, tombol tutup, dan fokus kembali.
- Tombol simpan disabled selama request.
- Tab mendukung selected state dan navigasi keyboard.
- Periksa desktop/mobile, light/dark, halaman publik dan dashboard.
- Periksa nama file panjang, empty/error/loading, dialog, dan scroll tabel.
- Build/lint adalah pemeriksaan kode; bukti browser dicatat terpisah.
