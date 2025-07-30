<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'total' => $this->total,
            'cash_received' => $this->cash_received,
            'change_amount' => $this->change_amount,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date,
            'cashier' => [
                'id' => $this->cashier->code,
                'name' => $this->cashier->name,
            ],
            'items' => SaleItemResource::collection($this->whenLoaded('saleItems')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
