<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ProductUnitConverter;
use PHPUnit\Framework\TestCase;

class ProductUnitConverterTest extends TestCase
{
    private ProductUnitConverter $converter;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = new ProductUnitConverter;
        $this->product = new Product([
            'unit_dus' => 1,
            'unit_pak' => 2,
            'unit_eceran' => 3,
            'dus_to_eceran' => 10,
            'pak_to_eceran' => 5,
        ]);
    }

    public function test_convert_to_eceran_from_dus_pak_and_eceran(): void
    {
        $this->assertSame(20, $this->converter->convertToEceran(2, 1, $this->product));
        $this->assertSame(15, $this->converter->convertToEceran(3, 2, $this->product));
        $this->assertSame(7, $this->converter->convertToEceran(7, 3, $this->product));
        $this->assertSame(0, $this->converter->convertToEceran(7, 99, $this->product));
    }

    public function test_convert_from_eceran_to_dus_pak_and_eceran(): void
    {
        $this->assertEquals(2, $this->converter->convertFromEceran(20, 1, $this->product));
        $this->assertEquals(3, $this->converter->convertFromEceran(15, 2, $this->product));
        $this->assertEquals(7, $this->converter->convertFromEceran(7, 3, $this->product));
        $this->assertEquals(0, $this->converter->convertFromEceran(7, 99, $this->product));
    }

    public function test_convert_price_to_and_from_eceran(): void
    {
        $this->assertEquals(1000, $this->converter->convertPriceToEceran(10000, 1, $this->product));
        $this->assertEquals(2000, $this->converter->convertPriceToEceran(10000, 2, $this->product));
        $this->assertEquals(10000, $this->converter->convertPriceToEceran(10000, 3, $this->product));

        $this->assertEquals(10000, $this->converter->convertPriceFromEceran(1000, 1, $this->product));
        $this->assertEquals(5000, $this->converter->convertPriceFromEceran(1000, 2, $this->product));
        $this->assertEquals(1000, $this->converter->convertPriceFromEceran(1000, 3, $this->product));
    }
}
