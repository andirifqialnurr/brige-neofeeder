# PRD - Bridge Neo Feeder

## 1. Ringkasan

Bridge Neo Feeder adalah aplikasi perantara antara data akademik kampus dan Neo Feeder PDDIKTI. Aplikasi ini membantu kampus menyiapkan, memvalidasi, dan mengirim data akademik ke Neo Feeder dengan dua mode:

- Phase 1: export/import Excel berbasis template standar Neo Feeder.
- Phase 2: otomatisasi dari sumber data SIAKAD/API/database kampus yang struktur datanya bisa berbeda-beda.

Produk ini tidak hanya menjadi form upload. Nilai utamanya adalah contract layer, validasi, staging, mapping, dry-run, audit log, dan kontrol sinkronisasi agar data yang dikirim ke Neo Feeder bisa ditelusuri dan diperbaiki sebelum masuk ke sistem resmi.

## 2. Latar Belakang

Neo Feeder menyediakan Web Service PDDIKTI dengan endpoint POST umum dan banyak aksi seperti `GetToken`, `GetDictionary`, `GetProdi`, `InsertBiodataMahasiswa`, `InsertRiwayatPendidikanMahasiswa`, `InsertKelasKuliah`, `UpdateNilaiPerkuliahanKelas`, dan lain-lain.

Dokumen Web Service menjelaskan bahwa data dari sistem kampus harus melalui mapping agar sesuai dengan standar PDDIKTI. Dokumen guide WS Neo Feeder menunjukkan bahwa struktur data sangat bergantung pada:

- field wajib dan tidak wajib,
- tipe data,
- kode referensi,
- UUID internal Neo Feeder,
- primary key,
- urutan operasi,
- response `error_code`, `error_desc`, dan `data`.

Karena struktur SIAKAD tiap kampus bisa berbeda, aplikasi harus membangun standar internal berdasarkan struktur Neo Feeder lebih dulu. Setelah itu, Excel upload dan otomatisasi SIAKAD memakai fondasi yang sama.

## 3. Tujuan Produk

Tujuan utama:

- Menyediakan template Excel standar berbasis kanal data Neo Feeder.
- Memvalidasi data Excel sebelum dikirim ke Neo Feeder.
- Menyimpan staging data, status validasi, status pengiriman, dan audit log.
- Menarik dan menyimpan referensi dari Neo Feeder seperti prodi, semester, agama, wilayah, status mahasiswa, jenis pendaftaran, jenis keluar, dosen, mata kuliah, dan kelas.
- Mengirim data ke Neo Feeder secara bertahap, aman, dan dapat diulang.
- Menjadi dasar otomatisasi integrasi SIAKAD kampus melalui mapping profile per kampus.

Tujuan jangka panjang:

- Mendukung banyak kampus sebagai tenant.
- Mendukung banyak variasi struktur SIAKAD.
- Mendukung koneksi database/API/file drop dari kampus.
- Menjalankan sinkronisasi otomatis terjadwal setelah mapping tervalidasi.
- Menyediakan monitoring, retry, dan rekonsiliasi data.

## 4. Non-Goals Awal

Yang tidak menjadi target awal:

- Menggantikan SIAKAD kampus.
- Membuat semua 199 operasi Web Service sebagai halaman manual sejak awal.
- Mengubah data langsung ke Neo Feeder tanpa staging dan preview.
- Mengelola data dosen dari nol, karena dokumen WS lebih banyak menyediakan dosen sebagai data baca/referensi.
- Menyediakan otomatisasi penuh untuk semua kampus tanpa fase learning/mapping.

## 5. Pengguna

### Admin Platform

Mengelola tenant kampus, konfigurasi koneksi Neo Feeder, versi template, dan monitoring global.

### Operator Kampus

Mengunduh template Excel, mengisi data, upload file, membaca hasil validasi, memperbaiki error, dan menjalankan pengiriman ke Neo Feeder.

### Tim Integrasi

Mempelajari struktur SIAKAD kampus, membuat mapping profile, menguji dry-run, dan mengaktifkan otomatisasi.

### Auditor/Internal Kampus

Melihat riwayat import, perubahan data, request/response Neo Feeder, error, dan status sinkronisasi.

## 6. Mode Produk

## 6.1 Phase 1 - Export/Import Excel

Phase 1 adalah fondasi MVP.

### Sasaran

- Kampus bisa mengunduh template Excel sesuai struktur Neo Feeder.
- Kampus mengisi template secara mandiri.
- Aplikasi memvalidasi file sebelum pengiriman.
- Operator bisa melihat error per sheet dan per baris.
- Data valid bisa dikirim ke Neo Feeder melalui queue.
- Response Neo Feeder disimpan lengkap.

### Alur Utama

1. Admin memasukkan konfigurasi Neo Feeder kampus.
2. Sistem melakukan test koneksi dan `GetToken`.
3. Sistem menarik referensi Neo Feeder.
4. Operator memilih periode/prodi/kanal yang ingin diisi.
5. Sistem generate template Excel.
6. Operator mengisi Excel.
7. Operator upload Excel.
8. Sistem membaca file ke staging.
9. Sistem menjalankan validasi field, tipe, referensi, dan dependency.
10. Operator melihat dry-run.
11. Operator memperbaiki error atau melanjutkan data valid.
12. Sistem mengirim data ke Neo Feeder sesuai urutan kanal.
13. Sistem menyimpan response dan ID hasil Neo Feeder.
14. Operator melihat ringkasan sukses/gagal dan bisa retry baris gagal.

### Kanal Prioritas Phase 1

Kanal minimal:

- referensi Neo Feeder,
- biodata mahasiswa,
- riwayat pendidikan mahasiswa,
- mata kuliah,
- kurikulum,
- mata kuliah kurikulum,
- kelas kuliah,
- peserta kelas kuliah,
- dosen pengajar kelas kuliah,
- nilai perkuliahan kelas,
- aktivitas kuliah mahasiswa/AKM,
- mahasiswa lulus/DO.

Kanal tambahan setelah MVP stabil:

- nilai transfer,
- substansi kuliah,
- rencana pembelajaran,
- rencana evaluasi,
- aktivitas mahasiswa,
- anggota aktivitas,
- bimbing mahasiswa,
- uji mahasiswa,
- prestasi mahasiswa,
- transkrip mahasiswa,
- skala nilai prodi.

### Acceptance Criteria Phase 1

- Template Excel bisa dibuat dari contract internal.
- Upload Excel menghasilkan staging records.
- Validasi menandai error wajib, format, referensi, dan dependency.
- Dry-run menampilkan payload Neo Feeder yang akan dikirim.
- Pengiriman berjalan lewat worker/queue, bukan request web langsung.
- Response sukses menyimpan ID seperti `id_mahasiswa`, `id_registrasi_mahasiswa`, `id_matkul`, atau `id_kelas_kuliah`.
- Response gagal menyimpan `error_code`, `error_desc`, payload request, dan payload response.
- Retry tidak membuat duplikasi jika record sudah punya identity Neo Feeder.

## 6.2 Phase 2 - Otomatisasi SIAKAD

Phase 2 memakai fondasi Phase 1, terutama schema contract, validator, resolver, staging, queue, dan audit log.

### Sasaran

- Sistem bisa mempelajari struktur data kampus.
- Tim integrasi bisa membuat mapping profile per kampus.
- Data dari SIAKAD bisa masuk staging otomatis.
- Validasi dan dry-run tetap sama seperti mode Excel.
- Sinkronisasi bisa dijalankan manual atau terjadwal.

### Sumber Data Yang Didukung Bertahap

Prioritas awal:

- file CSV/Excel export dari SIAKAD,
- database read-only,
- API kampus.

Prioritas berikutnya:

- SFTP/file drop,
- webhook,
- incremental sync berbasis timestamp,
- event-driven sync jika SIAKAD mendukung.

### Alur Learning Kampus

1. Kampus menyediakan contoh data terbatas.
2. Sistem/tim membaca daftar tabel, kolom, tipe data, sample value, dan relasi.
3. Sistem membuat source profile.
4. Tim memetakan source field ke Neo Feeder contract field.
5. Tim menentukan transform rule.
6. Sistem menjalankan sample import ke staging.
7. Validator menghasilkan error/warning.
8. Mapping diperbaiki sampai data lolos dry-run.
9. Sinkronisasi pilot dijalankan untuk batch kecil.
10. Setelah stabil, jadwal otomatis diaktifkan.

### Acceptance Criteria Phase 2

- Mapping profile bisa dibuat dan di-versioning.
- Source connector bisa membaca data tanpa mengubah database kampus.
- Transform rule bisa diuji dengan sample.
- Hasil transform masuk staging dengan format sama seperti Excel.
- Dry-run dan pengiriman tetap memakai pipeline Phase 1.
- Sinkronisasi terjadwal bisa dimatikan, dijeda, dan diulang.
- Setiap record punya lineage: sumber, mapping version, transform output, request Neo Feeder, response Neo Feeder.

## 7. Prinsip Data

- Neo Feeder adalah target contract utama.
- SIAKAD kampus adalah source yang harus dipetakan, bukan standar sistem.
- Semua data masuk staging sebelum dikirim.
- Tidak ada post langsung tanpa validasi dan dry-run.
- Semua referensi harus ditarik dari Neo Feeder kampus terkait.
- UUID hasil Neo Feeder harus disimpan untuk update/delete berikutnya.
- Error harus bisa ditelusuri sampai sheet, baris, field, payload, dan response.

## 8. Modul Produk

### Tenant Kampus

- data kampus,
- konfigurasi URL Neo Feeder,
- kredensial terenkripsi,
- status koneksi,
- daftar prodi aktif.

### Neo Feeder Contract

- daftar kanal,
- operasi read/insert/update/delete,
- field schema,
- referensi,
- validasi,
- dependency graph,
- payload builder.

### Reference Sync

- tarik referensi dari Neo Feeder,
- cache per tenant,
- refresh manual,
- refresh terjadwal,
- deteksi perubahan referensi.

### Template Excel

- generate workbook,
- sheet data,
- sheet referensi,
- kolom wajib,
- instruksi format,
- versioning template.

### Import Staging

- upload file,
- parsing sheet,
- normalisasi cell,
- status per row,
- grouping batch.

### Validation Engine

- required check,
- type check,
- max length,
- date format,
- enum,
- reference existence,
- dependency check,
- duplicate check,
- business warning.

### Sync Engine

- build payload,
- queue job,
- send request,
- retry,
- response handler,
- identity write-back,
- audit log.

### Mapping Automation

- source connection,
- schema discovery,
- source profile,
- mapping profile,
- transform rules,
- scheduled sync.

## 9. Kebutuhan Keamanan

- Kredensial Neo Feeder harus dienkripsi di database.
- Token tidak ditampilkan penuh di UI.
- Audit log mencatat siapa melakukan upload, validasi, post, retry, dan perubahan mapping.
- Role operator dibatasi per tenant kampus.
- File upload divalidasi ukuran, tipe, dan struktur sheet.
- Payload request/response yang mengandung data pribadi perlu akses terbatas.
- Export log harus mempertimbangkan masking data sensitif seperti NIK, NPWP, dan nomor kontak.

## 10. Kebutuhan Operasional

- Bisa dijalankan di VPS dengan Docker Compose.
- Bisa memakai token trial Neo Feeder.
- Mendukung environment development, staging, dan production.
- Worker harus bisa restart tanpa kehilangan job.
- Job harus idempotent sejauh mungkin.
- Ada halaman monitoring untuk queue, batch, error, dan retry.

## 11. Metrik Sukses

Phase 1:

- jumlah file berhasil divalidasi,
- jumlah record valid/gagal,
- waktu import sampai dry-run,
- jumlah record sukses terkirim,
- jumlah error Neo Feeder per kanal,
- jumlah retry sukses.

Phase 2:

- jumlah source profile kampus dibuat,
- waktu membuat mapping awal,
- akurasi mapping sample,
- jumlah sync otomatis sukses,
- jumlah intervensi manual per batch.

## 12. Risiko

- Struktur dokumen WS bisa berbeda dengan versi Neo Feeder yang dipakai kampus.
- Kampus mungkin punya data tidak lengkap atau format tidak konsisten.
- Kode/nama referensi lokal tidak selalu cocok dengan referensi Neo Feeder.
- Salah mapping UUID dapat menyebabkan data masuk ke entitas yang salah.
- Operasi update/delete butuh identity Neo Feeder yang harus disimpan.
- Batch besar rawan timeout jika tidak memakai worker dan queue.

## 13. Strategi Mitigasi

- Gunakan `GetDictionary` untuk membandingkan contract runtime dengan contract internal.
- Selalu tarik referensi dari tenant kampus sebelum template/dry-run.
- Gunakan staging dan dry-run untuk semua mode.
- Simpan identity map antara data lokal/staging dan UUID Neo Feeder.
- Terapkan retry terbatas dan manual review untuk error berulang.
- Mulai otomatisasi dari satu kampus pilot.

## 14. Open Questions

- Apakah target pertama hanya satu kampus atau langsung multi-tenant?
- Kanal Phase 1 mana yang wajib masuk MVP pertama: mahasiswa saja, atau sampai kelas/nilai?
- Apakah kampus akan mengisi ID Neo Feeder langsung di Excel, atau cukup kode/nama lalu sistem resolve?
- Format deployment trial VPS akan memakai domain publik, VPN, atau akses internal?
- Apakah token trial punya data dummy yang aman untuk post eksperimen?
