<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sell;
use App\Models\SellDetail;
use App\Models\SellReturCart;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class SellReturCartService
{
    public function __construct(
        protected ProductUnitConverter $unitConverter
    ) {}

    /**
     * Get the original sale price for a product in a specific unit
     * Considers warehouse out-of-town pricing when calculating prices for different units
     */
    public function getOriginalSalePrice(int|string $sellId, int|string $productId, int|string $unitId): float|int
    {
        // Find the sell detail that matches the product and unit
        $sellDetail = SellDetail::where('sell_id', $sellId)
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($sellDetail) {
            // Calculate the net total price (total price - total discount)
            $totalPrice = ($sellDetail->price * $sellDetail->quantity) - $sellDetail->diskon;

            // Return the net price per unit
            if ($sellDetail->quantity > 0) {
                return $totalPrice / $sellDetail->quantity;
            }

            return 0;
        }

        // If no exact match, calculate price based on unit conversion from available sell details
        $product = Product::find($productId);

        // Get warehouse information from the original sale to check if it's out of town
        $sell = Sell::with('warehouse')->find($sellId);
        $isOutOfTown = $sell && $sell->warehouse ? $sell->warehouse->isOutOfTown : false;

        // Get all sell details for this product to calculate the average price per eceran
        $sellDetails = SellDetail::where('sell_id', $sellId)
            ->where('product_id', $productId)
            ->get();

        if ($sellDetails->isNotEmpty()) {
            $totalPriceInEceran = 0;
            $totalQuantityInEceran = 0;

            foreach ($sellDetails as $sellDetail) {
                if ($sellDetail->quantity > 0) {
                    // Calculate total price for this detail (total price - total discount)
                    $totalPriceForThisDetail = ($sellDetail->price * $sellDetail->quantity) - $sellDetail->diskon;

                    // Convert quantity to eceran
                    $quantityInEceran = $this->unitConverter->convertToEceran($sellDetail->quantity, $sellDetail->unit_id, $product);

                    $totalPriceInEceran += $totalPriceForThisDetail;
                    $totalQuantityInEceran += $quantityInEceran;
                }
            }

            if ($totalQuantityInEceran > 0) {
                // Calculate price per eceran
                $pricePerEceran = $totalPriceInEceran / $totalQuantityInEceran;

                // Convert from eceran price to the requested unit price
                return $this->unitConverter->convertPriceFromEceran($pricePerEceran, $unitId, $product);
            }
        }

        // Fallback: Use current product pricing based on warehouse type
        if ($isOutOfTown) {
            // Use out-of-town pricing
            if ($unitId == $product->unit_dus && $product->price_sell_dus_out_of_town > 0) {
                return $product->price_sell_dus_out_of_town;
            } elseif ($unitId == $product->unit_pak && $product->price_sell_pak_out_of_town > 0) {
                return $product->price_sell_pak_out_of_town;
            } elseif ($unitId == $product->unit_eceran && $product->price_sell_eceran_out_of_town > 0) {
                return $product->price_sell_eceran_out_of_town;
            }
        } else {
            // Use regular pricing
            if ($unitId == $product->unit_dus && $product->price_sell_dus > 0) {
                return $product->price_sell_dus;
            } elseif ($unitId == $product->unit_pak && $product->price_sell_pak > 0) {
                return $product->price_sell_pak;
            } elseif ($unitId == $product->unit_eceran && $product->price_sell_eceran > 0) {
                return $product->price_sell_eceran;
            }
        }

        return 0;
    }

    /**
     * Get total remaining quantity in eceran for a product in a specific sell
     */
    public function getTotalRemainingQuantityInEceran(int|string $sellId, int|string $productId): float|int
    {
        $sellDetails = SellDetail::where('sell_id', $sellId)
            ->where('product_id', $productId)
            ->with('product')
            ->get();

        $totalRemainingEceran = 0;

        foreach ($sellDetails as $sellDetail) {
            $quantityInEceran = $this->unitConverter->convertToEceran(
                $sellDetail->quantity,
                $sellDetail->unit_id,
                $sellDetail->product
            );
            $totalRemainingEceran += $quantityInEceran;
        }

        return $totalRemainingEceran;
    }

    /**
     * Get total returned quantity in eceran for a product from cart
     */
    public function getTotalReturnedQuantityInEceran(int|string $userId, int|string $sellId, int|string $productId): float|int
    {
        $cartItems = SellReturCart::where('user_id', $userId)
            ->where('sell_id', $sellId)
            ->where('product_id', $productId)
            ->with('product')
            ->get();

        $totalReturnedEceran = 0;

        foreach ($cartItems as $cartItem) {
            $quantityInEceran = $this->unitConverter->convertToEceran(
                $cartItem->quantity,
                $cartItem->unit_id,
                $cartItem->product
            );
            $totalReturnedEceran += $quantityInEceran;
        }

        return $totalReturnedEceran;
    }

    /**
     * Get available quantities for return in all units.
     *
     * @return array{ok: true, data: array}|array{ok: false, error: string, status: int}
     */
    public function getAvailableReturnQuantities(int|string $sellId, int|string $productId): array
    {
        $userId = auth()->id();
        $product = Product::find($productId);

        if (! $product) {
            return ['ok' => false, 'error' => 'Product not found', 'status' => 404];
        }

        // Get total remaining quantity in eceran
        $totalRemainingEceran = $this->getTotalRemainingQuantityInEceran($sellId, $productId);

        // Get total already returned quantity in eceran from cart
        $totalReturnedEceran = $this->getTotalReturnedQuantityInEceran($userId, $sellId, $productId);

        // Calculate available quantity for return in eceran
        $availableForReturnEceran = $totalRemainingEceran - $totalReturnedEceran;

        // Convert to different units
        $availableUnits = [
            'eceran' => [
                'unit_id' => $product->unit_eceran,
                'quantity' => $availableForReturnEceran,
                'unit_name' => $product->unit_eceran ? Unit::find($product->unit_eceran)->name : null,
            ],
        ];

        if ($product->pak_to_eceran > 0) {
            $availableUnits['pak'] = [
                'unit_id' => $product->unit_pak,
                'quantity' => $this->unitConverter->convertFromEceran($availableForReturnEceran, $product->unit_pak, $product),
                'unit_name' => $product->unit_pak ? Unit::find($product->unit_pak)->name : null,
            ];
        }

        if ($product->dus_to_eceran > 0) {
            $availableUnits['dus'] = [
                'unit_id' => $product->unit_dus,
                'quantity' => $this->unitConverter->convertFromEceran($availableForReturnEceran, $product->unit_dus, $product),
                'unit_name' => $product->unit_dus ? Unit::find($product->unit_dus)->name : null,
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'product_id' => $productId,
                'total_remaining_eceran' => $totalRemainingEceran,
                'total_returned_eceran' => $totalReturnedEceran,
                'available_for_return_eceran' => $availableForReturnEceran,
                'available_units' => $availableUnits,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputRequests
     * @return array{ok: true, redirect: true}|array{ok: false, errors: array, status: int}
     */
    public function addCart(array $inputRequests): array
    {
        $userId = auth()->id();

        // Get the sell record to check status for piutang validation
        $sellId = $inputRequests[0]['sell_id'] ?? null;
        if ($sellId) {
            $sell = Sell::find($sellId);
            if (! $sell) {
                return [
                    'ok' => false,
                    'errors' => ['Data penjualan tidak ditemukan'],
                    'status' => 422,
                ];
            }

            // Calculate current cart total for piutang validation
            $currentCartTotal = SellReturCart::where('user_id', $userId)
                ->where('sell_id', $sellId)
                ->sum(DB::raw('quantity * price'));

            // Calculate new items total
            $newItemsTotal = 0;
            foreach ($inputRequests as $inputRequest) {
                if (isset($inputRequest['quantity']) && $inputRequest['quantity']) {
                    $correctPrice = $this->getOriginalSalePrice($sellId, $inputRequest['product_id'], $inputRequest['unit_id']);
                    $newItemsTotal += $inputRequest['quantity'] * $correctPrice;
                }
            }

            $totalReturnPrice = $currentCartTotal + $newItemsTotal;

            // Validation: Check if total return exceeds remaining debt for piutang status
            if ($sell->status === 'piutang') {
                $remainingDebt = $sell->grand_total - $sell->pay;

                if ($totalReturnPrice > $remainingDebt) {
                    return [
                        'ok' => false,
                        'errors' => [
                            'Total retur ('.number_format($totalReturnPrice, 0, ',', '.').
                                ') tidak boleh melebihi sisa piutang ('.number_format($remainingDebt, 0, ',', '.').')',
                        ],
                        'status' => 422,
                    ];
                }
            }
        }

        foreach ($inputRequests as $inputRequest) {
            $productId = $inputRequest['product_id'];
            $unitId = $inputRequest['unit_id'];
            $sellId = $inputRequest['sell_id'];

            if (isset($inputRequest['quantity']) && $inputRequest['quantity']) {
                $quantityRetur = $inputRequest['quantity'];

                // Get product for unit conversion
                $product = Product::find($productId);
                if (! $product) {
                    return [
                        'ok' => false,
                        'errors' => ['Product not found'],
                        'status' => 422,
                    ];
                }

                // Convert the return quantity to eceran for validation
                $returnQuantityInEceran = $this->unitConverter->convertToEceran($quantityRetur, $unitId, $product);

                // Get total remaining quantity in eceran from all sell details
                $totalRemainingEceran = $this->getTotalRemainingQuantityInEceran($sellId, $productId);

                // Get total already returned quantity in eceran from cart
                $totalReturnedEceran = $this->getTotalReturnedQuantityInEceran($userId, $sellId, $productId);

                // Calculate available quantity for return
                $availableForReturnEceran = $totalRemainingEceran - $totalReturnedEceran;

                // Validate that return quantity doesn't exceed available quantity
                if ($returnQuantityInEceran > $availableForReturnEceran) {
                    $availableInRequestedUnit = $this->unitConverter->convertFromEceran($availableForReturnEceran, $unitId, $product);

                    return [
                        'ok' => false,
                        'errors' => [
                            'Jumlah retur ('.$quantityRetur.') melebihi jumlah yang tersedia untuk dikembalikan ('.
                                number_format($availableInRequestedUnit, 2).' dalam unit yang diminta)',
                        ],
                        'status' => 422,
                    ];
                }

                $quantityRetur = round((float) $quantityRetur, 2);

                // Get the correct price from the original sale details
                $correctPrice = $this->getOriginalSalePrice($sellId, $productId, $unitId);

                // Check if cart item with same unit already exists
                $existingCart = SellReturCart::where('user_id', $userId)
                    ->where('sell_id', $sellId)
                    ->where('product_id', $productId)
                    ->where('unit_id', $unitId)
                    ->first();

                if ($existingCart) {
                    $existingCart->quantity = round((float) $existingCart->quantity + $quantityRetur, 2);
                    $existingCart->save();
                } else {
                    SellReturCart::create([
                        'sell_id' => $sellId,
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'unit_id' => $unitId,
                        'quantity' => $quantityRetur,
                        'price' => $correctPrice,
                    ]);
                }
            }
        }

        return ['ok' => true, 'redirect' => true];
    }

    public function destroyCart(int|string $id): void
    {
        $returCart = SellReturCart::find($id);
        $returCart->delete();
    }

    /**
     * @return array{ok: true, data: array}|array{ok: false, message: string, status: int}
     */
    public function updateCartQuantity(int|string $id, float|int|string $quantity): array
    {
        $returCart = SellReturCart::findOrFail($id);
        $product = Product::find($returCart->product_id);
        $newQuantity = round((float) $quantity, 2);

        // Convert new quantity to eceran for validation
        $newQuantityInEceran = $this->unitConverter->convertToEceran($newQuantity, $returCart->unit_id, $product);

        // Calculate available return quantities
        $totalRemainingEceran = $this->getTotalRemainingQuantityInEceran($returCart->sell_id, $returCart->product_id);
        $totalReturnedEceran = $this->getTotalReturnedQuantityInEceran(auth()->id(), $returCart->sell_id, $returCart->product_id);

        // Add back the old quantity before checking (since it's currently in cart)
        $oldQuantityInEceran = $this->unitConverter->convertToEceran($returCart->quantity, $returCart->unit_id, $product);
        $availableForReturnEceran = $totalRemainingEceran - $totalReturnedEceran + $oldQuantityInEceran;

        // Validate new quantity doesn't exceed available
        if ($newQuantityInEceran > $availableForReturnEceran) {
            $availableInRequestedUnit = $this->unitConverter->convertFromEceran($availableForReturnEceran, $returCart->unit_id, $product);

            return [
                'ok' => false,
                'message' => 'Jumlah melebihi yang tersedia untuk dikembalikan ('.number_format($availableInRequestedUnit, 2).')',
                'status' => 400,
            ];
        }

        // Update cart quantity
        $returCart->quantity = $newQuantity;
        $returCart->save();

        return [
            'ok' => true,
            'data' => [
                'quantity' => round((float) $returCart->quantity, 2),
                'subtotal' => round($returCart->price * $returCart->quantity, 2),
            ],
        ];
    }
}
