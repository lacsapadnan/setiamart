<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
                            {--filename= : Optional backup filename (.sql)}
                            {--timeout=0 : Process timeout in seconds (0 = no timeout)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a SQL backup in storage/app/backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port', 3306);
        $dumpBinary = (string) env('MYSQLDUMP_PATH', 'mysqldump');

        if (blank($database) || blank($username) || blank($host)) {
            throw new RuntimeException('Database credentials are not configured for backup.');
        }

        Storage::makeDirectory('backups');

        $fileName = $this->option('filename');
        if (blank($fileName)) {
            $fileName = 'backup_'.now()->format('Y-m-d_H-i-s').'.sql';
        }

        $relativePath = 'backups/'.basename($fileName);
        $absolutePath = storage_path('app/'.$relativePath);

        $timeout = (int) $this->option('timeout');

        $this->info('Starting mysqldump (this may take several minutes on large databases)...');

        $pending = Process::path(base_path())
            ->env([
                'MYSQL_PWD' => (string) $password,
            ]);

        // Large POS databases routinely exceed Laravel's default 60s process timeout.
        $pending = $timeout > 0 ? $pending->timeout($timeout) : $pending->forever();

        $process = $pending->run([
            $dumpBinary,
            '--user='.$username,
            '--host='.$host,
            '--port='.$port,
            '--single-transaction',
            '--quick',
            '--result-file='.$absolutePath,
            $database,
        ]);

        if ($process->failed()) {
            $this->error('Database backup failed.');
            if (filled($process->errorOutput())) {
                $this->error($process->errorOutput());
            }

            return self::FAILURE;
        }

        $sizeMb = file_exists($absolutePath)
            ? round(filesize($absolutePath) / 1024 / 1024, 2)
            : 0;

        $this->info($relativePath." ({$sizeMb} MB)");

        return self::SUCCESS;
    }
}
