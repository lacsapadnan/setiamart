<?php

namespace Tests\Unit;

use App\Support\QuantityInputNormalizer;
use PHPUnit\Framework\TestCase;

class QuantityInputNormalizerTest extends TestCase
{
    public function test_parses_plain_decimal_string(): void
    {
        $this->assertSame(1.5, QuantityInputNormalizer::toFloatOrNull('1.5'));
    }

    public function test_parses_comma_as_decimal_separator(): void
    {
        $this->assertSame(1.5, QuantityInputNormalizer::toFloatOrNull('1,5'));
    }

    public function test_parses_indonesian_thousands_with_comma_decimals(): void
    {
        $this->assertSame(1234.56, QuantityInputNormalizer::toFloatOrNull('1.234,56'));
    }

    public function test_parses_us_style_number(): void
    {
        $this->assertSame(1234.56, QuantityInputNormalizer::toFloatOrNull('1,234.56'));
    }

    public function test_returns_null_for_empty_or_invalid(): void
    {
        $this->assertNull(QuantityInputNormalizer::toFloatOrNull(null));
        $this->assertNull(QuantityInputNormalizer::toFloatOrNull(''));
        $this->assertNull(QuantityInputNormalizer::toFloatOrNull('   '));
        $this->assertNull(QuantityInputNormalizer::toFloatOrNull('abc'));
        $this->assertNull(QuantityInputNormalizer::toFloatOrNull([]));
    }

    public function test_rounds_to_two_decimal_places(): void
    {
        $this->assertSame(1.23, QuantityInputNormalizer::toFloatOrNull('1.2349'));
    }
}
