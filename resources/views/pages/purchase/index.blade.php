@extends('layouts.dashboard')

@section('title', 'Pembelian')
@section('menu-title', 'Pembelian')

@push('addon-style')
<link href="assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
<style>
    ::-webkit-scrollbar-thumb {
        -webkit-border-radius: 10px;
        border-radius: 10px;
        background: rgba(192, 192, 192, 0.3);
        -webkit-box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.5);
        background-color: #818B99;
    }
</style>
@endpush

@include('includes.datatable-pagination')

@section('content')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="gap-2 py-5 card-header align-items-center gap-md-5">
        <div class="card-title">
            <!--begin::Search-->
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-magnifier fs-1 position-absolute ms-4"><span class="path1"></span><span
                        class="path2"></span></i> <input type="text" data-kt-filter="search"
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data pembelian">
            </div>
            <!--end::Search-->
            @role('master')
            <div class="ms-2">
                <select id="warehouseFilter" class="form-select" aria-label="Warehouse filter" data-control="select2">
                    <option value="">All Cabang</option>
                    @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <div class="ms-2">
                <input type="text" id="warehouseFilter" class="form-control" value="{{ auth()->user()->warehouse_id }}"
                    disabled hidden>
                <input type="text" class="form-control" value="{{ auth()->user()->warehouse->name }}" disabled>
            </div>
            @endrole
            @role('master')
            <div class="ms-3">
                <select id="userFilter" class="form-select" aria-label="User filter" data-control="select2">
                    <option value="">All Users</option>
                    @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <div class="ms-3">
                <input type="text" id="userFilter" class="form-control" value="{{ auth()->id() }}" disabled hidden>
                <input type="text" class="form-control" value="{{ auth()->user()->name }}" disabled>
            </div>
            @endrole
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-calendar fs-1 position-absolute ms-4"></i>
                <input type="date" id="fromDateFilter" class="form-control form-control-solid ms-2"
                    data-kt-filter="date" placeholder="Dari Tanggal">
                <input type="date" id="toDateFilter" class="form-control form-control-solid ms-2" data-kt-filter="date"
                    placeholder="Ke Tanggal">
            </div>
        </div>
        <div class="gap-5 card-toolbar flex-row-fluid justify-content-end">
            <!--begin::Export dropdown-->
            <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click"
                data-kt-menu-placement="bottom-end">
                <i class="ki-duotone ki-exit-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                Export Data
            </button>
            @can('simpan pembelian')
            <a href="{{ route('pembelian.create') }}" type="button" class="btn btn-primary">
                Tambah Pembelian
            </a>
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
                            <th>Faktur Supplier</th>
                            <th>No. Order</th>
                            <th>Tanggal Terima</th>
                            <th>Kasir</th>
                            <th>Supplier</th>
                            <th>Cabang</th>
                            <th>Kas</th>
                            <th>Metode Bayar</th>
                            <th>Cash</th>
                            <th>Transfer</th>
                            <th>Subtotal</th>
                            <th>PPN</th>
                            <th>Potongan</th>
                            <th>Grand Total</th>
                            <th>Bayar</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
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
@includeIf('pages.purchase.modal')
@endsection

@push('addon-script')
<script src="assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script>
    "use strict";

        // Class definition
        var KTDatatablesExample = function() {
            // Shared variables
            var table;
            var datatable;

            // Private functions
            var initDatatable = function() {
                // Set date data order
                const tableRows = table.querySelectorAll('tbody tr');

                // Init datatable --- more info on datatables: https://datatables.net/manual/
                datatable = $(table).DataTable({
                    "info": false,
                    'order': [],
                    'pageLength': 10,
                    "fixedColumns": {
                        "rightColumns": 1
                    },
                    "dom": '<"top"lp>rt<"bottom"lp><"clear">',
                    "ajax": {
                        url: '{{ route('api.pembelian') }}',
                        type: 'GET',
                        dataSrc: '',
                    },
                    "columns": [{
                            "data": "invoice"
                        },
                        {
                            "data": "order_number"
                        },
                        {
                            "data": "reciept_date",
                        },
                        {
                            "data": "user.name",
                            defaultContent: '-'
                        },
                        {
                            "data": "supplier.name"
                        },
                        {
                            "data": "warehouse.name",
                            defaultContent: '-'
                        },
                        {
                            "data": "treasury.name",
                            defaultContent: '-'
                        },
                        {
                            "data": "payment_method",
                            defaultContent: '-'
                        },
                        {
                            "data": "cash",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(  data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        {
                            "data": "transfer",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        {
                            "data": "subtotal",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        {
                            "data": "tax",
                            render: function(data, type, row) {
                                return data + '%';
                            }
                        },
                        {
                            "data": "potongan",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        {
                            "data": "grand_total",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        {
                            "data": "pay",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        {
                            "data": "description",
                            "render": function(data, type, row) {
                                return data ? data : '-';
                            }
                        },
                        {
                            "data": "status",
                            "render": function(data, type, row) {
                                if (data == 'hutang') {
                                    return `<span class="badge badge-light-danger">Hutang</span>`;
                                } else {
                                    return `<span class="badge badge-light-primary">Lunas</span>`;
                                }
                            }
                        },
                        {
                            "data": "id",
                            "render": function(data, type, row) {
                                return `
                                    <a href="#" class="btn btn-sm btn-primary" onclick="openModal(${data})">Detail</a>
                                    @can('update pembelian')
                                        <a href="/pembelian/${data}/edit" class="btn btn-sm btn-warning">Edit</a>
                                    @endcan
                                    @can('hapus pembelian')
                                    <form action="/pembelian/${data}" method="POST" class="d-inline">
                                        @csrf
                                        @method('delete')
                                        <button class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus data?')">Hapus</button>
                                    </form>
                                    @endcan
                                `;
                            }
                        },

                    ],
                    columnDefs: [{
                        targets: -1,
                        className: 'min-w-250px'
                    }, ],
                });

                $('#fromDateFilter, #toDateFilter, #warehouseFilter, #userFilter').on('change', function() {
                    var fromDate = $('#fromDateFilter').val();
                    var toDate = $('#toDateFilter').val();
                    var warehouse_id = $('#warehouseFilter').val();
                    var user_id = $('#userFilter').val();

                    // Update the URL based on selected filters
                    var url = '{{ route('api.pembelian') }}';
                    var params = [];

                    if (fromDate) {
                        params.push('from_date=' + fromDate);
                    }

                    if (toDate) {
                        params.push('to_date=' + toDate);
                    }

                    if (warehouse_id) {
                        params.push('warehouse=' + warehouse_id);
                    }

                    if (user_id) {
                        params.push('user_id=' + user_id);
                    }

                    if (params.length > 0) {
                        url += '?' + params.join('&');
                    }

                    // Load data with updated URL
                    datatable.ajax.url(url).load();
                });
            }

            // Hook export buttons
            var exportButtons = () => {
                const documentTitle = 'Purchase Data Report';
                var buttons = new $.fn.dataTable.Buttons(table, {
                    buttons: [{
                            extend: 'copyHtml5',
                            title: documentTitle
                        },
                        {
                            extend: 'excelHtml5',
                            title: documentTitle
                        },
                        {
                            extend: 'csvHtml5',
                            title: documentTitle
                        },
                        {
                            extend: 'pdfHtml5',
                            title: documentTitle
                        }
                    ]
                }).container().appendTo($('#kt_datatable_example_buttons'));

                // Hook dropdown menu click event to datatable export buttons
                const exportButtons = document.querySelectorAll(
                    '#kt_datatable_example_export_menu [data-kt-export]');
                exportButtons.forEach(exportButton => {
                    exportButton.addEventListener('click', e => {
                        e.preventDefault();

                        // Get clicked export value
                        const exportValue = e.target.getAttribute('data-kt-export');

                        // Get current filter values
                        var fromDate = $('#fromDateFilter').val();
                        var toDate = $('#toDateFilter').val();
                        var warehouse_id = $('#warehouseFilter').val();
                        var user_id = $('#userFilter').val();
                        var searchValue = $('input[data-kt-filter="search"]').val();

                        // Log filter values for debugging
                        console.log('Export with filters:', {
                            from_date: fromDate,
                            to_date: toDate,
                            warehouse: warehouse_id,
                            user_id: user_id,
                            search: searchValue
                        });

                        // Build URL with filters and export flag
                        var url = '{{ route('api.pembelian') }}';
                        var params = ['export=true'];

                        if (fromDate) {
                            params.push('from_date=' + fromDate);
                        }

                        if (toDate) {
                            params.push('to_date=' + toDate);
                        }

                        if (warehouse_id) {
                            params.push('warehouse=' + warehouse_id);
                        }

                        if (user_id) {
                            params.push('user_id=' + user_id);
                        }

                        if (params.length > 0) {
                            url += '?' + params.join('&');
                        }

                        // Fetch all filtered data for export
                        $.ajax({
                            url: url,
                            type: 'GET',
                            success: function(response) {
                                // Log the response data count
                                console.log('Data received for export:', response.length);

                                // Create a temporary table with the filtered data
                                var tempTable = $('<table>').addClass('d-none');
                                var tempTableId = 'temp_export_table';
                                tempTable.attr('id', tempTableId);
                                $('body').append(tempTable);

                                // Initialize DataTable on the temporary table with the same columns
                                var exportDatatable = $('#' + tempTableId).DataTable({
                                    data: response,
                                    columns: datatable.settings().init().columns,
                                    buttons: [{
                                        extend: exportValue + 'Html5',
                                        title: documentTitle
                                    }]
                                });

                                // Trigger the export on the temporary table
                                exportDatatable.button('.buttons-' + exportValue).trigger();

                                // Destroy the temporary table after export
                                setTimeout(function() {
                                    exportDatatable.destroy();
                                    $('#' + tempTableId).remove();
                                }, 1000);
                            },
                            error: function(xhr, status, error) {
                                console.error('Error fetching export data:', error);
                            }
                        });
                    });
                });
            }

            // Search Datatable --- official docs reference: https://datatables.net/reference/api/search()
            var handleSearchDatatable = () => {
                const filterSearch = document.querySelector('[data-kt-filter="search"]');
                filterSearch.addEventListener('keyup', function(e) {
                    datatable.search(e.target.value).draw();
                });
            }

            // Public methods
            return {
                init: function() {
                    table = document.querySelector('#kt_datatable_example');

                    if (!table) {
                        return;
                    }

                    initDatatable();
                    exportButtons();
                    handleSearchDatatable();
                }
            };
        }();

        // On document ready
        KTUtil.onDOMContentLoaded(function() {
            KTDatatablesExample.init();
        });
</script>
<script>
    var datatable;
    var returDatatable;

    function openModal(id) {
        // Clear both table bodies
        $('#kt_datatable_detail tbody').empty();
        $('#kt_datatable_retur tbody').empty();

        // Remove any existing total displays
        $('#kt_datatable_detail').siblings('h2').remove();
        $('#kt_datatable_retur').siblings('h2').remove();

        // Check if DataTable instances exist and destroy them
        if ($.fn.DataTable.isDataTable('#kt_datatable_detail')) {
            datatable.destroy();
        }
        if ($.fn.DataTable.isDataTable('#kt_datatable_retur')) {
            returDatatable.destroy();
        }

        // Send a request to fetch the purchase details for the given ID
        $.ajax({
            url: '/pembelian/' + id + '?details_only=1',
            method: 'GET',
            success: function(response) {
                // Initialize the DataTable for purchase details
                datatable = $('#kt_datatable_detail').DataTable({
                    data: response,
                    columns: [
                        { data: 'product.name' },
                        { data: 'unit.name' },
                        { data: 'quantity' },
                        { data: 'price_unit', render: function(data) {
                            var formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(data);
                            return formattedPrice.replace(",00", "");
                        }},
                        { data: null, render: function(data) {
                            var subtotal = data.quantity * data.price_unit;
                            var formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(subtotal);
                            return formattedPrice.replace(",00", "");
                        }},
                        { data: 'discount_fix', render: function(data) {
                            var formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(data);
                            return formattedPrice.replace(",00", "");
                        }},
                        { data: 'discount_percent', render: function(data) {
                            return data + '%';
                        }},
                        { data: 'total_price', render: function(data) {
                            var formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(data);
                            return formattedPrice.replace(",00", "");
                        }}
                    ]
                });

                // Fetch and display returned products (retur detail)
                $.ajax({
                    url: '/pembelian-retur/api/dataByPurchaseId/' + id,
                    method: 'GET',
                    success: function(returResponse) {
                        // Initialize the DataTable for returned products
                        returDatatable = $('#kt_datatable_retur').DataTable({
                            data: returResponse,
                            columns: [
                                { data: null, render: function(data) {
                                    return "PBR-" + moment(data.created_at).format('YYYYMMDD') + "-" + String(data.id).padStart(4, '0');
                                }},
                                { data: 'product.name' },
                                { data: 'unit.name' },
                                { data: 'qty' },
                                { data: 'price', render: function(data) {
                                    var formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(data);
                                    return formattedPrice.replace(",00", "");
                                }},
                                { data: null, render: function(data) {
                                    var total = data.qty * data.price;
                                    var formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(total);
                                    return formattedPrice.replace(",00", "");
                                }},
                                { data: 'created_at', render: function(data) {
                                    return moment(data).format('DD MMMM YYYY');
                                }}
                            ]
                        });

                        // Calculate total retur
                        var totalRetur = returResponse.reduce((acc, item) => acc + (item.qty * item.price), 0);
                        var formattedTotalRetur = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(totalRetur).replace(",00", "");
                        $('#kt_datatable_retur').after(`<h2>Total Retur: ${formattedTotalRetur}</h2>`);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching return data:', error);
                    }
                });

                // Open the modal
                $('#kt_modal_1').modal('show');
            },
            error: function(xhr, status, error) {
                console.error(error); // Handle the error appropriately
            }
        });
    }
</script>
@endpush