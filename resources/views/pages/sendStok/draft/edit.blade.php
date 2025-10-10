@extends('layouts.dashboard')

@section('title', 'Edit Draft Pindah Stok')
@section('menu-title', 'Edit Draft Pindah Stok')

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
</style>
@endpush

@section('content')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="mt-3">
        <form id="form1">
            <div class="row">
                <div class="col-md-2">
                    <div class="mb-3 align-items-center">
                        <label for="inputEmail3" class="col-form-label">Marketing</label>
                        <input id="user_id" type="text" name="user_id" class="form-control"
                            value="{{ $sendStock->user->name }}" readonly />
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3 align-items-center">
                        <label for="from_warehouse" class="col-form-label">Dari Gudang</label>
                        <input id="from_warehouse" type="text" class="form-control"
                            value="{{ $sendStock->fromWarehouse->name }}" readonly />
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3 align-items-center">
                        <label for="status" class="col-form-label">Status</label>
                        <input id="status" type="text" class="form-control" value="DRAFT" readonly
                            style="background-color: #fff3cd; color: #856404;" />
                    </div>
                </div>
            </div>
        </form>
    </div>
    @include('components.alert')
    <div class="gap-2 py-5 card-header align-items-center gap-md-5">
        <div class="card-title">
            <!--begin::Search-->
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-magnifier fs-1 position-absolute ms-4"><span class="path1"></span><span
                        class="path2"></span></i> <input type="text" data-kt-filter="search"
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data inventori"
                    id="searchInput">
                <button type="button" id="addSelectedItems" class="btn btn-success ms-3">
                    <i class="fas fa-cart-plus"></i> Tambah Semua Item Terpilih
                </button>
            </div>
            <!--end::Search-->
        </div>
    </div>
    <div class="card-body">
        <div id="kt_datatable_example_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
            <div class="table-responsive">
                <table class="table align-middle border rounded table-row-dashed fs-6 g-5 dataTable no-footer"
                    id="kt_datatable_example">
                    <thead>
                        <tr class="text-start fw-bold fs-7 text-uppercase">
                            <th>Kelompok</th>
                            <th>Nama Barang</th>
                            <th>Barcode Dus</th>
                            <th>Barcode Eceran</th>
                            <th>Stok</th>
                            <th>Jml Pindah Dus</th>
                            <th>Jml Pindah Pak</th>
                            <th>Jml Pindah Eceran</th>
                            <th class="min-w-100px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 row">
        <div class="col-md-8">
            <div id="kt_datatable_example_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
                <div class="table-responsive">
                    <table class="table align-middle border rounded table-row-dashed fs-6 g-5 dataTable no-footer"
                        id="kt_datatable_cart">
                        <thead>
                            <tr class="text-start fw-bold fs-7 text-uppercase">
                                <th>No</th>
                                <th>Kelompok</th>
                                <th class="min-w-100px">Nama Barang</th>
                                <th>Jml Stok Pindah</th>
                                <th>Satuan</th>
                                <th>Hapus</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold">
                            @foreach ($sendStockDetails as $detail)
                            <tr class="odd" data-cart-id="{{ $detail->id }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $detail->product->group }}</td>
                                <td>{{ $detail->product->name }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-light-primary btn-icon qty-decrease" data-cart-id="{{ $detail->id }}">
                                            <i class="bi bi-dash"></i>
                                        </button>
                                        <input type="number" class="form-control form-control-sm text-center qty-input" value="{{ $detail->quantity }}" readonly style="width: 60px;">
                                        <button type="button" class="btn btn-sm btn-light-primary btn-icon qty-increase" data-cart-id="{{ $detail->id }}">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>{{ $detail->unit->name }}</td>
                                <td>
                                    <form action="{{ route('pindah-stok-draft.destroyCart', $detail->id) }}"
                                        method="POST">
                                        @csrf
                                        @method('delete')
                                        <button type="button" class="btn btn-sm btn-danger btn-delete-item">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <form id="form2" action="{{ route('pindah-stok-draft.update', $sendStock->id) }}" method="post">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col">
                        <div class="mb-1">
                            <label class="form-label">Cabang Tujuan</label>
                            <select name="to_warehouse" class="form-select" data-control="select2"
                                data-placeholder="Pilih cabang tujuan">
                                <option></option>
                                @foreach ($warehouses as $warehouse)
                                @if($warehouse->id != $sendStock->from_warehouse)
                                <option value="{{ $warehouse->id }}" {{ $warehouse->id == $sendStock->to_warehouse ?
                                    'selected' : '' }}>
                                    {{ $warehouse->name }}
                                </option>
                                @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mt-5 row">
                    <div class="d-flex gap-2">
                        <button type="button" onclick="submitForms()" class="btn btn-primary">Update Draft</button>
                        <button type="button" onclick="completeDraft()" class="btn btn-success">Selesaikan
                            Draft</button>
                        <a href="{{ route('pindah-stok-draft.index') }}" class="btn btn-secondary">Kembali</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@includeIf('pages.sell.modal')
@endsection

@push('addon-script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ URL::asset('assets/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>

{{-- calculated form --}}
<script>
    function submitForms() {
        // Submit form2 for updating draft
        document.getElementById('form2').submit();
    }

    function completeDraft() {
        // Show confirmation dialog
        Swal.fire({
            title: 'Konfirmasi Penyelesaian',
            text: 'Apakah Anda yakin ingin menyelesaikan draft ini? Stok akan dipindahkan secara permanen dan draft akan menjadi transaksi selesai.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, selesaikan!',
            cancelButtonText: 'Tidak, batal',
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-secondary'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Create a form to submit the complete request
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route('pindah-stok-draft.complete', $sendStock->id) }}';

                // Add CSRF token
                var csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';
                form.appendChild(csrfInput);

                // Append form to body and submit
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>

{{-- Datatables Top --}}
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
                    left: 2
                },
                "ajax": {
                    url: '{{ route('api.data-all') }}',
                    type: 'GET',
                    data: function(d) {
                        d.searchQuery = $('#searchInput').val();
                    }
                },
                "columns": [{
                        data: "product.group"
                    },
                    {
                        data: "product.name"
                    },
                    {
                        data: "product.barcode_dus",
                        render: function(data, type, row) {
                            if (data == null) {
                                return "Tidak ada barcode";
                            } else {
                                return data;
                            }
                        }
                    },
                    {
                        data: "product.barcode_eceran",
                        render: function(data, type, row) {
                            if (data == null) {
                                return "Tidak ada barcode";
                            } else {
                                return data;
                            }
                        }
                    },
                    {
                        data: "quantity"
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `<input type="text" name="quantity_dus" class="form-control">`;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `<input type="text" name="quantity_pak" class="form-control">`;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `<input type="text" name="quantity_eceran" class="form-control">`;
                        }
                    },
                    {
                        data: null,
                        className: 'text-center',
                        render: function(data, type, row) {
                            return `<button class="btn btn-primary btn-submit" data-product-id="${row.product.id}"><i class="fas fa-cart-plus"></i></button>`;
                        }
                    }
                ],
                "columnDefs": [{
                        target: 0,
                        className: 'min-w-100px',
                    },
                    {
                        target: 1,
                        className: 'min-w-100px',
                    },
                    {
                        targets: -1,
                        className: 'text-center'
                    }
                ],
            });

            $(table).on('click', '.btn-submit', function() {
                var rowData = datatable.row($(this).closest('tr')).data();
                var productId = $(this).data('product-id');
                var quantityDus = $(this).closest('tr').find('input[name="quantity_dus"]').val();
                var quantityPak = $(this).closest('tr').find('input[name="quantity_pak"]').val();
                var quantityEceran = $(this).closest('tr').find('input[name="quantity_eceran"]').val();

                if (!quantityDus && !quantityPak && !quantityEceran) {
                    Swal.fire({
                        title: 'Peringatan',
                        text: 'Masukkan minimal satu quantity',
                        icon: 'warning',
                        confirmButtonText: 'Ok, mengerti!',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                    return;
                }

                var inputRequest = {
                    product_id: productId,
                    quantity_dus: quantityDus,
                    quantity_pak: quantityPak,
                    quantity_eceran: quantityEceran,
                };

                // Send AJAX request
                $.ajax({
                    url: '{{ route('pindah-stok-draft.addItem', $sendStock->id) }}',
                    type: 'POST',
                    data: inputRequest,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        // reload page
                        location.reload();
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            title: 'Error',
                            text: 'Gagal menambahkan produk',
                            icon: 'error',
                            confirmButtonText: 'Ok, mengerti!',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            }
                        });
                    }
                });
            });

            // Handle bulk item addition
            $('#addSelectedItems').on('click', function() {
                var requests = [];
                var hasItems = false;

                // Loop through all visible rows in the datatable
                datatable.rows({ search: 'applied' }).every(function() {
                    var row = this.node();
                    var rowData = this.data();

                    var quantityDus = $(row).find('input[name="quantity_dus"]').val();
                    var quantityPak = $(row).find('input[name="quantity_pak"]').val();
                    var quantityEceran = $(row).find('input[name="quantity_eceran"]').val();

                    // Check if any quantity is filled
                    if (quantityDus || quantityPak || quantityEceran) {
                        hasItems = true;
                        requests.push({
                            product_id: rowData.product.id,
                            unit_dus: rowData.product.unit_dus,
                            unit_pak: rowData.product.unit_pak,
                            unit_eceran: rowData.product.unit_eceran,
                            quantity_dus: quantityDus || 0,
                            quantity_pak: quantityPak || 0,
                            quantity_eceran: quantityEceran || 0
                        });
                    }
                });

                if (!hasItems) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tidak ada item yang dipilih',
                        text: 'Silakan isi quantity untuk produk yang ingin ditambahkan ke draft'
                    });
                    return;
                }

                // Send bulk AJAX request
                $.ajax({
                    url: '{{ route('pindah-stok-draft.addItem', $sendStock->id) }}',
                    type: 'POST',
                    data: {
                        requests: requests
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Item berhasil ditambahkan ke draft',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = 'Terjadi kesalahan saat menambahkan item';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: errorMessage
                        });
                    }
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
                handleSearchDatatable();
                $(table).on('keydown', 'input[name^="quantity_"], input[name^="diskon_"]', function(event) {
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
</script>

{{-- Datatables Bottom --}}
<script>
    "use strict";

    // Initialize the data table
    var KTDatatablesCart = function() {
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
                table = document.querySelector('#kt_datatable_cart');

                if (!table) {
                    return;
                }

                initDatatable();
                handleSearchDatatable();
            }
        };
    }();

    // On document ready
    KTUtil.onDOMContentLoaded(function() {
        KTDatatablesCart.init();
    });

    // Delete item confirmation
    $(document).on('click', '.btn-delete-item', function() {
        var form = $(this).closest('form');

        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: 'Apakah Anda yakin ingin menghapus item ini dari draft?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Tidak, batal',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-secondary'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // Cart quantity update handlers
    $(document).on('click', '.qty-increase, .qty-decrease', function() {
        const button = $(this);
        const cartId = button.data('cart-id');
        const row = $(`tr[data-cart-id="${cartId}"]`);
        const input = row.find('.qty-input');
        const currentQty = parseInt(input.val());
        const isIncrease = button.hasClass('qty-increase');
        const newQty = isIncrease ? currentQty + 1 : Math.max(1, currentQty - 1);

        if (newQty === currentQty) return;

        button.prop('disabled', true);
        row.find('.qty-increase, .qty-decrease').prop('disabled', true);

        $.ajax({
            url: `/pindah-stok-draft/cart/${cartId}/quantity`,
            method: 'PATCH',
            data: {
                quantity: newQty,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    input.val(response.data.quantity);
                    toastr.success('Quantity updated successfully');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Failed to update quantity';
                toastr.error(message);
                input.val(currentQty);
            },
            complete: function() {
                row.find('.qty-increase, .qty-decrease').prop('disabled', false);
            }
        });
    });
</script>
@endpush
