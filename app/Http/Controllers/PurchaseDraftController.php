<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Purchase;
use App\Models\PurchaseCartDraft;
use App\Models\PurchaseDetail;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\CashflowService;
use App\Support\QuantityInputNormalizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseDraftController extends Controller
{
    protected CashflowService $cashflowService;

    public function __construct(CashflowService $cashflowService)
    {
        $this->cashflowService = $cashflowService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages.purchase.draft');
    }

    public function data()
    {
        $role = auth()->user()->getRoleNames();
        $query = Purchase::with('supplier', 'warehouse', 'user')
            ->where('status', 'draft')
            ->orderByDesc('id');

        if ($role->first() !== 'master') {
            $query->where('warehouse_id', auth()->user()->warehouse_id)
                ->where('user_id', auth()->id());
        }

        return response()->json($query->get());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort(404);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $query = Purchase::query()->where('id', $id)->where('status', 'draft');
        if (! auth()->user()->hasRole('master')) {
            $query->where('warehouse_id', auth()->user()->warehouse_id)
                ->where('user_id', auth()->id());
        }

        $purchase = $query->firstOrFail();

        $suppliers = Supplier::all();
        $inventories = Inventory::with('product')
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->whereHas('product', function ($query): void {
                $query->where('isShow', true);
            })
            ->get();
        $products = Product::where('isShow', true)->get();
        $units = Unit::all();
        $orderNumber = $purchase->order_number;
        $cart = PurchaseCartDraft::with('product.unit_dus', 'product.unit_pak', 'product.unit_eceran', 'unit')
            ->where('purchase_id', $purchase->id)
            ->orderByDesc('id')
            ->get();

        $subtotal = (int) round($cart->sum('total_price'));

        return view('pages.purchase.create', compact('suppliers', 'inventories', 'products', 'units', 'cart', 'subtotal', 'orderNumber', 'purchase'));
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
        $query = Purchase::query()->where('id', $id)->where('status', 'draft');
        if (! auth()->user()->hasRole('master')) {
            $query->where('warehouse_id', auth()->user()->warehouse_id)
                ->where('user_id', auth()->id());
        }

        $purchase = $query->firstOrFail();

        $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ], [
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak valid.',
        ]);

        $draftCart = PurchaseCartDraft::where('purchase_id', $purchase->id)->get();

        if ($draftCart->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'Keranjang draft pembelian kosong.']);
        }

        $subtotal = (int) str_replace([',', '.'], '', (string) ($request->subtotal ?? 0));
        $potongan = (int) str_replace([',', '.'], '', (string) ($request->potongan ?? 0));
        $grandTotal = (int) str_replace([',', '.'], '', (string) ($request->grand_total ?? 0));
        $tax = (float) str_replace([',', '.'], '', (string) ($request->tax ?? 0));
        $cash = (int) str_replace([',', '.'], '', (string) ($request->cash ?? 0));
        $transfer = (int) str_replace([',', '.'], '', (string) ($request->transfer ?? 0));
        $bayar = (int) str_replace([',', '.'], '', (string) ($request->pay ?? 0));
        $paymentMethod = $request->payment_method;

        $pay = 0;
        if ($paymentMethod === 'cash' && $cash > 0) {
            $pay = $cash;
        } elseif ($paymentMethod === 'transfer' && $transfer > 0) {
            $pay = $transfer;
        } elseif ($paymentMethod === 'split' && ($cash > 0 || $transfer > 0)) {
            $pay = $cash + $transfer;
        } elseif ($bayar > 0) {
            $pay = $bayar;
        }

        if (empty($paymentMethod)) {
            if ($cash > 0 && $transfer > 0) {
                $paymentMethod = 'split';
                $pay = $cash + $transfer;
            } elseif ($cash > 0) {
                $paymentMethod = 'cash';
                $pay = $cash;
            } elseif ($transfer > 0) {
                $paymentMethod = 'transfer';
                $pay = $transfer;
            }
        }

        $recieptDate = $this->parseRecieptDate((string) $request->reciept_date);

        if ($request->status === 'draft') {
            $purchase->update([
                'supplier_id' => $request->supplier_id,
                'invoice' => $request->invoice,
                'subtotal' => $subtotal,
                'potongan' => $potongan,
                'grand_total' => $grandTotal,
                'pay' => $pay,
                'reciept_date' => $recieptDate,
                'description' => $request->description,
                'tax' => $tax,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'cash' => $cash,
                'transfer' => $transfer,
            ]);

            return redirect()->route('pembelian-draft.index')->with('success', 'Draft pembelian berhasil diperbarui');
        }

        DB::beginTransaction();
        try {
            $status = $pay < $grandTotal ? 'hutang' : 'lunas';

            $purchase->update([
                'supplier_id' => $request->supplier_id,
                'invoice' => $request->invoice,
                'subtotal' => $subtotal,
                'potongan' => $potongan,
                'grand_total' => $grandTotal,
                'pay' => $pay,
                'reciept_date' => $recieptDate,
                'description' => $request->description,
                'tax' => $tax,
                'status' => $status,
                'payment_method' => $paymentMethod,
                'cash' => $cash,
                'transfer' => $transfer,
            ]);

            PurchaseDetail::where('purchase_id', $purchase->id)->delete();
            $supplier = Supplier::find($request->supplier_id);
            $warehouse = auth()->user()->warehouse;

            foreach ($draftCart as $cart) {
                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $cart->product_id,
                    'unit_id' => $cart->unit_id,
                    'quantity' => $cart->quantity,
                    'discount_fix' => $cart->discount_fix,
                    'discount_percent' => $cart->discount_percent,
                    'price_unit' => $cart->price_unit,
                    'total_price' => $cart->total_price,
                ]);

                $product = Product::find($cart->product_id);
                if (! $product) {
                    continue;
                }

                try {
                    if ($cart->unit_id == $product->unit_dus) {
                        if (($warehouse?->isOutOfTown ?? false) && $product->dus_to_eceran > 0) {
                            $product->lastest_price_eceran_out_of_town = $cart->price_unit / $product->dus_to_eceran;
                        } elseif ($product->dus_to_eceran > 0) {
                            $product->lastest_price_eceran = $cart->price_unit / $product->dus_to_eceran;
                        }
                    } elseif ($cart->unit_id == $product->unit_pak) {
                        if (($warehouse?->isOutOfTown ?? false) && $product->pak_to_eceran > 0) {
                            $product->lastest_price_eceran_out_of_town = $cart->price_unit / $product->pak_to_eceran;
                        } elseif ($product->pak_to_eceran > 0) {
                            $product->lastest_price_eceran = $cart->price_unit / $product->pak_to_eceran;
                        }
                    } elseif ($cart->unit_id == $product->unit_eceran) {
                        if ($warehouse?->isOutOfTown ?? false) {
                            $product->lastest_price_eceran_out_of_town = $cart->price_unit;
                        } else {
                            $product->lastest_price_eceran = $cart->price_unit;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Purchase draft finalize price update error: '.$e->getMessage());
                }

                $product->save();

                $inventory = Inventory::where('warehouse_id', auth()->user()->warehouse_id)
                    ->where('product_id', $cart->product_id)
                    ->first();

                $quantity = 0;
                if ($cart->unit_id == $product->unit_dus) {
                    $quantity = $cart->quantity * $product->dus_to_eceran;
                } elseif ($cart->unit_id == $product->unit_pak) {
                    $quantity = $cart->quantity * $product->pak_to_eceran;
                } elseif ($cart->unit_id == $product->unit_eceran) {
                    $quantity = $cart->quantity;
                }

                if ($inventory) {
                    $inventory->quantity += $quantity;
                    $inventory->save();
                } else {
                    Inventory::create([
                        'warehouse_id' => auth()->user()->warehouse_id,
                        'product_id' => $cart->product_id,
                        'quantity' => $quantity,
                    ]);
                }

                $unit = Unit::find($cart->unit_id);
                $unitType = 'ECERAN';
                if ($cart->unit_id == $product->unit_dus) {
                    $unitType = 'DUS';
                } elseif ($cart->unit_id == $product->unit_pak) {
                    $unitType = 'PAK';
                }

                if ($unit) {
                    ProductReport::create([
                        'product_id' => $cart->product_id,
                        'warehouse_id' => auth()->user()->warehouse_id,
                        'user_id' => auth()->id(),
                        'supplier_id' => $request->supplier_id,
                        'unit' => $unit->name,
                        'unit_type' => $unitType,
                        'qty' => $cart->quantity,
                        'price' => $cart->price_unit,
                        'for' => 'MASUK',
                        'type' => 'PEMBELIAN',
                        'description' => 'Pembelian '.$purchase->order_number,
                    ]);
                }
            }

            PurchaseCartDraft::where('purchase_id', $purchase->id)->delete();

            if ($pay > 0 && $supplier && $paymentMethod) {
                $this->cashflowService->handlePurchasePayment(
                    warehouseId: auth()->user()->warehouse_id,
                    orderNumber: $purchase->order_number,
                    supplierName: $supplier->name,
                    paymentMethod: $paymentMethod,
                    cash: $cash,
                    transfer: $transfer,
                    grandTotal: $grandTotal
                );
            }

            DB::commit();

            return redirect()->route('pembelian.index')->with('success', 'Draft pembelian berhasil diselesaikan');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return redirect()->back()->withErrors(['error' => 'Gagal memproses draft pembelian.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $query = Purchase::query()->where('id', $id)->where('status', 'draft');
        if (! auth()->user()->hasRole('master')) {
            $query->where('warehouse_id', auth()->user()->warehouse_id)
                ->where('user_id', auth()->id());
        }

        $purchase = $query->firstOrFail();
        PurchaseCartDraft::where('purchase_id', $purchase->id)->delete();
        $purchase->delete();

        return redirect()->route('pembelian-draft.index')->with('success', 'Draft pembelian berhasil dihapus');
    }

    public function addCart(Request $request)
    {
        $purchaseId = (int) $request->input('purchase_id');
        $requests = $request->input('requests', []);

        $purchase = Purchase::where('id', $purchaseId)->where('status', 'draft')->firstOrFail();
        if (! auth()->user()->hasRole('master') && $purchase->user_id !== auth()->id()) {
            abort(403);
        }

        foreach ($requests as $inputRequest) {
            $productId = $inputRequest['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            $quantityDus = QuantityInputNormalizer::toFloatOrNull($inputRequest['quantity_dus'] ?? null);
            if ($quantityDus !== null && $quantityDus > 0) {
                $this->processCartItem($purchase->id, auth()->id(), $productId, $quantityDus, $inputRequest['unit_dus'], $inputRequest['price_dus'], $inputRequest['discount_fix_dus'] ?? 0, $inputRequest['discount_percent_dus'] ?? 0);
            }

            $quantityPak = QuantityInputNormalizer::toFloatOrNull($inputRequest['quantity_pak'] ?? null);
            if ($quantityPak !== null && $quantityPak > 0) {
                $this->processCartItem($purchase->id, auth()->id(), $productId, $quantityPak, $inputRequest['unit_pak'], $inputRequest['price_pak'], $inputRequest['discount_fix_pak'] ?? 0, $inputRequest['discount_percent_pak'] ?? 0);
            }

            $quantityEceran = QuantityInputNormalizer::toFloatOrNull($inputRequest['quantity_eceran'] ?? null);
            if ($quantityEceran !== null && $quantityEceran > 0) {
                $this->processCartItem($purchase->id, auth()->id(), $productId, $quantityEceran, $inputRequest['unit_eceran'], $inputRequest['price_eceran'], $inputRequest['discount_fix_eceran'] ?? 0, $inputRequest['discount_percent_eceran'] ?? 0);
            }
        }

        return redirect()->back();
    }

    public function destroyCart($id)
    {
        $cart = PurchaseCartDraft::findOrFail($id);
        $cart->delete();

        return redirect()->back();
    }

    public function updateCartQuantity(Request $request, $id)
    {
        if ($request->has('quantity')) {
            $parsedQuantity = QuantityInputNormalizer::toFloatOrNull($request->input('quantity'));
            if ($parsedQuantity !== null) {
                $request->merge(['quantity' => $parsedQuantity]);
            }
        }

        $request->validate([
            'quantity' => 'required|numeric|decimal:0,2|min:0.01',
        ]);

        $purchaseCart = PurchaseCartDraft::findOrFail($id);
        $newQuantity = round((float) $request->quantity, 2);

        $priceUnit = $purchaseCart->price_unit;
        if ($purchaseCart->discount_fix && $purchaseCart->discount_percent) {
            $totalPrice = ($priceUnit * $newQuantity) - $purchaseCart->discount_fix - ($priceUnit * $newQuantity * $purchaseCart->discount_percent / 100);
        } elseif ($purchaseCart->discount_fix) {
            $totalPrice = ($priceUnit * $newQuantity) - $purchaseCart->discount_fix;
        } elseif ($purchaseCart->discount_percent) {
            $totalPrice = ($priceUnit * $newQuantity) - ($priceUnit * $newQuantity * $purchaseCart->discount_percent / 100);
        } else {
            $totalPrice = $priceUnit * $newQuantity;
        }

        $purchaseCart->quantity = $newQuantity;
        $purchaseCart->total_price = $totalPrice;
        $purchaseCart->save();

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully',
            'data' => [
                'quantity' => round((float) $purchaseCart->quantity, 2),
                'subtotal' => round((float) $purchaseCart->total_price, 2),
            ],
        ]);
    }

    private function processCartItem($purchaseId, $userId, $productId, $quantity, $unitId, $price, $discountFix, $discountPercent): void
    {
        $quantity = QuantityInputNormalizer::toFloatOrNull($quantity) ?? 0.0;
        if ($quantity <= 0) {
            return;
        }

        $quantity = round($quantity, 2);
        $price = (float) str_replace(',', '', (string) $price);
        $discountFix = (float) str_replace(',', '', (string) $discountFix);
        $discountPercent = (float) str_replace(',', '', (string) $discountPercent);

        if ($discountFix && $discountPercent) {
            $totalPrice = ($price * $quantity) - $discountFix - ($price * $quantity * $discountPercent / 100);
        } elseif ($discountFix) {
            $totalPrice = ($price * $quantity) - $discountFix;
        } elseif ($discountPercent) {
            $totalPrice = ($price * $quantity) - ($price * $quantity * $discountPercent / 100);
        } else {
            $totalPrice = $price * $quantity;
        }

        $existingCart = PurchaseCartDraft::where('purchase_id', $purchaseId)
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($existingCart) {
            $existingCart->quantity = round((float) $existingCart->quantity + $quantity, 2);
            $existingCart->total_price = round((float) $existingCart->price_unit * (float) $existingCart->quantity, 2);
            $existingCart->save();

            return;
        }

        PurchaseCartDraft::create([
            'purchase_id' => $purchaseId,
            'user_id' => $userId,
            'product_id' => $productId,
            'unit_id' => $unitId,
            'quantity' => $quantity,
            'discount_fix' => $discountFix,
            'discount_percent' => $discountPercent,
            'price_unit' => $quantity > 0 ? ($totalPrice / $quantity) : 0,
            'total_price' => $totalPrice,
        ]);
    }

    private function parseRecieptDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return Carbon::today()->format('Y-m-d');
        }
    }
}
