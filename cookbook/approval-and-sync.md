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

## Pengiriman Dan Retry

Start dan retry memakai transaksi dengan row lock pada batch. Setiap intent
pengiriman memiliki unique `idempotency_key`; satu attempt hanya boleh mempunyai
satu anak retry (`retry_of` unique). Start ulang memakai intent antrean yang
tersimpan, bukan membuat record pengiriman baru. Keamanan ini berlaku dalam
satu batch/record, bukan deduplikasi data orang yang sama di dua batch berbeda.

Worker mengklaim attempt secara atomik. Payload tersimpan dibandingkan dengan
data yang disetujui; token tidak disimpan dalam request payload. Duplikat job
tidak mengulang POST yang sudah diklaim/selesai. Penulisan `request_started_at`
menjadi batas atomik sebelum POST: worker yang klaimnya sudah dibatalkan tidak
bisa melewati batas tersebut.

Job diurutkan menurut dependency graph dan dijalankan sebagai chain. Guard
dependensi juga memeriksa kanal induk: menunggu yang masih aktif, menolak yang
gagal. Aturan ini konservatif pada tingkat kanal, bukan pencocokan per relasi
record. Pengisian/resolusi ID antar-entitas tetap perlu trial Neo Feeder.

| Keadaan | Hasil | Retry |
| --- | --- | --- |
| Gangguan jaringan saat GetToken | retrying, maksimal 3 eksekusi | Otomatis, sebelum POST data |
| Credential/koneksi tidak valid | failed | Manual setelah diperbaiki |
| Error bisnis dengan respons eksplisit | failed | Manual untuk attempt terakhir |
| Dependensi belum berhasil | failed/dependency_blocked | Setelah kanal induk berhasil |
| Timeout/HTTP error setelah POST dimulai | unknown | Diblokir, cek hasil Neo Feeder |
| Respons tanpa error_code atau insert tanpa ID | unknown | Diblokir, cek hasil Neo Feeder |
| Berhasil, aktif, atau attempt lama | status dipertahankan | Tidak boleh retry baru |

Tidak ada klaim exactly-once di Neo Feeder: tanpa idempotency key yang didukung
server tujuan, hasil ambigu harus direkonsiliasi, bukan diasumsikan gagal.
Rekonsiliasi/override untuk unknown belum tersedia di UI dan tidak dilakukan
otomatis. Jangan membuat ulang data tersebut sebelum hasil remote diperiksa.

`bridge:recover-sync` dijadwalkan tiap menit. Klaim syncing berusia 10 menit
ditutup secara konservatif: unknown jika sudah melewati batas POST, failed
sebelum POST. Antrean tersimpan yang tidak bergerak 2 menit dapat dijadwalkan
ulang; guard worker tetap berlaku. Tenant demo dilewati. Worker timeout 75 detik
berada di bawah retry_after queue 90 detik. Scheduler dan worker wajib berjalan.

Deployment membawa migration kedua untuk metadata attempt dan waktu start.
Intent lama tanpa approval_hash tidak otomatis diizinkan; tinjau antrean lama
dan hentikan worker versi lama saat upgrade. Pengujian lokal memakai SQLite dan
HTTP/queue fakes; uji konkurensi proses MySQL/Redis dan trial remote masih perlu
dilakukan di lingkungan terisolasi. Fingerprint seluruh batch diperiksa ulang
untuk keamanan; performa batch besar belum diuji.
