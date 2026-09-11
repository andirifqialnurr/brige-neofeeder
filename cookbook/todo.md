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
- [ ] Implement pembanding contract internal vs `GetDictionary`.

## 1.2 Reference Sync

- [ ] Implement sync `GetProfilPT`.
- [ ] Implement sync `GetProdi`.
- [ ] Implement sync `GetSemester`.
- [ ] Implement sync `GetAgama`.
- [ ] Implement sync `GetNegara`.
- [ ] Implement sync `GetWilayah`.
- [ ] Implement sync `GetJenisTinggal`.
- [ ] Implement sync `GetAlatTransportasi`.
- [ ] Implement sync `GetJenisPendaftaran`.
- [ ] Implement sync `GetJalurMasuk`.
- [ ] Implement sync `GetPembiayaan`.
- [ ] Implement sync `GetStatusMahasiswa`.
- [ ] Implement sync `GetJenisKeluar`.
- [ ] Implement sync `GetJenisEvaluasi`.
- [ ] Implement sync `GetKategoriKegiatan`.
- [ ] Implement sync `GetBasisEvaluasi`.
- [ ] Implement sync `GetListDosen`.
- [ ] Implement sync `GetListPenugasanDosen`.
- [ ] Implement sync `GetListMataKuliah`.
- [ ] Implement sync `GetListKelasKuliah`.
- [ ] Buat UI status referensi: last refresh, total rows, failed endpoint.

## 1.3 Template Excel Generator

- [ ] Buat service generate workbook.
- [ ] Buat sheet `README`.
- [ ] Buat sheet referensi per lookup.
- [ ] Buat sheet `mahasiswa_biodata`.
- [ ] Buat sheet `mahasiswa_riwayat_pendidikan`.
- [ ] Buat sheet `mata_kuliah`.
- [ ] Buat sheet `kurikulum`.
- [ ] Buat sheet `matkul_kurikulum`.
- [ ] Buat sheet `kelas_kuliah`.
- [ ] Buat sheet `dosen_pengajar_kelas`.
- [ ] Buat sheet `peserta_kelas`.
- [ ] Buat sheet `nilai_perkuliahan`.
- [ ] Buat sheet `perkuliahan_mahasiswa_akm`.
- [ ] Buat sheet `mahasiswa_lulus_do`.
- [ ] Tandai kolom wajib.
- [ ] Tambahkan notes format tanggal `yyyy-mm-dd`.
- [ ] Tambahkan dropdown untuk referensi kecil.
- [ ] Tambahkan template version dan generated timestamp.

## 1.4 Upload And Staging

- [ ] Buat endpoint upload workbook.
- [ ] Validasi file type dan ukuran.
- [ ] Simpan uploaded file metadata.
- [ ] Buat `import_batch`.
- [ ] Parse workbook di worker.
- [ ] Validasi sheet wajib.
- [ ] Simpan raw row.
- [ ] Simpan normalized row.
- [ ] Buat status per row: pending, valid, invalid, ready, syncing, success, failed, skipped.

## 1.5 Validation Engine

- [ ] Required field validation.
- [ ] Empty string/null normalization.
- [ ] Date format validation.
- [ ] Numeric validation.
- [ ] Character length validation.
- [ ] Enum validation.
- [ ] Reference existence validation.
- [ ] Duplicate row validation.
- [ ] Dependency validation antar sheet.
- [ ] Ambiguous reference validation.
- [ ] Severity: error, warning, info.
- [ ] UI error per sheet, row, dan field.

## 1.6 Dry-Run

- [ ] Tampilkan ringkasan valid/invalid/warning.
- [ ] Tampilkan dependency order.
- [ ] Tampilkan payload preview per row.
- [ ] Tampilkan calon insert/update/skip.
- [ ] Tampilkan missing references.
- [ ] Butuh approval operator sebelum sync.

## 1.7 Sync To Neo Feeder

- [ ] Buat sync batch.
- [ ] Enqueue record sync sesuai dependency graph.
- [ ] Refresh token saat perlu.
- [ ] Post satu record per attempt.
- [ ] Simpan raw request.
- [ ] Simpan raw response.
- [ ] Simpan `error_code` dan `error_desc`.
- [ ] Simpan ID hasil Neo Feeder.
- [ ] Implement retry untuk network/timeout/token.
- [ ] Jangan retry otomatis untuk business error.
- [ ] Buat UI retry manual.
- [ ] Buat UI batch progress.

## 1.8 Trial VPS

- [ ] Deploy stack awal ke VPS.
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
