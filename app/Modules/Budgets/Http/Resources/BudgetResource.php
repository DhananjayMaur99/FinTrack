<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    protected $progressStats;

    public function __construct($resource, $progressStats = null)
    {
        parent::__construct($resource);
        $this->progressStats = $progressStats;
    }

    public function toArray(Request $request): array
    {
        $category = $this->category;

        $data = [
            'id'      => $this->id,
            'user_id' => $this->user_id,
            'category' => $category ? [
                'id'   => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
            ] : null,
            'limit'  => (float) $this->limit,
            'period' => $this->period,
            'range'  => [
                'start' => optional($this->start_date)->format('Y-m-d'),
                'end'   => optional($this->end_date)->format('Y-m-d'),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (is_array($this->progressStats)) {
            $limit     = (float) ($this->progressStats['limit'] ?? $this->limit);
            $spent     = (float) ($this->progressStats['spent'] ?? 0);
            $remaining = (float) ($this->progressStats['remaining'] ?? max(0, $limit - $spent));
            $progress  = (float) ($this->progressStats['progress_percent'] ?? ($limit > 0 ? round(($spent / $limit) * 100, 2) : 0));
            $over      = (bool) ($this->progressStats['is_over_budget'] ?? ($spent > $limit));

            $data['stats'] = [
                'spent'            => $spent,
                'remaining'        => $remaining,
                'progress_percent' => $progress,
                'over'             => $over,
            ];
        }

        return $data;
    }
}
