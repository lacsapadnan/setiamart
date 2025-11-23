<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Sell;
use App\Models\SellCart;
use App\Models\SellCartDraft;
use App\Models\SellDetail;
use App\Models\SellRetur;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashflowService;
use App\Exports\SalesExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\ImagickEscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;
use DataTables;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SellController extends Controller
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
        return view('pages.sell.index', compact('masters', 'warehouses', 'users'));
    }

    public function data(Request $request)
    {
        try {
            $role = auth()->user()->getRoleNames();
            $user_id = $request->input('user_id');
            $fromDate = $request->input('from_date') ?? date('Y-m-d');
            $toDate = $request->input('to_date') ?? date('Y-m-d');
            $warehouse = $request->input('warehouse');
            $export = $request->input('export');
            $search = $request->input('search');

            $query = Sell::with([
                'warehouse',
                'customer',
                'cashier',
                'details.product.unit_dus',
                'details.product.unit_pak',
                'details.product.unit_eceran',
                'details' => function ($query) {
                    $query->whereNotNull('unit_id');
                },
                'details.unit'
            ])
                ->where('status', '!=', 'draft')
                ->orderBy('id', 'desc');

            if ($role[0] !== 'master') {
                $query->where('warehouse_id', auth()->user()->warehouse_id)
                    ->where('cashier_id', auth()->id());
            }

            if ($warehouse) {
                $query->where('warehouse_id', $warehouse);
            }

            if ($user_id) {
                $query->where('cashier_id', $user_id);
            }

            if ($fromDate && $toDate) {
                $endDate = \Carbon\Carbon::parse($toDate)->endOfDay();
                $query->whereBetween('created_at', [$fromDate, $endDate]);
            }

            if (!empty($search) && !empty($search['value'])) {
                $searchValue = $search['value'];
                $query->where(function ($q) use ($searchValue) {
                    $q->where('order_number', 'like', "%{$searchValue}%")
                        ->orWhereHas('customer', function ($q) use ($searchValue) {
                            $q->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('warehouse', function ($q) use ($searchValue) {
                            $q->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('cashier', function ($q) use ($searchValue) {
                            $q->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhere('payment_method', 'like', "%{$searchValue}%")
                        ->orWhere('status', 'like', "%{$searchValue}%");
                });
            }

            return DataTables::of($query)
                ->make(true);
        } catch (\Throwable $e) {
            Log::error('SellController data method error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage(),
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $inventories = Inventory::with(['product' => function ($query) {
            $query->where('isShow', true)
                ->with(['unit_dus', 'unit_pak', 'unit_eceran']);
        }])
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->get();

        $products = Product::with(['unit_dus', 'unit_pak', 'unit_eceran'])
            ->where('isShow', true)
            ->get();
        $customers = Customer::all();
        $today = date('Ymd');
        $year = substr($today, 2, 2);
        $today = substr($today, 2);
        $warehouseId = auth()->user()->warehouse_id;
        $userId = auth()->id();

        $lastOrder = Sell::where('cashier_id', $userId)
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
        $orderNumber = "PJ-" . $today . "-" . $warehouseId . auth()->id() . "-" . $formattedOrderNumber;
        $cart = SellCart::with('product', 'unit')->orderBy('id', 'desc')
            ->where('cashier_id', auth()->id())
            ->get();
        $subtotal = 0;
        foreach ($cart as $c) {
            $subtotal += ($c->price * $c->quantity) - $c->diskon;
        }
        $masters = User::role('master')->get();
        return view('pages.sell.create', compact('inventories', 'products', 'cart', 'subtotal', 'customers', 'orderNumber', 'masters'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $sellCart = SellCart::with(['product', 'unit'])->where('cashier_id', auth()->id())->get();

        // Validate cart has items if not a draft
        if ($sellCart->isEmpty() && $request->status !== 'draft') {
            return redirect()->back()->withInput()->withErrors('Keranjang penjualan kosong');
        }

        $transfer = (int)str_replace(',', '', $request->transfer ?? 0);
        $cash = (int)str_replace(',', '', $request->cash ?? 0);
        $grandtotal = (int)preg_replace('/[,.]/', '', $request->grand_total);
        $pay = $transfer + $cash;

        if ($request->status === 'draft') {
            $status = 'draft';
        } elseif ($pay < $grandtotal) {
            $status = 'piutang';
        } else {
            $status = 'lunas';
        }

        $grandTotalValid = 0;
        foreach ($sellCart as $sc) {
            $grandTotalValid += (int)preg_replace('/[,.]/', '', ($sc->price * $sc->quantity) - $sc->diskon);
        }

        if ($grandtotal !== $grandTotalValid) {
            return redirect()->back()->withInput()->withErrors('Terjadi Kesalahan Kalkulasi Total');
        }

        try {
            DB::beginTransaction();

            // Determine payment method if not provided
            $paymentMethod = $request->payment_method;
            if (empty($paymentMethod)) {
                if ($cash > 0 && $transfer > 0) {
                    $paymentMethod = 'split';
                } elseif ($cash > 0) {
                    $paymentMethod = 'cash';
                } elseif ($transfer > 0) {
                    $paymentMethod = 'transfer';
                }
            }

            $sell = Sell::create([
                'cashier_id' => auth()->id(),
                'warehouse_id' => auth()->user()->warehouse_id,
                'order_number' => $request->order_number,
                'customer_id' => $request->customer,
                'subtotal' => preg_replace('/[,.]/', '', $request->subtotal),
                'grand_total' => preg_replace('/[,.]/', '', $request->grand_total),
                'cash' => preg_replace('/[,.]/', '', $cash),
                'transfer' => preg_replace('/[,.]/', '', $transfer),
                'pay' => preg_replace('/[,.]/', '', $pay),
                'change' => preg_replace('/[,.]/', '', $request->change ?? 0),
                'transaction_date' => Carbon::createFromFormat('d/m/Y', $request->transaction_date)->format('Y-m-d'),
                'payment_method' => $paymentMethod,
                'status' => $status,
            ]);

            $customer = Customer::find($request->customer);

            if ($request->status == 'draft') {
                $draftData = [];
                foreach ($sellCart as $sc) {
                    $draftData[] = [
                        'sell_id' => $sell->id,
                        'cashier_id' => auth()->id(),
                        'product_id' => $sc->product_id,
                        'unit_id' => $sc->unit_id,
                        'quantity' => $sc->quantity,
                        'price' => $sc->price,
                        'diskon' => $sc->diskon,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                SellCartDraft::insert($draftData);

                // Clear the cart after creating draft
                SellCart::where('cashier_id', auth()->id())->delete();
            } else {
                // Prepare bulk insert data for sell details and product reports
                $sellDetailsData = [];
                $productReportsData = [];
                $now = now();

                foreach ($sellCart as $sc) {
                    // Prepare sell details data
                    $sellDetailsData[] = [
                        'sell_id' => $sell->id,
                        'product_id' => $sc->product_id,
                        'unit_id' => $sc->unit_id,
                        'quantity' => $sc->quantity,
                        'price' => $sc->price,
                        'diskon' => $sc->diskon,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Use already loaded relationships from eager loading
                    $unit = $sc->unit;
                    $product = $sc->product;

                    if ($sc->unit_id == $product->unit_dus) {
                        $unitType = 'DUS';
                    } elseif ($sc->unit_id == $product->unit_pak) {
                        $unitType = 'PAK';
                    } elseif ($sc->unit_id == $product->unit_eceran) {
                        $unitType = 'ECERAN';
                    }

                    // Prepare product reports data
                    $productReportsData[] = [
                        'product_id' => $sc->product_id,
                        'warehouse_id' => auth()->user()->warehouse_id,
                        'user_id' => auth()->id(),
                        'customer_id' => $request->customer,
                        'unit' => $unit->name,
                        'unit_type' => $unitType,
                        'qty' => $sc->quantity,
                        'price' => ($sc->price * $sc->quantity) - $sc->diskon,
                        'for' => 'KELUAR',
                        'type' => 'PENJUALAN',
                        'description' => 'Penjualan ' . $sell->order_number,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Bulk insert sell details
                SellDetail::insert($sellDetailsData);

                // Bulk insert product reports
                ProductReport::insert($productReportsData);

                // Only create cashflow if there's actual payment
                if ($pay > 0) {
                    // Use the determined payment method (should never be empty now)
                    if ($paymentMethod) {
                        $this->cashflowService->handleSalePayment(
                            warehouseId: auth()->user()->warehouse_id,
                            orderNumber: $sell->order_number,
                            customerName: $customer->name,
                            paymentMethod: $paymentMethod,
                            cash: $cash,
                            transfer: $transfer,
                            change: $sell->change
                        );
                    }
                }

                // Move cart deletion to end of transaction
                SellCart::where('cashier_id', auth()->id())->delete();
            }

            DB::commit();

            // Verify sell details were created if not a draft
            if ($request->status !== 'draft') {
                $detailCount = SellDetail::where('sell_id', $sell->id)->count();
                if ($detailCount === 0) {
                    return redirect()->back()->withInput()
                        ->withErrors('Terjadi kesalahan: Detail penjualan tidak tersimpan');
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
                return redirect()->route('penjualan.index');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()
                ->withErrors('Terjadi kesalahan saat menyimpan transaksi: ' . $e->getMessage());
        }
    }

    public function printReceipt($id)
    {
        $sell = Sell::with('warehouse', 'customer', 'cashier')->find($id);
        $details = SellDetail::with('product', 'unit')->where('sell_id', $id)->get();

        // Get return data for this sale
        $sellReturs = SellRetur::with(['sellReturDetails.product', 'sellReturDetails.unit'])
            ->where('sell_id', $id)
            ->get();

        // Create a collection of returned items with their quantities
        $returnedItems = collect();
        foreach ($sellReturs as $sellRetur) {
            foreach ($sellRetur->sellReturDetails as $returDetail) {
                $key = $returDetail->product_id . '_' . $returDetail->unit_id;
                if ($returnedItems->has($key)) {
                    $returnedItems[$key]['qty'] += $returDetail->qty;
                } else {
                    $returnedItems[$key] = [
                        'product_id' => $returDetail->product_id,
                        'unit_id' => $returDetail->unit_id,
                        'product_name' => $returDetail->product->name,
                        'unit_name' => $returDetail->unit->name,
                        'qty' => $returDetail->qty,
                        'price' => $returDetail->price
                    ];
                }
            }
        }

        $totalQuantity = 0;
        $totalQuantity += $details->count();

        // Get payment settings
        $paymentSetting = PaymentSetting::first();

        $pdf = Pdf::loadView('pages.sell.print', compact('sell', 'details', 'totalQuantity', 'returnedItems', 'paymentSetting'));

        // Save the PDF to a file
        $pdf->save("receipt.pdf");

        // Convert the PDF to an image using Imagick
        $imagick = new \Imagick();
        $imagick->readImage("receipt.pdf[0]"); // Read the first page of the PDF
        $imagick->setImageFormat('png');
        $imagick->writeImage('receipt.png');

        $connector = new FilePrintConnector("php://stdout");
        $printer = new Printer($connector);

        // Load the image
        $img = EscposImage::load("receipt.png", false);

        // Print the image
        $printer->graphics($img);

        // Close printer
        $printer->close();
    }

    public function checkCustomerStatus(Request $request)
    {
        $customerId = $request->input('customer_id'); // Get the customer ID from the request

        // Check if the customer has 'piutang' status
        $customer = Sell::where('customer_id', $customerId)
            ->where('status', 'piutang')
            ->first();

        if ($customer) {
            return response()->json(['status' => 'piutang']);
        } else {
            // Customer does not have 'piutang' status
            return response()->json(['status' => 'not_piutang']);
        }
    }

    public function validateMasterPassword(Request $request)
    {
        $user = User::where('id', $request->user_id)->first();

        // check the password request is same with user password
        if (password_verify($request->password, $user->password)) {
            return response()->json(['status' => 'success']);
        } else {
            return response()->json(['status' => 'failed']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $userRoles = auth()->user()->getRoleNames();

        $query = Sell::with([
            'warehouse',
            'customer',
            'cashier',
            'details.product.unit_dus',
            'details.product.unit_pak',
            'details.product.unit_eceran',
            'details.unit'
        ]);

        // Apply warehouse filter for non-master users
        if ($userRoles[0] != 'master') {
            $query->where('warehouse_id', auth()->user()->warehouse_id);
        }

        $sell = $query->find($id);

        if (!$sell) {
            return response()->json(['error' => 'Data penjualan tidak ditemukan atau Anda tidak memiliki akses'], 404);
        }

        // If 'details_only' parameter is present, return only details array
        if ($request->has('details_only')) {
            return response()->json($sell->details);
        }

        // Otherwise, return full sell object for credit detail modal
        return response()->json($sell);
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
        $sell = Sell::find($id);

        if (!$sell) {
            if (request()->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data penjualan tidak ditemukan'
                ], 404);
            }
            return redirect()->back()->with('error', 'Data penjualan tidak ditemukan');
        }

        // Delete the sell record
        $sell->delete();

        $message = "Data penjualan berhasil dihapus";

        // For AJAX requests, return JSON to avoid redirect method-mismatch issues
        if (request()->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function addCart(Request $request)
    {
        // $inputRequests = $request->input('requests');
        $requests = $request->input('requests');


        try {
            DB::beginTransaction();

            foreach ($requests as $inputRequest) {
                $productId = $inputRequest['product_id'];

                // Process quantity_dus if it exists
                if (isset($inputRequest['quantity_dus']) && $inputRequest['quantity_dus']) {
                    $this->processCartItem($productId, $inputRequest['quantity_dus'], $inputRequest['unit_dus'], $inputRequest['price_dus'], $inputRequest['diskon_dus'] ?? 0);
                    $this->decreaseInventory($productId, $inputRequest['quantity_dus'], $inputRequest['unit_dus']);
                }

                // Process quantity_pak if it exists
                if (isset($inputRequest['quantity_pak']) && $inputRequest['quantity_pak']) {
                    $this->processCartItem($productId, $inputRequest['quantity_pak'], $inputRequest['unit_pak'], $inputRequest['price_pak'], $inputRequest['diskon_pak'] ?? 0);
                    $this->decreaseInventory($productId, $inputRequest['quantity_pak'], $inputRequest['unit_pak']);
                }

                // Process quantity_eceran if it exists
                if (isset($inputRequest['quantity_eceran']) && $inputRequest['quantity_eceran']) {
                    $this->processCartItem($productId, $inputRequest['quantity_eceran'], $inputRequest['unit_eceran'], $inputRequest['price_eceran'], $inputRequest['diskon_eceran'] ?? 0);
                    $this->decreaseInventory($productId, $inputRequest['quantity_eceran'], $inputRequest['unit_eceran']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to add items to cart.'], 500);
        }

        return response()->json(['success' => 'Items added to cart successfully.'], 200);
    }

    private function processCartItem($productId, $quantity, $unitId, $price, $discount)
    {
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
            $existingCart->quantity += $quantity;
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
        $sellCart = SellCart::where('product_id', $request->product_id)
            ->where('id', $id)
            ->first();

        // Check if cart item exists
        if (!$sellCart) {
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item keranjang tidak ditemukan'
                ], 404);
            }
            return redirect()->back()->with('error', 'Item keranjang tidak ditemukan');
        }

        // Store values before deletion
        $unitId = $sellCart->unit_id;
        $productId = $sellCart->product_id;
        $quantity = $sellCart->quantity;

        // Delete the cart item
        $sellCart->delete();

        // Restore inventory
        $product = Product::find($productId);
        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->first();

        if ($inventory && $product) {
            if ($unitId == $product->unit_dus) {
                $inventory->quantity += $quantity * $product->dus_to_eceran;
            } elseif ($unitId == $product->unit_pak) {
                $inventory->quantity += $quantity * $product->pak_to_eceran;
            } elseif ($unitId == $product->unit_eceran) {
                $inventory->quantity += $quantity;
            }

            $inventory->save();
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item berhasil dihapus dari keranjang'
            ]);
        }

        return redirect()->back()->with('success', 'Item berhasil dihapus dari keranjang');
    }

    public function updateCartQuantity(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $sellCart = SellCart::findOrFail($id);
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

    public function print($id)
    {
        $sell = Sell::with('warehouse', 'customer', 'cashier')->find($id);
        $details = SellDetail::with('product', 'unit')->where('sell_id', $id)->get();

        // Get return data for this sale
        $sellReturs = SellRetur::with(['sellReturDetails.product', 'sellReturDetails.unit'])
            ->where('sell_id', $id)
            ->get();

        // Create a collection of returned items with their quantities
        $returnedItems = collect();
        foreach ($sellReturs as $sellRetur) {
            foreach ($sellRetur->sellReturDetails as $returDetail) {
                $key = $returDetail->product_id . '_' . $returDetail->unit_id;
                if ($returnedItems->has($key)) {
                    $returnedItems[$key]['qty'] += $returDetail->qty;
                } else {
                    $returnedItems[$key] = [
                        'product_id' => $returDetail->product_id,
                        'unit_id' => $returDetail->unit_id,
                        'product_name' => $returDetail->product->name,
                        'unit_name' => $returDetail->unit->name,
                        'qty' => $returDetail->qty,
                        'price' => $returDetail->price
                    ];
                }
            }
        }

        $totalQuantity = 0;
        $totalQuantity += $details->count();

        // Get payment settings
        $paymentSetting = PaymentSetting::first();

        $pdf = Pdf::loadView('pages.sell.print', compact('sell', 'details', 'totalQuantity', 'returnedItems', 'paymentSetting'));
        return response()->stream(function () use ($pdf) {
            echo $pdf->output();
        }, 200, [
            'attachment' => false,
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Transaksi-' . $sell->order_number . '.pdf"'
        ]);
    }

    public function credit()
    {
        $warehouses = Warehouse::with('users')->get();
        $users = User::with('roles', 'warehouse')->get();
        return view('pages.sell.credit', compact('warehouses', 'users'));
    }

    public function dataCredit(Request $request)
    {
        $userRoles = auth()->user()->getRoleNames();
        $user_id = $request->input('user_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $warehouse = $request->input('warehouse');

        $query = Sell::with([
            'warehouse',
            'customer',
            'cashier',
            'details.product.unit_dus',
            'details.product.unit_pak',
            'details.product.unit_eceran',
            'details.unit'
        ])->where('status', 'piutang');

        if ($userRoles[0] != 'master') {
            $query->where('warehouse_id', auth()->user()->warehouse_id);
        }

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse);
        }

        if ($user_id) {
            $query->where('cashier_id', $user_id);
        }

        if ($fromDate && $toDate) {
            $endDate = Carbon::parse($toDate)->endOfDay();
            $query->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        return response()->json($query->get());
    }

    public function payCredit(Request $request)
    {
        $validated = $request->validate([
            'sell_id' => 'required|integer',
            'payment' => 'required|string',
        ]);

        $sell = Sell::with('customer')->find($validated['sell_id']);

        if (!$sell) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sale not found'
            ]);
        }

        $grandTotal = (int) preg_replace('/[,.]/', '', $sell->grand_total);
        $currentPay = (int) preg_replace('/[,.]/', '', $sell->pay);
        $paymentMethod = $validated['payment'];

        // Initialize payment variables
        $payment = 0;
        $paymentCash = 0;
        $paymentTransfer = 0;
        $potongan = (int) preg_replace('/[,.]/', '', $request->potongan ?? 0);

        if ($paymentMethod === 'split') {
            $paymentCash = (int) preg_replace('/[,.]/', '', $request->pay_credit_cash ?? 0);
            $paymentTransfer = (int) preg_replace('/[,.]/', '', $request->pay_credit_transfer ?? 0);
            $payment = $paymentCash + $paymentTransfer;

            // Validate split payment
            if ($paymentCash < 0 || $paymentTransfer < 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pembayaran tidak boleh kurang dari 0'
                ]);
            }
        } else {
            $payment = (int) preg_replace('/[,.]/', '', $request->pay ?? 0);
            if ($payment <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pembayaran tidak boleh 0 atau kurang'
                ]);
            }
        }

        $sisaHutang = $grandTotal - $currentPay;

        // Validate total payment (payment + potongan) against remaining debt
        if ($payment > $sisaHutang) {
            return response()->json([
                'status' => 'error',
                'message' => 'Total pembayaran tidak boleh lebih dari sisa piutang'
            ]);
        }

        $sell->pay = $currentPay + $payment + $potongan;

        if ($sell->pay >= $grandTotal) {
            $sell->status = 'lunas';
        }

        $sell->save();

        // Handle cashflow using service
        $this->cashflowService->handleCreditPayment(
            warehouseId: $sell->warehouse_id,
            orderNumber: $sell->order_number,
            customerName: $sell->customer->name,
            paymentMethod: $paymentMethod,
            payment: $payment,
            paymentCash: $paymentCash,
            paymentTransfer: $paymentTransfer,
            potongan: $potongan,
            keterangan: $request->keterangan ?? ''
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pembayaran piutang berhasil'
        ]);
    }

    /**
     * Stream a synchronous Excel export for sales data
     */
    public function exportExcel(Request $request)
    {
        $role = auth()->user()->getRoleNames();
        $isMaster = isset($role[0]) && $role[0] === 'master';

        // Handle search parameter - can be string or object with value
        $search = $request->input('search');
        if (is_array($search) && isset($search['value'])) {
            $search = $search['value'];
        }

        // Apply same defaults as data() method
        $fromDate = $request->input('from_date') ?? date('Y-m-d');
        $toDate = $request->input('to_date') ?? date('Y-m-d');

        $filters = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'warehouse' => $request->input('warehouse'),
            'user_id' => $request->input('user_id'),
            'search' => $search,
        ];

        // Apply restrictions for non-master users
        if (!$isMaster) {
            $filters['restrict_warehouse_id'] = auth()->user()->warehouse_id;
            $filters['restrict_cashier_id'] = auth()->id();
        }

        // Test query before export
        $export = new SalesExport($filters);
        $query = $export->query();
        $count = $query->count();

        try {
            set_time_limit(600);
            ini_set('memory_limit', '1024M');

            $date = date('Ymd_His');
            $filename = "sales_{$date}.xlsx";

            if ($count > 20000) {
                return response('<h1>Dataset Too Large</h1><p>This export contains ' . number_format($count) . ' records. Please use date filters to reduce the dataset size to under 20,000 records.</p>', 413);
            }

            return Excel::download(new SalesExport($filters), $filename);
        } catch (\Exception $e) {
            Log::error('Sales export failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('<h1>Export Failed</h1><p>Error: ' . $e->getMessage() . '</p><p>Please check the logs for more details.</p>', 500);
        }
    }
}
