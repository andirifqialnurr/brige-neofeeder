# Snapshot sumber database SIAKAD

Mapping menerima snapshot MySQL read-only melalui `POST /api/mapping/sources/database`.
Operator memilih host, port, database, tabel, dan daftar kolom secara eksplisit.
Query yang dijalankan hanya `SELECT` dengan batas 2.000 baris; nama tabel dan
kolom memakai allowlist identifier sehingga tidak dapat menjadi SQL bebas.

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

Konfigurasi koneksi disimpan terenkripsi pada `source_connections` dan tidak
dikembalikan pada endpoint workspace. Gunakan akun database khusus baca dengan
hak minimum. Snapshot tetap terisolasi per tenant dan masuk ke alur mapping,
preview, staging, serta audit yang sama dengan file CSV/XLSX.

Pengujian lokal memakai PDO SQLite sebagai test double untuk query read-only dan
memastikan identifier berbahaya ditolak sebelum koneksi dibuat. Koneksi MySQL
SIAKAD kampus, hak akun, TLS, dan performa tabel aktual harus diverifikasi pada
lingkungan kampus sebelum dijadwalkan atau dipakai mengirim data ke Neo Feeder.
