@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('menu-title', 'Dashboard')
@section('content')
@include('components.alert')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <h2>Selamat datang, {{ auth()->user()->name }}</h2>
    <h3>Cabang {{ auth()->user()->warehouse->name }}</h3>
</div>

<div class="mt-5">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top 10 Produk Terlaris (90 Hari Terakhir)</h3>
            @if ($isMaster)
            <div class="card-toolbar">
                <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center">
                    <label class="mb-0 form-label me-3">Filter Cabang:</label>
                    <select name="warehouse_id" class="form-select w-200px" data-control="select2"
                        onchange="this.form.submit()">
                        <option value="all" {{ $warehouseId=='all' ? 'selected' : '' }}>
                            Semua Cabang
                        </option>
                        @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ $warehouse->id == $warehouseId ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                        @endforeach
                    </select>
                </form>
            </div>
            @endif
        </div>
        <div class="card-body">
            @if ($topProducts->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr class="text-gray-800 fw-bold fs-6">
                            <th>Rank</th>
                            <th>Nama Produk</th>
                            @if ($warehouseId == 'all')
                            <th>Cabang</th>
                            @endif
                            <th>Total Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topProducts as $index => $product)
                        <tr>
                            <td>
                                <span class="badge badge-light-primary fs-7">{{ $index + 1 }}</span>
                            </td>
                            <td>{{ $product->product_name ?? 'Unknown' }}</td>
                            @if ($warehouseId == 'all')
                            <td>
                                <span class="badge badge-light-info">{{ $product->warehouse_name ?? 'Unknown' }}</span>
                            </td>
                            @endif
                            <td>
                                <span class="badge badge-light-success">{{ number_format($product->total_sold) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="py-5 text-center">
                <div class="text-gray-600">
                    <i class="mb-3 fas fa-box fs-3x"></i>
                    <p class="fs-4">Belum ada data penjualan</p>
                    @if ($isMaster)
                    <p class="fs-6 text-muted">
                        @if ($warehouseId == 'all')
                        untuk semua cabang
                        @else
                        untuk cabang {{ $warehouses->where('id', $warehouseId)->first()->name ?? 'ini' }}
                        @endif
                    </p>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
