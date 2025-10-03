@extends('layouts.dashboard')

@section('title', 'Pengaturan Pembayaran')

@section('content')
<!--begin::Card-->
<div class="card">
    <!--begin::Card header-->
    <div class="pt-6 border-0 card-header">
        <!--begin::Card title-->
        <div class="card-title">
            <h2 class="fw-bold">Pengaturan Pembayaran</h2>
        </div>
        <!--end::Card title-->
        <!--begin::Card toolbar-->
        <div class="card-toolbar">
            <!--begin::Toolbar-->
            <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                @if($paymentSetting)
                <!--begin::Edit setting-->
                <a href="{{ route('payment-settings.edit', $paymentSetting->id) }}" class="btn btn-primary">
                    <i class="ki-duotone ki-pencil fs-2"></i>
                    Edit Pengaturan
                </a>
                <!--end::Edit setting-->
                @else
                <!--begin::Add setting-->
                <a href="{{ route('payment-settings.create') }}" class="btn btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Buat Pengaturan
                </a>
                <!--end::Add setting-->
                @endif
            </div>
            <!--end::Toolbar-->
        </div>
        <!--end::Card toolbar-->
    </div>
    <!--end::Card header-->
    <!--begin::Card body-->
    <div class="py-4 card-body">
        @if($paymentSetting)
        <!--begin::Settings display-->
        <div class="row g-6">
            <!--begin::In Town Settings-->
            <div class="col-md-6">
                <div class="card card-dashed h-xl-100">
                    <div class="card-header">
                        <h3 class="card-title">Pengaturan Dalam Kota</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-5">
                            <label class="fs-6 fw-semibold mb-2">Nomor Rekening BCA</label>
                            <div class="fs-4 fw-bold text-primary">{{ $paymentSetting->in_town_account_number }}</div>
                        </div>
                        <div>
                            <label class="fs-6 fw-semibold mb-2">Nama Rekening</label>
                            <div class="fs-4 fw-bold text-primary">{{ $paymentSetting->in_town_account_name }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::In Town Settings-->
            <!--begin::Out of Town Settings-->
            <div class="col-md-6">
                <div class="card card-dashed h-xl-100">
                    <div class="card-header">
                        <h3 class="card-title">Pengaturan Luar Kota</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-5">
                            <label class="fs-6 fw-semibold mb-2">Nomor Rekening BCA</label>
                            <div class="fs-4 fw-bold text-success">{{ $paymentSetting->out_of_town_account_number }}</div>
                        </div>
                        <div>
                            <label class="fs-6 fw-semibold mb-2">Nama Rekening</label>
                            <div class="fs-4 fw-bold text-success">{{ $paymentSetting->out_of_town_account_name }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Out of Town Settings-->
        </div>
        <!--end::Settings display-->
        @else
        <!--begin::No settings-->
        <div class="text-center py-10">
            <div class="text-gray-500 fs-6 mb-5">
                Belum ada pengaturan pembayaran yang dibuat.
            </div>
            <a href="{{ route('payment-settings.create') }}" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Buat Pengaturan Pembayaran
            </a>
        </div>
        <!--end::No settings-->
        @endif
    </div>
    <!--end::Card body-->
</div>
<!--end::Card-->
@endsection
