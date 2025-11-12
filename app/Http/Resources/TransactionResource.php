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
            'date' => $this->date?->format('Y-m-d'), // Transaction date in user's timezone
            'created_at' => $this->created_at?->toIso8601String(), // When transaction was recorded
            'updated_at' => $this->updated_at?->toIso8601String(), // Last modification time
        ];
    }
}
