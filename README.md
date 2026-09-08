# Bridge Neo Feeder

Bridge Neo Feeder adalah aplikasi jembatan antara data SIAKAD kampus dan Neo Feeder PDDIKTI. Frontend memakai React + Vite, backend memakai Laravel, database aplikasi memakai MySQL/MariaDB, dan queue/cache memakai Redis.

## Local Development

Runner lokal memakai Bun dari root project.

Setup pertama kali:

```bash
bun run setup:local
```

Jalankan frontend, backend, MySQL, dan Redis:

```bash
bun run dev
```

Akses lokal:

```text
Frontend: http://127.0.0.1:5173
Backend:  http://127.0.0.1:8000/api/health
```

Jalankan frontend dan backend saja jika MySQL/Redis sudah berjalan:

```bash
bun run dev:app
```

Jalankan stack penuh dengan queue worker dan scheduler:

```bash
bun run dev:full
```

Matikan MySQL dan Redis Docker:

```bash
bun run dev:stop
```

## Prasyarat Lokal

- Bun 1.3+
- PHP 8.2+
- Composer
- Docker Desktop

Neo Feeder tetap diakses lewat Web Service. Aplikasi ini tidak menulis langsung ke database internal Neo Feeder.
