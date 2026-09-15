# Paket persiapan trial Neo Feeder

Paket ini berisi data fiktif dan **belum diuji dengan Neo Feeder aktual**.
Tidak ada token, credential, tenant, approval, atau pengiriman yang dibuat oleh generator.
Gunakan lingkungan trial kampus yang telah disepakati. Jangan gunakan contoh ini
untuk data mahasiswa produksi.

## Isi paket

| File | Isi |
| --- | --- |
| `01-biodata.xlsx` | Workbook lengkap dengan satu baris biodata; sheet kanal lain kosong. |
| `02-riwayat.xlsx` | Workbook lengkap dengan satu baris riwayat pendidikan; isi ID mahasiswa setelah langkah biodata berhasil. |
| `01-biodata-mapping.csv` / `02-riwayat-mapping.csv` | Alternatif untuk latihan Mapping SIAKAD, bukan tambahan yang perlu diimpor bersamaan. |
| `contract-baseline.json` | Definisi internal saat paket dibuat; pembanding terhadap dictionary runtime. |
| `response-log.csv` | Catatan checkpoint, status, ID hasil, dan lokasi bukti; awalnya semua BELUM DIUJI. |
| `manifest.json` | Versi template dan checksum file paket asli. |

Pilih satu jalur per percobaan: workbook atau mapping CSV. Mengimpor keduanya
menciptakan dua sumber data untuk mahasiswa contoh yang sama.

## Sebelum token tersedia

1. Pastikan kode/migration terbaru tersedia pada lingkungan trial.
2. Siapkan tenant trial dan operator yang tepat. Tenant demo tetap dilarang mengirim.
3. Periksa database, Redis, worker dan scheduler pada Operasional.
4. Buat backup dan lakukan restore drill terpisah; simpan APP_KEY secara terlindungi.
5. Pelajari kedua workbook. Pertahankan seluruh sheet dan header asli.
6. Semua nilai `ISI_*` sengaja belum lengkap. Jangan anggap ID referensi, NIK nol,
   tanggal daftar, atau NIM contoh valid untuk trial sebelum disetujui kampus.

## Setelah akses trial tersedia

Kerjakan satu checkpoint dan tinjau hasilnya sebelum lanjut:

1. **Koneksi.** Simpan endpoint dan credential trial melalui pengaturan Neo Feeder.
   Jalankan test koneksi (`GetToken`). Catat berhasil/gagal; jangan salin token/password ke log.
2. **Pembacaan kecil.** Uji `GetProdi`, lalu ambil referensi yang dibutuhkan:
   profil PT, semester, agama, negara, wilayah, dan jenis pendaftaran. Periksa
   identitas kampus dan versi Neo Feeder. Sinkronisasi referensi dilakukan melalui UI.
3. **Contract aktual.** Ambil dictionary melalui fasilitas WS trial yang tersedia,
   lalu bandingkan operasi/field dengan `contract-baseline.json`. Selesaikan perbedaan
   sebelum POST; baseline internal bukan bukti kesesuaian versi runtime.
4. **Biodata.** Salin `01-biodata.xlsx` sebagai file kerja, ganti semua `ISI_*`
   di baris biodata, dan sesuaikan data fiktif dengan aturan trial kampus.
   Jangan mengisi ID mahasiswa untuk insert baru.
5. **Validasi.** Upload file kerja, periksa temuan per field, jalankan dry-run.
   Pastikan hanya satu kandidat insert biodata dan semua error sudah diselesaikan.
   Admin dapat membuka data lengkap pada detail baris; aksesnya tercatat di audit.
6. **Persetujuan pertama.** Minta persetujuan operator atas payload yang telah ditinjau,
   lalu konfirmasi pengiriman satu biodata. Persetujuan baru diperlukan jika data berubah.
7. **Hasil biodata.** Catat batch/attempt, `error_code`, dan `id_mahasiswa` dari riwayat
   pengiriman. Verifikasi record pada Neo Feeder. Sukses tanpa ID tetap perlu pemeriksaan.
8. **Riwayat pendidikan.** Salin `02-riwayat.xlsx` sebagai file kerja. Isi
   `id_mahasiswa` dari langkah 7, serta PT/prodi/semester/jenis daftar yang telah diverifikasi.
   Jangan mengisi `id_registrasi_mahasiswa` untuk insert baru.
9. **Persetujuan kedua.** Upload dan dry-run riwayat, selesaikan temuan, lalu minta
   persetujuan terpisah untuk satu insert riwayat. Catat `id_registrasi_mahasiswa`
   dan verifikasi hubungan mahasiswa, prodi, serta periode masuk pada Neo Feeder.
10. **Catatan akhir.** Lengkapi `response-log.csv` dengan bukti aktual yang telah
    disanitasi. Tandai PASS hanya untuk checkpoint yang benar-benar dibuktikan.

## Jika terjadi kegagalan

- `Perlu pemeriksaan` / `unknown`: hentikan pengiriman terkait, periksa hasil remote,
  dan jangan retry otomatis/manual. Jangan membuat batch duplikat untuk mengakali status.
- Business error: simpan kode/deskripsi yang telah disanitasi, perbaiki penyebab,
  lalu ikuti guard retry atau buat batch revisi sesuai perubahan data.
- Worker mati: periksa Operasional dan recovery; jangan menjalankan retry massal.
- Dictionary berbeda: perbaiki contract, ulang validasi/dry-run dan approval.

## Bukti minimum

Simpan waktu, versi aplikasi/runtime, tenant trial, action, batch/attempt, kode hasil,
ID hasil, verifikasi pembacaan kembali, dan nama pemeriksa. Hindari token/password,
payload mahasiswa utuh, NIK/NPWP/telepon pada catatan yang dibagikan.
Simpan file kerja yang telah diedit terpisah dari paket asli; checksum manifest
berlaku untuk paket asli saja. Uji kecil ini belum membuktikan seluruh kanal,
volume produksi, atau kesiapan penerapan pada kampus lain.
