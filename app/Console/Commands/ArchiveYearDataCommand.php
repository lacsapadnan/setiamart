<?php

namespace App\Console\Commands;

use App\Services\ArchiveYearDataService;
use Illuminate\Console\Command;
use Throwable;

class ArchiveYearDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:archive-year
                            {year : Tahun yang diarsipkan, contoh 2023}
                            {--dump : Buat file SQL arsip di storage/app/backups}
                            {--delete : Hapus data tahun tersebut setelah (atau tanpa) dump}
                            {--force : Lewati konfirmasi interaktif}
                            {--allow-open-balances : Izinkan hapus meski masih ada piutang/hutang}
                            {--filename= : Nama file dump opsional}
                            {--chunk=1000 : Ukuran batch delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dry-run, dump, dan/atau hapus data transaksi untuk satu tahun (bukan master data)';

    public function __construct(private ArchiveYearDataService $archiveYearDataService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) $this->argument('year');

        if ($year < 2000 || $year > (int) now()->format('Y')) {
            $this->error("Tahun tidak valid: {$year}");

            return self::FAILURE;
        }

        if ($year >= (int) now()->format('Y')) {
            $this->error('Tidak boleh mengarsipkan tahun berjalan. Pilih tahun sebelumnya.');

            return self::FAILURE;
        }

        [$start, $end] = $this->archiveYearDataService->dateRange($year);

        $this->info("Archive year {$year}");
        $this->line("Range: {$start->toDateTimeString()} s/d {$end->toDateTimeString()} (end exclusive)");
        $this->newLine();

        $counts = $this->archiveYearDataService->countRows($year);
        $total = collect($counts)->sum('count');

        $this->info('Transactional rows (can be deleted):');
        $this->table(
            ['Table', 'Label', 'Rows', 'Exists'],
            collect($counts)->map(fn (array $row) => [
                $row['table'],
                $row['label'],
                number_format($row['count']),
                $row['exists'] ? 'yes' : 'missing',
            ])->all()
        );

        $this->info('Total transactional rows: '.number_format($total));

        $referenceCounts = $this->archiveYearDataService->countReferenceRows();
        $this->newLine();
        $this->info('Reference/master rows (included in --dump for lookup, NEVER deleted):');
        $this->table(
            ['Table', 'Label', 'Rows', 'Exists'],
            collect($referenceCounts)->map(fn (array $row) => [
                $row['table'],
                $row['label'],
                number_format($row['count']),
                $row['exists'] ? 'yes' : 'missing',
            ])->all()
        );

        $openBalances = $this->archiveYearDataService->countOpenBalances($year);
        if ($openBalances['piutang'] > 0 || $openBalances['hutang'] > 0) {
            $this->warn("Open balances in {$year}: piutang={$openBalances['piutang']}, hutang={$openBalances['hutang']}");
        }

        $shouldDump = (bool) $this->option('dump');
        $shouldDelete = (bool) $this->option('delete');

        if (! $shouldDump && ! $shouldDelete) {
            $this->newLine();
            $this->comment('Dry-run only. Lanjutkan dengan:');
            $this->line('  php artisan db:archive-year '.$year.' --dump');
            $this->line('  php artisan db:archive-year '.$year.' --dump --delete');

            return self::SUCCESS;
        }

        if ($shouldDump) {
            try {
                $this->info('Creating archive dump...');
                $path = $this->archiveYearDataService->dumpYear($year, $this->option('filename'));
                $this->info("Dump saved: storage/app/{$path}");
                $this->warn('Salin file ini ke storage offline sebelum menghapus data production.');
            } catch (Throwable $e) {
                $this->error('Dump failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        if (! $shouldDelete) {
            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('Tidak ada data untuk dihapus.');

            return self::SUCCESS;
        }

        if (($openBalances['piutang'] > 0 || $openBalances['hutang'] > 0) && ! $this->option('allow-open-balances')) {
            $this->error('Masih ada piutang/hutang di tahun tersebut. Lunasi dulu, atau tambahkan --allow-open-balances.');

            return self::FAILURE;
        }

        if (! $shouldDump && ! $this->option('force')) {
            $this->error('Hapus tanpa --dump berisiko. Tambahkan --dump, atau --force jika memang yakin backup sudah ada.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("Hapus {$total} baris data transaksi tahun {$year}? Tindakan ini tidak bisa di-undo tanpa restore backup.", false)) {
                $this->warn('Dibatalkan.');

                return self::SUCCESS;
            }

            $typed = $this->ask("Ketik ulang tahun {$year} untuk konfirmasi");
            if ((int) $typed !== $year) {
                $this->error('Konfirmasi tahun tidak cocok. Dibatalkan.');

                return self::FAILURE;
            }
        }

        $chunk = max(1, (int) $this->option('chunk'));

        $this->info("Deleting in chunks of {$chunk}...");

        try {
            $deletedRows = $this->archiveYearDataService->deleteYear($year, $chunk);
        } catch (Throwable $e) {
            $this->error('Delete failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Label', 'Deleted'],
            collect($deletedRows)->map(fn (array $row) => [
                $row['table'],
                $row['label'],
                number_format($row['deleted']),
            ])->all()
        );

        $this->info('Deleted total: '.number_format(collect($deletedRows)->sum('deleted')));
        $this->comment('Master/reference data (users, produk, customer, gudang, dll) tidak dihapus — hanya ikut di file dump untuk lookup.');

        return self::SUCCESS;
    }
}
