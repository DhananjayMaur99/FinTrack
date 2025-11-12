<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CategoryUpdateRequest extends ApiRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                // Ensure updated name stays unique for this user; ignore current category id
                Rule::unique('categories', 'name')
                    ->where(fn($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($this->route('category')->id ?? null),
            ],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Ensure at least one updatable field is present in the request body for updates.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $updatable = ['name', 'icon'];

            if (! $this->hasAny($updatable)) {
                $validator->errors()->add('payload', 'At least one updatable field must be provided.');
            }
        });
    }
}
