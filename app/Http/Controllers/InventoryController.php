<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;

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

    public function data(Request $request)
    {
        try {
            $userRoles = auth()->user()->roles->pluck('name');
            $category = $request->input('category');
            $warehouseId = $request->input('warehouse_id');

            $query = Inventory::with(['product', 'warehouse']);

            if ($category) {
                $query->whereHas('product', function ($q) use ($category) {
                    $q->where('group', 'LIKE', '%'.$category.'%');
                });
            }

            if ($userRoles[0] != 'master') {
                $query->where('warehouse_id', auth()->user()->warehouse_id);
            } elseif ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            return datatables()->eloquent($query)->toJson();
        } catch (\Exception $e) {
            report($e);
            \Log::error('InventoryController data method error: '.$e->getMessage(), [
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

    public function exportData(Request $request)
    {
        try {
            $userRoles = auth()->user()->roles->pluck('name');
            $category = $request->input('category');
            $warehouseId = $request->input('warehouse_id');

            $query = Inventory::with(['product', 'warehouse']);

            if ($category) {
                $query->whereHas('product', function ($q) use ($category) {
                    $q->where('group', 'LIKE', '%'.$category.'%');
                });
            }

            if ($userRoles[0] != 'master') {
                $query->where('warehouse_id', auth()->user()->warehouse_id);
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
            \Log::error('InventoryController exportData method error: '.$e->getMessage(), [
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
        $draw = $request->input('draw', 1);
        $searchQuery = $request->input('searchQuery');

        // Tanpa kata kunci tidak ada hasil: langsung kembali tanpa query.
        if (! $searchQuery) {
            return response()->json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }

        $query = Inventory::with('product', 'warehouse')
            ->where('warehouse_id', auth()->user()->warehouse_id)
            ->whereHas('product', function ($query) use ($searchQuery) {
                $query->where('isShow', true)
                    ->where(function ($subQuery) use ($searchQuery) {
                        $subQuery->where('name', 'LIKE', '%'.$searchQuery.'%')
                            ->orWhere('barcode_dus', 'LIKE', '%'.$searchQuery.'%')
                            ->orWhere('barcode_pak', 'LIKE', '%'.$searchQuery.'%')
                            ->orWhere('barcode_eceran', 'LIKE', '%'.$searchQuery.'%');
                    });
            });

        $recordsFiltered = $query->count();

        // length dibatasi agar klien tidak bisa meminta seluruh gudang sekaligus.
        $length = (int) $request->input('length', 10);
        $length = $length < 1 ? 10 : min($length, 100);
        $start = max(0, (int) $request->input('start', 0));

        return response()->json([
            'draw' => $draw,
            // recordsTotal tidak ditampilkan di UI (info: false), jadi tidak perlu count(*) seluruh tabel.
            'recordsTotal' => $recordsFiltered,
            'recordsFiltered' => $recordsFiltered,
            'data' => $query->skip($start)->take($length)->get(),
        ]);
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
    public function update(Request $request, string $id)
    {
        $inventory = Inventory::findOrFail($id);
        $inventory->update($request->all());

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
