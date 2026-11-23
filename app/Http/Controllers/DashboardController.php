<?php

namespace App\Http\Controllers;

use App\Models\SellDetail;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Show dashboard page.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $userRoles = $user->getRoleNames();
        $isMaster = $userRoles->contains('master');

        // Determine warehouse ID for filtering
        if ($isMaster) {
            $warehouseId = $request->input('warehouse_id', $user->warehouse_id ?? 'all');
            $warehouses = Warehouse::orderBy('name', 'asc')->get();
        } else {
            $warehouseId = $user->warehouse_id;
            $warehouses = collect();
        }

        // Get top 10 selling products with optimized query
        $cacheKey = $warehouseId === 'all' ? 'top_products_all' : "top_products_$warehouseId";

        $topProducts = Cache::remember($cacheKey, 600, function () use ($warehouseId) {
            // Dibatasi 90 hari agar sells dipindai lewat index created_at (seluruh riwayat ±68 dtk, 90 hari ±1 dtk).
            $since = now()->subDays(90)->startOfDay();

            if ($warehouseId !== 'all') {
                // Query for specific warehouse
                return DB::table('sell_details')
                    ->select([
                        'products.name as product_name',
                        DB::raw('SUM(
                            CASE
                                WHEN sell_details.unit_id = products.unit_dus THEN CAST(sell_details.quantity AS DECIMAL(10,2)) * products.dus_to_eceran
                                WHEN sell_details.unit_id = products.unit_pak THEN CAST(sell_details.quantity AS DECIMAL(10,2)) * products.pak_to_eceran
                                ELSE CAST(sell_details.quantity AS DECIMAL(10,2))
                            END
                        ) as total_sold')
                    ])
                    ->join('sells', 'sell_details.sell_id', '=', 'sells.id')
                    ->join('products', 'sell_details.product_id', '=', 'products.id')
                    ->where('sells.warehouse_id', $warehouseId)
                    ->where('sells.status', '!=', 'batal')
                    ->where('sells.created_at', '>=', $since)
                    ->groupBy('sell_details.product_id', 'products.name')
                    ->orderByDesc('total_sold')
                    ->limit(10)
                    ->get();
            } else {
                // Query for all warehouses - aggregate by product across all warehouses
                return DB::table('sell_details')
                    ->select([
                        'products.name as product_name',
                        DB::raw('GROUP_CONCAT(DISTINCT warehouses.name ORDER BY warehouses.name SEPARATOR ", ") as warehouse_name'),
                        DB::raw('SUM(
                            CASE
                                WHEN sell_details.unit_id = products.unit_dus THEN CAST(sell_details.quantity AS DECIMAL(10,2)) * products.dus_to_eceran
                                WHEN sell_details.unit_id = products.unit_pak THEN CAST(sell_details.quantity AS DECIMAL(10,2)) * products.pak_to_eceran
                                ELSE CAST(sell_details.quantity AS DECIMAL(10,2))
                            END
                        ) as total_sold')
                    ])
                    ->join('sells', 'sell_details.sell_id', '=', 'sells.id')
                    ->join('products', 'sell_details.product_id', '=', 'products.id')
                    ->join('warehouses', 'sells.warehouse_id', '=', 'warehouses.id')
                    ->where('sells.status', '!=', 'batal')
                    ->where('sells.created_at', '>=', $since)
                    ->groupBy('sell_details.product_id', 'products.name')
                    ->orderByDesc('total_sold')
                    ->limit(10)
                    ->get();
            }
        });

        return view('pages.dashboard', compact('topProducts', 'warehouses', 'isMaster', 'warehouseId'));
    }
}
