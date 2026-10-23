<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'productName' => ['nullable', 'string', 'max:120'],
            'minPrice' => ['nullable', 'numeric', 'min:0'],
            'maxPrice' => ['nullable', 'numeric', 'min:0'],
            'productCategory' => ['nullable', 'array'],
            'productCategory.*' => ['string', 'in:'.implode(',', Product::CATEGORIES)],
            'productSubCategory' => ['nullable', 'array'],
            'productSubCategory.*' => ['string', 'in:'.implode(',', Product::SUB_CATEGORIES)],
            'productFor' => ['nullable', 'array'],
            'productFor.*' => ['string', 'in:'.implode(',', Product::PRODUCT_FOR)],
        ]);

        $products = Product::query()
            ->when($filters['productName'] ?? null, fn ($q, $name) => $q->where('name', 'like', '%'.addcslashes($name, '%_\\').'%'))
            ->when(isset($filters['minPrice']), fn ($q) => $q->where('price', '>=', $filters['minPrice']))
            ->when(isset($filters['maxPrice']), fn ($q) => $q->where('price', '<=', $filters['maxPrice']))
            ->when($filters['productCategory'] ?? null, fn ($q, $v) => $q->whereIn('category', $v))
            ->when($filters['productSubCategory'] ?? null, fn ($q, $v) => $q->whereIn('sub_category', $v))
            ->when($filters['productFor'] ?? null, fn ($q, $v) => $q->whereIn('product_for', $v))
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $products->map->toApi()->values(),
            'count' => $products->count(),
            'message' => 'All Products fetched Successfully',
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => $product->toApi(), 'message' => 'Product Details fetched Successfully']);
    }
}
