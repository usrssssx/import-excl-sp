<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Portal;
use App\Models\PortalUser;
use Illuminate\Http\Request;

trait InteractsWithBitrixContext
{
    protected function currentPortal(Request $request): Portal
    {
        /** @var Portal $portal */
        $portal = $request->attributes->get('bitrixPortal');

        return $portal;
    }

    protected function currentPortalUser(Request $request): PortalUser
    {
        /** @var PortalUser $user */
        $user = $request->attributes->get('bitrixUser');

        return $user;
    }
}
