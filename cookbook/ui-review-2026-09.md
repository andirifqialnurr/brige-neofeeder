# UI Review - September 2026

## Cakupan

Redesign dashboard, kampus, koneksi/referensi, template, import, validasi,
mapping, landing page, dan login. Token warna existing dipertahankan.

Referensi visual dan aturan operasional ada di [design-system.md](design-system.md).

## Bukti Verifikasi Lokal

- `bun run frontend:build`: lulus TypeScript dan production build.
- `bun run frontend:lint`: lulus.
- `git diff --check`: lulus.
- Browser Chrome: screenshot desktop 1440x900 dan viewport desktop 1920px;
  screenshot mobile 390x844; pemeriksaan DOM pada lebar 320px.
- Light/dark: screenshot dashboard dan media landing tampil sesuai tema;
  toggle mengikuti tema sistem yang sudah ter-resolve.
- Login: error credential contoh, reveal password, login berhasil ke fixture,
  dan logout kembali ke landing.
- Pencarian batch: pencarian nama file, hasil kosong, reset pencarian.
- Nama batch membuka halaman Validasi dengan batch yang sesuai.
- Dry-run contoh: temuan error tampil; mengganti batch membersihkan hasil lama.
- Dialog kampus: tambah melalui fixture berhasil, dialog ditutup, feedback tampil.
- Escape pada dialog menutup dialog dan mengembalikan fokus ke pemicu.
- Dialog koneksi menampilkan nilai edit; tab Koneksi/Referensi berpindah dengan ArrowRight.
- Tooltip bantuan dapat dibuka dengan klik dan ditutup dengan Escape.
- Menu mobile membuka navigasi, lalu menutup setelah halaman dipilih.
- Tabel panjang scroll di dalam wrapper. Nama file panjang tidak keluar sel.
- Pada 320px, overflow akibat body min-width dan scrollbar diperbaiki.
  Template, import, validasi, mapping, dan dialog upload tidak membuat halaman melebar.
- Dialog mobile 390px dan 320px tetap muat; form fokus pada input pertama.
- Error pemuatan kampus terlihat ketika fixture sempat berhenti; refresh memulihkan tabel.
- FAQ dapat dibuka/tutup dan link kembali ke atas bekerja.
- Gambar landing light/dark terverifikasi termuat, dengan naturalWidth bukan nol.

Screenshot produk menggunakan data contoh lokal, tersimpan di:

- `frontend/public/images/workspace-preview.png`
- `frontend/public/images/workspace-preview-dark.png`

## Batas Verifikasi

Browser dashboard memakai `scripts/ui-fixture.mjs`, bukan database kampus/VPS.
Fixture tidak memanggil Neo Feeder dan tidak menyimpan data ke database.
Pengujian ini membuktikan UI, interaksi, serta penanganan response contoh;
bukan acceptance autentikasi Laravel, parsing workbook, upload end-to-end,
atau sinkronisasi Neo Feeder. Tidak ada migrasi atau perubahan backend pada task ini.

Deployment ke VPS tetap memerlukan `./deploy.sh` dan pemeriksaan ulang sesudah deploy.

## Mengulang Pemeriksaan UI

Terminal pertama, dari root repository:

```powershell
bun scripts/ui-fixture.mjs
```

Terminal kedua, gunakan port khusus pengujian agar terpisah dari frontend normal:

```powershell
$env:VITE_API_BASE_URL = 'http://localhost:2001/api'
bun run --cwd frontend dev --port 1001
```

Buka `http://localhost:1001`, lalu login dengan akun fiktif
`qa@example.test` / `preview-only`. Tutup kedua proses setelah selesai.
Jangan memakai fixture sebagai backend produksi. Tidak perlu mengubah `.env`.
