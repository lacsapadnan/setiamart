@extends('layouts.dashboard')

@section('title', 'Retur Penjualan')
@section('menu-title', 'Retur Penjualan')

@push('addon-style')
<link href="{{ URL::asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet"
    type="text/css" />
@endpush

@section('content')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="mt-3">
        <form id="form1">
            <div class="row">
                <div class="col-md-2">
                    <div class="mb-3 align-items-center">
                        <label for="inputEmail3" class="col-form-label">Tanggal</label>
                        <div class="input-group" id="kt_td_picker_date_only" data-td-target-input="nearest"
                            data-td-target-toggle="nearest">
                            <input id="kt_td_picker_date_only_input" type="text" class="form-control"
                                data-td-target="#kt_td_picker_date_only" name="transaction_date"
                                value="{{ date('Y-m-d') }}" disabled>
                            <!-- Set the value to today's date and make it readonly -->
                            <span class="input-group-text" data-td-target="#kt_td_picker_date_only"
                                data-td-toggle="datetimepicker">
                                <i class="ki-duotone ki-calendar fs-2"><span class="path1"></span><span
                                        class="path2"></span></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3 align-items-center">
                        <label for="order_number" class="col-form-label">Faktur</label>
                        <input id="order_number" type="text" name="order_number" class="form-control"
                            value="{{ $penjualan->order_number }}" readonly />
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3 align-items-center">
                        <label for="inputEmail3" class="col-form-label">Marketing</label>
                        <input id="user_id" type="text" name="user_id" class="form-control"
                            value="{{ auth()->user()->name }}" />
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="gap-2 py-5 card-header align-items-center gap-md-5">
        <div class="card-title">
            <!--begin::Search-->
            <div class="my-1 d-flex align-items-center position-relative">
                <i class="ki-duotone ki-magnifier fs-1 position-absolute ms-4"><span class="path1"></span><span
                        class="path2"></span></i> <input type="text" data-kt-filter="search"
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data barang">
            </div>
            <!--end::Search-->
        </div>
    </div>
    <div class="card-body">
        <div id="kt_datatable_example_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
            <div class="table-responsive">
                <table class="table align-middle rounded border table-row-dashed fs-6 g-5 dataTable no-footer"
                    id="kt_datatable_example">
                    <thead>
                        <tr class="text-start fw-bold fs-7 text-uppercase">
                            <th>Nama Barang</th>
                            <th>Unit Penjualan</th>
                            <th>Harga</th>
                            <th>Quantity Penjualan</th>
                            <th>Unit Retur</th>
                            <th>Tersedia untuk Retur</th>
                            <th class="max-w-50px">Quantity Retur</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="row" style="margin-top: 100px">
        <div class="col">
            <div id="kt_datatable_example_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
                <div class="table-responsive">
                    <table class="table align-middle rounded border table-row-dashed fs-6 g-5 dataTable no-footer"
                        id="kt_datatable_cart">
                        <thead>
                            <tr class="text-start fw-bold fs-7 text-uppercase">
                                <th>No</th>
                                <th class="min-w-100px">Nama Barang</th>
                                <th>Unit</th>
                                <th>Jml Retur</th>
                                <th>Harga</th>
                                <th>Subtotal</th>
                                <th>Hapus</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold">
                            @forelse ($cart as $cart)
                            <tr class="odd" data-cart-id="{{ $cart->id }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $cart->product->name }}</td>
                                <td>{{ $cart->unit->name }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-light-primary btn-icon qty-decrease" data-cart-id="{{ $cart->id }}">
                                            <i class="bi bi-dash"></i>
                                        </button>
                                        <input type="number" class="form-control form-control-sm text-center qty-input" value="{{ $cart->quantity }}" readonly style="width: 60px;">
                                        <button type="button" class="btn btn-sm btn-light-primary btn-icon qty-increase" data-cart-id="{{ $cart->id }}">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>Rp{{ number_format($cart->price) }}</td>
                                <td class="cart-subtotal">Rp{{ number_format($cart->price * $cart->quantity) }}</td>
                                <td>
                                    <form action="{{ route('penjualan-retur.destroyCart', $cart->id) }}" method="POST">
                                        @csrf
                                        @method('delete')
                                        <button class="btn btn-sm btn-danger">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            {{-- show empty --}}
                            <tr></tr>
                            <td colspan="7" class="text-center">
                                <h5 class="my-5">Tidak ada barang retur</h5>
                            </td>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12">
            <form id="form2" action="{{ route('penjualan-retur.store') }}" method="post">
                @csrf
                <input type="hidden" name="sell_id" id="sell_id_form2" value="{{ $sellId }}">
                <div class="mt-5 row">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('addon-script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>

{{-- Datatables --}}
<script>
    "use strict";

        // Global variable to store available quantities for each product
        var availableQuantities = {};

        // Class definition
        var KTDatatablesExample = function() {
            // Shared variables
            var table;
            var datatable;

            // Function to get available quantities for a product
            var getAvailableQuantities = function(sellId, productId, callback) {
                if (availableQuantities[productId]) {
                    callback(availableQuantities[productId]);
                    return;
                }

                $.ajax({
                    url: `/penjualan-retur/api/available-quantities/${sellId}/${productId}`,
                    type: 'GET',
                    success: function(response) {
                        availableQuantities[productId] = response;
                        callback(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching available quantities:', error);
                        callback(null);
                    }
                });
            };

            // Function to create unit dropdown
            var createUnitDropdown = function(availableUnits, selectedUnitId = null) {
                var options = '';
                if (availableUnits.eceran && availableUnits.eceran.quantity > 0) {
                    var selected = selectedUnitId == availableUnits.eceran.unit_id ? 'selected' : '';
                    options += `<option value="${availableUnits.eceran.unit_id}" ${selected}>${availableUnits.eceran.unit_name}</option>`;
                }
                if (availableUnits.pak && availableUnits.pak.quantity > 0) {
                    var selected = selectedUnitId == availableUnits.pak.unit_id ? 'selected' : '';
                    options += `<option value="${availableUnits.pak.unit_id}" ${selected}>${availableUnits.pak.unit_name}</option>`;
                }
                if (availableUnits.dus && availableUnits.dus.quantity > 0) {
                    var selected = selectedUnitId == availableUnits.dus.unit_id ? 'selected' : '';
                    options += `<option value="${availableUnits.dus.unit_id}" ${selected}>${availableUnits.dus.unit_name}</option>`;
                }
                return `<select class="form-select unit-selector">${options}</select>`;
            };

            // Function to get available quantity for selected unit
            var getAvailableQuantityForUnit = function(availableUnits, unitId) {
                if (availableUnits.eceran && availableUnits.eceran.unit_id == unitId) {
                    return availableUnits.eceran.quantity;
                }
                if (availableUnits.pak && availableUnits.pak.unit_id == unitId) {
                    return availableUnits.pak.quantity;
                }
                if (availableUnits.dus && availableUnits.dus.unit_id == unitId) {
                    return availableUnits.dus.quantity;
                }
                return 0;
            };

            // Function to get price for selected unit
            var getPriceForUnit = function(row, unitId) {
                var product = row.product;

                // Check if the selected unit matches the original sale unit
                if (unitId == row.unit_id) {
                    return row.price - (row.diskon / row.quantity);
                }

                // Calculate price based on unit conversion
                var basePrice = row.price - (row.diskon / row.quantity);

                // If original was eceran, calculate other units
                if (row.unit_id == product.unit_eceran) {
                    if (unitId == product.unit_pak) {
                        return basePrice * product.pak_to_eceran;
                    } else if (unitId == product.unit_dus) {
                        return basePrice * product.dus_to_eceran;
                    }
                }
                // If original was pak, calculate other units
                else if (row.unit_id == product.unit_pak) {
                    if (unitId == product.unit_eceran) {
                        return basePrice / product.pak_to_eceran;
                    } else if (unitId == product.unit_dus) {
                        return basePrice * (product.dus_to_eceran / product.pak_to_eceran);
                    }
                }
                // If original was dus, calculate other units
                else if (row.unit_id == product.unit_dus) {
                    if (unitId == product.unit_eceran) {
                        return basePrice / product.dus_to_eceran;
                    } else if (unitId == product.unit_pak) {
                        return basePrice / (product.dus_to_eceran / product.pak_to_eceran);
                    }
                }

                return basePrice;
            };

            // Private functions
            var initDatatable = function() {
                // Set date data order
                const tableRows = table.querySelectorAll('tbody tr');
                // get id from route
                const id = window.location.pathname.split('/').pop();

                // Init datatable --- more info on datatables: https://datatables.net/manual/
                datatable = $(table).DataTable({
                    "info": false,
                    'order': [],
                    'pageLength': 10,
                    "ajax": {
                        url: '/penjualan/' + id + '?details_only=true',
                        type: 'GET',
                        dataSrc: '',
                    },
                    "columns": [
                        {
                            data: 'product.name'
                        },
                        {
                            data: 'unit.name'
                        },
                        {
                            data: 'price',
                            render: function(data, type, row) {
                                var totalPrice = row.price - (row.diskon / row.quantity);
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(totalPrice);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        {
                            data: 'quantity'
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<div class="unit-dropdown-container" data-product-id="${row.product.id}">Loading...</div>`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<div class="available-quantity-container" data-product-id="${row.product.id}">-</div>`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `
                                <input type="number" name="quantity_retur" class="form-control" min="0" step="0.01">
                                <input type="hidden" name="unit_retur" value="">
                                <input type="hidden" name="price_retur" value="">
                                <input type="hidden" name="sell_id" value="${id}">
                                `;
                            }
                        },
                        {
                            data: null,
                            className: 'text-center',
                            render: function(data, type, row) {
                                return `<button class="btn btn-warning btn-submit" data-product-id="${row.product.id}">Pilih</button>`;
                            }
                        }
                    ],
                    "drawCallback": function(settings) {
                        // Load available quantities for each product after table is drawn
                        var api = this.api();
                        api.rows().every(function(rowIdx, tableLoop, rowLoop) {
                            var row = this.data();
                            var productId = row.product.id;

                            getAvailableQuantities(id, productId, function(response) {
                                if (response && response.available_units) {
                                    var unitDropdown = createUnitDropdown(response.available_units, row.unit.id);
                                    $(`.unit-dropdown-container[data-product-id="${productId}"]`).html(unitDropdown);

                                    // Update available quantity display
                                    var selectedUnitId = row.unit.id;
                                    var availableQty = getAvailableQuantityForUnit(response.available_units, selectedUnitId);
                                    $(`.available-quantity-container[data-product-id="${productId}"]`).text(availableQty.toFixed(2));

                                    // Set initial hidden values
                                    var tableRow = $(table).find(`[data-product-id="${productId}"]`).closest('tr');
                                    tableRow.find('input[name="unit_retur"]').val(selectedUnitId);
                                    tableRow.find('input[name="price_retur"]').val(getPriceForUnit(row, selectedUnitId));

                                    // Set max attribute for quantity input
                                    var availableQty = getAvailableQuantityForUnit(response.available_units, selectedUnitId);
                                    tableRow.find('input[name="quantity_retur"]').attr('max', availableQty);
                                }
                            });
                        });
                    }
                });

                // Handle unit selection change
                $(table).on('change', '.unit-selector', function() {
                    var selectedUnitId = $(this).val();
                    var tableRow = $(this).closest('tr');
                    var productId = $(this).closest('.unit-dropdown-container').data('product-id');

                    // Update hidden values
                    tableRow.find('input[name="unit_retur"]').val(selectedUnitId);

                    // Update available quantity display
                    if (availableQuantities[productId]) {
                        var availableQty = getAvailableQuantityForUnit(availableQuantities[productId].available_units, selectedUnitId);
                        $(`.available-quantity-container[data-product-id="${productId}"]`).text(availableQty.toFixed(2));

                        // Update price
                        var rowData = datatable.row(tableRow).data();
                        var price = getPriceForUnit(rowData, selectedUnitId);
                        tableRow.find('input[name="price_retur"]').val(price);

                        // Update max attribute for quantity input
                        tableRow.find('input[name="quantity_retur"]').attr('max', availableQty);
                    }
                });

                // Handle quantity input validation
                $(table).on('input', 'input[name="quantity_retur"]', function() {
                    var quantity = parseFloat($(this).val()) || 0;
                    var maxQuantity = parseFloat($(this).attr('max'));

                    // Only validate if max attribute is properly set and greater than 0
                    if (maxQuantity !== undefined && maxQuantity !== null && !isNaN(maxQuantity) && maxQuantity > 0) {
                        if (quantity > maxQuantity) {
                            $(this).val(maxQuantity);
                            alert(`Jumlah retur tidak boleh melebihi ${maxQuantity}`);
                        }
                    }
                });

                $(table).on('keypress', 'input[name="quantity_retur"]', function(event) {
                    if (event.keyCode === 13) {
                        event.preventDefault();
                        $(this).closest('tr').find('.btn-submit').click();
                    }
                });

                $(table).on('click', '.btn-submit', function() {
                    var inputRequests = [];
                    var hasValidInput = false;

                    $(table).find('tbody tr').each(function() {
                        var quantityRetur = $(this).find('input[name="quantity_retur"]').val();
                        var unitRetur = $(this).find('input[name="unit_retur"]').val();
                        var priceRetur = $(this).find('input[name="price_retur"]').val();
                        var productId = $(this).closest('tr').find('.btn-submit').data('product-id');
                        var sellId = $(this).find('input[name="sell_id"]').val();

                        if (quantityRetur && parseFloat(quantityRetur) > 0) {
                            hasValidInput = true;
                            // Create an input object for the current row
                            var inputRequest = {
                                product_id: productId,
                                quantity: parseFloat(quantityRetur),
                                unit_id: unitRetur,
                                price: parseFloat(priceRetur),
                                sell_id: sellId
                            };

                            inputRequests.push(inputRequest);
                        }
                    });

                    if (!hasValidInput) {
                        alert('Silakan masukkan jumlah retur yang valid');
                        return;
                    }

                    // Send AJAX request
                    $.ajax({
                        url: '{{ route('penjualan-retur.addCart') }}',
                        type: 'POST',
                        data: {
                            input_requests: inputRequests
                        },
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            // Clear available quantities cache
                            availableQuantities = {};
                            // reload page
                            location.reload();
                        },
                        error: function(xhr, status, error) {
                            var response = xhr.responseJSON;
                            if (response && response.errors) {
                                // Handle validation errors and display alerts
                                var errorMessage = '';
                                for (var key in response.errors) {
                                    errorMessage += response.errors[key] + '\n';
                                }
                                alert(errorMessage);
                            } else {
                                alert('An error occurred while processing your request.');
                            }
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
                }
            };
        }();

        // On document ready
        KTUtil.onDOMContentLoaded(function() {
            KTDatatablesExample.init();
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
                url: `/penjualan-retur/cart/${cartId}/quantity`,
                method: 'PATCH',
                data: {
                    quantity: newQty,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        input.val(response.data.quantity);
                        row.find('.cart-subtotal').text('Rp' + new Intl.NumberFormat('id-ID').format(response.data.subtotal));
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