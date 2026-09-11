<?php

namespace Tests\Unit;

use App\Services\NeoFeeder\Contracts\ChannelContract;
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

    public function test_it_builds_insert_record_from_channel_fields(): void
    {
        $payload = (new NeoFeederPayloadBuilder())->buildInsert(
            $this->courseChannel(),
            new OperationContract(
                name: 'insert',
                action: 'InsertMataKuliah',
                type: 'insert',
                payloadMode: 'record',
            ),
            [
                'id_matkul' => 'neo-generated-id',
                'kode_mata_kuliah' => 'IF101',
                'nama_mata_kuliah' => 'Algoritma',
                'id_prodi' => 'prodi-1',
                'sks_mata_kuliah' => 3,
                'sks_tatap_muka' => 2,
                'unknown_column' => 'ignored',
            ],
        );

        $this->assertSame([
            'record' => [
                'kode_mata_kuliah' => 'IF101',
                'nama_mata_kuliah' => 'Algoritma',
                'id_prodi' => 'prodi-1',
                'sks_mata_kuliah' => 3,
                'sks_tatap_muka' => 2,
            ],
        ], $payload);
    }

    public function test_it_rejects_insert_records_with_missing_required_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required field [nama_mata_kuliah] is missing');

        (new NeoFeederPayloadBuilder())->buildInsert(
            $this->courseChannel(),
            new OperationContract(
                name: 'insert',
                action: 'InsertMataKuliah',
                type: 'insert',
                payloadMode: 'record',
            ),
            [
                'kode_mata_kuliah' => 'IF101',
                'id_prodi' => 'prodi-1',
                'sks_mata_kuliah' => 3,
            ],
        );
    }

    public function test_it_builds_update_key_record_payload(): void
    {
        $payload = (new NeoFeederPayloadBuilder())->buildUpdate(
            $this->courseChannel(),
            new OperationContract(
                name: 'update',
                action: 'UpdateMataKuliah',
                type: 'update',
                payloadMode: 'key_record',
                keyFields: ['id_matkul'],
            ),
            [
                'id_matkul' => 'matkul-1',
                'kode_mata_kuliah' => 'IF101',
                'nama_mata_kuliah' => 'Algoritma Lanjut',
            ],
        );

        $this->assertSame([
            'key' => [
                'id_matkul' => 'matkul-1',
            ],
            'record' => [
                'kode_mata_kuliah' => 'IF101',
                'nama_mata_kuliah' => 'Algoritma Lanjut',
            ],
        ], $payload);
    }

    public function test_it_rejects_update_payloads_with_missing_key_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required key field [id_matkul] is missing');

        (new NeoFeederPayloadBuilder())->buildUpdate(
            $this->courseChannel(),
            new OperationContract(
                name: 'update',
                action: 'UpdateMataKuliah',
                type: 'update',
                payloadMode: 'key_record',
                keyFields: ['id_matkul'],
            ),
            [
                'nama_mata_kuliah' => 'Algoritma Lanjut',
            ],
        );
    }

    public function test_it_rejects_empty_update_records(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update record is empty');

        (new NeoFeederPayloadBuilder())->buildUpdate(
            $this->courseChannel(),
            new OperationContract(
                name: 'update',
                action: 'UpdateMataKuliah',
                type: 'update',
                payloadMode: 'key_record',
                keyFields: ['id_matkul'],
            ),
            [
                'id_matkul' => 'matkul-1',
            ],
        );
    }

    public function test_it_builds_delete_key_payload(): void
    {
        $payload = (new NeoFeederPayloadBuilder())->buildDelete(
            new OperationContract(
                name: 'delete',
                action: 'DeleteMataKuliah',
                type: 'delete',
                payloadMode: 'key_only',
                keyFields: ['id_matkul'],
            ),
            [
                'id_matkul' => 'matkul-1',
                'nama_mata_kuliah' => 'ignored',
            ],
        );

        $this->assertSame([
            'key' => [
                'id_matkul' => 'matkul-1',
            ],
        ], $payload);
    }

    public function test_it_rejects_delete_payloads_with_missing_key_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required key field [id_matkul] is missing');

        (new NeoFeederPayloadBuilder())->buildDelete(
            new OperationContract(
                name: 'delete',
                action: 'DeleteMataKuliah',
                type: 'delete',
                payloadMode: 'key_only',
                keyFields: ['id_matkul'],
            ),
            [],
        );
    }

    private function courseChannel(): ChannelContract
    {
        return new ChannelContract(
            key: 'mata_kuliah',
            label: 'Mata Kuliah',
            fields: [
                ['name' => 'id_matkul', 'label' => 'ID Mata Kuliah', 'type' => 'uuid', 'primary' => true, 'required' => false],
                ['name' => 'kode_mata_kuliah', 'label' => 'Kode Mata Kuliah', 'type' => 'string', 'required' => true],
                ['name' => 'nama_mata_kuliah', 'label' => 'Nama Mata Kuliah', 'type' => 'string', 'required' => true],
                ['name' => 'id_prodi', 'label' => 'Program Studi', 'type' => 'uuid', 'required' => true],
                ['name' => 'sks_mata_kuliah', 'label' => 'SKS Mata Kuliah', 'type' => 'numeric', 'required' => true],
                ['name' => 'sks_tatap_muka', 'label' => 'SKS Tatap Muka', 'type' => 'numeric', 'required' => false],
            ],
        );
    }
}
