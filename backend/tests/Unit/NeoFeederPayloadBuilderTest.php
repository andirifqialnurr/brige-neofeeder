<?php

namespace Tests\Unit;

use App\Services\NeoFeeder\Contracts\OperationContract;
use App\Services\NeoFeeder\Payloads\NeoFeederPayloadBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NeoFeederPayloadBuilderTest extends TestCase
{
    public function test_it_builds_filter_from_read_list_key_fields(): void
    {
        $payload = (new NeoFeederPayloadBuilder())->buildReadList(
            new OperationContract(
                name: 'get_detail',
                action: 'GetDetailNilaiPerkuliahanKelas',
                type: 'read',
                payloadMode: 'filter',
                keyFields: ['id_kelas_kuliah', 'id_registrasi_mahasiswa'],
            ),
            [
                'id_kelas_kuliah' => 'kelas-1',
                'id_registrasi_mahasiswa' => "reg'2",
            ],
        );

        $this->assertSame([
            'filter' => "id_kelas_kuliah='kelas-1' and id_registrasi_mahasiswa='reg''2'",
        ], $payload);
    }

    public function test_it_keeps_raw_filter_and_pagination_options(): void
    {
        $payload = (new NeoFeederPayloadBuilder())->buildReadList(
            new OperationContract(
                name: 'get',
                action: 'GetListMataKuliah',
                type: 'list',
                payloadMode: 'filter',
                keyFields: ['id_matkul'],
            ),
            [
                'filter' => "kode_mata_kuliah like 'IF%'",
                'order' => 'kode_mata_kuliah asc',
                'limit' => 50,
                'offset' => 100,
            ],
        );

        $this->assertSame([
            'filter' => "kode_mata_kuliah like 'IF%'",
            'order' => 'kode_mata_kuliah asc',
            'limit' => 50,
            'offset' => 100,
        ], $payload);
    }

    public function test_it_rejects_non_read_list_operations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NeoFeederPayloadBuilder())->buildReadList(
            new OperationContract(
                name: 'insert',
                action: 'InsertMataKuliah',
                type: 'insert',
                payloadMode: 'record',
            ),
        );
    }
}
