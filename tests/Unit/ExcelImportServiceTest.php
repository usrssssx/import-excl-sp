<?php

namespace Tests\Unit;

use App\Services\Bitrix24\BitrixFieldCode;
use App\Services\Imports\ExcelImportService;
use PHPUnit\Framework\TestCase;

class ExcelImportServiceTest extends TestCase
{
    public function test_maps_uf_row_keys_to_normalized_format(): void
    {
        $service = new ExcelImportService(new BitrixFieldCode());

        $fieldMap = [
            'ufCrm_19_1763220197' => [
                'code' => 'ufCrm_19_1763220197',
                'apiCode' => 'UF_CRM_19_1763220197',
                'title' => 'Тестовое поле',
                'type' => 'string',
                'isRequired' => true,
                'isMultiple' => false,
                'items' => [],
            ],
        ];

        $result = $service->normalizeRow([
            'UF_CRM_19_1763220197' => 'значение',
        ], $fieldMap);

        $this->assertSame([], $result['errors']);
        $this->assertSame('значение', $result['fields']['UF_CRM_19_1763220197']);
    }

    public function test_returns_clear_error_for_invalid_date(): void
    {
        $service = new ExcelImportService(new BitrixFieldCode());

        $fieldMap = [
            'contractDate' => [
                'code' => 'contractDate',
                'apiCode' => 'contractDate',
                'title' => 'Дата договора',
                'type' => 'date',
                'isRequired' => true,
                'isMultiple' => false,
                'items' => [],
            ],
        ];

        $result = $service->normalizeRow([
            'contractDate' => '31-13-2026',
        ], $fieldMap);

        $this->assertSame([], $result['fields']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('неверный формат даты', mb_strtolower($result['errors'][0]));
    }

    public function test_returns_clear_error_for_invalid_enum_value(): void
    {
        $service = new ExcelImportService(new BitrixFieldCode());

        $fieldMap = [
            'stage' => [
                'code' => 'stage',
                'apiCode' => 'stage',
                'title' => 'Этап',
                'type' => 'enumeration',
                'isRequired' => false,
                'isMultiple' => false,
                'items' => [
                    ['id' => '10', 'title' => 'Новый'],
                    ['id' => '20', 'title' => 'В работе'],
                ],
            ],
        ];

        $result = $service->normalizeRow([
            'stage' => 'Закрыт',
        ], $fieldMap);

        $this->assertSame([], $result['fields']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('не найдено в списке', mb_strtolower($result['errors'][0]));
        $this->assertStringContainsString('10=Новый', $result['errors'][0]);
    }
}

