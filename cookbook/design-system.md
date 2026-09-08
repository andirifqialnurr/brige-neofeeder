# Design System - Bridge Neo Feeder

## 1. Prinsip Desain

Aplikasi ini adalah alat operasional untuk data akademik dan integrasi Neo Feeder. UI harus terasa tenang, padat, mudah dipindai, dan mendukung pekerjaan berulang.

Prinsip:

- Prioritaskan data, status, dan aksi.
- Hindari gaya landing page atau hero marketing.
- Jangan menyembunyikan error di modal panjang.
- Buat operator bisa memahami masalah per sheet, baris, dan field.
- Semua aksi berisiko seperti post, retry, delete, dan credential update harus eksplisit.
- Status sinkronisasi harus mudah dibaca tanpa membuka detail satu per satu.

## 2. Struktur Navigasi

Navigasi utama:

- Dashboard
- Kampus/Tenant
- Neo Feeder
- Referensi
- Template Excel
- Import Batch
- Validasi
- Sync
- Automation
- Mapping
- Audit Log
- Settings

Untuk Phase 1, menu `Automation` dan `Mapping` boleh tampil disabled atau hidden sampai siap.

## 3. Layout

### App Shell

- Sidebar kiri untuk navigasi modul.
- Header atas untuk tenant switcher, periode aktif, user menu, dan status koneksi.
- Konten utama memakai layout full-width dengan max readable width pada form.
- Tabel data memakai full-width.

### Page Pattern

Setiap halaman operasional memakai pola:

```text
Page title + primary action
Filter/status bar
Main table or workspace
Right drawer/detail panel when needed
```

Jangan memakai cards bertumpuk untuk data utama. Cards hanya untuk ringkasan metrik, item berulang, modal, atau detail kecil.

## 4. Warna

Palet harus netral dan fungsional.

Token warna:

```text
background: #f7f8fa
surface: #ffffff
surface-muted: #f1f3f5
border: #d9dee5
text-primary: #1f2933
text-secondary: #5b6776
text-muted: #8792a2
primary: #2563eb
primary-hover: #1d4ed8
success: #16803c
warning: #b7791f
danger: #c2410c
info: #0f766e
purple-accent: #7c3aed
```

Gunakan warna status secara terbatas. Jangan membuat UI dominan satu warna.

## 5. Typography

Font:

- System UI stack.
- Monospace untuk payload, field name, endpoint, UUID, dan kode.

Skala:

```text
page-title: 24px / 32px / 600
section-title: 18px / 28px / 600
subsection-title: 15px / 24px / 600
body: 14px / 22px / 400
table: 13px / 20px / 400
caption: 12px / 18px / 400
code: 12px / 18px / 400
```

Letter spacing: 0.

## 6. Komponen Utama

### Button

Jenis:

- Primary: aksi utama seperti `Upload`, `Generate Template`, `Start Sync`.
- Secondary: aksi biasa seperti `Refresh`, `Download`, `Preview`.
- Danger: aksi berisiko seperti `Delete`, `Reset Batch`.
- Ghost/Icon: aksi tabel seperti view detail, retry, copy, download.

Gunakan icon dari lucide jika tersedia:

- upload,
- download,
- refresh,
- play,
- pause,
- rotate-ccw,
- eye,
- settings,
- key,
- database,
- file-spreadsheet,
- check,
- alert-triangle,
- x-circle.

### Status Badge

Status batch:

```text
draft
uploaded
parsing
parsed
validating
valid
invalid
ready
syncing
partial_success
success
failed
cancelled
```

Status row:

```text
pending
valid
invalid
warning
ready
syncing
success
failed
skipped
ambiguous
```

### Data Table

Wajib:

- sticky header,
- density compact,
- column resize jika memungkinkan,
- filter per status,
- search,
- pagination,
- row detail drawer,
- export current view.

Kolom umum:

- status,
- sheet/channel,
- row number,
- natural key,
- operation,
- errors,
- warnings,
- last attempt,
- Neo Feeder ID,
- action.

### Validation Panel

Tampilan harus mendukung:

- group by sheet,
- group by severity,
- group by field,
- jump to row,
- copy error,
- filter blocking errors only.

Error format:

```text
Sheet mahasiswa_biodata, row 12, field nik:
NIK wajib 16 digit.
```

### Payload Viewer

Untuk dry-run dan sync detail:

- split view request/response,
- syntax highlight JSON,
- copy button,
- sensitive masking default,
- toggle reveal untuk role terbatas.

### Upload Wizard

Step:

1. pilih tenant/prodi/periode,
2. upload file,
3. parse,
4. validasi,
5. dry-run,
6. approve sync,
7. hasil.

Step indicator harus menunjukkan error count per tahap.

### Mapping Workspace Phase 2

Komponen:

- source table selector,
- source field list,
- target contract field list,
- mapping grid,
- transform rule editor,
- sample preview,
- validation result,
- unresolved references panel.

UI mapping harus memperlihatkan:

- required target fields yang belum terisi,
- source sample values,
- hasil transform,
- reference match confidence.

## 7. Page Inventory

## 7.1 Dashboard

Isi:

- status koneksi Neo Feeder,
- referensi terakhir refresh,
- batch import terbaru,
- sync success/failure rate,
- queue status,
- error terbaru.

## 7.2 Tenant Detail

Isi:

- profil kampus,
- URL Neo Feeder,
- status credential,
- prodi aktif,
- user/operator tenant,
- audit ringkas.

## 7.3 Neo Feeder Connection

Isi:

- endpoint URL,
- username/password form,
- test connection,
- token status masked,
- hasil `GetProfilPT`,
- timeout config.

## 7.4 References

Isi:

- daftar endpoint referensi,
- last sync,
- row count,
- failed reason,
- refresh button,
- preview records.

## 7.5 Template Excel

Isi:

- pilih template version,
- pilih kanal,
- pilih prodi/periode,
- generate,
- download,
- daftar template generated.

## 7.6 Import Batch

Isi:

- upload workbook,
- batch list,
- status,
- owner,
- created date,
- total rows,
- valid/error/warning counts.

## 7.7 Batch Detail

Tabs:

- Overview
- Sheets
- Validation
- Dry Run
- Sync Attempts
- Audit

## 7.8 Automation Source

Phase 2:

- source connection list,
- source type,
- status,
- schema discovery,
- sample data.

## 7.9 Mapping Profile

Phase 2:

- source profile,
- target channel,
- mapping version,
- field mapping,
- transform rules,
- sample validation.

## 8. Form Rules

- Required label pakai `*`.
- Field help singkat di bawah input jika perlu.
- Tanggal selalu `yyyy-mm-dd`.
- UUID dan kode pakai monospace.
- Dropdown referensi harus searchable.
- Jangan paksa dropdown untuk referensi besar seperti wilayah tanpa search.
- Untuk nilai numeric, tampilkan constraint min/max/precision.

## 9. Empty, Loading, Error States

Empty state harus memberi aksi berikutnya, bukan teks promosi.

Contoh:

```text
Belum ada batch import.
[Upload Excel]
```

Loading:

- skeleton untuk table,
- progress untuk parse/validation/sync,
- jangan blocking seluruh halaman jika hanya satu panel loading.

Error:

- tampilkan pesan teknis ringkas,
- link ke detail log jika ada,
- jangan hilangkan raw error dari Neo Feeder untuk role teknis.

## 10. Accessibility

- Semua icon button punya tooltip dan aria-label.
- Kontras warna status harus cukup.
- Error form harus terhubung dengan field.
- Navigasi keyboard untuk table dan modal.
- Jangan mengandalkan warna saja untuk status; gunakan icon/label.

## 11. Copywriting

Gunakan istilah konsisten:

- `Kampus`
- `Tenant`
- `Neo Feeder`
- `Referensi`
- `Template Excel`
- `Batch Import`
- `Validasi`
- `Dry-run`
- `Sinkronisasi`
- `Mapping`
- `Otomatisasi`

Nada teks:

- langsung,
- jelas,
- tidak marketing,
- tidak menyalahkan user.

Contoh:

```text
NIK wajib 16 digit.
Prodi tidak ditemukan di referensi Neo Feeder.
Baris ini belum dikirim karena masih ada error validasi.
```

## 12. Responsive

Desktop adalah target utama.

Mobile/tablet:

- dashboard ringkas,
- list batch bisa dibaca,
- detail error bisa dibuka,
- mapping workspace boleh tidak optimal di mobile.

Tabel besar harus menyediakan horizontal scroll yang jelas.

## 13. Data Density

Default:

- compact table,
- row height 36-44px,
- status badge pendek,
- action column icon-only dengan tooltip.

Untuk log dan payload:

- gunakan drawer atau full page detail,
- jangan menaruh JSON panjang di card kecil.
