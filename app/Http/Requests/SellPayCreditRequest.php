<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellPayCreditRequest extends FormRequest
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
            'sell_id' => 'required|integer',
            'payment' => 'required|string',
        ];
    }
}
