<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IncomeStatementClearCacheRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('all_branches')) {
            return;
        }

        $normalized = filter_var($this->input('all_branches'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $this->merge([
            'all_branches' => $normalized,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'nullable|integer|exists:users,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'warehouse' => 'nullable|integer|exists:warehouses,id',
            'all_branches' => 'nullable|boolean',
        ];
    }
}
