<?php

namespace App\Services\Bitrix24;

use App\Models\Portal;
use Illuminate\Support\Str;
use RuntimeException;

class Bitrix24Service
{
    public function __construct(
        private readonly Bitrix24Client $client,
        private readonly BitrixFieldCode $fieldCode,
    ) {
    }

    public function call(Portal $portal, string $method, array $params = []): array
    {
        return $this->client->call($portal, $method, $params);
    }

    public function getCurrentUser(Portal $portal): array
    {
        $response = $this->client->call($portal, 'user.current');

        return $response['result'] ?? [];
    }

    /**
     * @return array<int, array{entityTypeId:int,title:string}>
     */
    public function listSmartProcesses(Portal $portal): array
    {
        $response = $this->client->call($portal, 'crm.type.list', [
            'select' => ['id', 'entityTypeId', 'title', 'name'],
        ]);

        $items = $response['result']['types'] ?? $response['result'] ?? [];
        $types = [];

        foreach ($items as $item) {
            $entityTypeId = (int) ($item['entityTypeId'] ?? $item['ENTITY_TYPE_ID'] ?? $item['id'] ?? 0);

            if ($entityTypeId <= 0) {
                continue;
            }

            $title = (string) ($item['title'] ?? $item['TITLE'] ?? $item['name'] ?? $item['NAME'] ?? ('SP #'.$entityTypeId));
            $types[] = [
                'entityTypeId' => $entityTypeId,
                'title' => $title,
            ];
        }

        usort($types, static fn (array $left, array $right): int => strcmp($left['title'], $right['title']));

        return $types;
    }

    public function getSmartProcessByEntityType(Portal $portal, int $entityTypeId): ?array
    {
        $response = $this->client->call($portal, 'crm.type.get', [
            'entityTypeId' => $entityTypeId,
        ]);

        $item = $response['result']['type'] ?? $response['result'] ?? null;

        if (! is_array($item)) {
            return null;
        }

        return [
            'entityTypeId' => (int) ($item['entityTypeId'] ?? $item['ENTITY_TYPE_ID'] ?? $entityTypeId),
            'title' => (string) ($item['title'] ?? $item['TITLE'] ?? $item['name'] ?? $item['NAME'] ?? ('SP #'.$entityTypeId)),
        ];
    }

    /**
     * @return array<string, array{code:string,apiCode:string,title:string,type:string,isRequired:bool,isMultiple:bool,items:array<int,array{id:string,title:string}>}>
     */
    public function getItemFields(Portal $portal, int $entityTypeId): array
    {
        $response = $this->client->call($portal, 'crm.item.fields', [
            'entityTypeId' => $entityTypeId,
        ]);

        $fields = $response['result']['fields'] ?? $response['result'] ?? [];
        $normalized = [];

        foreach ($fields as $apiCode => $meta) {
            if (! is_array($meta)) {
                continue;
            }

            if (! $this->shouldExposeField((string) $apiCode, $meta)) {
                continue;
            }

            $displayCode = $this->fieldCode->toDisplayCode((string) $apiCode);
            $title = (string) ($meta['title'] ?? $meta['formLabel'] ?? $meta['listLabel'] ?? $apiCode);
            $type = strtolower((string) ($meta['type'] ?? $meta['TYPE'] ?? 'string'));

            $normalized[$displayCode] = [
                'code' => $displayCode,
                'apiCode' => (string) $apiCode,
                'title' => $title,
                'type' => $type,
                'isRequired' => (bool) ($meta['isRequired'] ?? $meta['required'] ?? false),
                'isMultiple' => (bool) ($meta['isMultiple'] ?? $meta['multiple'] ?? false),
                'items' => $this->normalizeItems($meta['items'] ?? []),
            ];
        }

        uasort($normalized, static function (array $left, array $right): int {
            return strcmp($left['title'], $right['title']);
        });

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    private function shouldExposeField(string $apiCode, array $meta): bool
    {
        $normalizedCode = strtolower(str_replace(['_', '-'], '', $apiCode));

        // Service/system fields are populated by Bitrix and should not be imported from Excel.
        $systemCodes = [
            'id',
            'createdby',
            'updatedby',
            'movedby',
            'createdtime',
            'updatedtime',
            'movedtime',
            'lastactivitytime',
            'lastcommunicationtime',
        ];

        if (in_array($normalizedCode, $systemCodes, true)) {
            return false;
        }

        if ($this->isTruthy($meta['isReadOnly'] ?? null)
            || $this->isTruthy($meta['readOnly'] ?? null)
            || $this->isTruthy($meta['readonly'] ?? null)
            || $this->isTruthy($meta['isImmutable'] ?? null)
            || $this->isTruthy($meta['immutable'] ?? null)
            || $this->isTruthy($meta['isSystem'] ?? null)
            || $this->isTruthy($meta['isComputed'] ?? null)
            || $this->isTruthy($meta['isCalculated'] ?? null)
        ) {
            return false;
        }

        $operations = $meta['operations'] ?? null;
        if (is_array($operations)) {
            $canAdd = $operations['add'] ?? $operations['ADD'] ?? null;
            if ($canAdd !== null && ! $this->isTruthy($canAdd)) {
                return false;
            }
        }

        // Bitrix file fields require multipart upload flow; plain Excel import should skip them.
        $type = strtolower((string) ($meta['type'] ?? $meta['TYPE'] ?? ''));
        if ($type === 'file') {
            return false;
        }

        return true;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return in_array(Str::lower(trim($value)), ['1', 'y', 'yes', 'true'], true);
        }

        return false;
    }

    /**
     * @param  array<int, array{fields:array<string,mixed>}>  $rows
     * @return array{results:array<string,mixed>,errors:array<string,mixed>}
     */
    public function addItemsBatch(Portal $portal, int $entityTypeId, array $rows): array
    {
        $commands = [];

        foreach ($rows as $index => $row) {
            $query = http_build_query([
                'entityTypeId' => $entityTypeId,
                'fields' => $row['fields'],
            ], '', '&', PHP_QUERY_RFC3986);

            $commands['add'.$index] = 'crm.item.add?'.$query;
        }

        if ($commands === []) {
            return ['results' => [], 'errors' => []];
        }

        $response = $this->client->call($portal, 'batch', [
            'halt' => 0,
            'cmd' => $commands,
        ]);

        if (isset($response['error'])) {
            throw new RuntimeException((string) ($response['error_description'] ?? $response['error']));
        }

        $resultNode = $response['result'] ?? [];

        return [
            'results' => $resultNode['result'] ?? [],
            'errors' => $resultNode['result_error'] ?? [],
        ];
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{id:string,title:string}>
     */
    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = (string) ($item['ID'] ?? $item['id'] ?? '');
            $title = (string) ($item['VALUE'] ?? $item['value'] ?? $item['NAME'] ?? $item['name'] ?? $id);

            if ($id === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'title' => $title,
            ];
        }

        return $normalized;
    }
}
