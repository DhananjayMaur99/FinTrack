<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // Load category even if soft deleted (for historical data)
        $category = $this->category()->withTrashed()->first();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'is_deleted' => $category->trashed(),
            ] : null,
            'category_id' => $this->category_id,
            'amount' => $this->amount,
            'description' => $this->description,
            'date' => $this->date, // legacy local date used by budgets
            'date_local' => $this->date_local,
            'occurred_at_utc' => $this->occurred_at_utc,
        ];
    }
}
