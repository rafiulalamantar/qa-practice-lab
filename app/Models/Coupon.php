<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'discount_percent', 'active'])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return ['discount_percent' => 'decimal:2', 'active' => 'boolean'];
    }
}
