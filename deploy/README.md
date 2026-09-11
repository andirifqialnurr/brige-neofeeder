# VPS Deployment

Deployment VPS memakai Docker untuk seluruh runtime aplikasi. Host hanya perlu Docker, Docker Compose, Git, dan Nginx sebagai reverse proxy.

## 1. Environment

Salin file environment:

```bash
cp .env.deploy.example .env
cp backend/.env.docker.example backend/.env
```

Edit `.env` di root:

```env
FRONTEND_BIND=127.0.0.1:1000
BACKEND_BIND=127.0.0.1:2000
VITE_API_BASE_URL=https://domain-anda.com/api
MYSQL_DATABASE=bridge_neofeeder
MYSQL_USER=bridge
MYSQL_PASSWORD=password-db-yang-sama-dengan-backend
MYSQL_ROOT_PASSWORD=password-root-db
```

Edit `backend/.env`:

```env
APP_URL=https://domain-anda.com
FRONTEND_URL=https://domain-anda.com
DB_DATABASE=bridge_neofeeder
DB_USERNAME=bridge
DB_PASSWORD=password-db-yang-sama-dengan-root-env
ADMIN_EMAIL=email-admin-anda
ADMIN_PASSWORD=password-admin-kuat
```

`APP_KEY` boleh kosong sebelum boot pertama, lalu isi dengan hasil `php artisan key:generate`.
Jangan regenerate `APP_KEY` setelah credential Neo Feeder tersimpan.

## 2. Deploy Script

Setelah env diisi, jalankan:

```bash
chmod +x deploy.sh
./deploy.sh
```

Script akan pull code terbaru, build image, start MySQL/Redis, generate `APP_KEY` jika masih kosong, menjalankan migration/seed, lalu start frontend, backend, worker, dan scheduler.

## 3. Manual Build And Start

```bash
docker compose up -d --build mysql redis
docker compose run --rm backend php artisan key:generate --show
```

Masukkan hasil key tersebut ke `backend/.env` pada `APP_KEY=...`, lalu jalankan:

```bash
docker compose up -d --build
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed --force
docker compose exec backend php artisan storage:link
```

## 4. Nginx Host

Gunakan contoh config:

```bash
sudo cp deploy/nginx/bridge-neofeeder.conf.example /etc/nginx/sites-available/bridge-neofeeder.conf
sudo nano /etc/nginx/sites-available/bridge-neofeeder.conf
sudo ln -s /etc/nginx/sites-available/bridge-neofeeder.conf /etc/nginx/sites-enabled/bridge-neofeeder.conf
sudo nginx -t
sudo systemctl reload nginx
```

## 5. Health Check

```bash
curl http://127.0.0.1:2000/api/health
curl http://127.0.0.1:1000
docker compose ps
docker compose logs -f backend
```

## 6. Trial Flow

1. Login memakai admin dari `backend/.env`.
2. Buat tenant kampus trial.
3. Input credential Neo Feeder trial.
4. Test koneksi.
5. Sync referensi kecil.
6. Generate template Excel.
7. Upload sample kecil.
8. Dry-run.
9. Approve sync batch kecil.
