# Mapping SIAKAD berbasis file

## Cakupan awal

Halaman `/mapping` mendukung CSV UTF-8 dan XLSX untuk biodata mahasiswa serta
riwayat pendidikan. Ini fondasi Phase 2 berbasis file. Koneksi database/API SIAKAD,
penemuan relasi otomatis, schedule dan incremental sync masih backlog.

Alur operator:

1. Pilih kampus dan upload sumber CSV/XLSX atau pilih sumber tersimpan.
2. Pilih pemisah CSV (koma/titik koma/tab) atau nama sheet XLSX. Kosong berarti sheet pertama.
3. Beri nama profil, pilih kanal, petakan kolom sumber atau isi nilai tetap.
4. Pilih normalisasi: spasi, tanggal `dd/mm/yyyy`, angka tanggal Excel, atau gender `L/P`.
5. Simpan profil. Perubahan berikutnya menghasilkan versi baru yang tidak menimpa versi lama.
6. Preview memvalidasi semua baris dan menampilkan sepuluh contoh yang bisa diperiksa.
7. Buat batch validasi. Batch terbuka pada Import Batch untuk perbaikan/dry-run/approval.

Maksimal 2 MB, 2.000 baris data, dan 64 kolom per file. Sheet berlebih ditolak,
bukan dipotong diam-diam. Header harus unik/terisi. Formula ditolak; NIK/NIM dengan
nol awal harus berupa teks pada file Excel. Daftar sumber/profil memuat 100 terbaru.

## Data dan konsistensi

- Snapshot sumber tersimpan di `source_connections`, terisolasi per tenant.
  Aplikasi menyimpan isi sel dan hash file; file upload asli tidak disimpan terpisah.
- `mapping_profiles` menunjuk versi terkini; aturan historis ada di `mapping_profile_versions`.
- Preview terikat pada sumber, versi, dan contract. Versi/hash lama ditolak.
- Satu sumber + versi membuat satu batch; panggilan stage berulang mengembalikan batch sama.
- Staging menyimpan asal file/sheet/baris/versi di `source_lineage`, terlihat pada detail baris.
- Validator dan mesin dry-run Phase 1 dipakai kembali. Tanggal kalender tidak valid ditolak.
- Preview menyamarkan NIK/NPWP/telepon. Nilai staging tetap utuh.
- Tidak ada job/request Neo Feeder dari upload, simpan profil, preview, atau staging.

Referensi kampus perlu sudah tersimpan agar validasi referensi bisa lulus. Gunakan
referensi demo untuk latihan; ID riwayat pendidikan nyata tetap harus diverifikasi
saat akses trial tersedia. Mengubah mapping memerlukan preview baru; persetujuan
pengiriman tetap dilakukan pada Import Batch.

## Validasi lokal

Test API mencakup CSV/XLSX, normalisasi tanggal/gender, nilai tetap, nol awal,
biodata/riwayat, versi immutable, penolakan data stale/lintas tenant, staging ulang,
lineage, formula/header ganda/batas file, dan tidak adanya request/job outbound.
Build dan lint frontend dijalankan. Browser memverifikasi formulir dan pilihan
kampus pada desktop/mobile. Upload melalui Browser ditolak kontrol izin sesi;
alur browser upload → preview → staging belum diverifikasi.

Hasil akhir lokal: **84 test backend / 467 assertion**, **11 test frontend**,
build/lint lulus. Migration mapping juga dijalankan pada MySQL 8.4.3 dalam suite
integrasi delivery + backup/restore (**6 test / 73 assertion**).
