@extends('layouts.dashboard')

@section('title', 'Penjualan')
@section('menu-title', 'Penjualan Barang')

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
                        <label for="inputEmail3" class="col-form-label">Marketing</label>
                        <input id="user_id" type="text" name="user_id" class="form-control"
                            value="{{ $sell->cashier->name }}" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3 row align-items-center">
                        <label for="inputEmail3" class="col-form-label">Customer</label>
                        <select id="customer" class="form-select" name="customer_id" data-control="select2"
                            data-placeholder="Pilih customer" data-allow-clear="true" required>
                            <option readonly disabled>Pilih Customer</option>
                            @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" {{ $customer->id == $sell->customer_id ? 'selected' : ''
                                }}>{{ $customer->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3 align-items-center">
                        <label for="inputEmail3" class="col-form-label">Piutang</label>
                        <input id="piutang" type="text" name="piutang" class="form-control"
                            placeholder="Masukan piutang" value="0" readonly />
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3 align-items-center">
                        <label for="inputEmail3" class="col-form-label">No. Order</label>
                        <input id="order_number" type="text" name="order_number" class="form-control"
                            placeholder="Masukan no.order" value="{{ $orderNumber }}" readonly />
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
                    class="form-control form-control-solid w-250px ps-14" placeholder="Cari data inventori"
                    id="searchInput">
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
                            <th>Kelompok</th>
                            <th>Nama Barang</th>
                            @php
                            $isOutOfTown = auth()->user()->warehouse->isOutOfTown ?? false;
                            @endphp
                            @if($isOutOfTown)
                            <th>Promo Luar Kota</th>
                            @else
                            <th>Promo</th>
                            @endif
                            <th>Stok</th>
                            <th>Jml Per Dus</th>
                            <th>Jml Per Pak</th>
                            <th>Jml Jual Dus</th>
                            <th>Diskon Dus</th>
                            <th>Harga Jual Dus</th>
                            <th>Jml Jual Pak</th>
                            <th>Diskon Pak</th>
                            <th>Harga Jual Pak</th>
                            <th>Jml Jual Eceran</th>
                            <th>Diskon Eceran</th>
                            <th>Harga Jual Eceran</th>
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
                    <table class="table align-middle rounded border table-row-dashed fs-6 g-5 dataTable no-footer"
                        id="kt_datatable_cart">
                        <thead>
                            <tr class="text-start fw-bold fs-7 text-uppercase">
                                <th>No</th>
                                <th>Kelompok</th>
                                <th class="min-w-100px">Nama Barang</th>
                                <th>Jml Beli</th>
                                <th>Satuan</th>
                                <th>Diskon</th>
                                <th>Subtotal</th>
                                <th>Hapus</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold">
                            @foreach ($cart as $cart)
                            <tr class="odd" data-cart-id="{{ $cart->id }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $cart->product->group }}</td>
                                <td>{{ $cart->product->name }}</td>
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
                                <td>{{ $cart->unit->name }}</td>
                                <td>{{ $cart->diskon ?? 0 }}</td>
                                <td class="cart-subtotal">
                                    {{ number_format($cart->price * $cart->quantity - $cart->diskon) }}
                                </td>
                                <td>
                                    <form action="{{ route('penjualan-draft.destroyCart', $cart->id) }}" method="POST">
                                        @csrf
                                        @method('delete')
                                        <input type="hidden" name="product_id" value="{{ $cart->product_id }}">
                                        <button class="btn btn-sm btn-danger">
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
            <form id="form2" action="{{ route('penjualan-draft.update', $sell->id) }}" method="post">
                @csrf
                @method('put')
                <input type="hidden" name="transaction_date" id="transaction_date_form2">
                <input type="hidden" name="order_number" id="order_number_form2">
                <input type="hidden" name="customer" id="customer_form2">
                <input type="hidden" name="user_id" id="user_id_form2">
                <input type="hidden" name="status" id="status_form2">
                <input type="hidden" name="cashier_id" id="cashier_form2" value="{{ auth()->id() }}">

                <div class="row">
                    <div class="col">
                        <div class="mb-1">
                            <label for="subtotal" class="col-form-label">Subtotal</label>
                            <input type="text" name="subtotal" class="form-control" id="subtotal"
                                value="{{ number_format($subtotal) }}" readonly />
                        </div>

                        <!-- Parent div with id="bayarDiv" -->
                        <div class="mb-1" id="bayarDiv">
                            <label for="bayar" class="col-form-label">Bayar</label>
                            <input type="text" name="pay" class="form-control" id="bayar" oninput="calculateTotal()" />
                        </div>

                        <!-- Additional input fields initially hidden -->
                        <div class="mb-1" style="display: none;" id="transferDiv">
                            <label for="transfer" class="col-form-label">Transfer</label>
                            <input type="text" name="transfer" class="form-control" id="transfer"
                                oninput="formatNumber(this); handleSplitPaymentInput(this, 'transfer');"
                                placeholder="Masukkan jumlah transfer" />
                            <small class="form-text text-muted">Sistem akan otomatis menghitung sisa untuk cash</small>
                        </div>

                        <div class="mb-1" style="display: none;" id="cashDiv">
                            <label for="cash" class="col-form-label">Cash</label>
                            <input type="text" name="cash" class="form-control" id="cash"
                                oninput="formatNumber(this); handleSplitPaymentInput(this, 'cash');"
                                placeholder="Masukkan jumlah cash" />
                            <small class="form-text text-muted">Sistem akan otomatis menghitung sisa untuk
                                transfer</small>
                        </div>

                        <div class="mb-1">
                            <label for="grandTotal" class="col-form-label">Grand Total</label>
                            <input type="text" name="grand_total" class="form-control" id="grandTotal" readonly />
                        </div>

                        <div class="mb-1">
                            <label for="grandTotal" class="col-form-label">Metode Bayar</label>
                            <select name="payment_method" class="form-select" aria-label="Select example"
                                onchange="togglePaymentFields()">
                                <option value="">Pilih Pembayaran</option>
                                <option value="transfer">Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="split">Split Payment</option>
                            </select>
                        </div>

                        <div class="mb-1">
                            <label for="kembali" class="col-form-label">Kembali</label>
                            <input type="text" name="change" class="form-control" id="kembali" readonly />
                        </div>

                        <div class="mb-1" id="sisaDiv" style="display: none;">
                            <label for="sisa" class="col-form-label">Sisa</label>
                            <input type="text" name="sisa" class="form-control" id="sisa" readonly />
                        </div>
                    </div>
                </div>
                <div class="mt-5 row">
                    <button type="button" onclick="submitForms()" class="btn btn-primary">Simpan</button>
                    <button type="button" onclick="draftForms()" class="mt-5 btn btn-danger">Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>
@includeIf('pages.sell.modal')
@includeIf('pages.sell.modal-password')
@endsection

@push('addon-script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ URL::asset('assets/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ URL::asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>

{{-- calculated form --}}
<script>
    function formatNumber(input) {
            // Hapus semua karakter non-digit
            let value = input.value.replace(/\D/g, '');

            // Tambahkan separator ribuan
            value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            // Set nilai input dengan format yang baru
            input.value = value;
        }

        function calculateTotal() {
            var subtotal = parseFloat(document.getElementById('subtotal').value.replace(/[^0-9.-]+/g, '')) || 0;
            var grandTotal = subtotal;

            var paymentMethod = document.getElementsByName('payment_method')[0].value;
            var transfer = parseFloat(document.getElementById('transfer').value.replace(/[^0-9.-]+/g, '')) || 0;
            var cash = parseFloat(document.getElementById('cash').value.replace(/[^0-9.-]+/g, '')) || 0;
            var bayar = parseFloat(document.getElementById('bayar').value.replace(/[^0-9.-]+/g, '')) || 0;

            if (paymentMethod === 'split') {
                // Calculate grand total based on the sum of transfer and cash
                grandTotal = subtotal;
            }

            var kembali = calculateKembali(paymentMethod, grandTotal, transfer, cash);
            var sisa = calculateSisa(paymentMethod, grandTotal, transfer, cash, bayar);

            document.getElementById('grandTotal').value = new Intl.NumberFormat('id-ID').format(grandTotal);
            document.getElementById('kembali').value = new Intl.NumberFormat('id-ID').format(kembali);

            // Show/hide and update sisa field
            var sisaDiv = document.getElementById('sisaDiv');
            if (sisa > 0) {
                sisaDiv.style.display = 'block';
                document.getElementById('sisa').value = new Intl.NumberFormat('id-ID').format(sisa);
            } else {
                sisaDiv.style.display = 'none';
            }
        }

        function calculateKembali(paymentMethod, grandTotal, transfer, cash) {
            var bayar = parseFloat(document.getElementById('bayar').value.replace(/[^0-9.-]+/g, '')) || 0;

            if (paymentMethod === 'transfer' || paymentMethod === 'cash') {
                // For "Transfer" or "Cash," calculate the change as (transfer or cash) - grand total
                return Math.max((paymentMethod === 'transfer' ? transfer : cash) - grandTotal, 0);
            } else if (paymentMethod === 'split') {
                // For "Split Payment," calculate the change as (transfer + cash) - grand total
                return Math.max(transfer + cash - grandTotal, 0);
            } else {
                // Handle other payment methods, if any, here
                return Math.max(bayar - grandTotal, 0);
            }
        }

        function calculateSisa(paymentMethod, grandTotal, transfer, cash, bayar) {
            var totalPayment = 0;

            if (paymentMethod === 'transfer') {
                totalPayment = transfer;
            } else if (paymentMethod === 'cash') {
                totalPayment = cash;
            } else if (paymentMethod === 'split') {
                totalPayment = transfer + cash;
            } else {
                totalPayment = bayar;
            }

            return Math.max(grandTotal - totalPayment, 0);
        }

        function togglePaymentFields() {
            const paymentMethod = document.getElementsByName('payment_method')[0].value;
            const bayarDiv = document.getElementById('bayarDiv');
            const transferDiv = document.getElementById('transferDiv');
            const cashDiv = document.getElementById('cashDiv');

            bayarDiv.style.display = 'none';
            transferDiv.style.display = 'none';
            cashDiv.style.display = 'none';

            if (paymentMethod === 'transfer') {
                transferDiv.style.display = 'block';
            } else if (paymentMethod === 'cash') {
                cashDiv.style.display = 'block';
            } else if (paymentMethod === 'split') {
                transferDiv.style.display = 'block';
                cashDiv.style.display = 'block';
                // Clear both fields when switching to split payment
                document.getElementById('transfer').value = '';
                document.getElementById('cash').value = '';
            }

            // Recalculate grand total when payment method changes
            calculateTotal();
        }

        function handleSplitPaymentInput(inputElement, inputType) {
            // Only handle auto-calculation for split payment
            const paymentMethod = document.getElementsByName('payment_method')[0].value;
            if (paymentMethod !== 'split') {
                calculateTotal(); // Still calculate for non-split payments
                return;
            }

            // Use subtotal as the base for calculation (this is the grand total we need to reach)
            const subtotal = parseFloat(document.getElementById('subtotal').value.replace(/[^0-9.-]+/g, '')) || 0;
            const inputValue = parseFloat(inputElement.value.replace(/[^0-9.-]+/g, '')) || 0;

            // Calculate remaining amount needed
            const remainingAmount = Math.max(0, subtotal - inputValue);

            // Debug: You can uncomment the line below to debug the calculation
            // console.log('Subtotal:', subtotal, 'Input Value:', inputValue, 'Remaining:', remainingAmount);

            if (inputValue > 0) {
                if (inputType === 'transfer') {
                    // User entered transfer amount, calculate remaining for cash
                    const cashInput = document.getElementById('cash');

                    // Auto-fill cash with remaining amount
                    if (remainingAmount > 0) {
                        // Format the number with thousand separators (commas)
                        let formattedValue = remainingAmount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        cashInput.value = formattedValue;
                    } else if (inputValue >= subtotal) {
                        // If transfer covers the full amount, clear cash
                        cashInput.value = '';
                    }
                } else if (inputType === 'cash') {
                    // User entered cash amount, calculate remaining for transfer
                    const transferInput = document.getElementById('transfer');

                    // Auto-fill transfer with remaining amount
                    if (remainingAmount > 0) {
                        // Format the number with thousand separators (commas)
                        let formattedValue = remainingAmount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        transferInput.value = formattedValue;
                    } else if (inputValue >= subtotal) {
                        // If cash covers the full amount, clear transfer
                        transferInput.value = '';
                    }
                }
            }

            // Always recalculate totals after auto-fill
            calculateTotal();
        }

        // Call the function initially to handle the default state of the form
        togglePaymentFields();

        // Calculate initial grand total
        calculateTotal();

        // Attach event listeners to the input fields to trigger the calculation
        document.getElementById('bayar').addEventListener('input', calculateTotal);
        document.getElementById('transfer').addEventListener('input', function() {
            // Don't call calculateTotal here since handleSplitPaymentInput will call it
        });
        document.getElementById('cash').addEventListener('input', function() {
            // Don't call calculateTotal here since handleSplitPaymentInput will call it
        });

        function submitForms() {
            // Copy values from form1 to form2 hidden inputs
            document.getElementById('transaction_date_form2').value = document.getElementById(
                'kt_td_picker_date_only_input').value;
            document.getElementById('order_number_form2').value = document.getElementById('order_number').value;
            document.getElementById('customer_form2').value = document.getElementById('customer').value;
            document.getElementById('user_id_form2').value = document.getElementById('user_id').value;
            var customerId = document.getElementById('customer_form2').value;

            // Make an AJAX request to check customer status
            $.ajax({
                url: '/check-customer-status', // Update the URL to your Laravel route
                method: 'GET',
                data: {
                    customer_id: customerId
                },
                success: function(response) {
                    if (response.status === 'not_piutang') {
                        document.getElementById('form2').submit();
                    } else {
                        $('#passwordModal').modal('show');
                    }
                },
                error: function(error) {
                    console.error('Error checking customer status:', error);
                }
            });
        }

        function checkMasterUserPassword() {
            var userId = document.getElementById('user_master').value;
            var masterUserPassword = document.getElementById('masterUserPassword').value;

            // Make an AJAX request to validate the master user's password
            $.ajax({
                url: '/validate-master-password', // Update the URL to your Laravel route
                method: 'POST',
                data: {
                    user_id: userId,
                    password: masterUserPassword
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status === 'success') {
                        // Password is correct, submit form2
                        document.getElementById('form2').submit();
                    } else {
                        // Password is incorrect, show an error message
                        alert('Invalid Master User Password. Please try again.');
                    }
                },
                error: function(error) {
                    console.error('Error validating master user password:', error);
                }
            });
        }

        function draftForms() {
            // Copy values from form1 to form2 hidden inputs
            document.getElementById('transaction_date_form2').value = document.getElementById(
                'kt_td_picker_date_only_input').value;
            document.getElementById('order_number_form2').value = document.getElementById('order_number').value;
            document.getElementById('customer_form2').value = document.getElementById('customer').value;
            document.getElementById('user_id_form2').value = document.getElementById('user_id').value;
            document.getElementById('status_form2').value = 'draft';

            // Submit form2
            document.getElementById('form2').submit();
        }
</script>

{{-- Datepicker --}}
<script>
    new tempusDominus.TempusDominus(document.getElementById("kt_td_picker_date_only"), {
            localization: {
                locale: "id",
                startOfTheWeek: 1
            },
            display: {
                viewMode: "calendar",
                components: {
                    decades: true,
                    year: true,
                    month: true,
                    date: true,
                    hours: false,
                    minutes: false,
                    seconds: false
                }
            }
        });
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
                        left: 3,
                        right: 1
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
                            data: function(row) {
                                @php
                                    $isOutOfTown = auth()->user()->warehouse->isOutOfTown ?? false;
                                @endphp
                                @if($isOutOfTown)
                                    return row.product.promo_out_of_town || row.product.promo || '';
                                @else
                                    return row.product.promo || '';
                                @endif
                            }
                        },
                        {
                            data: "quantity"
                        },
                        {
                            data: "product.dus_to_eceran"
                        },
                        {
                            data: "product.pak_to_eceran"
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                @php
                                    $isOutOfTown = auth()->user()->warehouse->isOutOfTown ?? false;
                                @endphp
                                var priceDus = @json($isOutOfTown ? 'price_sell_dus_out_of_town' : 'price_sell_dus');
                                var priceValue = row.product[priceDus];

                                return `
                                <input type="text" name="quantity_dus" class="form-control">
                                <input type="hidden" name="unit_dus" value="${row.product.unit_dus}">
                                <input type="hidden" name="price_dus" value="${priceValue}">
                                <input type="hidden" name="sell_id" value="{{ $sell->id }}">
                                `;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="number" name="diskon_dus" class="form-control">`;
                            }
                        },
                        @if(isset($isOutOfTown) && !$isOutOfTown)
                        {
                            data: "product.price_sell_dus",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        @else
                        {
                            data: "product.price_sell_dus_out_of_town",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        @endif
                        {
                            data: null,
                            render: function(data, type, row) {
                                @php
                                    $isOutOfTown = auth()->user()->warehouse->isOutOfTown ?? false;
                                @endphp
                                var pricePak = @json($isOutOfTown ? 'price_sell_pak_out_of_town' : 'price_sell_pak');
                                var priceValue = row.product[pricePak];

                                return `
                                <input type="text" name="quantity_pak" class="form-control">
                                <input type="hidden" name="unit_pak" value="${row.product.unit_pak}">
                                <input type="hidden" name="price_pak" value="${priceValue}">
                                `;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="number" name="diskon_pak" class="form-control">`;
                            }
                        },
                        @if(isset($isOutOfTown) && !$isOutOfTown)
                        {
                            data: "product.price_sell_pak",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        @else
                        {
                            data: "product.price_sell_pak_out_of_town",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        @endif
                        {
                            data: null,
                            render: function(data, type, row) {
                                @php
                                    $isOutOfTown = auth()->user()->warehouse->isOutOfTown ?? false;
                                @endphp
                                var priceEceran = @json($isOutOfTown ? 'price_sell_eceran_out_of_town' : 'price_sell_eceran');
                                var priceValue = row.product[priceEceran];

                                return `
                                <input type="text" name="quantity_eceran" class="form-control">
                                <input type="hidden" name="unit_eceran" value="${row.product.unit_eceran}">
                                <input type="hidden" name="price_eceran" value="${priceValue}">
                                `;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="number" name="diskon_eceran" class="form-control">`;
                            }
                        },
                        @if(isset($isOutOfTown) && !$isOutOfTown)
                        {
                            data: "product.price_sell_eceran",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        @else
                        {
                            data: "product.price_sell_eceran_out_of_town",
                            render: function(data, type, row) {
                                var formattedPrice = new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR'
                                }).format(data);
                                formattedPrice = formattedPrice.replace(",00", "");
                                return formattedPrice;
                            }
                        },
                        @endif
                        {
                            data: null,
                            className: 'text-center',
                            render: function(data, type, row) {
                                return `<button class="btn btn-primary btn-submit" data-product-id="${row.product.id}"><i class="fas fa-cart-plus"></i></button>`;
                            }
                        }
                    ],
                    "columnDefs": [{
                            target: [0, 1, 8, 11, 14],
                            className: 'min-w-100px',
                        },
                        {
                            target: [6, 7, 9, 10, 12, 13, 15],
                            className: 'min-w-80px',
                        },
                        {
                            targets: -1,
                            className: 'text-center'
                        }
                    ],
                });

                $(table).on('click', '.btn-submit', function() {
                    var rows = $(table).find('tbody tr');
                    var inputRequests = [];

                    rows.each(function() {
                        var rowData = datatable.row($(this)).data();
                        var productId = $(this).find('.btn-submit').data('product-id');
                        var quantityDus = $(this).find('input[name="quantity_dus"]').val();
                        var quantityPak = $(this).find('input[name="quantity_pak"]').val();
                        var quantityEceran = $(this).find('input[name="quantity_eceran"]').val();
                        var diskonDus = $(this).find('input[name="diskon_dus"]').val();
                        var diskonPak = $(this).find('input[name="diskon_pak"]').val();
                        var diskonEceran = $(this).find('input[name="diskon_eceran"]').val();
                        var unitDus = $(this).find('input[name="unit_dus"]').val();
                        var unitPak = $(this).find('input[name="unit_pak"]').val();
                        var unitEceran = $(this).find('input[name="unit_eceran"]').val();
                        var priceDus = $(this).find('input[name="price_dus"]').val();
                        var pricePak = $(this).find('input[name="price_pak"]').val();
                        var priceEceran = $(this).find('input[name="price_eceran"]').val();
                        var sellId = $(this).find('input[name="sell_id"]').val();

                        var inputRequest = {
                            product_id: productId,
                            quantity_dus: quantityDus,
                            quantity_pak: quantityPak,
                            quantity_eceran: quantityEceran,
                            diskon_dus: diskonDus,
                            diskon_pak: diskonPak,
                            diskon_eceran: diskonEceran,
                            unit_dus: unitDus,
                            unit_pak: unitPak,
                            unit_eceran: unitEceran,
                            price_dus: priceDus,
                            price_pak: pricePak,
                            price_eceran: priceEceran,
                            cashier_id: '{{ $sell->cashier_id }}',
                            sell_id: sellId
                        };

                        inputRequests.push(inputRequest);
                    });

                    // Send AJAX request
                    $.ajax({
                        url: '{{ route('penjualan-draft.addCart') }}',
                        type: 'POST',
                        data: {
                            requests: inputRequests
                        },
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            // reload page
                            location.reload();
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText);
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

            return {
                init: function() {
                    table = document.querySelector('#kt_datatable_cart');

                    if (!table) {
                        return;
                    }

                    initDatatable();
                }
            };
        }();

        // On document ready
        KTUtil.onDOMContentLoaded(function() {
            KTDatatablesCart.init();
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
                url: `/penjualan-draft/cart/${cartId}/quantity`,
                method: 'PATCH',
                data: {
                    quantity: newQty,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        input.val(response.data.quantity);
                        row.find('.cart-subtotal').text(new Intl.NumberFormat('id-ID').format(response.data.subtotal));
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

        // Class definition
</script>
@endpush
