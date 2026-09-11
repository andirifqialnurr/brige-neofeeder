<?php

namespace Tests\Unit;

use App\Services\NeoFeeder\Contracts\ChannelContract;
use App\Services\NeoFeeder\Contracts\DictionaryContractComparator;
use PHPUnit\Framework\TestCase;

class DictionaryContractComparatorTest extends TestCase
{
    public function test_it_compares_channel_contract_against_dictionary_payload(): void
    {
        $result = (new DictionaryContractComparator())->compare($this->courseChannel(), [
            'data' => [
                [
                    'act' => 'GetListMataKuliah',
                    'request' => [
                        ['field' => 'id_matkul'],
                        ['field' => 'kode_mata_kuliah'],
                    ],
                ],
                [
                    'act' => 'InsertMataKuliah',
                    'record' => [
                        ['name' => 'kode_mata_kuliah'],
                        ['name' => 'nama_mata_kuliah'],
                        ['name' => 'id_prodi'],
                        ['name' => 'sks_mata_kuliah'],
                        ['name' => 'field_runtime_baru'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('mata_kuliah', $result['channel']);
        $this->assertSame(['DeleteMataKuliah', 'UpdateMataKuliah'], $result['missing_operations']);
        $this->assertSame([], $result['extra_operations']);
        $this->assertSame([], $result['missing_fields']);
        $this->assertSame(['field_runtime_baru'], $result['extra_fields']);
    }

    public function test_it_supports_dictionary_field_lists_as_strings(): void
    {
        $result = (new DictionaryContractComparator())->compare($this->courseChannel(), [
            'data' => [
                [
                    'nama_fungsi' => 'GetListMataKuliah',
                    'fields' => ['id_matkul', 'kode_mata_kuliah'],
                ],
            ],
        ]);

        $this->assertContains('nama_mata_kuliah', $result['missing_fields']);
        $this->assertContains('sks_mata_kuliah', $result['missing_fields']);
    }

    private function courseChannel(): ChannelContract
    {
        return new ChannelContract(
            key: 'mata_kuliah',
            label: 'Mata Kuliah',
            fields: [
                ['name' => 'id_matkul', 'label' => 'ID Mata Kuliah'],
                ['name' => 'kode_mata_kuliah', 'label' => 'Kode Mata Kuliah'],
                ['name' => 'nama_mata_kuliah', 'label' => 'Nama Mata Kuliah'],
                ['name' => 'id_prodi', 'label' => 'Program Studi'],
                ['name' => 'sks_mata_kuliah', 'label' => 'SKS Mata Kuliah'],
            ],
            operations: [
                ['name' => 'get', 'action' => 'GetListMataKuliah', 'type' => 'list'],
                ['name' => 'insert', 'action' => 'InsertMataKuliah', 'type' => 'insert'],
                ['name' => 'update', 'action' => 'UpdateMataKuliah', 'type' => 'update'],
                ['name' => 'delete', 'action' => 'DeleteMataKuliah', 'type' => 'delete'],
            ],
        );
    }
}
