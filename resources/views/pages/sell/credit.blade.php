@extends('layouts.dashboard')

@section('title', 'Piutang')
@section('menu-title', 'Piutang')

@push('addon-style')
<link href="assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
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
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data piutang">
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
                        <tr class="text-start fw-bold fs-7 text-uppercase">
                            <th>No. Order</th>
                            <th>Customer</th>
                            <th>Kasir</th>
                            <th>Cabang</th>
                            <th>Total Pembelian</th>
                            <th>Terbayar</th>
                            <th>Sisa Piutang</th>
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
@includeIf('pages.sell.modal')

<!-- Modal Pembayaran Piutang -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">Pembayaran Piutang</h5>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <div class="mt-2 form-group">
                        <label class="form-label" for="payment_method">Metode Pembayaran:</label>
                        <select class="form-select" id="payment_method" name="payment">
                            <option value="">Pilih Pembayaran</option>
                            <option value="transfer">Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="split">Split</option>
                        </select>
                    </div>
                    <div class="mt-2 form-group">
                        <label class="form-label" for="total_piutang">Total Piutang:</label>
                        <input type="text" class="form-control" id="total_piutang" name="total_piutang" readonly>
                    </div>
                    <div class="mt-2 form-group">
                        <label class="form-label" for="discount">Potongan:</label>
                        <input type="text" class="form-control" id="discount" name="discount"
                            oninput="updateTotalPiutang()">
                    </div>

                    <div class="mt-2 form-group" id="payCreditGroup" style="display: none;">
                        <label class="form-label" for="pay_credit">Jumlah Pembayaran:</label>
                        <input type="text" class="form-control" id="pay_credit" name="pay_credit"
                            oninput="formatNumber(this)">
                    </div>
                    <div class="mt-2 form-group" id="splitPaymentFields" style="display: none;">
                        <label class="form-label" for="pay_credit_cash">Jumlah Pembayaran (Cash):</label>
                        <input type="text" class="form-control" id="pay_credit_cash" name="pay_credit_cash"
                            oninput="formatNumber(this)">
                        <label class="form-label" for="pay_credit_transfer">Jumlah Pembayaran (Transfer):</label>
                        <input type="text" class="form-control" id="pay_credit_transfer" name="pay_credit_transfer"
                            oninput="formatNumber(this)">
                    </div>
                    <div class="mt-2 form-group">
                        <label class="form-label" for="keterangan">Keterangan:</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                    </div>
                    <input type="hidden" id="sell_id" name="sell_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="submitPayment">Submit Pembayaran</button>
            </div>
        </div>
    </div>
</div>

<!-- Credit Detail Modal -->
<div class="modal fade" id="creditDetailModal" tabindex="-1" role="dialog" aria-labelledby="creditDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="creditDetailModalLabel">
                    <i class="ki-duotone ki-document fs-2 me-2"></i>
                    Detail Penjualan Piutang
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
                                <h6 class="card-title">Informasi Penjualan</h6>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">No. Order</div>
                                        <div class="fs-5 fw-bold" id="creditDetailOrderNumber">-</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Customer</div>
                                        <div class="fs-5 fw-bold" id="creditDetailCustomer">-</div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Tanggal</div>
                                        <div class="fs-6" id="creditDetailDate">-</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Cabang</div>
                                        <div class="fs-6" id="creditDetailWarehouse">-</div>
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
                                        <div class="fs-7 text-muted">Total Penjualan</div>
                                        <div class="fs-4 fw-bold text-primary" id="creditDetailGrandTotal">-</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Sudah Dibayar</div>
                                        <div class="fs-4 fw-bold text-success" id="creditDetailPaid">-</div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Sisa Piutang</div>
                                        <div class="fs-4 fw-bold text-danger" id="creditDetailRemaining">-</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="fs-7 text-muted">Status</div>
                                        <div class="fs-6">
                                            <span class="badge badge-light-success" id="creditDetailStatus">Piutang</span>
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
                        <div id="creditProductPaginationInfo" class="text-muted small"></div>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="spinner-border text-primary" role="status" id="creditDetailLoading" style="display: none;">
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
                                <tbody id="creditDetailProductsTable">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Memuat data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination Controls -->
                        <div class="d-flex justify-content-between align-items-center mt-3" id="creditProductPagination" style="display: none;">
                            <button class="btn btn-sm btn-outline-primary" id="creditPrevPage" disabled>
                                <i class="ki-duotone ki-arrow-left fs-6"></i>
                                Sebelumnya
                            </button>
                            <div class="text-muted small" id="creditPaginationInfo"></div>
                            <button class="btn btn-sm btn-outline-primary" id="creditNextPage" disabled>
                                Selanjutnya
                                <i class="ki-duotone ki-arrow-right fs-6"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="openPaymentModalFromDetail()">Terima Pembayaran</button>
            </div>
        </div>
    </div>
</div>

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
@endsection

@push('addon-script')
<script src="assets/plugins/custom/datatables/datatables.bundle.js"></script>
<script>
    document.getElementById('payment_method').addEventListener('change', function() {
            var paymentMethod = this.value;
            var payCreditGroup = document.getElementById('payCreditGroup');
            var splitPaymentFields = document.getElementById('splitPaymentFields');

            if (paymentMethod === 'split') {
                payCreditGroup.style.display = 'none';
                splitPaymentFields.style.display = 'block';
            } else if (paymentMethod) {
                payCreditGroup.style.display = 'block';
                splitPaymentFields.style.display = 'none';
            } else {
                payCreditGroup.style.display = 'none';
                splitPaymentFields.style.display = 'none';
            }
        });
</script>
<script>
    "use strict";

        function formatNumber(input) {
            // Hapus semua karakter non-digit
            let value = input.value.replace(/\D/g, '');

            // Tambahkan separator ribuan
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Set nilai input dengan format yang baru
            input.value = value;
        }
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
                    "dom": '<"top"lp>rt<"bottom"lp><"clear">',
                    "ajax": {
                        url: '{{ route('api.piutang') }}',
                        type: 'GET',
                        dataSrc: '',
                    },
                    "columns": [{
                            "data": "order_number"
                        },
                        {
                            "data": "customer.name"
                        },
                        {
                            "data": "cashier.name"
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
                            data: "id",
                            "render": function(data, type, row) {
                                return `
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary" onclick="openPaymentModal(${data}, ${row.grand_total - row.pay})">Terima</button>
                                        <button class="btn btn-sm btn-info" onclick="showCreditDetail(${data})">
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
                    var url = '{{ route('api.piutang') }}';
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
                    var sellId = rowData.id;
                    var payCredit = $(this).closest('tr').find('input[name="pay_credit"]').val();
                    var selectedPayment = $(this).closest('tr').find('select[name="payment"]').val();

                    var inputRequest = {
                        sell_id: sellId,
                        pay: payCredit,
                        payment: selectedPayment,
                    };

                    // Send AJAX request with the POST method
                    $.ajax({
                        url: '{{ route('bayar-piutang') }}',
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

                    $(table).on('keydown', 'input[name^="pay_credit"]', function(event) {
                        if (event.which === 13) {
                            event.preventDefault();
                            var btnSubmit = $(this).closest('tr').find('.btn-submit');
                            btnSubmit.click();
                        }
                    });
                }
            };
        }();

        // On document ready
        KTUtil.onDOMContentLoaded(function() {
            KTDatatablesExample.init();
        });

        let originalTotalPiutang = 0;

        function openPaymentModal(sellId, remaining) {
            $('#sell_id').val(sellId);
            $('#pay_credit').val(remaining);
            $('#total_piutang').val(new Intl.NumberFormat('id-ID', {
                style: 'decimal',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(remaining));
            originalTotalPiutang = remaining; // Simpan nilai total piutang asli
            $('#paymentModal').modal('show');
        }

        function updateTotalPiutang() {
            const totalPiutangInput = document.getElementById('total_piutang');
            const discountInput = document.getElementById('discount');

            // Ambil nilai potongan
            const discount = parseFloat(discountInput.value.replace(/[^0-9.-]+/g, "")) || 0;

            // Hitung total piutang baru
            const newTotal = originalTotalPiutang - discount;

            // Format dan set nilai total piutang
            totalPiutangInput.value = new Intl.NumberFormat('id-ID', {
                style: 'decimal',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(newTotal >= 0 ? newTotal : 0); // Pastikan tidak negatif
        }

        $('#paymentModal').on('hidden.bs.modal', function() {
            // Reset semua nilai dalam form modal
            $('#paymentForm')[0].reset();
            $('#payCreditGroup').hide();
            $('#splitPaymentFields').hide();
        });

        $('#submitPayment').click(function() {
            var paymentMethod = $('#payment_method').val();
            var formData = {
                sell_id: $('#sell_id').val(),
                potongan: $('#discount').val(),
                payment: paymentMethod,
                keterangan: $('#keterangan').val(),
            };

            if (paymentMethod === 'split') {
                formData.pay_credit_cash = $('#pay_credit_cash').val();
                formData.pay_credit_transfer = $('#pay_credit_transfer').val();
            } else {
                formData.pay = $('#pay_credit').val();
            }

            $.ajax({
                url: '{{ route('bayar-piutang') }}',
                type: 'POST',
                data: formData,
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
                            $('#paymentModal').modal('hide');
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

// Global constants for pagination
const itemsPerPage = 10;

// Global variables for credit pagination
let currentCreditPage = 1;
let creditProductsData = [];

// Function to render current page of credit products
function renderCreditProductsPage() {
    const startIndex = (currentCreditPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const currentItems = creditProductsData.slice(startIndex, endIndex);
    const totalItems = creditProductsData.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    // Update pagination info
    document.getElementById('creditProductPaginationInfo').textContent = `Menampilkan ${totalItems} produk`;

    let productsHtml = '';
    if (currentItems.length > 0) {
        currentItems.forEach((detail, index) => {
            const actualIndex = startIndex + index + 1;
            const unitPrice = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format(detail.price || 0).replace(',00', '');

            const totalPrice = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format((detail.quantity || 0) * (detail.price || 0)).replace(',00', '');

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

    document.getElementById('creditDetailProductsTable').innerHTML = productsHtml;

    // Update pagination controls
    if (totalPages > 1) {
        document.getElementById('creditProductPagination').style.display = 'flex';
        document.getElementById('creditPaginationInfo').textContent = `Halaman ${currentCreditPage} dari ${totalPages}`;
        document.getElementById('creditPrevPage').disabled = currentCreditPage === 1;
        document.getElementById('creditNextPage').disabled = currentCreditPage === totalPages;
    } else {
        document.getElementById('creditProductPagination').style.display = 'none';
    }
}

// Function to show credit detail modal
function showCreditDetail(sellId) {
    // Reset pagination
    currentCreditPage = 1;
    creditProductsData = [];

    // Show loading
    document.getElementById('creditDetailLoading').style.display = 'block';
    document.getElementById('creditDetailProductsTable').innerHTML = '<tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>';

    // Hide pagination initially
    document.getElementById('creditProductPagination').style.display = 'none';

    // Reset modal data
    document.getElementById('creditDetailOrderNumber').textContent = '-';
    document.getElementById('creditDetailCustomer').textContent = '-';
    document.getElementById('creditDetailDate').textContent = '-';
    document.getElementById('creditDetailWarehouse').textContent = '-';
    document.getElementById('creditDetailGrandTotal').textContent = '-';
    document.getElementById('creditDetailPaid').textContent = '-';
    document.getElementById('creditDetailRemaining').textContent = '-';

    // Store the current sell ID for payment modal
    window.currentSellId = sellId;

    // Fetch sell details
    fetch(`/penjualan/${sellId}`)
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

            // Fill sell information
            document.getElementById('creditDetailOrderNumber').textContent = data.order_number || '-';
            document.getElementById('creditDetailCustomer').textContent = data.customer?.name || '-';
            document.getElementById('creditDetailDate').textContent = data.created_at ?
                new Date(data.created_at).toLocaleDateString('id-ID') : '-';
            document.getElementById('creditDetailWarehouse').textContent = data.warehouse?.name || '-';

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

            document.getElementById('creditDetailGrandTotal').textContent = grandTotal;
            document.getElementById('creditDetailPaid').textContent = paid;
            document.getElementById('creditDetailRemaining').textContent = remaining;

            // Store products data for pagination
            creditProductsData = data.details || [];

            // Initialize pagination
            renderCreditProductsPage();
        })
        .catch(error => {
            console.error('Error loading sell details:', error);
            let errorMessage = 'Gagal memuat data detail';
            if (error.message.includes('404') || error.message.includes('tidak ditemukan')) {
                errorMessage = 'Data penjualan tidak ditemukan';
            } else if (error.message.includes('500')) {
                errorMessage = 'Terjadi kesalahan server';
            }
            document.getElementById('creditDetailProductsTable').innerHTML = `<tr><td colspan="6" class="text-center text-danger">${errorMessage}</td></tr>`;
        })
        .finally(() => {
            document.getElementById('creditDetailLoading').style.display = 'none';
        });

    // Show modal
    $('#creditDetailModal').modal('show');
}

// Function to open payment modal from detail modal
function openPaymentModalFromDetail() {
    if (window.currentSellId) {
        $('#creditDetailModal').modal('hide');
        // Get the remaining amount and open payment modal
        const remaining = document.getElementById('creditDetailRemaining').textContent;
        const remainingAmount = parseFloat(remaining.replace(/[^\d]/g, '')) || 0;
        openPaymentModal(window.currentSellId, remainingAmount);
    }
}

// Pagination event listeners for credit modal
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('creditPrevPage').addEventListener('click', function() {
        if (currentCreditPage > 1) {
            currentCreditPage--;
            renderCreditProductsPage();
        }
    });

    document.getElementById('creditNextPage').addEventListener('click', function() {
        const totalPages = Math.ceil(creditProductsData.length / itemsPerPage);
        if (currentCreditPage < totalPages) {
            currentCreditPage++;
            renderCreditProductsPage();
        }
    });
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
</script>
@endpush
