# TODO - Bridge Neo Feeder

## Phase 0 - Foundation

- [x] Buat struktur project: `frontend`, `backend`, `cookbook`.
- [x] Setup React + Vite untuk frontend.
- [x] Setup Laravel untuk backend API, queue worker, dan scheduler.
- [x] Setup MySQL/MariaDB, Redis, dan Docker Compose.
- [x] Setup Laravel migrations dan Eloquent models.
- [x] Setup lint, format, dan test runner untuk frontend/backend.
- [x] Buat environment template `.env.example`.
- [x] Buat modul auth dasar.
- [x] Buat modul tenant kampus.
- [x] Buat penyimpanan credential Neo Feeder terenkripsi.
- [x] Buat Neo Feeder HTTP client dasar.
- [x] Implement `GetToken` dan test connection.
- [x] Definisikan response parser `error_code`, `error_desc`, `data`.
- [x] Buat audit log dasar.

## UI Foundation - shadcn Dashboard

- [x] Setup Tailwind CSS di frontend.
- [x] Setup shadcn/ui dan path alias.
- [x] Setup theme token light/dark.
- [x] Pindahkan shared UI ke struktur shadcn-style.
- [x] Reslice dashboard awal sesuai `design-system.md`.
- [x] Tambahkan layout shell: sidebar, topbar, page header.
- [x] Tambahkan komponen dashboard: metric card, status badge, workspace panel.
- [x] Tambahkan table primitive untuk list data.
- [x] Tambahkan empty, loading, dan error state primitive.
- [x] Tambahkan landing page publik modern.
- [x] Refinement landing page dengan curved product canvas.
- [x] Tambahkan konten landing page untuk fitur, workflow, dan roadmap otomatisasi.
- [x] Tambahkan halaman login setema dashboard.
- [x] Validasi sesi login tersimpan via `/auth/me`.
- [x] Validasi build/lint frontend.

## Phase 1 - Export/Import Excel

## 1.1 Contract Layer

- [x] Buat struktur `ChannelContract`.
- [x] Buat struktur `FieldContract`.
- [x] Buat struktur `OperationContract`.
- [x] Buat dependency graph kanal.
- [x] Masukkan contract awal untuk referensi Neo Feeder.
- [x] Masukkan contract awal untuk biodata mahasiswa.
- [x] Masukkan contract awal untuk riwayat pendidikan mahasiswa.
- [x] Masukkan contract awal untuk mata kuliah.
- [x] Masukkan contract awal untuk kurikulum.
- [x] Masukkan contract awal untuk mata kuliah kurikulum.
- [x] Masukkan contract awal untuk kelas kuliah.
- [x] Masukkan contract awal untuk peserta kelas kuliah.
- [x] Masukkan contract awal untuk dosen pengajar kelas kuliah.
- [x] Masukkan contract awal untuk nilai perkuliahan kelas.
- [x] Masukkan contract awal untuk AKM/perkuliahan mahasiswa.
- [x] Masukkan contract awal untuk mahasiswa lulus/DO.
- [x] Buat payload builder generic read/list.
- [x] Buat payload builder generic insert.
- [x] Buat payload builder generic update.
- [x] Buat payload builder generic delete.
- [x] Buat override payload builder untuk operasi yang memakai `record object[]`.
- [x] Implement pembanding contract internal vs `GetDictionary`.

## 1.2 Reference Sync

- [x] Implement sync `GetProfilPT`.
- [x] Implement sync `GetProdi`.
- [x] Implement sync `GetSemester`.
- [x] Implement sync `GetAgama`.
- [x] Implement sync `GetNegara`.
- [x] Implement sync `GetWilayah`.
- [x] Implement sync `GetJenisTinggal`.
- [x] Implement sync `GetAlatTransportasi`.
- [x] Implement sync `GetJenisPendaftaran`.
- [x] Implement sync `GetJalurMasuk`.
- [x] Implement sync `GetPembiayaan`.
- [x] Implement sync `GetStatusMahasiswa`.
- [x] Implement sync `GetJenisKeluar`.
- [x] Implement sync `GetJenisEvaluasi`.
- [x] Implement sync `GetKategoriKegiatan`.
- [x] Implement sync `GetBasisEvaluasi`.
- [x] Implement sync `GetListDosen`.
- [x] Implement sync `GetListPenugasanDosen`.
- [x] Implement sync `GetListMataKuliah`.
- [x] Implement sync `GetListKelasKuliah`.
- [x] Buat UI status referensi: last refresh, total rows, failed endpoint.

## 1.3 Template Excel Generator

- [x] Buat service generate workbook.
- [x] Buat sheet `README`.
- [x] Buat sheet referensi per lookup.
- [x] Buat sheet `mahasiswa_biodata`.
- [x] Buat sheet `mahasiswa_riwayat_pendidikan`.
- [x] Buat sheet `mata_kuliah`.
- [x] Buat sheet `kurikulum`.
- [x] Buat sheet `matkul_kurikulum`.
- [x] Buat sheet `kelas_kuliah`.
- [x] Buat sheet `dosen_pengajar_kelas`.
- [x] Buat sheet `peserta_kelas`.
- [x] Buat sheet `nilai_perkuliahan`.
- [x] Buat sheet `perkuliahan_mahasiswa_akm`.
- [x] Buat sheet `mahasiswa_lulus_do`.
- [x] Tandai kolom wajib.
- [x] Tambahkan notes format tanggal `yyyy-mm-dd`.
- [x] Tambahkan dropdown untuk referensi kecil.
- [x] Tambahkan template version dan generated timestamp.
- [x] Buat endpoint download template Excel.
- [x] Sambungkan UI download template Excel.

## 1.4 Upload And Staging

- [x] Buat endpoint upload workbook.
- [x] Validasi file type dan ukuran.
- [x] Simpan uploaded file metadata.
- [x] Buat `import_batch`.
- [x] Parse workbook di worker.
- [x] Validasi sheet wajib.
- [x] Simpan raw row.
- [x] Simpan normalized row.
- [x] Buat status per row: pending, valid, invalid, ready, syncing, success, failed, skipped.
- [x] Buat endpoint list import batch.
- [x] Sambungkan UI upload dan list import batch.

## 1.5 Validation Engine

- [x] Required field validation.
- [x] Empty string/null normalization.
- [x] Date format validation.
- [x] Numeric validation.
- [x] Character length validation.
- [x] Enum validation.
- [x] Reference existence validation.
- [x] Duplicate row validation.
- [x] Dependency validation antar sheet.
- [x] Ambiguous reference validation.
- [x] Severity: error, warning, info.
- [x] UI error per sheet, row, dan field.

## 1.6 Dry-Run

- [x] Tampilkan ringkasan valid/invalid/warning.
- [x] Tampilkan dependency order.
- [x] Tampilkan payload preview per row.
- [x] Tampilkan calon insert/update/skip.
- [x] Tampilkan missing references.
- [x] Butuh approval operator sebelum sync.
- [x] Sambungkan UI pilih batch dan jalankan dry-run.

## 1.7 Sync To Neo Feeder

- [x] Buat sync batch.
- [x] Enqueue record sync sesuai dependency graph.
- [x] Refresh token saat perlu.
- [x] Post satu record per attempt.
- [x] Simpan raw request.
- [x] Simpan raw response.
- [x] Simpan `error_code` dan `error_desc`.
- [x] Simpan ID hasil Neo Feeder.
- [x] Implement retry untuk network/timeout/token.
- [x] Jangan retry otomatis untuk business error.
- [x] Buat UI retry manual.
- [x] Buat UI batch progress.

## 1.8 Trial VPS

- [x] Siapkan Docker Compose VPS untuk frontend, backend, worker, scheduler, MySQL, dan Redis.
- [x] Siapkan Dockerfile backend Laravel dengan PHP dan Composer di container.
- [x] Siapkan Dockerfile frontend React dengan Bun build dan Nginx static runtime.
- [x] Siapkan contoh Nginx reverse proxy host.
- [x] Deploy stack awal ke VPS.
- [ ] Buat tenant trial.
- [ ] Input credential/token trial.
- [ ] Test `GetToken`.
- [ ] Test `GetProdi`.
- [ ] Test reference sync kecil.
- [ ] Generate template mahasiswa.
- [ ] Upload 1 file sample.
- [ ] Dry-run 1 mahasiswa.
- [ ] Post `InsertBiodataMahasiswa`.
- [ ] Simpan `id_mahasiswa`.
- [ ] Post `InsertRiwayatPendidikanMahasiswa`.
- [ ] Simpan `id_registrasi_mahasiswa`.
- [ ] Dokumentasikan response aktual.

## Phase 2 - Otomatisasi SIAKAD

## 2.1 Source Discovery

- [ ] Buat model `source_connection`.
- [ ] Dukung source file CSV/Excel sebagai langkah awal.
- [ ] Dukung source database read-only.
- [ ] Dukung source API.
- [ ] Buat worker schema discovery.
- [ ] Simpan source tables.
- [ ] Simpan source fields.
- [ ] Simpan sample values.
- [ ] Deteksi tipe data awal.
- [ ] Deteksi candidate primary key.
- [ ] Deteksi candidate relation.

## 2.2 Mapping Profile

- [ ] Buat model `mapping_profile`.
- [ ] Buat versioning mapping.
- [ ] Buat UI mapping source field ke contract field.
- [ ] Dukung transform rule sederhana.
- [ ] Dukung constant value.
- [ ] Dukung lookup table lokal.
- [ ] Dukung concatenation/split sederhana.
- [ ] Dukung date parser.
- [ ] Dukung enum mapping, misalnya gender/status.
- [ ] Dukung reference resolver, misalnya kode prodi ke `id_prodi`.

## 2.3 Transform And Staging

- [ ] Extract source data ke snapshot.
- [ ] Transform source row menjadi normalized staging row.
- [ ] Simpan transform output.
- [ ] Simpan mapping version yang dipakai.
- [ ] Jalankan validator Phase 1.
- [ ] Tampilkan error/warning seperti upload Excel.
- [ ] Tampilkan source lineage per staging row.

## 2.4 Automation Sync

- [ ] Manual run untuk mapping profile.
- [ ] Scheduled run.
- [ ] Incremental strategy berbasis timestamp jika tersedia.
- [ ] Full refresh mode.
- [ ] Pause/resume schedule.
- [ ] Lock agar tidak ada dua sync tenant/kanal berjalan bersamaan.
- [ ] Alert jika error rate melewati threshold.
- [ ] Reconciliation report.

## 2.5 Pilot Kampus

- [ ] Pilih satu kampus pilot.
- [ ] Ambil sample data terbatas.
- [ ] Petakan mahasiswa dan riwayat pendidikan dulu.
- [ ] Petakan mata kuliah dan kelas.
- [ ] Petakan peserta kelas dan nilai.
- [ ] Jalankan dry-run.
- [ ] Jalankan post batch kecil.
- [ ] Evaluasi error dan perbaiki mapping.
- [ ] Baru aktifkan schedule.

## Hardening

- [ ] Masking NIK/NPWP/phone di UI.
- [ ] Access control per tenant.
- [ ] Audit access raw payload.
- [ ] File retention policy.
- [ ] Backup database.
- [ ] Export audit log.
- [ ] Rate limit per tenant.
- [ ] Timeout config per Neo Feeder connection.
- [ ] Circuit breaker jika Neo Feeder tidak stabil.
- [ ] Manual override untuk reference match ambiguous.

## Definition Of Done MVP

- [ ] Operator bisa login.
- [ ] Admin bisa membuat tenant kampus.
- [ ] Admin bisa test koneksi Neo Feeder.
- [ ] Sistem bisa refresh referensi utama.
- [ ] Operator bisa download template Excel.
- [ ] Operator bisa upload template terisi.
- [ ] Sistem bisa validasi dan menampilkan error per baris.
- [ ] Operator bisa melihat dry-run payload.
- [ ] Operator bisa approve sync.
- [ ] Worker bisa post data kecil ke Neo Feeder trial.
- [ ] Response dan ID hasil tersimpan.
- [ ] Retry manual tersedia.
- [ ] Audit log tersedia untuk flow utama.
