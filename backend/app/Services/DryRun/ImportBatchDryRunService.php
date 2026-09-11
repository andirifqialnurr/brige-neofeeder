<?php

namespace App\Services\DryRun;

use App\Models\ImportBatch;
use App\Models\StagingRecord;
use App\Services\NeoFeeder\Contracts\ChannelContract;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use App\Services\NeoFeeder\Contracts\OperationContract;
use App\Services\NeoFeeder\Payloads\NeoFeederPayloadBuilder;

final class ImportBatchDryRunService
{
    public function __construct(
        private readonly NeoFeederContractRegistry $registry,
        private readonly NeoFeederPayloadBuilder $payloadBuilder,
    ) {
    }

    public function preview(ImportBatch $batch): array
    {
        $records = $batch->stagingRecords()->orderBy('channel')->orderBy('row_number')->get();
        $payloads = $records->map(fn (StagingRecord $record): array => $this->previewRecord($record))->values();
        $summary = [
            'total_rows' => $records->count(),
            'valid_rows' => $records->where('status', 'valid')->count(),
            'invalid_rows' => $records->where('status', 'invalid')->count(),
            'warning_rows' => $records->filter(fn (StagingRecord $record): bool => ($record->validation_result['warnings'] ?? []) !== [])->count(),
        ];

        $dryRun = [
            'import_batch_id' => $batch->id,
            'summary' => $summary,
            'dependency_order' => $this->registry->dependencyOrder(),
            'payload_preview' => $payloads,
            'missing_references' => $this->missingReferences($records->all()),
            'requires_operator_approval' => true,
            'approved' => false,
        ];

        $batch->forceFill([
            'status' => 'dry_run_ready',
            'summary' => [
                ...($batch->summary ?? []),
                'dry_run' => [
                    'summary' => $summary,
                    'requires_operator_approval' => true,
                    'approved' => false,
                ],
            ],
        ])->save();

        return $dryRun;
    }

    private function previewRecord(StagingRecord $record): array
    {
        $channel = $this->registry->channel($record->channel);
        $candidate = $this->candidateOperation($record, $channel);

        return [
            'staging_record_id' => $record->id,
            'channel' => $record->channel,
            'sheet_name' => $record->sheet_name,
            'row_number' => $record->row_number,
            'status' => $record->status,
            'candidate_operation' => $candidate,
            'action' => $candidate === 'skip' || ! $channel instanceof ChannelContract ? null : $this->operation($channel, $candidate)?->action,
            'payload' => $candidate === 'skip' || ! $channel instanceof ChannelContract ? null : $this->payload($channel, $record, $candidate),
            'validation_result' => $record->validation_result ?? ['errors' => [], 'warnings' => [], 'info' => []],
        ];
    }

    private function candidateOperation(StagingRecord $record, ?ChannelContract $channel): string
    {
        if ($record->status !== 'valid' || ! $channel instanceof ChannelContract) {
            return 'skip';
        }

        $row = $record->normalized_row ?? [];

        if ($channel->identityFields !== [] && collect($channel->identityFields)->every(fn (string $field): bool => filled($row[$field] ?? null))) {
            return 'update';
        }

        return 'insert';
    }

    private function payload(ChannelContract $channel, StagingRecord $record, string $candidate): ?array
    {
        $operation = $this->operation($channel, $candidate);

        if (! $operation instanceof OperationContract) {
            return null;
        }

        return $candidate === 'update'
            ? $this->payloadBuilder->buildUpdate($channel, $operation, $record->normalized_row ?? [])
            : $this->payloadBuilder->buildInsert($channel, $operation, $record->normalized_row ?? []);
    }

    private function operation(ChannelContract $channel, string $type): ?OperationContract
    {
        foreach ($channel->operations as $payload) {
            $operation = OperationContract::fromArray($payload);

            if ($operation->type === $type) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * @param  list<StagingRecord>  $records
     * @return list<array<string, mixed>>
     */
    private function missingReferences(array $records): array
    {
        $missing = [];

        foreach ($records as $record) {
            foreach (($record->validation_result['errors'] ?? []) as $issue) {
                if (($issue['rule'] ?? null) !== 'reference_exists') {
                    continue;
                }

                $missing[] = [
                    'staging_record_id' => $record->id,
                    'channel' => $record->channel,
                    'sheet_name' => $record->sheet_name,
                    'row_number' => $record->row_number,
                    'field' => $issue['field'] ?? null,
                    'message' => $issue['message'] ?? null,
                ];
            }
        }

        return $missing;
    }
}
