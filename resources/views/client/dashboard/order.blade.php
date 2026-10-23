@extends('client.layout', ['page' => 'order'])

@section('nav', true)

@section('client')
<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="cartSection p-4">
                <h2 class="h5">Payment Method</h2>
                <p class="small text-secondary">Practice form only &mdash; the defaults are dummy values and nothing is charged.</p>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small" for="cc-number">Credit Card Number</label>
                        <input class="form-control" id="cc-number" value="4542 9931 9292 2293" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="expiry-month">Expiry Date</label>
                        <select class="form-select" id="expiry-month">@for ($m = 1; $m <= 12; $m++)<option>{{ sprintf('%02d', $m) }}</option>@endfor</select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="expiry-year">&nbsp;</label>
                        <select class="form-select" id="expiry-year">@for ($y = now()->year; $y <= now()->year + 10; $y++)<option>{{ $y }}</option>@endfor</select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small" for="cvv">CVV Code</label>
                        <input class="form-control" id="cvv" maxlength="3" autocomplete="off">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small" for="name-on-card">Name on Card</label>
                        <input class="form-control" id="name-on-card" autocomplete="off">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small" for="coupon">Apply Coupon</label>
                        <div class="input-group">
                            <input class="form-control" id="coupon" name="coupon" placeholder="e.g. PRACTICE10">
                            <button class="btn btn-outline-secondary" type="button" id="apply-coupon">Apply Coupon</button>
                        </div>
                        <p class="small mt-1 mb-0" id="coupon-result"></p>
                    </div>
                </div>

                <h2 class="h5 mt-4">Shipping Information</h2>
                <div class="user__name mb-2"><label type="text" id="shipping-email" class="text-secondary"></label></div>
                <div class="position-relative">
                    <input class="form-control" id="country" placeholder="Select Country" autocomplete="off" aria-label="Select Country">
                    <section class="ta-results list-group" id="country-results" hidden></section>
                </div>
                <button type="button" class="btn btn-lab btnn action__submit mt-4" id="place-order">Place Order</button>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="cartSection p-4">
                <h2 class="h5">Order Summary</h2>
                <ul class="list-unstyled d-grid gap-2" id="order-items"></ul>
                <div class="d-flex justify-content-between"><span>Subtotal</span><span id="order-subtotal"></span></div>
                <div class="d-flex justify-content-between text-success"><span>Discount</span><span id="order-discount">-$0</span></div>
                <div class="d-flex justify-content-between fw-bold mt-2"><span>Total</span><span id="order-total"></span></div>
            </div>
        </div>
    </div>
</div>
@endsection
