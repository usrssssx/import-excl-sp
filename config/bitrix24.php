<?php

return [
    'client_id' => env('BITRIX24_CLIENT_ID'),
    'client_secret' => env('BITRIX24_CLIENT_SECRET'),
    'oauth_server' => env('BITRIX24_OAUTH_SERVER', 'https://oauth.bitrix.info/oauth/token/'),
    'integrator_user_ids' => array_values(array_filter(array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) env('BITRIX24_INTEGRATOR_USER_IDS', '')),
    ))),
    'batch_size' => (int) env('BITRIX24_BATCH_SIZE', 50),
];
