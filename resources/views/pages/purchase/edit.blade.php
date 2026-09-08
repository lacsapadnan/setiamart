@extends('layouts.dashboard')

@section('title', 'Edit Pembelian')
@section('menu-title', 'Edit Pembelian')

@section('content')
@include('components.alert')
<div class="mt-5 border-0 card card-p-5 card-flush">
    <div class="card-body">
        <form action="{{ route('pembelian.update', $purchase->id) }}" method="POST">
            @method('PUT')
            @csrf
            <div class="row">
                <div class="col-md-5">
                    <div class="mb-3 row align-items-center">
                        <label for="inputEmail3" class="col-form-label">No. Faktur Supplier</label>
                        <input id="invoice" type="text" name="invoice" class="form-control"
                            placeholder="Masukan nomor faktur" value="{{ $purchase->invoice }}" />
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="mb-3 row align-items-center">
                        <label for="inputEmail3" class="col-form-label">Supplier</label>
                        <select id="supplier_id" class="form-select" name="supplier_id" data-control="select2"
                            data-placeholder="Pilih Supplier" data-allow-clear="true">
                            <option></option>
                            @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ $supplier->id == $purchase->supplier_id ? 'selected'
                                : '' }}>
                                {{ $supplier->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="inputEmail3" class="col-form-label">PPN</label>
                    <input type="text" name="tax" class="form-control" value="{{ $purchase->tax }}" />
                </div>
            </div>
            <div class="row">
                <h2 class="mt-10">List Produk</h2>
            </div>

            <!-- Optimized table layout for better performance -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Produk</th>
                            <th style="width: 120px;">Unit</th>
                            <th style="width: 80px;">Jumlah</th>
                            <th style="width: 100px;">Diskon Fix</th>
                            <th style="width: 100px;">Diskon %</th>
                            <th style="width: 120px;">Harga</th>
                            <th style="width: 120px;">Harga Dus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase->details as $key => $purchase_detail)
                        <tr>
                            <td>
                                <select class="form-select product-select" name="product_id[]" data-control="select2"
                                    data-placeholder="Pilih Produk" data-allow-clear="true" data-row="{{ $key }}">
                                    <option></option>
                                    @if ($purchase_detail->product)
                                    <option value="{{ $purchase_detail->product->id }}"
                                        data-unit-dus="{{ $purchase_detail->product->unit_dus }}"
                                        data-unit-pak="{{ $purchase_detail->product->unit_pak }}"
                                        data-unit-eceran="{{ $purchase_detail->product->unit_eceran }}" selected>
                                        {{ $purchase_detail->product->name }}
                                    </option>
                                    @endif
                                </select>
                            </td>
                            <td>
                                <select class="form-select unit-select" name="unit_id[]" data-control="select2"
                                    data-placeholder="Pilih Unit" data-allow-clear="true" data-row="{{ $key }}">
                                    <option></option>
                                    @foreach ($unitOptions as $unitId => $unitName)
                                    <option value="{{ $unitId }}" {{ $unitId==$purchase_detail->unit_id ? 'selected' :
                                        '' }}>
                                        {{ $unitName }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" name="qty[]" class="form-control" placeholder="Jumlah"
                                    value="{{ $purchase_detail->quantity }}" min="1" step="1" />
                            </td>
                            <td>
                                <input type="number" name="discount_fix[]" class="form-control" placeholder="0"
                                    value="{{ $purchase_detail->discount_fix }}" min="0" step="1" />
                            </td>
                            <td>
                                <input type="number" name="discount_percent[]" class="form-control" placeholder="0"
                                    value="{{ $purchase_detail->discount_percent }}" min="0" max="100" step="0.01" />
                            </td>
                            <td>
                                <input type="text" name="price_unit[]" class="form-control price-input" placeholder="0"
                                    value="{{ number_format($purchase_detail->price_unit, 0, ',', '.') }}" />
                            </td>
                            <td>
                                <input type="text" name="price_sell_dus[]" class="form-control price-input"
                                    placeholder="0"
                                    value="{{ number_format($purchase_detail->product?->price_sell_dus ?? 0, 0, ',', '.') }}" />
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Update Pembelian</button>
        </form>
    </div>
</div>
@endsection
@push('addon-script')
<script type="text/javascript">
    const unitOptions = @json($unitOptions);

    $(document).ready(function() {
        initializeSelect2();
        setupPriceFormatting();

        $('.product-select').on('change', function() {
            const $select = $(this);
            const row = $select.data('row');
            const selectedData = getSelectedProductData($select);
            const unitSelect = $(`.unit-select[data-row="${row}"]`);

            if (!selectedData.id) {
                unitSelect.val(null).trigger('change');
                unitSelect.prop('disabled', true);
                return;
            }

            unitSelect.prop('disabled', false);
            const currentUnit = unitSelect.val();
            unitSelect.empty();
            unitSelect.append(new Option('', '', false, false));

            Object.entries(unitOptions).forEach(([unitId, unitName]) => {
                const numericUnitId = Number(unitId);
                if ([selectedData.unitDus, selectedData.unitPak, selectedData.unitEceran].includes(numericUnitId)) {
                    unitSelect.append(new Option(unitName, numericUnitId, false, numericUnitId == currentUnit));
                }
            });

            unitSelect.trigger('change');
        });

        $('.product-select').each(function() {
            $(this).trigger('change');
        });
    });

    function getSelectedProductData($select) {
        const select2Data = $select.select2('data')[0] || {};
        const selectedOption = $select.find('option:selected');

        return {
            id: $select.val(),
            unitDus: select2Data.unit_dus ?? selectedOption.data('unit-dus'),
            unitPak: select2Data.unit_pak ?? selectedOption.data('unit-pak'),
            unitEceran: select2Data.unit_eceran ?? selectedOption.data('unit-eceran'),
        };
    }

    function initializeSelect2() {
        $('.unit-select').select2({
            width: '100%',
            placeholder: function() {
                return $(this).data('placeholder');
            },
            allowClear: true,
        });

        $('.product-select').select2({
            width: '100%',
            placeholder: function() {
                return $(this).data('placeholder');
            },
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: '{{ route('api.produk-select2') }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    return data;
                },
                cache: true
            }
        });
    }

    function setupPriceFormatting() {
        // Use event delegation for better performance
        $(document).on('input', '.price-input', debounce(function(e) {
            formatPriceInput(this);
        }, 300));

        // Format existing values on page load
        $('.price-input').each(function() {
            formatPriceInput(this);
        });
    }

    function formatPriceInput(element) {
        const value = element.value.replace(/[^\d]/g, '');
        if (value) {
            element.value = formatRupiah(value);
        }
    }

    function formatRupiah(angka, prefix) {
        const number_string = angka.replace(/[^,\d]/g, '').toString();
        const split = number_string.split(',');
        const sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        const ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            const separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
        return prefix === undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
    }

    // Debounce function to limit how often a function can fire
    function debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
</script>
@endpush
