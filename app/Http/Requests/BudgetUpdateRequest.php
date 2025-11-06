<?php

namespace App\Http\Requests; // <-- 1. Fixed namespace

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// 2. Fixed class name to match the file
class BudgetUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // The BudgetPolicy will authorize the actual request.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 3. Fixed rules to use 'sometimes' for updating
        return [
            'limit' => ['sometimes', 'numeric', 'min:0.01'],
            'period' => ['sometimes', Rule::in(['monthly', 'yearly'])],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],

            // Secure validation: Category must exist AND belong to the user AND not be deleted
            'category_id' => [
                'sometimes',
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],       ];
    }
}
