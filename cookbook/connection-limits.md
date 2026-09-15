# Batas request dan gangguan koneksi

- Login: 20 request/menit/IP dan 5 request/menit kombinasi IP+email.
- API terautentikasi: 300 pembacaan dan 120 perubahan/menit per kampus operator;
  admin dibatasi per akun karena dapat bekerja lintas kampus. HTTP 429 menyertakan
  Retry-After. Mengganti tenant_id pada request operator tidak mengubah kunci limit.
- Neo Feeder: default 120 request/menit per koneksi (`NEOFEEDER_REQUESTS_PER_MINUTE`),
  termasuk GetToken; satu request aktif per koneksi. Batas ini memakai shared cache.
- Timeout per koneksi 1–30 detik, default 30. Connect timeout maksimum 5 detik.
  Batas ini mempertahankan ruang untuk GetToken+POST pada worker timeout 75 detik.

Jeda koneksi (circuit breaker) aktif setelah tiga HTTP/transport failure dalam
jendela state lima menit atau response tanpa error_code. Jeda berlangsung 60 detik;
request selanjutnya dapat mencoba lagi setelah jeda, satu per koneksi. Response
business error tidak dihitung sebagai gangguan transport. GetToken sukses tidak
menghapus hitungan kegagalan POST; response aplikasi non-GetToken yang terbaca
menghapus hitungan tersebut.

Penolakan limit/jeda/lock terjadi sebelum HTTP request dan tidak dikirim ulang
otomatis. Attempt yang sudah dijadwalkan ditandai gagal aman untuk retry manual.
Timeout/exception setelah POST tetap unknown dan tidak boleh retry; guard tidak
mengubah aturan persetujuan maupun larangan outbound tenant demo.

Gunakan shared Redis cache pada deployment. Cache array hanya untuk test satu
proses. Perubahan ini membutuhkan migration penambahan `timeout_ms`; belum
diterapkan ke VPS dan belum diuji pada Neo Feeder aktual.

Pemeriksaan browser lokal membuktikan nilai timeout 30 → 20 detik tersimpan
setelah form dibuka ulang, lalu dikembalikan ke 30. Koneksi demo tetap nonaktif;
tombol test koneksi tidak dijalankan.
