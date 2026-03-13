<?php

namespace App\Services\Imports;

use App\Models\ImportJob;
use App\Services\Bitrix24\Bitrix24Service;
use Illuminate\Support\Facades\Storage;

class SmartProcessImportProcessor
{
    public function __construct(
        private readonly Bitrix24Service $bitrix24,
        private readonly ExcelImportService $excelImport,
    ) {
    }

    public function process(ImportJob $importJob): void
    {
        $portal = $importJob->portal;
        $absolutePath = Storage::disk('local')->path($importJob->source_file_path);
        $batchSize = max(1, (int) config('bitrix24.batch_size', 50));

        $payload = $this->excelImport->readRows($absolutePath);
        $fieldMap = $this->bitrix24->getItemFields($portal, $importJob->entity_type_id);

        $importJob->forceFill([
            'status' => ImportJob::STATUS_RUNNING,
            'started_at' => now(),
            'header_map' => $payload['headers'],
            'total_rows' => count($payload['rows']),
            'processed_rows' => 0,
            'success_rows' => 0,
            'error_rows' => 0,
            'meta' => array_merge($importJob->meta ?? [], [
                'batch_size' => $batchSize,
            ]),
        ])->save();

        $processed = 0;
        $success = 0;
        $errors = 0;
        $batchRows = [];

        foreach ($payload['rows'] as $row) {
            $normalized = $this->excelImport->normalizeRow($row['values'], $fieldMap);

            if ($normalized['errors'] !== []) {
                $this->recordError($importJob, $row['rowNumber'], $normalized['errors'], $row['values']);
                $processed++;
                $errors++;
                $this->syncProgress($importJob, $processed, $success, $errors);
                continue;
            }

            $batchRows[] = [
                'rowNumber' => $row['rowNumber'],
                'values' => $row['values'],
                'fields' => $normalized['fields'],
            ];

            if (count($batchRows) >= $batchSize) {
                [$processed, $success, $errors] = $this->processBatch($importJob, $batchRows, $processed, $success, $errors);
                $batchRows = [];
            }
        }

        if ($batchRows !== []) {
            [$processed, $success, $errors] = $this->processBatch($importJob, $batchRows, $processed, $success, $errors);
        }

        $errorFilePath = null;
        if ($errors > 0) {
            $errorFilePath = $this->excelImport->generateErrorReport($importJob->fresh());
        }

        $importJob->forceFill([
            'status' => ImportJob::STATUS_COMPLETED,
            'processed_rows' => $processed,
            'success_rows' => $success,
            'error_rows' => $errors,
            'error_file_path' => $errorFilePath,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * @param  array<int, array{rowNumber:int,values:array<string,mixed>,fields:array<string,mixed>}>  $batchRows
     * @return array{0:int,1:int,2:int}
     */
    private function processBatch(ImportJob $importJob, array $batchRows, int $processed, int $success, int $errors): array
    {
        try {
            $result = $this->bitrix24->addItemsBatch($importJob->portal, $importJob->entity_type_id, $batchRows);
        } catch (\Throwable $exception) {
            foreach ($batchRows as $row) {
                $this->recordError($importJob, $row['rowNumber'], [$exception->getMessage()], $row['values']);
                $processed++;
                $errors++;
            }

            $this->syncProgress($importJob, $processed, $success, $errors);

            return [$processed, $success, $errors];
        }

        foreach ($batchRows as $index => $row) {
            $command = 'add'.$index;
            $error = $result['errors'][$command] ?? null;

            if ($error) {
                if (is_array($error)) {
                    $errorText = (string) ($error['error_description'] ?? $error['error'] ?? json_encode($error, JSON_UNESCAPED_UNICODE));
                } else {
                    $errorText = (string) $error;
                }

                $this->recordError($importJob, $row['rowNumber'], [$errorText], $row['values']);
                $errors++;
            } else {
                $success++;
            }

            $processed++;
        }

        $this->syncProgress($importJob, $processed, $success, $errors);

        return [$processed, $success, $errors];
    }

    /**
     * @param  array<int,string>  $errors
     * @param  array<string,mixed>  $rowPayload
     */
    private function recordError(ImportJob $importJob, int $rowNumber, array $errors, array $rowPayload): void
    {
        $uniqueErrors = array_values(array_unique(array_map(
            static fn (string $message): string => trim($message),
            $errors,
        )));

        $importJob->errors()->create([
            'row_number' => $rowNumber,
            'error_message' => implode('; ', $uniqueErrors),
            'row_payload' => $rowPayload,
        ]);
    }

    private function syncProgress(ImportJob $importJob, int $processed, int $success, int $errors): void
    {
        $importJob->forceFill([
            'processed_rows' => $processed,
            'success_rows' => $success,
            'error_rows' => $errors,
        ])->save();
    }
}
