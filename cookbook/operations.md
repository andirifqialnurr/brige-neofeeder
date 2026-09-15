# Monitoring operasional

Admin membuka `/operations` untuk memeriksa database, Redis, jumlah job antrean
default, heartbeat worker/scheduler, dan 20 job gagal terakhir. Endpoint
`GET /api/operations/health` memerlukan admin dan memakai `Cache-Control: no-store`.
Payload job, exception, credential, dan alamat layanan internal tidak dikirim.
`GET /api/health` tetap sebagai pemeriksaan proses HTTP publik.

Scheduler menjalankan `bridge:heartbeat` setiap menit. Command mencatat heartbeat
scheduler di Redis lalu mengantrekan `RecordWorkerHeartbeat`. Hanya eksekusi job
yang mencatat heartbeat worker. Setelah 180 detik, status menjadi terlambat.
Ini mengukur kemampuan antrean default memproses pemeriksaan; antrean panjang
juga dapat membuat heartbeat terlambat. Ini bukan bukti semua worker sehat.

Jalankan `bun run dev:full` atau service worker/scheduler Docker yang sudah ada.
Halaman memperbarui data setiap 15 detik saat terlihat. Monitoring tidak mengirim
ke Neo Feeder dan tidak menyediakan retry massal atas job pengiriman.

Validasi lokal 2026-09-15: 3 test/15 assertion, build/lint frontend, 11 test frontend.
Browser: desktop dark, mobile 390px light; worker/scheduler berubah dari belum
terdeteksi menjadi normal setelah heartbeat diproses melalui Redis lokal.
