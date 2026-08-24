<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseEditMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_renders_only_selected_product_options(): void
    {
        [$user, $purchase, $selectedProduct, $otherProduct] = $this->createPurchaseWithProducts();

        $response = $this->actingAs($user)->get(route('pembelian.edit', $purchase->id));

        $response->assertOk();
        $response->assertSee($selectedProduct->name, false);
        $response->assertDontSee($otherProduct->name, false);
        $response->assertSee(route('api.produk-select2'), false);
        $this->assertSame(1, substr_count($response->getContent(), 'name="product_id[]"'));
    }

    public function test_select2_search_returns_matching_visible_products(): void
    {
        [$user, , $selectedProduct, $otherProduct] = $this->createPurchaseWithProducts();

        $hiddenProduct = Product::create([
            'group' => 'Test',
            'name' => 'Gula Pasir Hidden',
            'unit_dus' => $selectedProduct->unit_dus,
            'unit_pak' => $selectedProduct->unit_pak,
            'unit_eceran' => $selectedProduct->unit_eceran,
            'dus_to_eceran' => 10,
            'pak_to_eceran' => 5,
            'price_sell_dus' => 10000,
            'price_sell_pak' => 5000,
            'price_sell_eceran' => 1000,
            'isShow' => false,
        ]);

        $response = $this->actingAs($user)->getJson(route('api.produk-select2', [
            'q' => 'Gula',
        ]));

        $response->assertOk()
            ->assertJsonPath('pagination.more', false);

        $ids = collect($response->json('results'))->pluck('id');

        $this->assertTrue($ids->contains($selectedProduct->id));
        $this->assertFalse($ids->contains($hiddenProduct->id));
        $this->assertFalse($ids->contains($otherProduct->id));
    }

    public function test_select2_search_limits_results_to_thirty(): void
    {
        [$user] = $this->createPurchaseWithProducts();

        $unitId = Unit::query()->value('id');

        for ($i = 1; $i <= 35; $i++) {
            Product::create([
                'group' => 'Test',
                'name' => sprintf('Batch Product %02d', $i),
                'unit_dus' => $unitId,
                'unit_pak' => $unitId,
                'unit_eceran' => $unitId,
                'dus_to_eceran' => 10,
                'pak_to_eceran' => 5,
                'price_sell_dus' => 10000,
                'price_sell_pak' => 5000,
                'price_sell_eceran' => 1000,
                'isShow' => true,
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('api.produk-select2', [
            'q' => 'Batch Product',
        ]));

        $response->assertOk()
            ->assertJsonPath('pagination.more', true);

        $this->assertCount(30, $response->json('results'));
    }

    /**
     * @return array{0: User, 1: Purchase, 2: Product, 3: Product}
     */
    private function createPurchaseWithProducts(): array
    {
        $warehouse = Warehouse::create([
            'name' => 'Gudang Tes',
            'address' => 'Jl. Test',
            'phone' => '08123456789',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        $supplier = Supplier::create([
            'name' => 'Supplier Tes',
            'address' => 'Jl. Supplier',
            'phone' => '08111111111',
        ]);

        $unit = Unit::create([
            'name' => 'Dus',
        ]);

        $selectedProduct = Product::create([
            'group' => 'Test',
            'name' => 'Gula Pasir Super',
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

        $otherProduct = Product::create([
            'group' => 'Test',
            'name' => 'Minyak Goreng Extra',
            'unit_dus' => $unit->id,
            'unit_pak' => $unit->id,
            'unit_eceran' => $unit->id,
            'dus_to_eceran' => 10,
            'pak_to_eceran' => 5,
            'price_sell_dus' => 20000,
            'price_sell_pak' => 8000,
            'price_sell_eceran' => 2000,
            'isShow' => true,
        ]);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'invoice' => 'INV-TEST-001',
            'order_number' => 'PL-TEST-001',
            'subtotal' => '10000',
            'grand_total' => '10000',
            'pay' => '10000',
            'reciept_date' => now()->toDateString(),
            'tax' => '0',
            'status' => 'lunas',
        ]);

        PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $selectedProduct->id,
            'unit_id' => $unit->id,
            'quantity' => '1',
            'discount_fix' => '0',
            'discount_percent' => '0',
            'price_unit' => '10000',
            'total_price' => 10000,
        ]);

        return [$user, $purchase, $selectedProduct, $otherProduct];
    }
}
