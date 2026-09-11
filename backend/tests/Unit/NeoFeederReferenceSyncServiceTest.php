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

    public function test_it_rejects_unknown_reference_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NeoFeederReferenceSyncService())->normalize('GetUnknownEndpoint', []);
    }
}
