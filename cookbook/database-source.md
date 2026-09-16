# Snapshot sumber database SIAKAD

Mapping menerima koneksi atau snapshot MySQL read-only melalui `POST
/api/mapping/sources/database`. Operator dapat mengirim host, port, database,
username, dan password tanpa tabel/kolom untuk mendaftarkan koneksi sebelum
discovery. Setelah katalog siap, operator mengirim tabel dan daftar kolom untuk
membuat snapshot mapping. Query snapshot hanya `SELECT` dengan batas 2.000
baris; nama tabel dan kolom memakai allowlist identifier sehingga tidak dapat
menjadi SQL bebas.

Discovery schema disimpan terpisah pada `source_schema_tables` dan
`source_schema_columns`. Katalog menyimpan nama/type tabel, estimasi jumlah
baris, primary key, candidate key, ordinal kolom, tipe data, nullable, unique,
foreign key, kandidat relasi, dan maksimal lima sample value per kolom. Sample
value yang berpotensi mengandung identitas seperti nama, NIK, NIM, email, ID,
alamat, nomor telepon, credential, dan token disimpan sebagai `[masked]`.

Primary key aktual diambil dari metadata database. Candidate key berarti primary
key tunggal non-null atau unique key tunggal non-null ketika database tidak
memberi primary key. Candidate relation berasal dari foreign key aktual atau
heuristik nama seperti `*_id`, `id_*`, `kode_*`, `nim`, `nip`, dan `nidn`; hasil
heuristik diberi confidence `name_pattern` dan tidak boleh dianggap relasi pasti.
Discovery dibatasi 200 tabel dan 128 kolom per tabel.

Discovery dijalankan asynchronous melalui `POST
/api/mapping/sources/{source}/discover-schema`. Endpoint mengembalikan status
`202` dan hanya dapat dipanggil oleh tenant pemilik source atau admin. Status
discovery yang tersedia adalah `idle`, `queued`, `discovering`, `pending`,
`ready`, dan `failed`. Job bersifat unique per source agar klik berulang tidak
membuat discovery paralel. Hasil katalog dibaca melalui `GET
/api/mapping/sources/{source}/schema`; endpoint ini hanya mengembalikan
metadata aman dan sample yang sudah dimasking, bukan `connection_config`.

Kegagalan retry pertama disimpan sebagai status `pending` dengan pesan umum.
Setelah retry terakhir, status menjadi `failed`. Detail exception tidak
dikirim ke API dan hanya tersedia di log server untuk troubleshooting internal.

Halaman Mapping menyediakan trigger discovery, polling status worker, daftar
tabel, detail kolom, candidate key/relation, dan sample value yang sudah
dimasking. Operator dapat memilih tabel dari katalog untuk mengisi pilihan
kolom snapshot berikutnya.

Snapshot lanjutan memakai `POST
/api/mapping/sources/{source}/snapshot` dengan `table` dan `columns`. Endpoint
ini membaca credential dari source yang tersimpan, memperbarui source yang
sama, dan tidak membuat credential atau source baru di client.

Snapshot yang sudah pernah dibuat dapat disegarkan asynchronous melalui `POST
/api/mapping/sources/{source}/refresh-snapshot`. Statusnya terpisah dari
discovery schema (`idle`, `queued`, `refreshing`, `pending`, `ready`, `failed`)
agar operator dapat membedakan schema siap dari data snapshot terbaru.
Setiap snapshot memiliki `snapshot_version`. Versi ini menjadi bagian dari
idempotency key mapping run: stage ulang pada snapshot yang sama mengembalikan
batch lama, sedangkan snapshot baru dapat membuat batch baru untuk profile yang
sama.

Schedule Phase 2 menjalankan mode `full` dengan urutan
`refresh snapshot -> preview -> stage` melalui queue worker. Mode `full`
membaca ulang seluruh snapshot dari tabel/kolom yang dipilih, dengan batas
2.000 baris yang sama seperti snapshot manual. Stage tetap idempotent untuk
snapshot yang tidak berubah sehingga schedule tidak membuat batch duplikat.
Endpoint schedule berada di `/api/automation/schedules`; pilihan frekuensi MVP
adalah `hourly`, `daily`, dan `weekly`. Schedule hanya dapat dibuat untuk
snapshot database yang sudah siap dan berisi baris. Tahap ini belum melakukan
POST ke Neo Feeder; outbound baru boleh ditambahkan setelah token, approval,
dan kontrak kampus pilot tersedia.

Mode incremental belum diaktifkan. Implementasinya harus menunggu bukti kolom
timestamp/primary key, aturan update/delete, dan watermark dari database
SIAKAD pilot agar tidak melewatkan atau menghapus data secara keliru.
Eksekusi schedule memakai lock `tenant + channel`; schedule lain pada kanal yang
sama menunggu di queue sampai proses sebelumnya selesai.

Setiap eksekusi schedule menyimpan run history. Dalam window tujuh hari, setelah
minimal tiga run selesai, sistem menghitung error rate dan menyalakan alert
operasional jika melewati `error_rate_threshold` (default 50%). Alert terlihat di
daftar schedule dan tercatat sebagai `automation.schedule_alert_triggered`;
notifikasi email/webhook belum diaktifkan.

Konfigurasi koneksi disimpan terenkripsi pada `source_connections` dan tidak
dikembalikan pada endpoint workspace. Gunakan akun database khusus baca dengan
hak minimum. Snapshot tetap terisolasi per tenant dan masuk ke alur mapping,
preview, staging, serta audit yang sama dengan file CSV/XLSX.

Pengujian lokal memakai PDO SQLite sebagai test double untuk query read-only dan
memastikan identifier berbahaya ditolak sebelum koneksi dibuat. Koneksi MySQL
SIAKAD kampus, hak akun, TLS, dan performa tabel aktual harus diverifikasi pada
lingkungan kampus sebelum dijadwalkan atau dipakai mengirim data ke Neo Feeder.
