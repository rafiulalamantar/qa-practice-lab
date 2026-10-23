@extends('client.layout', ['page' => 'thanks'])

@section('nav', true)

@section('client')
<div class="container py-5 text-center">
    <h1 class="hero-primary">Thank you for the order.</h1>
    <p class="text-secondary">Thank you for Shopping With Us</p>
    <table class="table mx-auto mt-4" style="max-width: 520px">
        <tbody>
            <tr><td class="em-spacer-1 fw-semibold">Order Id(s)</td></tr>
            <tr><td class="em-spacer-1" id="order-ids"></td></tr>
        </tbody>
    </table>
    <button type="button" class="btn btn-outline-secondary" id="download-csv">Click To Download Order Details in CSV</button>
    <p class="mt-4">You can see all the Orders in <a href="{{ url('/client/dashboard/myorders') }}" routerlink="/dashboard/myorders">Orders History Page</a></p>
</div>
@endsection
