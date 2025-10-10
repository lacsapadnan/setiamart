<?php

namespace App\Http\Controllers;

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
use App\Services\CashflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class SellReturController extends Controller
{
    protected $cashflowService;

    public function __construct(CashflowService $cashflowService)
    {
        $this->cashflowService = $cashflowService;
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $masters = User::role('master')->get();
        $warehouses = Warehouse::all();
        $users = User::all();
        return view('pages.retur.index', compact('masters', 'warehouses', 'users'));
    }

    public function data(Request $request)
    {
        $role = auth()->user()->getRoleNames();
        $user_id = $request->input('user_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $warehouse = $request->input('warehouse');

        $defaultDate = now()->format('Y-m-d');

        if (!$fromDate) {
            $fromDate = $defaultDate;
        }

        if (!$toDate) {
            $toDate = $defaultDate;
        }

        if ($role[0] == 'master') {
            $retur = SellRetur::with(['sell.customer', 'product', 'warehouse', 'unit', 'user'])->orderBy('id', 'asc');
        } else {
            $retur = SellRetur::with(['sell.customer', 'product', 'warehouse', 'unit', 'user'])
                ->where('warehouse_id', auth()->user()->warehouse_id)
                ->where('user_id', auth()->id())
                ->orderBy('id', 'asc');
        }

        if ($warehouse) {
            $retur->where('warehouse_id', $warehouse);
        }

        if ($user_id) {
            $retur->where('user_id', $user_id);
        }

        if ($fromDate && $toDate) {
            $endDate = Carbon::parse($toDate)->endOfDay();

            $retur->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        $retur = $retur->get();

        $retur->each(function ($sellRetur) {
            $sellRetur->returNumber = "PJR-" . date('Ymd') . "-" . str_pad($sellRetur->id, 4, '0', STR_PAD_LEFT);

            // Handle null relationships
            if (!$sellRetur->sell) {
                $sellRetur->sell = (object) [
                    'order_number' => null,
                    'customer' => (object) ['name' => null]
                ];
            } elseif (!$sellRetur->sell->customer) {
                $sellRetur->sell->customer = (object) ['name' => null];
            }

            if (!$sellRetur->warehouse) {
                $sellRetur->warehouse = (object) ['name' => null];
            }

            if (!$sellRetur->user) {
                $sellRetur->user = (object) ['name' => null];
            }
        });

        return response()->json($retur);
    }

    public function dataBySaleId($saleId)
    {
        $userRoles = auth()->user()->getRoleNames();

        $query = SellReturDetail::with(['product', 'unit', 'sellRetur'])
            ->whereHas('sellRetur', function ($q) use ($saleId) {
                $q->where('sell_id', $saleId);
            });

        if ($userRoles[0] != 'master') {
            $query->whereHas('sellRetur', function ($q) {
                $q->where('warehouse_id', auth()->user()->warehouse_id);
            });
        }

        $retur = $query->get();

        // Add remark from sellRetur to each detail
        $retur->each(function ($detail) {
            $detail->remark = $detail->sellRetur->remark;
            $detail->created_at = $detail->sellRetur->created_at;
            $detail->id = $detail->sellRetur->id;
        });

        return response()->json($retur);
    }


    public function  dataDetail($id)
    {
        $returDetail = SellReturDetail::with('sellRetur', 'product', 'unit')->where('sell_retur_id', $id)->get();
        return response()->json($returDetail);
    }

    public function dataSell(Request $request)
    {
        $role = auth()->user()->getRoleNames();
        $user_id = $request->input('cashier_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $warehouse = $request->input('warehouse');

        $defaultDate = now()->format('Y-m-d');

        if (!$fromDate) {
            $fromDate = $defaultDate;
        }

        if (!$toDate) {
            $toDate = $defaultDate;
        }

        if ($role[0] == 'master') {
            $sells = Sell::with('details.product.unit_dus', 'details.product.unit_pak', 'details.product.unit_eceran', 'warehouse', 'customer')
                ->orderBy('id', 'desc');
        } else {
            $sells = Sell::with('details.product.unit_dus', 'details.product.unit_pak', 'details.product.unit_eceran', 'warehouse', 'customer')
                ->where('warehouse_id', auth()->user()->warehouse_id)
                ->orderBy('id', 'desc');
        }


        if ($warehouse) {
            $sells->where('warehouse_id', $warehouse);
        }

        if ($user_id) {
            $sells->where('cashier_id', $user_id);
        }

        if ($fromDate && $toDate) {
            $endDate = Carbon::parse($toDate)->endOfDay();

            $sells->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        $sells = $sells->get();
        return response()->json($sells);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $masters = User::role('master')->get();
        $warehouses = Warehouse::all();
        $role = auth()->user()->getRoleNames();
        if ($role[0] == 'master') {
            $users = User::all();
        } else {
            $users = User::where('warehouse_id', auth()->user()->warehouse_id)->get();
        }
        return view('pages.retur.list-penjualan', compact('masters', 'warehouses', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $returCart = SellReturCart::where('user_id', auth()->id())
            ->where('sell_id', $request->sell_id)
            ->get();

        // Get the sell record to check status and calculate remaining debt
        $sell = Sell::where('id', $request->sell_id)->first();

        if (!$sell) {
            return redirect()->back()->with('error', 'Data penjualan tidak ditemukan');
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
                return redirect()->back()->with(
                    'error',
                    'Total retur (' . number_format($totalReturnPrice, 0, ',', '.') .
                        ') tidak boleh melebihi sisa piutang (' . number_format($remainingDebt, 0, ',', '.') . ')'
                );
            }
        }

        $totalPrice = 0;

        $sellRetur = SellRetur::create([
            'sell_id' => $request->sell_id,
            'warehouse_id' => auth()->user()->warehouse_id,
            'user_id' => auth()->id(),
            'retur_date' => date('Y-m-d'),
        ]);

        foreach ($returCart as $rc) {
            $detailRetur = SellReturDetail::create([
                'sell_retur_id' => $sellRetur->id,
                'product_id' => $rc->product_id,
                'unit_id' => $rc->unit_id,
                'qty' => $rc->quantity,
                'price' => $rc->price,
            ]);

            $sell = Sell::where('id', $request->sell_id)
                ->with('customer')
                ->first();

            // Get product for unit conversion
            $product = Product::find($rc->product_id);

            // Convert return quantity to eceran for processing
            $returnQuantityInEceran = $this->convertToEceran($rc->quantity, $rc->unit_id, $product);

            // Get all sell details for this product to deduct proportionally
            $sellDetails = SellDetail::where('sell_id', $request->sell_id)
                ->where('product_id', $rc->product_id)
                ->with('product')
                ->get();

            $remainingToDeduct = $returnQuantityInEceran;

            foreach ($sellDetails as $sellDetail) {
                if ($remainingToDeduct <= 0) break;

                // Convert sell detail quantity to eceran
                $sellDetailEceran = $this->convertToEceran($sellDetail->quantity, $sellDetail->unit_id, $product);

                if ($sellDetailEceran > 0) {
                    // Calculate how much to deduct from this sell detail
                    $deductEceran = min($remainingToDeduct, $sellDetailEceran);

                    // Convert back to the sell detail's unit
                    $deductInOriginalUnit = $this->convertFromEceran($deductEceran, $sellDetail->unit_id, $product);

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

            // total price
            $totalPrice += $rc->quantity * $rc->price;

            $unit = Unit::find($rc->unit_id);

            if ($rc->unit_id == $product->unit_dus) {
                $unitType = 'DUS';
            } elseif ($rc->unit_id == $product->unit_pak) {
                $unitType = 'PAK';
            } elseif ($rc->unit_id == $product->unit_eceran) {
                $unitType = 'ECERAN';
            }

            ProductReport::create([
                'product_id' => $rc->product_id,
                'warehouse_id' => auth()->user()->warehouse_id,
                'user_id' => auth()->id(),
                'customer_id' => $sell->customer_id,
                'unit' => $unit->name,
                'unit_type' => $unitType,
                'qty' => $rc->quantity,
                'price' => $rc->price,
                'for' => 'MASUK',
                'type' => 'RETUR PENJUALAN',
                'description' => 'Retur Penjualan ' . $sell->order_number,
            ]);
        }

        // bring back the stock
        foreach ($returCart as $rc) {
            $product = Product::where('id', $rc->product_id)->first();
            $inventory = Inventory::where('product_id', $rc->product_id)
                ->where('warehouse_id', auth()->user()->warehouse_id)
                ->first();

            // Convert returned quantity to eceran and add to inventory
            $quantityInEceran = $this->convertToEceran($rc->quantity, $rc->unit_id, $product);
            $inventory->quantity += $quantityInEceran;
            $inventory->update();
        }

        $allReturned = true;

        // Query all SellDetail items for the sell_id
        $sellDetails = SellDetail::where('sell_id', $request->sell_id)->get();

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
        $sell = Sell::where('id', $request->sell_id)->first();
        if ($allReturned) {
            $sell->status = 'batal';
            $sell->update();
        }

        // delete the cart
        SellReturCart::where('user_id', auth()->id())->delete();

        return redirect()->route('penjualan-retur.index')->with('success', 'Retur berhasil disimpan');
    }

    public function konfirmReturn(Request $request)
    {
        $selectedIds = $request->input('selectedIds');
        $totalPrice = 0;
        $sell = Sell::where('id', $request->sell_id)
            ->with('customer')
            ->first();

        if (!$sell) {
            return response()->json(['error' => 'Data penjualan tidak ditemukan'], 404);
        }

        // Calculate total return price for validation
        $totalReturnPrice = 0;
        foreach ($selectedIds as $selectedId) {
            $sellReturDetails = DB::table('sell_retur_details')->where('sell_retur_id', $selectedId)->get();
            foreach ($sellReturDetails as $rc) {
                $totalReturnPrice += $rc->qty * $rc->price;
            }
        }

        // Validation: Check if total return exceeds remaining debt for piutang status
        if ($sell->status === 'piutang') {
            $remainingDebt = $sell->grand_total - $sell->pay;

            if ($totalReturnPrice > $remainingDebt) {
                return response()->json([
                    'error' => 'Total retur (' . number_format($totalReturnPrice, 0, ',', '.') .
                        ') tidak boleh melebihi sisa piutang (' . number_format($remainingDebt, 0, ',', '.') . ')'
                ], 422);
            }
        }

        foreach ($selectedIds as $selectedId) {
            $sellReturDetails = DB::table('sell_retur_details')->where('sell_retur_id', $selectedId)->get();
            foreach ($sellReturDetails as $rc) {
                $sell = Sell::where('id', $request->sell_id)
                    ->with('customer')
                    ->first();

                // Get product for unit conversion
                $product = Product::find($rc->product_id);

                // Convert return quantity to eceran for processing
                $returnQuantityInEceran = $this->convertToEceran($rc->qty, $rc->unit_id, $product);

                // Get all sell details for this product to deduct proportionally
                $sellDetails = SellDetail::where('sell_id', $request->sell_id)
                    ->where('product_id', $rc->product_id)
                    ->with('product')
                    ->get();

                $remainingToDeduct = $returnQuantityInEceran;

                foreach ($sellDetails as $sellDetail) {
                    if ($remainingToDeduct <= 0) break;

                    // Convert sell detail quantity to eceran
                    $sellDetailEceran = $this->convertToEceran($sellDetail->quantity, $sellDetail->unit_id, $product);

                    if ($sellDetailEceran > 0) {
                        // Calculate how much to deduct from this sell detail
                        $deductEceran = min($remainingToDeduct, $sellDetailEceran);

                        // Convert back to the sell detail's unit
                        $deductInOriginalUnit = $this->convertFromEceran($deductEceran, $sellDetail->unit_id, $product);

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

                // total price
                $totalPrice += $rc->qty * $rc->price;

                $unit = Unit::find($rc->unit_id);

                if ($rc->unit_id == $product->unit_dus) {
                    $unitType = 'DUS';
                } elseif ($rc->unit_id == $product->unit_pak) {
                    $unitType = 'PAK';
                } elseif ($rc->unit_id == $product->unit_eceran) {
                    $unitType = 'ECERAN';
                }
                ProductReport::create([
                    'product_id' => $rc->product_id,
                    'warehouse_id' => auth()->user()->warehouse_id,
                    'user_id' => auth()->id(),
                    'customer_id' => $sell->customer_id,
                    'unit' => $unit->name,
                    'unit_type' => $unitType,
                    'qty' => $rc->qty,
                    'price' => $rc->price,
                    'for' => 'MASUK',
                    'type' => 'RETUR PENJUALAN',
                    'description' => 'Retur Penjualan ' . $sell->order_number,
                ]);

                $inventory = Inventory::where('product_id', $rc->product_id)
                    ->where('warehouse_id', auth()->user()->warehouse_id)
                    ->first();

                // Convert returned quantity to eceran and add to inventory
                $quantityInEceran = $this->convertToEceran($rc->qty, $rc->unit_id, $product);
                $inventory->quantity += $quantityInEceran;
                $inventory->update();
            }
            // update remkar menjadi verify
            DB::table('sell_returs')
                ->where('id', $selectedId)
                ->update(['remark' => 'verify']);
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

        return response()->json(['message' => 'Return confirmed successfully']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $sellId = $id;
        $penjualan = Sell::with('customer', 'warehouse', 'details.product', 'details.unit')->findOrFail($id);
        $cart = SellReturCart::with('product', 'unit')
            ->where('user_id', auth()->id())
            ->where('sell_id', $id)
            ->get();
        $subtotal = 0;
        foreach ($cart as $c) {
            $subtotal += $c->price * $c->quantity;
        }
        return view('pages.retur.create', compact('penjualan', 'cart', 'subtotal', 'sellId'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $sellRetur = SellRetur::with('sellReturDetails', 'sell.customer')->findOrFail($id);

            // Check permissions - only master or return creator can delete
            $userRoles = auth()->user()->getRoleNames();
            if ($userRoles[0] !== 'master' && $sellRetur->user_id !== auth()->id()) {
                return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk menghapus retur ini');
            }

            // Check if return is already verified
            if ($sellRetur->remark === 'verify') {
                return redirect()->back()->with('error', 'Retur yang sudah diverifikasi tidak dapat dihapus');
            }

            DB::beginTransaction();

            // Get all return details
            $sellReturDetails = SellReturDetail::where('sell_retur_id', $id)->get();

            // Check if sell record exists for proper restoration
            $sellExists = $sellRetur->sell !== null;
            $forceDelete = !$sellExists;

            if ($sellExists) {
                // Normal deletion process with sell record restoration
                foreach ($sellReturDetails as $returnDetail) {
                    $product = Product::find($returnDetail->product_id);

                    if (!$product) {
                        continue; // Skip if product not found
                    }

                    // Convert returned quantity to eceran
                    $returnQuantityInEceran = $this->convertToEceran($returnDetail->qty, $returnDetail->unit_id, $product);

                    // Get all sell details for this product to restore proportionally
                    $sellDetails = SellDetail::where('sell_id', $sellRetur->sell_id)
                        ->where('product_id', $returnDetail->product_id)
                        ->get();

                    $remainingToRestore = $returnQuantityInEceran;

                    foreach ($sellDetails as $sellDetail) {
                        if ($remainingToRestore <= 0) break;

                        // Calculate how much to restore to this sell detail
                        $restoreEceran = min($remainingToRestore, $returnQuantityInEceran);

                        // Convert back to the sell detail's unit
                        $restoreInOriginalUnit = $this->convertFromEceran($restoreEceran, $sellDetail->unit_id, $product);

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

                    if (!$product) {
                        continue; // Skip if product not found
                    }

                    // Convert returned quantity to eceran
                    $returnQuantityInEceran = $this->convertToEceran($returnDetail->qty, $returnDetail->unit_id, $product);

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

            if ($forceDelete) {
                return redirect()->route('penjualan-retur.index')->with('success', 'Retur berhasil dihapus secara paksa. Data penjualan terkait tidak ditemukan, namun stok dan laporan produk telah diperbaiki.');
            } else {
                return redirect()->route('penjualan-retur.index')->with('success', 'Retur berhasil dihapus dan stok telah dikembalikan ke kondisi semula');
            }
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Terjadi kesalahan saat menghapus retur: ' . $e->getMessage());
        }
    }

    /**
     * Convert quantity to base unit (eceran)
     */
    private function convertToEceran($quantity, $unitId, $product)
    {
        if ($unitId == $product->unit_dus) {
            return $quantity * $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            return $quantity * $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            return $quantity;
        }
        return 0;
    }

    /**
     * Convert from base unit (eceran) to specific unit
     */
    private function convertFromEceran($quantityEceran, $unitId, $product)
    {
        if ($unitId == $product->unit_dus && $product->dus_to_eceran > 0) {
            return $quantityEceran / $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak && $product->pak_to_eceran > 0) {
            return $quantityEceran / $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            return $quantityEceran;
        }
        return 0;
    }

    /**
     * Get the original sale price for a product in a specific unit
     * Considers warehouse out-of-town pricing when calculating prices for different units
     */
    private function getOriginalSalePrice($sellId, $productId, $unitId)
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
                    $quantityInEceran = $this->convertToEceran($sellDetail->quantity, $sellDetail->unit_id, $product);

                    $totalPriceInEceran += $totalPriceForThisDetail;
                    $totalQuantityInEceran += $quantityInEceran;
                }
            }

            if ($totalQuantityInEceran > 0) {
                // Calculate price per eceran
                $pricePerEceran = $totalPriceInEceran / $totalQuantityInEceran;

                // Convert from eceran price to the requested unit price
                return $this->convertPriceFromEceran($pricePerEceran, $unitId, $product);
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
     * Convert price to base unit (eceran) price
     */
    private function convertPriceToEceran($price, $unitId, $product)
    {
        if ($unitId == $product->unit_dus) {
            return $price / $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            return $price / $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            return $price;
        }
        return 0;
    }

    /**
     * Convert price from base unit (eceran) to specific unit
     */
    private function convertPriceFromEceran($eceranPrice, $unitId, $product)
    {
        if ($unitId == $product->unit_dus) {
            return $eceranPrice * $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            return $eceranPrice * $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            return $eceranPrice;
        }
        return 0;
    }

    /**
     * Get total remaining quantity in eceran for a product in a specific sell
     */
    private function getTotalRemainingQuantityInEceran($sellId, $productId)
    {
        $sellDetails = SellDetail::where('sell_id', $sellId)
            ->where('product_id', $productId)
            ->with('product')
            ->get();

        $totalRemainingEceran = 0;

        foreach ($sellDetails as $sellDetail) {
            $quantityInEceran = $this->convertToEceran(
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
    private function getTotalReturnedQuantityInEceran($userId, $sellId, $productId)
    {
        $cartItems = SellReturCart::where('user_id', $userId)
            ->where('sell_id', $sellId)
            ->where('product_id', $productId)
            ->with('product')
            ->get();

        $totalReturnedEceran = 0;

        foreach ($cartItems as $cartItem) {
            $quantityInEceran = $this->convertToEceran(
                $cartItem->quantity,
                $cartItem->unit_id,
                $cartItem->product
            );
            $totalReturnedEceran += $quantityInEceran;
        }

        return $totalReturnedEceran;
    }

    /**
     * Get available quantities for return in all units
     */
    public function getAvailableReturnQuantities($sellId, $productId)
    {
        $userId = auth()->id();
        $product = Product::find($productId);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
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
                'unit_name' => $product->unit_eceran ? Unit::find($product->unit_eceran)->name : null
            ]
        ];

        if ($product->pak_to_eceran > 0) {
            $availableUnits['pak'] = [
                'unit_id' => $product->unit_pak,
                'quantity' => $this->convertFromEceran($availableForReturnEceran, $product->unit_pak, $product),
                'unit_name' => $product->unit_pak ? Unit::find($product->unit_pak)->name : null
            ];
        }

        if ($product->dus_to_eceran > 0) {
            $availableUnits['dus'] = [
                'unit_id' => $product->unit_dus,
                'quantity' => $this->convertFromEceran($availableForReturnEceran, $product->unit_dus, $product),
                'unit_name' => $product->unit_dus ? Unit::find($product->unit_dus)->name : null
            ];
        }

        return response()->json([
            'product_id' => $productId,
            'total_remaining_eceran' => $totalRemainingEceran,
            'total_returned_eceran' => $totalReturnedEceran,
            'available_for_return_eceran' => $availableForReturnEceran,
            'available_units' => $availableUnits
        ]);
    }

    public function addCart(Request $request)
    {
        $userId = auth()->id();
        $inputRequests = $request->input_requests;

        // Get the sell record to check status for piutang validation
        $sellId = $inputRequests[0]['sell_id'] ?? null;
        if ($sellId) {
            $sell = Sell::find($sellId);
            if (!$sell) {
                return response()->json([
                    'errors' => ['Data penjualan tidak ditemukan'],
                ], 422);
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
                    return response()->json([
                        'errors' => [
                            'Total retur (' . number_format($totalReturnPrice, 0, ',', '.') .
                                ') tidak boleh melebihi sisa piutang (' . number_format($remainingDebt, 0, ',', '.') . ')'
                        ],
                    ], 422);
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
                if (!$product) {
                    return response()->json([
                        'errors' => ['Product not found'],
                    ], 422);
                }

                // Convert the return quantity to eceran for validation
                $returnQuantityInEceran = $this->convertToEceran($quantityRetur, $unitId, $product);

                // Get total remaining quantity in eceran from all sell details
                $totalRemainingEceran = $this->getTotalRemainingQuantityInEceran($sellId, $productId);

                // Get total already returned quantity in eceran from cart
                $totalReturnedEceran = $this->getTotalReturnedQuantityInEceran($userId, $sellId, $productId);

                // Calculate available quantity for return
                $availableForReturnEceran = $totalRemainingEceran - $totalReturnedEceran;

                // Validate that return quantity doesn't exceed available quantity
                if ($returnQuantityInEceran > $availableForReturnEceran) {
                    $availableInRequestedUnit = $this->convertFromEceran($availableForReturnEceran, $unitId, $product);

                    return response()->json([
                        'errors' => [
                            'Jumlah retur (' . $quantityRetur . ') melebihi jumlah yang tersedia untuk dikembalikan (' .
                                number_format($availableInRequestedUnit, 2) . ' dalam unit yang diminta)'
                        ],
                    ], 422);
                }

                // Basic validation
                $rules = [
                    'quantity' => 'required|numeric|min:1',
                ];

                $message = [
                    'quantity.required' => 'Jumlah retur harus diisi',
                    'quantity.numeric' => 'Jumlah retur harus berupa angka',
                    'quantity.min' => 'Jumlah retur minimal 1',
                ];

                $validator = Validator::make(['quantity' => $quantityRetur], $rules, $message);

                if ($validator->fails()) {
                    return response()->json([
                        'errors' => $validator->errors()->all(),
                    ], 422);
                }

                // Get the correct price from the original sale details
                $correctPrice = $this->getOriginalSalePrice($sellId, $productId, $unitId);

                // Check if cart item with same unit already exists
                $existingCart = SellReturCart::where('user_id', $userId)
                    ->where('sell_id', $sellId)
                    ->where('product_id', $productId)
                    ->where('unit_id', $unitId)
                    ->first();

                if ($existingCart) {
                    $existingCart->quantity += $quantityRetur;
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

        return redirect()->back()->with('success', 'Berhasil menambahkan retur ke keranjang');
    }

    public function destroyCart($id)
    {
        $returCart = SellReturCart::find($id);
        $returCart->delete();

        return redirect()->back();
    }

    public function updateCartQuantity(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $returCart = SellReturCart::findOrFail($id);
        $product = Product::find($returCart->product_id);
        $newQuantity = $request->quantity;

        // Convert new quantity to eceran for validation
        $newQuantityInEceran = $this->convertToEceran($newQuantity, $returCart->unit_id, $product);

        // Calculate available return quantities
        $totalRemainingEceran = $this->getTotalRemainingQuantityInEceran($returCart->sell_id, $returCart->product_id);
        $totalReturnedEceran = $this->getTotalReturnedQuantityInEceran(auth()->id(), $returCart->sell_id, $returCart->product_id);
        
        // Add back the old quantity before checking (since it's currently in cart)
        $oldQuantityInEceran = $this->convertToEceran($returCart->quantity, $returCart->unit_id, $product);
        $availableForReturnEceran = $totalRemainingEceran - $totalReturnedEceran + $oldQuantityInEceran;

        // Validate new quantity doesn't exceed available
        if ($newQuantityInEceran > $availableForReturnEceran) {
            $availableInRequestedUnit = $this->convertFromEceran($availableForReturnEceran, $returCart->unit_id, $product);
            return response()->json([
                'success' => false,
                'message' => 'Jumlah melebihi yang tersedia untuk dikembalikan (' . number_format($availableInRequestedUnit, 2) . ')'
            ], 400);
        }

        // Update cart quantity
        $returCart->quantity = $newQuantity;
        $returCart->save();

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully',
            'data' => [
                'quantity' => $returCart->quantity,
                'subtotal' => $returCart->price * $returCart->quantity
            ]
        ]);
    }

    public function print($id)
    {
        $sellRetur = SellRetur::with('sell.customer', 'sell.details', 'warehouse', 'user')->where('id', $id)->first();
        $sellReturDetail = SellReturDetail::with('sellRetur.sell.details', 'product', 'unit')->where('sell_retur_id', $id)->get();
        $returNumber = "PJR-" . date('Ymd') . "-" . str_pad($sellRetur->id, 4, '0', STR_PAD_LEFT);

        $totalQuantity = $sellReturDetail->count();
        $totalPrice = 0;

        foreach ($sellReturDetail as $prd) {
            $totalPrice += $prd->qty * $prd->price;
        }

        $pdf = Pdf::loadView('pages.retur.print-retur', compact('sellRetur', 'sellReturDetail', 'totalQuantity', 'returNumber', 'totalPrice'));
        return response()->stream(function () use ($pdf) {
            echo $pdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Retur-Penjualan-' . $sellRetur->sell->order_number . '.pdf"'
        ]);
    }

    public function viewReturnPenjualan()
    {
        return view('pages.retur.view_return');
    }
}
