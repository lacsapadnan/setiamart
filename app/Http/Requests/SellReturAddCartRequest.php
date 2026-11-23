<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SellReturAddCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'input_requests' => 'required|array',
            'input_requests.*.quantity' => 'nullable|numeric|decimal:0,2|min:0.01',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'input_requests.*.quantity.numeric' => 'Jumlah retur harus berupa angka',
            'input_requests.*.quantity.decimal' => 'Jumlah retur maksimal 2 angka di belakang koma',
            'input_requests.*.quantity.min' => 'Jumlah retur minimal 0,01',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors()->all(),
        ], 422));
    }
}
