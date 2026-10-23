@extends('client.layout', ['page' => 'order-details'])

@section('nav', true)

@section('client')
<div class="container py-4">
    <a href="{{ url('/client/dashboard/myorders') }}">&larr; View Orders</a>
    <div class="cartSection p-4 mt-3" id="order-summary" hidden>
        <div class="email-title"><h1 class="h4">order summary</h1></div>
        <div class="row g-3">
            <div class="col-md-6">
                <small class="text-secondary d-block">Order Id</small>
                <div class="col-text order-id" id="detail-order-id"></div>
            </div>
            <div class="col-md-6">
                <small class="text-secondary d-block">Ordered By</small>
                <div class="col-text" id="detail-email"></div>
            </div>
            <div class="col-md-6">
                <small class="text-secondary d-block">Country</small>
                <div class="col-text" id="detail-country"></div>
            </div>
            <div class="col-md-6">
                <small class="text-secondary d-block">Ordered Date</small>
                <div class="col-text" id="detail-date"></div>
            </div>
        </div>
        <hr>
        <div class="d-flex gap-3 align-items-center">
            <img alt="" id="detail-image" style="width:110px" class="rounded">
            <div>
                <div class="title fw-bold" id="detail-product"></div>
                <div class="price text-success" id="detail-price"></div>
            </div>
        </div>
    </div>
    <div class="alert alert-danger mt-3 blink_me" id="order-error" hidden></div>
</div>
@endsection
