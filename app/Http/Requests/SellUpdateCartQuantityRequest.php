<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellUpdateCartQuantityRequest extends FormRequest
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
            'quantity' => 'required|numeric|decimal:0,2|min:0.01',
        ];
    }
}
