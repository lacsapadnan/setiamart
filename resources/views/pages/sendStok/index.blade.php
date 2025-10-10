@extends('layouts.dashboard')

@section('title', 'Pindah Stok')
@section('menu-title', 'Pindah Stok')

@push('addon-style')
<link href="assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
@endpush

@include('includes.datatable-pagination')

@section('content')
{{-- session success --}}
@include('components.alert')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="gap-2 py-5 card-header align-items-center gap-md-5">
        <div class="card-title">
            <!--begin::Search-->
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-magnifier fs-1 position-absolute ms-4"><span class="path1"></span><span
                        class="path2"></span></i> <input type="text" data-kt-filter="search"
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data supplier">
            </div>
            <!--end::Search-->
        </div>
        <div class="gap-5 card-toolbar flex-row-fluid justify-content-end">
            <!--begin::Export dropdown-->
            <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click"
                data-kt-menu-placement="bottom-end">
                <i class="ki-duotone ki-exit-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                Export Data
            </button>
            <a href="{{ route('pindah-stok.create') }}" type="button" class="btn btn-primary">
                Tambah Data
            </a>
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
                            <th>No.</th>
                            <th>Kasir</th>
                            <th>No. Pindah Stok</th>
                            <th>Cabang Awal</th>
                            <th>Cabang Tujuan</th>
                            <th>Tanggal Pindah Stok</th>
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
@includeIf('pages.sendStok.modal')
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
                    "info": true,
                    "order": [],
                    "dom": '<"top"lp>rt<"bottom"lp><"clear">',
                    "ajax": {
                        "url": "{{ route('api.pindah-stok') }}",
                        "type": "GET",
                        "dataSrc": ""
                    },
                    "columns": [{
                            "data": null,
                            "sortable": false,
                            "render": function(data, type, row, meta) {
                                return meta.row + meta.settings._iDisplayStart + 1;
                            }
                        },
                        {
                            "data": "user.name",
                            defaultContent: '-'
                        },
                        {
                            "data": "send_stock_number",
                        },
                        {
                            "data": "from_warehouse.name",
                        },
                        {
                            "data": "to_warehouse.name",
                        },
                        {
                            "data": "created_at",
                            "render": function(data, type, row) {
                                return moment(data).format('DD MMMM YYYY');
                            }
                        },
                        {
                            "data": "id",
                            "render": function(data, type, row) {
                                const url = '/pindah-stok/' + data
                                return `
                                    <a href="#" class="btn btn-sm btn-primary" onclick="openModal(${data})">Detail</a>
                                    <a href="/pindah-stok/print/${data}" target="_blank" class="btn btn-sm btn-success">Print</a>
                                    @can('hapus pindah stok')
                                        <button type="button" onclick="deleteSendStock('${url}')" class="btn btn-sm btn-danger"><i class="ki-solid ki-trash"></i>Hapus</button>
                                    @endcan
                                `;
                            }
                        }
                    ],
                });
            }

            // Hook export buttons
            var exportButtons = () => {
                const documentTitle = 'Pindah Stok Data Report';
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
    var datatable;

            function openModal(id) {
        // Clear the table body
        $('#kt_datatable_detail tbody').empty();

        // Remove any existing total display
        $('#kt_datatable_detail').next('h2').remove();

        // Check if DataTable instance exists and destroy it
        if ($.fn.DataTable.isDataTable('#kt_datatable_detail')) {
            datatable.destroy();
        }

        // Send a request to fetch the sell details for the given ID
        $.ajax({
            url: '/pindah-stok/' + id,
            method: 'GET',
            success: function(response) {
                // Initialize the DataTable on the table
                datatable = $('#kt_datatable_detail').DataTable({
                    data: response,
                    columns: [{
                            data: 'product.name'
                        },
                        {
                            data: 'unit.name'
                        },
                        {
                            data: 'quantity'
                        },
                        {
                            data: 'product.price_sell_dus',
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
                            data: 'product.price_sell_pak',
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
                            data: 'product.price_sell_eceran',
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
                            data: null,
                            render: function(data, type, row) {
                                var price = 0;

                                // Determine price based on unit
                                if (row.unit_id === row.product.unit_dus) {
                                    price = row.product.price_sell_dus;
                                } else if (row.unit_id === row.product.unit_pak) {
                                    price = row.product.price_sell_pak;
                                } else {
                                    price = row.product.price_sell_eceran;
                                }

                                var total = row.quantity * price;
                                var formattedTotal = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(total);
                                formattedTotal = formattedTotal.replace(",00", "");
                                return formattedTotal;
                            }
                        },
                    ]
                });

                // Calculate grand total based on quantity and appropriate unit price
                var grandTotal = response.reduce((acc, item) => {
                    var price = 0;

                    // Determine price based on unit
                    if (item.unit_id === item.product.unit_dus) {
                        price = item.product.price_sell_dus;
                    } else if (item.unit_id === item.product.unit_pak) {
                        price = item.product.price_sell_pak;
                    } else {
                        price = item.product.price_sell_eceran;
                    }

                    return acc + (item.quantity * price);
                }, 0);

                var formattedGrandTotal = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR'
                }).format(grandTotal).replace(",00", "");

                // Display grand total with red styling like in the reference
                $('#kt_datatable_detail').after(`<h2 style="color: #d33; border: 2px solid #d33; padding: 10px; text-align: center; margin-top: 15px;">Total Pindah Stok: ${formattedGrandTotal}</h2>`);

                // Open the modal
                $('#kt_modal_1').modal('show');
            },
            error: function(xhr, status, error) {
                console.error(error); // Handle the error appropriately
            }
        });
    }
</script>
<script>
    function deleteSendStock(url) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data ini akan dihapus!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    var form = document.createElement('form');
                    form.action = url;
                    form.method = 'POST';
                    form.innerHTML = '@csrf @method('delete')';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
</script>
@endpush
