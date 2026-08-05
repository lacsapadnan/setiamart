<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ArchiveYearDataService
{
    /**
     * Master/reference tables included in archive dumps for readability (names, not just IDs).
     * These are NEVER deleted by purge.
     *
     * @return list<array{table: string, label: string, safe_columns?: list<string>}>
     */
    public function referenceTables(): array
    {
        return [
            [
                'table' => 'users',
                'label' => 'User (tanpa password)',
                // Exclude secrets from archive dumps.
                'safe_columns' => ['id', 'name', 'email', 'email_verified_at', 'warehouse_id', 'created_at', 'updated_at'],
            ],
            [
                'table' => 'warehouses',
                'label' => 'Gudang/cabang',
            ],
            [
                'table' => 'customers',
                'label' => 'Customer',
            ],
            [
                'table' => 'suppliers',
                'label' => 'Supplier',
            ],
            [
                'table' => 'products',
                'label' => 'Produk',
            ],
            [
                'table' => 'units',
                'label' => 'Satuan',
            ],
            [
                'table' => 'treasuries',
                'label' => 'Kas besar',
            ],
            [
                'table' => 'kas_income_items',
                'label' => 'Item pemasukan kas',
            ],
            [
                'table' => 'kas_expense_items',
                'label' => 'Item pengeluaran kas',
            ],
        ];
    }

    /**
     * Transactional tables eligible for year archive/purge.
     * Master data is intentionally excluded from delete (see referenceTables for dump-only).
     *
     * Delete order: children first, then parents. Cascade FKs are a safety net.
     *
     * @return list<array{
     *     table: string,
     *     label: string,
     *     date_column?: string,
     *     via?: array{parent: string, fk: string, parent_date: string}
     * }>
     */
    public function tables(): array
    {
        return [
            [
                'table' => 'sell_retur_details',
                'label' => 'Detail retur penjualan',
                'via' => ['parent' => 'sell_returs', 'fk' => 'sell_retur_id', 'parent_date' => 'created_at'],
            ],
            [
                'table' => 'sell_returs',
                'label' => 'Retur penjualan',
                'date_column' => 'created_at',
            ],
            [
                'table' => 'sell_details',
                'label' => 'Detail penjualan',
                'via' => ['parent' => 'sells', 'fk' => 'sell_id', 'parent_date' => 'created_at'],
            ],
            [
                'table' => 'sell_cart_drafts',
                'label' => 'Draft keranjang penjualan',
                'via' => ['parent' => 'sells', 'fk' => 'sell_id', 'parent_date' => 'created_at'],
            ],
            [
                'table' => 'sells',
                'label' => 'Penjualan',
                'date_column' => 'created_at',
            ],
            [
                'table' => 'purchase_retur_details',
                'label' => 'Detail retur pembelian',
                'via' => ['parent' => 'purchase_returs', 'fk' => 'purchase_retur_id', 'parent_date' => 'created_at'],
            ],
            [
                'table' => 'purchase_returs',
                'label' => 'Retur pembelian',
                'date_column' => 'created_at',
            ],
            [
                'table' => 'purchase_details',
                'label' => 'Detail pembelian',
                'via' => ['parent' => 'purchases', 'fk' => 'purchase_id', 'parent_date' => 'created_at'],
            ],
            [
                'table' => 'purchases',
                'label' => 'Pembelian',
                'date_column' => 'created_at',
            ],
            [
                'table' => 'send_stock_details',
                'label' => 'Detail pindah stok',
                'via' => ['parent' => 'send_stocks', 'fk' => 'send_stock_id', 'parent_date' => 'created_at'],
            ],
            [
                'table' => 'send_stocks',
                'label' => 'Pindah stok',
                'date_column' => 'created_at',
            ],
            [
                'table' => 'settlements',
                'label' => 'Settlement',
                'via' => ['parent' => 'treasury_mutations', 'fk' => 'mutation_id', 'parent_date' => 'created_at'],
            ],
            [
                'table' => 'treasury_mutations',
                'label' => 'Mutasi kas besar',
                'date_column' => 'created_at',
            ],
            [
                'table' => 'product_reports',
                'label' => 'Laporan produk',
                'date_column' => 'created_at',
            ],
            [
                'table' => 'cashflows',
                'label' => 'Cashflow',
                'date_column' => 'created_at',
            ],
            [
                'table' => 'kas',
                'label' => 'Kas',
                'date_column' => 'date',
            ],
            [
                'table' => 'activity_log',
                'label' => 'Activity log',
                'date_column' => 'created_at',
            ],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function dateRange(int $year): array
    {
        $start = Carbon::create($year, 1, 1, 0, 0, 0, config('app.timezone'))->startOfDay();
        $end = $start->copy()->addYear();

        return [$start, $end];
    }

    /**
     * @return list<array{table: string, label: string, count: int, exists: bool}>
     */
    public function countRows(int $year): array
    {
        [$start, $end] = $this->dateRange($year);
        $results = [];

        foreach ($this->tables() as $definition) {
            $table = $definition['table'];
            $exists = Schema::hasTable($table);

            $results[] = [
                'table' => $table,
                'label' => $definition['label'],
                'exists' => $exists,
                'count' => $exists ? $this->countForDefinition($definition, $start, $end) : 0,
            ];
        }

        return $results;
    }

    /**
     * @return list<array{table: string, label: string, count: int, exists: bool}>
     */
    public function countReferenceRows(): array
    {
        $results = [];

        foreach ($this->referenceTables() as $definition) {
            $table = $definition['table'];
            $exists = Schema::hasTable($table);

            $results[] = [
                'table' => $table,
                'label' => $definition['label'],
                'exists' => $exists,
                'count' => $exists ? (int) DB::table($table)->count() : 0,
            ];
        }

        return $results;
    }

    /**
     * @return array{piutang: int, hutang: int}
     */
    public function countOpenBalances(int $year): array
    {
        [$start, $end] = $this->dateRange($year);

        $piutang = 0;
        $hutang = 0;

        if (Schema::hasTable('sells')) {
            $piutang = (int) DB::table('sells')
                ->where('status', 'piutang')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end)
                ->count();
        }

        if (Schema::hasTable('purchases')) {
            $hutang = (int) DB::table('purchases')
                ->where('status', 'hutang')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end)
                ->count();
        }

        return [
            'piutang' => $piutang,
            'hutang' => $hutang,
        ];
    }

    /**
     * Create a filtered SQL dump for the given year under storage/app/backups.
     */
    public function dumpYear(int $year, ?string $filename = null): string
    {
        [$start, $end] = $this->dateRange($year);

        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port', 3306);
        $dumpBinary = (string) env('MYSQLDUMP_PATH', 'mysqldump');

        if (blank($database) || blank($username) || blank($host)) {
            throw new RuntimeException('Database credentials are not configured for archive dump.');
        }

        Storage::makeDirectory('backups');

        if (blank($filename)) {
            $filename = "archive_{$year}_".now()->format('Y-m-d_H-i-s').'.sql';
        }

        $relativePath = 'backups/'.basename($filename);
        $absolutePath = storage_path('app/'.$relativePath);

        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }

        file_put_contents(
            $absolutePath,
            "-- POS archive dump for year {$year}\n"
            ."-- Range: {$start->toDateTimeString()} to {$end->toDateTimeString()} (exclusive end)\n"
            .'-- Generated: '.now()->toDateTimeString()."\n"
            ."-- Section 1: reference/master data (INSERT IGNORE, never purged)\n"
            ."-- Section 2: transactional data for the year\n\n"
        );

        $this->appendReferenceDumps($absolutePath, $dumpBinary, $database, $username, $password, $host, $port);

        file_put_contents($absolutePath, "\n-- ===== TRANSACTIONAL DATA ({$year}) =====\n", FILE_APPEND);

        foreach ($this->tables() as $definition) {
            $table = $definition['table'];

            if (! Schema::hasTable($table)) {
                continue;
            }

            $where = $this->mysqldumpWhereClause($definition, $start, $end);
            $output = $this->runMysqlDump(
                $dumpBinary,
                $database,
                $username,
                $password,
                $host,
                $port,
                $table,
                [
                    '--no-create-info',
                    '--skip-triggers',
                    '--complete-insert',
                    '--quick',
                    '--where='.$where,
                ]
            );

            file_put_contents($absolutePath, "\n-- Table: {$table}\n".$output, FILE_APPEND);
        }

        return $relativePath;
    }

    /**
     * @param  list<string>  $extraArgs
     */
    private function runMysqlDump(
        string $dumpBinary,
        string $database,
        ?string $username,
        ?string $password,
        string $host,
        string $port,
        string $table,
        array $extraArgs
    ): string {
        $tempRelative = 'backups/.tmp_archive_'.$table.'_'.uniqid('', true).'.sql';
        $tempAbsolute = storage_path('app/'.$tempRelative);

        $command = array_merge(
            [
                $dumpBinary,
                '--user='.$username,
                '--host='.$host,
                '--port='.$port,
                '--single-transaction',
                '--result-file='.$tempAbsolute,
            ],
            $extraArgs,
            [$database, $table]
        );

        $process = Process::path(base_path())
            ->env([
                'MYSQL_PWD' => (string) $password,
            ])
            ->forever()
            ->run($command);

        if ($process->failed()) {
            if (file_exists($tempAbsolute)) {
                unlink($tempAbsolute);
            }

            throw new RuntimeException("Archive dump failed for table [{$table}]: ".$process->errorOutput());
        }

        $output = file_exists($tempAbsolute) ? (string) file_get_contents($tempAbsolute) : '';

        if (file_exists($tempAbsolute)) {
            unlink($tempAbsolute);
        }

        return $output;
    }

    private function appendReferenceDumps(
        string $absolutePath,
        string $dumpBinary,
        string $database,
        ?string $username,
        ?string $password,
        string $host,
        string $port
    ): void {
        file_put_contents($absolutePath, "\n-- ===== REFERENCE / MASTER DATA (lookup) =====\n", FILE_APPEND);

        foreach ($this->referenceTables() as $definition) {
            $table = $definition['table'];

            if (! Schema::hasTable($table)) {
                continue;
            }

            if (isset($definition['safe_columns'])) {
                file_put_contents(
                    $absolutePath,
                    "\n-- Table: {$table} ({$definition['label']})\n".$this->buildSafeInsertIgnoreSql($table, $definition['safe_columns']),
                    FILE_APPEND
                );

                continue;
            }

            $output = $this->runMysqlDump(
                $dumpBinary,
                $database,
                $username,
                $password,
                $host,
                $port,
                $table,
                [
                    '--no-create-info',
                    '--skip-triggers',
                    '--complete-insert',
                    '--insert-ignore',
                    '--quick',
                    '--where=1',
                ]
            );

            file_put_contents($absolutePath, "\n-- Table: {$table} ({$definition['label']})\n".$output, FILE_APPEND);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function buildSafeInsertIgnoreSql(string $table, array $columns): string
    {
        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        if ($existingColumns === []) {
            return "-- skipped: no safe columns available for {$table}\n";
        }

        $rows = DB::table($table)->select($existingColumns)->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return "-- no rows in {$table}\n";
        }

        $columnList = implode(', ', array_map(fn (string $column) => "`{$column}`", $existingColumns));
        $sql = '';

        foreach ($rows->chunk(100) as $chunk) {
            $valueGroups = [];

            foreach ($chunk as $row) {
                $values = [];
                foreach ($existingColumns as $column) {
                    $values[] = $this->quoteSqlValue($row->{$column} ?? null);
                }
                $valueGroups[] = '('.implode(', ', $values).')';
            }

            $sql .= "INSERT IGNORE INTO `{$table}` ({$columnList}) VALUES\n".implode(",\n", $valueGroups).";\n";
        }

        return $sql;
    }

    private function quoteSqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'";
    }

    /**
     * Delete archived-year rows in FK-safe order. Uses query builder (no Eloquent events).
     *
     * @return list<array{table: string, label: string, deleted: int}>
     */
    public function deleteYear(int $year, int $chunkSize = 1000): array
    {
        [$start, $end] = $this->dateRange($year);
        $results = [];

        foreach ($this->tables() as $definition) {
            $table = $definition['table'];

            if (! Schema::hasTable($table)) {
                $results[] = [
                    'table' => $table,
                    'label' => $definition['label'],
                    'deleted' => 0,
                ];

                continue;
            }

            $deleted = $this->deleteForDefinition($definition, $start, $end, $chunkSize);

            $results[] = [
                'table' => $table,
                'label' => $definition['label'],
                'deleted' => $deleted,
            ];
        }

        return $results;
    }

    /**
     * @param  array{
     *     table: string,
     *     label: string,
     *     date_column?: string,
     *     via?: array{parent: string, fk: string, parent_date: string}
     * }  $definition
     */
    private function countForDefinition(array $definition, Carbon $start, Carbon $end): int
    {
        $query = DB::table($definition['table']);
        $this->applyYearFilter($query, $definition, $start, $end);

        return (int) $query->count();
    }

    /**
     * @param  array{
     *     table: string,
     *     label: string,
     *     date_column?: string,
     *     via?: array{parent: string, fk: string, parent_date: string}
     * }  $definition
     */
    private function deleteForDefinition(array $definition, Carbon $start, Carbon $end, int $chunkSize): int
    {
        $deleted = 0;
        $table = $definition['table'];

        do {
            $query = DB::table($table);
            $this->applyYearFilter($query, $definition, $start, $end);

            $ids = $query->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += DB::table($table)->whereIn('id', $ids)->delete();
        } while (true);

        return $deleted;
    }

    /**
     * @param  Builder  $query
     * @param  array{
     *     table: string,
     *     label: string,
     *     date_column?: string,
     *     via?: array{parent: string, fk: string, parent_date: string}
     * }  $definition
     */
    private function applyYearFilter($query, array $definition, Carbon $start, Carbon $end): void
    {
        if (isset($definition['via'])) {
            $via = $definition['via'];
            $query->whereIn($via['fk'], function ($sub) use ($via, $start, $end) {
                $sub->select('id')
                    ->from($via['parent'])
                    ->where($via['parent_date'], '>=', $start)
                    ->where($via['parent_date'], '<', $end);
            });

            return;
        }

        $dateColumn = $definition['date_column'] ?? 'created_at';
        $query->where($dateColumn, '>=', $start)
            ->where($dateColumn, '<', $end);
    }

    /**
     * @param  array{
     *     table: string,
     *     label: string,
     *     date_column?: string,
     *     via?: array{parent: string, fk: string, parent_date: string}
     * }  $definition
     */
    private function mysqldumpWhereClause(array $definition, Carbon $start, Carbon $end): string
    {
        $startSql = $start->format('Y-m-d H:i:s');
        $endSql = $end->format('Y-m-d H:i:s');

        if (isset($definition['via'])) {
            $via = $definition['via'];

            return sprintf(
                "%s IN (SELECT id FROM %s WHERE %s >= '%s' AND %s < '%s')",
                $via['fk'],
                $via['parent'],
                $via['parent_date'],
                $startSql,
                $via['parent_date'],
                $endSql
            );
        }

        $dateColumn = $definition['date_column'] ?? 'created_at';

        return sprintf(
            "%s >= '%s' AND %s < '%s'",
            $dateColumn,
            $startSql,
            $dateColumn,
            $endSql
        );
    }
}
