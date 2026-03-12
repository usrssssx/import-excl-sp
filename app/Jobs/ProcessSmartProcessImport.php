<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Imports\SmartProcessImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSmartProcessImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $importJobId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(SmartProcessImportProcessor $processor): void
    {
        $importJob = ImportJob::query()->with('portal')->findOrFail($this->importJobId);
        $processor->process($importJob);
    }

    public function failed(\Throwable $exception): void
    {
        $importJob = ImportJob::query()->find($this->importJobId);
        if (! $importJob) {
            return;
        }

        $importJob->forceFill([
            'status' => ImportJob::STATUS_FAILED,
            'finished_at' => now(),
            'meta' => array_merge($importJob->meta ?? [], [
                'failure_reason' => $exception->getMessage(),
            ]),
        ])->save();
    }
}
