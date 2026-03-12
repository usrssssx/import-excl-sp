<?php

namespace App\Services\Imports;

use App\Models\ImportJob;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelImportService
{
    /**
     * @return array{headers:array<int,array{header:string,title:string,code:string,column:int}>,rows:array<int,array{rowNumber:int,values:array<string,mixed>}>}
     */
    public function readRows(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheet(0);

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $highestRow = (int) $sheet->getHighestRow();

        $headers = [];

        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $rawHeader = trim((string) $sheet->getCellByColumnAndRow($column, 1)->getCalculatedValue());
            if ($rawHeader === '') {
                continue;
            }

            $parsed = $this->parseHeader($rawHeader);
            $headers[] = [
                'header' => $rawHeader,
                'title' => $parsed['title'],
                'code' => $parsed['code'],
                'column' => $column,
            ];
        }

        $rows = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = [];
            $hasAnyValue = false;

            foreach ($headers as $header) {
                $cell = $sheet->getCellByColumnAndRow($header['column'], $rowNumber);
                $value = $cell->getCalculatedValue();

                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($value !== null && $value !== '') {
                    $hasAnyValue = true;
                }

                $values[$header['code']] = $value;
            }

            if (! $hasAnyValue) {
                continue;
            }

            $rows[] = [
                'rowNumber' => $rowNumber,
                'values' => $values,
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string,mixed>  $rowValues
     * @param  array<string, array{code:string,apiCode:string,title:string,type:string,isRequired:bool,isMultiple:bool,items:array<int,array{id:string,title:string}>}>  $fieldMap
     * @return array{fields:array<string,mixed>,errors:array<int,string>}
     */
    public function normalizeRow(array $rowValues, array $fieldMap): array
    {
        $fields = [];
        $errors = [];

        foreach ($fieldMap as $displayCode => $meta) {
            $value = $rowValues[$displayCode] ?? null;

            if ($this->isEmpty($value)) {
                if ($meta['isRequired']) {
                    $errors[] = sprintf('Поле "%s" (%s) обязательно.', $meta['title'], $displayCode);
                }

                continue;
            }

            [$normalizedValue, $castError] = $this->castValue($value, $meta);

            if ($castError !== null) {
                $errors[] = $castError;
                continue;
            }

            $fields[$meta['apiCode']] = $normalizedValue;
        }

        return [
            'fields' => $fields,
            'errors' => $errors,
        ];
    }

    public function generateErrorReport(ImportJob $importJob): string
    {
        $headers = $importJob->header_map ?? [];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ошибки');

        $headerCells = array_map(static fn (array $header): string => (string) ($header['header'] ?? ($header['title'].' ('.$header['code'].')')), $headers);
        $headerCells[] = 'Ошибка';

        $sheet->fromArray($headerCells, null, 'A1');

        $rowPointer = 2;
        $errors = $importJob->errors()->orderBy('row_number')->get();

        foreach ($errors as $error) {
            $payload = $error->row_payload ?? [];
            $row = [];

            foreach ($headers as $header) {
                $cellValue = $payload[$header['code']] ?? null;

                if (is_array($cellValue)) {
                    $cellValue = implode('; ', array_map(static fn ($item): string => (string) $item, $cellValue));
                }

                $row[] = $cellValue;
            }

            $row[] = $error->error_message;
            $sheet->fromArray($row, null, 'A'.$rowPointer);
            $rowPointer++;
        }

        $columnsTotal = count($headerCells);
        for ($column = 1; $column <= $columnsTotal; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $relativePath = 'private/errors/import_'.$importJob->uuid.'_errors.xlsx';
        $absolutePath = storage_path('app/'.$relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        (new Xlsx($spreadsheet))->save($absolutePath);

        return $relativePath;
    }

    /**
     * @return array{title:string,code:string}
     */
    private function parseHeader(string $rawHeader): array
    {
        if (preg_match('/^(.*)\(([^)]+)\)\s*$/u', $rawHeader, $matches) === 1) {
            return [
                'title' => trim($matches[1]),
                'code' => trim($matches[2]),
            ];
        }

        return [
            'title' => $rawHeader,
            'code' => $rawHeader,
        ];
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{code:string,apiCode:string,title:string,type:string,isRequired:bool,isMultiple:bool,items:array<int,array{id:string,title:string}>}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castValue(mixed $value, array $meta): array
    {
        if ($meta['isMultiple']) {
            $parts = is_array($value)
                ? $value
                : preg_split('/\s*;\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

            $normalized = [];
            foreach ($parts as $part) {
                [$partValue, $error] = $this->castSingleValue($part, $meta);
                if ($error !== null) {
                    return [null, $error];
                }

                $normalized[] = $partValue;
            }

            return [$normalized, null];
        }

        return $this->castSingleValue($value, $meta);
    }

    /**
     * @param  array{code:string,apiCode:string,title:string,type:string,isRequired:bool,isMultiple:bool,items:array<int,array{id:string,title:string}>}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castSingleValue(mixed $value, array $meta): array
    {
        $type = $meta['type'];

        return match ($type) {
            'integer' => $this->castInteger($value, $meta),
            'double', 'money' => $this->castFloat($value, $meta),
            'boolean' => $this->castBoolean($value, $meta),
            'date' => $this->castDate($value, $meta, false),
            'datetime' => $this->castDate($value, $meta, true),
            'enumeration', 'list', 'crm_status' => $this->castEnum($value, $meta),
            'employee', 'user' => $this->castInteger($value, $meta),
            default => [(string) $value, null],
        };
    }

    /**
     * @param  array{code:string,title:string}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castInteger(mixed $value, array $meta): array
    {
        if (is_numeric($value)) {
            return [(int) $value, null];
        }

        return [null, sprintf('Поле "%s" (%s): ожидается целое число.', $meta['title'], $meta['code'])];
    }

    /**
     * @param  array{code:string,title:string}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castFloat(mixed $value, array $meta): array
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        if (is_numeric($value)) {
            return [(float) $value, null];
        }

        return [null, sprintf('Поле "%s" (%s): ожидается число.', $meta['title'], $meta['code'])];
    }

    /**
     * @param  array{code:string,title:string}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castBoolean(mixed $value, array $meta): array
    {
        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'y', 'yes', 'true', 'да'], true)) {
            return ['Y', null];
        }

        if (in_array($normalized, ['0', 'n', 'no', 'false', 'нет'], true)) {
            return ['N', null];
        }

        return [null, sprintf('Поле "%s" (%s): ожидается булево значение (да/нет).', $meta['title'], $meta['code'])];
    }

    /**
     * @param  array{code:string,title:string}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castDate(mixed $value, array $meta, bool $withTime): array
    {
        try {
            if (is_numeric($value)) {
                $date = Carbon::instance(Date::excelToDateTimeObject((float) $value));
            } else {
                $date = Carbon::parse((string) $value);
            }

            return [$withTime ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d'), null];
        } catch (\Throwable) {
            return [null, sprintf('Поле "%s" (%s): неверный формат даты.', $meta['title'], $meta['code'])];
        }
    }

    /**
     * @param  array{code:string,title:string,items:array<int,array{id:string,title:string}>}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castEnum(mixed $value, array $meta): array
    {
        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return [null, null];
        }

        foreach ($meta['items'] as $item) {
            if ((string) $item['id'] === $stringValue) {
                return [$item['id'], null];
            }

            if (mb_strtolower($item['title']) === mb_strtolower($stringValue)) {
                return [$item['id'], null];
            }
        }

        return [null, sprintf('Поле "%s" (%s): значение "%s" не найдено в списке.', $meta['title'], $meta['code'], $stringValue)];
    }
}
