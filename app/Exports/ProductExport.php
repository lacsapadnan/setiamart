<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromQuery, WithHeadings
{
    // FromQuery: dibaca per chunk (excel.exports.chunk_size), bukan seluruh produk sekaligus.
    public function query()
    {
        return Product::select(array_diff(
            Schema::getColumnListing('products'),
            ['created_at', 'updated_at']
        ))->orderBy('id');
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $columns = array_diff(
            Schema::getColumnListing('products'),
            ['created_at', 'updated_at']
        );

        // Convert column names to readable headers
        return array_map(function ($column) {
            return ucwords(str_replace('_', ' ', $column));
        }, $columns);
    }
}
