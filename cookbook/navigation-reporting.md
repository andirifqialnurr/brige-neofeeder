# Navigation And Reporting

## Alur Operator

1. Unduh workbook melalui Template Excel.
2. Isi workbook sesuai contract dan referensi kampus.
3. Upload melalui Import Batch. Upload berhasil membuka detail batch tersebut.
4. Periksa status parsing, ringkasan, filter sheet/status, dan temuan per baris
   di detail batch. Laporan Excel dan dry-run berada di halaman yang sama.
5. Approval operator, UI progress/retry, serta trial pengiriman aktual tetap
   mengikuti task yang belum dicentang di `todo.md`. Dry-run bukan pengiriman.

Tidak ada menu Validasi tersendiri. Dashboard bukan pintu duplikat untuk daftar
batch; daftar data hanya berada di modul pemiliknya.

## Struktur Route

| URL | File di frontend/src/pages |
| --- | --- |
| `/` | `landing/index.tsx` |
| `/login` | `login/index.tsx` |
| `/dashboard` | `dashboard/index.tsx` |
| `/campus` | `campus/index.tsx` |
| `/neo-feeder` | `neo-feeder/index.tsx`, tab referensi di `references.tsx` |
| `/template-excel` | `template-excel/index.tsx` |
| `/import-batch` | `import-batch/index.tsx` |
| `/import-batch/:uuid` | `import-batch/detail.tsx` |
| `/mapping` | `mapping/index.tsx` |

`App.tsx` menyusun route, autentikasi, dan layout. Registry kecil di `lib/router.ts`
menggunakan History API dan `useSyncExternalStore`; tidak bergantung pada nama
folder sebagai file-based router. Sidebar menggunakan anchor asli agar Ctrl/Meta
click tetap dapat membuka tab baru. Detail di-remount berdasarkan ID batch.

Refresh dan deep-link didukung fallback SPA yang sudah ada di
`frontend/docker/nginx/default.conf`: `try_files $uri $uri/ /index.html`.
Tidak diperlukan perubahan proxy host untuk route ini. Route tak dikenal
menampilkan 404 UI; `/validation` dialihkan ke `/import-batch`.

Route internal yang memerlukan login menyimpan tujuan melalui `?next=`.
URL eksternal, protocol-relative, backslash, dan halaman non-workspace ditolak
sebagai tujuan login. Sesi tersimpan diperiksa melalui `/auth/me` sebelum halaman
privat dirender. Pengguna yang sudah login tidak perlu login ulang di `/login`.

## Definisi Statistik

`GET /api/dashboard/statistics?days=30&tenant_id=<uuid>` memerlukan token aplikasi.
Operator selalu dibatasi pada kampusnya. Admin dapat memilih satu atau seluruh
kampus. Nilai `days` hanya 14, 30, atau 90. Respons tidak mengandung data pribadi,
raw payload, credential, atau token Neo Feeder dan menggunakan `Cache-Control: no-store`.

- KPI total dan komposisi mencakup seluruh riwayat, bukan hanya 50 batch pertama.
- Aktivitas import menghitung batch menurut tanggal dibuat; rentang kalender
  mencakup hari ini dan hari kosong diisi nol. Timezone aplikasi dikirim dalam respons.
- Keberhasilan pengiriman adalah attempt `success` / (`success` + `failed`).
  Attempt antrean/retrying tidak masuk denominator; retry baru dihitung terpisah.
  Tidak ada attempt selesai menghasilkan `null`, ditampilkan `-`, bukan 100%.
- Kelengkapan referensi adalah pasangan kampus/endpoint yang memiliki data
  dibanding kampus terpilih dikali endpoint referensi yang dikenal. Ini ukuran
  cakupan cache, bukan bukti data Neo Feeder lengkap atau mutakhir.
- Workspace melaporkan kampus aktif/total, operator aktif, koneksi aktif/total,
  jumlah baris referensi, kanal template, dan baris yang memiliki peringatan.
  Status koneksi aktif bukan hasil health check.
- Grafik menampilkan aktivitas import, komposisi batch, hasil staging, dan
  status attempt pengiriman. Tidak ada daftar record pada dashboard.
- Otomatisasi masih ditandai belum tersedia; tidak ada statistik mapping atau
  scheduler fiktif untuk fase 2.

## Dependensi Dan Lisensi

Versi dipin di manifest dan Bun lockfile: `@radix-ui/react-select` 2.2.6,
`apexcharts` 5.10.6, dan `react-apexcharts` 2.1.0. Instalasi lockfile telah
diverifikasi menggunakan `bun install --frozen-lockfile --ignore-scripts`.

ApexCharts memakai ketentuan Community/Commercial/OEM, bukan asumsi lisensi MIT
tanpa syarat. Tinjau kelayakan organisasi dan model distribusi NeoBridge sebelum
penggunaan komersial, termasuk SaaS/redistribusi. Rujukan resmi:
[License options](https://apexcharts.com/license/),
[Community](https://apexcharts.com/license/community/),
[Commercial](https://apexcharts.com/license/commercial/).
Pemeriksaan ini tidak menyatakan organisasi sudah memiliki lisensi yang sesuai.

Dashboard dimuat lazy agar chart tidak membebani bundle awal landing/login.
Build masih memberi peringatan chunk dashboard sekitar 533 kB minified
(sekitar 146 kB gzip); ini bukan error build. Optimasi modular chart dapat
dilanjutkan jika ukuran transfer menjadi kendala.

## Bukti Verifikasi Lokal - 2026-09-13

- Backend: 60 test, 221 assertion lulus. Test agregat mencakup autentikasi,
  isolasi tenant, filter admin, lebih dari 50 batch, periode kalender, cakupan
  referensi, dan denominator attempt pengiriman.
- Frontend: build dan ESLint lulus; 3 test registry route/return URL lulus.
- Browser dengan backend nyata dan seed fiktif pada SQLite terisolasi:
  dashboard kosong dan berisi, detail batch, filter invalid (5 baris), refresh
  deep-link, back/forward, serta pengalihan URL Validasi lama.
- Dropdown filter dan dropdown form upload memakai popup custom; ArrowDown,
  Enter, dan Escape diuji. Escape menutup popup sebelum dialog.
- Dropdown status form kampus berhasil mengganti pilihan tanpa submit; login
  dari deep-link `/template-excel` kembali ke halaman template setelah berhasil.
- Screenshot desktop dan mobile 390x844, light/dark; SVG grafik terisi dan
  tidak ada overflow horizontal halaman pada viewport mobile yang diperiksa.
- Belum merupakan verifikasi deployment VPS/MySQL pada perubahan ini, dan
  tidak ada request pengiriman ke Neo Feeder aktual. Jalankan deployment untuk
  mengaktifkan perubahan backend/frontend secara bersamaan.
