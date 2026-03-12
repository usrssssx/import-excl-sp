<?php

namespace App\Http\Middleware;

use App\Models\Portal;
use App\Models\PortalUser;
use App\Services\Bitrix24\Bitrix24Service;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveBitrixContext
{
    public function __construct(private readonly Bitrix24Service $bitrix24)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incomingContext = $this->extractIncomingContext($request);

        if ($incomingContext !== null) {
            [$portal, $portalUser] = $this->persistContext($incomingContext);

            $request->session()->put('bitrix_context', [
                'portal_id' => $portal->id,
                'portal_user_id' => $portalUser->id,
            ]);
        }

        $sessionContext = $request->session()->get('bitrix_context');

        if (! is_array($sessionContext)) {
            return response()->view('bitrix.missing-context', [], 401);
        }

        $portal = Portal::query()->find($sessionContext['portal_id'] ?? null);
        $portalUser = PortalUser::query()->find($sessionContext['portal_user_id'] ?? null);

        if (
            ! $portal
            || ! $portalUser
            || $portalUser->portal_id !== $portal->id
        ) {
            $request->session()->forget('bitrix_context');

            return response()->view('bitrix.missing-context', [], 401);
        }

        $request->attributes->set('bitrixPortal', $portal);
        $request->attributes->set('bitrixUser', $portalUser);
        View::share('portal', $portal);
        View::share('user', $portalUser);

        return $next($request);
    }

    /**
     * @return array{domain:string,protocol:string,member_id:string,access_token:string,refresh_token:?string,expires_at:?int,application_token:?string,user_id:int}|null
     */
    private function extractIncomingContext(Request $request): ?array
    {
        $authLower = $request->input('auth', []);
        $authUpper = $request->input('AUTH', []);

        $auth = [];
        if (is_array($authUpper)) {
            $auth = $authUpper;
        }
        if (is_array($authLower)) {
            $auth = array_merge($auth, $authLower);
        }

        $domain = (string) (
            $auth['domain']
            ?? $auth['DOMAIN']
            ?? $auth['server_domain']
            ?? $auth['SERVER_DOMAIN']
            ?? $request->input('DOMAIN')
            ?? $request->input('domain')
            ?? $request->input('AUTH.DOMAIN')
            ?? ''
        );
        $memberId = (string) (
            $auth['member_id']
            ?? $auth['MEMBER_ID']
            ?? $request->input('member_id')
            ?? $request->input('MEMBER_ID')
            ?? $request->input('AUTH.MEMBER_ID')
            ?? ''
        );
        $accessToken = (string) (
            $auth['access_token']
            ?? $auth['ACCESS_TOKEN']
            ?? $request->input('AUTH_ID')
            ?? $request->input('auth_id')
            ?? $request->input('AUTH.ACCESS_TOKEN')
            ?? ''
        );
        $refreshToken = $auth['refresh_token']
            ?? $auth['REFRESH_TOKEN']
            ?? $request->input('REFRESH_ID')
            ?? $request->input('AUTH.REFRESH_TOKEN');
        $authExpires = $auth['expires']
            ?? $auth['EXPIRES']
            ?? $request->input('AUTH_EXPIRES')
            ?? $request->input('AUTH.EXPIRES');
        $applicationToken = $auth['application_token']
            ?? $auth['APPLICATION_TOKEN']
            ?? $request->input('APP_SID')
            ?? $request->input('APPLICATION_TOKEN')
            ?? $request->input('AUTH.APPLICATION_TOKEN');
        $protocol = $this->normalizeProtocol(
            $auth['protocol']
            ?? $auth['PROTOCOL']
            ?? $request->input('PROTOCOL')
            ?? $request->input('AUTH.PROTOCOL')
            ?? 'https'
        );
        $userId = (int) (
            $auth['user_id']
            ?? $auth['USER_ID']
            ?? $request->input('USER_ID')
            ?? $request->input('user_id')
            ?? $request->input('AUTH.USER_ID')
            ?? 0
        );

        $domain = preg_replace('#^https?://#', '', trim($domain));
        $domain = trim((string) $domain, '/');

        if ($domain === '' || $accessToken === '') {
            return null;
        }

        if ($memberId === '') {
            $memberId = 'domain_'.preg_replace('/[^a-z0-9]+/i', '_', $domain);
        }

        $expiresAt = null;
        if (is_numeric($authExpires)) {
            $expires = (int) $authExpires;
            $expiresAt = $expires > now()->timestamp
                ? $expires - 60
                : now()->addSeconds(max(0, $expires - 60))->timestamp;
        }

        return [
            'domain' => $domain,
            'protocol' => $protocol,
            'member_id' => $memberId,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken ? (string) $refreshToken : null,
            'expires_at' => $expiresAt,
            'application_token' => $applicationToken ? (string) $applicationToken : null,
            'user_id' => $userId,
        ];
    }

    private function normalizeProtocol(mixed $protocol): string
    {
        $value = strtolower(trim((string) $protocol));
        $value = str_replace('://', '', $value);

        return match ($value) {
            '1', 'https' => 'https',
            '0', 'http' => 'http',
            default => 'https',
        };
    }

    /**
     * @param  array{domain:string,protocol:string,member_id:string,access_token:string,refresh_token:?string,expires_at:?int,application_token:?string,user_id:int}  $context
     * @return array{0:Portal,1:PortalUser}
     */
    private function persistContext(array $context): array
    {
        $portal = Portal::query()->updateOrCreate(
            ['member_id' => $context['member_id']],
            [
                'domain' => $context['domain'],
                'protocol' => $context['protocol'],
                'access_token' => $context['access_token'],
                'refresh_token' => $context['refresh_token'],
                'access_expires_at' => $context['expires_at'],
                'application_token' => $context['application_token'],
            ],
        );

        $currentUser = [];
        try {
            $currentUser = $this->bitrix24->getCurrentUser($portal);
        } catch (\Throwable) {
            // Keep context if user.current temporarily unavailable.
        }

        $bitrixUserId = (int) ($currentUser['ID'] ?? $currentUser['id'] ?? $context['user_id']);
        if ($bitrixUserId <= 0) {
            $bitrixUserId = (int) PortalUser::query()
                ->where('portal_id', $portal->id)
                ->where('bitrix_user_id', '>', 0)
                ->orderByDesc('last_seen_at')
                ->value('bitrix_user_id');
        }

        $name = trim((string) (($currentUser['NAME'] ?? '').' '.($currentUser['LAST_NAME'] ?? '')));

        $departmentIds = $currentUser['UF_DEPARTMENT'] ?? [];
        if (! is_array($departmentIds)) {
            $departmentIds = [];
        }

        $integratorIds = config('bitrix24.integrator_user_ids', []);
        $isIntegrator = in_array($bitrixUserId, $integratorIds, true);

        $portalUser = PortalUser::query()->updateOrCreate(
            [
                'portal_id' => $portal->id,
                'bitrix_user_id' => $bitrixUserId,
            ],
            [
                'name' => $name !== '' ? $name : null,
                'is_admin' => (bool) ($currentUser['ADMIN'] ?? $currentUser['IS_ADMIN'] ?? false),
                'is_integrator' => $isIntegrator,
                'department_ids' => array_values(array_map('intval', $departmentIds)),
                'last_seen_at' => now(),
            ],
        );

        return [$portal, $portalUser];
    }
}
