<?php

namespace App\Http\Requests;

use Illuminate\Support\Carbon;

class BudgetUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Category cannot be changed once a budget is created
            'category_id' => ['prohibited'],
            'limit'       => ['sometimes', 'numeric', 'min:0'],
            'amount'      => ['sometimes', 'numeric', 'min:0'],
            'period'      => ['sometimes', 'in:weekly,monthly,yearly'],
            'start_date'  => ['sometimes', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Ensure the client provides at least one updatable field in the body.
     * If the request body is empty (no updatable keys), we add a validation error.
     */
    // public function withValidator($validator): void
    // {
    //     $validator->after(function ($validator) {
    //         $updatable = ['limit', 'amount', 'period', 'start_date', 'end_date'];

    //         if (! $this->hasAny($updatable)) {
    //             $validator->errors()->add('payload', 'At least one updatable field must be provided.');
    //         }
    //     });
    // }

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
