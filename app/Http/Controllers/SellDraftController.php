<?php

namespace App\Http\Controllers;

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
use App\Support\QuantityInputNormalizer;
use App\Support\SalePaymentMethodResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use InvalidArgumentException;

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
        $sell = Sell::where('id', $id)->firstOrFail();
        $sellCart = SellCartDraft::where('sell_id', $id)
            ->get();

        $transfer = (int) str_replace(',', '', $request->transfer ?? 0);
        $cash = (int) str_replace(',', '', $request->cash ?? 0);
        $change = (int) preg_replace('/[,.]/', '', $request->change ?? 0);

        $pay = $transfer + $cash;

        if ($request->status == 'draft') {
            $status = 'draft';
        } elseif ($pay < preg_replace('/[,.]/', '', $request->grand_total)) {
            $status = 'piutang';
        } else {
            $status = 'lunas';
        }

        $rawPaymentMethod = $request->input('payment_method');
        $paymentMethod = SalePaymentMethodResolver::resolve(
            is_string($rawPaymentMethod) ? $rawPaymentMethod : null,
            $cash,
            $transfer
        );

        if ($request->status == 'draft') {
            $sell->status = $status;
            $sell->customer_id = $request->customer;
            $sell->grand_total = preg_replace('/[,.]/', '', $request->grand_total);
            $sell->pay = $pay;
            $sell->cash = $cash;
            $sell->transfer = $transfer;
            $sell->change = $change;
            $sell->payment_method = $paymentMethod;
            $sell->cashier_id = auth()->id();
            $sell->update();

            foreach ($sellCart as $sc) {
                $draftRow = SellCartDraft::where('sell_id', $id)
                    ->where('product_id', $sc->product_id)
                    ->where('unit_id', $sc->unit_id)
                    ->first();

                if ($draftRow) {
                    $draftRow->quantity = $sc->quantity;
                    $draftRow->save();
                } else {
                    SellCartDraft::create([
                        'cashier_id' => $request->cashier_id,
                        'product_id' => $sc->product_id,
                        'unit_id' => $sc->unit_id,
                        'quantity' => $sc->quantity,
                        'price' => $sc->price,
                        'diskon' => $sc->diskon,
                        'sell_id' => $id,
                    ]);
                }
            }

            return redirect()
                ->route('penjualan-draft.index')
                ->with('success', 'penjualan berhasil diubah');
        }

        try {
            DB::beginTransaction();

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
                $sell->order_number = 'PJ-'.$today.'-'.$warehouseId.$userId.'-'.$formattedOrderNumber;
            }

            if ($pay > 0 && $paymentMethod === null) {
                throw new InvalidArgumentException('Metode pembayaran tidak dapat ditentukan untuk jurnal kas.');
            }

            $sell->status = $status;
            $sell->customer_id = $request->customer;
            $sell->grand_total = preg_replace('/[,.]/', '', $request->grand_total);
            $sell->pay = $pay;
            $sell->cash = $cash;
            $sell->transfer = $transfer;
            $sell->change = $change;
            $sell->payment_method = $paymentMethod;
            $sell->cashier_id = auth()->id();
            $sell->save();

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
                    'description' => 'Penjualan '.$sell->order_number,
                ]);
            }

            $sellCart->each->delete();

            if ($pay > 0) {
                $customer = Customer::find($request->customer);
                $customerName = $customer ? $customer->name : '';

                $this->cashflowService->handleSalePayment(
                    warehouseId: auth()->user()->warehouse_id,
                    orderNumber: $sell->order_number,
                    customerName: $customerName,
                    paymentMethod: $paymentMethod,
                    cash: (float) $cash,
                    transfer: (float) $transfer,
                    change: (float) $sell->change,
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return redirect()->back()
                ->withInput()
                ->withErrors('Terjadi kesalahan saat menyimpan transaksi.');
        }

        try {
            sleep(1);

            $printUrl = route('penjualan.print', $sell->id);
            $script = "<script>
                setTimeout(function() {
                    window.open('$printUrl', '_blank');
                }, 500);
            </script>";

            return Response::make($script.'<script>setTimeout(function() { window.location.href = "'.route('penjualan.index').'"; }, 1000);</script>');
        } catch (\Throwable $th) {
            return redirect()->route('penjualan.index')->withErrors('Transaksi berhasil disimpan, tetapi gagal mencetak struk');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sell = Sell::where('id', $id)->first();

        if (! $sell) {
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

        $message = 'Penjualan draft berhasil dihapus';
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

        if (! is_array($inputRequests)) {
            return response()->json(['error' => 'Invalid input data.'], 400);
        }

        try {
            DB::beginTransaction();

            foreach ($inputRequests as $inputRequest) {
                if (! is_array($inputRequest)) {
                    continue;
                }

                $productId = $inputRequest['product_id'];
                $sellId = $inputRequest['sell_id'];
                $product = Product::find($productId);

                if (! $product) {
                    DB::rollBack();

                    return response()->json(['errors' => ['Produk tidak ditemukan.']], 422);
                }

                $quantityDus = QuantityInputNormalizer::toFloatOrNull($inputRequest['quantity_dus'] ?? null);
                $quantityPak = QuantityInputNormalizer::toFloatOrNull($inputRequest['quantity_pak'] ?? null);
                $quantityEceran = QuantityInputNormalizer::toFloatOrNull($inputRequest['quantity_eceran'] ?? null);

                if (! isset($inputRequest['unit_dus'])) {
                    continue;
                }

                if (! isset($inputRequest['unit_pak'])) {
                    continue;
                }

                if (! isset($inputRequest['unit_eceran'])) {
                    continue;
                }

                // Process quantity_dus if it exists
                if ($quantityDus !== null && $quantityDus > 0) {
                    $validationError = $this->validateSellPriceInput($inputRequest['price_dus'] ?? null, $product->name, 'dus');
                    if ($validationError !== null) {
                        DB::rollBack();

                        return response()->json(['errors' => [$validationError]], 422);
                    }
                    $this->processCartItem($productId, $sellId, $quantityDus, $inputRequest['unit_dus'], $inputRequest['price_dus'], $inputRequest['diskon_dus'] ?? 0);
                    $this->decreaseInventory($productId, $quantityDus, $inputRequest['unit_dus']);
                }

                // Process quantity_pak if it exists
                if ($quantityPak !== null && $quantityPak > 0) {
                    $validationError = $this->validateSellPriceInput($inputRequest['price_pak'] ?? null, $product->name, 'pak');
                    if ($validationError !== null) {
                        DB::rollBack();

                        return response()->json(['errors' => [$validationError]], 422);
                    }
                    $this->processCartItem($productId, $sellId, $quantityPak, $inputRequest['unit_pak'], $inputRequest['price_pak'], $inputRequest['diskon_pak'] ?? 0);
                    $this->decreaseInventory($productId, $quantityPak, $inputRequest['unit_pak']);
                }

                // Process quantity_eceran if it exists
                if ($quantityEceran !== null && $quantityEceran > 0) {
                    $validationError = $this->validateSellPriceInput($inputRequest['price_eceran'] ?? null, $product->name, 'eceran');
                    if ($validationError !== null) {
                        DB::rollBack();

                        return response()->json(['errors' => [$validationError]], 422);
                    }
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

    private function validateSellPriceInput($priceInput, string $productName, string $unitName): ?string
    {
        $normalizedPrice = $this->normalizeSellPriceInput($priceInput);
        if ($normalizedPrice === null || $normalizedPrice <= 0) {
            return "Harga jual produk {$productName} ({$unitName}) harus lebih dari 0.";
        }

        return null;
    }

    private function normalizeSellPriceInput($priceInput): ?float
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

    private function processCartItem($productId, $sellId, $quantity, $unitId, $price, $discount): void
    {
        $quantity = QuantityInputNormalizer::toFloatOrNull($quantity) ?? 0.0;
        if ($quantity <= 0) {
            return;
        }

        $quantity = round($quantity, 2);
        $price = (float) str_replace(',', '', (string) $price);
        $discount = (float) str_replace(',', '', (string) $discount);

        if ($sellId != null) {
            $existingCart = SellCartDraft::where('sell_id', $sellId)
                ->where('product_id', $productId)
                ->where('unit_id', $unitId)
                ->first();
            if ($existingCart) {
                $existingCart->quantity = round((float) $existingCart->quantity + $quantity, 2);
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

    private function decreaseInventory($productId, $quantity, $unitId): void
    {
        $quantity = QuantityInputNormalizer::toFloatOrNull($quantity) ?? 0.0;
        $quantity = round($quantity, 2);

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
        if ($request->has('quantity')) {
            $parsedQuantity = QuantityInputNormalizer::toFloatOrNull($request->input('quantity'));
            if ($parsedQuantity !== null) {
                $request->merge(['quantity' => $parsedQuantity]);
            }
        }

        $request->validate([
            'quantity' => 'required|numeric|decimal:0,2|min:0.01',
        ]);

        $sellCart = SellCartDraft::findOrFail($id);
        $product = Product::find($sellCart->product_id);
        $inventory = Inventory::where('product_id', $sellCart->product_id)
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->first();

        // Calculate inventory adjustment
        $oldQuantity = round((float) $sellCart->quantity, 2);
        $newQuantity = round((float) $request->quantity, 2);

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
                'message' => 'Stok tidak mencukupi',
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
                'quantity' => round((float) $sellCart->quantity, 2),
                'subtotal' => round((float) $sellCart->price * (float) $sellCart->quantity - (float) $sellCart->diskon, 2),
            ],
        ]);
    }
}
