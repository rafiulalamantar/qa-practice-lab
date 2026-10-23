<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['productId' => ['required', 'integer', 'exists:products,id']]);

        $item = CartItem::firstOrNew(['user_id' => $request->user()->id, 'product_id' => $data['productId']]);
        $item->quantity = min(($item->quantity ?? 0) + 1, 10);
        $item->save();

        return response()->json(['message' => 'Product Added To Cart', 'count' => $this->count($request)]);
    }

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->cartItems()->with('product')->latest('updated_at')->get();

        return response()->json([
            'products' => $items->map(fn (CartItem $item) => $item->product->toApi() + ['quantity' => $item->quantity])->values(),
            'count' => $items->sum('quantity'),
            'message' => $items->isEmpty() ? 'No Product in Cart' : 'Cart Data Found',
        ]);
    }

    public function count(Request $request): int
    {
        return (int) $request->user()->cartItems()->sum('quantity');
    }

    public function countJson(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->count($request)]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $deleted = $request->user()->cartItems()->where('product_id', $product->id)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Product is not in your cart'], 404);
        }

        return response()->json(['message' => 'Product Removed from cart', 'count' => $this->count($request)]);
    }
}
