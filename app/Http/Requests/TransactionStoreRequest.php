<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // <-- 1. Import the Rule class

class TransactionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Any authenticated user can attempt to store a transaction.
        // The policy will handle the 'create' logic.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'amount' is required, must be a number, and must be at least 1 cent.
            'amount' => ['required', 'numeric', 'min:0.01'],

            // 'date' is required and must be a valid date format.
            'date' => ['required', 'date'],

            // 'description' is optional.
            'description' => ['nullable', 'string'],

            // 'category_id' is optional.
            // THIS IS THE FIX:
            // It must be nullable, AND
            // It must exist in the 'categories' table, AND
            // It must belong to the currently logged-in user, AND
            // It must NOT be soft-deleted (to prevent using deleted categories in new transactions)
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
