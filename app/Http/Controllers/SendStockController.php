<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\SendStock;
use App\Models\SendStockCart;
use App\Models\SendStockDetail;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SendStockController extends Controller
{
    protected $stockTransferService;

    public function __construct(StockTransferService $stockTransferService)
    {
        $this->stockTransferService = $stockTransferService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages.sendStok.index');
    }

    public function data()
    {
        $sendStok = SendStock::with('fromWarehouse', 'toWarehouse', 'user')
            ->completed()
            ->orderBy('id', 'desc')
            ->get();
        
        return response()->json($sendStok);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $warehouses = Warehouse::orderBy('id', 'asc')->get();
        $products = Product::where('isShow', true)->orderBy('id', 'asc')->get();
        $units = Unit::orderBy('id', 'asc')->get();
        $cart = SendStockCart::with('product', 'unit')->where('user_id', auth()->id())->get();
        return view('pages.sendStok.create', compact('warehouses', 'products', 'units', 'cart'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $user = auth()->user();
            $fromWarehouse = $user->warehouse_id;
            $toWarehouse = $request->input('to_warehouse');
            $saveAsDraft = $request->has('save_as_draft');

            // Fetch cart items with products
            $carts = SendStockCart::with('product')->where('user_id', $user->id)->get();

            if ($carts->isEmpty()) {
                return redirect()->back()->with('error', 'Keranjang kosong. Tambahkan produk terlebih dahulu.');
            }

            // If saving as draft, create draft without inventory changes
            if ($saveAsDraft) {
                // Generate send stock number using same format as print function
                $date = now()->format('Ymd');
                $count = SendStock::count() + 1;
                $sendStockNumber = "PS-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);

                $sendStock = SendStock::create([
                    'user_id' => $user->id,
                    'from_warehouse' => $fromWarehouse,
                    'to_warehouse' => $toWarehouse,
                    'status' => 'draft',
                    'send_stock_number' => $sendStockNumber,
                ]);

                $sendStockDetails = [];

                foreach ($carts as $cart) {
                    $sendStockDetails[] = [
                        'send_stock_id' => $sendStock->id,
                        'product_id' => $cart->product_id,
                        'unit_id' => $cart->unit_id,
                        'quantity' => $cart->quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Insert all draft details in one query
                SendStockDetail::insert($sendStockDetails);

                // Clear cart
                SendStockCart::where('user_id', $user->id)->delete();

                DB::commit();
                return redirect()->route('pindah-stok-draft.index')->with('success', 'Draft pindah stok berhasil disimpan.');
            }

            // Fetch all related inventory records in one query (avoid looping queries)
            $productIds = $carts->pluck('product.id');
            $inventories = Inventory::whereIn('product_id', $productIds)
                ->where('warehouse_id', $fromWarehouse)
                ->get()
                ->keyBy('product_id'); // Store in an associative array for quick lookup

            $stockErrors = [];

            foreach ($carts as $cart) {
                $product = $cart->product;
                $unit = $cart->unit_id;
                $quantity = $cart->quantity;

                // Convert quantity to eceran
                $quantityEceran = match ($unit) {
                    $product->unit_dus => (int)$quantity * (int)$product->dus_to_eceran,
                    $product->unit_pak => (int)$quantity * (int)$product->pak_to_eceran,
                    default => (int)$quantity
                };

                // Check available stock
                $fromInventory = $inventories[$product->id] ?? null;

                if (!$fromInventory || $fromInventory->quantity < $quantityEceran) {
                    $stockErrors[] = "Stok tidak mencukupi untuk {$product->name}. Dibutuhkan: $quantityEceran, Tersedia: " . ($fromInventory->quantity ?? 0);
                }
            }

            if (!empty($stockErrors)) {
                DB::rollBack();
                return redirect()->back()->withErrors($stockErrors);
            }

            // Generate send stock number using same format as print function
            $date = now()->format('Ymd');
            $count = SendStock::count() + 1;
            $sendStockNumber = "PS-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sendStock = SendStock::create([
                'user_id' => $user->id,
                'from_warehouse' => $fromWarehouse,
                'to_warehouse' => $toWarehouse,
                'status' => 'completed',
                'completed_at' => now(),
                'send_stock_number' => $sendStockNumber,
            ]);

            $sendStockDetails = [];

            foreach ($carts as $cart) {
                $product = $cart->product;
                $unit = $cart->unit_id;
                $quantity = (int) $cart->quantity; // Cast to int to prevent type error

                $quantityEceran = match ($unit) {
                    $product->unit_dus => $quantity * $product->dus_to_eceran,
                    $product->unit_pak => $quantity * $product->pak_to_eceran,
                    default => $quantity
                };

                // Deduct stock from source warehouse
                Inventory::where('id', $inventories[$product->id]->id)->decrement('quantity', $quantityEceran);

                // Increase stock in destination warehouse (efficient atomic update)
                Inventory::updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $toWarehouse],
                    ['quantity' => DB::raw("quantity + $quantityEceran")]
                );

                // Prepare batch insert data for SendStockDetail
                $sendStockDetails[] = [
                    'send_stock_id' => $sendStock->id,
                    'product_id' => $product->id,
                    'unit_id' => $unit,
                    'quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert all transfer details in one query (batch insert)
            SendStockDetail::insert($sendStockDetails);

            // Create ProductReport entries for stock transfer tracking
            $this->stockTransferService->createStockTransferReports($sendStock, $carts);

            // Clear cart in one query
            SendStockCart::where('user_id', $user->id)->delete();

            DB::commit();
            return redirect()->route('pindah-stok.index')->with('success', 'Stok berhasil dipindahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $sendStockDetail = SendStockDetail::with('product', 'unit',)->where('send_stock_id', $id)->get();
        return response()->json($sendStockDetail);
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
    public function destroy(Request $request, string $id)
    {
        $sendStock = SendStock::findOrFail($id);

        // Fetch related details from SendStockDetail
        $sendStockDetails = SendStockDetail::where('send_stock_id', $id)->get();

        $stockErrors = [];

        // Loop through the details to reverse the stock movement
        foreach ($sendStockDetails as $detail) {
            $product = $detail->product;
            $unit = $detail->unit_id;
            $quantity = (int) $detail->quantity; // Cast to int to prevent type error

            // Convert quantity to eceran
            $quantityEceran = match ($unit) {
                $product->unit_dus => $quantity * $product->dus_to_eceran,
                $product->unit_pak => $quantity * $product->pak_to_eceran,
                default => $quantity
            };

            // Fetch inventories
            $fromInventory = Inventory::where('product_id', $product->id)
                ->where('warehouse_id', $sendStock->from_warehouse)
                ->first();

            $toInventory = Inventory::where('product_id', $product->id)
                ->where('warehouse_id', $sendStock->to_warehouse)
                ->first();

            // Check if there's enough stock in the destination warehouse to decrement
            if (!$toInventory || $toInventory->quantity < $quantityEceran) {
                $stockErrors[] = "Stok tidak mencukupi untuk mengembalikan {$product->name}. Dibutuhkan: $quantityEceran, Tersedia: " . ($toInventory->quantity ?? 0);
            }

            // Revert stock transfer: Increase in source warehouse, Decrease in destination warehouse
            Inventory::where('id', $fromInventory->id)->increment('quantity', $quantityEceran);

            // Decrease stock in destination warehouse
            Inventory::where('id', $toInventory->id)->decrement('quantity', $quantityEceran);
        }

        if (!empty($stockErrors)) {
            return redirect()->back()->withErrors($stockErrors);
        }

        // Delete related ProductReport entries
        $this->stockTransferService->deleteStockTransferReports($sendStock, $sendStockDetails->pluck('product_id')->toArray());

        // Delete the SendStockDetail and SendStock entries
        SendStockDetail::where('send_stock_id', $id)->delete();
        $sendStock->delete();

        return redirect()->route('pindah-stok.index')->with('success', 'Stok berhasil dikembalikan.');
    }

    public function addCart(Request $request)
    {
        try {
            DB::beginTransaction();

            // Check if this is a bulk request (multiple items) or single item
            if ($request->has('requests')) {
                // Handle bulk items
                $requests = $request->input('requests');

                foreach ($requests as $inputRequest) {
                    $productId = $inputRequest['product_id'];

                    // Process quantity_dus if it exists
                    if (isset($inputRequest['quantity_dus']) && $inputRequest['quantity_dus'] > 0) {
                        $this->processCartItem($productId, $inputRequest['quantity_dus'], $inputRequest['unit_dus']);
                    }

                    // Process quantity_pak if it exists
                    if (isset($inputRequest['quantity_pak']) && $inputRequest['quantity_pak'] > 0) {
                        $this->processCartItem($productId, $inputRequest['quantity_pak'], $inputRequest['unit_pak']);
                    }

                    // Process quantity_eceran if it exists
                    if (isset($inputRequest['quantity_eceran']) && $inputRequest['quantity_eceran'] > 0) {
                        $this->processCartItem($productId, $inputRequest['quantity_eceran'], $inputRequest['unit_eceran']);
                    }
                }

                DB::commit();
                return response()->json(['success' => 'Items added to cart successfully.'], 200);
            } else {
                // Handle single item (original format)
                $productId = $request->product_id;
                $product = Product::find($productId);

                if (!$product) {
                    DB::rollBack();
                    return redirect()->back()->with('error', 'Product not found');
                }

                // Process each unit type
                if ($request->has('quantity_dus') && $request->quantity_dus > 0) {
                    $this->processCartItem($productId, $request->quantity_dus, $product->unit_dus);
                }

                if ($request->has('quantity_pak') && $request->quantity_pak > 0) {
                    $this->processCartItem($productId, $request->quantity_pak, $product->unit_pak);
                }

                if ($request->has('quantity_eceran') && $request->quantity_eceran > 0) {
                    $this->processCartItem($productId, $request->quantity_eceran, $product->unit_eceran);
                }

                DB::commit();
                return redirect()->back()->with('success', 'Produk berhasil dimasukan ke keranjang');
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->has('requests')) {
                return response()->json(['error' => 'Failed to add items to cart: ' . $e->getMessage()], 500);
            } else {
                return redirect()->back()->with('error', 'Failed to add item to cart: ' . $e->getMessage());
            }
        }
    }

    private function processCartItem($productId, $quantity, $unitId)
    {
        // Cast to int to prevent type errors
        $quantity = (int) $quantity;

        $existingCart = SendStockCart::where('user_id', auth()->id())
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($existingCart) {
            $existingCart->quantity += $quantity;
            $existingCart->save();
        } else {
            SendStockCart::create([
                'user_id' => auth()->id(),
                'product_id' => $productId,
                'unit_id' => $unitId,
                'quantity' => $quantity,
            ]);
        }
    }

    public function destroyCart($id)
    {
        $cart = SendStockCart::find($id);

        if (!$cart) {
            return redirect()->back()->with('error', 'Item keranjang tidak ditemukan');
        }

        $cart->delete();

        return redirect()->back()->with('success', 'Item berhasil dihapus dari keranjang');
    }

    public function updateCartQuantity(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = SendStockCart::findOrFail($id);
        $cart->quantity = $request->quantity;
        $cart->save();

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully',
            'data' => [
                'quantity' => $cart->quantity
            ]
        ]);
    }

    public function print($id)
    {
        $sendStock = SendStock::with('fromWarehouse', 'toWarehouse')->where('id', $id)->first();
        $sendStockDetail = SendStockDetail::with('product', 'unit')->where('send_stock_id', $id)->get();
        $sendStockNumber = $sendStock->send_stock_number; // Use stored number instead of generating
        $totalQuantity = 0;

        $totalQuantity += $sendStockDetail->count();

        $pdf = Pdf::loadView('pages.sendStok.print', compact('sendStock', 'sendStockDetail', 'totalQuantity', 'sendStockNumber'));
        return response()->stream(function () use ($pdf) {
            echo $pdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Pindah-Stok-' . $sendStock->fromWarehouse->name . '-ke-' . $sendStock->toWarehouse->name . '.pdf"'
        ]);
    }
}
