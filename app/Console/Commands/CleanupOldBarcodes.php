<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupOldBarcodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'barcode:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up barcode PDF files older than 1 day';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting barcode cleanup...');

        try {
            $disk = Storage::disk('public');
            $directory = 'barcodes';

            // Check if directory exists
            if (!$disk->exists($directory)) {
                $this->info('Barcodes directory does not exist. Nothing to clean up.');
                return Command::SUCCESS;
            }

            $files = $disk->files($directory);

            if (empty($files)) {
                $this->info('No barcode files found. Nothing to clean up.');
                return Command::SUCCESS;
            }

            $threshold = now()->subDay()->timestamp;
            $deletedCount = 0;
            $errorCount = 0;
            $deletedFiles = [];

            foreach ($files as $file) {
                try {
                    $lastModified = $disk->lastModified($file);

                    if ($lastModified < $threshold) {
                        $filename = basename($file);
                        
                        if ($disk->delete($file)) {
                            $deletedCount++;
                            $deletedFiles[] = $filename;
                            $this->line("Deleted: {$filename}");
                        }
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Failed to process file: {$file} - {$e->getMessage()}");
                    Log::error('Barcode cleanup error', [
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Log the cleanup activity
            Log::info('Barcode cleanup completed', [
                'total_files_scanned' => count($files),
                'files_deleted' => $deletedCount,
                'errors' => $errorCount,
                'deleted_files' => $deletedFiles
            ]);

            // Display summary
            $this->newLine();
            $this->info("Cleanup completed successfully!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total files scanned', count($files)],
                    ['Files deleted', $deletedCount],
                    ['Errors', $errorCount],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('An error occurred during cleanup: ' . $e->getMessage());
            Log::error('Barcode cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}

