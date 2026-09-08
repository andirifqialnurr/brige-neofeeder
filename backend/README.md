# Bridge Neo Feeder Backend

Laravel API, queue worker, scheduler, dan integration layer untuk Bridge Neo Feeder.

## Stack

- Laravel
- MySQL/MariaDB
- Redis
- Laravel Queue
- Laravel Scheduler
- PhpSpreadsheet/maatwebsite Excel

## Setup

Disarankan jalankan dari root project:

```bash
bun run setup:local
bun run dev
```

Perintah manual backend:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Queue worker:

```bash
php artisan queue:work
```

Scheduler local:

```bash
php artisan schedule:work
```

Docker service pendukung:

```bash
docker compose up -d mysql redis
```

## Scope Awal

- Tenant kampus.
- Koneksi Neo Feeder.
- Reference cache.
- Import batch dan staging record.
- Sync attempt dan identity write-back.
- Audit log.

Neo Feeder tetap diakses melalui Web Service. Aplikasi ini tidak menulis langsung ke database internal Neo Feeder.
