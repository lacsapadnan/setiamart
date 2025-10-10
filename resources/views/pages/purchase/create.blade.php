@extends('layouts.dashboard')

@section('title', 'Pembelian')
@section('menu-title', 'Pembelian Barang')

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
        transform: rotateX(180deg) !important;
    }

    .dataTables_scrollBody::-webkit-scrollbar {
        height: 16px !important;
        ;
    }

    .dataTables_scrollBody table {
        transform: rotateX(180deg) !important;
        ;
    }
</style>
@endpush

@section('content')
@include('components.alert')
<div class="mt-5 border-0 card card-p-0 card-flush">
    <div class="mt-3">
        <form id="form1">
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3 row align-items-center">
                        <label for="inputEmail3" class="col-form-label">Tanggal Terima</label>
                        <div class="input-group" id="kt_td_picker_date_only" data-td-target-input="nearest"
                            data-td-target-toggle="nearest">
                            <input id="kt_td_picker_date_only_input" type="text" class="form-control"
                                data-td-target="#kt_td_picker_date_only" name="reciept_date" value="{{ date('Y-m-d') }}"
                                disabled />
                            <span class="input-group-text" data-td-target="#kt_td_picker_date_only"
                                data-td-toggle="datetimepicker">
                                <i class="ki-duotone ki-calendar fs-2"><span class="path1"></span><span
                                        class="path2"></span></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3 row align-items-center me-1">
                        <label for="inputEmail3" class="col-form-label">No. Order</label>
                        <input id="order_number" type="text" name="order_number" class="form-control"
                            value="{{ $orderNumber }}" readonly />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3 row align-items-center">
                        <label for="inputEmail3" class="col-form-label">No. Faktur Supplier</label>
                        <input id="invoice" type="text" name="invoice" class="form-control"
                            placeholder="Masukan nomor faktur" value="{{ old('invoice') }}" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3 row align-items-center">
                        <label for="inputEmail3" class="col-form-label">Supplier</label>
                        <select id="supplier_id" class="form-select" name="supplier_id" data-control="select2"
                            data-placeholder="Pilih Supplier" data-allow-clear="true">
                            <option value="{{ old('supplier_id') }}"></option>
                            @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
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
                        <tr class="text-gray-400 text-start fw-bold fs-7 text-uppercase">
                            <th class="min-w-100px">Nama Barang</th>
                            <th>Stok</th>
                            <th>Jml Per Dus</th>
                            <th>Jml Per Pak</th>
                            <th>Jml Beli Dus</th>
                            <th>Harga Dus</th>
                            <th>Diskon Fix</th>
                            <th>Diskon Persen</th>
                            <th>Jml Beli Pak</th>
                            <th>Harga Pak</th>
                            <th>Diskon Fix</th>
                            <th>Diskon Persen</th>
                            <th>Jml Beli Eceran</th>
                            <th>Harga Eceran</th>
                            <th>Diskon Fix</th>
                            <th>Diskon Persen</th>
                            <th class="min-w-100px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 fw-semibold">
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
                            <tr class="text-gray-400 text-start fw-bold fs-7 text-uppercase">
                                <th>No</th>
                                <th>Kelompok</th>
                                <th class="min-w-100px">Nama Barang</th>
                                <th>Jml Beli</th>
                                <th>Hrg Satuan</th>
                                <th>Unit</th>
                                <th>Subtotal</th>
                                <th>Hapus</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-900 fw-semibold">
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
                                <td>{{ number_format($cart->price_unit) }}</td>
                                <td>{{ $cart->unit->name }}</td>
                                <td class="cart-subtotal">
                                    {{ number_format($cart->total_price) }}
                                </td>
                                <td>
                                    <form action="{{ route('pembelian.destroyCart', $cart->id) }}" method="POST">
                                        @csrf
                                        @method('delete')
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
            <form id="form2" action="{{ route('pembelian.store') }}" method="post">
                @csrf
                <input type="hidden" name="reciept_date" id="reciept_date_form2">
                <input type="hidden" name="invoice" id="invoice_form2">
                <input type="hidden" name="supplier_id" id="supplier_id_form2">
                <input type="hidden" name="order_number" id="order_number_form2">
                <div class="row">
                    <div class="col">
                        <div class="mb-1">
                            <label for="subtotal" class="col-form-label">Subtotal</label>
                            <input type="text" name="subtotal" class="form-control" id="subtotal"
                                value="{{ number_format($subtotal, 0, ',', '.') }}" readonly />
                        </div>
                    </div>
                    <div class="col">
                        <div class="mb-1">
                            <label for="ppn" class="col-form-label">PPN</label>
                            <input type="text" name="tax" class="form-control" id="ppn" value="0"
                                oninput="calculateTotal()" />
                        </div>
                    </div>
                </div>
                <div class="mb-5">
                    <div class="col">
                        <div class="mb-1">
                            <label for="potongan" class="col-form-label">Potongan</label>
                            <input type="text" name="potongan" class="form-control" id="potongan"
                                oninput="formatNumber(this); calculateTotal()" value="0" />
                        </div>
                    </div>
                </div>

                <!-- Enhanced payment method section -->
                <div class="mb-1" id="bayarDiv">
                    <label for="bayar" class="col-form-label">Bayar</label>
                    <input type="text" name="pay" class="form-control" id="bayar"
                        oninput="formatNumber(this); calculateTotal()" value="0" />
                </div>

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
                    <small class="form-text text-muted">Sistem akan otomatis menghitung sisa untuk transfer</small>
                </div>

                <div class="row">
                    <div class="col">
                        <div class="mb-1">
                            <label for="grandTotal" class="col-form-label">Grand Total</label>
                            <input type="text" name="grand_total" class="form-control" id="grandTotal" 
                                value="" readonly />
                        </div>
                    </div>
                    <div class="col">
                        <div class="mb-1">
                            <label for="kembali" class="col-form-label">Kembali</label>
                            <input type="text" name="change" class="form-control" id="kembali" readonly />
                        </div>
                    </div>
                </div>

                <div class="mb-1" id="sisaDiv" style="display: none;">
                    <label for="sisa" class="col-form-label">Sisa</label>
                    <input type="text" name="remaint" class="form-control" id="sisa" readonly />
                </div>

                <div class="mt-5 row">
                    <div class="mb-1">
                        <label for="inputEmail3" class="col-form-label">Metode Bayar</label>
                        <select name="payment_method" class="form-select" aria-label="Select example"
                            onchange="togglePaymentFields()">
                            <option value="">Pilih Pembayaran</option>
                            <option value="transfer">Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="split">Split Payment</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label for="inputEmail3" class="col-form-label">Keterangan</label>
                        <input name="description" type="text" class="form-control" />
                    </div>
                </div>
                <div class="mt-5 row">
                    <button type="submit" onclick="submitForms()" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@includeIf('pages.purchase.modal')
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
        // Parse Indonesian number format (dots as thousands separators)
        var subtotalText = document.getElementById('subtotal').value.replace(/[.,]/g, '');
        var subtotal = parseFloat(subtotalText) || 0;
        
        // If subtotal is 0, try to calculate from cart items
        if (subtotal === 0 || isNaN(subtotal)) {
            let calculatedSubtotal = 0;
            $('tr[data-cart-id]').each(function() {
                const subtotalText = $(this).find('.cart-subtotal').text().replace(/[.,]/g, '');
                calculatedSubtotal += parseInt(subtotalText) || 0;
            });
            subtotal = calculatedSubtotal;
            // Update the subtotal field with proper formatting
            $('#subtotal').val(new Intl.NumberFormat('id-ID').format(subtotal));
        }
        
        var tax = parseFloat(document.getElementById('ppn').value.replace(/[.,]/g, '')) || 0;
        var potongan = parseFloat(document.getElementById('potongan').value.replace(/[.,]/g, '')) || 0;

        var paymentMethod = document.getElementsByName('payment_method')[0].value;
        var transfer = parseFloat(document.getElementById('transfer').value.replace(/[^0-9.-]+/g, '')) || 0;
        var cash = parseFloat(document.getElementById('cash').value.replace(/[^0-9.-]+/g, '')) || 0;
        var bayar = parseFloat(document.getElementById('bayar').value.replace(/[^0-9.-]+/g, '')) || 0;

        // If tax, bayar, and potongan are not provided, set them to 0
        tax = isNaN(tax) ? 0 : tax;
        bayar = isNaN(bayar) ? 0 : bayar;
        potongan = isNaN(potongan) ? 0 : potongan;

        var grandTotal = subtotal + (subtotal * (tax / 100)) - potongan;

        var kembali = calculateKembali(paymentMethod, grandTotal, transfer, cash, bayar);
        var sisa = calculateSisa(paymentMethod, grandTotal, transfer, cash, bayar);

        document.getElementById('ppn').value = tax.toFixed(0);
        document.getElementById('grandTotal').value = new Intl.NumberFormat('id-ID').format(grandTotal);
        document.getElementById('kembali').value = new Intl.NumberFormat('id-ID').format(kembali);
        document.getElementById('potongan').value = potongan.toFixed(0);

        // Show/hide and update sisa field
        var sisaDiv = document.getElementById('sisaDiv');
        if (sisa > 0) {
            sisaDiv.style.display = 'block';
            document.getElementById('sisa').value = new Intl.NumberFormat('id-ID').format(sisa);
        } else {
            sisaDiv.style.display = 'none';
        }
    }

    function calculateKembali(paymentMethod, grandTotal, transfer, cash, bayar) {
        if (paymentMethod === 'transfer') {
            return Math.max(transfer - grandTotal, 0);
        } else if (paymentMethod === 'cash') {
            return Math.max(cash - grandTotal, 0);
        } else if (paymentMethod === 'split') {
            return Math.max(transfer + cash - grandTotal, 0);
        } else {
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
        } else {
            // Show default bayar field if no specific payment method is selected
            bayarDiv.style.display = 'block';
        }

        // Recalculate total when payment method changes
        calculateTotal();
    }

    function handleSplitPaymentInput(inputElement, inputType) {
        // Only handle auto-calculation for split payment
        const paymentMethod = document.getElementsByName('payment_method')[0].value;
        if (paymentMethod !== 'split') {
            calculateTotal(); // Still calculate for non-split payments
            return;
        }

        // Get the grand total (including tax and discount)
        var subtotal = parseFloat(document.getElementById('subtotal').value.replace(/[^0-9.-]+/g, '')) || 0;
        var tax = parseFloat(document.getElementById('ppn').value.replace(/[^0-9.-]+/g, '')) || 0;
        var potongan = parseFloat(document.getElementById('potongan').value.replace(/[^0-9.-]+/g, '')) || 0;
        const grandTotal = subtotal + (subtotal * (tax / 100)) - potongan;

        const inputValue = parseFloat(inputElement.value.replace(/[^0-9.-]+/g, '')) || 0;

        // Calculate remaining amount needed
        const remainingAmount = Math.max(0, grandTotal - inputValue);

        if (inputValue > 0) {
            if (inputType === 'transfer') {
                // User entered transfer amount, calculate remaining for cash
                const cashInput = document.getElementById('cash');

                // Auto-fill cash with remaining amount
                if (remainingAmount > 0) {
                    let formattedValue = remainingAmount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    cashInput.value = formattedValue;
                } else if (inputValue >= grandTotal) {
                    // If transfer covers the full amount, clear cash
                    cashInput.value = '';
                }
            } else if (inputType === 'cash') {
                // User entered cash amount, calculate remaining for transfer
                const transferInput = document.getElementById('transfer');

                // Auto-fill transfer with remaining amount
                if (remainingAmount > 0) {
                    let formattedValue = remainingAmount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    transferInput.value = formattedValue;
                } else if (inputValue >= grandTotal) {
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

    // Calculate initial total
    calculateTotal();

    // Attach event listeners to the input fields to trigger the calculation
    document.getElementById('bayar').addEventListener('input', function() {
        formatNumber(this);
        calculateTotal();
    });

    function validateAndSetPaymentMethod() {
        const paymentMethodSelect = document.getElementsByName('payment_method')[0];
        const cash = parseFloat(document.getElementById('cash').value.replace(/[^0-9.-]+/g, '')) || 0;
        const transfer = parseFloat(document.getElementById('transfer').value.replace(/[^0-9.-]+/g, '')) || 0;
        const bayar = parseFloat(document.getElementById('bayar').value.replace(/[^0-9.-]+/g, '')) || 0;

        // If payment method is not selected but we have payment amounts, set it automatically
        if (!paymentMethodSelect.value) {
            if (cash > 0 && transfer > 0) {
                paymentMethodSelect.value = 'split';
            } else if (cash > 0) {
                paymentMethodSelect.value = 'cash';
            } else if (transfer > 0) {
                paymentMethodSelect.value = 'transfer';
            } else if (bayar > 0) {
                // If using the general bayar field, we need to determine from visible fields
                const transferDiv = document.getElementById('transferDiv');
                const cashDiv = document.getElementById('cashDiv');

                if (transferDiv.style.display !== 'none') {
                    paymentMethodSelect.value = 'transfer';
                } else if (cashDiv.style.display !== 'none') {
                    paymentMethodSelect.value = 'cash';
                }
            }
        }

        return true;
    }

    function submitForms() {
        // Validate and set payment method before submission
        validateAndSetPaymentMethod();

        // Get the values from form1
        var recieptDate = $('#kt_td_picker_date_only_input').val();
        var invoice = $('#invoice').val();
        var supplierId = $('#supplier_id').val();

        // Assign the values to the hidden input fields in form2
        $('#reciept_date_form2').val(recieptDate);
        $('#invoice_form2').val(invoice);
        $('#supplier_id_form2').val(supplierId);
        document.getElementById('order_number_form2').value = document.getElementById('order_number').value;

        // Only submit form2, not both forms
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
                        left: 2,
                    },
                    "ajax": {
                        url: '{{ route('api.data-all') }}',
                        type: 'GET',
                        data: function(d) {
                            d.searchQuery = $('#searchInput').val();
                        }
                    },
                    "columns": [{
                            data: "product.name"
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
                                return `
                                    <input type="text" name="quantity_dus" class="form-control">
                                    <input type="hidden" name="unit_dus" value="${row.product.unit_dus}">
                                `;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="price_dus" class="form-control price-input">`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="discount_fix_dus" class="form-control price-input">`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="discount_percent_dus" class="form-control price-input">`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `
                                    <input type="text" name="quantity_pak" class="form-control">
                                    <input type="hidden" name="unit_pak" value="${row.product.unit_pak}">
                                `;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="price_pak" class="form-control price-input">`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="discount_fix_pak" class="form-control price-input">`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="discount_percent_pak" class="form-control price-input">`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `
                                <input type="text" name="quantity_eceran" class="form-control">
                                <input type="hidden" name="unit_eceran" value="${row.product.unit_eceran}">
                            `;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="price_eceran" class="form-control price-input">`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="discount_fix_eceran" class="form-control price-input">`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `<input type="text" name="discount_percent_eceran" class="form-control price-input">`;
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
                            target: 4,
                            className: 'min-w-80px',
                        },
                        {
                            target: 5,
                            className: 'min-w-150px',
                        },
                        {
                            target: 6,
                            className: 'min-w-100px',
                        },
                        {
                            target: 7,
                            className: 'min-w-50px',
                        },
                        {
                            target: 8,
                            className: 'min-w-100px',
                        },
                        {
                            target: 9,
                            className: 'min-w-150px',
                        },
                        {
                            target: 10,
                            className: 'min-w-100px',
                        },
                        {
                            target: 12,
                            className: 'min-w-80px',
                        },
                        {
                            target: 13,
                            className: 'min-w-150px',
                        },
                        {
                            target: 14,
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
                        var unitDus = $(this).find('input[name="unit_dus"]').val();
                        var unitPak = $(this).find('input[name="unit_pak"]').val();
                        var unitEceran = $(this).find('input[name="unit_eceran"]').val();
                        var priceDus = $(this).find('input[name="price_dus"]').val();
                        var pricePak = $(this).find('input[name="price_pak"]').val();
                        var priceEceran = $(this).find('input[name="price_eceran"]').val();
                        var diskonFixDus = $(this).find('input[name="discount_fix_dus"]').val();
                        var diskonFixPak = $(this).find('input[name="discount_fix_pak"]').val();
                        var diskonFixEceran = $(this).find('input[name="discount_fix_eceran"]')
                            .val();
                        var diskonPersenDus = $(this).find('input[name="discount_percent_dus"]')
                            .val();
                        var diskonPersenPak = $(this).find('input[name="discount_percent_pak"]')
                            .val();
                        var diskonPersenEceran = $(this).find(
                            'input[name="discount_percent_eceran"]').val();

                        var inputRequest = {
                            product_id: productId,
                            quantity_dus: quantityDus,
                            quantity_pak: quantityPak,
                            quantity_eceran: quantityEceran,
                            unit_dus: unitDus,
                            unit_pak: unitPak,
                            unit_eceran: unitEceran,
                            price_dus: priceDus,
                            price_pak: pricePak,
                            price_eceran: priceEceran,
                            discount_fix_dus: diskonFixDus,
                            discount_fix_pak: diskonFixPak,
                            discount_fix_eceran: diskonFixEceran,
                            discount_percent_dus: diskonPersenDus,
                            discount_percent_pak: diskonPersenPak,
                            discount_percent_eceran: diskonPersenEceran,
                        };

                        inputRequests.push(inputRequest);
                    });

                    // Send AJAX request
                    $.ajax({
                        url: '{{ route('pembelian.addCart') }}',
                        type: 'POST',
                        data: {
                            requests: inputRequests // Pass the array of objects as "requests"
                        },
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            location.reload();
                        },
                        error: function(xhr, status, error) {
                            // Handle error response
                            console.log(xhr.responseText);
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
                    $(table).on('keydown',
                        'input[name^="quantity_"], input[name^="diskon_"], input[name^="price_"]',
                        function(event) {
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
            // Ensure totals are correct on initial load
            setTimeout(function() {
                calculateTotal();
            }, 100); // Small delay to ensure DOM is fully ready
        });

        // Class definition
</script>

{{-- Modal --}}
<script>
    function closeModal() {
            const modal = new bootstrap.Modal(document.getElementById('kt_modal_1'));
            modal.hide();

            // Remove the .modal-open class from the body element
            document.body.classList.remove('modal-open');

            // Remove the modal backdrop element
            const modalBackdrop = document.querySelector('.modal-backdrop');
            if (modalBackdrop) {
                modalBackdrop.remove();
            }
        }

        const buttons = document.getElementsByClassName('btn-open-modal');
        Array.from(buttons).forEach(button => {
            button.addEventListener('click', openModal);
        });

        const modalCloseButton = document.querySelector('.modal .close');
        if (modalCloseButton) {
            modalCloseButton.addEventListener('click', closeModal);
        }

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
                url: `/pembelian/cart/${cartId}/quantity`,
                method: 'PATCH',
                data: {
                    quantity: newQty,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        input.val(response.data.quantity);
                        row.find('.cart-subtotal').text(new Intl.NumberFormat('id-ID').format(response.data.subtotal));
                        
                        // Recalculate subtotal from all cart items
                        let subtotal = 0;
                        $('tr[data-cart-id]').each(function() {
                            const subtotalText = $(this).find('.cart-subtotal').text().replace(/[.,]/g, '');
                            subtotal += parseInt(subtotalText) || 0;
                        });
                        
                        // Update subtotal field
                        $('#subtotal').val(new Intl.NumberFormat('id-ID').format(subtotal));
                        
                        // Call calculateTotal to update grand total with tax and discount
                        calculateTotal();
                        
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