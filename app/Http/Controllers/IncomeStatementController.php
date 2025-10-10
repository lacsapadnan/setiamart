<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Kas;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Sell;
use App\Models\SellDetail;
use App\Models\SellRetur;
use App\Models\SellReturDetail;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncomeStatementController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:baca laba rugi');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::all();
        $users = User::all();
        return view('pages.income-statement.index', compact('warehouses', 'users'));
    }

    /**
     * Clear cache for income statement data
     */
    public function clearCache(Request $request)
    {
        $user_id = $request->input('user_id');
        $fromDate = $request->input('from_date') ?? now()->format('Y-m-d');
        $toDate = $request->input('to_date') ?? now()->format('Y-m-d');
        $warehouse_id = $request->input('warehouse');
        $all_branches = $request->boolean('all_branches', false);

        $cacheKey = "income_statement_" . md5(serialize([
            'user_id' => $user_id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'warehouse_id' => $warehouse_id,
            'all_branches' => $all_branches,
            'auth_user' => auth()->id()
        ]));

        cache()->forget($cacheKey);

        return response()->json(['message' => 'Cache cleared successfully']);
    }

    public function data(Request $request)
    {
        // Increase memory limit and execution time for large datasets
        ini_set('memory_limit', '1G'); // Increased from 512M to 1G
        ini_set('max_execution_time', 600); // Increased from 300 to 600 seconds

        $user_id = $request->input('user_id');
        $fromDate = $request->input('from_date') ?? now()->format('Y-m-d');
        $toDate = $request->input('to_date') ?? now()->format('Y-m-d');
        $warehouse_id = $request->input('warehouse');

        // Set default all_branches based on user role
        $user = auth()->user();
        $defaultAllBranches = $user->can('lihat semua laba rugi');
        $all_branches = $request->boolean('all_branches', $defaultAllBranches);

        // If user doesn't have permission to see all income statements
        if (!$user->can('lihat semua laba rugi')) {
            // Force use their own warehouse and user data
            $warehouse_id = auth()->user()->warehouse_id;
            $user_id = auth()->id();
            $all_branches = false; // Disable all branches for users without permission
        }

        // If all_branches is true, set warehouse_id to null to include all warehouses
        if ($all_branches) {
            $warehouse_id = null;
        }

        $endDate = Carbon::parse($toDate)->endOfDay();

        // Create cache key for this specific request
        $cacheKey = "income_statement_" . md5(serialize([
            'user_id' => $user_id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'warehouse_id' => $warehouse_id,
            'all_branches' => $all_branches,
            'auth_user' => auth()->id()
        ]));

        // Try to get cached result first (cache for 5 minutes)
        $cachedResult = cache()->get($cacheKey);
        if ($cachedResult) {
            return response()->json($cachedResult);
        }

        try {
            // Get warehouse info to determine if it's out of town
            $warehouse = null;
            if ($warehouse_id) {
                $warehouse = Warehouse::select('id', 'name', 'isOutOfTown')->find($warehouse_id);
            }

            // Use parallel processing for performance improvement
            $results = $this->calculateDataInParallel($fromDate, $endDate, $warehouse_id, $user_id, $warehouse, $all_branches);

            $salesData = $results['salesData'];
            $cogsData = $results['cogsData'];
            $operatingExpenses = $results['operatingExpenses'];
            $otherIncome = $results['otherIncome'];
            $stockBurden = $results['stockBurden'];

            // Synchronize product lists and ensure consistent ordering
            $this->synchronizeProductData($salesData, $cogsData);

            // Calculate totals with safe numeric operations
            $totalRevenue = floatval($salesData['total_revenue'] ?? 0);
            $totalCogs = floatval($cogsData['total_cogs'] ?? 0);
            $totalOtherIncome = floatval($otherIncome['total_other_income'] ?? 0);
            $totalOperatingExpenses = floatval($operatingExpenses['total_operating_expenses'] ?? 0);
            $totalStockBurden = floatval($stockBurden['total_stock_burden'] ?? 0);

            // Fix: Properly calculate gross profit by subtracting COGS from revenue
            $grossProfit = $totalRevenue - abs($totalCogs);
            $netIncome = $grossProfit - $totalStockBurden - $totalOperatingExpenses + $totalOtherIncome;

            $warehouseName = $warehouse ? $warehouse->name : ($all_branches ? 'Semua Cabang' : 'Semua Gudang');

            $response = [
                'sales_data' => $salesData,
                'cogs_data' => [
                    'total_cogs' => -abs($cogsData['total_cogs']), // Ensure COGS is negative
                    'cogs_by_product' => $cogsData['cogs_by_product'],
                    'is_out_of_town' => $cogsData['is_out_of_town'] ?? false
                ],
                'stock_burden' => $stockBurden,
                'operating_expenses' => $operatingExpenses,
                'other_income' => $otherIncome,
                'gross_profit' => $grossProfit,
                'net_income' => $netIncome,
                'period' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'warehouse' => $warehouseName
                ],
                'cache_generated_at' => now()->toISOString()
            ];

            // Cache the result for 5 minutes
            cache()->put($cacheKey, $response, 300);

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Income Statement calculation error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate income statement. Please try with a smaller date range.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate data in parallel for better performance
     */
    private function calculateDataInParallel($fromDate, $endDate, $warehouse_id, $user_id, $warehouse, $all_branches)
    {
        // Use database transactions to avoid conflicts
        return DB::transaction(function () use ($fromDate, $endDate, $warehouse_id, $user_id, $warehouse, $all_branches) {
            // Optimize by running calculations in sequence but with optimized queries
            $salesData = $this->calculateSalesRevenueOptimized($fromDate, $endDate, $warehouse_id, $user_id, $all_branches);
            $cogsData = $this->calculateCostOfGoodsSoldOptimized($fromDate, $endDate, $warehouse_id, $user_id, $warehouse, $all_branches);
            $stockBurden = $this->calculateStockBurden($warehouse_id, $user_id, $warehouse, $all_branches);
            $operatingExpenses = $this->calculateOperatingExpensesOptimized($fromDate, $endDate, $warehouse_id, $user_id, $all_branches);
            $otherIncome = $this->calculateOtherIncomeOptimized($fromDate, $endDate, $warehouse_id, $user_id, $all_branches);

            return [
                'salesData' => $salesData,
                'cogsData' => $cogsData,
                'stockBurden' => $stockBurden,
                'operatingExpenses' => $operatingExpenses,
                'otherIncome' => $otherIncome
            ];
        });
    }

    private function calculateSalesRevenueOptimized($fromDate, $endDate, $warehouse_id, $user_id, $all_branches = false)
    {
        try {
            // Step 1: Get total revenue and count using a single optimized query
            $baseQuery = DB::table('sells as s')
                ->join('sell_details as sd', 's.id', '=', 'sd.sell_id')
                ->join('products as p', 'sd.product_id', '=', 'p.id')
                ->where('s.status', 'lunas')  // Only include paid transactions
                ->whereBetween('s.created_at', [$fromDate, $endDate]);

            if ($warehouse_id && !$all_branches) {
                $baseQuery->where('s.warehouse_id', $warehouse_id);
            }

            if ($user_id) {
                $baseQuery->where('s.cashier_id', $user_id);
            }

            // Get aggregated data in a single query
            $aggregatedData = $baseQuery->select(
                DB::raw('COUNT(DISTINCT s.id) as total_transactions'),
                'sd.product_id',
                'p.name as product_name',
                DB::raw('SUM(sd.quantity) as total_quantity'),
                DB::raw('SUM(sd.quantity * (sd.price - COALESCE(sd.diskon, 0))) as total_sales')
            )
                ->groupBy('sd.product_id', 'p.name')
                ->get();

            // Initialize response structure
            $salesData = [
                'total_revenue' => 0,
                'total_transactions' => 0,
                'sales_by_product' => []
            ];

            // Process aggregated data
            foreach ($aggregatedData as $row) {
                // Update totals
                $salesData['total_revenue'] += floatval($row->total_sales);
                if (!isset($salesData['total_transactions'])) {
                    $salesData['total_transactions'] = intval($row->total_transactions);
                }

                // Add product-specific data
                $salesData['sales_by_product'][$row->product_id] = [
                    'product_name' => $row->product_name,
                    'quantity_sold' => floatval($row->total_quantity),
                    'total_revenue' => floatval($row->total_sales)
                ];
            }

            return $salesData;
        } catch (\Exception $e) {
            Log::error('Error in calculateSalesRevenueOptimized: ' . $e->getMessage());
            throw $e;
        }
    }

    private function calculateCostOfGoodsSoldOptimized($fromDate, $endDate, $warehouse_id, $user_id, $warehouse, $all_branches = false)
    {
        try {
            // Get COGS data from sell_details with proper cost pricing
            $baseQuery = DB::table('sells as s')
                ->join('sell_details as sd', 's.id', '=', 'sd.sell_id')
                ->join('products as p', 'sd.product_id', '=', 'p.id')
                ->where('s.status', 'lunas')  // Only include paid transactions
                ->whereBetween('s.created_at', [$fromDate, $endDate]);

            if ($warehouse_id && !$all_branches) {
                $baseQuery->where('s.warehouse_id', $warehouse_id);
            }

            if ($user_id) {
                $baseQuery->where('s.cashier_id', $user_id);
            }

            // Get the data with warehouse info for cost price calculation
            $salesData = $baseQuery
                ->select(
                    'sd.product_id',
                    'p.name as product_name',
                    'sd.quantity',
                    'sd.unit_id',
                    'p.lastest_price_eceran',
                    'p.lastest_price_eceran_out_of_town',
                    's.warehouse_id'
                )
                ->get();

            $totalCogs = 0;
            $cogsByProduct = [];

            foreach ($salesData as $item) {
                // Determine if warehouse is out of town
                $isOutOfTown = false;
                if ($item->warehouse_id) {
                    $itemWarehouse = Warehouse::find($item->warehouse_id);
                    $isOutOfTown = $itemWarehouse ? ($itemWarehouse->isOutOfTown ?? false) : false;
                }

                // Get cost price based on location
                $costPrice = $isOutOfTown ?
                    floatval($item->lastest_price_eceran_out_of_town ?? $item->lastest_price_eceran ?? 0) :
                    floatval($item->lastest_price_eceran ?? 0);

                // Convert quantity to eceran for consistency
                $product = Product::find($item->product_id);
                $quantityInEceran = $this->convertToEceran($item->quantity, $item->unit_id, $product);

                // Calculate total cost for this item (cost per eceran * quantity in eceran)
                $itemTotalCost = $quantityInEceran * $costPrice;
                $totalCogs += $itemTotalCost;

                // Aggregate by product
                if (!isset($cogsByProduct[$item->product_id])) {
                    $cogsByProduct[$item->product_id] = [
                        'product_name' => $item->product_name,
                        'quantity_sold_eceran' => 0,
                        'total_cogs' => 0,
                        'cost_price' => $costPrice
                    ];
                }

                $cogsByProduct[$item->product_id]['quantity_sold_eceran'] += $quantityInEceran;
                $cogsByProduct[$item->product_id]['total_cogs'] += $itemTotalCost;

                // Update average cost price
                if ($cogsByProduct[$item->product_id]['quantity_sold_eceran'] > 0) {
                    $cogsByProduct[$item->product_id]['cost_price'] =
                        $cogsByProduct[$item->product_id]['total_cogs'] / $cogsByProduct[$item->product_id]['quantity_sold_eceran'];
                }
            }

            return [
                'total_cogs' => $totalCogs,
                'cogs_by_product' => $cogsByProduct,
                'is_out_of_town' => $warehouse ? ($warehouse->isOutOfTown ?? false) : false
            ];
        } catch (\Exception $e) {
            Log::error('Error in calculateCostOfGoodsSoldOptimized: ' . $e->getMessage());
            throw $e;
        }
    }

    private function calculateOperatingExpensesOptimized($fromDate, $endDate, $warehouse_id, $user_id, $all_branches = false)
    {
        // First, let's get ALL expenses to see what we have
        $allExpensesQuery = DB::table('kas as k')
            ->leftJoin('kas_expense_items as kei', 'k.kas_expense_item_id', '=', 'kei.id')
            ->select(
                'k.date',
                'k.invoice',
                'k.description',
                'k.amount',
                'kei.name as category_name',
                'k.kas_expense_item_id'
            )
            ->where('k.type', 'Kas Keluar')
            ->whereBetween('k.date', [$fromDate, $endDate]);

        if ($warehouse_id && !$all_branches) {
            $allExpensesQuery->where('k.warehouse_id', $warehouse_id);
        }

        // Use cursor for memory efficiency with large datasets
        $allExpenses = collect();
        foreach ($allExpensesQuery->cursor() as $expense) {
            $allExpenses->push($expense);
        }

        // Filter out "LAIN LAIN" and "LAIN-LAIN" categories
        $filteredExpenses = $allExpenses->filter(function ($expense) {
            // Include if:
            // 1. No category assigned (kas_expense_item_id is null)
            // 2. Category name is not "LAIN LAIN" or "LAIN-LAIN" (case insensitive)
            if (is_null($expense->kas_expense_item_id) || is_null($expense->category_name)) {
                return true;
            }

            $categoryUpper = strtoupper($expense->category_name);
            return $categoryUpper !== 'LAIN LAIN' && $categoryUpper !== 'LAIN-LAIN';
        });

        // Calculate total
        $totalExpenses = $filteredExpenses->sum(function ($expense) {
            return floatval($expense->amount ?? 0);
        });

        // Group by category
        $expensesByCategory = $filteredExpenses->groupBy(function ($expense) {
            return $expense->category_name ?? 'Lainnya';
        })->map(function ($categoryExpenses, $categoryName) {
            return [
                'category' => $categoryName,
                'total_amount' => $categoryExpenses->sum(function ($expense) {
                    return floatval($expense->amount ?? 0);
                }),
                'count' => $categoryExpenses->count()
            ];
        })->values()->toArray();

        // Prepare detailed expense list
        $expenseDetails = $filteredExpenses->map(function ($expense) {
            return [
                'date' => $expense->date,
                'invoice' => $expense->invoice ?? '-',
                'description' => $expense->description ?? '-',
                'category' => $expense->category_name ?? 'Lainnya',
                'amount' => floatval($expense->amount ?? 0)
            ];
        })->sortByDesc('date')->values()->toArray();

        return [
            'total_operating_expenses' => $totalExpenses,
            'expenses_by_category' => $expensesByCategory,
            'expense_details' => $expenseDetails
        ];
    }

    private function calculateOtherIncomeOptimized($fromDate, $endDate, $warehouse_id, $user_id, $all_branches = false)
    {
        // Return zero for other income as per user requirement to remove PENDAPATAN LAIN-LAIN
        return [
            'total_other_income' => 0,
            'income_by_category' => [],
            'income_details' => []
        ];
    }

    private function calculateNetSalesForProduct($sale, $sellDetail)
    {
        $originalQuantity = floatval($sellDetail->quantity ?? 0);
        $originalPrice = floatval($sellDetail->price ?? 0);
        $originalDiskon = floatval($sellDetail->diskon ?? 0);

        // Calculate original revenue
        $originalRevenue = ($originalPrice * $originalQuantity) - $originalDiskon;

        // Get total returned quantity for this product from this sale
        $totalReturnedQuantity = 0;
        $totalReturnedRevenue = 0;

        foreach ($sale->sellReturs as $sellRetur) {
            if ($sellRetur->detail) {
                $productReturns = $sellRetur->detail->where('product_id', $sellDetail->product_id);

                foreach ($productReturns as $returnDetail) {
                    $returnedQty = floatval($returnDetail->qty ?? 0);
                    $returnedPrice = floatval($returnDetail->price ?? 0);

                    // Convert returned quantity to same unit as original sale
                    $returnedQuantityConverted = $this->convertBetweenUnits(
                        $returnedQty,
                        $returnDetail->unit_id,
                        $sellDetail->unit_id,
                        $sellDetail->product
                    );

                    $totalReturnedQuantity += $returnedQuantityConverted;
                    $totalReturnedRevenue += ($returnedPrice * $returnedQty);
                }
            }
        }

        // Calculate net values
        $netQuantity = max(0, $originalQuantity - $totalReturnedQuantity);
        $netRevenue = max(0, $originalRevenue - $totalReturnedRevenue);

        return [
            'net_quantity' => $netQuantity,
            'net_revenue' => $netRevenue
        ];
    }

    private function calculateNetCogsForProduct($soldProduct, $product)
    {
        // Convert original sold quantity to eceran
        $originalQuantityEceran = $this->convertQuantityToEceran(
            $soldProduct->qty,
            $soldProduct->unit_type,
            $product
        );

        // Get total returned quantity for this product (in eceran)
        $totalReturnedQuantityEceran = 0;

        // Find returns for this product from the same time period
        $returns = ProductReport::where('product_id', $product->id)
            ->where('for', 'MASUK')
            ->where('type', 'RETUR PENJUALAN')
            ->where('warehouse_id', $soldProduct->warehouse_id)
            ->get();

        foreach ($returns as $returnReport) {
            $returnQuantityEceran = $this->convertQuantityToEceran(
                $returnReport->qty,
                $returnReport->unit_type,
                $product
            );
            $totalReturnedQuantityEceran += $returnQuantityEceran;
        }

        // Calculate net quantity
        $netQuantityEceran = max(0, $originalQuantityEceran - $totalReturnedQuantityEceran);

        return [
            'net_quantity_eceran' => $netQuantityEceran
        ];
    }

    private function convertBetweenUnits($quantity, $fromUnitId, $toUnitId, $product)
    {
        // If same unit, no conversion needed
        if ($fromUnitId == $toUnitId) {
            return floatval($quantity);
        }

        // Convert to eceran first, then to target unit
        $quantityInEceran = $this->convertToEceran($quantity, $fromUnitId, $product);

        // Convert from eceran to target unit
        if ($product->unit_dus == $toUnitId) {
            $conversion = floatval($product->dus_to_eceran ?? 1);
            return $conversion > 0 ? $quantityInEceran / $conversion : 0;
        } elseif ($product->unit_pak == $toUnitId) {
            $conversion = floatval($product->pak_to_eceran ?? 1);
            return $conversion > 0 ? $quantityInEceran / $conversion : 0;
        } else {
            // Target is eceran
            return $quantityInEceran;
        }
    }

    private function isFullyReturned($sell)
    {
        // Get all sell details for this sale
        $sellDetails = $sell->details;

        if ($sellDetails->isEmpty()) {
            return false;
        }

        // Get all return details for this sale
        $returnDetails = collect();
        foreach ($sell->sellReturs as $sellRetur) {
            if ($sellRetur->detail) {
                $returnDetails = $returnDetails->merge($sellRetur->detail);
            }
        }

        if ($returnDetails->isEmpty()) {
            return false;
        }

        // Check if every product in the sale has been fully returned
        foreach ($sellDetails as $sellDetail) {
            $originalQuantity = $this->convertToEceran(
                $sellDetail->quantity,
                $sellDetail->unit_id,
                $sellDetail->product
            );

            // Get total returned quantity for this product
            $totalReturnedQuantity = 0;
            $productReturns = $returnDetails->where('product_id', $sellDetail->product_id);

            foreach ($productReturns as $returnDetail) {
                $returnedQuantity = $this->convertToEceran(
                    $returnDetail->qty,
                    $returnDetail->unit_id,
                    $sellDetail->product
                );
                $totalReturnedQuantity += $returnedQuantity;
            }

            // If any product is not fully returned, the sale is not fully returned
            if ($totalReturnedQuantity < $originalQuantity) {
                return false;
            }
        }

        // All products have been fully returned
        return true;
    }

    private function convertToEceran($quantity, $unitId, $product)
    {
        $safeQuantity = floatval($quantity ?? 0);

        if ($safeQuantity <= 0) {
            return 0;
        }

        // Check which unit this is and convert accordingly
        if ($product->unit_dus == $unitId) {
            $conversion = floatval($product->dus_to_eceran ?? 1);
            return $safeQuantity * ($conversion > 0 ? $conversion : 1);
        } elseif ($product->unit_pak == $unitId) {
            $conversion = floatval($product->pak_to_eceran ?? 1);
            return $safeQuantity * ($conversion > 0 ? $conversion : 1);
        } else {
            // Assume it's eceran/pcs
            return $safeQuantity;
        }
    }

    private function synchronizeProductData(&$salesData, &$cogsData)
    {
        // Get products that exist in either dataset (but only include non-zero products)
        $validProducts = [];

        // Add products from sales data (only those with positive values)
        foreach ($salesData['sales_by_product'] as $productId => $product) {
            if ($product['quantity_sold'] > 0 || $product['total_revenue'] > 0) {
                $validProducts[$productId] = $product['product_name'];
            }
        }

        // Add products from COGS data (only those with positive values)
        foreach ($cogsData['cogs_by_product'] as $productId => $product) {
            if ($product['quantity_sold_eceran'] > 0 || $product['total_cogs'] > 0) {
                $validProducts[$productId] = $product['product_name'];
            }
        }

        // Filter sales data to only include valid products, add missing ones with zero values
        $filteredSalesData = [];
        foreach ($validProducts as $productId => $productName) {
            if (isset($salesData['sales_by_product'][$productId])) {
                $filteredSalesData[$productId] = $salesData['sales_by_product'][$productId];
            } else {
                $filteredSalesData[$productId] = [
                    'product_name' => $productName,
                    'quantity_sold' => 0,
                    'total_revenue' => 0
                ];
            }
        }

        // Filter COGS data to only include valid products, add missing ones with zero values
        $filteredCogsData = [];
        foreach ($validProducts as $productId => $productName) {
            if (isset($cogsData['cogs_by_product'][$productId])) {
                $filteredCogsData[$productId] = $cogsData['cogs_by_product'][$productId];
            } else {
                $filteredCogsData[$productId] = [
                    'product_name' => $productName,
                    'quantity_sold_eceran' => 0,
                    'cost_price' => 0,
                    'total_cogs' => 0
                ];
            }
        }

        // Sort both arrays by product name
        $sortFunction = function ($a, $b) {
            return strcmp($a['product_name'], $b['product_name']);
        };

        $sortedSales = array_values($filteredSalesData);
        usort($sortedSales, $sortFunction);
        $salesData['sales_by_product'] = $sortedSales;

        $sortedCogs = array_values($filteredCogsData);
        usort($sortedCogs, $sortFunction);
        $cogsData['cogs_by_product'] = $sortedCogs;
    }

    private function convertQuantityToEceran($quantity, $unitType, $product)
    {
        // Ensure quantity is numeric
        $safeQuantity = floatval($quantity ?? 0);

        if ($safeQuantity <= 0) {
            return 0;
        }

        switch ($unitType) {
            case 'DUS':
                $conversion = floatval($product->dus_to_eceran ?? 1);
                return $safeQuantity * ($conversion > 0 ? $conversion : 1);
            case 'PAK':
                $conversion = floatval($product->pak_to_eceran ?? 1);
                return $safeQuantity * ($conversion > 0 ? $conversion : 1);
            case 'ECERAN':
            default:
                return $safeQuantity;
        }
    }

    private function convertQuantityToEceranFromData($quantity, $unitType, $productData)
    {
        // Ensure quantity is numeric
        $safeQuantity = floatval($quantity ?? 0);

        if ($safeQuantity <= 0) {
            return 0;
        }

        switch ($unitType) {
            case 'DUS':
                $conversion = floatval($productData->dus_to_eceran ?? 1);
                return $safeQuantity * ($conversion > 0 ? $conversion : 1);
            case 'PAK':
                $conversion = floatval($productData->pak_to_eceran ?? 1);
                return $safeQuantity * ($conversion > 0 ? $conversion : 1);
            case 'ECERAN':
            default:
                return $safeQuantity;
        }
    }

    /**
     * Get cost price (harga modal) for a product based on warehouse location
     * Uses lastest_price_eceran for in-town and lastest_price_eceran_out_of_town for out-of-town
     */
    private function getCostPrice($productId, $unitId, $warehouseId = null)
    {
        $product = Product::find($productId);

        if (!$product) {
            return 0;
        }

        // Determine if warehouse is out of town
        $isOutOfTown = false;
        if ($warehouseId) {
            $warehouse = Warehouse::find($warehouseId);
            $isOutOfTown = $warehouse ? ($warehouse->isOutOfTown ?? false) : false;
        }

        // Get base cost price based on location
        $baseCostPrice = $isOutOfTown ?
            floatval($product->lastest_price_eceran_out_of_town ?? $product->lastest_price_eceran ?? 0) :
            floatval($product->lastest_price_eceran ?? 0);

        // For non-eceran units, we use the same base cost price
        // since lastest_price_eceran is the reference cost price per unit
        return $baseCostPrice;
    }

    /**
     * Get cost price by unit type string and warehouse location
     */
    private function getCostPriceByUnitType($product, $unitType, $isOutOfTown = false)
    {
        // Get base cost price based on location
        $baseCostPrice = $isOutOfTown ?
            floatval($product->lastest_price_eceran_out_of_town ?? $product->lastest_price_eceran ?? 0) :
            floatval($product->lastest_price_eceran ?? 0);

        // All units use the same base cost price per unit
        // since lastest_price_eceran is the reference cost price
        return $baseCostPrice;
    }

    /**
     * Calculate stock burden - inventory quantity * latest price (eceran)
     * This represents the value of unsold inventory that affects profitability
     * Not affected by date filters, only by warehouse and user filters
     */
    private function calculateStockBurden($warehouse_id, $user_id, $warehouse, $all_branches = false)
    {
        try {
            $query = DB::table('inventories as i')
                ->join('products as p', 'i.product_id', '=', 'p.id')
                ->join('warehouses as w', 'i.warehouse_id', '=', 'w.id')
                ->where('i.quantity', '>', 0)
                ->where('p.isShow', true) // Only include active products
                ->select(
                    'i.product_id',
                    'p.name as product_name',
                    'i.quantity',
                    'p.lastest_price_eceran',
                    'p.lastest_price_eceran_out_of_town',
                    'w.isOutOfTown',
                    'i.warehouse_id'
                );

            // Apply warehouse filter
            if ($warehouse_id && !$all_branches) {
                $query->where('i.warehouse_id', $warehouse_id);
            }

            $inventoryData = $query->get();

            $totalStockBurden = 0;
            $stockBurdenByProduct = [];

            foreach ($inventoryData as $item) {
                // Determine if warehouse is out of town
                $isOutOfTown = (bool) ($item->isOutOfTown ?? false);

                // Get cost price based on location
                $costPrice = $isOutOfTown ?
                    floatval($item->lastest_price_eceran_out_of_town ?? $item->lastest_price_eceran ?? 0) :
                    floatval($item->lastest_price_eceran ?? 0);

                // Calculate burden for this product (quantity * cost price)
                $productBurden = floatval($item->quantity) * $costPrice;
                $totalStockBurden += $productBurden;

                // Aggregate by product
                if (!isset($stockBurdenByProduct[$item->product_id])) {
                    $stockBurdenByProduct[$item->product_id] = [
                        'product_name' => $item->product_name,
                        'total_quantity' => 0,
                        'cost_price' => $costPrice,
                        'total_burden' => 0
                    ];
                }

                $stockBurdenByProduct[$item->product_id]['total_quantity'] += floatval($item->quantity);
                $stockBurdenByProduct[$item->product_id]['total_burden'] += $productBurden;

                // Update average cost price (weighted by quantity)
                if ($stockBurdenByProduct[$item->product_id]['total_quantity'] > 0) {
                    $stockBurdenByProduct[$item->product_id]['cost_price'] =
                        $stockBurdenByProduct[$item->product_id]['total_burden'] /
                        $stockBurdenByProduct[$item->product_id]['total_quantity'];
                }
            }

            // Sort by product name
            $sortedStockBurden = array_values($stockBurdenByProduct);
            usort($sortedStockBurden, function ($a, $b) {
                return strcmp($a['product_name'], $b['product_name']);
            });

            return [
                'total_stock_burden' => $totalStockBurden,
                'stock_burden_by_product' => $sortedStockBurden
            ];
        } catch (\Exception $e) {
            Log::error('Error in calculateStockBurden: ' . $e->getMessage());
            throw $e;
        }
    }
}
