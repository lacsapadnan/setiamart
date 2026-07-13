<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseCartDraft;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseDraftContinueTest extends TestCase
{
    use RefreshDatabase;

    public function test_continuing_purchase_draft_prefill_receipt_date(): void
    {
        [$user, $supplier, $product, $unit] = $this->createPurchaseContext();

        $receiptDate = Carbon::parse('2026-07-10');

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $user->warehouse_id,
            'order_number' => 'PL-TEST-DRAFT-001',
            'invoice' => 'INV-001',
            'subtotal' => 10000,
            'potongan' => 0,
            'grand_total' => 10000,
            'pay' => 0,
            'reciept_date' => $receiptDate->toDateString(),
            'status' => 'draft',
            'payment_method' => 'cash',
            'cash' => 0,
            'transfer' => 0,
        ]);

        PurchaseCartDraft::create([
            'purchase_id' => $purchase->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => 1,
            'discount_fix' => 0,
            'discount_percent' => 0,
            'price_unit' => 10000,
            'total_price' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('pembelian-draft.show', $purchase->id));

        $response->assertOk();
        $response->assertSee('value="'.$receiptDate->format('d/m/Y').'"', false);
        $response->assertSee('name="reciept_date"', false);
    }

    /**
     * @return array{0: User, 1: Supplier, 2: Product, 3: Unit}
     */
    private function createPurchaseContext(): array
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

        $supplier = Supplier::create([
            'name' => 'Supplier Test',
            'address' => 'Supplier Address',
            'phone' => '08111111111',
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

        return [$user, $supplier, $product, $unit];
    }
}
