@extends('client.layout', ['page' => 'cart'])

@section('nav', true)

@section('client')
<div class="container py-4">
    <h1 class="h3">My Cart</h1>
    <div id="cart-empty" class="text-center py-5" hidden>
        <h1 class="h4">No Products in Your Cart !</h1>
        <a href="{{ url('/client/dashboard/dash') }}" class="btn btn-lab mt-2">Continue Shopping</a>
    </div>
    <div class="row g-4" id="cart-content" hidden>
        <div class="col-lg-8"><ul class="list-unstyled d-grid gap-3 mb-0" id="cart-items"></ul></div>
        <div class="col-lg-4">
            <div class="cartSection p-4">
                <ul class="list-unstyled mb-3">
                    <li class="totalRow d-flex justify-content-between"><span class="label">Subtotal</span><span class="value" id="cart-subtotal"></span></li>
                    <li class="totalRow d-flex justify-content-between fw-bold mt-2"><span class="label">Total</span><span class="value" id="cart-total"></span></li>
                </ul>
                <button type="button" class="btn btn-lab w-100" id="checkout">Checkout</button>
            </div>
        </div>
    </div>
</div>
@endsection
