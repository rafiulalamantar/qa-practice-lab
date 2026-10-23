@extends('client.layout', ['page' => 'myorders'])

@section('nav', true)

@section('client')
<div class="container py-4">
    <h1 class="h3">Your Orders</h1>
    <div id="no-orders" class="text-center py-5" hidden>
        <p class="mt-2">You have No Orders to show at this time. Please Visit Back Us</p>
        <a href="{{ url('/client/dashboard/dash') }}" class="btn btn-lab">Go Back to Shop</a>
    </div>
    <div class="table-responsive bg-white rounded" id="orders-wrap" hidden>
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="thead-dark table-dark">
                <tr><th scope="col">Order Id</th><th scope="col">Image</th><th scope="col">Name</th><th scope="col">Price</th><th scope="col">Ordered Date</th><th scope="col">View</th><th scope="col">Delete</th></tr>
            </thead>
            <tbody id="orders-body"></tbody>
        </table>
    </div>
</div>
@endsection
