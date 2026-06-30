<?php

namespace App\Modules\Canteen\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'student_id_ref' => $this->student_id_ref,
            'student_name' => $this->student_name,
            'grade' => $this->grade,
            'class_name' => $this->class_name,
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'total' => (string) $this->total,
            'status' => $this->status,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'items' => $this->whenLoaded('items'),
            'wallet_ready_transaction' => $this->whenLoaded('walletReadyTransaction'),
        ];
    }
}
