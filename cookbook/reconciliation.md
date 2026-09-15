# Rekonsiliasi batch

Tab Rekonsiliasi pada detail batch membaca status attempt terakhir per baris,
mengelompokkan belum dikirim, invalid, aktif, sukses, gagal, unknown, dan dilewati.
`unknown` selalu tampil sebagai **Perlu pemeriksaan**, walaupun status staging
bernilai failed. Retry historis tidak dihitung sebagai mahasiswa tambahan.

Filter status dan pagination 25 baris tersedia; ringkasan mencakup seluruh batch.
Jumlah snapshot sumber/selisih tersedia untuk mapping. Untuk workbook, jumlah
baris sumber asli tidak diklaim karena parser tidak menyimpan hitungan independen.
Laporan dibatasi 20.000 staging record per batch dan menolak batch yang masih parsing.

Ekspor CSV memakai filter aktif, waktu pembuatan, ID baris/attempt, versi mapping,
status dan ID hasil yang diizinkan contract. Tidak menyertakan raw payload, nama,
NIK, credential atau deskripsi error bebas. Ekspor tercatat di audit dan formula
spreadsheet dinetralkan. Snapshot UI dan ekspor dapat berbeda jika worker berjalan;
masing-masing menampilkan waktu pengambilannya.

Laporan merupakan rekonsiliasi catatan lokal, bukan pembacaan balik Neo Feeder.
Tidak menjalankan retry atau mengubah hasil unknown.

Pemeriksaan UI lokal: tab dan filter Perlu pemeriksaan bekerja pada batch demo
tiga baris; tampilan desktop dan viewport 390px diperiksa. Ekspor dibuktikan oleh
test backend, belum melalui download browser.
