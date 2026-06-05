<?php

namespace App\Http\Resources\Invoices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
