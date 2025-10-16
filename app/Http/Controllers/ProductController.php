<?php

namespace App\Http\Controllers;

use App\Exports\ProductExport;
use App\Http\Requests\ProductRequest;
use App\Imports\ProductImport;
use App\Jobs\GenerateBarcodePDF;
use App\Jobs\MergeBarcodePDFs;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $unit = Unit::all();
        $categories = Category::all();

        return view('pages.product.index', compact('unit', 'categories'));
    }

    public function data()
    {
        $product = Product::with('unit_dus', 'unit_pak', 'unit_eceran')->get();

        return response()->json($product);
    }

    public function dataSearch(Request $request)
    {
        $searchQuery = $request->input('searchQuery');
        $selectedCategory = $request->input('category');

        $query = Product::with('unit_dus', 'unit_pak', 'unit_eceran');

        if ($searchQuery) {
            $query->where(function ($innerQuery) use ($searchQuery) {
                $innerQuery
                    ->where('name', 'LIKE', '%'.$searchQuery.'%')
                    ->orWhere('barcode_dus', 'LIKE', '%'.$searchQuery.'%')
                    ->orWhere('barcode_eceran', 'LIKE', '%'.$searchQuery.'%')
                    ->orWhere('barcode_pak', 'LIKE', '%'.$searchQuery.'%')
                    ->orWhere('group', 'LIKE', '%'.$searchQuery.'%');
            });
        } elseif ($selectedCategory) {
            $query->where(function ($innerQuery) use ($selectedCategory) {
                $innerQuery->where('group', 'LIKE', '%'.$selectedCategory.'%');
            });
        }

        $recordsFiltered = $query->count();

        // Apply pagination using Laravel's paginate() method
        $pageSize = $request->input('length', 10); // Number of records per page, defaults to 10
        $currentPage = $request->input('start', 0) / $pageSize + 1;
        $product = $query->paginate($pageSize, ['*'], 'page', $currentPage);

        // Prepare the JSON response
        $response = [
            'draw' => $request->input('draw', 1),
            'recordsTotal' => Product::count(), // Total count of all records in the table
            'recordsFiltered' => $recordsFiltered,
            'data' => $product->items(),
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
    public function store(ProductRequest $request)
    {
        try {
            DB::beginTransaction();

            $category = Category::where('name', $request->category)->first();
            if (! $category) {
                $category = Category::create([
                    'name' => $request->category,
                ]);
            } else {
                $category = Category::where('name', $request->category)->first();
            }
            $data = $request->validated();
            $data['group'] = $category->name;
            $product = Product::create($data);

            $warehouses = Warehouse::all();
            foreach ($warehouses as $warehouse) {
                Inventory::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 0,
                ]);
            }

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'Produk berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->with('error', 'Gagal menambahkan produk: '.$e->getMessage());
        }
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
        $product = Product::findOrFail($id);
        $unit = Unit::orderBy('id', 'asc')->get();
        $categories = Category::all();

        return view('pages.product.edit', compact('product', 'unit', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, string $id)
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($id);
            $category = Category::firstOrCreate(['name' => $request->category]);

            $product->fill($request->validated());
            $product->group = $category->name;
            $product->isShow = $request->has('isShow') ? true : false;
            $product->update();

            DB::commit();

            return redirect()->route('produk.index')->with('success', 'Produk berhasil diubah');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Gagal mengubah produk: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return redirect()
            ->back()
            ->with('success', 'Produk berhasil dihapus');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,xlsx',
        ]);

        Excel::import(new ProductImport, $request->file('file'));

        return redirect()
            ->back()
            ->with('success', 'Produk berhasil diimport');
    }

    public function downloadTemplate()
    {
        $template = public_path('assets\template\template_import_produk.xlsx');

        return response()->download($template);
    }

    public function export()
    {
        $date = date('Y-m-d');
        $filename = "export_product_{$date}.xlsx";

        return Excel::download(new ProductExport, $filename);
    }

    /**
     * Generate barcode PDF for mass printing (async via queue)
     */
    public function generateBarcodePDF(Request $request)
    {
        try {
            $request->validate([
                'products' => 'required|array|max:2000', // Keep server-side limit for safety
                'products.*.id' => 'required|integer',
                'products.*.quantity' => 'required|integer|min:1|max:100',
            ]);

            $requestedProducts = collect($request->input('products'))
                ->map(fn ($p) => [
                    'id' => (int) $p['id'],
                    'quantity' => (int) $p['quantity'],
                ])
                ->toArray();

            $validProductIds = \App\Models\Product::whereIn('id', array_column($requestedProducts, 'id'))
                ->pluck('id')
                ->toArray();

            $validProducts = array_filter($requestedProducts, function ($product) use ($validProductIds) {
                return in_array($product['id'], $validProductIds);
            });

            if (empty($validProducts)) {
                throw new \Exception('Tidak ada produk valid yang ditemukan.');
            }

            $batchId = (string) \Illuminate\Support\Str::uuid();
            $filename = 'barcode_labels_'.date('Y-m-d_His').'_'.$batchId.'.pdf';
            $userId = auth()->id();

            // Chunk products into smaller batches (50 products per job for faster processing)
            $chunks = array_chunk($validProducts, 50);

            $jobs = [];
            foreach ($chunks as $index => $chunk) {
                $jobs[] = new GenerateBarcodePDF($chunk, $filename, $index, count($chunks));
            }

            // Use job batching with merge callback
            $batch = \Illuminate\Support\Facades\Bus::batch($jobs)
                ->then(function (\Illuminate\Bus\Batch $batch) use ($filename, $chunks, $userId, $validProducts) {
                    // Merge all PDF parts after all jobs complete
                    dispatch(new MergeBarcodePDFs($filename, count($chunks), $userId, count($validProducts), $batch->id));
                })
                ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($filename) {
                })
                ->name('barcode-generation-'.$batchId)
                ->dispatch();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Barcode PDF sedang diproses. Silakan tunggu beberapa saat.',
                    'filename' => $filename,
                    'total_products' => count($validProducts),
                ]);
            }

            return redirect()->back()->with([
                'success' => 'Barcode PDF sedang diproses.',
            ]);

        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function downloadBarcodePDF($filename)
    {
        try {
            // Sanitize filename to prevent directory traversal
            $filename = basename($filename);
            $path = 'barcodes/'.$filename;

            // Check if file exists
            if (! Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan atau masih dalam proses.',
                ], 404);
            }

            $fullPath = storage_path('app/public/'.$path);

            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(false); // Set to true if you want to auto-delete after download

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengunduh file.',
            ], 500);
        }
    }

    /**
     * List all generated barcode PDFs
     */
    public function listBarcodePDFs()
    {
        try {
            $files = Storage::disk('public')->files('barcodes');

            $pdfs = collect($files)->map(function ($file) {
                return [
                    'filename' => basename($file),
                    'size' => Storage::disk('public')->size($file),
                    'created_at' => Storage::disk('public')->lastModified($file),
                    'url' => route('barcode.download', ['filename' => basename($file)]),
                ];
            })->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'files' => $pdfs,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar file.',
            ], 500);
        }
    }

    /**
     * Delete barcode PDF
     */
    public function deleteBarcodePDF($filename)
    {
        try {
            $filename = basename($filename);
            $path = 'barcodes/'.$filename;

            if (! Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan.',
                ], 404);
            }

            Storage::disk('public')->delete($path);

            return response()->json([
                'success' => true,
                'message' => 'File berhasil dihapus.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus file.',
            ], 500);
        }
    }
}
