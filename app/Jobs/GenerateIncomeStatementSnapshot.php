<?php

namespace App\Jobs;

use App\Services\IncomeStatementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateIncomeStatementSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly array $context) {}

    /**
     * Execute the job.
     */
    public function handle(IncomeStatementService $incomeStatementService): void
    {
        try {
            $payload = $incomeStatementService->generateSnapshot($this->context);
            $incomeStatementService->persistSnapshot($this->context, $payload);
        } catch (\Throwable $exception) {
            $incomeStatementService->persistSnapshot($this->context, [
                'status' => 'failed',
                'error' => 'Failed to generate income statement snapshot.',
                'cache_generated_at' => now()->toISOString(),
            ]);
            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Income statement snapshot job failed', [
            'error' => $exception->getMessage(),
            'context' => $this->context,
        ]);
    }
}
