<?php

namespace App\Http\Controllers;

use App\Models\Cashflow;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Sell;
use App\Models\SellCartDraft;
use App\Models\SellDetail;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class SellDraftController extends Controller
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
        return view('pages.sell.draft');
    }

    public function data()
    {
        $sells = Sell::with('details.product.unit_dus', 'details.product.unit_pak', 'details.product.unit_eceran', 'warehouse', 'customer')
            ->where('status', 'draft')
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->orderBy('id', 'desc')
            ->get();
        return response()->json($sells);
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
        $sell = Sell::with('warehouse', 'customer')
            ->where('id', $id)
            ->first();
        $inventories = Inventory::with('product')
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->get();
        $products = Product::all();
        $customers = Customer::all();
        $orderNumber = $sell->order_number;
        $cart = SellCartDraft::with('product', 'unit')
            ->where('sell_id', $id)
            ->orderBy('id', 'desc')
            ->get();
        $subtotal = 0;
        foreach ($cart as $c) {
            $subtotal += $c->price * $c->quantity - $c->diskon;
        }
        $masters = User::role('master')->get();
        return view('pages.sell.show-draft', compact('sell', 'inventories', 'products', 'cart', 'subtotal', 'customers', 'orderNumber', 'masters'));
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
        $sell = Sell::where('id', $id)->first();
        $sellCart = SellCartDraft::where('sell_id', $id)
            ->get();

        $transfer = str_replace(',', '', $request->transfer ?? 0);
        $cash = str_replace(',', '', $request->cash ?? 0);
        $change = str_replace('.', '', $request->change ?? 0);

        $pay = $transfer + $cash;

        if ($request->status == 'draft') {
            $status = 'draft';
        } elseif ($pay < preg_replace('/[,.]/', '', $request->grand_total)) {
            $status = 'piutang';
        } else {
            $status = 'lunas';
        }

        // Generate new order number if status is not draft and cashier changed
        if ($status !== 'draft' && $sell->cashier_id !== auth()->id()) {
            $today = date('Ymd');
            $today = substr($today, 2);
            $warehouseId = auth()->user()->warehouse_id;
            $userId = auth()->id();

            $lastOrder = Sell::where('cashier_id', $userId)
                ->where('warehouse_id', $warehouseId)
                ->whereDate('created_at', now())
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastOrder) {
                $lastOrderNumberPart = explode('-', $lastOrder->order_number);
                $lastOrderNumber = intval(end($lastOrderNumberPart));
                $newOrderNumber = $lastOrderNumber + 1;
            } else {
                $newOrderNumber = 1;
            }

            $formattedOrderNumber = str_pad($newOrderNumber, 4, '0', STR_PAD_LEFT);
            $sell->order_number = "PJ-" . $today . "-" . $warehouseId . $userId . "-" . $formattedOrderNumber;
        }

        $sell->status = $status;
        $sell->customer_id = $request->customer;
        $sell->grand_total = preg_replace('/[,.]/', '', $request->grand_total);
        $sell->pay = $pay;
        $sell->cash = $cash ?? 0;
        $sell->transfer = $transfer ?? 0;
        $sell->change = $change ?? 0;
        $sell->payment_method = $request->payment_method ?? null;
        $sell->cashier_id = auth()->id();
        $sell->update();

        if ($request->status == 'draft') {
            foreach ($sellCart as $sc) {
                $sellCart = SellCartDraft::where('sell_id', $id)
                    ->where('product_id', $sc->product_id)
                    ->where('unit_id', $sc->unit_id)
                    ->first();

                if ($sellCart) {
                    $sellCart->quantity = $sc->quantity;
                    $sellCart->save();
                } else {
                    SellCartDraft::create([
                        'cashier_id' => $request->cashier_id,
                        'product_id' => $sc->product_id,
                        'unit_id' => $sc->unit_id,
                        'quantity' => $sc->quantity,
                        'price' => $sc->price,
                        'diskon' => $sc->diskon,
                        'sell_id' => $id
                    ]);
                }
            }

            return redirect()
                ->route('penjualan-draft.index')
                ->with('success', 'penjualan berhasil diubah');
        } else {
            foreach ($sellCart as $sc) {
                SellDetail::create([
                    'sell_id' => $sell->id,
                    'product_id' => $sc->product_id,
                    'unit_id' => $sc->unit_id,
                    'quantity' => $sc->quantity,
                    'price' => $sc->price,
                    'diskon' => $sc->diskon,
                ]);

                $unit = Unit::find($sc->unit_id);
                $product = Product::find($sc->product_id);

                if ($sc->unit_id == $product->unit_dus) {
                    $unitType = 'DUS';
                } elseif ($sc->unit_id == $product->unit_pak) {
                    $unitType = 'PAK';
                } elseif ($sc->unit_id == $product->unit_eceran) {
                    $unitType = 'ECERAN';
                }

                ProductReport::create([
                    'product_id' => $sc->product_id,
                    'warehouse_id' => auth()->user()->warehouse_id,
                    'user_id' => auth()->id(),
                    'customer_id' => $request->customer,
                    'unit' => $unit->name,
                    'unit_type' => $unitType,
                    'qty' => $sc->quantity,
                    'price' => $sc->price,
                    'for' => 'KELUAR',
                    'type' => 'PENJUALAN',
                    'description' => 'Penjualan ' . $sell->order_number,
                ]);
            }

            $sellCart->each->delete();

            // Only handle cashflow if it's not a credit sale (piutang)
            if ($status !== 'piutang' && $request->payment_method) {
                // Get customer name for the cashflow record
                $customer = Customer::find($request->customer);
                $customerName = $customer ? $customer->name : '';

                // Handle cashflow using service
                $cashflowService = app(CashflowService::class);
                $cashflowService->handleSalePayment(
                    warehouseId: auth()->user()->warehouse_id,
                    orderNumber: $request->order_number,
                    customerName: $customerName,
                    paymentMethod: $request->payment_method,
                    cash: $cash,
                    transfer: $transfer,
                    change: $sell->change
                );
            }
        }

        if ($request->status != 'draft') {
            try {
                // Add a small delay to ensure database operations are fully committed
                sleep(1);

                $printUrl = route('penjualan.print', $sell->id);
                $script = "<script>
                    setTimeout(function() {
                        window.open('$printUrl', '_blank');
                    }, 500);
                </script>";
                return Response::make($script . '<script>setTimeout(function() { window.location.href = "' . route('penjualan.index') . '"; }, 1000);</script>');
            } catch (\Throwable $th) {
                return redirect()->route('penjualan.index')->withErrors('Transaksi berhasil disimpan, tetapi gagal mencetak struk');
            }
        } else {
            return redirect()->route('penjualan-draft.index')->with('success', 'Penjualan draft berhasil diubah');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sell = Sell::where('id', $id)->first();

        if (!$sell) {
            return redirect()
                ->route('penjualan-draft.index')
                ->with('error', 'Data penjualan draft tidak ditemukan');
        }

        $sellCart = SellCartDraft::where('cashier_id', $sell->cashier_id)
            ->where('sell_id', $id)
            ->get();

        // Restore inventory for each cart item
        foreach ($sellCart as $sc) {
            $inventory = Inventory::where('product_id', $sc->product_id)
                ->where('warehouse_id', auth()->user()->warehouse_id)
                ->first();

            if ($sc->unit_id == $sc->product->unit_dus) {
                $inventory->quantity += $sc->quantity * $sc->product->dus_to_eceran;
            } elseif ($sc->unit_id == $sc->product->unit_pak) {
                $inventory->quantity += $sc->quantity * $sc->product->pak_to_eceran;
            } elseif ($sc->unit_id == $sc->product->unit_eceran) {
                $inventory->quantity += $sc->quantity;
            }

            $inventory->save();
        }

        // Delete associated cashflows (in case draft was converted and then reverted)
        $deletedCashflows = $this->cashflowService->deleteAllSaleCashflows($sell->order_number);

        // Delete cart items and sell record
        $sellCart->each->delete();
        $sell->delete();

        $message = "Penjualan draft berhasil dihapus";
        if ($deletedCashflows > 0) {
            $message .= " beserta {$deletedCashflows} record cashflow terkait";
        }

        return redirect()
            ->route('penjualan-draft.index')
            ->with('success', $message);
    }

    public function addCart(Request $request)
    {
        $inputRequests = $request->input('requests');

        if (is_null($inputRequests)) {
            return response()->json(['error' => 'Invalid input data.'], 400);
        }

        if (!is_array($inputRequests)) {
            return response()->json(['error' => 'Invalid input data.'], 400);
        }

        try {
            DB::beginTransaction();

            foreach ($inputRequests as $inputRequest) {
                if (!is_array($inputRequest)) {
                    continue;
                }

                $productId = $inputRequest['product_id'];
                $sellId = $inputRequest['sell_id'];

                $quantityDus = isset($inputRequest['quantity_dus']) ? intval($inputRequest['quantity_dus']) : 0;
                $quantityPak = isset($inputRequest['quantity_pak']) ? intval($inputRequest['quantity_pak']) : 0;
                $quantityEceran = isset($inputRequest['quantity_eceran']) ? intval($inputRequest['quantity_eceran']) : 0;

                if (!isset($inputRequest['unit_dus'])) {
                    continue;
                }

                if (!isset($inputRequest['unit_pak'])) {
                    continue;
                }

                if (!isset($inputRequest['unit_eceran'])) {
                    continue;
                }

                // Process quantity_dus if it exists
                if ($quantityDus) {
                    $this->processCartItem($productId, $sellId, $quantityDus, $inputRequest['unit_dus'], $inputRequest['price_dus'], $inputRequest['diskon_dus'] ?? 0);
                    $this->decreaseInventory($productId, $quantityDus, $inputRequest['unit_dus']);
                }

                // Process quantity_pak if it exists
                if ($quantityPak) {
                    $this->processCartItem($productId, $sellId, $quantityPak, $inputRequest['unit_pak'], $inputRequest['price_pak'], $inputRequest['diskon_pak'] ?? 0);
                    $this->decreaseInventory($productId, $quantityPak, $inputRequest['unit_pak']);
                }

                // Process quantity_eceran if it exists
                if ($quantityEceran) {
                    $this->processCartItem($productId, $sellId, $quantityEceran, $inputRequest['unit_eceran'], $inputRequest['price_eceran'], $inputRequest['diskon_eceran'] ?? 0);
                    $this->decreaseInventory($productId, $quantityEceran, $inputRequest['unit_eceran']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to add items to cart.'], 500);
        }

        return response()->json(['success' => 'Items added to cart successfully.'], 200);
    }

    private function processCartItem($productId, $sellId, $quantity, $unitId, $price, $discount)
    {
        $totalPrice = $price * $quantity;

        // Apply the discount to the total price if the discount is provided
        if ($discount > 0) {
            $totalPrice -= $discount;
        }

        if ($sellId != null) {
            $existingCart = SellCartDraft::where('sell_id', $sellId)
                ->where('product_id', $productId)
                ->where('unit_id', $unitId)
                ->first();
            if ($existingCart) {
                $existingCart->quantity += $quantity;
                $existingCart->save();
            } else {
                SellCartDraft::create([
                    'cashier_id' => auth()->id(),
                    'sell_id' => $sellId,
                    'product_id' => $productId,
                    'unit_id' => $unitId,
                    'quantity' => $quantity,
                    'price' => $price,
                    'diskon' => $discount,
                ]);
            }
        }
    }

    private function decreaseInventory($productId, $quantity, $unitId)
    {
        $product = Product::find($productId);
        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->first();

        if ($unitId == $product->unit_dus) {
            $inventory->quantity -= $quantity * $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            $inventory->quantity -= $quantity * $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            $inventory->quantity -= $quantity;
        }

        $inventory->save();
    }

    public function destroyCart(Request $request, $id)
    {
        $sellCart = SellCartDraft::where('product_id', $request->product_id)
            ->where('id', $id)
            ->first();

        $sellCart->delete();

        // check unit id is unit_dus, unit_pak, or unit_eceran
        $unitId = $sellCart->unit_id;
        $product = Product::find($sellCart->product_id);
        $inventory = Inventory::where('product_id', $sellCart->product_id)
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->first();

        if ($unitId == $product->unit_dus) {
            $inventory->quantity += $sellCart->quantity * $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            $inventory->quantity += $sellCart->quantity * $product->pak_to_eceran;
        } elseif ($unitId == $product->unit_eceran) {
            $inventory->quantity += $sellCart->quantity;
        }

        $inventory->save();

        return redirect()->back();
    }

    public function updateCartQuantity(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $sellCart = SellCartDraft::findOrFail($id);
        $product = Product::find($sellCart->product_id);
        $inventory = Inventory::where('product_id', $sellCart->product_id)
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->first();

        // Calculate inventory adjustment
        $oldQuantity = $sellCart->quantity;
        $newQuantity = $request->quantity;

        // Restore old quantity to inventory
        if ($sellCart->unit_id == $product->unit_dus) {
            $inventory->quantity += $oldQuantity * $product->dus_to_eceran;
        } elseif ($sellCart->unit_id == $product->unit_pak) {
            $inventory->quantity += $oldQuantity * $product->pak_to_eceran;
        } elseif ($sellCart->unit_id == $product->unit_eceran) {
            $inventory->quantity += $oldQuantity;
        }

        // Deduct new quantity from inventory
        if ($sellCart->unit_id == $product->unit_dus) {
            $inventory->quantity -= $newQuantity * $product->dus_to_eceran;
        } elseif ($sellCart->unit_id == $product->unit_pak) {
            $inventory->quantity -= $newQuantity * $product->pak_to_eceran;
        } elseif ($sellCart->unit_id == $product->unit_eceran) {
            $inventory->quantity -= $newQuantity;
        }

        // Check if inventory is sufficient
        if ($inventory->quantity < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Stok tidak mencukupi'
            ], 400);
        }

        $inventory->save();

        // Update cart quantity
        $sellCart->quantity = $newQuantity;
        $sellCart->save();

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully',
            'data' => [
                'quantity' => $sellCart->quantity,
                'subtotal' => $sellCart->price * $sellCart->quantity - $sellCart->diskon
            ]
        ]);
    }
}
