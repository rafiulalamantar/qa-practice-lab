@extends('client.layout', ['page' => 'product-details'])

@section('nav', true)

@section('client')
<div class="container py-4">
    <a href="{{ url('/client/dashboard/dash') }}" class="continue">&larr; Continue Shopping</a>
    <div class="row g-4 mt-1" id="product-detail" hidden>
        <div class="col-md-6"><img class="img-fluid rounded" alt="" id="detail-image"></div>
        <div class="col-md-6">
            <h2 id="detail-name"></h2>
            <h3 class="h4 text-success" id="detail-price"></h3>
            <p class="text-capitalize text-secondary small" id="detail-meta"></p>
            <h6 class="mt-4">Product Description</h6>
            <p id="detail-description"></p>
            <button class="btn btn-lab" type="button" id="detail-add">Add to Cart</button>
        </div>
    </div>
</div>
@endsection
