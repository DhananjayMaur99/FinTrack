<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource; // Note: Corrected typo from JsonJsonResource

class BudgetResource extends JsonResource
{
    /**
     * Our custom stats, passed from the controller.
     *
     * @var array|null
     */
    protected $progressStats;

    /**
     * Create a new resource instance.
     * (THIS IS THE FIX - This constructor accepts the 2nd argument)
     *
     * @param  mixed  $resource
     * @param  array|null  $progressStats
     * @return void
     */
    public function __construct($resource, $progressStats = null)
    {
        // Call the parent constructor
        parent::__construct($resource);

        // Store our custom stats
        $this->progressStats = $progressStats;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Load category (budget.category_id can be NULL for "overall" budgets)
        $category = $this->category;

        // 1. Get the base budget data
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
            ] : null,
            'category_id' => $this->category_id,
            'category_name' => $category?->name ?? 'Overall Budget',
            'limit' => $this->limit,
            'period' => $this->period,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ];

        // 2. (THE FIX)
        // If our constructor received stats, merge them.
        if ($this->progressStats !== null) {
            $data['progress_stats'] = $this->progressStats;
        }

        return $data;
    }
}
