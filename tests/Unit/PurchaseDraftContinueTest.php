<?php

namespace Tests\Unit;

use App\Models\Purchase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PurchaseDraftContinueTest extends TestCase
{
    public function test_purchase_receipt_date_is_cast_and_formatted_for_draft_form(): void
    {
        $purchase = new Purchase([
            'status' => 'draft',
            'reciept_date' => '2026-07-10',
        ]);

        $this->assertInstanceOf(Carbon::class, $purchase->reciept_date);
        $this->assertSame('10/07/2026', $purchase->reciept_date->format('d/m/Y'));

        $isDraftMode = $purchase->status === 'draft';
        $value = Blade::render(
            '{{ $isDraftMode && $purchase->reciept_date ? $purchase->reciept_date->format(\'d/m/Y\') : date(\'d/m/Y\') }}',
            [
                'isDraftMode' => $isDraftMode,
                'purchase' => $purchase,
            ]
        );

        $this->assertSame('10/07/2026', trim($value));
    }

    public function test_draft_form_falls_back_to_today_when_receipt_date_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13'));

        $purchase = new Purchase([
            'status' => 'draft',
            'reciept_date' => null,
        ]);

        $isDraftMode = $purchase->status === 'draft';
        $value = Blade::render(
            '{{ $isDraftMode && $purchase->reciept_date ? $purchase->reciept_date->format(\'d/m/Y\') : date(\'d/m/Y\') }}',
            [
                'isDraftMode' => $isDraftMode,
                'purchase' => $purchase,
            ]
        );

        $this->assertSame('13/07/2026', trim($value));

        Carbon::setTestNow();
    }
}
