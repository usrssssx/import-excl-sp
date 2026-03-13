<?php

namespace App\Services\Imports;

use App\Models\ImportJob;
use App\Services\Bitrix24\BitrixFieldCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ExcelImportService
{
    public function __construct(private readonly BitrixFieldCode $fieldCode)
    {
    }

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
            $rawHeader = trim((string) $sheet->getCell($this->cellAddress($column, 1))->getCalculatedValue());
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

        if ($headers === []) {
            throw new RuntimeException('В первой строке файла не найдены заголовки колонок. Используйте шаблон, скачанный из приложения.');
        }

        $codeCounters = [];
        foreach ($headers as $header) {
            $code = $header['code'];
            $codeCounters[$code] = ($codeCounters[$code] ?? 0) + 1;
        }

        $duplicateCodes = array_keys(array_filter($codeCounters, static fn (int $count): bool => $count > 1));
        if ($duplicateCodes !== []) {
            throw new RuntimeException(sprintf(
                'В строке заголовков найдены дубли кодов полей: %s. Проверьте шаблон и удалите дубликаты.',
                implode(', ', $duplicateCodes),
            ));
        }

        $rows = [];

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = [];
            $hasAnyValue = false;

            foreach ($headers as $header) {
                $cell = $sheet->getCell($this->cellAddress((int) $header['column'], $rowNumber));
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
                'values' => $this->normalizeRowValuesByDisplayCodes($values),
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
        $normalizedRowValues = $this->normalizeRowValuesByDisplayCodes($rowValues);

        foreach ($fieldMap as $displayCode => $meta) {
            $value = $normalizedRowValues[$displayCode] ?? null;

            if ($this->isEmpty($value)) {
                if ($meta['isRequired']) {
                    $errors[] = sprintf(
                        'Поле "%s" (%s) обязательно для заполнения.',
                        $meta['title'],
                        $displayCode,
                    );
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

        // local disk root is storage/app/private, so keep relative paths without "private/" prefix.
        $relativePath = 'errors/import_'.$importJob->uuid.'_errors.xlsx';
        $absolutePath = Storage::disk('local')->path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        (new Xlsx($spreadsheet))->save($absolutePath);

        return $relativePath;
    }

    private function cellAddress(int $column, int $row): string
    {
        return Coordinate::stringFromColumnIndex($column).$row;
    }

    /**
     * @return array{title:string,code:string}
     */
    private function parseHeader(string $rawHeader): array
    {
        if (preg_match('/^(.*)\(([^)]+)\)\s*$/u', $rawHeader, $matches) === 1) {
            return [
                'title' => trim($matches[1]),
                'code' => $this->fieldCode->normalizeDisplayCode(trim($matches[2])),
            ];
        }

        return [
            'title' => $rawHeader,
            'code' => $this->fieldCode->normalizeDisplayCode($rawHeader),
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
        if (is_int($value)) {
            return [$value, null];
        }

        if (is_float($value) && floor($value) === $value) {
            return [(int) $value, null];
        }

        if (is_string($value)) {
            $normalized = str_replace(["\xc2\xa0", ' '], '', trim($value));
            if ($normalized !== '' && preg_match('/^-?\d+$/', $normalized) === 1) {
                return [(int) $normalized, null];
            }
        }

        return [null, sprintf(
            'Поле "%s" (%s): ожидается целое число, получено "%s".',
            $meta['title'],
            $meta['code'],
            $this->stringifyValue($value),
        )];
    }

    /**
     * @param  array{code:string,title:string}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castFloat(mixed $value, array $meta): array
    {
        if (is_string($value)) {
            $value = str_replace(["\xc2\xa0", ' '], '', trim($value));
            $value = str_replace(',', '.', $value);
        }

        if (is_numeric($value)) {
            return [(float) $value, null];
        }

        return [null, sprintf(
            'Поле "%s" (%s): ожидается число, получено "%s".',
            $meta['title'],
            $meta['code'],
            $this->stringifyValue($value),
        )];
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

        return [null, sprintf(
            'Поле "%s" (%s): ожидается булево значение (да/нет, true/false, 1/0), получено "%s".',
            $meta['title'],
            $meta['code'],
            $this->stringifyValue($value),
        )];
    }

    /**
     * @param  array{code:string,title:string}  $meta
     * @return array{0:mixed,1:?string}
     */
    private function castDate(mixed $value, array $meta, bool $withTime): array
    {
        if (is_numeric($value) && ! is_string($value)) {
            try {
                $date = Carbon::instance(Date::excelToDateTimeObject((float) $value));

                return [$withTime ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d'), null];
            } catch (\Throwable) {
                return [null, sprintf(
                    'Поле "%s" (%s): неверный формат даты.',
                    $meta['title'],
                    $meta['code'],
                )];
            }
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return [null, sprintf(
                'Поле "%s" (%s): пустое значение даты.',
                $meta['title'],
                $meta['code'],
            )];
        }

        foreach ($this->supportedDateFormats($withTime) as $format) {
            try {
                $date = Carbon::createFromFormat($format, $stringValue);
            } catch (\Throwable) {
                continue;
            }
            $errors = Carbon::getLastErrors();
            $hasFormatErrors = ! is_array($errors)
                || (($errors['warning_count'] ?? 0) > 0)
                || (($errors['error_count'] ?? 0) > 0);

            if (! $date || $hasFormatErrors) {
                continue;
            }

            return [$withTime ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d'), null];
        }

        return [null, sprintf(
            'Поле "%s" (%s): неверный формат даты "%s". Используйте %s.',
            $meta['title'],
            $meta['code'],
            $this->stringifyValue($value),
            $withTime ? 'YYYY-MM-DD HH:MM:SS' : 'YYYY-MM-DD',
        )];
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

        $hints = array_map(
            static fn (array $item): string => $item['id'].'='.$item['title'],
            array_slice($meta['items'], 0, 5),
        );
        $hintText = $hints !== [] ? ' Примеры: '.implode(', ', $hints).'.' : '';

        return [null, sprintf(
            'Поле "%s" (%s): значение "%s" не найдено в списке.%s',
            $meta['title'],
            $meta['code'],
            $stringValue,
            $hintText,
        )];
    }

    /**
     * @param  array<string,mixed>  $rowValues
     * @return array<string,mixed>
     */
    private function normalizeRowValuesByDisplayCodes(array $rowValues): array
    {
        $normalized = [];

        foreach ($rowValues as $code => $value) {
            $displayCode = $this->fieldCode->normalizeDisplayCode((string) $code);
            $normalized[$displayCode] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<int,string>
     */
    private function supportedDateFormats(bool $withTime): array
    {
        if ($withTime) {
            return [
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'Y-m-d\TH:i:s',
                'Y-m-d\TH:i',
                'd.m.Y H:i:s',
                'd.m.Y H:i',
                'd/m/Y H:i:s',
                'd/m/Y H:i',
            ];
        }

        return [
            'Y-m-d',
            'd.m.Y',
            'd/m/Y',
        ];
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'invalid';
    }
}
