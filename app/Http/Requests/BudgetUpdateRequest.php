<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BudgetUpdateRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:foreigns,id'],
            'category_id' => ['required', 'integer', 'exists:foreigns,id'],
            'limit' => ['required', 'numeric', 'between:-99999999.99,99999999.99'],
            'period' => ['required', "in:'monthly','yearly'"],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ];
    }
}
