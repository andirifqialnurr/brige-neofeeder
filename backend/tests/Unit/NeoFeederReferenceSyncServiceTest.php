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

    public function test_it_rejects_unknown_reference_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NeoFeederReferenceSyncService())->normalize('GetUnknownEndpoint', []);
    }
}
