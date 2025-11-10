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
            'amount' => ['required', 'numeric', 'min:0.01'],
            // date is now optional; we will default it if absent
            'date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $tz = $this->user()?->timezone
            ?: $this->header('X-Timezone')
            ?: config('app.timezone', 'UTC');

        // Default local date if not provided
        if (! $this->has('date') || empty($this->input('date'))) {
            $this->merge([
                'date' => now($tz)->toDateString(),
            ]);
        }

        // Always include UTC precise timestamp for ordering (not validated rule; optional internal use)
        $this->merge([
            'occurred_at_utc' => now('UTC')->toDateTimeString(),
        ]);
    }
}
