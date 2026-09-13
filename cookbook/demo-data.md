# Demo Data

## Menjalankan

Seed ini mengisi database Laravel sungguhan, bukan API fixture frontend.
Jalankan migration terlebih dahulu. Seed tidak dipanggil oleh `DatabaseSeeder`
atau `deploy.sh` dan tidak membutuhkan credential Neo Feeder.

Lokal, dari folder `backend`:

```sh
php artisan bridge:seed-demo
```

VPS, dari root repo setelah deploy versi terbaru:

```sh
docker compose exec backend php artisan bridge:seed-demo --allow-production
```

Masukkan password minimal 12 karakter saat diminta. Password tidak dicetak,
tidak disimpan di repository, dan tidak menjadi argumen shell.
Gunakan database trial terpisah bila memungkinkan; jangan gunakan `migrate:fresh`
pada database berisi data kampus. Pada produksi, flag opt-in wajib.

## Akun Dan Isolasi

| Akun | Kampus | Peran |
| --- | --- | --- |
| `demo-operator@example.test` | DEMO01 / Kampus Demo Nusantara | Operator |
| `demo-empty@example.test` | DEMO02 / Kampus Demo Kosong | Operator |

Keduanya menggunakan password yang dimasukkan saat pembuatan pertama.
Seed tidak membuat admin baru; gunakan admin milik Anda untuk pemeriksaan
menu tambah kampus, pengelolaan koneksi, dan akses lintas kampus.
Mengulang command tidak mengganti password, menghapus perubahan demo, atau
menggandakan batch. Kode/email yang berbenturan dengan data non-demo ditolak.
Jangan menjalankan dua proses seed bersamaan.

Tenant demo memiliki `metadata.demo=true`. Pengiriman batch, retry, test
koneksi, dan refresh referensi diblok di backend, termasuk worker outbound.
Koneksi contoh berstatus inactive, tanpa password/token. Jangan menghapus
marker demo atau mengubah tenant demo menjadi tenant produksi.
Workbook demo hanya boleh diupload ke tenant demo.

## Cakupan

| Menu / kondisi | Contoh |
| --- | --- |
| Landing dan login | Login operator demo, logout, login kembali |
| Dashboard / Import Batch | Empat batch dengan status validated, invalid, failed |
| Kampus | Dua kampus terpisah; DEMO02 kosong untuk uji isolasi dan empty state |
| Neo Feeder / koneksi | Satu koneksi inactive tanpa credential; kampus kedua tanpa koneksi |
| Neo Feeder / referensi | Lookup sintetis yang dipakai validator; endpoint lain tetap kosong |
| Template Excel | Template 11 kanal; dua workbook terisi di disk uploads |
| Detail / dry-run | 12 baris pada `demo-valid.xlsx`, termasuk biodata insert/update dan nilai kelas |
| Temuan / laporan Excel | 16 baris pada `demo-perbaikan.xlsx`: required, duplicate, date_format, enum, reference_exists, ambiguous_reference, dependency_check |
| Parsing gagal | `demo-file-rusak.xlsx` adalah metadata simulasi kegagalan, bukan file upload nyata |
| Pengiriman | `demo-pengiriman.xlsx`: 3 record dan 3 attempt sintetis, masing-masing berhasil, gagal, dan perlu pemeriksaan |
| Persetujuan | Dry-run `demo-valid.xlsx`, buka tab Pengiriman, centang konfirmasi, lalu Setujui; Kirim tetap diblok untuk demo |
| Mapping | Tetap placeholder fase 2; tidak membuat mapping atau jadwal fiktif |

Workbook terisi dibangun dari contract, lalu dibaca parser dan validator yang
sama dengan upload. Nilai ID, NIK berawalan nol, nama, dan referensinya fiktif.
Status valid berarti lolos validator internal, bukan diterima Neo Feeder.
Referensi ambigu masih berupa warning pada validator saat ini; review resolusi
referensi dan approval wajib diselesaikan sebelum trial pengiriman produksi.

Workbook disimpan di `storage/app/uploads/demo-v1/<tenant-id>/` (disk `uploads`).
Untuk menguji upload ulang, ambil file itu dari lingkungan lokal/container dan
upload melalui menu Import Batch pada DEMO01. Semua sheet harus tetap ada.
Laporan temuan bukan template reimport dan tidak memuat nilai data mentah.

State loading, network error, pagination besar, akses ditolak, dan proses sync
aktif diuji melalui test/fixture terkontrol, bukan seed yang sengaja dibiarkan
seolah-olah ada worker aktif. Progress dan retry tersedia dalam tab Pengiriman
di detail Import Batch; retry demo tetap dinonaktifkan. Otomatisasi masih fase 2.
Riwayat pengiriman merupakan metadata simulasi, bukan workbook upload maupun
bukti penerimaan Neo Feeder. Menjalankan seed kembali menambahkan riwayat ini
untuk demo lama tanpa mengubah tiga batch awal atau password akun.

## Verifikasi

`DemoDataSeederTest` memeriksa idempotensi, penolakan produksi tanpa opt-in,
perlindungan data yang sudah ada, 11 kanal, login asli, dry-run insert/update,
laporan, dan tidak adanya request HTTP/job outbound.
`ImportBatchInspectionTest` mencakup pagination lebih dari 50 batch, filter,
akses lintas tenant/batch, audit akses, report formula-safe, dan guard parsing.
Suite menggunakan SQLite; penerimaan MySQL/VPS dan Neo Feeder aktual tetap
harus diuji terpisah.

### Hasil Lokal 13 September 2026

- Backend: 57 test / 200 assertion lulus, termasuk upload workbook melalui
  endpoint dengan queue sync pada test, parser, validator, preview, dan report.
- Browser menggunakan Laravel asli dengan database SQLite sementara, terpisah
  dari `.env` dan database kerja. Login dua operator demo berhasil; kampus kosong
  tidak menampilkan batch kampus pertama.
- Desktop dan viewport mobile 390x844: filter sheet/status, data/payload/temuan,
  light/dark, kembali ke daftar, pencarian server, dan dry-run diperiksa.
  Pesan temuan mobile dibungkus sebagai daftar, bukan tabel horizontal.
- Download melalui UI menghasilkan XLSX 22 baris (header + 21 temuan), tanpa
  sel formula; nilai awal nol tetap utuh dalam preview payload.
- Build TypeScript/Vite dan ESLint lulus. Pengujian tidak menghubungi Neo Feeder,
  tidak menjalankan worker Redis persisten, dan tidak melakukan deploy VPS.
