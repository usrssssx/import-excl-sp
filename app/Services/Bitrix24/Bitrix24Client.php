<?php

namespace App\Services\Bitrix24;

use App\Models\Portal;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Bitrix24Client
{
    public function call(Portal $portal, string $method, array $params = []): array
    {
        $response = $this->performCall($portal, $method, $params);

        if (($response['error'] ?? null) === 'expired_token' || ($response['error'] ?? null) === 'INVALID_TOKEN') {
            $this->refreshToken($portal);
            $response = $this->performCall($portal, $method, $params);
        }

        return $response;
    }

    protected function performCall(Portal $portal, string $method, array $params = []): array
    {
        $accessToken = $this->ensureAccessToken($portal);
        $url = sprintf('%s/rest/%s.json', $portal->baseUrl(), $method);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post($url, array_merge($params, ['auth' => $accessToken]));

        if (! $response->ok()) {
            throw new RuntimeException(sprintf('Bitrix24 API [%s] returned HTTP %d', $method, $response->status()));
        }

        return $response->json() ?: [];
    }

    protected function ensureAccessToken(Portal $portal): string
    {
        if (! $portal->access_token) {
            throw new RuntimeException('Bitrix24 access token is missing.');
        }

        if (! $portal->access_expires_at || $portal->access_expires_at > now()->addSeconds(30)->timestamp) {
            return $portal->access_token;
        }

        $this->refreshToken($portal);

        return (string) $portal->access_token;
    }

    public function refreshToken(Portal $portal): void
    {
        if (! $portal->refresh_token) {
            throw new RuntimeException('Bitrix24 refresh token is missing.');
        }

        $clientId = config('bitrix24.client_id');
        $clientSecret = config('bitrix24.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException('BITRIX24_CLIENT_ID / BITRIX24_CLIENT_SECRET are not configured.');
        }

        $oauthResponse = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post(config('bitrix24.oauth_server'), [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $portal->refresh_token,
            ]);

        if (! $oauthResponse->ok()) {
            throw new RuntimeException(sprintf('Bitrix24 OAuth refresh returned HTTP %d', $oauthResponse->status()));
        }

        $payload = $oauthResponse->json() ?: [];

        if (! isset($payload['access_token'])) {
            throw new RuntimeException('Bitrix24 OAuth refresh did not return access_token.');
        }

        $portal->forceFill([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $portal->refresh_token,
            'access_expires_at' => now()->addSeconds(max(0, (int) ($payload['expires_in'] ?? 3600) - 60))->timestamp,
        ])->save();
    }
}
