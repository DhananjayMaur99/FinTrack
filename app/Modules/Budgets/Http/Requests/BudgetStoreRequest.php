<?php

namespace App\Modules\Budgets\Http\Requests;

use App\Shared\Http\Requests\ApiRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BudgetStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Ensure the chosen category exists and is owned by the authenticated user
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn($query) => $query->where('user_id', $this->user()->id))->whereNull('deleted_at'),
            ],
            'limit'       => ['required', 'numeric', 'min:0'],
            'period'      => ['required', 'in:weekly,monthly,yearly'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
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
        // NoOverflow keeps a 31st start date from landing two months out.
        return match ($period) {
            'weekly'  => $startC->copy()->addWeek()->subDay()->toDateString(),
            'yearly'  => $startC->copy()->addYearNoOverflow()->subDay()->toDateString(),
            default   => $startC->copy()->addMonthNoOverflow()->subDay()->toDateString(), //default = monthly
        };
    }
}
