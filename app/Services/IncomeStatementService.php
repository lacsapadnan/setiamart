<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncomeStatementService
{
    public function buildContext(array $validated, User $user): array
    {
        $userId = $validated['user_id'] ?? null;
        $fromDate = $validated['from_date'] ?? now()->format('Y-m-d');
        $toDate = $validated['to_date'] ?? now()->format('Y-m-d');
        $warehouseId = $validated['warehouse'] ?? null;
        $allBranches = (bool) ($validated['all_branches'] ?? $user->can('lihat semua laba rugi'));

        if (! $user->can('lihat semua laba rugi')) {
            $warehouseId = $user->warehouse_id;
            $userId = $user->id;
            $allBranches = false;
        }

        if ($allBranches) {
            $warehouseId = null;
        }

        return [
            'user_id' => $userId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'warehouse_id' => $warehouseId,
            'all_branches' => $allBranches,
            'auth_user' => $user->id,
        ];
    }

    public function snapshotKey(array $context): string
    {
        return 'income_statement_'.md5(serialize($context));
    }

    public function getSnapshot(array $context): ?array
    {
        return Cache::get($this->snapshotKey($context));
    }

    public function storePending(array $context): void
    {
        Cache::put($this->snapshotKey($context), [
            'status' => 'pending',
            'cache_generated_at' => now()->toISOString(),
        ], 300);
    }

    public function clearSnapshot(array $context): void
    {
        Cache::forget($this->snapshotKey($context));
    }

    public function generateSnapshot(array $context): array
    {
        $fromDate = $context['from_date'];
        $toDate = $context['to_date'];
        $warehouseId = $context['warehouse_id'];
        $userId = $context['user_id'];
        $allBranches = $context['all_branches'];
        $endDate = Carbon::parse($toDate)->endOfDay();

        $warehouse = $warehouseId ? Warehouse::select('id', 'name', 'isOutOfTown')->find($warehouseId) : null;
        $results = $this->calculateDataInParallel($fromDate, $endDate, $warehouseId, $userId, $warehouse, $allBranches);

        $salesData = $results['salesData'];
        $cogsData = $results['cogsData'];
        $operatingExpenses = $results['operatingExpenses'];
        $otherIncome = $results['otherIncome'];
        $stockBurden = $results['stockBurden'];

        $this->synchronizeProductData($salesData, $cogsData);

        $totalRevenue = (float) ($salesData['total_revenue'] ?? 0);
        $totalCogs = (float) ($cogsData['total_cogs'] ?? 0);
        $totalOtherIncome = (float) ($otherIncome['total_other_income'] ?? 0);
        $totalOperatingExpenses = (float) ($operatingExpenses['total_operating_expenses'] ?? 0);
        $totalStockBurden = (float) ($stockBurden['total_stock_burden'] ?? 0);

        $grossProfit = $totalRevenue - abs($totalCogs);
        $netIncome = $grossProfit - $totalStockBurden - $totalOperatingExpenses + $totalOtherIncome;
        $warehouseName = $warehouse ? $warehouse->name : ($allBranches ? 'Semua Cabang' : 'Semua Gudang');

        return [
            'status' => 'ready',
            'sales_data' => $salesData,
            'cogs_data' => [
                'total_cogs' => -abs($cogsData['total_cogs']),
                'cogs_by_product' => $cogsData['cogs_by_product'],
                'is_out_of_town' => $cogsData['is_out_of_town'] ?? false,
            ],
            'stock_burden' => $stockBurden,
            'operating_expenses' => $operatingExpenses,
            'other_income' => $otherIncome,
            'gross_profit' => $grossProfit,
            'net_income' => $netIncome,
            'period' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'warehouse' => $warehouseName,
            ],
            'cache_generated_at' => now()->toISOString(),
        ];
    }

    public function persistSnapshot(array $context, array $payload): void
    {
        Cache::put($this->snapshotKey($context), $payload, 300);
    }

    private function calculateDataInParallel($fromDate, $endDate, $warehouseId, $userId, $warehouse, $allBranches): array
    {
        return DB::transaction(function () use ($fromDate, $endDate, $warehouseId, $userId, $warehouse, $allBranches) {
            return [
                'salesData' => $this->calculateSalesRevenueOptimized($fromDate, $endDate, $warehouseId, $userId, $allBranches),
                'cogsData' => $this->calculateCostOfGoodsSoldOptimized($fromDate, $endDate, $warehouseId, $userId, $warehouse, $allBranches),
                'stockBurden' => $this->calculateStockBurden($warehouseId, $warehouse, $allBranches),
                'operatingExpenses' => $this->calculateOperatingExpensesOptimized($fromDate, $endDate, $warehouseId, $allBranches),
                'otherIncome' => $this->calculateOtherIncomeOptimized(),
            ];
        });
    }

    private function calculateSalesRevenueOptimized($fromDate, $endDate, $warehouseId, $userId, $allBranches = false): array
    {
        $query = DB::table('sells as s')
            ->join('sell_details as sd', 's.id', '=', 'sd.sell_id')
            ->join('products as p', 'sd.product_id', '=', 'p.id')
            ->where('s.status', 'lunas')
            ->whereBetween('s.created_at', [$fromDate, $endDate]);

        if ($warehouseId && ! $allBranches) {
            $query->where('s.warehouse_id', $warehouseId);
        }
        if ($userId) {
            $query->where('s.cashier_id', $userId);
        }

        $aggregatedData = $query->select(
            DB::raw('COUNT(DISTINCT s.id) as total_transactions'),
            'sd.product_id',
            'p.name as product_name',
            DB::raw('SUM(sd.quantity) as total_quantity'),
            DB::raw('SUM(sd.quantity * (sd.price - COALESCE(sd.diskon, 0))) as total_sales')
        )->groupBy('sd.product_id', 'p.name')->get();

        $salesData = ['total_revenue' => 0, 'total_transactions' => 0, 'sales_by_product' => []];
        foreach ($aggregatedData as $row) {
            $salesData['total_revenue'] += (float) $row->total_sales;
            $salesData['total_transactions'] = (int) $row->total_transactions;
            $salesData['sales_by_product'][$row->product_id] = [
                'product_name' => $row->product_name,
                'quantity_sold' => (float) $row->total_quantity,
                'total_revenue' => (float) $row->total_sales,
            ];
        }

        return $salesData;
    }

    private function calculateCostOfGoodsSoldOptimized($fromDate, $endDate, $warehouseId, $userId, $warehouse, $allBranches = false): array
    {
        $query = DB::table('sells as s')
            ->join('sell_details as sd', 's.id', '=', 'sd.sell_id')
            ->join('products as p', 'sd.product_id', '=', 'p.id')
            ->where('s.status', 'lunas')
            ->whereBetween('s.created_at', [$fromDate, $endDate]);

        if ($warehouseId && ! $allBranches) {
            $query->where('s.warehouse_id', $warehouseId);
        }
        if ($userId) {
            $query->where('s.cashier_id', $userId);
        }

        $salesData = $query->select(
            'sd.product_id',
            'p.name as product_name',
            'sd.quantity',
            'sd.unit_id',
            'p.lastest_price_eceran',
            'p.lastest_price_eceran_out_of_town',
            's.warehouse_id'
        )->get();

        $totalCogs = 0;
        $cogsByProduct = [];

        foreach ($salesData as $item) {
            $itemWarehouse = Warehouse::find($item->warehouse_id);
            $isOutOfTown = $itemWarehouse ? (bool) ($itemWarehouse->isOutOfTown ?? false) : false;
            $costPrice = $isOutOfTown
                ? (float) ($item->lastest_price_eceran_out_of_town ?? $item->lastest_price_eceran ?? 0)
                : (float) ($item->lastest_price_eceran ?? 0);

            $product = Product::find($item->product_id);
            $quantityInEceran = $this->convertToEceran($item->quantity, $item->unit_id, $product);
            $itemTotalCost = $quantityInEceran * $costPrice;
            $totalCogs += $itemTotalCost;

            if (! isset($cogsByProduct[$item->product_id])) {
                $cogsByProduct[$item->product_id] = [
                    'product_name' => $item->product_name,
                    'quantity_sold_eceran' => 0,
                    'total_cogs' => 0,
                    'cost_price' => $costPrice,
                ];
            }

            $cogsByProduct[$item->product_id]['quantity_sold_eceran'] += $quantityInEceran;
            $cogsByProduct[$item->product_id]['total_cogs'] += $itemTotalCost;
            if ($cogsByProduct[$item->product_id]['quantity_sold_eceran'] > 0) {
                $cogsByProduct[$item->product_id]['cost_price'] =
                    $cogsByProduct[$item->product_id]['total_cogs'] / $cogsByProduct[$item->product_id]['quantity_sold_eceran'];
            }
        }

        return [
            'total_cogs' => $totalCogs,
            'cogs_by_product' => $cogsByProduct,
            'is_out_of_town' => $warehouse ? ($warehouse->isOutOfTown ?? false) : false,
        ];
    }

    private function calculateOperatingExpensesOptimized($fromDate, $endDate, $warehouseId, $allBranches = false): array
    {
        $query = DB::table('kas as k')
            ->leftJoin('kas_expense_items as kei', 'k.kas_expense_item_id', '=', 'kei.id')
            ->select('k.date', 'k.invoice', 'k.description', 'k.amount', 'kei.name as category_name', 'k.kas_expense_item_id')
            ->where('k.type', 'Kas Keluar')
            ->whereBetween('k.date', [$fromDate, $endDate]);

        if ($warehouseId && ! $allBranches) {
            $query->where('k.warehouse_id', $warehouseId);
        }

        $allExpenses = collect();
        foreach ($query->cursor() as $expense) {
            $allExpenses->push($expense);
        }

        $filteredExpenses = $allExpenses->filter(function ($expense) {
            if (is_null($expense->kas_expense_item_id) || is_null($expense->category_name)) {
                return true;
            }
            $categoryUpper = strtoupper($expense->category_name);

            return $categoryUpper !== 'LAIN LAIN' && $categoryUpper !== 'LAIN-LAIN';
        });

        return [
            'total_operating_expenses' => $filteredExpenses->sum(fn ($e) => (float) ($e->amount ?? 0)),
            'expenses_by_category' => $filteredExpenses->groupBy(fn ($e) => $e->category_name ?? 'Lainnya')->map(fn ($rows, $name) => [
                'category' => $name,
                'total_amount' => $rows->sum(fn ($e) => (float) ($e->amount ?? 0)),
                'count' => $rows->count(),
            ])->values()->toArray(),
            'expense_details' => $filteredExpenses->map(fn ($e) => [
                'date' => $e->date,
                'invoice' => $e->invoice ?? '-',
                'description' => $e->description ?? '-',
                'category' => $e->category_name ?? 'Lainnya',
                'amount' => (float) ($e->amount ?? 0),
            ])->sortByDesc('date')->values()->toArray(),
        ];
    }

    private function calculateOtherIncomeOptimized(): array
    {
        return ['total_other_income' => 0, 'income_by_category' => [], 'income_details' => []];
    }

    private function calculateStockBurden($warehouseId, $warehouse, $allBranches = false): array
    {
        try {
            $query = DB::table('inventories as i')
                ->join('products as p', 'i.product_id', '=', 'p.id')
                ->join('warehouses as w', 'i.warehouse_id', '=', 'w.id')
                ->where('i.quantity', '>', 0)
                ->where('p.isShow', true)
                ->select('i.product_id', 'p.name as product_name', 'i.quantity', 'p.lastest_price_eceran', 'p.lastest_price_eceran_out_of_town', 'w.isOutOfTown', 'i.warehouse_id');

            if ($warehouseId && ! $allBranches) {
                $query->where('i.warehouse_id', $warehouseId);
            }

            $inventoryData = $query->get();
            $totalStockBurden = 0;
            $stockBurdenByProduct = [];
            foreach ($inventoryData as $item) {
                $isOutOfTown = (bool) ($item->isOutOfTown ?? false);
                $costPrice = $isOutOfTown
                    ? (float) ($item->lastest_price_eceran_out_of_town ?? $item->lastest_price_eceran ?? 0)
                    : (float) ($item->lastest_price_eceran ?? 0);
                $productBurden = (float) $item->quantity * $costPrice;
                $totalStockBurden += $productBurden;
                if (! isset($stockBurdenByProduct[$item->product_id])) {
                    $stockBurdenByProduct[$item->product_id] = [
                        'product_name' => $item->product_name,
                        'total_quantity' => 0,
                        'cost_price' => $costPrice,
                        'total_burden' => 0,
                    ];
                }
                $stockBurdenByProduct[$item->product_id]['total_quantity'] += (float) $item->quantity;
                $stockBurdenByProduct[$item->product_id]['total_burden'] += $productBurden;
            }

            $sorted = array_values($stockBurdenByProduct);
            usort($sorted, fn ($a, $b) => strcmp($a['product_name'], $b['product_name']));

            return ['total_stock_burden' => $totalStockBurden, 'stock_burden_by_product' => $sorted];
        } catch (\Exception $e) {
            Log::error('Error in calculateStockBurden: '.$e->getMessage());
            throw $e;
        }
    }

    private function convertToEceran($quantity, $unitId, $product): float
    {
        if (! $product) {
            return 0;
        }
        $safeQuantity = (float) ($quantity ?? 0);
        if ($safeQuantity <= 0) {
            return 0;
        }

        if ($product->unit_dus == $unitId) {
            return $safeQuantity * ((float) ($product->dus_to_eceran ?? 1) ?: 1);
        }
        if ($product->unit_pak == $unitId) {
            return $safeQuantity * ((float) ($product->pak_to_eceran ?? 1) ?: 1);
        }

        return $safeQuantity;
    }

    private function synchronizeProductData(array &$salesData, array &$cogsData): void
    {
        $validProducts = [];
        foreach ($salesData['sales_by_product'] as $productId => $product) {
            if ($product['quantity_sold'] > 0 || $product['total_revenue'] > 0) {
                $validProducts[$productId] = $product['product_name'];
            }
        }
        foreach ($cogsData['cogs_by_product'] as $productId => $product) {
            if ($product['quantity_sold_eceran'] > 0 || $product['total_cogs'] > 0) {
                $validProducts[$productId] = $product['product_name'];
            }
        }

        $filteredSalesData = [];
        $filteredCogsData = [];
        foreach ($validProducts as $productId => $productName) {
            $filteredSalesData[$productId] = $salesData['sales_by_product'][$productId] ?? [
                'product_name' => $productName,
                'quantity_sold' => 0,
                'total_revenue' => 0,
            ];
            $filteredCogsData[$productId] = $cogsData['cogs_by_product'][$productId] ?? [
                'product_name' => $productName,
                'quantity_sold_eceran' => 0,
                'cost_price' => 0,
                'total_cogs' => 0,
            ];
        }

        $sortedSales = array_values($filteredSalesData);
        $sortedCogs = array_values($filteredCogsData);
        usort($sortedSales, fn ($a, $b) => strcmp($a['product_name'], $b['product_name']));
        usort($sortedCogs, fn ($a, $b) => strcmp($a['product_name'], $b['product_name']));
        $salesData['sales_by_product'] = $sortedSales;
        $cogsData['cogs_by_product'] = $sortedCogs;
    }
}
