@extends('layouts.dashboard')

@section('title', 'Hutang')
@section('menu-title', 'Hutang')

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
@include('components.alert')
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
            <div class="ms-3">
                <select id="userFilter" class="form-select" aria-label="User filter" data-control="select2">
                    <option value="">All Users</option>
                    @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-calendar fs-1 position-absolute ms-4"></i>
                <input type="date" id="fromDateFilter" class="form-control form-control-solid ms-2"
                    data-kt-filter="date" placeholder="Dari Tanggal">
                <input type="date" id="toDateFilter" class="form-control form-control-solid ms-2" data-kt-filter="date"
                    placeholder="Ke Tanggal">
            </div>
            @endrole
        </div>
        <div class="gap-5 card-toolbar flex-row-fluid justify-content-end">
            <!--begin::Export dropdown-->
            <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click"
                data-kt-menu-placement="bottom-end">
                <i class="ki-duotone ki-exit-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                Export Data
            </button>
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
                            <th>tanggal Terima</th>
                            <th>Supplier</th>
                            <th>Cabang</th>
                            <th>Grand Total</th>
                            <th>Terbayar</th>
                            <th>Sisa</th>
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
<!-- Modal Pembayaran Hutang -->
<div class="modal fade" id="payDebtModal" tabindex="-1" role="dialog" aria-labelledby="payDebtModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payDebtModalLabel">Bayar Hutang</h5>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <form id="payDebtForm">
                    <div class="form-group">
                        <label class="form-label" for="payDebtAmount">Jumlah Pembayaran</label>
                        <input type="text" class="form-control" id="payDebtAmount" name="pay_debt"
                            oninput="formatNumber(this);" />
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="paymentMethod">Metode Pembayaran</label>
                        <select class="form-select" id="paymentMethod" name="payment">
                            <option value="transfer">Transfer</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <input type="hidden" id="purchaseId" name="purchase_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="submitPayment">Submit Pembayaran</button>
            </div>
        </div>
    </div>
</div>
@includeIf('pages.purchase.modal')

<!-- Credit & Debt Details Modal -->
<div class="modal fade" id="creditDebtModal" tabindex="-1" role="dialog" aria-labelledby="creditDebtModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="creditDebtModalLabel">
                    <i class="ki-duotone ki-dollar fs-2 me-2"></i>
                    Detail Hutang & Piutang
                </h5>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Debt Section -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="ki-duotone ki-arrow-down fs-3 text-danger me-2"></i>
                                    Hutang (Debt)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="spinner-border text-primary" role="status" id="debtLoading" style="display: none;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                                <div id="debtContent">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="fs-7 text-muted">Total Hutang</div>
                                            <div class="fs-4 fw-bold text-danger" id="totalDebt">Rp 0</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="fs-7 text-muted">Jumlah Transaksi</div>
                                            <div class="fs-4 fw-bold" id="debtCount">0</div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-borderless">
                                            <thead>
                                                <tr>
                                                    <th class="ps-0 fw-bold fs-7 text-muted">Supplier</th>
                                                    <th class="fw-bold fs-7 text-muted">Nominal</th>
                                                    <th class="fw-bold fs-7 text-muted">Tanggal</th>
                                                </tr>
                                            </thead>
                                            <tbody id="debtTableBody">
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">Memuat data...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Credit Section -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="ki-duotone ki-arrow-up fs-3 text-success me-2"></i>
                                    Piutang (Credit)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="spinner-border text-primary" role="status" id="creditLoading" style="display: none;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                                <div id="creditContent">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="fs-7 text-muted">Total Piutang</div>
                                            <div class="fs-4 fw-bold text-success" id="totalCredit">Rp 0</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="fs-7 text-muted">Jumlah Transaksi</div>
                                            <div class="fs-4 fw-bold" id="creditCount">0</div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-borderless">
                                            <thead>
                                                <tr>
                                                    <th class="ps-0 fw-bold fs-7 text-muted">Customer</th>
                                                    <th class="fw-bold fs-7 text-muted">Nominal</th>
                                                    <th class="fw-bold fs-7 text-muted">Tanggal</th>
                                                </tr>
                                            </thead>
                                            <tbody id="creditTableBody">
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">Memuat data...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card bg-light border">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="fs-7 text-muted">Total Hutang</div>
                                        <div class="fs-3 fw-bold text-danger" id="summaryTotalDebt">Rp 0</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="fs-7 text-muted">Total Piutang</div>
                                        <div class="fs-3 fw-bold text-success" id="summaryTotalCredit">Rp 0</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="fs-7 text-muted">Selisih</div>
                                        <div class="fs-3 fw-bold" id="netAmount">Rp 0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a href="{{ route('hutang') }}" class="btn btn-outline-danger">Kelola Hutang</a>
                <a href="{{ route('piutang') }}" class="btn btn-outline-success">Kelola Piutang</a>
            </div>
        </div>
    </div>
</div>

<!-- Debt Detail Modal -->
<div class="modal fade" id="debtDetailModal" tabindex="-1" role="dialog" aria-labelledby="debtDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="debtDetailModalLabel">
                    <i class="ki-duotone ki-document fs-2 me-2"></i>
                    Detail Pembelian Hutang
                </h5>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title">Informasi Pembelian</h6>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">No. Order</div>
                                        <div class="fs-5 fw-bold" id="detailOrderNumber">-</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Supplier</div>
                                        <div class="fs-5 fw-bold" id="detailSupplier">-</div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Tanggal Terima</div>
                                        <div class="fs-6" id="detailReceiptDate">-</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Cabang</div>
                                        <div class="fs-6" id="detailWarehouse">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title">Ringkasan Pembayaran</h6>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Total Pembelian</div>
                                        <div class="fs-4 fw-bold text-primary" id="detailGrandTotal">-</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Sudah Dibayar</div>
                                        <div class="fs-4 fw-bold text-success" id="detailPaid">-</div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Sisa Hutang</div>
                                        <div class="fs-4 fw-bold text-danger" id="detailRemaining">-</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Status</div>
                                        <div class="fs-6">
                                            <span class="badge badge-light-danger" id="detailStatus">Hutang</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">Detail Produk</h6>
                        <div id="productPaginationInfo" class="text-muted small"></div>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="spinner-border text-primary" role="status" id="detailLoading" style="display: none;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Nama Produk</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-center">Satuan</th>
                                        <th class="text-end">Harga Satuan</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="detailProductsTable">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Memuat data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination Controls -->
                        <div class="d-flex justify-content-between align-items-center mt-3" id="productPagination" style="display: none;">
                            <button class="btn btn-sm btn-outline-primary" id="prevPage" disabled>
                                <i class="ki-duotone ki-arrow-left fs-6"></i>
                                Sebelumnya
                            </button>
                            <div class="text-muted small" id="paginationInfo"></div>
                            <button class="btn btn-sm btn-outline-primary" id="nextPage" disabled>
                                Selanjutnya
                                <i class="ki-duotone ki-arrow-right fs-6"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a href="#" id="detailPaymentLink" class="btn btn-primary">Bayar Hutang</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('addon-script')
<script src="assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script>
    function formatNumber(input) {
            // Hapus semua karakter non-digit
            let value = input.value.replace(/\D/g, '');

            // Tambahkan separator ribuan
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Set nilai input dengan format yang baru
            input.value = value;
        }
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
                    "ajax": {
                        url: '{{ route('api.hutang') }}',
                        type: 'GET',
                        dataSrc: '',
                    },
                    "dom": '<"top"lp>rt<"bottom"lp><"clear">',
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
                            "data": "supplier.name"
                        },
                        {
                            "data": "warehouse.name"
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
                            "data": null,
                            "render": function(data, type, row) {
                                // grand total - paid
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data.grand_total - data.pay);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `
                                    <div class="d-flex gap-2">
                                        <a href="bayar-hutang/${row.id}" class="btn btn-sm btn-primary">Bayar</a>
                                        <button class="btn btn-sm btn-info" onclick="showDebtDetail(${row.id})">
                                            <i class="ki-duotone ki-eye fs-6"></i>
                                            Detail
                                        </button>
                                    </div>
                                `;
                            }
                        },
                    ],
                });

                $('#fromDateFilter, #toDateFilter, #warehouseFilter, #userFilter').on('change', function() {
                    var fromDate = $('#fromDateFilter').val();
                    var toDate = $('#toDateFilter').val();
                    var warehouse_id = $('#warehouseFilter').val();
                    var user_id = $('#userFilter').val();

                    // Update the URL based on selected filters
                    var url = '{{ route('api.hutang') }}';
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

                $(table).on('click', '.btn-submit', function() {
                    var rowData = datatable.row($(this).closest('tr')).data();
                    $('#purchaseId').val(rowData.id);
                    $('#payDebtModal').modal('show');
                });

                $('#submitPayment').on('click', function() {
                    var inputRequest = {
                        purchase_id: $('#purchaseId').val(),
                        pay: $('#payDebtAmount').val(),
                        payment: $('#paymentMethod').val(),
                    };

                    console.log(inputRequest);

                    // Send AJAX request with the POST method
                    $.ajax({
                        url: '{{ route('bayar-hutang') }}',
                        type: 'POST',
                        data: inputRequest,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil',
                                    text: response.message,
                                }).then(() => {
                                    $('#payDebtModal').modal('hide');
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal',
                                    text: response.message,
                                });
                            }
                        },
                    });
                });
            }

            // Hook export buttons
            var exportButtons = () => {
                const documentTitle = 'Piutang Data Report';
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
document.addEventListener('DOMContentLoaded', function() {
    const creditDebtModal = document.getElementById('creditDebtModal');

    creditDebtModal.addEventListener('show.bs.modal', function() {
        loadCreditDebtData();
    });

    function loadCreditDebtData() {
        // Show loading spinners
        document.getElementById('debtLoading').style.display = 'block';
        document.getElementById('creditLoading').style.display = 'block';

        // Fetch debt data
        fetch('{{ route('api.hutang') }}')
            .then(response => response.json())
            .then(debtData => {
                displayDebtData(debtData);
            })
            .catch(error => {
                console.error('Error loading debt data:', error);
                document.getElementById('debtTableBody').innerHTML = '<tr><td colspan="3" class="text-center text-danger">Gagal memuat data hutang</td></tr>';
            })
            .finally(() => {
                document.getElementById('debtLoading').style.display = 'none';
            });

        // Fetch credit data
        fetch('{{ route('api.piutang') }}')
            .then(response => response.json())
            .then(creditData => {
                displayCreditData(creditData);
            })
            .catch(error => {
                console.error('Error loading credit data:', error);
                document.getElementById('creditTableBody').innerHTML = '<tr><td colspan="3" class="text-center text-danger">Gagal memuat data piutang</td></tr>';
            })
            .finally(() => {
                document.getElementById('creditLoading').style.display = 'none';
            });
    }

    function displayDebtData(data) {
        let totalDebt = 0;
        let debtRows = '';

        if (data && data.length > 0) {
            // Take only first 5 records for display
            const displayData = data.slice(0, 5);

            displayData.forEach(item => {
                const remaining = item.grand_total - item.pay;
                totalDebt += remaining;

                const formattedAmount = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR'
                }).format(remaining).replace(',00', '');

                const date = new Date(item.reciept_date).toLocaleDateString('id-ID');

                debtRows += `
                    <tr>
                        <td class="ps-0">${item.supplier?.name || 'N/A'}</td>
                        <td class="text-danger fw-semibold">${formattedAmount}</td>
                        <td class="text-muted fs-8">${date}</td>
                    </tr>
                `;
            });

            // Add "View More" link if there are more than 5 records
            if (data.length > 5) {
                debtRows += `
                    <tr>
                        <td colspan="3" class="text-center">
                            <a href="{{ route('hutang') }}" class="text-primary fw-semibold">Lihat ${data.length - 5} data lainnya...</a>
                        </td>
                    </tr>
                `;
            }
        } else {
            debtRows = '<tr><td colspan="3" class="text-center text-muted">Tidak ada data hutang</td></tr>';
        }

        document.getElementById('debtTableBody').innerHTML = debtRows;
        document.getElementById('totalDebt').textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(totalDebt).replace(',00', '');
        document.getElementById('debtCount').textContent = data ? data.length : 0;
        document.getElementById('summaryTotalDebt').textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(totalDebt).replace(',00', '');

        updateNetAmount();
    }

    function displayCreditData(data) {
        let totalCredit = 0;
        let creditRows = '';

        if (data && data.length > 0) {
            // Take only first 5 records for display
            const displayData = data.slice(0, 5);

            displayData.forEach(item => {
                const remaining = item.grand_total - item.pay;
                totalCredit += remaining;

                const formattedAmount = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR'
                }).format(remaining).replace(',00', '');

                const date = new Date(item.created_at).toLocaleDateString('id-ID');

                creditRows += `
                    <tr>
                        <td class="ps-0">${item.customer?.name || 'N/A'}</td>
                        <td class="text-success fw-semibold">${formattedAmount}</td>
                        <td class="text-muted fs-8">${date}</td>
                    </tr>
                `;
            });

            // Add "View More" link if there are more than 5 records
            if (data.length > 5) {
                creditRows += `
                    <tr>
                        <td colspan="3" class="text-center">
                            <a href="{{ route('piutang') }}" class="text-primary fw-semibold">Lihat ${data.length - 5} data lainnya...</a>
                        </td>
                    </tr>
                `;
            }
        } else {
            creditRows = '<tr><td colspan="3" class="text-center text-muted">Tidak ada data piutang</td></tr>';
        }

        document.getElementById('creditTableBody').innerHTML = creditRows;
        document.getElementById('totalCredit').textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(totalCredit).replace(',00', '');
        document.getElementById('creditCount').textContent = data ? data.length : 0;
        document.getElementById('summaryTotalCredit').textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(totalCredit).replace(',00', '');

        updateNetAmount();
    }

    function updateNetAmount() {
        const totalDebtText = document.getElementById('summaryTotalDebt').textContent;
        const totalCreditText = document.getElementById('summaryTotalCredit').textContent;

        // Extract numeric values
        const totalDebt = parseFloat(totalDebtText.replace(/[^\d]/g, '')) || 0;
        const totalCredit = parseFloat(totalCreditText.replace(/[^\d]/g, '')) || 0;

        const netAmount = totalCredit - totalDebt;
        const netAmountElement = document.getElementById('netAmount');

        netAmountElement.textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(netAmount).replace(',00', '');

        // Set color based on net amount
        if (netAmount > 0) {
            netAmountElement.className = 'fs-3 fw-bold text-success';
        } else if (netAmount < 0) {
            netAmountElement.className = 'fs-3 fw-bold text-danger';
        } else {
            netAmountElement.className = 'fs-3 fw-bold text-muted';
        }
    }
});

// Global variables for pagination
let currentDebtPage = 1;
const itemsPerPage = 10;
let debtProductsData = [];

// Function to show debt detail modal
function showDebtDetail(purchaseId) {
    // Reset pagination
    currentDebtPage = 1;
    debtProductsData = [];

    // Show loading
    document.getElementById('detailLoading').style.display = 'block';
    document.getElementById('detailProductsTable').innerHTML = '<tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>';

    // Hide pagination initially
    document.getElementById('productPagination').style.display = 'none';

    // Reset modal data
    document.getElementById('detailOrderNumber').textContent = '-';
    document.getElementById('detailSupplier').textContent = '-';
    document.getElementById('detailReceiptDate').textContent = '-';
    document.getElementById('detailWarehouse').textContent = '-';
    document.getElementById('detailGrandTotal').textContent = '-';
    document.getElementById('detailPaid').textContent = '-';
    document.getElementById('detailRemaining').textContent = '-';

    // Fetch purchase details
    fetch(`/pembelian/${purchaseId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Check if the response contains an error
            if (data.error) {
                throw new Error(data.error);
            }

            // Fill purchase information
            document.getElementById('detailOrderNumber').textContent = data.order_number || '-';
            document.getElementById('detailSupplier').textContent = data.supplier?.name || '-';
            document.getElementById('detailReceiptDate').textContent = data.reciept_date ?
                new Date(data.reciept_date).toLocaleDateString('id-ID') : '-';
            document.getElementById('detailWarehouse').textContent = data.warehouse?.name || '-';

            // Fill payment summary
            const grandTotal = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format(data.grand_total || 0).replace(',00', '');

            const paid = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format(data.pay || 0).replace(',00', '');

            const remaining = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format((data.grand_total || 0) - (data.pay || 0)).replace(',00', '');

            document.getElementById('detailGrandTotal').textContent = grandTotal;
            document.getElementById('detailPaid').textContent = paid;
            document.getElementById('detailRemaining').textContent = remaining;

            // Set payment link
            document.getElementById('detailPaymentLink').href = `bayar-hutang/${purchaseId}`;

            // Store products data for pagination
            debtProductsData = data.details || [];

            // Initialize pagination
            renderDebtProductsPage();
        })
        .catch(error => {
            console.error('Error loading purchase details:', error);
            let errorMessage = 'Gagal memuat data detail';
            if (error.message.includes('404') || error.message.includes('tidak ditemukan')) {
                errorMessage = 'Data pembelian tidak ditemukan atau Anda tidak memiliki akses';
            } else if (error.message.includes('500')) {
                errorMessage = 'Terjadi kesalahan server';
            }
            document.getElementById('detailProductsTable').innerHTML = `<tr><td colspan="6" class="text-center text-danger">${errorMessage}</td></tr>`;
        })
        .finally(() => {
            document.getElementById('detailLoading').style.display = 'none';
        });

    // Show modal
    $('#debtDetailModal').modal('show');
}

// Function to render current page of debt products
function renderDebtProductsPage() {
    const startIndex = (currentDebtPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const currentItems = debtProductsData.slice(startIndex, endIndex);
    const totalItems = debtProductsData.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    // Update pagination info
    document.getElementById('productPaginationInfo').textContent = `Menampilkan ${totalItems} produk`;

    let productsHtml = '';
    if (currentItems.length > 0) {
        currentItems.forEach((detail, index) => {
            const actualIndex = startIndex + index + 1;
            const unitPrice = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format(detail.price_unit || 0).replace(',00', '');

            const totalPrice = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format(detail.total_price || 0).replace(',00', '');

            productsHtml += `
                <tr>
                    <td class="text-center">${actualIndex}</td>
                    <td>${detail.product?.name || '-'}</td>
                    <td class="text-center">${detail.quantity || 0}</td>
                    <td class="text-center">${detail.unit?.name || '-'}</td>
                    <td class="text-end">${unitPrice}</td>
                    <td class="text-end">${totalPrice}</td>
                </tr>
            `;
        });
    } else {
        productsHtml = '<tr><td colspan="6" class="text-center text-muted">Tidak ada detail produk</td></tr>';
    }

    document.getElementById('detailProductsTable').innerHTML = productsHtml;

    // Update pagination controls
    if (totalPages > 1) {
        document.getElementById('productPagination').style.display = 'flex';
        document.getElementById('paginationInfo').textContent = `Halaman ${currentDebtPage} dari ${totalPages}`;
        document.getElementById('prevPage').disabled = currentDebtPage === 1;
        document.getElementById('nextPage').disabled = currentDebtPage === totalPages;
    } else {
        document.getElementById('productPagination').style.display = 'none';
    }
}

// Pagination event listeners for debt modal
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('prevPage').addEventListener('click', function() {
        if (currentDebtPage > 1) {
            currentDebtPage--;
            renderDebtProductsPage();
        }
    });

    document.getElementById('nextPage').addEventListener('click', function() {
        const totalPages = Math.ceil(debtProductsData.length / itemsPerPage);
        if (currentDebtPage < totalPages) {
            currentDebtPage++;
            renderDebtProductsPage();
        }
    });
});
</script>
@endpush
