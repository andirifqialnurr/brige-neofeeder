# Snapshot sumber database SIAKAD

Mapping menerima snapshot MySQL read-only melalui `POST /api/mapping/sources/database`.
Operator memilih host, port, database, tabel, dan daftar kolom secara eksplisit.
Query yang dijalankan hanya `SELECT` dengan batas 2.000 baris; nama tabel dan
kolom memakai allowlist identifier sehingga tidak dapat menjadi SQL bebas.

Konfigurasi koneksi disimpan terenkripsi pada `source_connections` dan tidak
dikembalikan pada endpoint workspace. Gunakan akun database khusus baca dengan
hak minimum. Snapshot tetap terisolasi per tenant dan masuk ke alur mapping,
preview, staging, serta audit yang sama dengan file CSV/XLSX.

Pengujian lokal memakai PDO SQLite sebagai test double untuk query read-only dan
memastikan identifier berbahaya ditolak sebelum koneksi dibuat. Koneksi MySQL
SIAKAD kampus, hak akun, TLS, dan performa tabel aktual harus diverifikasi pada
lingkungan kampus sebelum dijadwalkan atau dipakai mengirim data ke Neo Feeder.
