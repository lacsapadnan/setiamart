<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Sell;
use App\Models\SellDetail;
use App\Models\SellRetur;
use App\Models\SellReturCart;
use App\Models\SellReturDetail;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class SellReturService
{
    public function __construct(
        protected ProductUnitConverter $unitConverter,
        protected CashflowService $cashflowService
    ) {}

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function store(int|string $sellId): array
    {
        $returCart = SellReturCart::where('user_id', auth()->id())
            ->where('sell_id', $sellId)
            ->get();

        // Get the sell record to check status and calculate remaining debt
        $sell = Sell::where('id', $sellId)->first();

        if (! $sell) {
            return ['ok' => false, 'error' => 'Data penjualan tidak ditemukan'];
        }

        // Calculate total return price
        $totalReturnPrice = 0;
        foreach ($returCart as $rc) {
            $totalReturnPrice += $rc->quantity * $rc->price;
        }

        // Validation: Check if total return exceeds remaining debt for piutang status
        if ($sell->status === 'piutang') {
            $remainingDebt = $sell->grand_total - $sell->pay;

            if ($totalReturnPrice > $remainingDebt) {
                return [
                    'ok' => false,
                    'error' => 'Total retur ('.number_format($totalReturnPrice, 0, ',', '.').
                        ') tidak boleh melebihi sisa piutang ('.number_format($remainingDebt, 0, ',', '.').')',
                ];
            }
        }

        $totalPrice = 0;

        $sellRetur = SellRetur::create([
            'sell_id' => $sellId,
            'warehouse_id' => auth()->user()->warehouse_id,
            'user_id' => auth()->id(),
            'retur_date' => now()->toDateString(),
        ]);

        foreach ($returCart as $rc) {
            SellReturDetail::create([
                'sell_retur_id' => $sellRetur->id,
                'product_id' => $rc->product_id,
                'unit_id' => $rc->unit_id,
                'qty' => $rc->quantity,
                'price' => $rc->price,
            ]);

            $sell = Sell::where('id', $sellId)
                ->with('customer')
                ->first();

            $this->applyReturnLineItem(
                $sell,
                $rc->product_id,
                $rc->unit_id,
                $rc->quantity,
                $rc->price,
                auth()->user()->warehouse_id,
                auth()->id(),
                updateInventory: false
            );

            // total price
            $totalPrice += $rc->quantity * $rc->price;
        }

        // bring back the stock
        foreach ($returCart as $rc) {
            $product = Product::where('id', $rc->product_id)->first();
            $this->addInventoryFromReturn(
                $rc->product_id,
                $rc->unit_id,
                $rc->quantity,
                $product,
                auth()->user()->warehouse_id
            );
        }

        $allReturned = true;

        // Query all SellDetail items for the sell_id
        $sellDetails = SellDetail::where('sell_id', $sellId)->get();

        // Check if all SellDetail quantities are 0
        foreach ($sellDetails as $sellDetail) {
            if ($sellDetail->quantity > 0) {
                $allReturned = false;
                break;
            }
        }

        // Create cashflow if the sell status is 'lunas'
        if ($sell->status === 'lunas') {
            $this->cashflowService->handleReturnTransaction(
                warehouseId: auth()->user()->warehouse_id,
                orderNumber: $sell->order_number,
                customerName: $sell->customer->name ?? 'Unknown',
                totalReturnAmount: $totalReturnPrice,
                sellStatus: $sell->status,
                paidAmount: $sell->pay
            );
        }

        // Update status to "batal" if all items are returned
        $sell = Sell::where('id', $sellId)->first();
        if ($allReturned) {
            $sell->status = 'batal';
            $sell->update();
        }

        // delete the cart
        SellReturCart::where('user_id', auth()->id())->delete();

        return ['ok' => true];
    }

    /**
     * @param  array<int, mixed>  $selectedIds
     * @return array{ok: true}|array{ok: false, error: string, status: int}
     */
    public function konfirmReturn(int|string $sellId, array $selectedIds): array
    {
        $totalPrice = 0;
        $sell = Sell::where('id', $sellId)
            ->with('customer')
            ->first();

        if (! $sell) {
            return ['ok' => false, 'error' => 'Data penjualan tidak ditemukan', 'status' => 404];
        }

        // Calculate total return price for validation
        $totalReturnPrice = 0;
        foreach ($selectedIds as $selectedId) {
            $sellReturDetails = SellReturDetail::where('sell_retur_id', $selectedId)->get();
            foreach ($sellReturDetails as $rc) {
                $totalReturnPrice += $rc->qty * $rc->price;
            }
        }

        // Validation: Check if total return exceeds remaining debt for piutang status
        if ($sell->status === 'piutang') {
            $remainingDebt = $sell->grand_total - $sell->pay;

            if ($totalReturnPrice > $remainingDebt) {
                return [
                    'ok' => false,
                    'error' => 'Total retur ('.number_format($totalReturnPrice, 0, ',', '.').
                        ') tidak boleh melebihi sisa piutang ('.number_format($remainingDebt, 0, ',', '.').')',
                    'status' => 422,
                ];
            }
        }

        foreach ($selectedIds as $selectedId) {
            $sellReturDetails = SellReturDetail::where('sell_retur_id', $selectedId)->get();
            foreach ($sellReturDetails as $rc) {
                $sell = Sell::where('id', $sellId)
                    ->with('customer')
                    ->first();

                $this->applyReturnLineItem(
                    $sell,
                    $rc->product_id,
                    $rc->unit_id,
                    $rc->qty,
                    $rc->price,
                    auth()->user()->warehouse_id,
                    auth()->id(),
                    updateInventory: true
                );

                // total price
                $totalPrice += $rc->qty * $rc->price;
            }
            // update remkar menjadi verify
            SellRetur::where('id', $selectedId)->update(['remark' => 'verify']);
        }

        // Create cashflow if the sell status is 'lunas'
        if ($sell->status === 'lunas') {
            $this->cashflowService->handleReturnTransaction(
                warehouseId: auth()->user()->warehouse_id,
                orderNumber: $sell->order_number,
                customerName: $sell->customer->name ?? 'Unknown',
                totalReturnAmount: $totalReturnPrice,
                sellStatus: $sell->status,
                paidAmount: $sell->pay
            );
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true, forceDelete: bool}|array{ok: false, error: string}
     */
    public function destroy(int|string $id): array
    {
        try {
            $sellRetur = SellRetur::with('sellReturDetails', 'sell.customer')->findOrFail($id);

            // Check permissions - only master or return creator can delete
            $userRoles = auth()->user()->getRoleNames();
            if ($userRoles[0] !== 'master' && $sellRetur->user_id !== auth()->id()) {
                return ['ok' => false, 'error' => 'Anda tidak memiliki izin untuk menghapus retur ini'];
            }

            // Check if return is already verified
            if ($sellRetur->remark === 'verify') {
                return ['ok' => false, 'error' => 'Retur yang sudah diverifikasi tidak dapat dihapus'];
            }

            DB::beginTransaction();

            // Get all return details
            $sellReturDetails = SellReturDetail::where('sell_retur_id', $id)->get();

            // Check if sell record exists for proper restoration
            $sellExists = $sellRetur->sell !== null;
            $forceDelete = ! $sellExists;

            if ($sellExists) {
                // Normal deletion process with sell record restoration
                foreach ($sellReturDetails as $returnDetail) {
                    $product = Product::find($returnDetail->product_id);

                    if (! $product) {
                        continue; // Skip if product not found
                    }

                    // Convert returned quantity to eceran
                    $returnQuantityInEceran = $this->unitConverter->convertToEceran($returnDetail->qty, $returnDetail->unit_id, $product);

                    // Get all sell details for this product to restore proportionally
                    $sellDetails = SellDetail::where('sell_id', $sellRetur->sell_id)
                        ->where('product_id', $returnDetail->product_id)
                        ->get();

                    $remainingToRestore = $returnQuantityInEceran;

                    foreach ($sellDetails as $sellDetail) {
                        if ($remainingToRestore <= 0) {
                            break;
                        }

                        // Calculate how much to restore to this sell detail
                        $restoreEceran = min($remainingToRestore, $returnQuantityInEceran);

                        // Convert back to the sell detail's unit
                        $restoreInOriginalUnit = $this->unitConverter->convertFromEceran($restoreEceran, $sellDetail->unit_id, $product);

                        // Update the sell detail quantity
                        $sellDetail->quantity += $restoreInOriginalUnit;
                        $sellDetail->save();

                        // Update grand total (proportional to restoration)
                        // Calculate net price per unit: (total price - total discount) / quantity
                        $netPricePerUnit = (($sellDetail->price * $sellDetail->quantity) - $sellDetail->diskon) / $sellDetail->quantity;
                        $sellRetur->sell->grand_total += ($restoreInOriginalUnit * $netPricePerUnit);

                        $remainingToRestore -= $restoreEceran;
                    }

                    // Remove stock that was added back during return
                    $inventory = Inventory::where('product_id', $returnDetail->product_id)
                        ->where('warehouse_id', $sellRetur->warehouse_id)
                        ->first();

                    if ($inventory) {
                        $inventory->quantity -= $returnQuantityInEceran;
                        $inventory->save();
                    }

                    // Delete the product report entry for this return
                    ProductReport::where('type', 'RETUR PENJUALAN')
                        ->where('product_id', $returnDetail->product_id)
                        ->where('warehouse_id', $sellRetur->warehouse_id)
                        ->where('user_id', $sellRetur->user_id)
                        ->where('qty', $returnDetail->qty)
                        ->where('price', $returnDetail->price)
                        ->first()?->delete();
                }

                // Update the sell record
                $sellRetur->sell->save();

                // If sell was marked as 'batal', check if we should restore its status
                if ($sellRetur->sell->status === 'batal') {
                    // Check if there are still items with quantity > 0
                    $hasRemainingItems = SellDetail::where('sell_id', $sellRetur->sell_id)
                        ->where('quantity', '>', 0)
                        ->exists();

                    if ($hasRemainingItems) {
                        // Restore status based on payment
                        $sellRetur->sell->status = $sellRetur->sell->grand_total > $sellRetur->sell->paid ? 'piutang' : 'lunas';
                        $sellRetur->sell->save();
                    }
                }
            } else {
                // Force deletion process when sell record doesn't exist
                foreach ($sellReturDetails as $returnDetail) {
                    $product = Product::find($returnDetail->product_id);

                    if (! $product) {
                        continue; // Skip if product not found
                    }

                    // Convert returned quantity to eceran
                    $returnQuantityInEceran = $this->unitConverter->convertToEceran($returnDetail->qty, $returnDetail->unit_id, $product);

                    // Remove stock that was added back during return (reverse the return)
                    $inventory = Inventory::where('product_id', $returnDetail->product_id)
                        ->where('warehouse_id', $sellRetur->warehouse_id)
                        ->first();

                    if ($inventory) {
                        $inventory->quantity -= $returnQuantityInEceran;
                        $inventory->save();
                    }

                    // Delete the product report entry for this return
                    ProductReport::where('type', 'RETUR PENJUALAN')
                        ->where('product_id', $returnDetail->product_id)
                        ->where('warehouse_id', $sellRetur->warehouse_id)
                        ->where('user_id', $sellRetur->user_id)
                        ->where('qty', $returnDetail->qty)
                        ->where('price', $returnDetail->price)
                        ->first()?->delete();
                }
            }

            // Delete return details and the return record
            SellReturDetail::where('sell_retur_id', $id)->delete();
            $sellRetur->delete();

            DB::commit();

            return ['ok' => true, 'forceDelete' => $forceDelete];
        } catch (\Exception $e) {
            DB::rollback();

            return ['ok' => false, 'error' => 'Terjadi kesalahan saat menghapus retur: '.$e->getMessage()];
        }
    }

    /**
     * Shared deduct sell-detail + ProductReport (+ optional inventory) used by store and konfirmReturn.
     */
    private function applyReturnLineItem(
        Sell $sell,
        int|string $productId,
        int|string $unitId,
        float|int|string $qty,
        float|int|string $price,
        mixed $warehouseId,
        int|string $userId,
        bool $updateInventory = true
    ): void {
        // Get product for unit conversion
        $product = Product::find($productId);

        // Convert return quantity to eceran for processing
        $returnQuantityInEceran = $this->unitConverter->convertToEceran($qty, $unitId, $product);

        // Get all sell details for this product to deduct proportionally
        $sellDetails = SellDetail::where('sell_id', $sell->id)
            ->where('product_id', $productId)
            ->with('product')
            ->get();

        $remainingToDeduct = $returnQuantityInEceran;

        foreach ($sellDetails as $sellDetail) {
            if ($remainingToDeduct <= 0) {
                break;
            }

            // Convert sell detail quantity to eceran
            $sellDetailEceran = $this->unitConverter->convertToEceran($sellDetail->quantity, $sellDetail->unit_id, $product);

            if ($sellDetailEceran > 0) {
                // Calculate how much to deduct from this sell detail
                $deductEceran = min($remainingToDeduct, $sellDetailEceran);

                // Convert back to the sell detail's unit
                $deductInOriginalUnit = $this->unitConverter->convertFromEceran($deductEceran, $sellDetail->unit_id, $product);

                // Update grand total (proportional to deduction)
                // Calculate net price per unit: (total price - total discount) / quantity
                $netPricePerUnit = (($sellDetail->price * $sellDetail->quantity) - $sellDetail->diskon) / $sellDetail->quantity;
                $sell->grand_total = $sell->grand_total - ($deductInOriginalUnit * $netPricePerUnit);

                // Update the sell detail quantity
                $sellDetail->quantity -= $deductInOriginalUnit;
                $sellDetail->update();

                $remainingToDeduct -= $deductEceran;
            }
        }

        $sell->update();

        $unit = Unit::find($unitId);

        if ($unitId == $product->unit_dus) {
            $unitType = 'DUS';
        } elseif ($unitId == $product->unit_pak) {
            $unitType = 'PAK';
        } elseif ($unitId == $product->unit_eceran) {
            $unitType = 'ECERAN';
        }

        ProductReport::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'customer_id' => $sell->customer_id,
            'unit' => $unit->name,
            'unit_type' => $unitType ?? null,
            'qty' => $qty,
            'price' => $price,
            'for' => 'MASUK',
            'type' => 'RETUR PENJUALAN',
            'description' => 'Retur Penjualan '.$sell->order_number,
        ]);

        if ($updateInventory) {
            $this->addInventoryFromReturn($productId, $unitId, $qty, $product, $warehouseId);
        }
    }

    private function addInventoryFromReturn(
        int|string $productId,
        int|string $unitId,
        float|int|string $qty,
        Product $product,
        mixed $warehouseId
    ): void {

        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        // Convert returned quantity to eceran and add to inventory
        $quantityInEceran = $this->unitConverter->convertToEceran($qty, $unitId, $product);
        $inventory->quantity += $quantityInEceran;
        $inventory->update();
    }
}
