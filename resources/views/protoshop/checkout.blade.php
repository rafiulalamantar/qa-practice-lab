@extends('protoshop.layout')

@section('content')
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr><th>Product</th><th>Quantity</th><th class="text-center">Price</th><th class="text-center">Total</th><th></th></tr>
        </thead>
        <tbody id="cart-rows"></tbody>
        <tfoot>
            <tr>
                <td></td><td></td><td></td>
                <td><h3 class="h5 mb-0">Total</h3></td>
                <td class="text-end"><h3 class="h5 mb-0"><strong id="cart-total">$0.00</strong></h3></td>
            </tr>
            <tr>
                <td></td><td></td><td></td>
                <td><a href="{{ url('/protoshop/shop') }}" class="btn btn-secondary">Continue Shopping</a></td>
                <td class="text-end"><a href="{{ url('/protoshop/delivery') }}" class="btn btn-success" id="checkout-btn">Checkout</a></td>
            </tr>
        </tfoot>
    </table>
</div>
<p id="empty-cart" class="text-secondary" hidden>Your cart is empty.</p>
@endsection
