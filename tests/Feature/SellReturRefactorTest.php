<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Sell;
use App\Models\SellDetail;
use App\Models\SellRetur;
use App\Models\SellReturCart;
use App\Models\SellReturDetail;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellReturRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_cart_rejects_invalid_quantity_via_form_request(): void
    {
        [$user, $product, $unit, $sell] = $this->createReturContext();

        $response = $this->actingAs($user)->postJson(route('penjualan-retur.addCart'), [
            'input_requests' => [[
                'sell_id' => $sell->id,
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => 'abc',
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);

        $this->assertDatabaseCount('sell_retur_carts', 0);
    }

    public function test_add_cart_rejects_quantity_exceeding_available(): void
    {
        [$user, $product, $unit, $sell] = $this->createReturContext(soldQty: 2);

        $response = $this->actingAs($user)->postJson(route('penjualan-retur.addCart'), [
            'input_requests' => [[
                'sell_id' => $sell->id,
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => 5,
            ]],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);

        $this->assertDatabaseCount('sell_retur_carts', 0);
    }

    public function test_add_cart_stores_item_and_available_quantities_endpoint_works(): void
    {
        [$user, $product, $unit, $sell] = $this->createReturContext(soldQty: 5);

        $response = $this->actingAs($user)->post(route('penjualan-retur.addCart'), [
            'input_requests' => [[
                'sell_id' => $sell->id,
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => 2,
            ]],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sell_retur_carts', [
            'user_id' => $user->id,
            'sell_id' => $sell->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1000,
        ]);

        $available = $this->actingAs($user)->getJson(
            route('api.retur-available-quantities', [$sell->id, $product->id])
        );

        $available->assertOk();
        $this->assertEquals($product->id, $available->json('product_id'));
        $this->assertEquals(5, $available->json('total_remaining_eceran'));
        $this->assertEquals(2, $available->json('total_returned_eceran'));
        $this->assertEquals(3, $available->json('available_for_return_eceran'));
    }

    public function test_update_cart_quantity_accepts_decimal_and_rejects_over_limit(): void
    {
        [$user, $product, $unit, $sell] = $this->createReturContext(soldQty: 5);

        $cart = SellReturCart::create([
            'sell_id' => $sell->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 1,
            'price' => 1000,
        ]);

        $ok = $this->actingAs($user)->patchJson(
            route('penjualan-retur.updateCartQuantity', $cart->id),
            ['quantity' => '1.50']
        );

        $ok->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 1.5);

        $this->assertSame(1.5, round((float) $cart->fresh()->quantity, 2));

        $fail = $this->actingAs($user)->patchJson(
            route('penjualan-retur.updateCartQuantity', $cart->id),
            ['quantity' => 10]
        );

        $fail->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_store_retur_updates_stock_sell_detail_report_and_clears_cart(): void
    {
        // Use piutang + unpaid to avoid pre-existing CashflowService null payment_method insert.
        [$user, $product, $unit, $sell, $inventory] = $this->createReturContext(
            soldQty: 5,
            inventoryQty: 100,
            status: 'piutang'
        );

        SellReturCart::create([
            'sell_id' => $sell->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 2,
            'price' => 1000,
        ]);

        $response = $this->actingAs($user)->post(route('penjualan-retur.store'), [
            'sell_id' => $sell->id,
        ]);

        $response->assertRedirect(route('penjualan-retur.index'));

        $this->assertDatabaseCount('sell_returs', 1);
        $this->assertDatabaseCount('sell_retur_details', 1);
        $this->assertDatabaseCount('sell_retur_carts', 0);

        $this->assertEquals(3.0, round((float) SellDetail::first()->quantity, 2));
        $this->assertEquals(102.0, round((float) $inventory->fresh()->quantity, 2));
        $this->assertEquals(3000.0, round((float) $sell->fresh()->grand_total, 2));

        $this->assertDatabaseHas('product_reports', [
            'product_id' => $product->id,
            'type' => 'RETUR PENJUALAN',
            'for' => 'MASUK',
            'qty' => 2,
        ]);
    }

    public function test_store_marks_sell_batal_when_all_quantities_returned(): void
    {
        [$user, $product, $unit, $sell] = $this->createReturContext(soldQty: 2, status: 'piutang');

        SellReturCart::create([
            'sell_id' => $sell->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 2,
            'price' => 1000,
        ]);

        $this->actingAs($user)->post(route('penjualan-retur.store'), [
            'sell_id' => $sell->id,
        ])->assertRedirect(route('penjualan-retur.index'));

        $this->assertSame('batal', $sell->fresh()->status);
    }

    public function test_konfirm_return_verifies_remark_and_updates_inventory(): void
    {
        [$user, $product, $unit, $sell, $inventory] = $this->createReturContext(
            soldQty: 5,
            inventoryQty: 50,
            status: 'piutang'
        );

        $retur = SellRetur::create([
            'sell_id' => $sell->id,
            'warehouse_id' => $user->warehouse_id,
            'user_id' => $user->id,
            'retur_date' => now()->toDateString(),
        ]);

        SellReturDetail::create([
            'sell_retur_id' => $retur->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'qty' => 1,
            'price' => 1000,
        ]);

        $response = $this->actingAs($user)->postJson(route('konfirmReturn'), [
            'sell_id' => $sell->id,
            'selectedIds' => [$retur->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Return confirmed successfully');

        $this->assertSame('verify', $retur->fresh()->remark);
        $this->assertEquals(51.0, round((float) $inventory->fresh()->quantity, 2));
        $this->assertEquals(4.0, round((float) SellDetail::first()->quantity, 2));
    }

    public function test_destroy_unverified_retur_restores_stock_and_rejects_verified(): void
    {
        [$user, $product, $unit, $sell, $inventory] = $this->createReturContext(
            soldQty: 3,
            inventoryQty: 50,
            status: 'piutang'
        );

        // Simulate store effects: detail deducted, stock already increased by return
        SellDetail::first()->update(['quantity' => 2]);
        $sell->update(['grand_total' => 2000]);
        $inventory->update(['quantity' => 51]);

        $retur = SellRetur::create([
            'sell_id' => $sell->id,
            'warehouse_id' => $user->warehouse_id,
            'user_id' => $user->id,
            'retur_date' => now()->toDateString(),
        ]);

        SellReturDetail::create([
            'sell_retur_id' => $retur->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'qty' => 1,
            'price' => 1000,
        ]);

        ProductReport::create([
            'product_id' => $product->id,
            'warehouse_id' => $user->warehouse_id,
            'user_id' => $user->id,
            'customer_id' => $sell->customer_id,
            'unit' => $unit->name,
            'unit_type' => 'ECERAN',
            'qty' => 1,
            'price' => 1000,
            'for' => 'MASUK',
            'type' => 'RETUR PENJUALAN',
            'description' => 'Retur Penjualan '.$sell->order_number,
        ]);

        $this->actingAs($user)
            ->delete(route('penjualan-retur.destroy', $retur->id))
            ->assertRedirect(route('penjualan-retur.index'));

        $this->assertDatabaseMissing('sell_returs', ['id' => $retur->id]);
        $this->assertEquals(50.0, round((float) $inventory->fresh()->quantity, 2));

        $verified = SellRetur::create([
            'sell_id' => $sell->id,
            'warehouse_id' => $user->warehouse_id,
            'user_id' => $user->id,
            'retur_date' => now()->toDateString(),
        ]);
        $verified->forceFill(['remark' => 'verify'])->save();

        $this->actingAs($user)
            ->from(route('penjualan-retur.index'))
            ->delete(route('penjualan-retur.destroy', $verified->id))
            ->assertRedirect(route('penjualan-retur.index'))
            ->assertSessionHas('error', 'Retur yang sudah diverifikasi tidak dapat dihapus');

        $this->assertDatabaseHas('sell_returs', ['id' => $verified->id]);
    }

    public function test_retur_data_endpoints_return_json(): void
    {
        [$user, $product, $unit, $sell] = $this->createReturContext();

        $retur = SellRetur::create([
            'sell_id' => $sell->id,
            'warehouse_id' => $user->warehouse_id,
            'user_id' => $user->id,
            'retur_date' => now()->toDateString(),
        ]);

        SellReturDetail::create([
            'sell_retur_id' => $retur->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'qty' => 1,
            'price' => 1000,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.retur'))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('api.retur.byorder', $sell->id))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('api.retur-penjualan-detail', $retur->id))
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($user)
            ->getJson(route('api.penjualan-retur'))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: Product, 2: Unit, 3: Sell, 4: Inventory}
     */
    private function createReturContext(
        float|int $soldQty = 5,
        float|int $inventoryQty = 100,
        string $status = 'lunas'
    ): array {
        Role::findOrCreate('kasir');
        Role::findOrCreate('master');

        $warehouse = Warehouse::create([
            'name' => 'Warehouse Retur',
            'address' => 'Test Address',
            'phone' => '08123456789',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);
        $user->assignRole('kasir');

        $customer = Customer::create([
            'name' => 'Customer Retur',
            'description' => 'Test',
        ]);

        $unitDus = Unit::create(['name' => 'Dus']);
        $unitPak = Unit::create(['name' => 'Pak']);
        $unit = Unit::create(['name' => 'Pcs']);

        $product = Product::create([
            'group' => 'Test',
            'name' => 'Produk Retur',
            'unit_dus' => $unitDus->id,
            'unit_pak' => $unitPak->id,
            'unit_eceran' => $unit->id,
            'dus_to_eceran' => 10,
            'pak_to_eceran' => 5,
            'price_sell_dus' => 10000,
            'price_sell_pak' => 5000,
            'price_sell_eceran' => 1000,
            'isShow' => true,
        ]);

        $inventory = Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $inventoryQty,
        ]);

        $sell = Sell::create([
            'cashier_id' => $user->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_number' => 'PJ-RETUR-001',
            'subtotal' => $soldQty * 1000,
            'grand_total' => $soldQty * 1000,
            'pay' => $status === 'lunas' ? $soldQty * 1000 : 0,
            'change' => 0,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => $status,
            'cash' => $status === 'lunas' ? $soldQty * 1000 : 0,
            'transfer' => 0,
        ]);

        SellDetail::create([
            'sell_id' => $sell->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => $soldQty,
            'price' => 1000,
            'diskon' => 0,
        ]);

        return [$user, $product, $unit, $sell, $inventory];
    }
}
