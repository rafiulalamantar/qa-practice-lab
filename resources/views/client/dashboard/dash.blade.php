@extends('client.layout', ['page' => 'dash'])

@section('nav', true)

@section('client')
<div class="container-fluid px-3 px-lg-4 py-4">
    <div class="row g-4">
        <aside class="col-lg-3">
            <form class="filters p-3" id="filters">
                <h6 class="mt-0">Search</h6>
                <input type="text" name="search" class="form-control" placeholder="search" aria-label="Search products">
                <h6>Price range</h6>
                <div class="d-flex gap-2">
                    <input type="number" name="minPrice" formcontrolname="minPrice" class="form-control" placeholder="Min Price" min="0">
                    <input type="number" name="maxPrice" formcontrolname="maxPrice" class="form-control" placeholder="Max Price" min="0">
                </div>
                @foreach (['productCategory' => ['Categories', \App\Models\Product::CATEGORIES], 'productSubCategory' => ['Sub Categories', \App\Models\Product::SUB_CATEGORIES], 'productFor' => ['Search For', \App\Models\Product::PRODUCT_FOR]] as $field => [$heading, $options])
                    <h6>{{ $heading }}</h6>
                    @foreach ($options as $option)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="{{ $field }}" value="{{ $option }}" id="{{ $field }}-{{ $option }}">
                            <label class="form-check-label text-capitalize" for="{{ $field }}-{{ $option }}">{{ $option }}</label>
                        </div>
                    @endforeach
                @endforeach
                <button type="reset" class="btn btn-outline-secondary btn-sm mt-3 w-100">Clear filters</button>
            </form>
        </aside>
        <section class="col-lg-9">
            <div id="res" class="text-secondary mb-3" aria-live="polite"></div>
            <div class="row g-3" id="products"></div>
            <div id="no-products" class="text-center py-5" hidden>
                <h2 class="h5">No Products Found</h2>
            </div>
        </section>
    </div>
</div>
@endsection
