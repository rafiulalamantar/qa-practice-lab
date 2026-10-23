<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['couponCode' => ['required', 'string', 'max:40']]);
        $coupon = $this->findCoupon($data['couponCode']);

        if (! $coupon) {
            return response()->json(['message' => 'Invalid Coupon'], 404);
        }

        return response()->json(['message' => 'Coupon Applied', 'discountPercent' => (float) $coupon->discount_percent]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orders' => ['required', 'array', 'min:1', 'max:20'],
            'orders.*.country' => ['required', 'string', Rule::in(Countries::all())],
            'orders.*.productOrderedId' => ['required', 'integer', 'exists:products,id'],
            'couponCode' => ['nullable', 'string', 'max:40'],
        ], [
            'orders.*.country.in' => 'Please select a valid country from the suggestions.',
        ]);

        $user = $request->user();
        $discount = 0;

        if (! empty($data['couponCode'])) {
            $coupon = $this->findCoupon($data['couponCode']);
            if (! $coupon) {
                return response()->json(['message' => 'Invalid Coupon'], 422);
            }
            $discount = (float) $coupon->discount_percent;
        }

        $products = Product::findMany(collect($data['orders'])->pluck('productOrderedId'))->keyBy('id');

        $orders = DB::transaction(function () use ($data, $user, $products, $discount) {
            $orders = collect($data['orders'])->map(function (array $line) use ($user, $products, $discount) {
                $product = $products[$line['productOrderedId']];

                return Order::create([
                    'order_number' => Order::newOrderNumber(),
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $product->image,
                    'product_description' => $product->description,
                    'price' => round($product->price * (1 - $discount / 100), 2),
                    'discount_percent' => $discount,
                    'country' => $line['country'],
                    'ordered_by' => $user->email,
                ]);
            });

            $user->cartItems()->whereIn('product_id', $products->keys())->delete();

            return $orders;
        });

        return response()->json([
            'orders' => $orders->pluck('order_number'),
            'productOrderId' => $orders->pluck('product_id')->map(fn ($id) => (string) $id),
            'message' => 'Order Placed Successfully',
        ], 201);
    }

    public function forCustomer(Request $request, string $userId): JsonResponse
    {
        if ($userId !== (string) $request->user()->id) {
            return response()->json(['message' => 'You are not authorize to view these orders'], 403);
        }

        $orders = $request->user()->orders()->latest()->latest('id')->get();

        return response()->json([
            'data' => $orders->map->toApi()->values(),
            'count' => $orders->count(),
            'message' => $orders->isEmpty() ? 'No Orders' : 'Orders fetched for customer Successfully',
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $request->validate(['id' => ['required', 'string']]);
        $order = Order::where('order_number', $request->query('id'))->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'You are not authorize to view this order'], 403);
        }

        return response()->json(['data' => $order->toApi(), 'message' => 'Orders fetched for customer Successfully']);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'You are not authorize to delete this order'], 403);
        }

        $order->delete();

        return response()->json(['message' => 'Orders Deleted Successfully']);
    }

    public function downloadCsv(Request $request): StreamedResponse
    {
        $orders = $request->user()->orders()->latest()->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order Id', 'Product Name', 'Price', 'Discount %', 'Country', 'Ordered By', 'Ordered Date']);
            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->order_number, $order->product_name, $order->price, $order->discount_percent,
                    $order->country, $order->ordered_by, $order->created_at->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, 'orders.csv', ['Content-Type' => 'text/csv']);
    }

    private function findCoupon(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper(trim($code)))->where('active', true)->first();
    }
}
