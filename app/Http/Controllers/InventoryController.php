<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryDataRequest;
use App\Http\Requests\InventoryExportRequest;
use App\Http\Requests\InventoryRequest;
use App\Http\Requests\InventoryUpdateRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $product = Product::orderBy('id', 'asc')->get();
        $warehouse = Warehouse::orderBy('id', 'asc')->get();
        $categories = Category::all();

        return view('pages.inventory.index', compact('product', 'warehouse', 'categories'));
    }

    public function data(InventoryDataRequest $request)
    {
        try {
            $validated = $request->validated();
            $userRoles = Auth::user()->roles->pluck('name');
            $category = $validated['category'] ?? null;
            $warehouseId = $validated['warehouse_id'] ?? null;

            $query = Inventory::with(['product', 'warehouse']);

            if ($category) {
                $query->whereHas('product', function ($q) use ($category) {
                    $q->where('group', 'LIKE', '%'.$category.'%');
                });
            }

            if ($userRoles[0] != 'master') {
                $query->where('warehouse_id', Auth::user()->warehouse_id);
            } elseif ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            return datatables()->eloquent($query)->toJson();
        } catch (\Exception $e) {
            report($e);
            Log::error('InventoryController data method error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Terjadi kesalahan saat mengambil data inventory.',
            ], 500);
        }
    }

    public function exportData(InventoryExportRequest $request)
    {
        try {
            $validated = $request->validated();
            $userRoles = Auth::user()->roles->pluck('name');
            $category = $validated['category'] ?? null;
            $warehouseId = $validated['warehouse_id'] ?? null;

            $query = Inventory::with(['product', 'warehouse']);

            if ($category) {
                $query->whereHas('product', function ($q) use ($category) {
                    $q->where('group', 'LIKE', '%'.$category.'%');
                });
            }

            if ($userRoles[0] != 'master') {
                $query->where('warehouse_id', Auth::user()->warehouse_id);
            } elseif ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            $inventories = $query->get();

            $data = $inventories->map(function ($inventory) {
                return [
                    'warehouse' => $inventory->warehouse->name ?? '',
                    'category' => $inventory->product->group ?? '',
                    'product_name' => $inventory->product->name ?? '',
                    'dus_to_eceran' => $inventory->product->dus_to_eceran ?? '',
                    'pak_to_eceran' => $inventory->product->pak_to_eceran ?? '',
                    'quantity' => $inventory->quantity ?? 0,
                ];
            });

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            report($e);
            Log::error('InventoryController exportData method error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Terjadi kesalahan saat mengekspor data inventory.',
            ], 500);
        }
    }

    public function dataAll(Request $request)
    {
        $searchQuery = $request->input('searchQuery');

        $query = Inventory::with('product', 'warehouse')
            ->where('warehouse_id', Auth::user()->warehouse_id)
            ->whereHas('product', function ($query) {
                $query->where('isShow', true);
            });

        if ($searchQuery) {
            $query->whereHas('product', function ($query) use ($searchQuery) {
                $query->where('isShow', true)
                    ->where(function ($subQuery) use ($searchQuery) {
                        $subQuery->where('name', 'LIKE', '%'.$searchQuery.'%')
                            ->orWhere('barcode_dus', 'LIKE', '%'.$searchQuery.'%')
                            ->orWhere('barcode_pak', 'LIKE', '%'.$searchQuery.'%')
                            ->orWhere('barcode_eceran', 'LIKE', '%'.$searchQuery.'%');
                    });
            });
        } else {
            $query->whereRaw('1 = 0'); // Return no results when no search query is provided
        }

        $recordsFiltered = $query->count();

        // Apply pagination using Laravel's paginate() method
        $pageSize = $request->input('length', 10); // Number of records per page, defaults to 10
        $currentPage = $request->input('start', 0) / $pageSize + 1;
        $inventory = $query->paginate($pageSize, ['*'], 'page', $currentPage);

        // Prepare the JSON response
        $response = [
            'draw' => $request->input('draw', 1),
            'recordsTotal' => Inventory::count(), // Total count of all records in the table
            'recordsFiltered' => $recordsFiltered,
            'data' => $inventory->items(),
        ];

        return response()->json($response);
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
    public function store(InventoryRequest $request)
    {
        Inventory::create($request->validated());

        return redirect()->route('inventori.index')->with('success', 'Inventory berhasil ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $inventory = Inventory::findOrFail($id);

        return response()->json($inventory);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(InventoryUpdateRequest $request, string $id)
    {
        $inventory = Inventory::findOrFail($id);
        $inventory->update($request->validated());

        return redirect()->route('inventori.index')->with('success', 'Inventory berhasil diubah');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $inventory = Inventory::findOrFail($id);
        $inventory->delete();

        return redirect()->route('inventori.index')->with('success', 'Inventory berhasil dihapus');
    }
}
