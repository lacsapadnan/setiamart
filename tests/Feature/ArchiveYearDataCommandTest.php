<?php

namespace Tests\Feature;

use App\Services\ArchiveYearDataService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ArchiveYearDataCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $this->artisan('db:archive-year --help')
            ->assertSuccessful();
    }

    public function test_rejects_current_year(): void
    {
        $year = (int) now()->format('Y');

        $this->artisan('db:archive-year', ['year' => $year])
            ->expectsOutputToContain('Tidak boleh mengarsipkan tahun berjalan')
            ->assertFailed();
    }

    public function test_rejects_invalid_year(): void
    {
        $this->artisan('db:archive-year', ['year' => 1999])
            ->expectsOutputToContain('Tahun tidak valid')
            ->assertFailed();
    }

    public function test_date_range_is_full_calendar_year_exclusive_end(): void
    {
        $service = new ArchiveYearDataService;
        [$start, $end] = $service->dateRange(2023);

        $this->assertTrue($start->equalTo(Carbon::parse('2023-01-01 00:00:00')));
        $this->assertTrue($end->equalTo(Carbon::parse('2024-01-01 00:00:00')));
    }

    public function test_tables_exclude_master_data_from_purge(): void
    {
        $service = new ArchiveYearDataService;
        $tables = collect($service->tables())->pluck('table');

        $this->assertTrue($tables->contains('sells'));
        $this->assertTrue($tables->contains('purchases'));
        $this->assertTrue($tables->contains('cashflows'));
        $this->assertTrue($tables->contains('send_stocks'));

        $this->assertFalse($tables->contains('products'));
        $this->assertFalse($tables->contains('customers'));
        $this->assertFalse($tables->contains('warehouses'));
        $this->assertFalse($tables->contains('inventories'));
        $this->assertFalse($tables->contains('users'));
    }

    public function test_reference_tables_include_users_without_password_column(): void
    {
        $service = new ArchiveYearDataService;
        $references = collect($service->referenceTables())->keyBy('table');

        $this->assertTrue($references->has('users'));
        $this->assertTrue($references->has('warehouses'));
        $this->assertTrue($references->has('customers'));
        $this->assertTrue($references->has('products'));

        $userColumns = $references['users']['safe_columns'] ?? [];
        $this->assertContains('id', $userColumns);
        $this->assertContains('name', $userColumns);
        $this->assertContains('email', $userColumns);
        $this->assertNotContains('password', $userColumns);
        $this->assertNotContains('remember_token', $userColumns);
    }

    public function test_delete_order_removes_child_tables_before_parents(): void
    {
        $service = new ArchiveYearDataService;
        $tables = collect($service->tables())->pluck('table')->values();

        $this->assertTrue($tables->search('sell_details') < $tables->search('sells'));
        $this->assertTrue($tables->search('sell_retur_details') < $tables->search('sell_returs'));
        $this->assertTrue($tables->search('purchase_details') < $tables->search('purchases'));
        $this->assertTrue($tables->search('send_stock_details') < $tables->search('send_stocks'));
        $this->assertTrue($tables->search('settlements') < $tables->search('treasury_mutations'));
    }

    public function test_delete_without_dump_requires_force(): void
    {
        $this->mock(ArchiveYearDataService::class, function ($mock) {
            $mock->shouldReceive('dateRange')->andReturn([
                Carbon::parse('2023-01-01'),
                Carbon::parse('2024-01-01'),
            ]);
            $mock->shouldReceive('countRows')->andReturn([
                [
                    'table' => 'sells',
                    'label' => 'Penjualan',
                    'exists' => true,
                    'count' => 10,
                ],
            ]);
            $mock->shouldReceive('countReferenceRows')->andReturn([
                [
                    'table' => 'users',
                    'label' => 'User (tanpa password)',
                    'exists' => true,
                    'count' => 3,
                ],
            ]);
            $mock->shouldReceive('countOpenBalances')->andReturn([
                'piutang' => 0,
                'hutang' => 0,
            ]);
            $mock->shouldNotReceive('deleteYear');
        });

        $this->artisan('db:archive-year', [
            'year' => 2023,
            '--delete' => true,
        ])
            ->expectsOutputToContain('Hapus tanpa --dump berisiko')
            ->assertFailed();
    }
}
