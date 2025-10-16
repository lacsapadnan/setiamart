@extends('layouts.dashboard')

@section('title', 'Produk')
@section('menu-title', 'Produk')

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
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data produk"
                    id="searchInput">
            </div>
            <!--end::Search-->
            <select id="categoryFilter" class="form-select" aria-label="Category filter" data-control="select2">
                <option>All Categories</option>
                @foreach ($categories as $category)
                <option value="{{ $category->name }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="gap-5 card-toolbar flex-row-fluid justify-content-end">
            <!--begin::Export dropdown-->
            <a href="{{ route('product.export') }}" class="btn btn-light-primary" target="_blank">
                <i class="ki-duotone ki-exit-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                Export Data
            </a>
            @can('import produk')
            <button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_2">
                <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                Import Data Data
            </button>
            @endcan
            <button type="button" class="btn btn-light-primary" id="printBarcodeBtn">
                <i class="ki-duotone ki-barcode fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                    <span class="path6"></span>
                    <span class="path7"></span>
                    <span class="path8"></span>
                </i>
                Cetak Barcode
            </button>
            @can('simpan produk')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_1">
                Tambah Data
            </button>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <div id="kt_datatable_example_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
            <div class="table-responsive">
                <table class="table align-middle rounded border table-row-dashed fs-6 g-5 dataTable no-footer"
                    id="kt_datatable_example">
                    <thead>
                        <tr class="text-gray-400 text-start fw-bold fs-7 text-uppercase">
                            <th class="min-w-100px">Kelompok</th>
                            <th class="min-w-150px">Nama Barang</th>
                            <th>Promo</th>
                            <th>Promo Luar Kota</th>
                            <th>Barcode Dus</th>
                            <th>Barcode Pak</th>
                            <th>Barcode Ecer</th>
                            <th>Satuan Dus</th>
                            <th>Satuan Pak</th>
                            <th>Satuan Eceran</th>
                            <th>Jml. Dus ke Eceran</th>
                            <th>Jml. Pak ke Eceran</th>
                            <th>Harga Eceran Terakhir</th>
                            <th>Harga Jual Dus</th>
                            <th>Harga Jual Pak</th>
                            <th>Harga Jual Eceran</th>
                            <th>Harga Eceran Terakhir Luar Kota</th>
                            <th>Harga Jual Dus Luar Kota</th>
                            <th>Harga Jual Pak Luar Kota</th>
                            <th>Harga Jual Eceran Luar Kota</th>
                            <th>Hadiah</th>
                            <th>Hadiah Luar Kota</th>
                            <th class="min-w-200px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 fw-semibold">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@includeIf('pages.product.modal')
@includeIf('pages.product.import')
@includeIf('pages.product.barcode-modal')
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
                    "order": [],
                    "pageLength": 10,
                    "scrollX": true,
                    deferRender: true,
                    processing: true,
                    serverSide: true,
                    fixedColumns: {
                        left: 3
                    },
                    "dom": '<"top"lp>rt<"bottom"lp><"clear">',
                    "ajax": {
                        url: '{{ route('api.produk-search') }}',
                        type: 'GET',
                        data: function(d) {
                            d.searchQuery = $('#searchInput').val();
                            d.category = $('#categoryFilter').val();
                        },
                    },
                    "columns": [{
                            data: 'group'
                        },
                        {
                            data: 'name'
                        },
                        {
                            data: 'promo'
                        },
                        {
                            data: 'promo_out_of_town'
                        },
                        {
                            data: 'barcode_dus',
                            defaultContent: '-'
                        },
                        {
                            data: 'barcode_pak',
                            defaultContent: '-'
                        },
                        {
                            data: 'barcode_eceran',
                            defaultContent: '-'
                        },
                        {
                            data: 'unit_dus.name',
                            defaultContent: '-'
                        },
                        {
                            data: 'unit_pak.name',
                            defaultContent: '-'
                        },
                        {
                            data: 'unit_eceran.name',
                            defaultContent: '-'
                        },
                        {
                            data: 'dus_to_eceran',
                        },
                        {
                            data: 'pak_to_eceran'
                        },
                        {
                            data: 'lastest_price_eceran',
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
                            data: 'price_sell_dus',
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
                            data: 'price_sell_pak',
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
                            data: 'price_sell_eceran',
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
                            data: 'lastest_price_eceran_out_of_town',
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
                            data: 'price_sell_dus_out_of_town',
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
                            data: 'price_sell_pak_out_of_town',
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
                            data: 'price_sell_eceran_out_of_town',
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
                            data: 'hadiah',
                            defaultContent: '-'
                        },
                        {
                            data: 'hadiah_out_of_town',
                            defaultContent: '-'
                        },
                        {
                            data: null
                        },
                    ],
                    "columnDefs": [{
                            className: 'min-w-100px',
                            targets: 0
                        },
                        {
                            className: 'min-w-200px',
                            targets: 1
                        },
                        {
                            className: 'min-w-100px',
                            targets: [11, 12, 13],
                        },
                        {
                            className: 'min-w-200px',
                            targets: -1,
                            render: function(data, type, row) {
                                var editUrl = "/produk/" + row.id + "/edit";
                                var deleteUrl = "/produk/" + row.id;

                                return `
                                    @can('update produk')
                                        <a href="${editUrl}" type="button" class="btn btn-sm btn-warning me-2">Edit</a>
                                    @endcan
                                    @can('hapus produk')
                                        <form action="${deleteUrl}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    @endcan
                                `;
                            },


                        },
                    ]
                });

                $('#categoryFilter').on('change', function() {
                    datatable.ajax.reload();
                });
            }

            // Hook export buttons
            var exportButtons = () => {
                const documentTitle = 'Product Data Report';
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
    $(document).ready(function() {
            $('#otherSelect').select2({
                tags: true,
            });
        });
</script>
<script>
    // Barcode Print Modal Functionality
    var barcodeDataTable;
    var selectedProductsMap = {}; // { [productId]: quantity }
    
    $(document).ready(function() {
        // Open barcode print modal
        $('#printBarcodeBtn').on('click', function() {
            // Show modal first
            $('#barcode_print_modal').modal('show');
            
            // Initialize or reload DataTable
            if ($.fn.DataTable.isDataTable('#barcodeProductTable')) {
                try {
                    $('#barcodeProductTable').DataTable().ajax.reload(null, false);
                } catch (e) {
                    // re-init if needed
                    initializeBarcodeDataTable();
                }
            } else {
                initializeBarcodeDataTable();
            }
        });

        function initializeBarcodeDataTable() {
            barcodeDataTable = $('#barcodeProductTable').DataTable({
                ajax: {
                    url: '{{ route('api.produk') }}',
                    type: 'GET',
                    dataSrc: function(data) {
                        // Filter out malformed/placeholder rows
                        if (!Array.isArray(data)) return [];
                        return data.filter(function(p){
                            if (!p) return false;
                            const name = (p.name || '').toString().trim();
                            const idOk = typeof p.id !== 'undefined' && p.id !== null;
                            return idOk && name !== '' && name !== '-';
                        });
                    }
                },
                columns: [
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<div class="text-center"><input type="checkbox" class="form-check-input product-checkbox" value="${data}"></div>`;
                        }
                    },
                    {
                        data: 'name',
                        render: function(data){
                            return `<span class="fw-semibold">${data}</span>`;
                        }
                    },
                    {
                        data: 'price_sell_eceran',
                        render: function(data) {
                            return `<span class="fw-semibold">` + new Intl.NumberFormat('id-ID', {
                                style: 'currency',
                                currency: 'IDR'
                            }).format(data).replace(',00', '') + `</span>`;
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function(data) {
                            return `<input type="number" class="form-control form-control-sm quantity-input text-center" 
                                           value="1" min="1" max="100" disabled data-product-id="${data}">`;
                        }
                    }
                ],
                pageLength: 10,
                order: [[1, 'asc']],
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ produk",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 produk",
                    infoFiltered: "(difilter dari _MAX_ total produk)",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    },
                    zeroRecords: "Tidak ada data yang ditemukan",
                    emptyTable: "Tidak ada produk yang tersedia"
                },
                dom: '<"row g-2 align-items-center"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row g-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                drawCallback: function() {
                    // Re-attach event handlers after table redraw
                    attachCheckboxHandlers();
                    // Safely get DT instance and guard when undefined
                    var dt;
                    try { dt = $('#barcodeProductTable').DataTable(); } catch(e) { dt = null; }
                    if (!dt) { return; }
                    // Re-apply selection state from map for current page rows
                    $('#barcodeProductTable').find('tbody tr').each(function() {
                        var rowData = dt.row(this).data();
                        if (!rowData) return;
                        var pid = String(rowData.id);
                        var isSelected = selectedProductsMap.hasOwnProperty(pid);
                        var checkbox = $(this).find('.product-checkbox');
                        var qtyInput = $(this).find('.quantity-input');
                        checkbox.prop('checked', isSelected);
                        qtyInput.prop('disabled', !isSelected);
                        if (isSelected) {
                            qtyInput.val(selectedProductsMap[pid]);
                        }
                    });
                    updateSelectAllState();
                }
            });
        }

        function attachCheckboxHandlers() {
            // Update select all state
            updateSelectAllState();
        }

        function updateSelectAllState() {
            // consider filtered rows across all pages
            var totalFiltered = barcodeDataTable ? barcodeDataTable.rows({ search: 'applied' }).data().length : 0;
            var selectedCount = 0;
            if (barcodeDataTable) {
                var ids = {};
                barcodeDataTable.rows({ search: 'applied' }).data().each(function(d){ ids[String(d.id)] = true; });
                Object.keys(selectedProductsMap).forEach(function(pid){ if (ids[pid]) selectedCount++; });
            }

            if (totalFiltered === 0) {
                $('#selectAllBarcodes').prop('checked', false).prop('indeterminate', false);
            } else if (selectedCount === 0) {
                $('#selectAllBarcodes').prop('checked', false).prop('indeterminate', false);
            } else if (selectedCount === totalFiltered) {
                $('#selectAllBarcodes').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#selectAllBarcodes').prop('checked', false).prop('indeterminate', true);
            }
        }

        // Select all checkbox handler
        $(document).on('change', '#selectAllBarcodes', function() {
            const isChecked = $(this).is(':checked');
            // Apply to all filtered rows across all pages
            if (barcodeDataTable) {
                barcodeDataTable.rows({ search: 'applied' }).data().each(function(d){
                    var pid = String(d.id);
                    if (isChecked) {
                        if (!selectedProductsMap.hasOwnProperty(pid)) selectedProductsMap[pid] = 1;
                    } else {
                        if (selectedProductsMap.hasOwnProperty(pid)) delete selectedProductsMap[pid];
                    }
                });
                // Update current page UI
                $('#barcodeProductTable').find('.product-checkbox').prop('checked', isChecked);
                $('#barcodeProductTable').find('.quantity-input').each(function(){
                    var pid = String($(this).data('product-id'));
                    $(this).prop('disabled', !isChecked);
                    if (isChecked) $(this).val(selectedProductsMap[pid] || 1);
                });
                updateSelectAllState();
            }
        });

        // Dedicated "Pilih Semua Produk" button - selects all filtered rows
        $(document).on('click', '#selectAllProductsBtn', function(){
            if (!barcodeDataTable) return;
            barcodeDataTable.rows({ search: 'applied' }).data().each(function(d){
                var pid = String(d.id);
                if (!selectedProductsMap.hasOwnProperty(pid)) selectedProductsMap[pid] = $('#globalLabelQty').val() ? parseInt($('#globalLabelQty').val()) : 1;
            });
            // Update UI on current page
            $('#barcodeProductTable').find('.product-checkbox').prop('checked', true);
            $('#barcodeProductTable').find('.quantity-input').each(function(){
                var pid = String($(this).data('product-id'));
                $(this).prop('disabled', false);
                $(this).val(selectedProductsMap[pid] || 1);
            });
            updateSelectAllState();
        });

        // If nothing is selected but user presses Cetak, and there is no search filter, auto-select ALL product IDs from server
        function fetchAllIdsIfNeeded(callback){
            if (Object.keys(selectedProductsMap).length > 0) return callback();
            var filterSearch = $('.dataTables_filter input').val() || '';
            if (filterSearch.trim() !== '') return callback();
            $.get('{{ route('api.produk') }}', function(list){
                if (Array.isArray(list)) {
                    list.forEach(function(p){ selectedProductsMap[String(p.id)] = parseInt($('#globalLabelQty').val()) || 1; });
                }
                callback();
            });
        }

        // Apply a global quantity to ALL currently selected products
        $(document).on('click', '#applyGlobalQtyBtn', function(){
            var qty = parseInt($('#globalLabelQty').val());
            if (isNaN(qty) || qty < 1 || qty > 100) {
                Swal.fire({ icon: 'warning', title: 'Jumlah tidak valid', text: 'Masukkan angka 1-100' });
                return;
            }
            Object.keys(selectedProductsMap).forEach(function(pid){ selectedProductsMap[pid] = qty; });
            // reflect on current page inputs
            $('#barcodeProductTable').find('.quantity-input').each(function(){
                var pid = String($(this).data('product-id'));
                if (selectedProductsMap.hasOwnProperty(pid)) $(this).val(qty);
            });
        });

        // Individual checkbox handler
        $(document).on('change', '.product-checkbox', function() {
            const productId = $(this).val();
            const isChecked = $(this).is(':checked');
            const quantityInput = $(`.quantity-input[data-product-id="${productId}"]`);
            quantityInput.prop('disabled', !isChecked);
            if (isChecked) {
                var qty = parseInt(quantityInput.val()) || 1;
                selectedProductsMap[String(productId)] = qty;
            } else {
                delete selectedProductsMap[String(productId)];
            }
            
            // Update select all checkbox state
            updateSelectAllState();
        });

        // Quantity change handler keeps map in sync
        $(document).on('input change', '.quantity-input', function(){
            var pid = String($(this).data('product-id'));
            if (selectedProductsMap.hasOwnProperty(pid)) {
                var qty = parseInt($(this).val());
                if (!isNaN(qty) && qty >= 1 && qty <= 100) {
                    selectedProductsMap[pid] = qty;
                }
            }
        });

        // Wait for file completion by polling download endpoint
        function waitForFileCompletion(filename, totalProducts) {
            let attempts = 0;
            const maxAttempts = 150; // 5 minutes max (150 * 2 seconds)
            
            const checkInterval = setInterval(function() {
                attempts++;
                
                $.ajax({
                    url: '{{ route('barcode.download', ':filename') }}'.replace(':filename', filename),
                    type: 'HEAD', // Just check if file exists without downloading
                    success: function() {
                        // File is ready
                        clearInterval(checkInterval);
                        Swal.close();
                        
                        // Show download prompt
                        Swal.fire({
                            icon: 'success',
                            title: 'Barcode PDF Siap!',
                            html: `Barcode PDF untuk ${totalProducts} produk telah berhasil dibuat.`,
                            showCancelButton: true,
                            confirmButtonText: '<i class="ki-duotone ki-file-down"><span class="path1"></span><span class="path2"></span></i> Download PDF',
                            cancelButtonText: 'Tutup',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#6c757d'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open('{{ route('barcode.download', ':filename') }}'.replace(':filename', filename), '_blank');
                            }
                        });
                    },
                    error: function(xhr) {
                        if (attempts >= maxAttempts) {
                            // Timeout
                            clearInterval(checkInterval);
                            Swal.close();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Proses Memakan Waktu Lama',
                                text: 'Pembuatan PDF masih berlangsung. Silakan cek kembali beberapa saat lagi melalui menu Daftar Barcode.',
                                confirmButtonText: 'OK'
                            });
                        }
                        // Otherwise keep waiting
                    }
                });
            }, 2000); // Check every 2 seconds
        }

        // Process products in batches
        async function processBatches(selectedProducts, batchSize = 1500) {
            const batches = [];
            for (let i = 0; i < selectedProducts.length; i += batchSize) {
                batches.push(selectedProducts.slice(i, i + batchSize));
            }

            const totalBatches = batches.length;
            let completedBatches = 0;
            const generatedFiles = [];

            // Show progress
            Swal.fire({
                title: 'Memproses Batch',
                html: `Memproses ${totalBatches} batch (${selectedProducts.length} produk total)...`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Process each batch
            for (let i = 0; i < batches.length; i++) {
                const batch = batches[i];
                const batchNumber = i + 1;
                
                try {
                    // Update progress
                    Swal.update({
                        html: `Memproses batch ${batchNumber}/${totalBatches} (${batch.length} produk)...`
                    });
                    // Ensure loading spinner stays visible after update
                    Swal.showLoading();

                    // Submit batch
                    const response = await $.ajax({
                        url: '{{ route('produk.generate-barcode') }}',
                        type: 'POST',
                        headers: { 
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        data: JSON.stringify({ products: batch }),
                        timeout: 300000 // 5 minute timeout per batch
                    });

                    generatedFiles.push({
                        filename: response.filename,
                        batchNumber: batchNumber,
                        productCount: batch.length
                    });

                    completedBatches++;

                } catch (error) {
                    console.error(`Batch ${batchNumber} failed:`, error);
                    // Continue with next batch
                }
            }

            // Close progress modal
            Swal.close();

            if (completedBatches > 0) {
                // Show completion message with download links
                const failedBatches = totalBatches - completedBatches;
                let message = `${completedBatches} batch berhasil diproses`;
                if (failedBatches > 0) {
                    message += `, ${failedBatches} batch gagal`;
                }

                let downloadHtml = '<div class="mt-3"><h6>File PDF yang tersedia:</h6><ul class="list-unstyled">';
                generatedFiles.forEach(file => {
                    const downloadUrl = '{{ route('barcode.download', ':filename') }}'.replace(':filename', file.filename);
                    downloadHtml += `<li class="mb-2">
                        <a href="${downloadUrl}" class="btn btn-sm btn-primary" target="_blank">
                            <i class="ki-duotone ki-file-down"><span class="path1"></span><span class="path2"></span></i>
                            Batch ${file.batchNumber} (${file.productCount} produk)
                        </a>
                    </li>`;
                });
                downloadHtml += '</ul></div>';

                Swal.fire({
                    icon: failedBatches > 0 ? 'warning' : 'success',
                    title: 'Proses Selesai',
                    html: `<p>${message}</p>${downloadHtml}`,
                    confirmButtonText: 'OK'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Semua batch gagal diproses. Silakan coba lagi.',
                    confirmButtonText: 'OK'
                });
            }
        }

        // Generate barcode button handler
        $('#generateBarcodeBtn').on('click', function() {
            fetchAllIdsIfNeeded(async function(){
                const selectedProducts = Object.keys(selectedProductsMap).map(function(pid){
                    return { id: pid, quantity: selectedProductsMap[pid] };
                });

                if (selectedProducts.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Pilih minimal satu produk untuk dicetak'
                    });
                    return;
                }

                // Close barcode selection modal
                $('#barcode_print_modal').modal('hide');

                // Check if batching is needed
                const batchSize = 1500; // Process 1500 products per batch
                const needsBatching = selectedProducts.length > batchSize;

                if (needsBatching) {
                    const totalBatches = Math.ceil(selectedProducts.length / batchSize);
                    const proceed = await Swal.fire({
                        icon: 'info',
                        title: 'Batch Processing',
                        html: `Anda memilih <strong>${selectedProducts.length}</strong> produk.<br><br>
                               Akan diproses dalam <strong>${totalBatches}</strong> batch (${batchSize} produk per batch).<br><br>
                               <small>Setiap batch akan menghasilkan file PDF terpisah.</small>`,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Proses Batch',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#3085d6'
                    });
                    
                    if (!proceed.isConfirmed) {
                        return;
                    }
                    
                    // Process in batches
                    await processBatches(selectedProducts, batchSize);
                    return;
                }

                // Single batch processing (≤1500 products)
                if (selectedProducts.length > 500) {
                    const proceed = await Swal.fire({
                        icon: 'info',
                        title: 'Konfirmasi',
                        text: `Anda akan mencetak ${selectedProducts.length} produk. Proses mungkin memakan waktu beberapa menit. Lanjutkan?`,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Lanjutkan',
                        cancelButtonText: 'Batal'
                    });
                    
                    if (!proceed.isConfirmed) {
                        return;
                    }
                }

                // Show loading message for single batch
                Swal.fire({
                    title: 'Membuat Barcode PDF',
                    html: 'Silakan tunggu, sedang memproses barcode...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Submit form to generate PDF via queue
                $.ajax({
                    url: '{{ route('produk.generate-barcode') }}',
                    type: 'POST',
                    headers: { 
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({ products: selectedProducts }),
                    success: function(response) {
                        // Start waiting for file completion
                        waitForFileCompletion(response.filename, response.total_products);
                    },
                    error: function(xhr) {
                        Swal.close();
                        let errorMessage = 'Gagal membuat barcode';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMessage
                        });
                    }
                });
            });
        });

        // Reset modal when closed
        $('#barcode_print_modal').on('hidden.bs.modal', function() {
            if (barcodeDataTable) {
                $('#selectAllBarcodes').prop('checked', false).prop('indeterminate', false);
                $('.product-checkbox').prop('checked', false);
                $('.quantity-input').val(1).prop('disabled', true);
                selectedProductsMap = {};
            }
        });
    });
</script>
<style>
    .swal2-toast-large {
        width: 400px !important;
        font-size: 1rem !important;
    }
</style>
@endpush
