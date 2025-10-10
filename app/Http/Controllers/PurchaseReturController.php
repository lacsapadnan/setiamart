<?php

namespace App\Http\Controllers;

use App\Models\Cashflow;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseRetur;
use App\Models\PurchaseReturCart;
use App\Models\PurchaseReturDetail;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PurchaseReturController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $masters = User::role('master')->get();
        $warehouses = Warehouse::all();
        $users = User::all();
        return view('pages.PurchaseRetur.index', compact('masters', 'warehouses', 'users'));
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
            $retur = PurchaseRetur::with('purchase.supplier', 'warehouse', 'details', 'user')
                ->orderBy('id', 'desc');
        } else {
            $retur = PurchaseRetur::with('purchase.supplier', 'warehouse', 'details', 'user')
                ->where('warehouse_id', auth()->user()->warehouse_id)
                ->where('user_id', auth()->id())
                ->orderBy('id', 'desc');
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
        return response()->json($retur);
    }

    public function dataByPurchaseId($purchaseId)
    {
        $userRoles = auth()->user()->getRoleNames();
        $query = PurchaseReturDetail::with(['product', 'unit', 'purchaseRetur']);
        $query->whereHas('purchaseRetur', function ($q) use ($purchaseId) {
            $q->where('purchase_id', $purchaseId);
        });
        if ($userRoles[0] != 'master') {
            $query->whereHas('purchaseRetur', function ($q) {
                $q->where('warehouse_id', auth()->user()->warehouse_id);
            });
        }
        $returDetails = $query->get();
        $returDetails->each(function ($detail) {
            $detail->created_at = $detail->purchaseRetur->created_at;
            $detail->id = $detail->purchaseRetur->id;
        });
        return response()->json($returDetails);
    }

    public function  dataDetail($id)
    {
        $returDetail = PurchaseReturDetail::with('purchaseRetur', 'product', 'unit')->where('purchase_retur_id', $id)->get();
        return response()->json($returDetail);
    }

    public function dataPurchase(Request $request)
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
            $purchases = Purchase::with('details.product.unit_dus', 'details.product.unit_pak', 'details.product.unit_eceran', 'warehouse', 'supplier')
                ->orderBy('id', 'desc');
        } else {
            $purchases = Purchase::with('details.product.unit_dus', 'details.product.unit_pak', 'details.product.unit_eceran', 'warehouse', 'supplier')
                ->where('warehouse_id', auth()->user()->warehouse_id)
                ->orderBy('id', 'desc');
        }

        if ($warehouse) {
            $purchases->where('warehouse_id', $warehouse);
        }

        if ($user_id) {
            $purchases->where('user_id', $user_id);
        }

        if ($fromDate && $toDate) {
            $endDate = Carbon::parse($toDate)->endOfDay();

            $purchases->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        $purchases = $purchases->get();
        return response()->json($purchases);
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
        return view('pages.PurchaseRetur.list-pembelian', compact('masters', 'warehouses', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $returCart = PurchaseReturCart::where('user_id', auth()->id())
            ->where('purchase_id', $request->purchase_id)
            ->get();

        // Get the purchase record to check status and calculate remaining debt
        $purchase = Purchase::where('id', $request->purchase_id)->first();

        if (!$purchase) {
            return redirect()->back()->with('error', 'Data pembelian tidak ditemukan');
        }

        // Calculate total return price
        $totalReturnPrice = 0;
        foreach ($returCart as $rc) {
            $totalReturnPrice += $rc->quantity * $rc->price;
        }

        // Validation: Check if total return exceeds remaining debt for piutang status
        if ($purchase->status === 'piutang') {
            $remainingDebt = $purchase->grand_total - $purchase->pay;
            if ($totalReturnPrice > $remainingDebt) {
                return redirect()->back()->with(
                    'error',
                    'Total retur (' . number_format($totalReturnPrice, 0, ',', '.') .
                        ') tidak boleh melebihi sisa piutang (' . number_format($remainingDebt, 0, ',', '.') . ')'
                );
            }
        }

        $totalPrice = 0;

        $purchaseRetur = PurchaseRetur::create([
            'purchase_id' => $request->purchase_id,
            'warehouse_id' => auth()->user()->warehouse_id,
            'user_id' => auth()->id(),
            'retur_date' => date('Y-m-d'),
        ]);

        foreach ($returCart as $rc) {
            // Simpan detail retur
            PurchaseReturDetail::create([
                'purchase_retur_id' => $purchaseRetur->id,
                'product_id' => $rc->product_id,
                'unit_id' => $rc->unit_id,
                'price' => $rc->price,
                'qty' => $rc->quantity,
            ]);

            // Proses pengurangan stok dan update detail pembelian
            $product = Product::find($rc->product_id);
            $inventory = Inventory::where('product_id', $rc->product_id)
                ->where('warehouse_id', auth()->user()->warehouse_id)
                ->first();

            // Konversi qty retur ke eceran
            $returnQuantityInEceran = $this->convertToEceran($rc->quantity, $rc->unit_id, $product);

            // Update inventory (stok keluar)
            if ($inventory) {
                $inventory->quantity -= $returnQuantityInEceran;
                $inventory->update();
            }

            // Update detail pembelian secara proporsional (jika ada beberapa unit)
            $purchaseDetails = PurchaseDetail::where('purchase_id', $request->purchase_id)
                ->where('product_id', $rc->product_id)
                ->with('product')
                ->get();
            $remainingToDeduct = $returnQuantityInEceran;
            foreach ($purchaseDetails as $purchaseDetail) {
                if ($remainingToDeduct <= 0) break;
                $purchaseDetailEceran = $this->convertToEceran($purchaseDetail->quantity, $purchaseDetail->unit_id, $product);
                if ($purchaseDetailEceran > 0) {
                    $deductEceran = min($remainingToDeduct, $purchaseDetailEceran);
                    $deductInOriginalUnit = $this->convertFromEceran($deductEceran, $purchaseDetail->unit_id, $product);
                    // Update qty dan total_price
                    $purchaseDetail->quantity -= $deductInOriginalUnit;
                    $purchaseDetail->total_price -= $deductInOriginalUnit * $purchaseDetail->price_unit;
                    $purchaseDetail->update();
                    $remainingToDeduct -= $deductEceran;
                }
            }
        }

        PurchaseReturCart::where('user_id', auth()->id())->delete();
        return redirect()->route('pembelian-retur.index')->with('success', 'Retur berhasil disimpan');
    }


    public function konfirmReturnPembelian(Request $request)
    {
        $selectedIds = $request->input('selectedIds');
        $totalPrice = 0;
        foreach ($selectedIds as $selectedId) {
            $purchaseReturDetails = DB::table('purchase_retur_details')->where('purchase_retur_id', $selectedId)->get();
            foreach ($purchaseReturDetails as $rc) {
                $purchase = Purchase::where('id', $request->purchase_id)->first();
                $product = Product::find($rc->product_id);
                $inventory = Inventory::where('product_id', $rc->product_id)
                    ->where('warehouse_id', auth()->user()->warehouse_id)
                    ->first();

                // Konversi qty retur ke eceran
                $returnQuantityInEceran = $this->convertToEceran($rc->qty, $rc->unit_id, $product);

                // Update inventory (stok keluar)
                if ($inventory) {
                    $inventory->quantity -= $returnQuantityInEceran;
                    $inventory->update();
                }

                // Update detail pembelian secara proporsional (jika ada beberapa unit)
                $purchaseDetails = PurchaseDetail::where('purchase_id', $request->purchase_id)
                    ->where('product_id', $rc->product_id)
                    ->with('product')
                    ->get();
                $remainingToDeduct = $returnQuantityInEceran;
                $totalPriceForThisReturn = 0;
                foreach ($purchaseDetails as $purchaseDetail) {
                    if ($remainingToDeduct <= 0) break;
                    $purchaseDetailEceran = $this->convertToEceran($purchaseDetail->quantity, $purchaseDetail->unit_id, $product);
                    if ($purchaseDetailEceran > 0) {
                        $deductEceran = min($remainingToDeduct, $purchaseDetailEceran);
                        $deductInOriginalUnit = $this->convertFromEceran($deductEceran, $purchaseDetail->unit_id, $product);
                        // Update qty dan total_price
                        $purchaseDetail->quantity -= $deductInOriginalUnit;
                        $purchaseDetail->total_price -= $deductInOriginalUnit * $purchaseDetail->price_unit;
                        $purchaseDetail->update();
                        $totalPriceForThisReturn += $deductInOriginalUnit * $purchaseDetail->price_unit;
                        $remainingToDeduct -= $deductEceran;
                    }
                }
                // update the purchase grand total
                $purchase->subtotal -= $totalPriceForThisReturn;
                $purchase->grand_total -= $totalPriceForThisReturn;
                $purchase->update();

                $totalPrice += $totalPriceForThisReturn;

                $unit = Unit::find($rc->unit_id);
                if ($rc->unit_id == $product->unit_dus) {
                    $unitType = 'DUS';
                } elseif ($rc->unit_id == $product->unit_pak) {
                    $unitType = 'PAK';
                } elseif ($rc->unit_id == $product->unit_eceran) {
                    $unitType = 'ECERAN';
                } else {
                    $unitType = $unit ? $unit->name : '-';
                }

                ProductReport::create([
                    'product_id' => $rc->product_id,
                    'warehouse_id' => auth()->user()->warehouse_id,
                    'user_id' => auth()->id(),
                    'supplier_id' => $purchase->supplier_id,
                    'unit' => $unit ? $unit->name : '-',
                    'unit_type' => $unitType,
                    'qty' => $rc->qty,
                    'price' => $rc->price,
                    'for' => 'KELUAR',
                    'type' => 'RETUR PEMBELIAN',
                    'description' => 'Retur Pembelian ' . $purchase->order_number,
                ]);
            }
            // update remark menjadi verify
            DB::table('purchase_returs')
                ->where('id', $selectedId)
                ->update(['remark' => 'verify']);

            Cashflow::create([
                'warehouse_id' => auth()->user()->warehouse_id,
                'user_id' => auth()->id(),
                'for' => 'Retur pembelian',
                'description' => 'Retur Pembelian ' . $purchase->order_number . ' Supplier ' . $purchase->supplier->name,
                'in' => $totalPrice,
                'out' => 0,
                'payment_method' => null,
            ]);
        }
        return response()->json(['message' => 'Return confirmed successfully']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $purchaseId = $id;
        $pembelian = Purchase::with('supplier', 'warehouse', 'details', 'details.unit')->findOrFail($id);
        $cart = PurchaseReturCart::with('product', 'unit')
            ->where('purchase_id', $id)
            ->where('user_id', auth()->id())->get();
        $subtotal = 0;
        foreach ($cart as $c) {
            $subtotal += $c->price * $c->quantity;
        }
        return view('pages.PurchaseRetur.create', compact('pembelian', 'cart', 'subtotal', 'purchaseId'));
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
            DB::beginTransaction();

            // Find the purchase return record
            $purchaseRetur = PurchaseRetur::with(['details.product', 'purchase'])->findOrFail($id);

            // Check authorization - only creator or master can delete
            $userRoles = auth()->user()->getRoleNames();
            if ($userRoles[0] !== 'master' && $purchaseRetur->user_id !== auth()->id()) {
                return response()->json([
                    'error' => 'Unauthorized to delete this return'
                ], 403);
            }

            // Process each return detail
            foreach ($purchaseRetur->details as $detail) {
                $product = $detail->product;

                // Convert return quantity to eceran for inventory calculation
                $returnQuantityInEceran = $this->convertToEceran($detail->qty, $detail->unit_id, $product);

                // Update inventory - INCREASE stock (we're undoing the return)
                $inventory = Inventory::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $purchaseRetur->warehouse_id)
                    ->first();

                if ($inventory) {
                    $inventory->quantity += $returnQuantityInEceran;
                    $inventory->update();
                }

                // Update purchase details - RESTORE original purchase quantities
                $purchaseDetails = PurchaseDetail::where('purchase_id', $purchaseRetur->purchase_id)
                    ->where('product_id', $detail->product_id)
                    ->with('product')
                    ->get();

                $remainingToRestore = $returnQuantityInEceran;
                foreach ($purchaseDetails as $purchaseDetail) {
                    if ($remainingToRestore <= 0) break;

                    $purchaseDetailEceran = $this->convertToEceran($purchaseDetail->quantity, $purchaseDetail->unit_id, $product);
                    if ($purchaseDetailEceran > 0) {
                        $restoreEceran = min($remainingToRestore, $purchaseDetailEceran);
                        $restoreInOriginalUnit = $this->convertFromEceran($restoreEceran, $purchaseDetail->unit_id, $product);

                        // Restore qty dan total_price
                        $purchaseDetail->quantity += $restoreInOriginalUnit;
                        $purchaseDetail->total_price += $restoreInOriginalUnit * $purchaseDetail->price_unit;
                        $purchaseDetail->update();

                        $remainingToRestore -= $restoreEceran;
                    }
                }

                // Update purchase totals
                $purchase = $purchaseRetur->purchase;
                $totalRestoredPrice = $detail->qty * $detail->price;
                $purchase->subtotal += $totalRestoredPrice;
                $purchase->grand_total += $totalRestoredPrice;
                $purchase->update();

                // Create product report for the restoration
                $unit = Unit::find($detail->unit_id);
                if ($detail->unit_id == $product->unit_dus) {
                    $unitType = 'DUS';
                } elseif ($detail->unit_id == $product->unit_pak) {
                    $unitType = 'PAK';
                } elseif ($detail->unit_id == $product->unit_eceran) {
                    $unitType = 'ECERAN';
                } else {
                    $unitType = $unit ? $unit->name : '-';
                }

                ProductReport::create([
                    'product_id' => $detail->product_id,
                    'warehouse_id' => $purchaseRetur->warehouse_id,
                    'user_id' => auth()->id(),
                    'supplier_id' => $purchase->supplier_id,
                    'unit' => $unit ? $unit->name : '-',
                    'unit_type' => $unitType,
                    'qty' => $detail->qty,
                    'price' => $detail->price,
                    'for' => 'MASUK',
                    'type' => 'HAPUS RETUR PEMBELIAN',
                    'description' => 'Hapus Retur Pembelian ' . $purchase->order_number,
                ]);
            }

            // Remove cashflow entries related to this return
            Cashflow::where('description', 'LIKE', '%Retur Pembelian ' . $purchaseRetur->purchase->order_number . '%')
                ->where('warehouse_id', $purchaseRetur->warehouse_id)
                ->delete();

            // Remove product reports related to this return
            ProductReport::where('description', 'LIKE', '%Retur Pembelian ' . $purchaseRetur->purchase->order_number . '%')
                ->where('warehouse_id', $purchaseRetur->warehouse_id)
                ->delete();

            // Delete return details
            $purchaseRetur->details()->delete();

            // Delete the main return record
            $purchaseRetur->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Return berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'error' => true,
                'message' => 'Gagal menghapus return: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addCart(Request $request)
    {
        $userId = auth()->id();
        $inputRequests = $request->input_requests;

        foreach ($inputRequests as $inputRequest) {
            $productId = $inputRequest['product_id'];
            $unitId = $inputRequest['unit_id'];
            $purchaseId = $inputRequest['purchase_id'];

            if (isset($inputRequest['quantity']) && $inputRequest['quantity']) {
                $quantityRetur = $inputRequest['quantity'];

                // Ambil data produk
                $product = Product::find($productId);
                if (!$product) {
                    return response()->json([
                        'errors' => ['Product not found'],
                    ], 422);
                }

                // Konversi qty retur ke eceran
                $returnQuantityInEceran = $this->convertToEceran($quantityRetur, $unitId, $product);

                // Total sisa qty pembelian (eceran)
                $totalRemainingEceran = $this->getTotalRemainingQuantityInEceran($purchaseId, $productId);
                // Total qty sudah diretur (eceran)
                $totalReturnedEceran = $this->getTotalReturnedQuantityInEceran($userId, $purchaseId, $productId);
                // Sisa qty yang bisa diretur (eceran)
                $availableForReturnEceran = $totalRemainingEceran - $totalReturnedEceran;

                // Validasi tidak boleh melebihi sisa qty
                if ($returnQuantityInEceran > $availableForReturnEceran) {
                    $availableInRequestedUnit = $this->convertFromEceran($availableForReturnEceran, $unitId, $product);
                    return response()->json([
                        'errors' => [
                            'Jumlah retur (' . $quantityRetur . ') melebihi jumlah yang tersedia untuk dikembalikan (' .
                                number_format($availableInRequestedUnit, 2) . ' dalam unit yang diminta)'
                        ],
                    ], 422);
                }

                // Validasi basic
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

                // Ambil harga dari detail pembelian yang sesuai, konversi jika perlu
                $purchaseDetailDus = PurchaseDetail::where('purchase_id', $purchaseId)
                    ->where('product_id', $productId)
                    ->where('unit_id', $product->unit_dus)
                    ->first();
                $purchaseDetailPak = PurchaseDetail::where('purchase_id', $purchaseId)
                    ->where('product_id', $productId)
                    ->where('unit_id', $product->unit_pak)
                    ->first();
                $purchaseDetailEceran = PurchaseDetail::where('purchase_id', $purchaseId)
                    ->where('product_id', $productId)
                    ->where('unit_id', $product->unit_eceran)
                    ->first();

                if ($unitId == $product->unit_eceran && $purchaseDetailDus && $product->dus_to_eceran > 0) {
                    // Retur PCS dari pembelian DUS
                    $price = $purchaseDetailDus->price_unit / $product->dus_to_eceran;
                } elseif ($unitId == $product->unit_eceran && $purchaseDetailPak && $product->pak_to_eceran > 0) {
                    // Retur PCS dari pembelian PAK
                    $price = $purchaseDetailPak->price_unit / $product->pak_to_eceran;
                } elseif ($unitId == $product->unit_eceran && $purchaseDetailEceran) {
                    // Retur PCS dari pembelian PCS
                    $price = $purchaseDetailEceran->price_unit;
                } elseif ($unitId == $product->unit_dus && $purchaseDetailDus) {
                    $price = $purchaseDetailDus->price_unit;
                } elseif ($unitId == $product->unit_pak && $purchaseDetailPak) {
                    $price = $purchaseDetailPak->price_unit;
                } else {
                    // Fallback ke master produk jika tidak ada detail pembelian sama sekali
                    if ($unitId == $product->unit_eceran) {
                        $price = $product->price_eceran ?? 0;
                    } elseif ($unitId == $product->unit_pak) {
                        $price = $product->price_pak ?? 0;
                    } elseif ($unitId == $product->unit_dus) {
                        $price = $product->price_dus ?? 0;
                    } else {
                        $price = 0;
                    }
                }

                // Cek jika cart dengan unit sama sudah ada
                $existingCart = PurchaseReturCart::where('user_id', $userId)
                    ->where('purchase_id', $purchaseId)
                    ->where('product_id', $productId)
                    ->where('unit_id', $unitId)
                    ->first();

                if ($existingCart) {
                    $existingCart->quantity += $quantityRetur;
                    $existingCart->save();
                } else {
                    PurchaseReturCart::create([
                        'purchase_id' => $purchaseId,
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'unit_id' => $unitId,
                        'quantity' => $quantityRetur,
                        'price' => $price,
                    ]);
                }
            }
        }
        return redirect()->back()->with('success', 'Berhasil menambahkan retur ke keranjang');
    }

    public function destroyCart($id)
    {
        $returCart = PurchaseReturCart::find($id);
        $returCart->delete();

        return redirect()->back();
    }

    public function updateCartQuantity(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $returCart = PurchaseReturCart::findOrFail($id);
        $product = Product::find($returCart->product_id);
        $newQuantity = $request->quantity;

        // Convert new quantity to eceran for validation
        $newQuantityInEceran = $this->convertToEceran($newQuantity, $returCart->unit_id, $product);

        // Calculate available return quantities
        $totalRemainingEceran = $this->getTotalRemainingQuantityInEceran($returCart->purchase_id, $returCart->product_id);
        $totalReturnedEceran = $this->getTotalReturnedQuantityInEceran(auth()->id(), $returCart->purchase_id, $returCart->product_id);
        
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
        $purchaseRetur = PurchaseRetur::with('purchase.supplier', 'purchase.details', 'warehouse', 'details', 'user')->findOrFail($id);
        $purchaseReturDetail = PurchaseReturDetail::with('product', 'unit')->where('purchase_retur_id', $id)->get();
        $returNumber = "PBR-" . date('Ymd') . "-" . str_pad(PurchaseRetur::count() + 1, 4, '0', STR_PAD_LEFT);
        $totalQuantity = $purchaseReturDetail->count();
        $totalPrice = 0;

        foreach ($purchaseReturDetail as $prd) {
            $totalPrice += $prd->price * $prd->qty;
        }

        $pdf = Pdf::loadView('pages.PurchaseRetur.print-retur', compact('purchaseRetur', 'purchaseReturDetail', 'totalQuantity', 'returNumber', 'totalPrice'));
        return response()->stream(function () use ($pdf) {
            echo $pdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Retur-Pembelian-' . $purchaseRetur->purchase->order_number . '.pdf"'
        ]);
    }

    public function viewReturnPembelian()
    {
        return view('pages.PurchaseRetur.view_return');
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
     * Get total remaining quantity in eceran for a product in a specific purchase
     */
    private function getTotalRemainingQuantityInEceran($purchaseId, $productId)
    {
        $purchaseDetails = PurchaseDetail::where('purchase_id', $purchaseId)
            ->where('product_id', $productId)
            ->with('product')
            ->get();

        $totalRemainingEceran = 0;

        foreach ($purchaseDetails as $purchaseDetail) {
            $quantityInEceran = $this->convertToEceran(
                $purchaseDetail->quantity,
                $purchaseDetail->unit_id,
                $purchaseDetail->product
            );
            $totalRemainingEceran += $quantityInEceran;
        }

        return $totalRemainingEceran;
    }

    /**
     * Get total returned quantity in eceran for a product from cart
     */
    private function getTotalReturnedQuantityInEceran($userId, $purchaseId, $productId)
    {
        $cartItems = PurchaseReturCart::where('user_id', $userId)
            ->where('purchase_id', $purchaseId)
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
     * Get available quantities for return in all units (untuk frontend)
     */
    public function getAvailableReturnQuantities($purchaseId, $productId)
    {
        $userId = auth()->id();
        $product = Product::find($productId);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Get total remaining quantity in eceran
        $totalRemainingEceran = $this->getTotalRemainingQuantityInEceran($purchaseId, $productId);
        // Get total already returned quantity in eceran from cart
        $totalReturnedEceran = $this->getTotalReturnedQuantityInEceran($userId, $purchaseId, $productId);
        // Calculate available quantity for return in eceran
        $availableForReturnEceran = $totalRemainingEceran - $totalReturnedEceran;

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
}
