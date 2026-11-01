<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UserUpdateRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Ensure at least one updatable field is present in the request body for updates.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $updatable = ['name', 'email', 'timezone', 'password'];

            if (! $this->hasAny($updatable)) {
                $validator->errors()->add('payload', 'At least one updatable field must be provided.');
            }
        });
    }
}
