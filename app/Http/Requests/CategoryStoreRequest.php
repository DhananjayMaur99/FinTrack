<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // We handle authorization in the controller with Policies.
        // We can just return true here.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // THE FIX:
        // We ONLY validate fields we expect from the user.
        // We REMOVED the 'user_id' rule, because we set that
        // securely in the controller.
        return [
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['string', 'nullable'],
        ];
    }
}
