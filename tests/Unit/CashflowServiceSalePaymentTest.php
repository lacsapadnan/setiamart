<?php

namespace Tests\Unit;

use App\Models\Cashflow;
use App\Services\CashflowService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RecordingSaleCashflowService extends CashflowService
{
    /** @var list<array<string, mixed>> */
    public array $cashflowPayloads = [];

    public function createCashflow(array $data): Cashflow
    {
        $this->cashflowPayloads[] = $data;

        return new Cashflow($data);
    }
}

class CashflowServiceSalePaymentTest extends TestCase
{
    public function test_handle_sale_payment_throws_on_unknown_method_when_amount_present(): void
    {
        $service = new CashflowService;

        $this->expectException(InvalidArgumentException::class);
        $service->handleSalePayment(
            warehouseId: 1,
            orderNumber: 'PJ-TEST-001',
            customerName: 'Test',
            paymentMethod: 'crypto',
            cash: 1000,
            transfer: 0,
            change: 0
        );
    }

    public function test_handle_sale_payment_unknown_method_with_zero_amounts_is_silent(): void
    {
        $service = new CashflowService;
        $service->handleSalePayment(
            warehouseId: 1,
            orderNumber: 'PJ-TEST-ZERO',
            customerName: 'Test',
            paymentMethod: 'crypto',
            cash: 0,
            transfer: 0,
            change: 0
        );

        $this->addToAssertionCount(1);
    }

    public function test_handle_sale_payment_creates_cashflow_for_cash_via_subclass(): void
    {
        $service = new RecordingSaleCashflowService;
        $service->handleSalePayment(
            warehouseId: 7,
            orderNumber: 'PJ-TEST-002',
            customerName: 'Toko',
            paymentMethod: 'cash',
            cash: 5000,
            transfer: 0,
            change: 0
        );

        $this->assertCount(1, $service->cashflowPayloads);
        $this->assertSame(7, $service->cashflowPayloads[0]['warehouse_id']);
        $this->assertSame('Penjualan', $service->cashflowPayloads[0]['for']);
        $this->assertSame(5000.0, $service->cashflowPayloads[0]['in']);
        $this->assertSame(0, $service->cashflowPayloads[0]['out']);
        $this->assertSame('cash', $service->cashflowPayloads[0]['payment_method']);
    }

    public function test_handle_sale_payment_normalizes_method_casing(): void
    {
        $service = new RecordingSaleCashflowService;
        $service->handleSalePayment(
            warehouseId: 1,
            orderNumber: 'PJ-TEST-003',
            customerName: 'Toko',
            paymentMethod: '  Cash ',
            cash: 100,
            transfer: 0,
            change: 0
        );

        $this->assertCount(1, $service->cashflowPayloads);
        $this->assertSame(100.0, $service->cashflowPayloads[0]['in']);
    }
}
