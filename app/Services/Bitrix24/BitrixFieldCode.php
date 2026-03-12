<?php

namespace App\Services\Bitrix24;

class BitrixFieldCode
{
    public function toDisplayCode(string $apiCode): string
    {
        if (str_starts_with($apiCode, 'UF_CRM_')) {
            return 'ufCrm_'.substr($apiCode, 7);
        }

        return $apiCode;
    }

    public function toApiCode(string $displayCode): string
    {
        if (str_starts_with($displayCode, 'ufCrm_')) {
            return 'UF_CRM_'.substr($displayCode, 6);
        }

        return $displayCode;
    }
}
