<?php

namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use App\Http\Requests\PaymentSettingStoreRequest;
use App\Http\Requests\PaymentSettingUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentSettingController extends Controller
{
    public function index()
    {
        // Get the first (and likely only) payment setting record
        $paymentSetting = PaymentSetting::first();

        return view('pages.payment-settings.index', compact('paymentSetting'));
    }

    public function create()
    {
        // Check if settings already exist
        $existing = PaymentSetting::first();
        if ($existing) {
            return redirect()->route('payment-settings.edit', $existing->id)
                ->with('warning', 'Pengaturan pembayaran sudah ada. Anda dapat mengeditnya.');
        }

        return view('pages.payment-settings.form');
    }

    public function store(PaymentSettingStoreRequest $request)
    {
        // Check if settings already exist
        $existing = PaymentSetting::first();
        if ($existing) {
            return redirect()->route('payment-settings.edit', $existing->id)
                ->with('warning', 'Pengaturan pembayaran sudah ada. Anda dapat mengeditnya.');
        }

        PaymentSetting::create($request->validated());

        return redirect()->route('payment-settings.index')
            ->with('success', 'Pengaturan pembayaran berhasil dibuat.');
    }

    public function edit(PaymentSetting $paymentSetting)
    {
        return view('pages.payment-settings.form', compact('paymentSetting'));
    }

    public function update(PaymentSettingUpdateRequest $request, PaymentSetting $paymentSetting)
    {
        $paymentSetting->update($request->validated());

        return redirect()->route('payment-settings.index')
            ->with('success', 'Pengaturan pembayaran berhasil diubah.');
    }

    public function destroy(PaymentSetting $paymentSetting)
    {
        $paymentSetting->delete();

        return redirect()->route('payment-settings.index')
            ->with('success', 'Pengaturan pembayaran berhasil dihapus.');
    }
}
