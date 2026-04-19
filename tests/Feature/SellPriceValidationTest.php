<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellCartDraft;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellPriceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_penjualan_add_cart_rejects_zero_price(): void
    {
        [$user, $product, $unit] = $this->createSalesContext();

        $payload = [
            'requests' => [[
                'product_id' => $product->id,
                'quantity_dus' => 1,
                'quantity_pak' => 0,
                'quantity_eceran' => 0,
                'diskon_dus' => 0,
                'diskon_pak' => 0,
                'diskon_eceran' => 0,
                'unit_dus' => $unit->id,
                'unit_pak' => $unit->id,
                'unit_eceran' => $unit->id,
                'price_dus' => 0,
                'price_pak' => 5000,
                'price_eceran' => 1000,
            ]],
        ];

        $response = $this->actingAs($user)->postJson(route('penjualan.addCart'), $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0', "Harga jual produk {$product->name} (dus) harus lebih dari 0.");

        $this->assertDatabaseCount('sell_carts', 0);
    }

    public function test_penjualan_add_cart_rejects_empty_price(): void
    {
        [$user, $product, $unit] = $this->createSalesContext();

        $payload = [
            'requests' => [[
                'product_id' => $product->id,
                'quantity_dus' => 0,
                'quantity_pak' => 1,
                'quantity_eceran' => 0,
                'diskon_dus' => 0,
                'diskon_pak' => 0,
                'diskon_eceran' => 0,
                'unit_dus' => $unit->id,
                'unit_pak' => $unit->id,
                'unit_eceran' => $unit->id,
                'price_dus' => 10000,
                'price_pak' => '',
                'price_eceran' => 1000,
            ]],
        ];

        $response = $this->actingAs($user)->postJson(route('penjualan.addCart'), $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0', "Harga jual produk {$product->name} (pak) harus lebih dari 0.");

        $this->assertDatabaseCount('sell_carts', 0);
    }

    public function test_penjualan_add_cart_allows_valid_price(): void
    {
        [$user, $product, $unit] = $this->createSalesContext();

        $payload = [
            'requests' => [[
                'product_id' => $product->id,
                'quantity_dus' => 1,
                'quantity_pak' => 0,
                'quantity_eceran' => 0,
                'diskon_dus' => 0,
                'diskon_pak' => 0,
                'diskon_eceran' => 0,
                'unit_dus' => $unit->id,
                'unit_pak' => $unit->id,
                'unit_eceran' => $unit->id,
                'price_dus' => 10000,
                'price_pak' => 5000,
                'price_eceran' => 1000,
            ]],
        ];

        $response = $this->actingAs($user)->postJson(route('penjualan.addCart'), $payload);

        $response->assertOk()
            ->assertJsonPath('success', 'Items added to cart successfully.');

        $this->assertDatabaseHas('sell_carts', [
            'cashier_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'price' => 10000,
        ]);
    }

    public function test_penjualan_draft_add_cart_rejects_zero_price(): void
    {
        [$user, $product, $unit] = $this->createSalesContext();
        $sell = Sell::create([
            'cashier_id' => $user->id,
            'customer_id' => null,
            'warehouse_id' => $user->warehouse_id,
            'order_number' => 'DRAFT-001',
            'subtotal' => 0,
            'grand_total' => 0,
            'pay' => 0,
            'change' => 0,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'draft',
            'cash' => 0,
            'transfer' => 0,
        ]);

        $payload = [
            'requests' => [[
                'product_id' => $product->id,
                'sell_id' => $sell->id,
                'quantity_dus' => 1,
                'quantity_pak' => 0,
                'quantity_eceran' => 0,
                'diskon_dus' => 0,
                'diskon_pak' => 0,
                'diskon_eceran' => 0,
                'unit_dus' => $unit->id,
                'unit_pak' => $unit->id,
                'unit_eceran' => $unit->id,
                'price_dus' => 0,
                'price_pak' => 5000,
                'price_eceran' => 1000,
            ]],
        ];

        $response = $this->actingAs($user)->postJson(route('penjualan-draft.addCart'), $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0', "Harga jual produk {$product->name} (dus) harus lebih dari 0.");

        $this->assertDatabaseCount('sell_cart_drafts', 0);
    }

    public function test_penjualan_draft_add_cart_accepts_decimal_quantity(): void
    {
        [$user, $product, $unit] = $this->createSalesContext();
        $sell = Sell::create([
            'cashier_id' => $user->id,
            'customer_id' => null,
            'warehouse_id' => $user->warehouse_id,
            'order_number' => 'DRAFT-DEC-001',
            'subtotal' => 0,
            'grand_total' => 0,
            'pay' => 0,
            'change' => 0,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'draft',
            'cash' => 0,
            'transfer' => 0,
        ]);

        $payload = [
            'requests' => [[
                'product_id' => $product->id,
                'sell_id' => $sell->id,
                'quantity_dus' => '1,5',
                'quantity_pak' => 0,
                'quantity_eceran' => 0,
                'diskon_dus' => 0,
                'diskon_pak' => 0,
                'diskon_eceran' => 0,
                'unit_dus' => $unit->id,
                'unit_pak' => $unit->id,
                'unit_eceran' => $unit->id,
                'price_dus' => 10000,
                'price_pak' => 5000,
                'price_eceran' => 1000,
            ]],
        ];

        $response = $this->actingAs($user)->postJson(route('penjualan-draft.addCart'), $payload);

        $response->assertOk()
            ->assertJsonPath('success', 'Items added to cart successfully.');

        $this->assertDatabaseHas('sell_cart_drafts', [
            'sell_id' => $sell->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => '1.5',
        ]);
    }

    public function test_penjualan_draft_update_cart_quantity_accepts_decimal_string(): void
    {
        [$user, $product, $unit] = $this->createSalesContext();
        $sell = Sell::create([
            'cashier_id' => $user->id,
            'customer_id' => null,
            'warehouse_id' => $user->warehouse_id,
            'order_number' => 'DRAFT-PATCH-001',
            'subtotal' => 0,
            'grand_total' => 0,
            'pay' => 0,
            'change' => 0,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'draft',
            'cash' => 0,
            'transfer' => 0,
        ]);

        $cart = SellCartDraft::create([
            'sell_id' => $sell->id,
            'cashier_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 1,
            'price' => 1000,
            'diskon' => 0,
        ]);

        $response = $this->actingAs($user)->patchJson(route('penjualan-draft.updateCartQuantity', $cart->id), [
            'quantity' => '2,25',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 2.25);

        $this->assertDatabaseHas('sell_cart_drafts', [
            'id' => $cart->id,
            'quantity' => '2.25',
        ]);
    }

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
