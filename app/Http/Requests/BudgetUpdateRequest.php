<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class BudgetUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'limit'       => ['sometimes', 'numeric', 'min:0'],
            'amount'      => ['sometimes', 'numeric', 'min:0'], // alias
            'period'      => ['sometimes', 'in:weekly,monthly,yearly'],
            'start_date'  => ['sometimes', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount') && !$this->has('limit')) {
            $this->merge(['limit' => $this->input('amount')]);
        }

        // If start_date/period change but end_date is not sent, recompute it
        $start = $this->input('start_date');
        $period = $this->input('period');
        if ($start && !$this->filled('end_date')) {
            $this->merge([
                'end_date' => $this->computeEndDate($start, $period ?? 'monthly'),
            ]);
        }
    }

    private function computeEndDate(string $start, string $period): string
    {
        $startC = Carbon::parse($start)->startOfDay();
        return match ($period) {
            'weekly'  => $startC->copy()->addWeek()->subDay()->toDateString(),
            'yearly'  => $startC->copy()->addYear()->subDay()->toDateString(),
            default   => $startC->copy()->addMonth()->subDay()->toDateString(),
        };
    }
}
