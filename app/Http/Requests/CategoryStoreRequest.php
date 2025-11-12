<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // We handle authorization in the controller with Policies. 
        return true;
    }

    /**
     * validation rules
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            // Name must be unique per-user so two users can each have "Groceries"
            'name' => [
                'required',
                'string',
                'max:255',
                // Rule::unique('categories', 'name')
                //     ->where(fn($query) => $query->where('user_id', $this->user()->id)),
            ],
            'icon' => ['string', 'nullable'],
        ];
    }
}
