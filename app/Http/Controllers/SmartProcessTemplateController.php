<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithBitrixContext;
use App\Services\Bitrix24\Bitrix24Service;
use App\Services\Imports\ExcelTemplateService;
use App\Services\Permissions\SmartProcessPermissionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SmartProcessTemplateController extends Controller
{
    use InteractsWithBitrixContext;

    public function __construct(
        private readonly Bitrix24Service $bitrix24,
        private readonly SmartProcessPermissionService $permissions,
        private readonly ExcelTemplateService $excelTemplate,
    ) {
    }

    public function download(Request $request, int $entityTypeId): BinaryFileResponse
    {
        $portal = $this->currentPortal($request);
        $user = $this->currentPortalUser($request);

        abort_unless($this->permissions->canUpload($user, $entityTypeId), 403, 'Нет доступа к выбранному смарт-процессу.');

        $fields = $this->bitrix24->getItemFields($portal, $entityTypeId);
        $templatePath = $this->excelTemplate->generate($fields);

        return response()
            ->download($templatePath, sprintf('smart-process-%d-template.xlsx', $entityTypeId))
            ->deleteFileAfterSend(true);
    }
}
