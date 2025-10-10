@extends('layouts.dashboard')

@section('title', 'Laporan Laba Rugi')
@section('menu-title', 'Laporan Laba Rugi')

@push('addon-style')
<link href="{{ URL::asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet"
    type="text/css" />
<style>
    .income-statement-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .section-title {
        font-weight: bold;
        font-size: 1.2em;
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .financial-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .financial-item:last-child {
        border-bottom: none;
    }

    .total-line {
        font-weight: bold;
        font-size: 1.1em;
        border-top: 2px solid #2c3e50;
        padding-top: 10px;
        margin-top: 10px;
    }

    .profit-positive {
        color: #27ae60;
    }

    .profit-negative {
        color: #e74c3c;
    }

    .currency {
        font-family: monospace;
    }

    .detail-tables-container {
        display: flex;
        gap: 20px;
    }

    .detail-table-card {
        flex: 1;
        height: 600px;
        display: flex;
        flex-direction: column;
    }

    .detail-table-card .card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .detail-table-card .table-responsive {
        flex: 1;
        overflow-y: auto;
    }

    .detail-table-card .dataTables_wrapper {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .detail-table-card .dataTables_scroll {
        flex: 1;
    }
</style>
@endpush

@section('content')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="gap-2 py-5 card-header align-items-center gap-md-5">
        <div class="card-title">
            <h2>Laporan Laba Rugi</h2>
        </div>
        <div class="gap-5 card-toolbar flex-row-fluid justify-content-end">
            <div class="row g-3">
                @can('lihat semua laba rugi')
                <div class="col-md-3">
                    <select class="form-select" data-control="select2" data-placeholder="Pilih Gudang"
                        id="warehouseFilter">
                        <option value="">Semua Gudang</option>
                        <option value="all_branches">Semua Cabang</option>
                        @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" data-control="select2" data-placeholder="Pilih User" id="userFilter">
                        <option value="">Semua User</option>
                        @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <div class="col-md-3">
                    <input type="hidden" id="warehouseFilter" value="{{ auth()->user()->warehouse_id }}">
                    <input type="text" class="form-control" value="{{ auth()->user()->warehouse->name }}" disabled>
                </div>
                <div class="col-md-3">
                    <input type="hidden" id="userFilter" value="{{ auth()->id() }}">
                    <input type="text" class="form-control" value="{{ auth()->user()->name }}" disabled>
                </div>
                @endcan
                <div class="col-md-3">
                    <input type="date" id="fromDateFilter" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <input type="date" id="toDateFilter" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-12">
                    <button type="button" class="btn btn-primary" id="generateReport">
                        <span class="indicator-label">
                            <i class="ki-duotone ki-magnifier fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Generate Laporan
                        </span>
                        <span class="indicator-progress" style="display: none;">
                            Please wait...
                            <span class="align-middle spinner-border spinner-border-sm ms-2"></span>
                        </span>
                    </button>
                    <button type="button" class="btn btn-warning" id="clearCache">
                        <span class="indicator-label">
                            <i class="ki-duotone ki-trash fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            Clear Cache
                        </span>
                        <span class="indicator-progress" style="display: none;">
                            Clearing...
                            <span class="align-middle spinner-border spinner-border-sm ms-2"></span>
                        </span>
                    </button>
                    <button type="button" class="btn btn-success" id="exportReport" style="display: none;">
                        <span class="indicator-label">
                            <i class="ki-duotone ki-file-down fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Export Excel
                        </span>
                        <span class="indicator-progress" style="display: none;">
                            Exporting...
                            <span class="align-middle spinner-border spinner-border-sm ms-2"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body" id="reportContent" style="display: none;">
        <!-- Report Header -->
        <div class="mb-4 text-center">
            <h3 id="reportTitle">LAPORAN LABA RUGI</h3>
            <p id="reportPeriod" class="text-muted"></p>
            <p id="reportWarehouse" class="text-muted"></p>
        </div>

        <!-- Revenue Section -->
        <div class="income-statement-section">
            <div class="section-title">PENDAPATAN</div>
            <div id="revenueSection">
                <div class="financial-item">
                    <span>Penjualan</span>
                    <span class="currency" id="salesRevenue">Rp 0</span>
                </div>
                <div class="financial-item total-line">
                    <span><strong>Total Pendapatan</strong></span>
                    <span class="currency" id="totalRevenue"><strong>Rp 0</strong></span>
                </div>
            </div>
        </div>

        <!-- Cost of Goods Sold Section -->
        <div class="income-statement-section">
            <div class="section-title">HARGA POKOK PENJUALAN</div>
            <div id="cogsSection">
                <div class="financial-item">
                    <span>Harga Pokok Penjualan</span>
                    <span class="currency" id="totalCogs">Rp 0</span>
                </div>
                <div class="financial-item total-line">
                    <span><strong>Total Harga Pokok Penjualan</strong></span>
                    <span class="currency" id="totalCogsAmount"><strong>Rp 0</strong></span>
                </div>
            </div>
        </div>

        <!-- Gross Profit Section -->
        <div class="income-statement-section">
            <div class="financial-item total-line">
                <span><strong>LABA KOTOR</strong></span>
                <span class="currency" id="grossProfit"><strong>Rp 0</strong></span>
            </div>
        </div>

        <!-- Stock Burden Section -->
        <div class="income-statement-section">
            <div class="section-title">BEBAN STOK</div>
            <div id="stockBurdenSection">
                <div class="financial-item">
                    <span>Nilai Stok di Inventory</span>
                    <span class="currency" id="totalStockBurden">Rp 0</span>
                </div>
            </div>
            <div class="financial-item total-line">
                <span><strong>Total Beban Stok</strong></span>
                <span class="currency" id="totalStockBurdenAmount"><strong>Rp 0</strong></span>
            </div>
        </div>

        <!-- Operating Expenses Section -->
        <div class="income-statement-section">
            <div class="section-title">BEBAN OPERASIONAL</div>
            <div id="expensesSection">
                <!-- Expenses will be populated here -->
            </div>
            <div class="financial-item total-line">
                <span><strong>Total Beban Operasional</strong></span>
                <span class="currency" id="totalExpenses"><strong>Rp 0</strong></span>
            </div>
        </div>

        <!-- Other Income Section - REMOVED as per user request -->

        <!-- Net Income Section -->
        <div class="income-statement-section" style="background: #2c3e50; color: white;">
            <div class="financial-item total-line" style="border-top: none; font-size: 1.3em;">
                <span><strong>LABA BERSIH</strong></span>
                <span class="currency" id="netIncome"><strong>Rp 0</strong></span>
            </div>
        </div>

        <!-- Detailed Tables -->
        <div class="mt-5">
            <!-- First Row: Sales and COGS -->
            <div class="detail-tables-container">
                <div class="detail-table-card card">
                    <div class="card-header">
                        <h5>Detail Penjualan per Produk</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-row-bordered gy-5 gs-7" id="salesDetailTable">
                                <thead>
                                    <tr class="text-gray-800 fw-bold fs-6">
                                        <th>No</th>
                                        <th>Produk</th>
                                        <th>Qty Terjual</th>
                                        <th>Total Pendapatan</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="detail-table-card card">
                    <div class="card-header">
                        <h5>Detail HPP per Produk</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-row-bordered gy-5 gs-7" id="cogsDetailTable">
                                <thead>
                                    <tr class="text-gray-800 fw-bold fs-6">
                                        <th>No</th>
                                        <th>Produk</th>
                                        <th>Qty (Eceran)</th>
                                        <th>Harga Modal</th>
                                        <th>Total HPP</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Second Row: Stock Burden -->
            <div class="mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Detail Beban Stok per Produk</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-row-bordered gy-5 gs-7" id="stockBurdenDetailTable">
                                <thead>
                                    <tr class="text-gray-800 fw-bold fs-6">
                                        <th>No</th>
                                        <th>Produk</th>
                                        <th>Qty Stok (Eceran)</th>
                                        <th>Harga Modal</th>
                                        <th>Total Beban Stok</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Third Row: Operating Expenses Detail -->
            <div class="mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Detail Beban Operasional</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-row-bordered gy-5 gs-7" id="operatingExpensesDetailTable">
                                <thead>
                                    <tr class="text-gray-800 fw-bold fs-6">
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Invoice</th>
                                        <th>Deskripsi</th>
                                        <th>Kategori</th>
                                        <th>Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('addon-script')
<script src="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
    "use strict";

        let reportData = null;

        $(document).ready(function() {
            // Add CSRF token to all AJAX requests
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Initialize Select2
            $('#warehouseFilter, #userFilter').select2();

            // Initialize DataTables
            initializeDataTables();

            // Generate report button click
            $('#generateReport').click(function() {
                generateReport();
            });

            // Clear cache button click
            $('#clearCache').click(function() {
                clearCache();
            });

            // Export report button click
            $('#exportReport').click(function() {
                exportToExcel();
            });

            // Handle all_branches checkbox change
            $('#all_branches').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#warehouse').val('').prop('disabled', true);
                } else {
                    $('#warehouse').prop('disabled', false);
                }
                loadData();
            });

            // Initial state setup
            if ($('#all_branches').is(':checked')) {
                $('#warehouse').val('').prop('disabled', true);
            }

            function loadData() {
                $.ajax({
                    url: '/income-statement/data',
                    type: 'GET',
                    data: {
                        warehouse: $('#warehouse').val(),
                        user_id: $('#user').val(),
                        from_date: $('#from_date').val(),
                        to_date: $('#to_date').val(),
                        all_branches: $('#all_branches').is(':checked')
                    },
                    success: function(response) {
                        // Update the display with the response data
                        updateDisplay(response);
                    },
                    error: function(xhr) {
                        console.error('Error loading data:', xhr);
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to load data. Please try again.',
                            icon: 'error'
                        });
                    }
                });
            }

            // Handle filter button click
            $('#filter').click(function() {
                loadData();
            });

            // Handle clear cache button click
            $('#clearCache').click(function() {
                $.ajax({
                    url: '/income-statement/clear-cache',
                    type: 'POST',
                    success: function() {
                        loadData();
                    },
                    error: function(xhr) {
                        console.error('Error clearing cache:', xhr);
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to clear cache. Please try again.',
                            icon: 'error'
                        });
                    }
                });
            });

            // Auto-generate report on page load
            generateReport();
        });

        function initializeDataTables() {
            // Initialize Sales Detail Table
            $('#salesDetailTable').DataTable({
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": 0 }, // No column not sortable
                    { "className": "text-center", "targets": [0, 2] },
                    { "className": "text-end", "targets": 3 }
                ]
            });

            // Initialize COGS Detail Table
            $('#cogsDetailTable').DataTable({
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": 0 }, // No column not sortable
                    { "className": "text-center", "targets": [0, 2] },
                    { "className": "text-end", "targets": [3, 4] }
                ]
            });

            // Initialize Stock Burden Detail Table
            $('#stockBurdenDetailTable').DataTable({
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": 0 }, // No column not sortable
                    { "className": "text-center", "targets": [0, 2] },
                    { "className": "text-end", "targets": [3, 4] }
                ]
            });

            // Initialize Operating Expenses Detail Table
            $('#operatingExpensesDetailTable').DataTable({
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": 0 },
                    { "className": "text-center", "targets": [0] },
                    { "className": "text-end", "targets": 5 }
                ],
                "order": [[1, "desc"]] // Sort by date descending
            });
        }

        function generateReport() {
            const fromDate = $('#fromDateFilter').val();
            const toDate = $('#toDateFilter').val();
            const warehouse = $('#warehouseFilter').val();
            const user = $('#userFilter').val();

            if (!fromDate || !toDate) {
                alert('Silakan pilih tanggal mulai dan tanggal akhir');
                return;
            }

            // Validate date range - warn if too long
            const startDate = new Date(fromDate);
            const endDate = new Date(toDate);
            const diffTime = Math.abs(endDate - startDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays > 365) {
                Swal.fire({
                    title: 'Peringatan!',
                    text: 'Range tanggal lebih dari 1 tahun. Ini mungkin memakan waktu lama untuk diproses. Apakah Anda yakin ingin melanjutkan?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Lanjutkan',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeGenerate(fromDate, toDate, warehouse, user);
                    }
                });
                return;
            }

            executeGenerate(fromDate, toDate, warehouse, user);
        }

        function executeGenerate(fromDate, toDate, warehouse, user) {
            // Show button loading state
            const generateBtn = $('#generateReport');
            generateBtn.attr('disabled', true);
            generateBtn.find('.indicator-label').hide();
            generateBtn.find('.indicator-progress').show();

            // Hide export button and report content
            $('#exportReport').hide();
            $('#reportContent').hide();

            // Check if "Semua Cabang" is selected
            const isAllBranches = warehouse === 'all_branches';
            const warehouseId = isAllBranches ? '' : warehouse;

            $.ajax({
                url: '{{ route('api.income-statement') }}',
                type: 'GET',
                data: {
                    from_date: fromDate,
                    to_date: toDate,
                    warehouse: warehouseId,
                    user_id: user,
                    all_branches: isAllBranches
                },
                timeout: 300000, // 5 minutes timeout
                success: function(response) {
                    reportData = response;
                    populateReport(response);
                    $('#reportContent').show();
                    $('#exportReport').show();

                    // Show cache info if available
                    if (response.cache_generated_at) {
                        toastr.info(`Data loaded from cache (generated at: ${new Date(response.cache_generated_at).toLocaleString()})`);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error generating report:', error);

                    let errorMessage = 'Terjadi kesalahan saat menggenerate laporan';
                    let showClearCache = false;

                    if (xhr.status === 500) {
                        errorMessage = xhr.responseJSON?.error || 'Server error. Data terlalu besar atau server overload.';
                        showClearCache = true;
                    } else if (xhr.status === 502) {
                        errorMessage = 'Server timeout. Coba kurangi range tanggal atau clear cache terlebih dahulu.';
                        showClearCache = true;
                    } else if (xhr.status === 504) {
                        errorMessage = 'Gateway timeout. Data terlalu besar untuk diproses. Coba kurangi range tanggal.';
                        showClearCache = true;
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    // Show error alert with suggestions
                    let alertHtml = errorMessage;
                    if (showClearCache) {
                        alertHtml += '<br><br><strong>Saran:</strong><br>• Kurangi range tanggal<br>• Clear cache dan coba lagi<br>• Pilih warehouse specific jika memungkinkan';
                    }

                    Swal.fire({
                        title: 'Error!',
                        html: alertHtml,
                        icon: 'error',
                        confirmButtonText: 'OK',
                        showCancelButton: showClearCache,
                        cancelButtonText: showClearCache ? 'Clear Cache' : null
                    }).then((result) => {
                        if (result.dismiss === Swal.DismissReason.cancel && showClearCache) {
                            clearCache();
                        }
                    });
                },
                complete: function() {
                    // Reset button state
                    generateBtn.attr('disabled', false);
                    generateBtn.find('.indicator-progress').hide();
                    generateBtn.find('.indicator-label').show();
                }
            });
        }

        function clearCache() {
            const fromDate = $('#fromDateFilter').val();
            const toDate = $('#toDateFilter').val();
            const warehouse = $('#warehouseFilter').val();
            const user = $('#userFilter').val();

            // Check if "Semua Cabang" is selected
            const isAllBranches = warehouse === 'all_branches';
            const warehouseId = isAllBranches ? '' : warehouse;

            // Show loading state
            const clearBtn = $('#clearCache');
            clearBtn.attr('disabled', true);
            clearBtn.find('.indicator-label').hide();
            clearBtn.find('.indicator-progress').show();

            $.ajax({
                url: '{{ route('api.income-statement.clear-cache') }}',
                type: 'POST',
                data: {
                    from_date: fromDate,
                    to_date: toDate,
                    warehouse: warehouseId,
                    user_id: user,
                    all_branches: isAllBranches,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    toastr.success('Cache berhasil dihapus. Silakan generate laporan lagi.');
                },
                error: function(xhr, status, error) {
                    console.error('Error clearing cache:', error);
                    toastr.error('Gagal menghapus cache');
                },
                complete: function() {
                    // Reset button state
                    clearBtn.attr('disabled', false);
                    clearBtn.find('.indicator-progress').hide();
                    clearBtn.find('.indicator-label').show();
                }
            });
        }

        function populateReport(data) {
            // Format currency function
            const formatCurrency = (amount) => {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(amount).replace(',00', '');
            };

            // Update report header
            const fromDate = new Date($('#fromDateFilter').val()).toLocaleDateString('id-ID');
            const toDate = new Date($('#toDateFilter').val()).toLocaleDateString('id-ID');
            $('#reportPeriod').text(`Periode: ${fromDate} - ${toDate}`);
            $('#reportWarehouse').text(`Gudang: ${data.period.warehouse}`);

            // Update revenue section
            $('#salesRevenue').text(formatCurrency(data.sales_data.total_revenue));
            $('#totalRevenue').text(formatCurrency(data.sales_data.total_revenue));

            // Update COGS section
            $('#totalCogs').text(formatCurrency(data.cogs_data.total_cogs));
            $('#totalCogsAmount').text(formatCurrency(data.cogs_data.total_cogs));

            // Update gross profit
            const grossProfitClass = data.gross_profit >= 0 ? 'profit-positive' : 'profit-negative';
            $('#grossProfit').text(formatCurrency(data.gross_profit)).removeClass('profit-positive profit-negative').addClass(grossProfitClass);

            // Update stock burden section
            $('#totalStockBurden').text(formatCurrency(data.stock_burden.total_stock_burden));
            $('#totalStockBurdenAmount').text(formatCurrency(data.stock_burden.total_stock_burden));

            // Update expenses section
            let expensesHtml = '';
            data.operating_expenses.expenses_by_category.forEach(expense => {
                expensesHtml += `
                    <div class="financial-item">
                        <span>${expense.category}</span>
                        <span class="currency">${formatCurrency(expense.total_amount)}</span>
                    </div>
                `;
            });
            $('#expensesSection').html(expensesHtml);
            $('#totalExpenses').text(formatCurrency(data.operating_expenses.total_operating_expenses));

            // Other income section removed as per user request

            // Update net income
            const netIncomeClass = data.net_income >= 0 ? 'profit-positive' : 'profit-negative';
            $('#netIncome').text(formatCurrency(data.net_income)).removeClass('profit-positive profit-negative').addClass(netIncomeClass);

            // Populate sales detail table with DataTables
            const salesTable = $('#salesDetailTable').DataTable();
            salesTable.clear();

            data.sales_data.sales_by_product.forEach((product, index) => {
                salesTable.row.add([
                    index + 1,
                    product.product_name,
                    product.quantity_sold,
                    formatCurrency(product.total_revenue)
                ]);
            });
            salesTable.draw();

            // Populate COGS detail table with DataTables
            const cogsTable = $('#cogsDetailTable').DataTable();
            cogsTable.clear();

            data.cogs_data.cogs_by_product.forEach((product, index) => {
                cogsTable.row.add([
                    index + 1,
                    product.product_name,
                    product.quantity_sold_eceran,
                    formatCurrency(product.cost_price),
                    formatCurrency(product.total_cogs)
                ]);
            });
            cogsTable.draw();

            // Populate Stock Burden detail table with DataTables
            const stockBurdenTable = $('#stockBurdenDetailTable').DataTable();
            stockBurdenTable.clear();

            data.stock_burden.stock_burden_by_product.forEach((product, index) => {
                stockBurdenTable.row.add([
                    index + 1,
                    product.product_name,
                    product.total_quantity,
                    formatCurrency(product.cost_price),
                    formatCurrency(product.total_burden)
                ]);
            });
            stockBurdenTable.draw();

            // Populate Operating Expenses detail table with DataTables
            const expensesTable = $('#operatingExpensesDetailTable').DataTable();
            expensesTable.clear();

            data.operating_expenses.expense_details.forEach((expense, index) => {
                expensesTable.row.add([
                    index + 1,
                    new Date(expense.date).toLocaleDateString('id-ID'),
                    expense.invoice || '-',
                    expense.description || '-',
                    expense.category,
                    formatCurrency(expense.amount)
                ]);
            });
            expensesTable.draw();
        }

        function exportToExcel() {
            if (!reportData) {
                Swal.fire({
                    title: 'Warning!',
                    text: 'Tidak ada data untuk diekspor. Silakan generate laporan terlebih dahulu.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Show export button loading state
            const exportBtn = $('#exportReport');
            exportBtn.attr('disabled', true);
            exportBtn.find('.indicator-label').hide();
            exportBtn.find('.indicator-progress').show();

            const formatCurrency = (amount) => {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(amount).replace(',00', '');
            };

            // Create workbook
            const wb = XLSX.utils.book_new();

            // Income Statement Sheet
            const incomeStatementData = [
                ['LAPORAN LABA RUGI'],
                [`Periode: ${$('#fromDateFilter').val()} - ${$('#toDateFilter').val()}`],
                [`Gudang: ${reportData.period.warehouse}`],
                [''],
                ['PENDAPATAN'],
                ['Penjualan', formatCurrency(reportData.sales_data.total_revenue)],
                ['Total Pendapatan', formatCurrency(reportData.sales_data.total_revenue)],
                [''],
                ['HARGA POKOK PENJUALAN'],
                ['Harga Pokok Penjualan', formatCurrency(reportData.cogs_data.total_cogs)],
                ['Total Harga Pokok Penjualan', formatCurrency(reportData.cogs_data.total_cogs)],
                [''],
                ['LABA KOTOR', formatCurrency(reportData.gross_profit)],
                [''],
                ['BEBAN STOK'],
                ['Nilai Stok di Inventory', formatCurrency(reportData.stock_burden.total_stock_burden)],
                ['Total Beban Stok', formatCurrency(reportData.stock_burden.total_stock_burden)],
                [''],
                ['BEBAN OPERASIONAL']
            ];

            // Add expenses
            reportData.operating_expenses.expenses_by_category.forEach(expense => {
                incomeStatementData.push([expense.category, formatCurrency(expense.total_amount)]);
            });
            incomeStatementData.push(['Total Beban Operasional', formatCurrency(reportData.operating_expenses.total_operating_expenses)]);
            incomeStatementData.push(['']);
            incomeStatementData.push(['LABA BERSIH', formatCurrency(reportData.net_income)]);

            const ws1 = XLSX.utils.aoa_to_sheet(incomeStatementData);
            XLSX.utils.book_append_sheet(wb, ws1, 'Laporan Laba Rugi');

            // Sales Detail Sheet
            const salesData = [
                ['Detail Penjualan per Produk'],
                ['No', 'Produk', 'Qty Terjual', 'Total Pendapatan']
            ];
            reportData.sales_data.sales_by_product.forEach((product, index) => {
                salesData.push([index + 1, product.product_name, product.quantity_sold, formatCurrency(product.total_revenue)]);
            });

            const ws2 = XLSX.utils.aoa_to_sheet(salesData);
            XLSX.utils.book_append_sheet(wb, ws2, 'Detail Penjualan');

            // COGS Detail Sheet
            const cogsData = [
                ['Detail HPP per Produk'],
                ['No', 'Produk', 'Qty (Eceran)', 'Harga Modal', 'Total HPP']
            ];
            reportData.cogs_data.cogs_by_product.forEach((product, index) => {
                cogsData.push([index + 1, product.product_name, product.quantity_sold_eceran, formatCurrency(product.cost_price), formatCurrency(product.total_cogs)]);
            });

            const ws3 = XLSX.utils.aoa_to_sheet(cogsData);
            XLSX.utils.book_append_sheet(wb, ws3, 'Detail HPP');

            // Stock Burden Detail Sheet
            const stockBurdenData = [
                ['Detail Beban Stok per Produk'],
                ['No', 'Produk', 'Qty Stok (Eceran)', 'Harga Modal', 'Total Beban Stok']
            ];
            reportData.stock_burden.stock_burden_by_product.forEach((product, index) => {
                stockBurdenData.push([index + 1, product.product_name, product.total_quantity, formatCurrency(product.cost_price), formatCurrency(product.total_burden)]);
            });

            const ws4 = XLSX.utils.aoa_to_sheet(stockBurdenData);
            XLSX.utils.book_append_sheet(wb, ws4, 'Detail Beban Stok');

            // Operating Expenses Detail Sheet
            const operatingExpensesData = [
                ['Detail Beban Operasional'],
                ['No', 'Tanggal', 'Invoice', 'Deskripsi', 'Kategori', 'Jumlah']
            ];
            reportData.operating_expenses.expense_details.forEach((expense, index) => {
                operatingExpensesData.push([
                    index + 1,
                    new Date(expense.date).toLocaleDateString('id-ID'),
                    expense.invoice || '-',
                    expense.description || '-',
                    expense.category,
                    formatCurrency(expense.amount)
                ]);
            });

            const ws5 = XLSX.utils.aoa_to_sheet(operatingExpensesData);
            XLSX.utils.book_append_sheet(wb, ws5, 'Detail Beban Operasional');

            // Export
            const filename = `Laporan_Laba_Rugi_${$('#fromDateFilter').val()}_${$('#toDateFilter').val()}.xlsx`;

            try {
                XLSX.writeFile(wb, filename);

                // Show success message
                Swal.fire({
                    title: 'Success!',
                    text: 'Laporan berhasil diekspor ke Excel',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } catch (error) {
                console.error('Export error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Terjadi kesalahan saat mengekspor laporan',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            } finally {
                // Reset export button state
                const exportBtn = $('#exportReport');
                exportBtn.attr('disabled', false);
                exportBtn.find('.indicator-progress').hide();
                exportBtn.find('.indicator-label').show();
            }
        }
</script>
@endpush