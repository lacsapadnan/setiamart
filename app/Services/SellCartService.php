<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\SellCart;
use Illuminate\Support\Facades\DB;

class SellCartService
{
    public function __construct(
        protected ProductUnitConverter $unitConverter
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $requests
     * @return array{ok: true}|array{ok: false, errors?: array, error?: string, status: int}
     */
    public function addCart(array $requests): array
    {
        try {
            DB::beginTransaction();

            foreach ($requests as $inputRequest) {
                $productId = $inputRequest['product_id'];
                $product = Product::find($productId);

                if (! $product) {
                    DB::rollBack();

                    return ['ok' => false, 'errors' => ['Produk tidak ditemukan.'], 'status' => 422];
                }

                // Process quantity_dus if it exists
                if (isset($inputRequest['quantity_dus']) && $inputRequest['quantity_dus']) {
                    $validationError = $this->validateSellPriceInput($inputRequest['price_dus'] ?? null, $product->name, 'dus');
                    if ($validationError !== null) {
                        DB::rollBack();

                        return ['ok' => false, 'errors' => [$validationError], 'status' => 422];
                    }
                    $this->processCartItem($productId, $inputRequest['quantity_dus'], $inputRequest['unit_dus'], $inputRequest['price_dus'], $inputRequest['diskon_dus'] ?? 0);
                    $this->decreaseInventory($productId, $inputRequest['quantity_dus'], $inputRequest['unit_dus']);
                }

                // Process quantity_pak if it exists
                if (isset($inputRequest['quantity_pak']) && $inputRequest['quantity_pak']) {
                    $validationError = $this->validateSellPriceInput($inputRequest['price_pak'] ?? null, $product->name, 'pak');
                    if ($validationError !== null) {
                        DB::rollBack();

                        return ['ok' => false, 'errors' => [$validationError], 'status' => 422];
                    }
                    $this->processCartItem($productId, $inputRequest['quantity_pak'], $inputRequest['unit_pak'], $inputRequest['price_pak'], $inputRequest['diskon_pak'] ?? 0);
                    $this->decreaseInventory($productId, $inputRequest['quantity_pak'], $inputRequest['unit_pak']);
                }

                // Process quantity_eceran if it exists
                if (isset($inputRequest['quantity_eceran']) && $inputRequest['quantity_eceran']) {
                    $validationError = $this->validateSellPriceInput($inputRequest['price_eceran'] ?? null, $product->name, 'eceran');
                    if ($validationError !== null) {
                        DB::rollBack();

                        return ['ok' => false, 'errors' => [$validationError], 'status' => 422];
                    }
                    $this->processCartItem($productId, $inputRequest['quantity_eceran'], $inputRequest['unit_eceran'], $inputRequest['price_eceran'], $inputRequest['diskon_eceran'] ?? 0);
                    $this->decreaseInventory($productId, $inputRequest['quantity_eceran'], $inputRequest['unit_eceran']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['ok' => false, 'error' => 'Failed to add items to cart.', 'status' => 500];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{ok: false, message: string, status: int}
     */
    public function destroyCart(int|string $id, mixed $productId): array
    {
        $sellCart = SellCart::where('product_id', $productId)
            ->where('id', $id)
            ->first();

        // Check if cart item exists
        if (! $sellCart) {
            return [
                'ok' => false,
                'message' => 'Item keranjang tidak ditemukan',
                'status' => 404,
            ];
        }

        // Store values before deletion
        $unitId = $sellCart->unit_id;
        $cartProductId = $sellCart->product_id;
        $quantity = $sellCart->quantity;

        // Delete the cart item
        $sellCart->delete();

        // Restore inventory
        $this->increaseInventory($cartProductId, $quantity, $unitId);

        return ['ok' => true];
    }

    /**
     * @return array{ok: true, data: array}
     */
    public function updateCartQuantity(int|string $id, float|int|string $quantity): array
    {
        $sellCart = SellCart::findOrFail($id);
        $product = Product::find($sellCart->product_id);
        $inventory = Inventory::where('product_id', $sellCart->product_id)
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->first();

        // Calculate inventory adjustment
        $oldQuantity = $sellCart->quantity;
        $newQuantity = round((float) $quantity, 2);

        // Restore old quantity to inventory, then deduct new quantity
        if ($inventory && $product) {
            $inventory->quantity += $this->unitConverter->convertToEceran($oldQuantity, $sellCart->unit_id, $product);
            $inventory->quantity -= $this->unitConverter->convertToEceran($newQuantity, $sellCart->unit_id, $product);
            $inventory->save();
        }

        // Update cart quantity
        $sellCart->quantity = $newQuantity;
        $sellCart->save();

        return [
            'ok' => true,
            'data' => [
                'quantity' => round((float) $sellCart->quantity, 2),
                'subtotal' => round($sellCart->price * $sellCart->quantity - $sellCart->diskon, 2),
            ],
        ];
    }

    private function validateSellPriceInput(mixed $priceInput, string $productName, string $unitName): ?string
    {
        $normalizedPrice = $this->normalizeSellPriceInput($priceInput);
        if ($normalizedPrice === null || $normalizedPrice <= 0) {
            return "Harga jual produk {$productName} ({$unitName}) harus lebih dari 0.";
        }

        return null;
    }

    private function normalizeSellPriceInput(mixed $priceInput): ?float
    {
        if ($priceInput === null || $priceInput === '') {
            return null;
        }

        $normalizedPrice = str_replace(',', '', (string) $priceInput);
        if (! is_numeric($normalizedPrice)) {
            return null;
        }

        return (float) $normalizedPrice;
    }

    private function processCartItem(
        int|string $productId,
        float|int|string $quantity,
        int|string $unitId,
        mixed $price,
        mixed $discount
    ): void {
        // Cast to numeric types to support decimal quantity input
        $quantity = round((float) $quantity, 2);
        $price = (float) str_replace(',', '', $price); // Remove commas if present
        $discount = (float) str_replace(',', '', $discount);

        // Calculate the total price based on the unit price and quantity
        $totalPrice = $price * $quantity;

        // Apply the discount to the total price if the discount is provided
        if ($discount > 0) {
            $totalPrice -= $discount;
        }

        $existingCart = SellCart::where('cashier_id', auth()->id())
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($existingCart) {
            $existingCart->quantity = round((float) $existingCart->quantity + $quantity, 2);
            $existingCart->save();
        } else {
            SellCart::create([
                'cashier_id' => auth()->id(),
                'product_id' => $productId,
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'price' => $price,
                'diskon' => $discount,
            ]);
        }
    }

    private function decreaseInventory(int|string $productId, float|int|string $quantity, int|string $unitId): void
    {
        // Cast to float to support decimal quantity input
        $quantity = round((float) $quantity, 2);

        $product = Product::find($productId);
        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->first();

        // Check if product and inventory exist
        if (! $product || ! $inventory) {
            return;
        }

        $inventory->quantity -= $this->unitConverter->convertToEceran($quantity, $unitId, $product);
        $inventory->save();
    }

    private function increaseInventory(int|string $productId, float|int|string $quantity, int|string $unitId): void
    {
        $quantity = round((float) $quantity, 2);

        $product = Product::find($productId);
        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->first();

        if (! $inventory || ! $product) {
            return;
        }

        $inventory->quantity += $this->unitConverter->convertToEceran($quantity, $unitId, $product);
        $inventory->save();
    }
}
