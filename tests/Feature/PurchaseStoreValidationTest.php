<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseStoreValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_missing_supplier_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('pembelian.create'))->post(route('pembelian.store'), [
            'order_number' => 'PL-TEST-0001',
            'reciept_date' => '2026-04-13',
        ]);

        $response->assertRedirect(route('pembelian.create'));
        $response->assertInvalid([
            'supplier_id' => 'Supplier wajib dipilih.',
        ]);
    }

    public function test_store_rejects_blank_supplier_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('pembelian.create'))->post(route('pembelian.store'), [
            'supplier_id' => '',
            'order_number' => 'PL-TEST-0001',
            'reciept_date' => '2026-04-13',
        ]);

        $response->assertRedirect(route('pembelian.create'));
        $response->assertInvalid([
            'supplier_id' => 'Supplier wajib dipilih.',
        ]);
    }
}
