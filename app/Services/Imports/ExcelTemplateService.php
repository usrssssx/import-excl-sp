<?php

namespace App\Services\Imports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelTemplateService
{
    /**
     * @param  array<string, array{code:string,apiCode:string,title:string,type:string,isRequired:bool,isMultiple:bool,items:array<int,array{id:string,title:string}>}>  $fields
     */
    public function generate(array $fields): string
    {
        $spreadsheet = new Spreadsheet();
        $templateSheet = $spreadsheet->getActiveSheet();
        $templateSheet->setTitle('Template');

        $enumRows = [];
        $columnIndex = 1;

        foreach ($fields as $field) {
            $templateSheet->setCellValueByColumnAndRow(
                $columnIndex,
                1,
                sprintf('%s (%s)', $field['title'], $field['code']),
            );

            if ($field['items'] !== []) {
                foreach ($field['items'] as $item) {
                    $enumRows[] = [
                        $field['code'],
                        $field['title'],
                        $item['id'],
                        $item['title'],
                    ];
                }
            }

            $columnIndex++;
        }

        for ($column = 1; $column < $columnIndex; $column++) {
            $templateSheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        if ($enumRows !== []) {
            $listsSheet = $spreadsheet->createSheet();
            $listsSheet->setTitle('Lists');
            $listsSheet->fromArray(['Field Code', 'Field Name', 'Value ID', 'Value Title'], null, 'A1');

            $rowPointer = 2;
            foreach ($enumRows as $enumRow) {
                $listsSheet->fromArray($enumRow, null, 'A'.$rowPointer);
                $rowPointer++;
            }

            foreach (range('A', 'D') as $column) {
                $listsSheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        $dir = storage_path('app/private/templates');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filePath = $dir.'/template_'.now()->format('Ymd_His_').bin2hex(random_bytes(3)).'.xlsx';

        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
