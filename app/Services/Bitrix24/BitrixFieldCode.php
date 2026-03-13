<?php

namespace App\Services\Bitrix24;

class BitrixFieldCode
{
    public function normalizeDisplayCode(string $code): string
    {
        $normalized = trim($code);
        if ($normalized === '') {
            return $normalized;
        }

        $ufSuffix = $this->extractUfCrmSuffix($normalized);
        if ($ufSuffix === null) {
            return $normalized;
        }

        return 'ufCrm_'.$ufSuffix;
    }

    public function toDisplayCode(string $apiCode): string
    {
        return $this->normalizeDisplayCode($apiCode);
    }

    public function toApiCode(string $displayCode): string
    {
        $normalizedDisplayCode = $this->normalizeDisplayCode($displayCode);

        if (str_starts_with($normalizedDisplayCode, 'ufCrm_')) {
            return 'UF_CRM_'.substr($normalizedDisplayCode, 6);
        }

        return $normalizedDisplayCode;
    }

    private function extractUfCrmSuffix(string $code): ?string
    {
        $candidate = str_replace('-', '_', trim($code));

        if (preg_match('/^uf[_]?crm[_]*(.+)$/i', $candidate, $matches) !== 1) {
            return null;
        }

        $suffix = preg_replace('/_+/', '_', trim((string) $matches[1]));
        $suffix = trim((string) $suffix, '_');

        return $suffix !== '' ? $suffix : null;
    }
}
