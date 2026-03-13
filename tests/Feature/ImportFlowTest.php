<?php

namespace Tests\Feature;

use App\Jobs\ProcessSmartProcessImport;
use App\Models\ImportJob;
use App\Models\Portal;
use App\Models\PortalUser;
use App\Services\Bitrix24\Bitrix24Service;
use App\Services\Imports\SmartProcessImportProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class ImportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_creates_import_job_and_status_reports_progress_and_result_with_mocked_bitrix(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$portal, $portalUser] = $this->createPortalContext();

        $this->mock(Bitrix24Service::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getSmartProcessByEntityType')
                ->once()
                ->andReturn([
                    'entityTypeId' => 1042,
                    'title' => 'Сделки импорта',
                ]);
        });

        $csv = implode("\n", [
            'Название (title),Ответственный (assignedById)',
            'OK row,1',
            'Error row,abc',
        ]);
        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $response = $this
            ->withSession($this->bitrixSession($portal, $portalUser))
            ->post(route('imports.store'), [
                'entity_type_id' => 1042,
                'excel_file' => $file,
            ]);

        $response->assertRedirect();
        Queue::assertPushed(ProcessSmartProcessImport::class, 1);

        $importJob = ImportJob::query()->firstOrFail();
        $this->assertSame(ImportJob::STATUS_QUEUED, $importJob->status);

        $this->mock(Bitrix24Service::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getItemFields')
                ->once()
                ->andReturn([
                    'title' => [
                        'code' => 'title',
                        'apiCode' => 'title',
                        'title' => 'Название',
                        'type' => 'string',
                        'isRequired' => true,
                        'isMultiple' => false,
                        'items' => [],
                    ],
                    'assignedById' => [
                        'code' => 'assignedById',
                        'apiCode' => 'assignedById',
                        'title' => 'Ответственный',
                        'type' => 'integer',
                        'isRequired' => false,
                        'isMultiple' => false,
                        'items' => [],
                    ],
                ]);

            $mock->shouldReceive('addItemsBatch')
                ->once()
                ->andReturn([
                    'results' => [
                        'add0' => ['item' => ['id' => 501]],
                    ],
                    'errors' => [],
                ]);
        });

        /** @var SmartProcessImportProcessor $processor */
        $processor = app(SmartProcessImportProcessor::class);
        $processor->process($importJob->fresh('portal'));

        $importJob->refresh();
        $this->assertSame(ImportJob::STATUS_COMPLETED, $importJob->status);
        $this->assertSame(2, $importJob->total_rows);
        $this->assertSame(2, $importJob->processed_rows);
        $this->assertSame(1, $importJob->success_rows);
        $this->assertSame(1, $importJob->error_rows);
        $this->assertNotNull($importJob->error_file_path);
        $this->assertTrue(Storage::disk('local')->exists((string) $importJob->error_file_path));

        $statusResponse = $this
            ->withSession($this->bitrixSession($portal, $portalUser))
            ->getJson(route('imports.status', $importJob));

        $statusResponse
            ->assertOk()
            ->assertJson([
                'status' => ImportJob::STATUS_COMPLETED,
                'total_rows' => 2,
                'processed_rows' => 2,
                'success_rows' => 1,
                'error_rows' => 1,
                'finished' => true,
            ])
            ->assertJsonPath('same_sp_url', route('dashboard.index').'#sp-1042');

        $this->assertNotNull($statusResponse->json('error_report_url'));
    }

    public function test_status_hides_error_download_link_when_file_is_missing(): void
    {
        [$portal, $portalUser] = $this->createPortalContext();

        $importJob = ImportJob::query()->create([
            'uuid' => '6cb390f4-8f91-4fc1-9ec6-a5f5ea1f7b11',
            'portal_id' => $portal->id,
            'bitrix_user_id' => $portalUser->bitrix_user_id,
            'entity_type_id' => 1042,
            'entity_title' => 'Сделки импорта',
            'status' => ImportJob::STATUS_COMPLETED,
            'total_rows' => 3,
            'processed_rows' => 3,
            'success_rows' => 1,
            'error_rows' => 2,
            'source_file_path' => 'private/imports/dummy.xlsx',
            'error_file_path' => 'errors/missing-errors-file.xlsx',
            'finished_at' => now(),
        ]);

        $statusResponse = $this
            ->withSession($this->bitrixSession($portal, $portalUser))
            ->getJson(route('imports.status', $importJob));

        $statusResponse
            ->assertOk()
            ->assertJsonPath('error_report_url', null);
    }

    /**
     * @return array{0:Portal,1:PortalUser}
     */
    private function createPortalContext(): array
    {
        $portal = Portal::query()->create([
            'member_id' => 'member-test-1',
            'domain' => 'b24-test.bitrix24.ru',
            'protocol' => 'https',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
        ]);

        $portalUser = PortalUser::query()->create([
            'portal_id' => $portal->id,
            'bitrix_user_id' => 1,
            'name' => 'Test Admin',
            'is_admin' => true,
            'is_integrator' => false,
            'department_ids' => [],
            'last_seen_at' => now(),
        ]);

        return [$portal, $portalUser];
    }

    /**
     * @return array{bitrix_context:array{portal_id:int,portal_user_id:int}}
     */
    private function bitrixSession(Portal $portal, PortalUser $portalUser): array
    {
        return [
            'bitrix_context' => [
                'portal_id' => $portal->id,
                'portal_user_id' => $portalUser->id,
            ],
        ];
    }
}

