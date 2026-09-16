# Menyiapkan paket trial

Runbook gate-by-gate untuk menjalankan paket ini tersedia di
[`cookbook/pilot-runbook.md`](../cookbook/pilot-runbook.md).

Jalankan tanpa credential Neo Feeder:

```bash
docker compose exec backend php artisan bridge:trial-pack
```

Untuk lokal: `cd backend`, lalu `php artisan bridge:trial-pack`.
Command mencetak direktori paket di `storage/app/private/trial-packs/<timestamp-uuid>`.
Setiap run membuat direktori baru tanpa menimpa file kerja sebelumnya.

Paket berisi dua workbook satu record, dua CSV mapping, baseline contract, log
checkpoint, manifest checksum, dan runbook. Panduan lengkap ada di
[`backend/resources/trial/README.md`](../backend/resources/trial/README.md), dan
disalin ke paket saat dibuat. File contoh memakai data fiktif dan placeholder
referensi; validasi sengaja belum lulus sebelum referensi trial diisi.

Generator tidak membuat tenant/batch, mengubah credential, memberikan approval,
atau menghubungi Neo Feeder. Trial aktual dan verifikasi ID hasil tetap menunggu
akses serta persetujuan atas payload yang telah ditinjau.

Validasi lokal 2026-09-15: generator dijalankan dan menghasilkan delapan file
(termasuk manifest). Test paket lulus **1 test / 24 assertion**: checksum,
dua workbook satu record, seluruh sheet wajib, NIK sebagai teks, placeholder
ditolak validator, serta tidak ada request/job outbound.
