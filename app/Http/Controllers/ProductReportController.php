<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReport;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::all();
        $users = User::all();
        $products = Product::all();
        return view('pages.product.report', compact('warehouses', 'users', 'products'));
    }

    public function data(Request $request)
    {
        $role = auth()->user()->getRoleNames();
        $user_id = $request->input('user_id');
        $fromDate = $request->input('from_date') ?? now()->format('Y-m-d');
        $toDate = $request->input('to_date') ?? now()->format('Y-m-d');
        $for = $request->input('for');
        $product = $request->input('product');

        $query = ProductReport::with(['user:id,name', 'supplier:id,name', 'customer:id,name', 'product:id,name'])
            ->select('id', 'product_id', 'unit_type', 'user_id', 'type', 'for', 'description', 'supplier_id', 'customer_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(price * qty) as total_value'), 'price')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->groupBy('id', 'product_id', 'unit_type', 'user_id', 'supplier_id', 'customer_id', 'type', 'description', 'price');

        if ($role[0] !== 'master') {
            $query->where('user_id', auth()->user()->id)
                ->where('warehouse_id', auth()->user()->warehouse_id);
        } else {
            if ($warehouse = $request->input('warehouse')) {
                $query->where('warehouse_id', $warehouse);
            }
        }

        if ($user_id) {
            $query->where('user_id', $user_id);
        }

        if ($for) {
            $query->where('for', $for);
        }

        if ($product) {
            $query->where('product_id', $product);
        }

        $report = $query->get();

        // Calculate totals
        $totalNilai = $report->sum('total_value');
        $totalDus = $report->where('unit_type', 'DUS')->sum('total_qty');
        $totalPak = $report->where('unit_type', 'PAK')->sum('total_qty');
        $totalEceran = $report->where('unit_type', 'ECERAN')->sum('total_qty');

        $response = [
            'report' => $report,
            'totalNilai' => $totalNilai,
            'totalDus' => $totalDus,
            'totalPak' => $totalPak,
            'totalEceran' => $totalEceran,
        ];

        return response()->json($response);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $productReport = ProductReport::findOrFail($id);

            // Check if user has permission to delete this record
            $userRoles = auth()->user()->getRoleNames();
            if ($userRoles[0] !== 'master') {
                // Non-master users can only delete their own records from their warehouse
                if (
                    $productReport->user_id !== auth()->user()->id ||
                    $productReport->warehouse_id !== auth()->user()->warehouse_id
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki izin untuk menghapus data ini'
                    ], 403);
                }
            }

            $productReport->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data laporan produk berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }
}
