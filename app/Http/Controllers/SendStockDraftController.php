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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendStockDraftController extends Controller
{
    protected $stockTransferService;

    public function __construct(StockTransferService $stockTransferService)
    {
        $this->stockTransferService = $stockTransferService;
    }
    /**
     * Display a listing of draft send stocks.
     */
    public function index()
    {
        return view('pages.sendStok.draft.index');
    }

    /**
     * Get data for DataTables.
     */
    public function data()
    {
        try {
            $userRoles = auth()->user()->getRoleNames();
            $query = SendStock::with(['fromWarehouse', 'toWarehouse', 'user'])->draft();

            if ($userRoles[0] !== 'master') {
                $query->where('from_warehouse', auth()->user()->warehouse_id);
            }

            $sendStokDrafts = $query->orderBy('id', 'desc')->get();

            // Log for debugging
            Log::info('Draft Send Stock Data', [
                'count' => $sendStokDrafts->count(),
                'user_role' => $userRoles[0],
                'user_warehouse' => auth()->user()->warehouse_id,
                'data' => $sendStokDrafts->toArray()
            ]);

            return response()->json($sendStokDrafts);
        } catch (\Exception $e) {
            Log::error('Error fetching draft send stock data: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created draft send stock.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $fromWarehouse = $user->warehouse_id;
        $toWarehouse = $request->input('to_warehouse');

        // Fetch cart items with products
        $carts = SendStockCart::with('product')->where('user_id', $user->id)->get();

        if ($carts->isEmpty()) {
            return redirect()->back()->with('error', 'Keranjang kosong. Tambahkan produk terlebih dahulu.');
        }

        // Create draft send stock
        $sendStock = SendStock::create([
            'user_id' => $user->id,
            'from_warehouse' => $fromWarehouse,
            'to_warehouse' => $toWarehouse,
            'status' => 'draft',
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

        return redirect()->route('pindah-stok-draft.index')->with('success', 'Draft pindah stok berhasil disimpan.');
    }

    /**
     * Display the specified draft send stock.
     */
    public function show(string $id)
    {
        $sendStockDetail = SendStockDetail::with('product', 'unit')->where('send_stock_id', $id)->get();
        return response()->json($sendStockDetail);
    }

    /**
     * Show the form for editing the specified draft send stock.
     */
    public function edit(string $id)
    {
        $sendStock = SendStock::with(['fromWarehouse', 'toWarehouse'])->findOrFail($id);

        if ($sendStock->status !== 'draft') {
            return redirect()->route('pindah-stok-draft.index')->with('error', 'Hanya draft yang dapat diedit.');
        }

        $warehouses = Warehouse::orderBy('id', 'asc')->get();
        $products = Product::where('isShow', true)->orderBy('id', 'asc')->get();
        $units = Unit::orderBy('id', 'asc')->get();

        // Get existing details and convert to cart format for editing
        $sendStockDetails = SendStockDetail::with('product', 'unit')
            ->where('send_stock_id', $id)
            ->get();

        return view('pages.sendStok.draft.edit', compact('sendStock', 'warehouses', 'products', 'units', 'sendStockDetails'));
    }

    /**
     * Update the specified draft send stock.
     */
    public function update(Request $request, string $id)
    {
        $sendStock = SendStock::findOrFail($id);

        if ($sendStock->status !== 'draft') {
            return redirect()->route('pindah-stok-draft.index')->with('error', 'Hanya draft yang dapat diupdate.');
        }

        $sendStock->update([
            'to_warehouse' => $request->input('to_warehouse'),
        ]);

        return redirect()->route('pindah-stok-draft.index')->with('success', 'Draft berhasil diperbarui.');
    }

    /**
     * Remove the specified draft send stock.
     */
    public function destroy(string $id)
    {
        try {
            $sendStock = SendStock::findOrFail($id);

            if ($sendStock->status !== 'draft') {
                return redirect()->route('pindah-stok-draft.index')->with('error', 'Hanya draft yang dapat dihapus.');
            }

            // Delete related details first
            SendStockDetail::where('send_stock_id', $id)->delete();

            // Delete the draft
            $sendStock->delete();

            return redirect()->route('pindah-stok-draft.index')->with('success', 'Draft berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('pindah-stok-draft.index')->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Complete/Execute a draft send stock.
     */
    public function complete(string $id)
    {
        try {
            $sendStock = SendStock::findOrFail($id);

            if ($sendStock->status !== 'draft') {
                return redirect()->route('pindah-stok-draft.index')->with('error', 'Hanya draft yang dapat diselesaikan.');
            }

            // Fetch draft details
            $sendStockDetails = SendStockDetail::with('product')->where('send_stock_id', $id)->get();

            // Fetch all related inventory records
            $productIds = $sendStockDetails->pluck('product.id');
            $inventories = Inventory::whereIn('product_id', $productIds)
                ->where('warehouse_id', $sendStock->from_warehouse)
                ->get()
                ->keyBy('product_id');

            $stockErrors = [];

            // Validate stock availability
            foreach ($sendStockDetails as $detail) {
                $product = $detail->product;
                $unit = $detail->unit_id;
                $quantity = $detail->quantity;

                // Convert quantity to eceran
                $quantityEceran = match ($unit) {
                    $product->unit_dus => $quantity * $product->dus_to_eceran,
                    $product->unit_pak => $quantity * $product->pak_to_eceran,
                    default => $quantity
                };

                // Check available stock
                $fromInventory = $inventories[$product->id] ?? null;

                if (!$fromInventory || $fromInventory->quantity < $quantityEceran) {
                    $stockErrors[] = "Stok tidak mencukupi untuk {$product->name}. Dibutuhkan: $quantityEceran, Tersedia: " . ($fromInventory->quantity ?? 0);
                }
            }

            if (!empty($stockErrors)) {
                return redirect()->back()->with('error', implode('<br>', $stockErrors));
            }

            // Execute the stock transfer
            foreach ($sendStockDetails as $detail) {
                $product = $detail->product;
                $unit = $detail->unit_id;
                $quantity = $detail->quantity;

                $quantityEceran = match ($unit) {
                    $product->unit_dus => $quantity * $product->dus_to_eceran,
                    $product->unit_pak => $quantity * $product->pak_to_eceran,
                    default => $quantity
                };

                // Deduct stock from source warehouse
                Inventory::where('id', $inventories[$product->id]->id)->decrement('quantity', $quantityEceran);

                // Increase stock in destination warehouse
                Inventory::updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $sendStock->to_warehouse],
                    ['quantity' => DB::raw("quantity + $quantityEceran")]
                );
            }

            // Create ProductReport entries for stock transfer tracking
            $this->stockTransferService->createStockTransferReports($sendStock, $sendStockDetails);

            // Update status to completed
            $sendStock->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return redirect()->route('pindah-stok-draft.index')->with('success', 'Draft berhasil diselesaikan dan stok telah dipindahkan.');
        } catch (\Exception $e) {
            return redirect()->route('pindah-stok-draft.index')->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Add item to draft cart.
     */
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

    /**
     * Remove item from draft cart.
     */
    public function destroyCart($id)
    {
        try {
            // Try to find in SendStockCart first (for create/add to cart scenario)
            $cart = SendStockCart::find($id);
            if ($cart) {
                $cart->delete();
                return redirect()->back()->with('success', 'Item berhasil dihapus dari keranjang');
            }

            // If not found in cart, try SendStockDetail (for edit draft scenario)
            $detail = SendStockDetail::find($id);
            if ($detail) {
                // Check if this detail belongs to a draft
                $sendStock = SendStock::find($detail->send_stock_id);
                if ($sendStock && $sendStock->status === 'draft') {
                    $detail->delete();
                    return redirect()->back()->with('success', 'Item berhasil dihapus dari draft');
                } else {
                    return redirect()->back()->with('error', 'Tidak dapat menghapus item dari transaksi yang sudah selesai');
                }
            }

            return redirect()->back()->with('error', 'Item tidak ditemukan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
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

    /**
     * Add item to existing draft (for edit functionality).
     */
    public function addToExistingDraft(Request $request, $draftId)
    {
        try {
            $sendStock = SendStock::findOrFail($draftId);

            if ($sendStock->status !== 'draft') {
                if ($request->has('requests')) {
                    return response()->json(['error' => 'Hanya draft yang dapat diedit.'], 400);
                } else {
                    return redirect()->back()->with('error', 'Hanya draft yang dapat diedit.');
                }
            }

            DB::beginTransaction();

            // Check if this is a bulk request (multiple items) or single item
            if ($request->has('requests')) {
                // Handle bulk items
                $requests = $request->input('requests');

                foreach ($requests as $inputRequest) {
                    $productId = $inputRequest['product_id'];

                    // Process quantity_dus if it exists
                    if (isset($inputRequest['quantity_dus']) && $inputRequest['quantity_dus'] > 0) {
                        $this->processDraftItem($draftId, $productId, $inputRequest['quantity_dus'], $inputRequest['unit_dus']);
                    }

                    // Process quantity_pak if it exists
                    if (isset($inputRequest['quantity_pak']) && $inputRequest['quantity_pak'] > 0) {
                        $this->processDraftItem($draftId, $productId, $inputRequest['quantity_pak'], $inputRequest['unit_pak']);
                    }

                    // Process quantity_eceran if it exists
                    if (isset($inputRequest['quantity_eceran']) && $inputRequest['quantity_eceran'] > 0) {
                        $this->processDraftItem($draftId, $productId, $inputRequest['quantity_eceran'], $inputRequest['unit_eceran']);
                    }
                }

                DB::commit();
                return response()->json(['success' => 'Items added to draft successfully.'], 200);
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
                    $this->processDraftItem($draftId, $productId, $request->quantity_dus, $product->unit_dus);
                }

                if ($request->has('quantity_pak') && $request->quantity_pak > 0) {
                    $this->processDraftItem($draftId, $productId, $request->quantity_pak, $product->unit_pak);
                }

                if ($request->has('quantity_eceran') && $request->quantity_eceran > 0) {
                    $this->processDraftItem($draftId, $productId, $request->quantity_eceran, $product->unit_eceran);
                }

                DB::commit();
                return redirect()->back()->with('success', 'Produk berhasil ditambahkan ke draft');
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->has('requests')) {
                return response()->json(['error' => 'Failed to add items to draft: ' . $e->getMessage()], 500);
            } else {
                return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
        }
    }

    private function processDraftItem($draftId, $productId, $quantity, $unitId)
    {
        $existingDetail = SendStockDetail::where('send_stock_id', $draftId)
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($existingDetail) {
            $existingDetail->quantity += $quantity;
            $existingDetail->save();
        } else {
            SendStockDetail::create([
                'send_stock_id' => $draftId,
                'product_id' => $productId,
                'unit_id' => $unitId,
                'quantity' => $quantity,
            ]);
        }
    }
}
