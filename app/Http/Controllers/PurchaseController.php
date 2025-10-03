<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Purchase;
use App\Models\PurchaseCart;
use App\Models\PurchaseDetail;
use App\Models\PurchaseRetur;
use App\Models\PurchaseReturDetail;
use App\Models\Supplier;
use App\Models\Treasury;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
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
        return view('pages.purchase.index', compact('masters', 'warehouses', 'users'));
    }

    public function data(Request $request)
    {
        try {
            $role = auth()->user()->getRoleNames();
            $user_id = $request->input('user_id');
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $warehouse = $request->input('warehouse');
            $isExport = $request->input('export', false);

            $defaultDate = now()->format('Y-m-d');

            if (!$fromDate) {
                $fromDate = $defaultDate;
            }

            if (!$toDate) {
                $toDate = $defaultDate;
            }

            if ($role->first() == 'master') {
                $purchases = Purchase::with('details.product.unit_dus', 'details.product.unit_pak', 'details.product.unit_eceran', 'treasury', 'supplier', 'warehouse', 'user')
                    ->orderBy('id', 'desc');
            } else {
                $purchases = Purchase::with('details.product.unit_dus', 'details.product.unit_pak', 'details.product.unit_eceran', 'treasury', 'supplier', 'warehouse', 'user')
                    ->where('warehouse_id', auth()->user()->warehouse_id)
                    ->where('user_id', auth()->id())
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
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Terjadi kesalahan saat mengambil data pembelian: ' . $e->getMessage(),
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $treasuries = Treasury::all();
        $suppliers = Supplier::all();
        $inventories = Inventory::with('product')
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->whereHas('product', function ($query) {
                $query->where('isShow', true);
            })
            ->get();
        $products = Product::where('isShow', true)->get();
        $units = Unit::all();
        $today = date('Ymd');
        $year = substr($today, 2, 2);
        $today = substr($today, 2);
        $warehouseId = auth()->user()->warehouse_id;
        $userId = auth()->id();

        $lastOrder = Purchase::where('user_id', $userId)
            ->where('warehouse_id', $warehouseId)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastOrder) {
            // Extract the numerical part of the order number and increment it
            $lastOrderNumberPart = explode('-', $lastOrder->order_number);
            $lastOrderNumber = intval(end($lastOrderNumberPart));
            $newOrderNumber = $lastOrderNumber + 1;
        } else {
            // Reset the order number to 1
            $newOrderNumber = 1;
        }

        // Format the new order number with leading zeros
        $formattedOrderNumber = str_pad($newOrderNumber, 4, '0', STR_PAD_LEFT);

        // Generate the order number string with warehouseId in the middle
        $orderNumber = "PL-" . $today . "-" . $warehouseId . auth()->id() . "-" . $formattedOrderNumber;
        $cart = PurchaseCart::with('product.unit_dus', 'product.unit_pak', 'product.unit_eceran')->where('user_id', auth()->id())->get();
        $subtotal = 0;
        foreach ($cart as $c) {
            $subtotal += $c->total_price;
        }

        return view('pages.purchase.create', compact('treasuries', 'suppliers', 'inventories', 'products', 'units', 'cart', 'subtotal', 'orderNumber'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {

            $existingCart = PurchaseCart::where('user_id', auth()->id())->get();

            $subtotal = (int)str_replace([',', '.'], '', $request->subtotal ?? 0);
            $remaint = (int)str_replace([',', '.'], '', $request->remaint ?? 0);
            $potongan = (int)str_replace([',', '.'], '', $request->potongan ?? 0);
            $grandTotal = (int) str_replace([',', '.'], '', $request->grand_total);

            // Get payment method first
            $paymentMethod = $request->payment_method;
            $cash = (int)str_replace([',', '.'], '', $request->cash ?? 0);
            $transfer = (int)str_replace([',', '.'], '', $request->transfer ?? 0);
            $bayar = (int)str_replace([',', '.'], '', $request->pay ?? 0);

            // Calculate actual payment amount based on payment method
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

            // Auto-detect payment method if not set
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
                } elseif ($bayar > 0) {
                    $pay = $bayar;
                }
            }

            if ($pay < $grandTotal) {
                $status = 'hutang';
            } else {
                $status = 'lunas';
            }

            $purchase = Purchase::create([
                'user_id' => auth()->id(),
                'supplier_id' => $request->supplier_id,
                'treasury_id' => $request->treasury_id,
                'warehouse_id' => auth()->user()->warehouse_id,
                'order_number' => $request->order_number,
                'invoice' => $request->invoice,
                'subtotal' => $subtotal,
                'potongan' => $potongan,
                'grand_total' => $grandTotal,
                'pay' => $pay,
                'reciept_date' => Carbon::createFromFormat('d/m/Y', $request->reciept_date)->format('Y-m-d'),
                'description' => $request->description,
                'tax' => $request->tax,
                'status' => $status,
                'payment_method' => $paymentMethod,
                'cash' => $cash,
                'transfer' => $transfer,
            ]);

            $supplier = Supplier::find($request->supplier_id);

            foreach ($existingCart as $cart) {
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

                // update product price
                $product = Product::find($cart->product_id);

                // Get the warehouse to check if it's out of town
                $warehouse = auth()->user()->warehouse;

                try {
                    if ($cart->unit_id == $product->unit_dus) {
                        if ($warehouse && $warehouse->isOutOfTown) {
                            if ($product->dus_to_eceran <= 0) {
                                Log::error('DivisionByZeroError: Product ID ' . $product->id . ' has dus_to_eceran = ' . $product->dus_to_eceran . ' (Product: ' . $product->name . ')');
                                throw new \DivisionByZeroError('Product ' . $product->name . ' (ID: ' . $product->id . ') has invalid dus_to_eceran value: ' . $product->dus_to_eceran);
                            }
                            $product->lastest_price_eceran_out_of_town = $cart->price_unit / $product->dus_to_eceran;
                        } else {
                            if ($product->dus_to_eceran <= 0) {
                                Log::error('DivisionByZeroError: Product ID ' . $product->id . ' has dus_to_eceran = ' . $product->dus_to_eceran . ' (Product: ' . $product->name . ')');
                                throw new \DivisionByZeroError('Product ' . $product->name . ' (ID: ' . $product->id . ') has invalid dus_to_eceran value: ' . $product->dus_to_eceran);
                            }
                            $product->lastest_price_eceran = $cart->price_unit / $product->dus_to_eceran;
                        }
                    } elseif ($cart->unit_id == $product->unit_pak) {
                        if ($warehouse && $warehouse->isOutOfTown) {
                            if ($product->pak_to_eceran <= 0) {
                                Log::error('DivisionByZeroError: Product ID ' . $product->id . ' has pak_to_eceran = ' . $product->pak_to_eceran . ' (Product: ' . $product->name . ')');
                                throw new \DivisionByZeroError('Product ' . $product->name . ' (ID: ' . $product->id . ') has invalid pak_to_eceran value: ' . $product->pak_to_eceran);
                            }
                            $product->lastest_price_eceran_out_of_town = $cart->price_unit / $product->pak_to_eceran;
                        } else {
                            if ($product->pak_to_eceran <= 0) {
                                Log::error('DivisionByZeroError: Product ID ' . $product->id . ' has pak_to_eceran = ' . $product->pak_to_eceran . ' (Product: ' . $product->name . ')');
                                throw new \DivisionByZeroError('Product ' . $product->name . ' (ID: ' . $product->id . ') has invalid pak_to_eceran value: ' . $product->pak_to_eceran);
                            }
                            $product->lastest_price_eceran = $cart->price_unit / $product->pak_to_eceran;
                        }
                    } elseif ($cart->unit_id == $product->unit_eceran) {
                        if ($warehouse && $warehouse->isOutOfTown) {
                            $product->lastest_price_eceran_out_of_town = $cart->price_unit;
                        } else {
                            $product->lastest_price_eceran = $cart->price_unit;
                        }
                    }
                } catch (\DivisionByZeroError $e) {
                    Log::error('DivisionByZeroError in PurchaseController::store: ' . $e->getMessage());
                    throw $e;
                }
                $product->update();

                // update inventory
                $inventory = Inventory::where('warehouse_id', auth()->user()->warehouse_id)
                    ->where('product_id', $cart->product_id)
                    ->first();

                // check quantity is dus, pak, or eceran
                $quantity = 0;
                if ($cart->unit_id == $product->unit_dus) {
                    $quantity = $cart->quantity * $product->dus_to_eceran;
                } elseif ($cart->unit_id == $product->unit_pak) {
                    $quantity = $cart->quantity * $product->pak_to_eceran;
                } elseif ($cart->unit_id == $product->unit_eceran) {
                    $quantity = $cart->quantity * 1;
                }

                if ($inventory) {
                    $inventory->quantity += $quantity;
                    $inventory->update();
                } else {
                    Inventory::create([
                        'warehouse_id' => auth()->user()->warehouse_id,
                        'product_id' => $cart->product_id,
                        'quantity' => $quantity,
                    ]);
                }

                $unit = Unit::find($cart->unit_id);

                if ($cart->unit_id == $product->unit_dus) {
                    $unitType = 'DUS';
                } elseif ($cart->unit_id == $product->unit_pak) {
                    $unitType = 'PAK';
                } elseif ($cart->unit_id == $product->unit_eceran) {
                    $unitType = 'ECERAN';
                }

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
                    'description' => 'Pembelian ' . $request->order_number,
                ]);
            }

            // clear the cart
            PurchaseCart::where('user_id', auth()->id())->delete();

            // Fix: Check if we have any payment amount, not just the generic pay field


            if ($pay > 0) {


                if ($paymentMethod) {
                    // Change back to using handlePurchasePayment
                    $this->cashflowService->handlePurchasePayment(
                        warehouseId: auth()->user()->warehouse_id,
                        orderNumber: $request->order_number,
                        supplierName: $supplier->name,
                        paymentMethod: $paymentMethod,
                        cash: $cash,
                        transfer: $transfer,
                        grandTotal: $grandTotal  // Pass grand total
                    );
                } else {
                }
            } else {
            }

            return redirect()->route('pembelian.index')->with('success', 'Pembelian berhasil ditambahkan');
        } catch (\Exception $e) {
            // show the error message
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $userRoles = auth()->user()->getRoleNames();

        $query = Purchase::with([
            'supplier',
            'warehouse',
            'details.product.unit_dus',
            'details.product.unit_pak',
            'details.product.unit_eceran',
            'details.unit'
        ]);

        // Apply warehouse filter for non-master users
        if ($userRoles[0] != 'master') {
            $query->where('warehouse_id', auth()->user()->warehouse_id);
        }

        $purchase = $query->find($id);

        if (!$purchase) {
            return response()->json(['error' => 'Data pembelian tidak ditemukan atau Anda tidak memiliki akses'], 404);
        }

        // If 'details_only' parameter is present, return only details array
        if ($request->has('details_only')) {
            return response()->json($purchase->details);
        }

        // Otherwise, return full purchase object for debt detail modal
        return response()->json($purchase);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // Optimized query with only necessary relationships
        $purchase = Purchase::with([
            'details.product:id,name,price_sell_dus,unit_dus,unit_pak,unit_eceran,dus_to_eceran,pak_to_eceran',
            'details.unit:id,name',
            'supplier:id,name',
            'warehouse:id,name'
        ])->findOrFail($id);

        // Only get suppliers for the dropdown
        $suppliers = Supplier::select('id', 'name')->orderBy('name', 'asc')->get();

        // Get all active products with their units
        $products = Product::select(
            'id',
            'name',
            'price_sell_dus',
            'unit_dus',
            'unit_pak',
            'unit_eceran'
        )
            ->where('isShow', true)
            ->orderBy('name', 'asc')
            ->get();

        // Get all units for the select dropdowns
        $units = Unit::select('id', 'name')->orderBy('name', 'asc')->get();

        // Pre-build unit options for better performance
        $unitOptions = $units->mapWithKeys(function ($unit) {
            return [$unit->id => $unit->name];
        });

        return view('pages.purchase.edit', compact('purchase', 'suppliers', 'products', 'unitOptions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            // 1. Get original purchase details for reversal
            $purchase = Purchase::with(['details.product', 'details.unit'])->findOrFail($id);

            $product_ids = $request->input('product_id', []);
            $unit_ids = $request->input('unit_id', []);
            $quantities = $request->input('qty', []);
            $discount_fixes = $request->input('discount_fix', []);
            $discount_percents = $request->input('discount_percent', []);
            $price_units = $request->input('price_unit', []);
            $tax = $request->tax ?? 0;

            // Validate inputs
            foreach ($quantities as $qty) {
                if ($qty <= 0) {
                    throw new \Exception('Quantity must be greater than 0');
                }
            }

            // 2. Calculate new subtotal
            $subtotal = $this->calculateSubtotal($product_ids, $quantities, $price_units, $discount_fixes, $discount_percents);
            $grand_total = $subtotal + ($subtotal * $tax / 100);

            // 3. For each detail, reverse old inventory and create new
            foreach ($purchase->details as $index => $detail) {
                // Reverse old inventory
                $this->reverseInventoryChange($detail, $purchase->warehouse_id);

                // Format price for new inventory
                $formattedPriceUnit = $price_units[$index];
                $numericPriceUnit = (int)str_replace(['Rp. ', '.', ','], '', $formattedPriceUnit);

                // Apply new inventory
                $this->applyNewInventoryChange(
                    $product_ids[$index],
                    $unit_ids[$index],
                    $quantities[$index],
                    $purchase->warehouse_id,
                    $purchase->order_number,
                    $numericPriceUnit
                );
            }

            // 4. Update purchase record
            $purchase->update([
                'invoice' => $request->invoice,
                'supplier_id' => $request->supplier_id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'grand_total' => $grand_total
            ]);

            // 5. Update purchase details
            $this->updatePurchaseDetails($purchase, $product_ids, $unit_ids, $quantities, $price_units, $discount_fixes, $discount_percents);

            DB::commit();
            return redirect()->route('pembelian.index')->with('success', 'Pembelian berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal mengupdate pembelian: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $purchase = Purchase::with(['details.product', 'details.unit'])->find($id);

            if (!$purchase) {
                return redirect()->route('pembelian.index')->with('error', 'Data pembelian tidak ditemukan');
            }

            // Decrease stock for each purchase detail
            foreach ($purchase->details as $detail) {
                $this->reverseInventoryChange($detail, $purchase->warehouse_id, true);
            }

            // Delete the purchase (this will also cascade delete the details)
            $purchase->delete();

            DB::commit();
            $message = "Pembelian berhasil dihapus";

            return redirect()->route('pembelian.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal menghapus pembelian: ' . $e->getMessage()]);
        }
    }

    public function addCart(Request $request)
    {
        $userId = auth()->id();
        $requests = $request->input('requests');

        foreach ($requests as $inputRequest) {
            $productId = $inputRequest['product_id'];

            // Process quantity_dus if it exists
            if (isset($inputRequest['quantity_dus']) && $inputRequest['quantity_dus']) {
                $this->processCartItem($userId, $productId, $inputRequest['quantity_dus'], $inputRequest['unit_dus'], $inputRequest['price_dus'], $inputRequest['discount_fix_dus'] ?? 0, $inputRequest['discount_percent_dus'] ?? 0);
            }

            // Process quantity_pak if it exists
            if (isset($inputRequest['quantity_pak']) && $inputRequest['quantity_pak']) {
                $this->processCartItem($userId, $productId, $inputRequest['quantity_pak'], $inputRequest['unit_pak'], $inputRequest['price_pak'], $inputRequest['discount_fix_pak'] ?? 0, $inputRequest['discount_percent_pak'] ?? 0);
            }

            // Process quantity_eceran if it exists
            if (isset($inputRequest['quantity_eceran']) && $inputRequest['quantity_eceran']) {
                $this->processCartItem($userId, $productId, $inputRequest['quantity_eceran'], $inputRequest['unit_eceran'], $inputRequest['price_eceran'], $inputRequest['discount_fix_eceran'] ?? 0, $inputRequest['discount_percent_eceran'] ?? 0);
            }
        }

        return redirect()->back();
    }

    private function processCartItem($userId, $productId, $quantity, $unitId, $price, $discountFix, $discountPercent)
    {
        $totalPrice = 0;

        // calculated total price if discount_fix or discount_percent exists or both exists
        if ($discountFix && $discountPercent) {
            $totalPrice = ($price * $quantity) - $discountFix - ($price * $quantity * $discountPercent / 100);
        } elseif ($discountFix) {
            $totalPrice = ($price * $quantity) - $discountFix;
        } elseif ($discountPercent) {
            $totalPrice = ($price * $quantity) - ($price * $quantity * $discountPercent / 100);
        } else {
            $totalPrice = $price * $quantity;
        }

        $existingCart = PurchaseCart::where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($existingCart) {
            $existingCart->quantity += $quantity;
            $existingCart->update();
        } else {
            PurchaseCart::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'discount_fix' => $discountFix,
                'discount_percent' => $discountPercent,
                'price_unit' => $totalPrice / $quantity,
                'total_price' => $totalPrice,
            ]);
        }
    }

    public function destroyCart($id)
    {
        $purchaseCart = PurchaseCart::find($id);
        $purchaseCart->delete();

        return redirect()->back();
    }

    public function debt()
    {
        $warehouses = Warehouse::all();
        $users = User::all();
        return view('pages.purchase.debt', compact('warehouses', 'users'));
    }

    public function dataDebt(Request $request)
    {
        $userRoles = auth()->user()->getRoleNames();
        $user_id = $request->input('user_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $warehouse = $request->input('warehouse');

        if ($userRoles->first() == 'master') {
            $purchases = Purchase::with('supplier', 'treasury', 'warehouse')
                ->where('status', 'hutang');
        } else {
            $purchases = Purchase::with('supplier', 'treasury', 'warehouse')
                ->where('warehouse_id', auth()->user()->warehouse_id)
                ->where('status', 'hutang');
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

        $purchases = $purchases->orderBy('id', 'desc')->get();

        return response()->json($purchases);
    }

    public function payDebtPage(string $id)
    {
        $debt = Purchase::with('supplier', 'treasury', 'warehouse')->find($id);
        $purchaseReturs = PurchaseRetur::with(['details' => function ($query) {
            $query->where('status', '!=', 'clearance')
                ->orWhereNull('status');
        }, 'details.product', 'details.unit'])
            ->whereHas('purchase', function ($query) use ($debt) {
                $query->where('supplier_id', $debt->supplier_id);
            })
            ->whereHas('details', function ($query) {
                $query->where('status', '!=', 'clearance')
                    ->orWhereNull('status');
            })
            ->get();

        return view('pages.purchase.payDebt', compact('debt', 'purchaseReturs'));
    }

    public function payDebt(Request $request)
    {
        // Sanitize the inputs
        $retur = intval(str_replace(',', '', $request->input('retur')));
        $potongan = intval(str_replace(',', '', $request->input('potongan')));
        $bayarHutang = intval(str_replace(',', '', $request->input('bayar_hutang')));
        $selectedReturs = array_map('intval', $request->input('selected_returs', []));

        DB::beginTransaction();
        try {
            // Fetch debt
            $debt = Purchase::with('supplier')->find($request->debt_id);
            if (!$debt) {
                return redirect()->back()->withErrors('Debt not found.');
            }

            // Update grand total and payment
            $debt->grand_total -= $retur;
            $debt->pay += $bayarHutang;

            // Update status to 'lunas' if fully paid
            if ($debt->pay >= $debt->grand_total) {
                $debt->status = 'lunas';
            }

            $debt->save();

            // Update selected return products
            $this->updateSelectedReturs($selectedReturs);

            // Create cash flow records
            $this->createCashflow($debt, $bayarHutang, $potongan, $request->payment_method);

            DB::commit();
            return redirect()->route('hutang')->withSuccess('Berhasil bayar hutang');
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->withErrors($th->getMessage());
        }
    }

    private function updateSelectedReturs(array $selectedReturs)
    {
        foreach ($selectedReturs as $returProduct) {
            $purchaseReturs = PurchaseReturDetail::find($returProduct);
            if ($purchaseReturs) {
                $purchaseReturs->status = 'clearance';
                $purchaseReturs->save();
            } else {
                return redirect()->back()->withErrors("Retur product with ID {$returProduct} not found.");
            }
        }
    }

    private function createCashflow($debt, $bayarHutang, $potongan, $paymentMethod)
    {
        // Map payment method ID to string
        $paymentMethodString = $this->mapPaymentMethod($paymentMethod);

        // Handle debt payment using service
        $this->cashflowService->handleDebtPayment(
            warehouseId: $debt->warehouse_id,
            orderNumber: $debt->order_number,
            supplierName: $debt->supplier->name,
            bayarHutang: $bayarHutang,
            potongan: $potongan,
            paymentMethod: $paymentMethodString
        );
    }

    /**
     * Map payment method ID to string representation
     */
    private function mapPaymentMethod($paymentMethodId)
    {
        switch ($paymentMethodId) {
            case 1:
                return 'kas kecil';
            case 2:
                return 'kas besar';
            case 'transfer':
                return 'transfer';
            case 'cash':
                return 'cash';
            default:
                return 'kas kecil'; // Default fallback
        }
    }

    private function convertToBaseUnit($quantity, $unitId, $product)
    {
        if ($unitId == $product->unit_dus) {
            return $quantity * $product->dus_to_eceran;
        } elseif ($unitId == $product->unit_pak) {
            return $quantity * $product->pak_to_eceran;
        }
        return $quantity; // Already in eceran
    }

    private function getUnitType($unitId, $product)
    {
        if ($unitId == $product->unit_dus) {
            return 'DUS';
        } elseif ($unitId == $product->unit_pak) {
            return 'PAK';
        }
        return 'ECERAN';
    }

    private function reverseInventoryChange($detail, $warehouseId, $isDeletion = false)
    {
        // Convert quantity to base unit (eceran)
        $baseQuantity = $this->convertToBaseUnit($detail->quantity, $detail->unit_id, $detail->product);

        // Update inventory
        $inventory = Inventory::where('warehouse_id', $warehouseId)
            ->where('product_id', $detail->product_id)
            ->first();

        if ($inventory) {
            $inventory->quantity -= $baseQuantity;
            $inventory->save();
        }

        // Create reversal product report with appropriate type based on context
        $type = $isDeletion ? 'PEMBELIAN_HAPUS' : 'PEMBELIAN_EDIT_REVERSAL';
        $description = $isDeletion ?
            'Hapus Pembelian ' . $detail->purchase->order_number :
            'Reversal Pembelian Edit ' . $detail->purchase->order_number;

        ProductReport::create([
            'product_id' => $detail->product_id,
            'warehouse_id' => $warehouseId,
            'user_id' => auth()->id(),
            'unit' => $detail->unit->name,
            'unit_type' => $this->getUnitType($detail->unit_id, $detail->product),
            'qty' => -$detail->quantity, // Negative to indicate reversal
            'price' => $detail->price_unit,
            'for' => 'KELUAR',
            'type' => $type,
            'description' => $description
        ]);
    }

    private function applyNewInventoryChange($productId, $unitId, $quantity, $warehouseId, $orderNumber, $priceUnit)
    {
        $product = Product::findOrFail($productId);
        $unit = Unit::findOrFail($unitId);

        // Convert quantity to base unit (eceran)
        $baseQuantity = $this->convertToBaseUnit($quantity, $unitId, $product);

        // Update inventory
        $inventory = Inventory::firstOrCreate(
            ['warehouse_id' => $warehouseId, 'product_id' => $productId],
            ['quantity' => 0]
        );

        $inventory->quantity += $baseQuantity;
        $inventory->save();

        // Create new product report
        ProductReport::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'user_id' => auth()->id(),
            'unit' => $unit->name,
            'unit_type' => $this->getUnitType($unitId, $product),
            'qty' => $quantity,
            'price' => $priceUnit,
            'for' => 'MASUK',
            'type' => 'PEMBELIAN_EDIT',
            'description' => 'Update Pembelian ' . $orderNumber
        ]);
    }

    private function calculateSubtotal($productIds, $quantities, $priceUnits, $discountFixes, $discountPercents)
    {
        $subtotal = 0;
        foreach ($productIds as $key => $productId) {
            $formattedPriceUnit = $priceUnits[$key];
            $numericPriceUnit = (int)str_replace(['Rp. ', '.', ','], '', $formattedPriceUnit);

            if (isset($quantities[$key]) && isset($discountFixes[$key]) && isset($discountPercents[$key])) {
                $subtotal += ($numericPriceUnit - $discountFixes[$key]) * (1 - $discountPercents[$key] / 100) * $quantities[$key];
            }
        }
        return $subtotal;
    }

    private function updatePurchaseDetails($purchase, $productIds, $unitIds, $quantities, $priceUnits, $discountFixes, $discountPercents)
    {
        foreach ($purchase->details as $key => $detail) {
            $numericPriceUnit = (int)str_replace(['Rp. ', '.', ','], '', $priceUnits[$key]);

            $detail->update([
                'product_id' => $productIds[$key],
                'unit_id' => $unitIds[$key],
                'quantity' => $quantities[$key],
                'discount_fix' => $discountFixes[$key],
                'discount_percent' => $discountPercents[$key],
                'price_unit' => $numericPriceUnit,
                'total_price' => ($numericPriceUnit - $discountFixes[$key]) * (1 - $discountPercents[$key] / 100) * $quantities[$key]
            ]);
        }
    }
}
