# Uji kapasitas mapping

Preview mapping tetap memproses seluruh snapshot sumber sampai batas `2.000`
baris. Respons API hanya mengirim satu halaman (maksimal 100 baris), sehingga
operator dapat menelusuri data tanpa memuat seluruh hasil ke browser sekaligus.

Regression test:

```bash
cd backend
php artisan test --do-not-cache-result tests/Feature/FileMappingTest.php --filter=full_two_thousand
```

Test membuat 2.000 baris fiktif dengan natural key unik, lalu memastikan summary
tetap 2.000 baris, halaman terakhir benar, dan halaman pertama berisi 100 baris.
Pengujian ini tidak menghubungi Neo Feeder dan tidak memakai data kampus.

Batas file sumber tetap ditegakkan saat upload (2.000 baris data, 64 kolom,
ukuran file 2 MB). Bila kebutuhan melebihi batas itu, gunakan beberapa snapshot
dan batch terpisah setelah aturan dependensi serta idempotensi ditinjau.
