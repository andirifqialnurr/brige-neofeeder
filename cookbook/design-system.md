# Design System - Bridge Neo Feeder

## Arah Visual

Dashboard operasional yang tenang, ringkas, dan berpusat pada tabel. Identitas
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
| Judul halaman | 24 / 32px | 600 |
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
- PageHeader: satu h1 pendek dan aksi utama.
- SectionHeader: judul area data dan bantuan opsional.
- WorkspacePanel: section tanpa card styling.
- DataTable: lebar penuh, scroll horizontal lokal, empty state di luar tabel
  kosong agar tidak memaksa scroll pada mobile.

## Layout Dan Navigasi

- Sidebar desktop 224px; grup Operasional, Pengaturan, dan Otomatisasi.
- Mobile: menu bisa dibuka/tutup, tidak selalu memenuhi layar di atas konten.
- Topbar hanya konteks workspace, identitas pengguna, tema, logout.
- Judul tidak diulang di topbar, banner, dan header tabel.
- Search/notifikasi/menu pengguna tidak boleh tampil sebagai tombol dummy.
- Fitur belum tersedia memakai status rencana, bukan aksi palsu.
- Tabel mengambil lebar utama; tambah kampus, credential, upload melalui dialog.

## Pola Halaman

| Halaman | Isi utama | Aksi |
| --- | --- | --- |
| Dashboard | Batch terbaru | Upload Excel, lihat semua |
| Kampus | Daftar kampus | Tambah, refresh |
| Neo Feeder | Tab koneksi dan referensi | Tambah/edit, test, sync |
| Template Excel | Sheet workbook | Download |
| Import Batch | Daftar batch dan pencarian nama/kampus | Upload, refresh, validasi |
| Validasi | Pilih batch, satu tombol dry-run, ringkasan hasil, tabel | Dry-run |
| Mapping | Status fase 2 | Belum ada aksi hingga tersedia |

- Angka harus berasal dari API. Jangan menampilkan hardcoded Draft/nol/Local Dev.
- Data belum tersedia tidak disamakan dengan nol.
- Hasil dry-run dibersihkan saat pilihan batch berubah.
- Pencarian kosong berbeda dari belum ada batch.
- Feedback sukses/error dekat dengan aksi; tidak diulang di beberapa panel.

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
