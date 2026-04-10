@extends('layouts.dashboard')

@section('title', 'Inventori')
@section('menu-title', 'Inventori')

@push('addon-style')
<link href="{{ URL::asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet"
    type="text/css" />
<style>
    ::-webkit-scrollbar-thumb {
        -webkit-border-radius: 10px;
        border-radius: 10px;
        background: rgba(192, 192, 192, 0.3);
        -webkit-box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.5);
        background-color: #818B99;
    }

    .dataTables_scrollBody {
        transform: rotateX(180deg);
    }

    .dataTables_scrollBody::-webkit-scrollbar {
        height: 16px;
    }

    .dataTables_scrollBody table {
        transform: rotateX(180deg);
    }
</style>
@endpush

@include('includes.datatable-pagination')

@section('content')
@include('components.alert')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="gap-2 py-5 card-header align-items-center gap-md-5">
        <div class="card-title">
            <!--begin::Search-->
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-magnifier fs-1 position-absolute ms-4"><span class="path1"></span><span
                        class="path2"></span></i> <input type="text" data-kt-filter="search"
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data inventori">
            </div>
            <select id="categoryFilter" class="form-select ms-3" aria-label="Category filter" data-control="select2">
                <option value="">All Kelompok</option>
                @foreach ($categories as $category)
                <option value="{{ $category->name }}">{{ $category->name }}</option>
                @endforeach
            </select>
            @role('master')
            <select id="warehouseFilter" class="form-select ms-4" aria-label="Branch filter" data-control="select2">
                <option value="">All Cabangs</option>
                @foreach ($warehouse as $item)
                <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
            @endrole
            <!--end::Search-->
        </div>
        <div class="gap-5 card-toolbar flex-row-fluid justify-content-end">
            <!--begin::Export dropdown-->
            <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click"
                data-kt-menu-placement="bottom-end">
                <i class="ki-duotone ki-exit-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                Export Data
            </button>
            @can('simpan inventory')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_1">
                Tambah Data
            </button>
            @endcan
            <!--begin::Menu-->
            <div id="kt_datatable_example_export_menu"
                class="py-4 menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px"
                data-kt-menu="true">
                <!--begin::Menu item-->
                <div class="px-3 menu-item">
                    <a href="#" class="px-3 menu-link" data-kt-export="copy">
                        Copy to clipboard
                    </a>
                </div>
                <!--end::Menu item-->
                <!--begin::Menu item-->
                <div class="px-3 menu-item">
                    <a href="#" class="px-3 menu-link" data-kt-export="excel">
                        Export as Excel
                    </a>
                </div>
                <!--end::Menu item-->
                <!--begin::Menu item-->
                <div class="px-3 menu-item">
                    <a href="#" class="px-3 menu-link" data-kt-export="csv">
                        Export as CSV
                    </a>
                </div>
                <!--end::Menu item-->
                <!--begin::Menu item-->
                <div class="px-3 menu-item">
                    <a href="#" class="px-3 menu-link" data-kt-export="pdf">
                        Export as PDF
                    </a>
                </div>
                <!--end::Menu item-->
            </div>
            <div id="kt_datatable_example_buttons" class="d-none"></div>
        </div>
    </div>
    <div class="card-body">
        <div id="kt_datatable_example_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
            <div class="table-responsive">
                <table class="table align-middle rounded border table-row-dashed fs-6 g-5 dataTable no-footer"
                    id="kt_datatable_example">
                    <thead>
                        <tr class="text-gray-400 text-start fw-bold fs-7 text-uppercase">
                            <th>Cabang</th>
                            <th>Kelompok</th>
                            <th>Nama Barang</th>
                            <th>Jml Per Dus</th>
                            <th>Jml Per Pak</th>
                            <th>Stok</th>
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
@includeIf('pages.inventory.modal')
@includeIf('pages.inventory.edit')
@endsection

@push('addon-script')
<script src="assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script>
    $(function () {
        const canUpdateInventory = @json(auth()->user()->can('update inventory'));
        const canDeleteInventory = @json(auth()->user()->can('hapus inventory'));
        const csrfToken = '{{ csrf_token() }}';

        const dataTable = $('#kt_datatable_example').DataTable({
            serverSide: true,
            processing: true,
            order: [],
            pageLength: 10,
            dom: '<"top"lp>rt<"bottom"lp><"clear">',
            ajax: {
                url: '{{ route('api.inventori') }}',
                type: 'GET',
                data: function (d) {
                    d.category = $('#categoryFilter').val() || '';
                    d.warehouse_id = $('#warehouseFilter').val() || '';
                }
            },
            columns: [
                { data: 'warehouse.name', name: 'warehouse.name', defaultContent: '' },
                { data: 'product.group', name: 'product.group', defaultContent: '' },
                { data: 'product.name', name: 'product.name', defaultContent: '' },
                { data: 'product.dus_to_eceran', name: 'product.dus_to_eceran', defaultContent: '' },
                { data: 'product.pak_to_eceran', name: 'product.pak_to_eceran', defaultContent: '' },
                { data: 'quantity', name: 'quantity', defaultContent: 0 },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function (data) {
                        let html = '';
                        if (canUpdateInventory) {
                            html += '<button type="button" class="btn btn-sm btn-primary edit-button" data-id="' + data + '" data-toggle="modal" data-target="#editModal">Edit</button> ';
                        }
                        if (canDeleteInventory) {
                            html += '<form action="/inventori/' + data + '" method="POST" class="d-inline">';
                            html += '<input type="hidden" name="_token" value="' + csrfToken + '">';
                            html += '<input type="hidden" name="_method" value="DELETE">';
                            html += '<button type="submit" class="btn btn-sm btn-danger">Hapus</button>';
                            html += '</form>';
                        }
                        return html;
                    }
                }
            ],
            buttons: [
                { extend: 'copyHtml5', title: 'Inventory Data Report', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                { extend: 'excelHtml5', title: 'Inventory Data Report', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                { extend: 'csvHtml5', title: 'Inventory Data Report', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                { extend: 'pdfHtml5', title: 'Inventory Data Report', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } }
            ]
        });

        dataTable.buttons().container().appendTo('#kt_datatable_example_buttons');

        $('[data-kt-filter="search"]').on('keyup', function () {
            dataTable.search(this.value).draw();
        });

        $('#categoryFilter, #warehouseFilter').on('change', function () {
            dataTable.ajax.reload();
        });

        $('#kt_datatable_example_export_menu [data-kt-export="copy"]').on('click', function (e) {
            e.preventDefault();
            dataTable.button('.buttons-copy').trigger();
        });
        $('#kt_datatable_example_export_menu [data-kt-export="excel"]').on('click', function (e) {
            e.preventDefault();
            dataTable.button('.buttons-excel').trigger();
        });
        $('#kt_datatable_example_export_menu [data-kt-export="csv"]').on('click', function (e) {
            e.preventDefault();
            dataTable.button('.buttons-csv').trigger();
        });
        $('#kt_datatable_example_export_menu [data-kt-export="pdf"]').on('click', function (e) {
            e.preventDefault();
            dataTable.button('.buttons-pdf').trigger();
        });

        $(document).on('click', '.edit-button', function () {
            const id = $(this).data('id');
            $.ajax({
                url: '/inventori/' + id + '/edit',
                method: 'GET',
                success: function (response) {
                    $('#editInventoryId').val(response.id);
                    $('#productInput').val(response.product_id).trigger('change');
                    $('#cabangInput').val(response.warehouse_id).trigger('change');
                    $('#quantityInput').val(response.quantity);
                    $('#editForm').attr('action', '/inventori/' + id);
                    $('#editModal').modal('show');
                },
                error: function (error) {
                    console.error('Error fetching data:', error);
                }
            });
        });
    });
</script>
@endpush