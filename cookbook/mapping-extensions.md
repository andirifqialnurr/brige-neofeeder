# Pengembangan mapping tanpa token

## Pencocokan referensi

Aturan `reference_label` mencocokkan nama referensi setelah trim tanpa membedakan
huruf besar. `reference_code` memakai `kode_program_studi` untuk GetProdi dan
`kode_mata_kuliah` untuk GetListMataKuliah. Keduanya hanya membaca snapshot referensi
kampus yang tersimpan; tidak memanggil Neo Feeder.

Pilih **Padanan ID** pada aturan untuk menetapkan nilai asal tertentu ke ID kampus.
Pencarian pilihan menampilkan maksimal 100 nama/ID. Padanan harus unik per nilai
asal, maksimal 100 per aturan, dan disimpan dalam versi profil baru.
Hasil ambigu/tidak ditemukan menjadi error yang tetap dibawa ke staging dan dry-run,
termasuk untuk field opsional. Sistem tidak memilih kandidat pertama secara otomatis.

Preview mengikat aturan, contract, sumber, hasil transformasi dan hasil validasi.
Jika perubahan referensi mengubah hasil tersebut, staging ditolak sampai preview
diulang. Untuk batch yang sudah terbentuk, buat versi mapping baru untuk koreksi.

Validasi lokal: FileMappingTest 7 test / 78 assertion mencakup isolasi tenant,
padanan ambigu, referensi yang berubah, dan alur staging sebelumnya. Pengujian ini
belum membuktikan dictionary atau kecocokan kode referensi Neo Feeder aktual.

## Transformasi lanjutan

- Tabel padanan/enum: maksimal 100 pasangan unik, exact match setelah trim.
  Nilai yang tidak memiliki padanan menjadi error; string `0` tetap diproses.
- Gabung kolom: kolom utama diikuti maksimal tujuh kolom sesuai urutan pilihan;
  nilai kosong dilewati, pemisah termasuk spasi dipertahankan.
- Ambil bagian teks: pemisah literal, nomor bagian mulai 1 sampai 64. Bagian
  yang tidak tersedia/kosong menjadi error, termasuk untuk field opsional.

Semua parameter menjadi bagian versi profil dan hash preview. Satu field memakai
satu transformasi; pipeline beberapa transformasi belum tersedia.

## Mata kuliah dan kelas

Mapping mendukung empat kanal: biodata, riwayat, mata kuliah, dan kelas kuliah.
Mata kuliah memerlukan prodi, kode, nama, serta SKS; kelas memerlukan prodi,
semester, ID mata kuliah, nama kelas, dan penanda PDITT sesuai contract internal.
ID utama kosong menghasilkan kandidat insert; ID terisi menghasilkan update pada dry-run.

Kelas hanya dapat memakai mata kuliah yang sudah ada pada snapshot referensi kampus.
Batch mata kuliah baru harus dikirim dengan persetujuan tersendiri, ID hasil
diverifikasi, dan referensi diperbarui sebelum staging kelas. Tidak ada penyambungan
ID sementara atau pengiriman otomatis lintas batch. Jika raw referensi menyertakan
id_prodi, prodi mata kuliah harus cocok; jika tidak, muncul peringatan untuk
verifikasi manual. Kode mata kuliah ambigu harus diselesaikan dengan padanan ID.

Mapping memeriksa SKS 1–999.99 dan kapasitas kelas sebagai bilangan bulat 0–99999,
serta menolak natural key duplikat. Pengujian memakai data fiktif dan contract
internal; aturan versi Neo Feeder aktual tetap perlu dicocokkan saat trial.

Validasi akhir lokal: 96 test backend / 619 assertion, 6 test integrasi / 73
assertion (MySQL 8.4.3 + Redis 5), serta 11 test frontend lulus. Build/lint frontend
dan Pint seluruh backend lulus. Resolver mengindeks snapshot referensi sekali per
endpoint pada satu preview, sehingga tidak memindai ulang semua referensi per baris.

UI rekonsiliasi dan penyimpanan timeout diperiksa pada browser. Interaksi dialog
padanan/transformasi dan alur upload → preview → staging mapping belum dibuktikan
melalui browser karena izin upload ditolak pada sesi sebelumnya; pengujian backend
tetap memakai fixture fiktif terpisah. Workflow Redis 7/Ubuntu belum dijalankan di GitHub.
