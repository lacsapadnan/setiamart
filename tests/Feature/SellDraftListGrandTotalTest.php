<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellCartDraft;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellDraftListGrandTotalTest extends TestCase
{
    use RefreshDatabase;

    public function test_penjualan_draft_api_grand_total_reflects_cart_lines_not_stale_sell_column(): void
    {
        [$user, $product, $unit] = $this->createSalesContext();

        $customer = Customer::create([
            'name' => 'Pelanggan Draft',
        ]);

        $sell = Sell::create([
            'cashier_id' => $user->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $user->warehouse_id,
            'order_number' => 'PJ-TEST-DRAFT-001',
            'subtotal' => 9_999_999,
            'grand_total' => 9_999_999,
            'pay' => 0,
            'change' => 0,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'draft',
            'cash' => 0,
            'transfer' => 0,
        ]);

        $response = $this->actingAs($user)->getJson(route('api.penjualan-draft'));

        $response->assertOk();
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertSame($sell->id, $payload[0]['id']);
        $this->assertSame(0, $payload[0]['grand_total']);
    }

    public function test_penjualan_draft_api_grand_total_matches_sum_of_cart_drafts(): void
    {
        [$user, $product, $unit] = $this->createSalesContext();

        $customer = Customer::create([
            'name' => 'Pelanggan Draft 2',
        ]);

        $sell = Sell::create([
            'cashier_id' => $user->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $user->warehouse_id,
            'order_number' => 'PJ-TEST-DRAFT-002',
            'subtotal' => 100,
            'grand_total' => 100,
            'pay' => 0,
            'change' => 0,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'draft',
            'cash' => 0,
            'transfer' => 0,
        ]);

        SellCartDraft::create([
            'sell_id' => $sell->id,
            'cashier_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 2,
            'price' => 5000,
            'diskon' => 500,
        ]);

        $response = $this->actingAs($user)->getJson(route('api.penjualan-draft'));

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame(9500, $payload[0]['grand_total']);
    }

    /**
     * @return array{0: User, 1: Product, 2: Unit}
     */
    private function createSalesContext(): array
    {
        $warehouse = Warehouse::create([
            'name' => 'Warehouse Test',
            'address' => 'Test Address',
            'phone' => '08123456789',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        $unit = Unit::create([
            'name' => 'Unit Test',
        ]);

        $product = Product::create([
            'group' => 'Test',
            'name' => 'Produk Uji',
            'unit_dus' => $unit->id,
            'unit_pak' => $unit->id,
            'unit_eceran' => $unit->id,
            'dus_to_eceran' => 10,
            'pak_to_eceran' => 5,
            'price_sell_dus' => 10000,
            'price_sell_pak' => 5000,
            'price_sell_eceran' => 1000,
            'isShow' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 1000,
        ]);

        return [$user, $product, $unit];
    }
}
