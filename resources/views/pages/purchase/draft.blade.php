@extends('layouts.dashboard')

@section('title', 'Draft Pembelian')
@section('menu-title', 'Draft Pembelian')

@push('addon-style')
<link href="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
@endpush

@section('content')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="gap-2 py-5 card-header align-items-center gap-md-5">
        <div class="card-title">
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-magnifier fs-1 position-absolute ms-4"><span class="path1"></span><span class="path2"></span></i>
                <input type="text" data-kt-filter="search" class="form-control form-control-solid w-250px ps-14" placeholder="Cari draft pembelian">
            </div>
        </div>
        <div class="gap-5 card-toolbar flex-row-fluid justify-content-end">
            @can('simpan pembelian')
            <a href="{{ route('pembelian.create') }}" type="button" class="btn btn-primary">
                Tambah Pembelian
            </a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <div id="kt_datatable_example_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
            <div class="table-responsive">
                <table class="table align-middle rounded border table-row-dashed fs-6 g-5 dataTable no-footer" id="kt_datatable_example">
                    <thead>
                        <tr class="text-start fw-bold fs-7 text-uppercase">
                            <th>No. Order</th>
                            <th>Supplier</th>
                            <th>Cabang</th>
                            <th>Total Pembelian</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 fw-semibold">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('addon-script')
<script src="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
<script>
    "use strict";

    var KTPurchaseDraftDatatable = function() {
        var table;
        var datatable;

        var initDatatable = function() {
            datatable = $(table).DataTable({
                info: false,
                order: [],
                pageLength: 10,
                ajax: {
                    url: '{{ route('api.pembelian-draft') }}',
                    type: 'GET',
                    dataSrc: '',
                },
                columns: [
                    { data: 'order_number' },
                    { data: 'supplier.name', defaultContent: '-' },
                    { data: 'warehouse.name', defaultContent: '-' },
                    {
                        data: 'grand_total',
                        render: function(data) {
                            return new Intl.NumberFormat('id-ID', {
                                style: 'currency',
                                currency: 'IDR'
                            }).format(data).replace(',00', '');
                        }
                    },
                    {
                        data: 'id',
                        render: function(data) {
                            return `
                                <a href="/pembelian-draft/${data}" class="btn btn-sm btn-primary">Lanjutkan Transaksi</a>
                                <form action="/pembelian-draft/${data}" method="POST" class="d-inline">
                                    @method('delete')
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                </form>
                            `;
                        }
                    },
                ],
            });
        };

        var handleSearchDatatable = function() {
            const filterSearch = document.querySelector('[data-kt-filter="search"]');
            filterSearch.addEventListener('keyup', function(e) {
                datatable.search(e.target.value).draw();
            });
        };

        return {
            init: function() {
                table = document.querySelector('#kt_datatable_example');

                if (!table) {
                    return;
                }

                initDatatable();
                handleSearchDatatable();
            }
        };
    }();

    KTUtil.onDOMContentLoaded(function() {
        KTPurchaseDraftDatatable.init();
    });
</script>
@endpush
