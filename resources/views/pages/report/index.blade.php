@extends('layouts.dashboard')

@section('title', 'Laporan')
@section('menu-title', 'Laporan')

@push('addon-style')
<link href="{{ URL::asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet"
    type="text/css" />
@endpush

@include('includes.datatable-pagination')

@section('content')
@include('components.alert')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="gap-2 py-5 card-header align-items-center gap-md-5">
        <div class="card-title">
            <!--begin::Search-->
            <!-- Add user_id filter select -->
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-magnifier fs-1 position-absolute ms-4"><span class="path1"></span><span
                        class="path2"></span></i> <input type="text" data-kt-filter="search"
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data produk"
                    id="searchInput">
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
            <div id="kt_datatable_example_buttons" class="d-none"></div>
        </div>

    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <h2>Awal: <span id="awalValue">Calculating...</span></h2>
            </div>
            <div class="col-md-4">
                <h2>Akhir: <span id="akhirValue">Calculating...</span></h2>
            </div>
        </div>
        <div id="loadingSpinner"
            style="display: none; position: absolute; top: 90%; left: 50%; transform: translate(-50%, -50%);">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <div id="kt_datatable_example_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
            <div class="table-responsive">
                <table class="table align-middle rounded border table-row-dashed fs-6 g-5 dataTable no-footer"
                    id="kt_datatable_example">
                    <thead>
                        <tr class="text-gray-400 text-start fw-bold fs-7 text-uppercase">
                            <th>No</th>
                            <th class="w-150px">Tanggal</th>
                            <th>Untuk</th>
                            <th>Kasir</th>
                            <th class="w-350px">Keterangan</th>
                            <th>Metode Bayar</th>
                            <th>Masuk</th>
                            <th>Keluar</th>
                            @can('hapus laporan')
                            <th>Aksi</th>
                            @endcan
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
<script src="{{ URL::asset('assets/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
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
                    info: true,
                    order: [],
                    pageLength: 10,
                    "dom": '<"top"lp>rt<"bottom"lp><"clear">',
                    ajax: {
                        url: '{{ route('api.report') }}',
                        type: 'GET',
                        dataSrc: function(json) {
                            // Handle the response structure from our refactored API
                            const numberFormatter = new Intl.NumberFormat('id-ID', {
                                style: 'currency',
                                currency: 'IDR',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            });

                            // Update the summary values
                            $('#awalValue').text(numberFormatter.format(json.awalValue || 0));
                            $('#akhirValue').text(numberFormatter.format(json.akhirValue || 0));

                            // Return the cashflows array for DataTable
                            return json.cashflows || [];
                        },
                        beforeSend: function() {
                            // Show loading spinner and hide the table
                            $('#loadingSpinner').show();
                            $(table).hide();
                        },
                        complete: function() {
                            // Hide loading spinner and show the table
                            $('#loadingSpinner').hide();
                            $(table).show();
                        },
                        error: function(xhr, status, error) {
                            // Hide loading spinner and show the table even if there's an error
                            $('#loadingSpinner').hide();
                            $(table).show();

                            console.error('DataTable AJAX Error:', error);
                            console.error('Response:', xhr.responseText);

                            // Show user-friendly error message
                            Swal.fire({
                                title: 'Error',
                                text: 'Gagal memuat data laporan. Silakan coba lagi.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        },
                    },
                    columns: [{
                            data: null,
                            render: function(data, type, row, meta) {
                                return meta.row + meta.settings._iDisplayStart + 1;
                            }
                        },
                        {
                            data: "created_at",
                            render: function(data, type, row) {
                                if (!data) return '-';
                                return moment(data).format('DD MMMM YYYY');
                            }
                        },
                        {
                            data: "for",
                            defaultContent: '-'
                        },
                        {
                            data: "user.name",
                            defaultContent: '-'
                        },
                        {
                            data: "description",
                            defaultContent: '-'
                        },
                        {
                            data: "payment_method",
                            defaultContent: '-'
                        },
                        {
                            data: "in",
                            render: function(data, type, row) {
                                if (!data || data === 0) return 'Rp 0';
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                }).format(data);
                                return formattedPrice;
                            }
                        },
                        {
                            data: "out",
                            render: function(data, type, row) {
                                if (!data || data === 0) return 'Rp 0';
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0,
                                }).format(data);
                                return formattedPrice;
                            }
                        },
                        @can('hapus laporan')
                            {
                                data: "id",
                                render: function(data, type, row) {
                                    if (!data) return '';
                                    return `
                                    <form id="deleteForm_${data}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="id" value="${data}">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(${data})">Delete</button>
                                    </form>
                                    `;
                                }
                            }
                        @endcan
                    ],
                });

                // Handle filter changes
                $('#fromDateFilter, #toDateFilter, #warehouseFilter, #userFilter').on('change', function() {
                    var fromDate = $('#fromDateFilter').val();
                    var toDate = $('#toDateFilter').val();
                    var warehouse_id = $('#warehouseFilter').val();
                    var user_id = $('#userFilter').val();

                    // Update the URL based on selected filters
                    var url = '{{ route('api.report') }}';
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
                const documentTitle = 'Customer Orders Report';
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
                        const target = document.querySelector('.dt-buttons .buttons-' +
                            exportValue);

                        // Trigger click event on hidden datatable export buttons
                        target.click();
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
    function confirmDelete(id) {
            Swal.fire({
                title: 'Yakin menghapus data ini?',
                text: 'Data yang terhapus tidak dapat dikembalikan',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, hapus data!'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteRecord(id);
                }
            });
        }

        function deleteRecord(id) {
            // Create a form dynamically to handle the DELETE request properly
            var form = document.createElement('form');
            form.method = 'POST';
            var destroyUrlTemplate = "{{ route('laporan.destroy', ['id' => '__ID__']) }}";
            form.action = destroyUrlTemplate.replace('__ID__', encodeURIComponent(id));
            form.style.display = 'none';

            // Add CSRF token
            var csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = "{{ csrf_token() }}";
            form.appendChild(csrfToken);

            // Add method field for DELETE
            var methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);

            // Append form to body and submit
            document.body.appendChild(form);
            form.submit();
        }
</script>
@endpush