<?php

namespace Tests\Unit;

use App\Support\SalePaymentMethodResolver;
use PHPUnit\Framework\TestCase;

class SalePaymentMethodResolverTest extends TestCase
{
    public function test_returns_null_when_no_method_and_no_amounts(): void
    {
        $this->assertNull(SalePaymentMethodResolver::resolve(null, 0, 0));
        $this->assertNull(SalePaymentMethodResolver::resolve('', 0, 0));
    }

    public function test_derives_cash_from_amount_only(): void
    {
        $this->assertSame('cash', SalePaymentMethodResolver::resolve(null, 1000, 0));
        $this->assertSame('cash', SalePaymentMethodResolver::resolve(null, '1.397.000', 0));
    }

    public function test_derives_transfer_from_amount_only(): void
    {
        $this->assertSame('transfer', SalePaymentMethodResolver::resolve(null, 0, 500_000));
    }

    public function test_derives_split_when_both_amounts_positive(): void
    {
        $this->assertSame('split', SalePaymentMethodResolver::resolve(null, 100, 200));
    }

    public function test_normalizes_whitelisted_request_values(): void
    {
        $this->assertSame('cash', SalePaymentMethodResolver::resolve('Cash', 0, 0));
        $this->assertSame('transfer', SalePaymentMethodResolver::resolve('  Transfer  ', 0, 0));
        $this->assertSame('split', SalePaymentMethodResolver::resolve('SPLIT', 0, 0));
    }

    public function test_unknown_request_value_falls_back_to_derivation(): void
    {
        $this->assertSame('cash', SalePaymentMethodResolver::resolve('bitcoin', 1000, 0));
    }

    public function test_unknown_request_value_with_no_amounts_returns_null(): void
    {
        $this->assertNull(SalePaymentMethodResolver::resolve('bitcoin', 0, 0));
    }
}
