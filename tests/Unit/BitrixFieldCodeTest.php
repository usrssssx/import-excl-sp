<?php

namespace Tests\Unit;

use App\Services\Bitrix24\BitrixFieldCode;
use PHPUnit\Framework\TestCase;

class BitrixFieldCodeTest extends TestCase
{
    public function test_normalizes_uf_codes_to_display_format(): void
    {
        $service = new BitrixFieldCode();

        $this->assertSame('ufCrm_19_1763220197', $service->normalizeDisplayCode('UF_CRM_19_1763220197'));
        $this->assertSame('ufCrm_19_1763220197', $service->normalizeDisplayCode('uf_crm_19_1763220197'));
        $this->assertSame('ufCrm_19_1763220197', $service->normalizeDisplayCode('UfCrM_19_1763220197'));
    }

    public function test_converts_display_code_back_to_api_code(): void
    {
        $service = new BitrixFieldCode();

        $this->assertSame('UF_CRM_19_1763220197', $service->toApiCode('UF_CRM_19_1763220197'));
        $this->assertSame('UF_CRM_19_1763220197', $service->toApiCode('ufCrm_19_1763220197'));
        $this->assertSame('title', $service->toApiCode('title'));
    }
}

