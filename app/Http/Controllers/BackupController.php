<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function backupDatabase(): BinaryFileResponse|RedirectResponse
    {
        try {
            $fileName = 'backup_'.now()->format('Y-m-d_H-i-s').'.sql';
            $exitCode = Artisan::call('db:backup', [
                '--filename' => $fileName,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException('Backup command returned non-zero exit code.');
            }

            $relativePath = trim(Artisan::output());
            $absolutePath = storage_path('app/'.$relativePath);

            Log::info('Database backup generated', [
                'user_id' => auth()->id(),
                'path' => $relativePath,
            ]);

            return response()->download($absolutePath)->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            report($exception);

            Log::warning('Database backup failed', [
                'user_id' => auth()->id(),
                'error' => $exception->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Backup database gagal diproses. Silakan coba lagi.');
        }
    }
}
