<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\SellCart;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SellPiutangAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // validateMasterPassword endpoint tests
    // ---------------------------------------------------------------

    public function test_validate_master_password_succeeds_and_stores_session_for_master_user(): void
    {
        [$cashier, $master, $warehouse] = $this->createUsersAndWarehouse();

        $response = $this->actingAs($cashier)
            ->postJson(route('validate-master-password'), [
                'user_id' => $master->id,
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $response->assertSessionHas('piutang_authorization');
        $response->assertSessionHas('piutang_authorized_by', $master->id);
    }

    public function test_validate_master_password_fails_for_non_master_user(): void
    {
        [$cashier, $master, $warehouse] = $this->createUsersAndWarehouse();

        $anotherKasir = User::factory()->create([
            'warehouse_id' => $warehouse->id,
            'password' => bcrypt('password'),
        ]);
        $anotherKasir->assignRole('kasir');

        $response = $this->actingAs($cashier)
            ->postJson(route('validate-master-password'), [
                'user_id' => $anotherKasir->id,
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'failed');

        $response->assertSessionMissing('piutang_authorization');
    }

    public function test_validate_master_password_fails_with_wrong_password(): void
    {
        [$cashier, $master, $warehouse] = $this->createUsersAndWarehouse();

        $response = $this->actingAs($cashier)
            ->postJson(route('validate-master-password'), [
                'user_id' => $master->id,
                'password' => 'wrong-password',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'failed');

        $response->assertSessionMissing('piutang_authorization');
    }

    // ---------------------------------------------------------------
    // SellController@store piutang guard tests
    // ---------------------------------------------------------------

    public function test_store_rejects_piutang_without_master_authorization(): void
    {
        [$cashier, $master, $warehouse] = $this->createUsersAndWarehouse();
        $this->seedCart($cashier, $warehouse, 10000);

        $response = $this->actingAs($cashier)->post(route('penjualan.store'), [
            'order_number' => 'PJ-TEST-001',
            'customer' => Customer::create(['name' => 'Pelanggan Tes'])->id,
            'subtotal' => '10000',
            'grand_total' => '10000',
            'cash' => '5000',
            'transfer' => '0',
            'pay' => '5000',
            'change' => '0',
            'transaction_date' => now()->format('d/m/Y'),
            'payment_method' => 'cash',
            'status' => 'piutang',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $errors = session('errors');
        $this->assertStringContainsString('otorisasi', $errors->first());

        $this->assertDatabaseMissing('sells', ['order_number' => 'PJ-TEST-001']);
    }

    public function test_store_rejects_piutang_with_expired_authorization(): void
    {
        [$cashier, $master, $warehouse] = $this->createUsersAndWarehouse();
        $this->seedCart($cashier, $warehouse, 10000);

        $expiredTimestamp = now()->subMinutes(10)->timestamp;

        $response = $this->actingAs($cashier)
            ->withSession([
                'piutang_authorization' => $expiredTimestamp,
                'piutang_authorized_by' => $master->id,
            ])
            ->post(route('penjualan.store'), [
                'order_number' => 'PJ-TEST-002',
                'customer' => Customer::create(['name' => 'Pelanggan Tes 2'])->id,
                'subtotal' => '10000',
                'grand_total' => '10000',
                'cash' => '5000',
                'transfer' => '0',
                'pay' => '5000',
                'change' => '0',
                'transaction_date' => now()->format('d/m/Y'),
                'payment_method' => 'cash',
                'status' => 'piutang',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $this->assertDatabaseMissing('sells', ['order_number' => 'PJ-TEST-002']);
    }

    public function test_store_accepts_piutang_with_valid_master_authorization(): void
    {
        [$cashier, $master, $warehouse] = $this->createUsersAndWarehouse();
        $customer = Customer::create(['name' => 'Pelanggan Tes 3']);
        $this->seedCart($cashier, $warehouse, 10000);

        $response = $this->actingAs($cashier)
            ->withSession([
                'piutang_authorization' => now()->timestamp,
                'piutang_authorized_by' => $master->id,
            ])
            ->post(route('penjualan.store'), [
                'order_number' => 'PJ-TEST-003',
                'customer' => $customer->id,
                'subtotal' => '10000',
                'grand_total' => '10000',
                'cash' => '5000',
                'transfer' => '0',
                'pay' => '5000',
                'change' => '0',
                'transaction_date' => now()->format('d/m/Y'),
                'payment_method' => 'cash',
                'status' => 'piutang',
            ]);

        $this->assertDatabaseHas('sells', [
            'order_number' => 'PJ-TEST-003',
            'status' => 'piutang',
        ]);

        // Session should be cleared after use
        $response->assertSessionMissing('piutang_authorization');
    }

    public function test_store_accepts_lunas_without_master_authorization(): void
    {
        [$cashier, $master, $warehouse] = $this->createUsersAndWarehouse();
        $customer = Customer::create(['name' => 'Pelanggan Tes 4']);
        $this->seedCart($cashier, $warehouse, 10000);

        $response = $this->actingAs($cashier)->post(route('penjualan.store'), [
            'order_number' => 'PJ-TEST-004',
            'customer' => $customer->id,
            'subtotal' => '10000',
            'grand_total' => '10000',
            'cash' => '10000',
            'transfer' => '0',
            'pay' => '10000',
            'change' => '0',
            'transaction_date' => now()->format('d/m/Y'),
            'payment_method' => 'cash',
            'status' => 'lunas',
        ]);

        $this->assertDatabaseHas('sells', [
            'order_number' => 'PJ-TEST-004',
            'status' => 'lunas',
        ]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return array{0: User, 1: User, 2: Warehouse}
     */
    private function createUsersAndWarehouse(): array
    {
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

        $warehouse = Warehouse::create([
            'name' => 'Warehouse Tes',
            'address' => 'Jl. Test',
            'phone' => '08123456789',
        ]);

        /** @var User $cashier */
        $cashier = User::factory()->create([
            'warehouse_id' => $warehouse->id,
            'password' => bcrypt('password'),
        ]);
        $cashier->assignRole('kasir');

        /** @var User $master */
        $master = User::factory()->create([
            'warehouse_id' => $warehouse->id,
            'password' => bcrypt('password'),
        ]);
        $master->assignRole('master');

        return [$cashier, $master, $warehouse];
    }

    private function seedCart(User $cashier, Warehouse $warehouse, int $grandTotal): void
    {
        $unit = Unit::create(['name' => 'Pcs']);

        $product = Product::create([
            'group' => 'Test',
            'name' => 'Produk Tes',
            'unit_dus' => $unit->id,
            'unit_pak' => $unit->id,
            'unit_eceran' => $unit->id,
            'dus_to_eceran' => 10,
            'pak_to_eceran' => 5,
            'price_sell_dus' => $grandTotal,
            'price_sell_pak' => $grandTotal,
            'price_sell_eceran' => $grandTotal,
            'isShow' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 1000,
        ]);

        SellCart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 1,
            'price' => $grandTotal,
            'diskon' => 0,
        ]);
    }
}
