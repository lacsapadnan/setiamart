<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseCart;
use App\Models\SellCart;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartQuantityDecimalTest extends TestCase
{
    use RefreshDatabase;

    public function test_sell_cart_quantity_can_be_updated_with_two_decimal_places(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Test Address',
            'phone' => '081234567890',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        $unit = Unit::create([
            'name' => 'Dus',
        ]);

        $product = Product::create([
            'group' => 'Test',
            'name' => 'Test Product',
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

        $cart = SellCart::create([
            'cashier_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => '1',
            'price' => 10000,
            'diskon' => 0,
        ]);

        $response = $this->actingAs($user)->patchJson("/penjualan/cart/{$cart->id}/quantity", [
            'quantity' => '1.25',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 1.25);

        $this->assertSame(1.25, round((float) $cart->fresh()->quantity, 2));
    }

    public function test_purchase_cart_quantity_can_be_updated_with_two_decimal_places(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Test Address',
            'phone' => '081234567890',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        $unit = Unit::create([
            'name' => 'Dus',
        ]);

        $product = Product::create([
            'group' => 'Test',
            'name' => 'Test Product',
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

        $cart = PurchaseCart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => '1',
            'discount_fix' => 0,
            'discount_percent' => 0,
            'price_unit' => 10000,
            'total_price' => 10000,
        ]);

        $response = $this->actingAs($user)->patchJson("/pembelian/cart/{$cart->id}/quantity", [
            'quantity' => '1.25',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 1.25);

        $this->assertSame(1.25, round((float) $cart->fresh()->quantity, 2));
    }
}
