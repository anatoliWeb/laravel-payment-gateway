<?php

namespace App\Http\Resources\Invoices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'due_amount' => $this->due_amount,
            'payer_user_id' => $this->payer_user_id,
            'company_id' => $this->company_id,
            'seller_id' => $this->seller_id,
            'subscription_id' => $this->subscription_id,
            'payment_id' => $this->payment_id,
            'description' => $this->description,
            'issued_at' => $this->issued_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'voided_at' => $this->voided_at?->toISOString(),
            'overdue_at' => $this->overdue_at?->toISOString(),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
