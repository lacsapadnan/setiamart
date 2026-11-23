<?php

namespace App\Services;

use App\Models\Sell;
use App\Models\SellRetur;
use App\Models\SellReturDetail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class SellReturQueryService
{
    /**
     * @param  array{user_id?: mixed, from_date?: mixed, to_date?: mixed, warehouse?: mixed}  $filters
     */
    public function data(array $filters, bool $isMaster, int $authUserId, mixed $authWarehouseId): Collection
    {
        $userId = $filters['user_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $warehouse = $filters['warehouse'] ?? null;

        $defaultDate = now()->toDateString();

        if (! $fromDate) {
            $fromDate = $defaultDate;
        }

        if (! $toDate) {
            $toDate = $defaultDate;
        }

        if ($isMaster) {
            $retur = SellRetur::with(['sell.customer', 'product', 'warehouse', 'unit', 'user'])->orderBy('id', 'asc');
        } else {
            $retur = SellRetur::with(['sell.customer', 'product', 'warehouse', 'unit', 'user'])
                ->where('warehouse_id', $authWarehouseId)
                ->where('user_id', $authUserId)
                ->orderBy('id', 'asc');
        }

        if ($warehouse) {
            $retur->where('warehouse_id', $warehouse);
        }

        if ($userId) {
            $retur->where('user_id', $userId);
        }

        if ($fromDate && $toDate) {
            $endDate = Carbon::parse($toDate)->endOfDay();

            $retur->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        $retur = $retur->get();

        $retur->each(function ($sellRetur) {
            $sellRetur->returNumber = 'PJR-'.now()->format('Ymd').'-'.str_pad($sellRetur->id, 4, '0', STR_PAD_LEFT);

            // Handle null relationships
            if (! $sellRetur->sell) {
                $sellRetur->sell = (object) [
                    'order_number' => null,
                    'customer' => (object) ['name' => null],
                ];
            } elseif (! $sellRetur->sell->customer) {
                $sellRetur->sell->customer = (object) ['name' => null];
            }

            if (! $sellRetur->warehouse) {
                $sellRetur->warehouse = (object) ['name' => null];
            }

            if (! $sellRetur->user) {
                $sellRetur->user = (object) ['name' => null];
            }
        });

        return $retur;
    }

    public function dataBySaleId(int|string $saleId, bool $isMaster, mixed $authWarehouseId): Collection
    {
        $query = SellReturDetail::with(['product', 'unit', 'sellRetur'])
            ->whereHas('sellRetur', function ($q) use ($saleId) {
                $q->where('sell_id', $saleId);
            });

        if (! $isMaster) {
            $query->whereHas('sellRetur', function ($q) use ($authWarehouseId) {
                $q->where('warehouse_id', $authWarehouseId);
            });
        }

        $retur = $query->get();

        // Add remark from sellRetur to each detail
        $retur->each(function ($detail) {
            $detail->remark = $detail->sellRetur->remark;
            $detail->created_at = $detail->sellRetur->created_at;
            $detail->id = $detail->sellRetur->id;
        });

        return $retur;
    }

    public function dataDetail(int|string $id): Collection
    {
        return SellReturDetail::with('sellRetur', 'product', 'unit')->where('sell_retur_id', $id)->get();
    }

    /**
     * @param  array{cashier_id?: mixed, from_date?: mixed, to_date?: mixed, warehouse?: mixed}  $filters
     * @return mixed
     */
    public function dataSell(array $filters, bool $isMaster, mixed $authWarehouseId)
    {
        $userId = $filters['cashier_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $warehouse = $filters['warehouse'] ?? null;

        $defaultDate = now()->toDateString();

        if (! $fromDate) {
            $fromDate = $defaultDate;
        }

        if (! $toDate) {
            $toDate = $defaultDate;
        }

        // Hanya kolom yang dipakai DataTable list-penjualan (order_number, customer.name, warehouse.name, grand_total, id).
        $sells = Sell::select('id', 'order_number', 'grand_total', 'customer_id', 'warehouse_id')
            ->with('warehouse:id,name', 'customer:id,name')
            ->orderBy('id', 'desc');

        if (! $isMaster) {
            $sells->where('warehouse_id', $authWarehouseId);
        }

        if ($warehouse) {
            $sells->where('warehouse_id', $warehouse);
        }

        if ($userId) {
            $sells->where('cashier_id', $userId);
        }

        // whereBetween (bukan whereDate) supaya index created_at bisa dipakai.
        $sells->whereBetween('created_at', [
            Carbon::parse($fromDate)->startOfDay(),
            Carbon::parse($toDate)->endOfDay(),
        ]);

        // Server-side paging: hanya satu halaman yang dimuat ke memori, berapa pun rentang tanggalnya.
        return DataTables::of($sells)->make(true);
    }
}
