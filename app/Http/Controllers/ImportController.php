<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithBitrixContext;
use App\Jobs\ProcessSmartProcessImport;
use App\Models\ImportJob;
use App\Services\Bitrix24\Bitrix24Service;
use App\Services\Imports\ExcelImportService;
use App\Services\Permissions\SmartProcessPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    use InteractsWithBitrixContext;

    public function __construct(
        private readonly SmartProcessPermissionService $permissions,
        private readonly Bitrix24Service $bitrix24,
        private readonly ExcelImportService $excelImport,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $portal = $this->currentPortal($request);
        $user = $this->currentPortalUser($request);

        $validated = $request->validate([
            'entity_type_id' => ['required', 'integer', 'min:1'],
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $entityTypeId = (int) $validated['entity_type_id'];
        abort_unless($this->permissions->canUpload($user, $entityTypeId), 403, 'Нет доступа к выбранному смарт-процессу.');

        $smartProcess = null;
        try {
            $smartProcess = $this->bitrix24->getSmartProcessByEntityType($portal, $entityTypeId);
        } catch (\Throwable) {
            // crm.type.get may be unavailable for some portals/scopes.
            // Import can proceed without entity title metadata.
        }

        $sourcePath = $validated['excel_file']->storeAs(
            'private/imports',
            now()->format('Ymd_His').'_'.Str::uuid()->toString().'.'.$validated['excel_file']->getClientOriginalExtension(),
            'local',
        );

        $importJob = ImportJob::query()->create([
            'uuid' => Str::uuid()->toString(),
            'portal_id' => $portal->id,
            'bitrix_user_id' => $user->bitrix_user_id,
            'entity_type_id' => $entityTypeId,
            'entity_title' => $smartProcess['title'] ?? null,
            'source_file_path' => $sourcePath,
            'status' => ImportJob::STATUS_QUEUED,
        ]);

        ProcessSmartProcessImport::dispatch($importJob->id);

        return redirect()
            ->route('imports.show', $importJob)
            ->with('status', 'Файл поставлен в очередь на загрузку.');
    }

    public function show(Request $request, ImportJob $importJob): View
    {
        $user = $this->currentPortalUser($request);

        $this->authorizeImportAccess($request, $importJob, $user->canManagePermissions());

        $errorReportUrl = $importJob->error_rows > 0
            ? URL::signedRoute('imports.errors.public', ['importJob' => $importJob])
            : null;

        return view('imports.show', [
            'importJob' => $importJob,
            'user' => $user,
            'errorReportUrl' => $errorReportUrl,
        ]);
    }

    public function status(Request $request, ImportJob $importJob): JsonResponse
    {
        $user = $this->currentPortalUser($request);
        $this->authorizeImportAccess($request, $importJob, $user->canManagePermissions());

        $importJob->refresh();

        $progress = $importJob->total_rows > 0
            ? (int) floor(($importJob->processed_rows / $importJob->total_rows) * 100)
            : ($importJob->isFinished() ? 100 : 0);

        $errorReportUrl = $importJob->error_rows > 0
            ? URL::signedRoute('imports.errors.public', ['importJob' => $importJob])
            : null;

        return response()->json([
            'status' => $importJob->status,
            'total_rows' => $importJob->total_rows,
            'processed_rows' => $importJob->processed_rows,
            'success_rows' => $importJob->success_rows,
            'error_rows' => $importJob->error_rows,
            'progress_percent' => $progress,
            'finished' => $importJob->isFinished(),
            'error_report_url' => $errorReportUrl,
            'dashboard_url' => route('dashboard.index'),
        ]);
    }

    public function downloadErrors(Request $request, ImportJob $importJob): BinaryFileResponse
    {
        $user = $this->currentPortalUser($request);
        $this->authorizeImportAccess($request, $importJob, $user->canManagePermissions());

        return $this->downloadErrorsFile($importJob);
    }

    public function downloadErrorsPublic(ImportJob $importJob): BinaryFileResponse
    {
        return $this->downloadErrorsFile($importJob);
    }

    private function downloadErrorsFile(ImportJob $importJob): BinaryFileResponse
    {
        abort_if($importJob->error_rows <= 0, 404, 'Ошибок в этом импорте нет.');

        $absolutePath = null;
        if ($importJob->error_file_path !== null) {
            $candidatePath = Storage::disk('local')->path($importJob->error_file_path);
            if (is_file($candidatePath)) {
                $absolutePath = $candidatePath;
            }
        }

        if ($absolutePath === null) {
            $relativePath = $this->excelImport->generateErrorReport($importJob->fresh());
            $importJob->forceFill([
                'error_file_path' => $relativePath,
            ])->save();

            $absolutePath = Storage::disk('local')->path($relativePath);
        }

        abort_unless(is_file($absolutePath), 404, 'Файл с ошибками не найден.');

        return response()->download($absolutePath, 'import-errors-'.$importJob->uuid.'.xlsx');
    }

    private function authorizeImportAccess(Request $request, ImportJob $importJob, bool $canManageAll): void
    {
        $portal = $this->currentPortal($request);
        $user = $this->currentPortalUser($request);

        abort_unless($importJob->portal_id === $portal->id, 404);

        if (! $canManageAll) {
            abort_unless($importJob->bitrix_user_id === $user->bitrix_user_id, 403, 'Нет доступа к этому импорту.');
        }
    }
}
