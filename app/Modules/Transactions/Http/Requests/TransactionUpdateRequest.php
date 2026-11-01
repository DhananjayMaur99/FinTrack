<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TransactionUpdateRequest extends ApiRequest
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
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'date' => ['sometimes', 'date'], // local date can be adjusted
            'description' => ['sometimes', 'nullable', 'string'],
            'category_id' => [
                'sometimes',
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'), // don't update to a  soft deleted category
            ],
        ];
    }
    /**
     * Ensure at least one updatable field is present in the request body for updates.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $updatable = ['amount', 'date', 'description', 'category_id'];

            if (! $this->hasAny($updatable)) {
                $validator->errors()->add('payload', 'At least one updatable field must be provided.');
            }
        });
    }
}
