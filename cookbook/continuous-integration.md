# Continuous integration

`.github/workflows/ci.yml` berjalan pada push, pull request, dan pemicu manual.
Tiga job terpisah memeriksa frontend (frozen lockfile, lint/test/build), backend
(PHP 8.4, Pint, PHPUnit SQLite), serta integrasi (MySQL 8.4, Redis 7, HTTP simulator,
dump/restore pada instance MySQL kedua). Runtime Bun mengikuti packageManager frontend.

Workflow memiliki izin contents:read dan tidak menyimpan credential checkout.
Tidak memakai secret kampus, environment produksi, atau melakukan deployment.
Password service MySQL dalam YAML hanya untuk container sementara milik job CI.
Database dan prefix Redis test memakai identitas acak per run.

Referensi input action: [checkout](https://github.com/actions/checkout),
[setup-bun](https://github.com/oven-sh/setup-bun),
[setup-php](https://github.com/shivammathur/setup-php).

Workflow belum dijalankan di GitHub karena commit/push tertahan izin sesi.
Validasi lokal tidak menggantikan bukti run Ubuntu/Redis 7. Setelah push berhasil,
periksa ketiga job pada tab Actions; branch protection tidak diubah oleh pekerjaan ini.

Validasi persiapan: YAML berhasil diparse, 93 test backend / 584 assertion,
6 test integrasi / 73 assertion pada MySQL 8.4.3 dan Redis 5 lokal, serta 11 test
frontend lulus. Pint seluruh backend lulus setelah sembilan file lama diformat
mengikuti preset proyek. Build/lint frontend juga lulus. Redis 7/Ubuntu CI tetap
menunggu run GitHub yang sebenarnya.
