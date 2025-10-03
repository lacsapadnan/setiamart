@extends('layouts.dashboard')

@section('title', 'Edit Bank')
@section('menu-title', 'Edit Bank')

@push('addon-style')
    <link href="assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
@endpush

@section('content')
    <form action="{{ route('bank.update', $bank->id) }}" method="post" class="mt-5">
        @csrf
        @method('PUT')
        <div class="mb-10">
            <label class="form-label" for="bank_name">Nama Bank</label>
            <input name="bank_name" type="text" class="form-control" placeholder="Masukan nama bank" value="{{ $bank->bank_name }}" />
        </div>
        <div class="mb-10">
            <label class="form-label" for="account_number">Nomor Rekening</label>
            <input name="account_number" type="text" class="form-control" placeholder="Masukan nomor rekening" value="{{ $bank->account_number }}" />
        </div>
        <div class="mb-10">
            <label class="form-label" for="account_name">Nama Rekening</label>
            <input name="account_name" type="text" class="form-control" placeholder="Masukan nama rekening" value="{{ $bank->account_name }}" />
        </div>
        <button type="submit" class="btn btn-success">Update bank</button>
    </form>
@endsection
