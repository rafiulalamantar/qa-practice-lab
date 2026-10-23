@extends('protoshop.layout')

@section('nav-right')
<a class="nav-link btn btn-primary text-white px-3" href="{{ url('/protoshop/checkout') }}" id="checkout-link">
    Checkout ( <span data-cart-count>0</span> ) <span class="visually-hidden">(current)</span>
</a>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-3 mb-4">
        <h1 class="h3 my-3">Shop Name</h1>
        <div class="list-group">
            <a href="#" class="list-group-item">Category 1</a>
            <a href="#" class="list-group-item">Category 2</a>
            <a href="#" class="list-group-item">Category 3</a>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="rounded bg-dark text-white p-4 my-3" id="shop-banner">
            <h2 class="h4 mb-1">Phones week</h2>
            <p class="mb-0 text-white-50">Add phones to the cart, then check out.</p>
        </div>
        <div class="row" id="product-grid" data-chaos-delay>
            @foreach ($products as $product)
                <app-card class="col-lg-3 col-md-6 mb-3">
                    <div class="card h-100">
                        <img class="card-img-top" src="{{ asset($product['image']) }}" alt="{{ $product['name'] }}">
                        <div class="card-body">
                            <h4 class="card-title h6"><a href="#">{{ $product['name'] }}</a></h4>
                            <h5 class="h6">${{ number_format($product['price'], 2) }}</h5>
                            <p class="card-text small text-secondary">{{ $product['description'] }}</p>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-info btn-sm" type="button" data-add="{{ $product['id'] }}">Add <i class="visually-hidden">to cart</i></button>
                        </div>
                    </div>
                </app-card>
            @endforeach
        </div>
    </div>
</div>
@endsection
