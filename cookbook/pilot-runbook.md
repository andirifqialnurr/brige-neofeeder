# Pilot Runbook Neo Feeder

Dokumen ini menjadi prosedur trial untuk satu kampus. Pilot harus memakai tenant,
database, credential, dan endpoint Neo Feeder yang terpisah dari produksi. Tidak
ada token, password, NIK, atau payload mahasiswa utuh yang boleh disimpan di Git,
issue, screenshot, atau log yang dibagikan.

## Tujuan Dan Batasan

Pilot pertama membuktikan alur minimum berikut:

1. `GetToken` berhasil pada koneksi kampus.
2. Reference kampus dapat dibaca dan dipakai validator.
3. Satu biodata dapat di-dry-run, dikirim, dan diverifikasi.
4. Satu riwayat pendidikan dapat memakai `id_mahasiswa` hasil langkah 3.
5. Struktur SIAKAD dapat dibaca dengan akun database read-only jika otomasi
   database ikut diuji.

Pilot ini belum membuktikan seluruh kanal, volume produksi, sinkronisasi delete,
atau strategi incremental. Schedule tetap nonaktif sampai seluruh gate disetujui.

## Peran Dan Persetujuan

| Peran | Tanggung jawab |
| --- | --- |
| Pemilik kampus | Menyetujui endpoint, data uji, dan waktu trial. |
| Admin Bridge | Membuat tenant/koneksi, mengatur secret, dan memeriksa audit. |
| Operator kampus | Mengisi template, meninjau temuan, dan meminta approval. |
| Reviewer teknis | Memeriksa payload, response, ID hasil, dan bukti baca balik. |

Satu orang boleh memegang lebih dari satu peran, tetapi approval pengiriman
harus tetap dicatat sebagai keputusan eksplisit. Tenant demo tidak boleh dipakai
untuk menguji pengiriman.

## Prasyarat

- [ ] Endpoint Neo Feeder trial dan versi `live2.php` disepakati.
- [ ] Username/password trial dikirim melalui secret manager atau kanal aman.
- [ ] Tenant trial baru dibuat; bukan tenant demo dan bukan tenant produksi.
- [ ] Backup dan restore drill database Bridge sudah berhasil.
- [ ] Container backend, worker, scheduler, MySQL, dan Redis sehat.
- [ ] Paket trial dibuat dengan `php artisan bridge:trial-pack`.
- [ ] Kode aplikasi dan migration pada VPS sudah sama dengan commit yang diuji.
- [ ] Waktu trial dan pemilik keputusan rollback ditentukan.

Gunakan `backend/resources/trial/` sebagai paket asli. Salin workbook ke area
kerja terpisah sebelum mengubah nilai `ISI_*`; jangan mengubah paket baseline.

## Evidence Register

Catat metadata berikut pada dokumen lokal yang tidak di-commit:

| Field | Nilai |
| --- | --- |
| Kampus / kode tenant | `<nama> / <kode>` |
| Environment | `trial` |
| Commit aplikasi | `<git sha>` |
| Runtime | `<PHP/Laravel/MySQL/Redis>` |
| Pemeriksa | `<nama>` |
| Waktu mulai/selesai | `<ISO-8601>` |
| Endpoint/action | `<host tersanitasi> / <act>` |
| Batch/attempt | `<id>` |
| `error_code` | `<kode>` |
| ID hasil | `<id_mahasiswa atau id_registrasi_mahasiswa>` |
| Bukti baca balik | `<lokasi bukti tersanitasi>` |
| Keputusan | `PASS / FAIL / PERLU PEMERIKSAAN` |

Masking semua identifier pribadi pada bukti. Token hanya boleh terlihat sebagai
`[configured]` atau `[redacted]`.

## Gate 0 - Infrastruktur Dan Isolasi

1. Login sebagai admin Bridge dan pastikan tenant trial bukan tenant demo.
2. Buka menu Operasional; database, Redis, worker, dan scheduler harus sehat.
3. Pastikan queue tidak berisi pekerjaan produksi yang bisa bercampur dengan trial.
4. Buat atau pilih koneksi Neo Feeder berstatus `draft`.
5. Simpan credential melalui form aplikasi; jangan mengirim password melalui query
   string atau menaruhnya di file `.env` yang masuk Git.
6. Catat commit aplikasi, versi migration, dan waktu pengujian pada evidence
   register.

Gate lulus jika lingkungan terisolasi dan ada pemilik rollback. Jika tidak,
hentikan trial sebelum koneksi diuji.

## Gate 1 - Koneksi Dan Token

1. Masukkan base URL trial, username, dan password pada koneksi kampus.
2. Jalankan test koneksi dari UI Neo Feeder.
3. Pastikan response memiliki `error_code`, `error_desc`, dan `data.token`.
4. Catat hanya hasil `ok`, durasi, kode error, dan `token_received`; jangan catat
   token mentah.
5. Tandai `PASS` hanya jika token diterima dari endpoint yang disetujui.

Jika gagal karena jaringan atau credential, perbaiki koneksi lalu ulangi Gate 1.
Jika timeout terjadi setelah POST data, status harus `Perlu pemeriksaan`; jangan
membuat retry untuk menguji koneksi.

## Gate 2 - Reference Dan Contract

1. Jalankan sync reference satu endpoint terlebih dahulu, mulai dari `GetProdi`.
2. Verifikasi kampus, prodi, dan semester yang dipakai data uji.
3. Sync reference lain yang dibutuhkan template: agama, negara, wilayah, jenis
   pendaftaran, dan reference terkait.
4. Ambil dictionary jika tersedia pada WS trial dan bandingkan dengan contract
   internal. Perbedaan field atau operasi harus dicatat sebagai blocker.
5. Pastikan status reference menunjukkan baris yang tersimpan untuk tenant trial.

Jangan mengganti ID reference dengan tebakan. `error_code` bisnis dari reference
dicatat apa adanya setelah secret dan PII dihapus.

## Gate 3 - Satu Biodata

1. Salin `01-biodata.xlsx` ke file kerja.
2. Ganti seluruh placeholder dengan satu data uji yang disetujui kampus.
3. Jangan mengisi `id_mahasiswa` untuk insert baru.
4. Upload ke tenant trial dan tunggu parsing selesai.
5. Perbaiki seluruh error; warning harus ditinjau operator.
6. Jalankan dry-run dan pastikan hanya satu kandidat `InsertBiodataMahasiswa`.
7. Operator meminta approval eksplisit atas fingerprint dry-run.
8. Kirim batch kecil satu record.
9. Catat `error_code`, `error_desc` tersanitasi, dan `id_mahasiswa`.
10. Baca kembali record pada Neo Feeder dan simpan bukti yang sudah dimasking.

PASS membutuhkan response sukses dan ID hasil yang dapat diverifikasi. Sukses
tanpa ID, response tidak lengkap, timeout, atau HTTP error setelah POST berarti
`Perlu pemeriksaan` dan tidak boleh dikirim ulang.

## Gate 4 - Satu Riwayat Pendidikan

1. Salin `02-riwayat.xlsx` ke file kerja baru.
2. Isi `id_mahasiswa` dari hasil Gate 3 yang telah diverifikasi.
3. Isi prodi, semester, dan jenis pendaftaran dari reference tenant trial.
4. Jangan mengisi `id_registrasi_mahasiswa` untuk insert baru.
5. Upload, validasi, dry-run, dan approval sebagai batch terpisah.
6. Kirim satu record setelah Gate 3 dinyatakan PASS.
7. Catat dan verifikasi `id_registrasi_mahasiswa` serta relasinya ke mahasiswa.

Jika Gate 3 belum PASS, Gate 4 tidak boleh dijalankan karena ID induk belum
terpercaya.

## Gate 5 - Discovery SIAKAD Read-Only

Jalankan hanya setelah jalur Excel lulus. Kampus menyediakan akun database
khusus dengan hak minimum `SELECT`, bukan akun aplikasi atau root.

1. Catat engine, host private, port, database, dan pemilik koneksi.
2. Daftarkan koneksi tanpa memilih tabel/kolom terlebih dahulu.
3. Jalankan schema discovery dan tunggu status `ready`.
4. Periksa primary key, candidate relation, tipe data, nullable, dan sample yang
   sudah dimasking.
5. Pilih satu tabel dan kolom terbatas untuk snapshot, maksimal 2.000 baris.
6. Buat mapping profile untuk biodata saja dan jalankan preview/staging.
7. Bandingkan hasil mapping database dengan workbook Gate 3.
8. Catat kandidat kolom timestamp, primary key, dan aturan perubahan/delete yang
   dijelaskan pemilik SIAKAD.

Jangan mengaktifkan mode incremental hanya karena nama kolom terlihat seperti
`updated_at`. Dibutuhkan bukti bahwa nilainya monoton/terpercaya, primary key
stabil, dan kebijakan delete diketahui.

## Stop Conditions Dan Rollback

- Credential salah atau endpoint tidak sesuai: hentikan dan koreksi koneksi.
- Dictionary berbeda: hentikan POST sampai contract diperbarui dan ditinjau.
- Reference tidak ditemukan/ambigu: perbaiki reference atau mapping, bukan
  menebak ID.
- Error validasi: buat revisi batch; jangan mengubah batch yang sudah memiliki
  attempt.
- `Perlu pemeriksaan`/unknown: hentikan record, cek Neo Feeder, dan jangan retry.
- Data uji ternyata produksi atau melebihi scope: hentikan trial dan hapus akses.
- Worker/scheduler tidak sehat: pause schedule dan selesaikan operasional dahulu.

Rollback aplikasi menggunakan image/commit sebelumnya dan migration sesuai
prosedur deploy. Rollback tidak membatalkan data yang sudah terkirim ke Neo
Feeder; data remote harus direkonsiliasi berdasarkan ID hasil.

## Exit Criteria

Pilot boleh ditutup dengan keputusan `PASS` hanya jika:

- Gate 0 sampai Gate 4 memiliki evidence register lengkap.
- Satu biodata dan satu riwayat dapat dibaca kembali dengan ID yang benar.
- Tidak ada attempt berstatus `unknown` yang belum direkonsiliasi.
- Payload, response, approval, dan audit dapat ditelusuri tanpa membuka secret.
- Mapping database, jika diuji, menghasilkan staging yang setara dengan alur Excel.
- Pemilik kampus menyetujui scope kanal berikutnya dan jadwal tindak lanjut.

Keputusan `PASS` ini hanya berlaku untuk tenant, endpoint, contract, dan data
uji yang dicatat. Itu bukan sertifikasi semua kampus atau semua kanal Neo Feeder.
