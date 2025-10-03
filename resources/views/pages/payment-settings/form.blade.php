@extends('layouts.dashboard')

@section('title', isset($paymentSetting) ? 'Edit Pengaturan Pembayaran' : 'Tambah Pengaturan Pembayaran')

@section('content')
<!--begin::Card-->
<div class="card">
    <!--begin::Card header-->
    <div class="pt-6 border-0 card-header">
        <!--begin::Card title-->
        <div class="card-title">
            <h2 class="fw-bold">{{ isset($paymentSetting) ? 'Edit Pengaturan Pembayaran' : 'Tambah Pengaturan Pembayaran' }}</h2>
        </div>
        <!--end::Card title-->
    </div>
    <!--end::Card header-->
    <!--begin::Card body-->
    <div class="py-4 card-body">
        <!--begin::Alert-->
        <div class="p-5 mb-10 alert alert-info d-flex align-items-center">
            <!--begin::Icon-->
            <span class="svg-icon svg-icon-2hx svg-icon-info me-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path opacity="0.3"
                        d="M20.5543 4.37824L12.1798 2.02473C12.0626 1.99176 11.9376 1.99176 11.8203 2.02473L3.44572 4.37824C3.18118 4.45258 3 4.6807 3 4.93945V13.569C3 14.6914 3.48613 15.8404 4.4407 16.8889C5.26474 17.8069 6.33444 18.6696 7.51648 19.477C8.8037 20.3602 10.1799 21.1849 11.5164 21.7864C11.8246 21.9287 12.1754 21.9287 12.4837 21.7864C13.8201 21.1849 15.1963 20.3602 16.4835 19.477C17.6656 18.6696 18.7353 17.8069 19.5593 16.8889C20.5139 15.8404 21 14.6914 21 13.569V4.93945C21 4.6807 20.8188 4.45258 20.5543 4.37824Z"
                        fill="currentColor" />
                    <path
                        d="M10.5606 11.3042L9.57283 10.3018C9.28174 10.0065 8.80522 10.0065 8.51412 10.3018C8.22897 10.5912 8.22897 11.0559 8.51412 11.3452L10.4182 13.2773C10.8055 13.6747 11.451 13.6747 11.8383 13.2773L15.4859 9.58051C15.771 9.29117 15.771 8.82648 15.4859 8.53714C15.1948 8.24176 14.7183 8.24176 14.4272 8.53714L11.7002 11.3042C11.3869 11.6221 10.874 11.6221 10.5606 11.3042Z"
                        fill="currentColor" />
                </svg>
            </span>
            <!--end::Icon-->
            <!--begin::Wrapper-->
            <div class="d-flex flex-column">
                <!--begin::Title-->
                <h4 class="mb-1 text-info">Informasi Pengaturan Pembayaran</h4>
                <!--end::Title-->
                <!--begin::Content-->
                <span>Pengaturan rekening ini akan digunakan pada struk pembayaran:</span>
                <ul class="mb-0">
                    <li>Dalam Kota: Digunakan untuk gudang dalam kota</li>
                    <li>Luar Kota: Digunakan untuk gudang luar kota</li>
                    <li>Pengaturan ini akan tercatat dalam log aktivitas</li>
                </ul>
                <!--end::Content-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Alert-->

        <!--begin::Form-->
        <form id="payment_setting_form" class="form"
            action="{{ isset($paymentSetting) ? route('payment-settings.update', $paymentSetting) : route('payment-settings.store') }}"
            method="POST">
            @csrf
            @if(isset($paymentSetting))
            @method('PUT')
            @endif

            <!--begin::In Town Settings-->
            <div class="mb-10">
                <h3 class="mb-5 text-primary">Pengaturan Dalam Kota</h3>
                <div class="row">
                    <div class="col-md-6 mb-7 fv-row">
                        <label class="mb-2 required fw-semibold fs-6">Nomor Rekening BCA</label>
                        <input type="text" name="in_town_account_number" class="form-control form-control-solid"
                            value="{{ isset($paymentSetting) ? $paymentSetting->in_town_account_number : old('in_town_account_number') }}"
                            placeholder="Contoh: 7285132827" required />
                        @error('in_town_account_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-7 fv-row">
                        <label class="mb-2 required fw-semibold fs-6">Nama Rekening</label>
                        <input type="text" name="in_town_account_name" class="form-control form-control-solid"
                            value="{{ isset($paymentSetting) ? $paymentSetting->in_town_account_name : old('in_town_account_name') }}"
                            placeholder="Contoh: Andreas Jati Perkasa" required />
                        @error('in_town_account_name')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            <!--end::In Town Settings-->

            <!--begin::Out of Town Settings-->
            <div class="mb-10">
                <h3 class="mb-5 text-success">Pengaturan Luar Kota</h3>
                <div class="row">
                    <div class="col-md-6 mb-7 fv-row">
                        <label class="mb-2 required fw-semibold fs-6">Nomor Rekening BCA</label>
                        <input type="text" name="out_of_town_account_number" class="form-control form-control-solid"
                            value="{{ isset($paymentSetting) ? $paymentSetting->out_of_town_account_number : old('out_of_town_account_number') }}"
                            placeholder="Contoh: 8421789002" required />
                        @error('out_of_town_account_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-7 fv-row">
                        <label class="mb-2 required fw-semibold fs-6">Nama Rekening</label>
                        <input type="text" name="out_of_town_account_name" class="form-control form-control-solid"
                            value="{{ isset($paymentSetting) ? $paymentSetting->out_of_town_account_name : old('out_of_town_account_name') }}"
                            placeholder="Contoh: Rizky Setiawan Wijaya" required />
                        @error('out_of_town_account_name')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            <!--end::Out of Town Settings-->

            <!--begin::Actions-->
            <div class="pt-10 text-center">
                <a href="{{ route('payment-settings.index') }}" class="btn btn-light me-3">Kembali</a>
                <button type="submit" class="btn btn-primary" id="submit_btn">
                    <span class="indicator-label">
                        {{ isset($paymentSetting) ? 'Update' : 'Simpan' }}
                    </span>
                    <span class="indicator-progress">
                        Mohon tunggu... <span class="align-middle spinner-border spinner-border-sm ms-2"></span>
                    </span>
                </button>
            </div>
            <!--end::Actions-->
        </form>
        <!--end::Form-->
    </div>
    <!--end::Card body-->
</div>
<!--end::Card-->
@endsection

@push('addon-script')
<script>
    "use strict";

    // Class definition
    var KTPaymentSettingForm = function () {
        // Elements
        var form;
        var submitButton;
        var validator;

        // Handle form
        var handleForm = function(e) {
            validator = FormValidation.formValidation(
                form,
                {
                    fields: {
                        'in_town_account_number': {
                            validators: {
                                notEmpty: {
                                    message: 'Nomor rekening dalam kota harus diisi'
                                },
                                regexp: {
                                    regexp: /^[0-9]+$/,
                                    message: 'Nomor rekening hanya boleh berisi angka'
                                },
                                stringLength: {
                                    min: 8,
                                    max: 20,
                                    message: 'Nomor rekening harus antara 8-20 digit'
                                }
                            }
                        },
                        'in_town_account_name': {
                            validators: {
                                notEmpty: {
                                    message: 'Nama rekening dalam kota harus diisi'
                                },
                                stringLength: {
                                    min: 3,
                                    max: 255,
                                    message: 'Nama rekening harus antara 3-255 karakter'
                                }
                            }
                        },
                        'out_of_town_account_number': {
                            validators: {
                                notEmpty: {
                                    message: 'Nomor rekening luar kota harus diisi'
                                },
                                regexp: {
                                    regexp: /^[0-9]+$/,
                                    message: 'Nomor rekening hanya boleh berisi angka'
                                },
                                stringLength: {
                                    min: 8,
                                    max: 20,
                                    message: 'Nomor rekening harus antara 8-20 digit'
                                }
                            }
                        },
                        'out_of_town_account_name': {
                            validators: {
                                notEmpty: {
                                    message: 'Nama rekening luar kota harus diisi'
                                },
                                stringLength: {
                                    min: 3,
                                    max: 255,
                                    message: 'Nama rekening harus antara 3-255 karakter'
                                }
                            }
                        }
                    },
                    plugins: {
                        trigger: new FormValidation.plugins.Trigger(),
                        bootstrap: new FormValidation.plugins.Bootstrap5({
                            rowSelector: '.fv-row',
                            eleInvalidClass: '',
                            eleValidClass: ''
                        })
                    }
                }
            );

            // Handle form submit
            submitButton.addEventListener('click', function (e) {
                e.preventDefault();

                validator.validate().then(function (status) {
                    if (status == 'Valid') {
                        submitButton.setAttribute('data-kt-indicator', 'on');
                        submitButton.disabled = true;
                        form.submit();
                    }
                });
            });
        }

        // Public functions
        return {
            // Initialization
            init: function () {
                form = document.querySelector('#payment_setting_form');
                submitButton = document.querySelector('#submit_btn');
                handleForm();
            }
        };
    }();

    // On document ready
    KTUtil.onDOMContentLoaded(function () {
        KTPaymentSettingForm.init();
    });
</script>
@endpush
