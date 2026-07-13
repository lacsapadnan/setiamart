<?php

namespace Tests\Unit;

use App\Exports\SalesExport;
use PHPUnit\Framework\TestCase;

class SalesExportTest extends TestCase
{
    public function test_map_rounds_currency_columns_to_whole_numbers(): void
    {
        $export = new SalesExport;

        $row = (object) [
            'order_number' => 'PJ-TEST-0001',
            'cashier_name' => 'Kasir',
            'customer_name' => 'Customer',
            'warehouse_name' => 'Cabang',
            'payment_method' => 'cash',
            'cash' => '2924000',
            'transfer' => '0',
            'grand_total' => '2671576.6666667',
            'status' => 'lunas',
            'created_at' => '2026-04-16 16:53:35',
        ];

        $mapped = $export->map($row);

        $this->assertSame(2924000.0, $mapped[5]);
        $this->assertSame(0.0, $mapped[6]);
        $this->assertSame(2671577.0, $mapped[7]);
    }
}
