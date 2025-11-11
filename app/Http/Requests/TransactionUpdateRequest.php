<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // <-- 1. Import the Rule class

class TransactionUpdateRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // This is perfect for PATCH updates.
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'date' => ['sometimes', 'date'], // local date can be adjusted
            'description' => ['sometimes', 'nullable', 'string'],

            // THIS IS THE FIX:
            // Prevent updating to a soft-deleted category
            'category_id' => [
                'sometimes',
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        // If client sends no date in an update we do not auto-fill (avoid overwriting existing value).
        // However, if explicitly wanting to "reset" date to today, they can send a flag or the new date.
        // We still attach a fresh occurred_at_utc if date is being changed for audit trail.
        if ($this->has('date')) {
            $this->merge([
                'occurred_at_utc' => now('UTC')->toDateTimeString(),
            ]);
        }
    }
}
