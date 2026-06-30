<?php

namespace App\Modules\Canteen\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'unit' => $this->unit,
            'selling_price' => (string) $this->selling_price,
            'cost_price' => $this->cost_price !== null ? (string) $this->cost_price : null,
            'is_active' => $this->is_active,
            'is_restricted_default' => $this->is_restricted_default,
            'restriction_tags' => $this->restriction_tags ?? [],
            'image_path' => $this->image_path,
            'metadata' => $this->metadata,
            'on_hand' => $this->when(isset($this->on_hand), (string) $this->on_hand),
            'is_low_stock' => $this->when(isset($this->is_low_stock), $this->is_low_stock),
            'is_out_of_stock' => $this->when(isset($this->is_out_of_stock), $this->is_out_of_stock),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
