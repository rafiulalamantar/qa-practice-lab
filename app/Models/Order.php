<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_number', 'user_id', 'product_id', 'product_name', 'product_image',
    'product_description', 'price', 'discount_percent', 'country', 'ordered_by',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'discount_percent' => 'decimal:2'];
    }

    public static function newOrderNumber(): string
    {
        return bin2hex(random_bytes(12));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function toApi(): array
    {
        return [
            '_id' => $this->order_number,
            'orderById' => (string) $this->user_id,
            'orderBy' => $this->ordered_by,
            'productOrderedId' => $this->product_id ? (string) $this->product_id : null,
            'productName' => $this->product_name,
            'productImage' => asset($this->product_image),
            'productDescription' => $this->product_description,
            'orderPrice' => (float) $this->price,
            'discountPercent' => (float) $this->discount_percent,
            'country' => $this->country,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
