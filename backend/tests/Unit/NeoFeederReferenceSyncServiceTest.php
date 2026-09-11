<?php

namespace Tests\Unit;

use App\Services\NeoFeeder\References\NeoFeederReferenceSyncService;
use InvalidArgumentException;
use Tests\TestCase;

class NeoFeederReferenceSyncServiceTest extends TestCase
{
    public function test_it_normalizes_get_profil_pt_response(): void
    {
        $rows = (new NeoFeederReferenceSyncService())->normalize('GetProfilPT', [
            'data' => [
                'id_perguruan_tinggi' => 'pt-1',
                'kode_perguruan_tinggi' => '001001',
                'nama_perguruan_tinggi' => 'Universitas Contoh',
            ],
        ]);

        $this->assertSame([
            [
                'endpoint' => 'GetProfilPT',
                'value_key' => 'id_perguruan_tinggi',
                'value' => 'pt-1',
                'label' => 'Universitas Contoh',
                'raw_payload' => [
                    'id_perguruan_tinggi' => 'pt-1',
                    'kode_perguruan_tinggi' => '001001',
                    'nama_perguruan_tinggi' => 'Universitas Contoh',
                ],
            ],
        ], $rows);
    }

    public function test_it_normalizes_get_prodi_response(): void
    {
        $rows = (new NeoFeederReferenceSyncService())->normalize('GetProdi', [
            'data' => [
                [
                    'id_prodi' => 'prodi-1',
                    'kode_program_studi' => '55201',
                    'nama_program_studi' => 'Informatika',
                ],
                [
                    'id_prodi' => 'prodi-2',
                    'kode_program_studi' => '61201',
                    'nama_program_studi' => 'Manajemen',
                ],
            ],
        ]);

        $this->assertSame([
            [
                'endpoint' => 'GetProdi',
                'value_key' => 'id_prodi',
                'value' => 'prodi-1',
                'label' => 'Informatika',
                'raw_payload' => [
                    'id_prodi' => 'prodi-1',
                    'kode_program_studi' => '55201',
                    'nama_program_studi' => 'Informatika',
                ],
            ],
            [
                'endpoint' => 'GetProdi',
                'value_key' => 'id_prodi',
                'value' => 'prodi-2',
                'label' => 'Manajemen',
                'raw_payload' => [
                    'id_prodi' => 'prodi-2',
                    'kode_program_studi' => '61201',
                    'nama_program_studi' => 'Manajemen',
                ],
            ],
        ], $rows);
    }

    public function test_it_normalizes_get_semester_response(): void
    {
        $rows = (new NeoFeederReferenceSyncService())->normalize('GetSemester', [
            'data' => [
                [
                    'id_semester' => '20241',
                    'nama_semester' => '2024/2025 Ganjil',
                ],
            ],
        ]);

        $this->assertSame([
            [
                'endpoint' => 'GetSemester',
                'value_key' => 'id_semester',
                'value' => '20241',
                'label' => '2024/2025 Ganjil',
                'raw_payload' => [
                    'id_semester' => '20241',
                    'nama_semester' => '2024/2025 Ganjil',
                ],
            ],
        ], $rows);
    }

    public function test_it_normalizes_get_agama_response(): void
    {
        $rows = (new NeoFeederReferenceSyncService())->normalize('GetAgama', [
            'data' => [
                [
                    'id_agama' => 1,
                    'nama_agama' => 'Islam',
                ],
            ],
        ]);

        $this->assertSame([
            [
                'endpoint' => 'GetAgama',
                'value_key' => 'id_agama',
                'value' => '1',
                'label' => 'Islam',
                'raw_payload' => [
                    'id_agama' => 1,
                    'nama_agama' => 'Islam',
                ],
            ],
        ], $rows);
    }

    public function test_it_normalizes_get_negara_response(): void
    {
        $rows = (new NeoFeederReferenceSyncService())->normalize('GetNegara', [
            'data' => [
                [
                    'id_negara' => 'ID',
                    'nama_negara' => 'Indonesia',
                ],
            ],
        ]);

        $this->assertSame([
            [
                'endpoint' => 'GetNegara',
                'value_key' => 'id_negara',
                'value' => 'ID',
                'label' => 'Indonesia',
                'raw_payload' => [
                    'id_negara' => 'ID',
                    'nama_negara' => 'Indonesia',
                ],
            ],
        ], $rows);
    }

    public function test_it_normalizes_get_wilayah_response(): void
    {
        $rows = (new NeoFeederReferenceSyncService())->normalize('GetWilayah', [
            'data' => [
                [
                    'id_wilayah' => '016000',
                    'nama_wilayah' => 'Kota Batam',
                ],
            ],
        ]);

        $this->assertSame([
            [
                'endpoint' => 'GetWilayah',
                'value_key' => 'id_wilayah',
                'value' => '016000',
                'label' => 'Kota Batam',
                'raw_payload' => [
                    'id_wilayah' => '016000',
                    'nama_wilayah' => 'Kota Batam',
                ],
            ],
        ], $rows);
    }

    public function test_it_normalizes_get_jenis_tinggal_response(): void
    {
        $rows = (new NeoFeederReferenceSyncService())->normalize('GetJenisTinggal', [
            'data' => [
                [
                    'id_jenis_tinggal' => 1,
                    'nama_jenis_tinggal' => 'Bersama orang tua',
                ],
            ],
        ]);

        $this->assertSame([
            [
                'endpoint' => 'GetJenisTinggal',
                'value_key' => 'id_jenis_tinggal',
                'value' => '1',
                'label' => 'Bersama orang tua',
                'raw_payload' => [
                    'id_jenis_tinggal' => 1,
                    'nama_jenis_tinggal' => 'Bersama orang tua',
                ],
            ],
        ], $rows);
    }

    public function test_it_rejects_unknown_reference_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NeoFeederReferenceSyncService())->normalize('GetUnknownEndpoint', []);
    }
}
