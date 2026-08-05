<?php

namespace Tests\Unit;

use App\Models\SendStock;
use Tests\TestCase;

class SendStockNumberGenerationUnitTest extends TestCase
{
    public function test_send_stock_model_exposes_generator_method(): void
    {
        $this->assertTrue(method_exists(SendStock::class, 'generateSendStockNumber'));
    }

    public function test_controllers_no_longer_use_total_count_for_send_stock_numbers(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/SendStockController.php'));
        $draftController = file_get_contents(app_path('Http/Controllers/SendStockDraftController.php'));

        $this->assertStringNotContainsString(
            'SendStock::count()',
            $controller,
            'SendStockController must not use total row count for send_stock_number'
        );
        $this->assertStringNotContainsString(
            'SendStock::count()',
            $draftController,
            'SendStockDraftController must not use total row count for send_stock_number'
        );
    }

    public function test_legacy_count_based_formula_can_collide_with_existing_number(): void
    {
        // Reproduce the production failure mode: after a delete (or concurrent create),
        // count()+1 can regenerate a number that already exists for today.
        $existingNumber = 'PS-20260805-11618';
        $totalRowsAfterDelete = 11617;
        $legacyCandidate = 'PS-20260805-'.str_pad((string) ($totalRowsAfterDelete + 1), 4, '0', STR_PAD_LEFT);

        $this->assertSame($existingNumber, $legacyCandidate);
    }
}
