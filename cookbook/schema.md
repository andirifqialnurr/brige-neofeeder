# Schema - Neo Feeder Contract And Local Data

## 1. Tujuan Schema

File ini mendefinisikan contract internal untuk Bridge Neo Feeder. Contract ini menjadi sumber untuk:

- generate template Excel,
- parsing upload,
- validasi data,
- resolver referensi,
- payload builder,
- urutan post ke Neo Feeder,
- mapping otomatisasi SIAKAD.

Schema ini bukan pengganti dokumentasi resmi Neo Feeder. Implementasi tetap perlu membandingkan contract internal dengan `GetDictionary` pada tenant trial/production.

## 2. Konsep Utama

### Channel

Channel adalah kanal data bisnis yang bisa diisi lewat Excel atau otomatisasi.

Contoh:

- `mahasiswa_biodata`
- `mahasiswa_riwayat_pendidikan`
- `mata_kuliah`
- `kelas_kuliah`
- `peserta_kelas`
- `nilai_perkuliahan`
- `perkuliahan_mahasiswa_akm`

### Operation

Operation adalah aksi Neo Feeder di balik channel.

Contoh:

- `InsertBiodataMahasiswa`
- `UpdateBiodataMahasiswa`
- `DeleteBiodataMahasiswa`
- `GetBiodataMahasiswa`

### Field

Field adalah kolom data target Neo Feeder. Field punya tipe, required flag, primary key flag, reference source, dan validators.

### Reference

Reference adalah data lookup dari Neo Feeder, misalnya agama, prodi, semester, wilayah, jenis pendaftaran, dosen, mata kuliah, atau kelas.

### Identity

Identity adalah ID Neo Feeder yang disimpan setelah post sukses, misalnya:

- `id_mahasiswa`
- `id_registrasi_mahasiswa`
- `id_matkul`
- `id_kurikulum`
- `id_kelas_kuliah`
- `id_bobot_nilai`

## 3. Laravel Contract Shape

Contract bisa disimpan sebagai PHP config array, database rows, atau kombinasi keduanya. Untuk awal, gunakan PHP config agar mudah direview dan dites.

```php
return [
    'key' => 'mahasiswa_biodata',
    'label' => 'Biodata Mahasiswa',
    'sheet_name' => 'mahasiswa_biodata',
    'phase' => 1,
    'priority' => 'mvp',
    'dependencies' => [],
    'natural_key' => ['nik'],
    'operations' => [
        'get' => [
            'action' => 'GetBiodataMahasiswa',
            'kind' => 'get',
            'payload_mode' => 'list_query',
        ],
        'insert' => [
            'action' => 'InsertBiodataMahasiswa',
            'kind' => 'insert',
            'payload_mode' => 'record',
            'response_identity_fields' => ['id_mahasiswa'],
        ],
        'update' => [
            'action' => 'UpdateBiodataMahasiswa',
            'kind' => 'update',
            'payload_mode' => 'key_record',
            'key_fields' => ['id_mahasiswa'],
        ],
        'delete' => [
            'action' => 'DeleteBiodataMahasiswa',
            'kind' => 'delete',
            'payload_mode' => 'key_only',
            'key_fields' => ['id_mahasiswa'],
        ],
    ],
    'fields' => [
        [
            'name' => 'nama_mahasiswa',
            'type' => 'string',
            'required' => true,
            'max_length' => 100,
        ],
        [
            'name' => 'jenis_kelamin',
            'type' => 'char',
            'required' => true,
            'max_length' => 1,
            'enum_values' => ['L', 'P', '*'],
        ],
        [
            'name' => 'id_agama',
            'type' => 'integer',
            'required' => true,
            'reference' => [
                'endpoint' => 'GetAgama',
                'value_field' => 'id_agama',
                'label_field' => 'nama_agama',
                'match_by' => ['id_agama', 'nama_agama'],
            ],
        ],
    ],
];
```

Field contract mendukung properti:

```text
name
type
required
primary
max_length
precision
scale
default_value
enum_values
date_format
reference.endpoint
reference.value_field
reference.label_field
reference.match_by
notes
```

Operation contract mendukung `payload_mode`:

```text
record
key_record
key_only
list_query
record_array
key_record_array
```
```

## 4. Payload Rules

### Read/List

```json
{
  "act": "GetProdi",
  "token": "...",
  "filter": "",
  "order": "",
  "limit": "100",
  "offset": "0"
}
```

### Insert

```json
{
  "act": "InsertBiodataMahasiswa",
  "token": "...",
  "record": {}
}
```

### Update

```json
{
  "act": "UpdateBiodataMahasiswa",
  "token": "...",
  "key": {},
  "record": {}
}
```

### Delete

```json
{
  "act": "DeleteBiodataMahasiswa",
  "token": "...",
  "key": {}
}
```

### Record Array

Beberapa operasi memakai detail array, misalnya rencana evaluasi.

```json
{
  "act": "InsertRencanaEvaluasi",
  "token": "...",
  "record": []
}
```

## 5. Global Validation Rules

- Required field tidak boleh kosong.
- Empty string dinormalisasi menjadi `null` untuk field nullable.
- Date wajib `yyyy-mm-dd`.
- `uuid` harus format UUID.
- `boolean01` hanya menerima `0` atau `1`.
- Numeric harus angka valid dan mengikuti precision/scale.
- Char/string harus mengikuti panjang maksimal.
- Enum harus salah satu nilai yang diperbolehkan.
- Reference field harus ditemukan di cache referensi tenant.
- Primary key untuk update/delete wajib tersedia.
- Insert tidak boleh mengirim generated primary key kecuali dokumen operasi memintanya.
- Payload tidak dikirim jika ada validation error.
- Warning boleh dikirim hanya setelah operator approve.

## 6. Reference Endpoints

Referensi utama:

| Endpoint | Value Field | Label Field | Kegunaan |
| --- | --- | --- | --- |
| `GetProfilPT` | `id_perguruan_tinggi` | `nama_perguruan_tinggi` | kampus target |
| `GetProdi` | `id_prodi` | `nama_program_studi` | prodi tenant |
| `GetAllPT` | `id_perguruan_tinggi` | `nama_perguruan_tinggi` | kampus asal |
| `GetAllProdi` | `id_prodi` | `nama_program_studi` | prodi asal |
| `GetSemester` | `id_semester` | `nama_semester` | periode akademik |
| `GetAgama` | `id_agama` | `nama_agama` | biodata mahasiswa/dosen |
| `GetNegara` | `id_negara` | `nama_negara` | kewarganegaraan |
| `GetWilayah` | `id_wilayah` | `nama_wilayah` | alamat |
| `GetJenisTinggal` | `id_jenis_tinggal` | `nama_jenis_tinggal` | biodata mahasiswa |
| `GetAlatTransportasi` | `id_alat_transportasi` | `nama_alat_transportasi` | biodata mahasiswa |
| `GetJenjangPendidikan` | `id_jenjang_didik` | `nama_jenjang_didik` | pendidikan orang tua/wali |
| `GetPekerjaan` | `id_pekerjaan` | `nama_pekerjaan` | pekerjaan orang tua/wali |
| `GetPenghasilan` | `id_penghasilan` | `nama_penghasilan` | penghasilan orang tua/wali |
| `GetKebutuhanKhusus` | `id_kebutuhan_khusus` | `nama_kebutuhan_khusus` | kebutuhan khusus |
| `GetJenisPendaftaran` | `id_jenis_daftar` | `nama_jenis_daftar` | riwayat pendidikan |
| `GetJalurMasuk` | `id_jalur_masuk` | `nama_jalur_masuk` | riwayat pendidikan |
| `GetPembiayaan` | `id_pembiayaan` | `nama_pembiayaan` | pembiayaan awal |
| `GetStatusMahasiswa` | `id_status_mahasiswa` | `nama_status_mahasiswa` | AKM |
| `GetJenisKeluar` | `id_jenis_keluar` | `jenis_keluar` | lulus/DO |
| `GetJenisEvaluasi` | `id_jenis_evaluasi` | `nama_jenis_evaluasi` | dosen pengajar kelas |
| `GetKategoriKegiatan` | `id_kategori_kegiatan` | `nama_kategori_kegiatan` | bimbing/uji |
| `GetBasisEvaluasi` | `id_basis_evaluasi` | `nama_basis_evaluasi` | rencana evaluasi |
| `GetListDosen` | `id_dosen` | `nama_dosen` | dosen |
| `GetListPenugasanDosen` | `id_registrasi_dosen` | `nama_dosen` | dosen pengajar |
| `GetListMataKuliah` | `id_matkul` | `nama_mata_kuliah` | mata kuliah |
| `GetListKelasKuliah` | `id_kelas_kuliah` | `nama_kelas_kuliah` | kelas kuliah |
| `GetListRiwayatPendidikanMahasiswa` | `id_registrasi_mahasiswa` | `nim` | mahasiswa terdaftar |

## 7. Dependency Graph

Urutan sync dasar:

```text
references
  -> mahasiswa_biodata
  -> mahasiswa_riwayat_pendidikan
  -> mata_kuliah
  -> kurikulum
  -> matkul_kurikulum
  -> kelas_kuliah
  -> dosen_pengajar_kelas
  -> peserta_kelas
  -> nilai_perkuliahan
  -> perkuliahan_mahasiswa_akm
  -> aktivitas_mahasiswa
  -> anggota_aktivitas
  -> bimbing_uji
  -> prestasi_mahasiswa
  -> mahasiswa_lulus_do
```

Catatan:

- `dosen_pengajar_kelas` bergantung pada kelas dan penugasan dosen.
- `peserta_kelas` bergantung pada kelas dan riwayat pendidikan mahasiswa.
- `nilai_perkuliahan` bergantung pada peserta kelas.
- `perkuliahan_mahasiswa_akm` bergantung pada riwayat pendidikan dan semester.
- `lulus_do` bergantung pada riwayat pendidikan dan jenis keluar.

## 8. Channel Contracts Phase 1

## 8.1 mahasiswa_biodata

Operations:

- get: `GetBiodataMahasiswa`
- insert: `InsertBiodataMahasiswa`
- update: `UpdateBiodataMahasiswa`
- delete: `DeleteBiodataMahasiswa`

Natural key:

- `nik`
- fallback review manual: `nama_mahasiswa`, `tanggal_lahir`, `nama_ibu_kandung`

Response identity:

- `id_mahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_mahasiswa` | uuid | update/delete | primary identity |
| `nama_mahasiswa` | string(100) | yes |  |
| `jenis_kelamin` | char(1) | yes | `L`, `P`, `*` |
| `tempat_lahir` | string(32) | yes |  |
| `tanggal_lahir` | date | yes | `yyyy-mm-dd` |
| `id_agama` | integer | yes | `GetAgama` |
| `nik` | string(16) | yes | 16 digit |
| `nisn` | string(10) | no |  |
| `npwp` | string(15) | no |  |
| `kewarganegaraan` | char(2) | yes | `GetNegara.id_negara` |
| `jalan` | string(80) | no |  |
| `dusun` | string(60) | no |  |
| `rt` | numeric(2,0) | no |  |
| `rw` | numeric(2,0) | no |  |
| `kelurahan` | string(60) | yes |  |
| `kode_pos` | numeric(5,0) | no |  |
| `id_wilayah` | char(8) | yes | `GetWilayah` |
| `id_jenis_tinggal` | numeric(2,0) | no | `GetJenisTinggal` |
| `id_alat_transportasi` | numeric(2,0) | no | `GetAlatTransportasi` |
| `telepon` | string(20) | no |  |
| `handphone` | string(20) | no |  |
| `email` | string(60) | no | email format warning |
| `penerima_kps` | boolean01 | yes | `0` atau `1` |
| `nomor_kps` | string(80) | no | required warning jika penerima KPS |
| `nik_ayah` | string(16) | no | 16 digit jika diisi |
| `nama_ayah` | string(100) | no |  |
| `tanggal_lahir_ayah` | date | no | `yyyy-mm-dd` |
| `id_pendidikan_ayah` | numeric(2,0) | no | `GetJenjangPendidikan` |
| `id_pekerjaan_ayah` | integer | no | `GetPekerjaan` |
| `id_penghasilan_ayah` | integer | no | `GetPenghasilan` |
| `nik_ibu` | string(16) | no | 16 digit jika diisi |
| `nama_ibu_kandung` | string(100) | yes |  |
| `tanggal_lahir_ibu` | date | no | `yyyy-mm-dd` |
| `id_pendidikan_ibu` | numeric(2,0) | no | `GetJenjangPendidikan` |
| `id_pekerjaan_ibu` | integer | no | `GetPekerjaan` |
| `id_penghasilan_ibu` | integer | no | `GetPenghasilan` |
| `nama_wali` | string(100) | no |  |
| `tanggal_lahir_wali` | date | no | `yyyy-mm-dd` |
| `id_pendidikan_wali` | numeric(2,0) | no | `GetJenjangPendidikan` |
| `id_pekerjaan_wali` | integer | no | `GetPekerjaan` |
| `id_penghasilan_wali` | integer | no | `GetPenghasilan` |
| `id_kebutuhan_khusus_mahasiswa` | integer | yes | default `0`, `GetKebutuhanKhusus` |
| `id_kebutuhan_khusus_ayah` | integer | yes | default `0`, `GetKebutuhanKhusus` |
| `id_kebutuhan_khusus_ibu` | integer | yes | default `0`, `GetKebutuhanKhusus` |

## 8.2 mahasiswa_riwayat_pendidikan

Operations:

- get: `GetListRiwayatPendidikanMahasiswa`
- insert: `InsertRiwayatPendidikanMahasiswa`
- update: `UpdateRiwayatPendidikanMahasiswa`
- delete: `DeleteRiwayatPendidikanMahasiswa`

Dependencies:

- `mahasiswa_biodata`
- references

Natural key:

- `nim`, `id_prodi`, `id_periode_masuk`

Response identity:

- `id_registrasi_mahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_registrasi_mahasiswa` | uuid | update/delete | primary identity |
| `id_mahasiswa` | uuid | yes | `mahasiswa_biodata.id_mahasiswa` |
| `nim` | string(24) | yes |  |
| `id_jenis_daftar` | numeric(2,0) | yes | `GetJenisPendaftaran` |
| `id_jalur_daftar` | numeric(4,0) | no | `GetJalurMasuk` |
| `id_periode_masuk` | char(5) | yes | `GetSemester` |
| `tanggal_daftar` | date | yes | `yyyy-mm-dd` |
| `id_perguruan_tinggi` | uuid | yes | `GetProfilPT` |
| `id_prodi` | uuid | yes | `GetProdi` |
| `id_bidang_minat` | uuid | no | `GetListBidangMinat` |
| `sks_diakui` | numeric(3,0) | no |  |
| `id_perguruan_tinggi_asal` | uuid | no | `GetAllPT` |
| `id_prodi_asal` | uuid | no | `GetAllProdi` |
| `id_pembiayaan` | numeric | no | `GetPembiayaan` |
| `biaya_masuk` | numeric(16,2) | yes |  |

## 8.3 mata_kuliah

Operations:

- get: `GetListMataKuliah`
- insert: `InsertMataKuliah`
- update: `UpdateMataKuliah`
- delete: `DeleteMataKuliah`

Natural key:

- `id_prodi`, `kode_mata_kuliah`

Response identity:

- `id_matkul`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_matkul` | uuid | update/delete | primary identity |
| `kode_mata_kuliah` | string(20) | yes |  |
| `nama_mata_kuliah` | string(200) | yes |  |
| `id_prodi` | uuid | yes | `GetProdi` |
| `id_jenis_mata_kuliah` | char(1) | no | `A`, `B`, `C`, `D`, `S` |
| `id_kelompok_mata_kuliah` | char(1) | no | `A`..`H` |
| `sks_mata_kuliah` | numeric(5,2) | yes | minimum 1 |
| `sks_tatap_muka` | numeric(5,2) | no |  |
| `sks_praktek` | numeric(5,2) | no |  |
| `sks_praktek_lapangan` | numeric(5,2) | no |  |
| `sks_simulasi` | numeric(5,2) | no |  |
| `metode_kuliah` | string(50) | no |  |
| `ada_sap` | boolean01 | no |  |
| `ada_silabus` | boolean01 | no |  |
| `ada_bahan_ajar` | boolean01 | no |  |
| `ada_acara_praktek` | boolean01 | no |  |
| `ada_diktat` | boolean01 | no |  |
| `tanggal_mulai_efektif` | date | no | `yyyy-mm-dd` |
| `tanggal_akhir_efektif` | date | no | `yyyy-mm-dd` |

## 8.4 kurikulum

Operations:

- get: `GetListKurikulum`
- insert: `InsertKurikulum`
- update: `UpdateKurikulum`
- delete: `DeleteKurikulum`

Natural key:

- `id_prodi`, `id_semester`, `nama_kurikulum`

Response identity:

- `id_kurikulum`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_kurikulum` | uuid | update/delete | primary identity |
| `nama_kurikulum` | string(60) | yes |  |
| `id_prodi` | uuid | yes | `GetProdi` |
| `id_semester` | char(5) | yes | `GetSemester` |
| `jumlah_sks_lulus` | numeric(3,0) | yes |  |
| `jumlah_sks_wajib` | numeric(3,0) | yes |  |
| `jumlah_sks_pilihan` | numeric(3,0) | yes |  |

## 8.5 matkul_kurikulum

Operations:

- get: `GetMatkulKurikulum`
- insert: `InsertMatkulKurikulum`
- delete: `DeleteMatkulKurikulum`

Dependencies:

- `kurikulum`
- `mata_kuliah`

Natural key:

- `id_kurikulum`, `id_matkul`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_kurikulum` | uuid | yes | `GetListKurikulum` |
| `id_matkul` | uuid | yes | `GetListMataKuliah` |
| `semester` | numeric(2,0) | yes | 1..14 warning outside normal range |
| `sks_mata_kuliah` | numeric(5,2) | no |  |
| `sks_tatap_muka` | numeric(5,2) | no |  |
| `sks_praktek` | numeric(5,2) | no |  |
| `sks_praktek_lapangan` | numeric(5,2) | no |  |
| `sks_simulasi` | numeric(5,2) | no |  |
| `apakah_wajib` | boolean01 | yes | `1` wajib, `0` tidak |

## 8.6 kelas_kuliah

Operations:

- get: `GetListKelasKuliah`
- insert: `InsertKelasKuliah`
- update: `UpdateKelasKuliah`
- delete: `DeleteKelasKuliah`

Dependencies:

- `mata_kuliah`
- references

Natural key:

- `id_semester`, `id_prodi`, `id_matkul`, `nama_kelas_kuliah`

Response identity:

- `id_kelas_kuliah`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_kelas_kuliah` | uuid | update/delete | primary identity |
| `id_prodi` | uuid | yes | `GetProdi` |
| `id_semester` | char(5) | yes | `GetSemester` |
| `id_matkul` | uuid | yes | `GetListMataKuliah` |
| `nama_kelas_kuliah` | string(5) | yes |  |
| `bahasan` | string(200) | no |  |
| `tanggal_mulai_efektif` | date | no | required warning for Kampus Merdeka |
| `tanggal_akhir_efektif` | date | no | required warning for Kampus Merdeka |
| `tanggal_tutup_daftar` | date | no | required warning for Kampus Merdeka |
| `apa_untuk_pditt` | boolean01 | yes |  |
| `kapasitas` | numeric(5,0) | no |  |
| `lingkup` | numeric(1,0) | no | `1` internal, `2` external, `3` campuran |
| `mode` | char(1) | no | `O`, `F`, `M` |

## 8.7 dosen_pengajar_kelas

Operations:

- get: `GetDosenPengajarKelasKuliah`
- insert: `InsertDosenPengajarKelasKuliah`
- update: `UpdateDosenPengajarKelasKuliah`
- delete: `DeleteDosenPengajarKelasKuliah`

Dependencies:

- `kelas_kuliah`
- reference `GetListPenugasanDosen`

Natural key:

- `id_kelas_kuliah`, `id_registrasi_dosen`, `id_substansi`

Response identity:

- `id_aktivitas_mengajar`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_aktivitas_mengajar` | uuid | update/delete | primary identity |
| `id_registrasi_dosen` | uuid | yes | `GetListPenugasanDosen` |
| `id_kelas_kuliah` | uuid | yes | `GetListKelasKuliah` |
| `id_substansi` | uuid | no | `GetListSubstansiKuliah` |
| `sks_substansi_total` | numeric(5,2) | yes |  |
| `rencana_minggu_pertemuan` | numeric(2,0) | yes |  |
| `realisasi_minggu_pertemuan` | numeric(2,0) | no |  |
| `id_jenis_evaluasi` | integer | yes | `GetJenisEvaluasi` |

## 8.8 peserta_kelas

Operations:

- get: `GetPesertaKelasKuliah`
- insert: `InsertPesertaKelasKuliah`
- delete: `DeletePesertaKelasKuliah`

Dependencies:

- `kelas_kuliah`
- `mahasiswa_riwayat_pendidikan`

Natural key:

- `id_kelas_kuliah`, `id_registrasi_mahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_kelas_kuliah` | uuid | yes | `GetListKelasKuliah` |
| `id_registrasi_mahasiswa` | uuid | yes | `GetListRiwayatPendidikanMahasiswa` |

## 8.9 nilai_perkuliahan

Operations:

- get list: `GetListNilaiPerkuliahanKelas`
- get detail: `GetDetailNilaiPerkuliahanKelas`
- update: `UpdateNilaiPerkuliahanKelas`

Dependencies:

- `peserta_kelas`

Natural key:

- `id_kelas_kuliah`, `id_registrasi_mahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_registrasi_mahasiswa` | uuid | yes | `GetListRiwayatPendidikanMahasiswa` |
| `id_kelas_kuliah` | uuid | yes | `GetListKelasKuliah` |
| `nilai_angka` | numeric(4,1) | no | 0..100 warning |
| `nilai_indeks` | numeric(4,2) | no | 0..4 warning |
| `nilai_huruf` | string(3) | no | match skala nilai warning |

## 8.10 perkuliahan_mahasiswa_akm

Operations:

- get list: `GetListPerkuliahanMahasiswa`
- get detail: `GetDetailPerkuliahanMahasiswa`
- insert: `InsertPerkuliahanMahasiswa`
- update: `UpdatePerkuliahanMahasiswa`
- delete: `DeletePerkuliahanMahasiswa`

Dependencies:

- `mahasiswa_riwayat_pendidikan`
- references

Natural key:

- `id_registrasi_mahasiswa`, `id_semester`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_registrasi_mahasiswa` | uuid | yes | `GetListRiwayatPendidikanMahasiswa` |
| `id_semester` | char(5) | yes | `GetSemester` |
| `id_status_mahasiswa` | char(1) | yes | `GetStatusMahasiswa` |
| `ips` | double | no | 0..4 warning |
| `ipk` | double | no | 0..4 warning |
| `sks_semester` | numeric(3,0) | no |  |
| `total_sks` | numeric(3,0) | no | dokumen juga menampilkan `sks_total` pada read |
| `biaya_kuliah_smt` | numeric(16,2) | yes |  |

## 8.11 mahasiswa_lulus_do

Operations:

- get list: `GetListMahasiswaLulusDO`
- get detail: `GetDetailMahasiswaLulusDO`
- insert: `InsertMahasiswaLulusDO`
- update: `UpdateMahasiswaLulusDO`
- delete: `DeleteMahasiswaLulusDO`

Dependencies:

- `mahasiswa_riwayat_pendidikan`
- references

Natural key:

- `id_registrasi_mahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_registrasi_mahasiswa` | uuid | yes | `GetListRiwayatPendidikanMahasiswa` |
| `id_jenis_keluar` | char(1) | yes | `GetJenisKeluar` |
| `tanggal_keluar` | date | yes | `yyyy-mm-dd` |
| `id_periode_keluar` | numeric(5,0) | yes | `GetListPeriodePerkuliahan`/semester |
| `keterangan` | string(128) | no |  |
| `nomor_sk_yudisium` | string(80) | no |  |
| `tanggal_sk_yudisium` | date | no | `yyyy-mm-dd` |
| `ipk` | double | no | 0..4 warning |
| `nomor_ijazah` | string(80) | no |  |
| `jalur_skripsi` | numeric(1,0) | no |  |
| `judul_skripsi` | string(500) | no |  |
| `bulan_awal_bimbingan` | date | no | `yyyy-mm-dd` |
| `bulan_akhir_bimbingan` | date | no | `yyyy-mm-dd` |

## 9. Channel Contracts Phase 1 Standard/Advanced

## 9.1 nilai_transfer

Operations:

- get: `GetNilaiTransferPendidikanMahasiswa`
- insert: `InsertNilaiTransferPendidikanMahasiswa`
- update: `UpdateNilaiTransferPendidikanMahasiswa`
- delete: `DeleteNilaiTransferPendidikanMahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_transfer` | uuid | update/delete | primary identity |
| `id_registrasi_mahasiswa` | uuid | yes | `GetListRiwayatPendidikanMahasiswa` |
| `kode_mata_kuliah_asal` | string(20) | yes |  |
| `nama_mata_kuliah_asal` | string(200) | yes |  |
| `sks_mata_kuliah_asal` | numeric(2,0) | yes |  |
| `nilai_huruf_asal` | string(3) | yes |  |
| `id_matkul` | uuid | yes | `GetListMataKuliah` |
| `sks_mata_kuliah_diakui` | numeric(2,0) | yes |  |
| `nilai_huruf_diakui` | string(3) | yes |  |
| `nilai_angka_diakui` | numeric(5,2) | yes |  |
| `id_perguruan_tinggi` | uuid | no | `GetAllPT` |

## 9.2 aktivitas_mahasiswa

Operations:

- get: `GetListAktivitasMahasiswa`
- insert: `InsertAktivitasMahasiswa`
- update: `UpdateAktivitasMahasiswa`
- delete: `DeleteAktivitasMahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_aktivitas` | uuid | update/delete | primary identity |
| `jenis_anggota` | numeric | yes | `0` personal, `1` kelompok |
| `id_jenis_aktivitas` | numeric | yes | `GetJenisAktivitasMahasiswa` |
| `id_prodi` | uuid | yes | `GetProdi` |
| `id_semester` | char(5) | yes | `GetSemester` |
| `judul` | string | yes |  |
| `keterangan` | text | no |  |
| `lokasi` | string | no |  |
| `sk_tugas` | string | no |  |
| `tanggal_sk_tugas` | date | no | `yyyy-mm-dd` |

## 9.3 anggota_aktivitas

Operations:

- get: `GetListAnggotaAktivitasMahasiswa`
- insert: `InsertAnggotaAktivitasMahasiswa`
- delete: `DeleteAnggotaAktivitasMahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_anggota` | uuid | delete | primary identity if returned |
| `id_aktivitas` | uuid | yes | `GetListAktivitasMahasiswa` |
| `id_registrasi_mahasiswa` | uuid | yes | `GetListRiwayatPendidikanMahasiswa` |
| `jenis_peran` | char | yes | `1` ketua, `2` anggota, `3` personal |

## 9.4 bimbing_mahasiswa

Operations:

- get: `GetListBimbingMahasiswa`
- insert: `InsertBimbingMahasiswa`
- delete: `DeleteBimbingMahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_bimbing_mahasiswa` | uuid | delete | primary identity |
| `id_aktivitas` | uuid | yes | `GetListAktivitasMahasiswa` |
| `id_kategori_kegiatan` | integer | yes | `GetKategoriKegiatan` |
| `id_dosen` | uuid | yes | `GetListDosen` |
| `pembimbing_ke` | numeric | yes |  |

## 9.5 uji_mahasiswa

Operations:

- get: `GetListUjiMahasiswa`
- insert: `InsertUjiMahasiswa`
- delete: `DeleteUjiMahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_uji` | uuid | delete | primary identity if returned |
| `id_aktivitas` | uuid | yes | `GetListAktivitasMahasiswa` |
| `id_kategori_kegiatan` | integer | yes | `GetKategoriKegiatan` |
| `id_dosen` | uuid | yes | `GetListDosen` |
| `penguji_ke` | numeric | yes |  |

## 9.6 prestasi_mahasiswa

Operations:

- get: `GetListPrestasiMahasiswa`
- insert: `InsertPrestasiMahasiswa`
- update: `UpdatePrestasiMahasiswa`
- delete: `DeletePrestasiMahasiswa`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_prestasi` | uuid | update/delete | primary identity |
| `id_mahasiswa` | uuid | yes | `GetListMahasiswa`/local identity |
| `id_jenis_prestasi` | integer | yes | `GetJenisPrestasi` |
| `id_tingkat_prestasi` | integer | yes | `GetTingkatPrestasi` |
| `nama_prestasi` | string | yes |  |
| `tahun_prestasi` | numeric | yes |  |
| `penyelenggara` | string | no |  |
| `peringkat` | integer | no |  |
| `id_aktivitas` | uuid | no | `GetListAktivitasMahasiswa` |

## 9.7 skala_nilai_prodi

Operations:

- get: `GetListSkalaNilaiProdi`
- insert: `InsertSkalaNilaiProdi`
- update: `UpdateSkalaNilaiProdi`
- delete: `DeleteSkalaNilaiProdi`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_bobot_nilai` | uuid | update/delete | primary identity |
| `id_prodi` | uuid | yes | `GetProdi` |
| `nilai_huruf` | string(3) | yes |  |
| `nilai_indeks` | numeric(4,2) | no |  |
| `bobot_minimum` | numeric(5,2) | yes |  |
| `bobot_maksimum` | numeric(5,2) | yes |  |
| `tanggal_mulai_efektif` | date | yes | `yyyy-mm-dd` |
| `tanggal_akhir_efektif` | date | yes | `yyyy-mm-dd` |

## 9.8 rencana_pembelajaran

Operations:

- get: `GetListRencanaPembelajaran`
- insert: `InsertRencanaPembelajaran`
- update: `UpdateRencanaPembelajaran`
- delete: `DeleteRencanaPembelajaran`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_rencana_ajar` | uuid | update/delete | primary identity |
| `id_matkul` | uuid | yes | `GetListMataKuliah` |
| `pertemuan` | numeric(2,0) | no |  |
| `materi_indonesia` | string(1000) | no |  |
| `materi_inggris` | string(1000) | no |  |

## 9.9 rencana_evaluasi

Operations:

- get: `GetListRencanaEvaluasi`
- insert: `InsertRencanaEvaluasi`
- update: `UpdateRencanaEvaluasi`

Payload mode:

- insert: `record_array`
- update: `key_record_array`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_matkul` | uuid | yes | `GetListMataKuliah` |
| `id_basis_evaluasi` | numeric | yes | `GetBasisEvaluasi` |
| `komponen_evaluasi` | string(3) | conditional | `TGS`, `QIZ`, `UTS`, `UAS` |
| `deskripsi_indonesia` | string(1000) | yes |  |
| `deskripsi_inggris` | string(1000) | no |  |
| `bobot_evaluasi` | numeric(3,2) | no | 0..100 |

## 9.10 substansi_kuliah

Operations:

- get: `GetListSubstansiKuliah`
- insert: `InsertSubstansiKuliah`
- update: `UpdateSubstansiKuliah`
- delete: `DeleteSubstansiKuliah`

Fields:

| Field | Type | Required | Ref/Rule |
| --- | --- | --- | --- |
| `id_substansi` | uuid | update/delete | primary identity |
| `id_prodi` | uuid | yes | `GetProdi` |
| `nama_substansi` | string(50) | yes |  |
| `sks_mata_kuliah` | numeric(5,2) | no |  |
| `sks_tatap_muka` | numeric(5,2) | no |  |
| `sks_praktek` | numeric(5,2) | no |  |
| `sks_praktek_lapangan` | numeric(5,2) | no |  |
| `sks_simulasi` | numeric(5,2) | no |  |
| `id_jenis_substansi` | char(5) | yes | `GetJenisSubstansi` |

## 10. Phase 2 Automation Schema

## 10.1 Source Connection

```php
[
    'id' => 'uuid',
    'tenant_id' => 'uuid',
    'type' => 'mysql',
    'name' => 'SIAKAD Kampus',
    'encrypted_config' => '...',
    'status' => 'draft',
    'last_tested_at' => null,
]
```

Supported source type:

```text
xlsx
csv
mysql
mariadb
postgres
sqlserver
api
```

Prioritas otomatisasi awal adalah MySQL/MariaDB karena banyak SIAKAD lama memakai stack tersebut.

## 10.2 Source Profile

```php
[
    'id' => 'uuid',
    'tenant_id' => 'uuid',
    'source_connection_id' => 'uuid',
    'version' => 1,
    'discovered_at' => '2026-09-08 19:00:00',
    'tables' => [
        [
            'name' => 'mahasiswa',
            'row_count' => 12000,
            'primary_key_candidates' => ['id', 'nim'],
            'fields' => [
                [
                    'name' => 'nim',
                    'type' => 'varchar(24)',
                    'nullable' => false,
                    'sample_values' => ['2310001', '2310002'],
                    'distinct_count' => 12000,
                ],
            ],
        ],
    ],
]
```

## 10.3 Mapping Profile

```php
[
    'id' => 'uuid',
    'tenant_id' => 'uuid',
    'source_profile_id' => 'uuid',
    'target_channel' => 'mahasiswa_biodata',
    'version' => 1,
    'status' => 'draft',
    'rules' => [
        [
            'target_field' => 'nama_mahasiswa',
            'source_expression' => 'mahasiswa.nama',
            'constant_value' => null,
            'transform' => [
                ['type' => 'trim', 'config' => []],
                ['type' => 'uppercase_words', 'config' => []],
            ],
            'resolver' => null,
        ],
        [
            'target_field' => 'id_agama',
            'source_expression' => 'mahasiswa.agama',
            'transform' => [
                ['type' => 'trim', 'config' => []],
            ],
            'resolver' => [
                'reference_endpoint' => 'GetAgama',
                'match_by' => ['id_agama', 'nama_agama'],
                'on_ambiguous' => 'manual_review',
                'on_missing' => 'error',
            ],
        ],
    ],
]
```

Transform rule awal:

```text
trim
uppercase
lowercase
uppercase_words
date_parse
enum_map
concat
split
number_parse
left_pad
right_pad
null_if_empty
```

## 10.4 Lineage

Setiap staging row dari otomatisasi harus menyimpan:

- source connection,
- source profile version,
- mapping profile version,
- source table/query,
- source primary key,
- raw source row,
- transformed row,
- validation result,
- payload request,
- Neo Feeder response.

## 11. Local Database Draft

Tabel inti:

```text
tenants
users
tenant_memberships
neofeeder_connections
contract_versions
channel_contracts
reference_snapshots
reference_records
template_versions
uploaded_files
import_batches
staging_records
validation_results
sync_batches
sync_jobs
sync_attempts
sync_identities
source_connections
source_profiles
mapping_profiles
mapping_rules
automation_schedules
audit_logs
```

## 12. Status Model

### Import Batch

```text
draft
uploaded
parsing
parsed
validating
validated
ready
syncing
partial_success
success
failed
cancelled
```

### Staging Record

```text
pending
parsed
invalid
valid
ready
syncing
success
failed
skipped
ambiguous
```

### Sync Attempt

```text
queued
running
success
failed
retrying
cancelled
```

## 13. Trial Strategy

Trial urutan kecil:

1. `GetToken`
2. `GetProfilPT`
3. `GetProdi`
4. `GetSemester`
5. `GetAgama`
6. `InsertBiodataMahasiswa`
7. Simpan `id_mahasiswa`
8. `InsertRiwayatPendidikanMahasiswa`
9. Simpan `id_registrasi_mahasiswa`
10. Cek dengan `GetListMahasiswa` dan `GetListRiwayatPendidikanMahasiswa`

Setelah itu baru lanjut:

1. `InsertMataKuliah`
2. `InsertKurikulum`
3. `InsertMatkulKurikulum`
4. `InsertKelasKuliah`
5. `InsertPesertaKelasKuliah`
6. `UpdateNilaiPerkuliahanKelas`
7. `InsertPerkuliahanMahasiswa`

## 14. Known Verification Items

Hal yang harus diverifikasi langsung dengan token trial:

- apakah semua operasi menerima payload JSON dengan `act`, `token`, `record`, dan `key` seperti asumsi contract,
- apakah numeric lebih aman dikirim sebagai string atau number,
- apakah empty optional field harus dihilangkan atau dikirim `null`,
- nama field response identity untuk setiap insert,
- error code aktual untuk duplicate data,
- perilaku update jika field optional dikirim `null`,
- timeout dan batas batch aman untuk endpoint tenant.
