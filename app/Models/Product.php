<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'category', 'sub_category', 'product_for', 'price', 'description', 'image'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const CATEGORIES = ['fashion', 'electronics', 'household'];

    public const SUB_CATEGORIES = ['t-shirts', 'shirts', 'shoes', 'mobiles', 'laptops', 'kitchen'];

    public const PRODUCT_FOR = ['men', 'women', 'unisex'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function toApi(): array
    {
        return [
            '_id' => (string) $this->id,
            'productName' => $this->name,
            'productCategory' => $this->category,
            'productSubCategory' => $this->sub_category,
            'productFor' => $this->product_for,
            'productPrice' => (float) $this->price,
            'productDescription' => $this->description,
            'productImage' => asset($this->image),
        ];
    }
}
