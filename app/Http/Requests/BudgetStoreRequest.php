<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class BudgetStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'limit'       => ['required_without:amount', 'numeric', 'min:0'],
            'amount'      => ['sometimes', 'numeric', 'min:0'], // alias for limit
            'period'      => ['required', 'in:weekly,monthly,yearly'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && !$this->has('limit')) {
            $this->merge(['limit' => $this->input('amount')]);
        }

        if (!$this->filled('end_date') && $this->filled('start_date')) {
            $this->merge([
                'end_date' => $this->computeEndDate(
                    $this->input('start_date'),
                    $this->input('period', 'monthly')
                ),
            ]);
        }
    }

    private function computeEndDate(string $start, string $period): string
    {
        $startC = Carbon::parse($start)->startOfDay();
        return match ($period) {
            'weekly'  => $startC->copy()->addWeek()->subDay()->toDateString(),
            'yearly'  => $startC->copy()->addYear()->subDay()->toDateString(),
            default   => $startC->copy()->addMonth()->subDay()->toDateString(), // monthly
        };
    }
}
