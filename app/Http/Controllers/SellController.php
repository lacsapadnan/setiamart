<?php

namespace App\Http\Controllers;

use App\Exports\SalesExport;
use App\Http\Requests\SellPayCreditRequest;
use App\Http\Requests\SellUpdateCartQuantityRequest;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellCart;
use App\Models\SellDetail;
use App\Models\SellRetur;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SellCartService;
use App\Services\SellCreditService;
use App\Services\SellQueryService;
use App\Services\SellService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;

class SellController extends Controller
{
    public function __construct(
        protected SellService $sellService,
        protected SellCartService $cartService,
        protected SellCreditService $creditService,
        protected SellQueryService $queryService,
    ) {}

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
        $user = auth()->user();
        $isMaster = $user->getRoleNames()[0] === 'master';

        return $this->queryService->data(
            [
                'user_id' => $request->input('user_id'),
                'from_date' => $request->input('from_date') ?? date('Y-m-d'),
                'to_date' => $request->input('to_date') ?? date('Y-m-d'),
                'warehouse' => $request->input('warehouse'),
                'search' => $request->input('search'),
            ],
            $isMaster,
            $user->id,
            $user->warehouse_id,
        );
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
        $orderNumber = 'PJ-'.$today.'-'.$warehouseId.auth()->id().'-'.$formattedOrderNumber;
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
        $result = $this->sellService->store($request->all());

        if (! $result['ok']) {
            $redirect = redirect()->back();
            if ($result['with_input'] ?? false) {
                $redirect = $redirect->withInput();
            }

            return $redirect->withErrors($result['error']);
        }

        if ($result['type'] === 'draft') {
            return redirect()->route('penjualan.index');
        }

        try {
            // Add a small delay to ensure database operations are fully committed
            sleep(1);

            $printUrl = route('penjualan.print', $result['sell_id']);
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
                $key = $returDetail->product_id.'_'.$returDetail->unit_id;
                if ($returnedItems->has($key)) {
                    $returnedItems[$key]['qty'] += $returDetail->qty;
                } else {
                    $returnedItems[$key] = [
                        'product_id' => $returDetail->product_id,
                        'unit_id' => $returDetail->unit_id,
                        'product_name' => $returDetail->product->name,
                        'unit_name' => $returDetail->unit->name,
                        'qty' => $returDetail->qty,
                        'price' => $returDetail->price,
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
        $pdf->save('receipt.pdf');

        // Convert the PDF to an image using Imagick
        $imagick = new \Imagick;
        $imagick->readImage('receipt.pdf[0]'); // Read the first page of the PDF
        $imagick->setImageFormat('png');
        $imagick->writeImage('receipt.png');

        $connector = new FilePrintConnector('php://stdout');
        $printer = new Printer($connector);

        // Load the image
        $img = EscposImage::load('receipt.png', false);

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
        $user = User::role('master')
            ->where('id', $request->user_id)
            ->first();

        if (! $user) {
            return response()->json(['status' => 'failed']);
        }

        if (password_verify($request->password, $user->password)) {
            session([
                'piutang_authorization' => now()->timestamp,
                'piutang_authorized_by' => $user->id,
            ]);

            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'failed']);
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
            'details.unit',
        ]);

        // Apply warehouse filter for non-master users
        if ($userRoles[0] != 'master') {
            $query->where('warehouse_id', auth()->user()->warehouse_id);
        }

        $sell = $query->find($id);

        if (! $sell) {
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
        $result = $this->sellService->destroy($id);

        if (! $result['ok']) {
            if (request()->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], $result['status']);
            }

            return redirect()->back()->with('error', $result['message']);
        }

        // For AJAX requests, return JSON to avoid redirect method-mismatch issues
        if (request()->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
            ]);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    public function addCart(Request $request)
    {
        $result = $this->cartService->addCart($request->input('requests', []));

        if (! $result['ok']) {
            if (isset($result['errors'])) {
                return response()->json(['errors' => $result['errors']], $result['status']);
            }

            return response()->json(['error' => $result['error']], $result['status']);
        }

        return response()->json(['success' => 'Items added to cart successfully.'], 200);
    }

    public function destroyCart(Request $request, $id)
    {
        $result = $this->cartService->destroyCart($id, $request->product_id);

        if (! $result['ok']) {
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], $result['status']);
            }

            return redirect()->back()->with('error', $result['message']);
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Item berhasil dihapus dari keranjang',
            ]);
        }

        return redirect()->back()->with('success', 'Item berhasil dihapus dari keranjang');
    }

    public function updateCartQuantity(SellUpdateCartQuantityRequest $request, $id)
    {
        $result = $this->cartService->updateCartQuantity($id, $request->quantity);

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully',
            'data' => $result['data'],
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
                $key = $returDetail->product_id.'_'.$returDetail->unit_id;
                if ($returnedItems->has($key)) {
                    $returnedItems[$key]['qty'] += $returDetail->qty;
                } else {
                    $returnedItems[$key] = [
                        'product_id' => $returDetail->product_id,
                        'unit_id' => $returDetail->unit_id,
                        'product_name' => $returDetail->product->name,
                        'unit_name' => $returDetail->unit->name,
                        'qty' => $returDetail->qty,
                        'price' => $returDetail->price,
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
            'Content-Disposition' => 'inline; filename="Transaksi-'.$sell->order_number.'.pdf"',
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
        $user = auth()->user();
        $isMaster = $user->getRoleNames()[0] == 'master';

        return response()->json($this->queryService->dataCredit(
            [
                'user_id' => $request->input('user_id'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'warehouse' => $request->input('warehouse'),
            ],
            $isMaster,
            $user->warehouse_id,
        ));
    }

    public function payCredit(SellPayCreditRequest $request)
    {
        $result = $this->creditService->payCredit([
            'sell_id' => $request->validated('sell_id'),
            'payment' => $request->validated('payment'),
            'potongan' => $request->input('potongan'),
            'pay' => $request->input('pay'),
            'pay_credit_cash' => $request->input('pay_credit_cash'),
            'pay_credit_transfer' => $request->input('pay_credit_transfer'),
            'keterangan' => $request->input('keterangan'),
        ]);

        if (! $result['ok']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
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
        if (! $isMaster) {
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

            return Excel::download(new SalesExport($filters), $filename);
        } catch (\Exception $e) {
            report($e);
            Log::error('Sales export failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('<h1>Export Failed</h1><p>Terjadi kesalahan saat export penjualan. Silakan coba lagi.</p>', 500);
        }
    }
}
