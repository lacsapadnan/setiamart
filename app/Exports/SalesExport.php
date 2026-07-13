<?php

namespace App\Exports;

use App\Models\Sell;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SalesExport implements FromQuery, WithColumnFormatting, WithCustomChunkSize, WithHeadings, WithMapping
{
    use Exportable;

    protected ?string $fromDate;

    protected ?string $toDate;

    protected ?int $warehouseId;

    protected ?int $userId;

    protected ?string $search;

    protected ?int $restrictWarehouseId;

    protected ?int $restrictCashierId;

    public function __construct(array $filters = [])
    {
        $this->fromDate = $filters['from_date'] ?? null;
        $this->toDate = $filters['to_date'] ?? null;
        $this->warehouseId = isset($filters['warehouse']) && $filters['warehouse'] !== '' ? (int) $filters['warehouse'] : null;
        $this->userId = isset($filters['user_id']) && $filters['user_id'] !== '' ? (int) $filters['user_id'] : null;
        $this->search = $filters['search'] ?? null;

        // Restrictions applied for non-master users
        $this->restrictWarehouseId = $filters['restrict_warehouse_id'] ?? null;
        $this->restrictCashierId = $filters['restrict_cashier_id'] ?? null;
    }

    public function query(): Builder
    {
        $query = Sell::query()
            ->select([
                'sells.id',
                'sells.order_number',
                'sells.payment_method',
                'sells.cash',
                'sells.transfer',
                'sells.grand_total',
                'sells.status',
                'sells.created_at',
                'cashier.name as cashier_name',
                'customers.name as customer_name',
                'warehouses.name as warehouse_name',
            ])
            ->leftJoin('users as cashier', 'cashier.id', '=', 'sells.cashier_id')
            ->leftJoin('customers', 'customers.id', '=', 'sells.customer_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'sells.warehouse_id')
            ->where('sells.status', '!=', 'draft')
            ->orderBy('sells.id', 'desc');

        // Apply user restrictions for non-master users
        if ($this->restrictWarehouseId) {
            $query->where('sells.warehouse_id', $this->restrictWarehouseId);
        }
        if ($this->restrictCashierId) {
            $query->where('sells.cashier_id', $this->restrictCashierId);
        }

        // Apply optional filters
        if ($this->warehouseId) {
            $query->where('sells.warehouse_id', $this->warehouseId);
        }
        if ($this->userId) {
            $query->where('sells.cashier_id', $this->userId);
        }
        if ($this->fromDate && $this->toDate) {
            $endDate = Carbon::parse($this->toDate)->endOfDay();
            $query->whereBetween('sells.created_at', [$this->fromDate, $endDate]);
        }

        // Apply search filter
        if (! empty($this->search)) {
            $searchValue = $this->search;
            $query->where(function (Builder $subQuery) use ($searchValue) {
                $subQuery->where('sells.order_number', 'like', "%{$searchValue}%")
                    ->orWhere('sells.payment_method', 'like', "%{$searchValue}%")
                    ->orWhere('sells.status', 'like', "%{$searchValue}%")
                    ->orWhere('cashier.name', 'like', "%{$searchValue}%")
                    ->orWhere('customers.name', 'like', "%{$searchValue}%")
                    ->orWhere('warehouses.name', 'like', "%{$searchValue}%");
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'No. Order',
            'Kasir',
            'Customer',
            'Cabang',
            'Metode Pembayaran',
            'Cash',
            'Transfer',
            'Total Penjualan',
            'Status',
            'Tanggal',
        ];
    }

    public function map($row): array
    {
        return [
            $row->order_number,
            $row->cashier_name ?? '',
            $row->customer_name ?? '',
            $row->warehouse_name ?? '',
            $row->payment_method ?? '',
            $this->roundCurrency($row->cash ?? 0),
            $this->roundCurrency($row->transfer ?? 0),
            $this->roundCurrency($row->grand_total ?? 0),
            $row->status ?? '',
            // ↓ aman untuk string/null
            ! empty($row->created_at) ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s') : '',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Cash
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Transfer
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Grand Total
        ];
    }

    /**
     * Define chunk size for processing large datasets
     * Smaller chunks for better memory management and timeout prevention
     */
    public function chunkSize(): int
    {
        return 1000; // Reduced from 2000 to 500 for better performance
    }

    private function roundCurrency(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 0);
        }

        $normalized = str_replace(',', '', (string) $value);
        if (! is_numeric($normalized)) {
            return 0.0;
        }

        return round((float) $normalized, 0);
    }
}
