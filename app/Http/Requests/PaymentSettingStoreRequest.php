<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentSettingStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('simpan pengaturan pembayaran');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'in_town_account_number' => 'required|string|max:50',
            'in_town_account_name' => 'required|string|max:255',
            'out_of_town_account_number' => 'required|string|max:50',
            'out_of_town_account_name' => 'required|string|max:255',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'in_town_account_number.required' => 'Nomor rekening dalam kota wajib diisi.',
            'in_town_account_name.required' => 'Nama rekening dalam kota wajib diisi.',
            'out_of_town_account_number.required' => 'Nomor rekening luar kota wajib diisi.',
            'out_of_town_account_name.required' => 'Nama rekening luar kota wajib diisi.',
        ];
    }
}
