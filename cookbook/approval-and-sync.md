# Approval And Sync

## Persetujuan Operator

`POST /api/import-batches/:id/approve` memerlukan token aplikasi, akses tenant,
`confirmed: true`, dan `dry_run_hash` dari dry-run/detail terbaru. Persetujuan
belum mengirim data dan dapat diuji pada tenant demo tanpa credential Neo Feeder.

Hanya batch `dry_run_ready` yang tidak kosong dan seluruh barisnya valid dapat
disetujui. Warning tidak memblokir, tetapi perlu ditinjau operator. Batch invalid
tetap boleh di-dry-run untuk pemeriksaan, bukan untuk pengiriman parsial.

Fingerprint SHA-256 mencakup batch/tenant, versi template, seluruh contract,
baris asal/normalisasi, dan temuan validasi. Urutan key object dinormalisasi agar
serialisasi JSON MySQL tidak membatalkan persetujuan tanpa perubahan data.
Status runtime dan respons sync tidak menjadi bagian fingerprint.

`approved_hash`, `approved_by`, dan `approved_at` disimpan pada import batch.
Audit `import.sync.approved` menyimpan aktor dan fingerprint, tanpa menyalin PII.
Approval berulang untuk versi yang sama tidak membuat audit ganda.

Dry-run ulang, parsing, dan validasi ulang menghapus approval. Pengiriman/retry
memeriksa fingerprint aktual, bukan hanya flag frontend. Batch dengan riwayat
attempt tidak dapat diparse atau di-dry-run ulang; koreksi menggunakan batch baru.
Tidak ada approval otomatis untuk batch lama setelah migration.

Deployment membutuhkan migration Laravel sebelum backend/worker versi baru
berjalan. Halaman detail mendapat objek `approval` dan `is_demo` dari API.
Integrasi aktual tetap harus diverifikasi menggunakan Neo Feeder trial.
