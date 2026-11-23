<?php

namespace App\Services;

use App\Models\Sell;
use Carbon\Carbon;
use DataTables;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SellQueryService
{
    /**
     * @param  array{user_id?: mixed, from_date?: mixed, to_date?: mixed, warehouse?: mixed, search?: mixed}  $filters
     * @return mixed
     */
    public function data(array $filters, bool $isMaster, int $authUserId, mixed $authWarehouseId)
    {
        try {
            $userId = $filters['user_id'] ?? null;
            $fromDate = $filters['from_date'] ?? now()->toDateString();
            $toDate = $filters['to_date'] ?? now()->toDateString();
            $warehouse = $filters['warehouse'] ?? null;
            $search = $filters['search'] ?? null;

            $query = Sell::with([
                'warehouse',
                'customer',
                'cashier',
                'details.product.unit_dus',
                'details.product.unit_pak',
                'details.product.unit_eceran',
                'details' => function ($query) {
                    $query->whereNotNull('unit_id');
                },
                'details.unit',
            ])
                ->where('status', '!=', 'draft')
                ->orderBy('id', 'desc');

            if (! $isMaster) {
                $query->where('warehouse_id', $authWarehouseId)
                    ->where('cashier_id', $authUserId);
            }

            if ($warehouse) {
                $query->where('warehouse_id', $warehouse);
            }

            if ($userId) {
                $query->where('cashier_id', $userId);
            }

            if ($fromDate && $toDate) {
                $endDate = Carbon::parse($toDate)->endOfDay();
                $query->whereBetween('created_at', [$fromDate, $endDate]);
            }

            if (! empty($search) && ! empty($search['value'])) {
                $searchValue = $search['value'];
                $query->where(function ($q) use ($searchValue) {
                    $q->where('order_number', 'like', "%{$searchValue}%")
                        ->orWhereHas('customer', function ($q) use ($searchValue) {
                            $q->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('warehouse', function ($q) use ($searchValue) {
                            $q->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhereHas('cashier', function ($q) use ($searchValue) {
                            $q->where('name', 'like', "%{$searchValue}%");
                        })
                        ->orWhere('payment_method', 'like', "%{$searchValue}%")
                        ->orWhere('status', 'like', "%{$searchValue}%");
                });
            }

            return DataTables::of($query)
                ->make(true);
        } catch (\Throwable $e) {
            report($e);
            Log::error('SellQueryService data method error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'filters' => $filters,
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Terjadi kesalahan saat mengambil data penjualan.',
            ], 500);
        }
    }

    /**
     * @param  array{user_id?: mixed, from_date?: mixed, to_date?: mixed, warehouse?: mixed}  $filters
     */
    public function dataCredit(array $filters, bool $isMaster, mixed $authWarehouseId): Collection
    {
        $userId = $filters['user_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $warehouse = $filters['warehouse'] ?? null;

        $query = Sell::with([
            'warehouse',
            'customer',
            'cashier',
            'details.product.unit_dus',
            'details.product.unit_pak',
            'details.product.unit_eceran',
            'details.unit',
        ])->where('status', 'piutang');

        if (! $isMaster) {
            $query->where('warehouse_id', $authWarehouseId);
        }

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse);
        }

        if ($userId) {
            $query->where('cashier_id', $userId);
        }

        if ($fromDate && $toDate) {
            $endDate = Carbon::parse($toDate)->endOfDay();
            $query->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        return $query->get();
    }
}
