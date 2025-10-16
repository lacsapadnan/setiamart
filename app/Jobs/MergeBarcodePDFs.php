<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

class MergeBarcodePDFs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    protected string $filename;
    protected int $totalParts;
    protected ?int $userId;
    protected ?int $totalProducts;
    protected ?string $batchId;

    public function __construct(string $filename, int $totalParts, ?int $userId = null, ?int $totalProducts = null, ?string $batchId = null)
    {
        $this->filename = $filename;
        $this->totalParts = $totalParts;
        $this->userId = $userId;
        $this->totalProducts = $totalProducts;
        $this->batchId = $batchId;
    }

    public function handle(): void
    {
        try {
            ini_set('memory_limit', '1024M');
            set_time_limit(600);

            $pdf = PDFMerger::init();
            $partFiles = [];

            // Add all parts to merger
            for ($i = 0; $i < $this->totalParts; $i++) {
                $partFilename = str_replace('.pdf', "_part{$i}.pdf", $this->filename);
                $partPath = 'barcodes/' . $partFilename;

                if (!Storage::disk('public')->exists($partPath)) {
                    Log::warning('Part file not found', [
                        'part' => $i,
                        'path' => $partPath
                    ]);
                    continue;
                }

                $fullPath = storage_path('app/public/' . $partPath);
                $pdf->addPDF($fullPath, 'all');
                $partFiles[] = $partPath;

            }

            if (empty($partFiles)) {
                throw new \Exception('No part files found to merge');
            }

            // Merge and save
            $mergedPath = storage_path('app/public/barcodes/' . $this->filename);
            $pdf->merge();
            $pdf->save($mergedPath);

            // Verify merged file
            if (!file_exists($mergedPath)) {
                throw new \Exception('Merged PDF was not created');
            }

            // Delete part files
            foreach ($partFiles as $partPath) {
                Storage::disk('public')->delete($partPath);
            }

        } catch (\Exception $e) {
            Log::error('PDF merge failed', [
                'filename' => $this->filename,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PDF merge job failed permanently', [
            'filename' => $this->filename,
            'error' => $exception->getMessage()
        ]);
    }
}
