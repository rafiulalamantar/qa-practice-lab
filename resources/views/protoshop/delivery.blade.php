@extends('protoshop.layout')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div id="purchase-success" class="alert alert-success alert-dismissible" role="alert" hidden>
            <strong>Success!</strong> Thank you! Your order will be delivered in next few weeks :-).
            <button type="button" class="btn-close" aria-label="Close" data-dismiss-alert></button>
        </div>
        <form id="delivery-form" novalidate>
            <label for="country" class="form-label">Please choose your delivery location. Then click on purchase button</label>
            <div class="position-relative mb-3">
                <input class="form-control" id="country" type="text" autocomplete="off" placeholder="Please enter 3 or more characters">
                <div class="suggestions" id="country-suggestions" hidden><ul></ul></div>
            </div>
            <div class="checkbox checkbox-primary mb-3">
                <input class="form-check-input" id="checkbox2" type="checkbox">
                <label for="checkbox2" class="d-inline">I agree with the <a href="#" id="terms-link">term &amp; Conditions</a></label>
            </div>
            <div class="alert alert-danger py-1 px-2" id="delivery-error" hidden></div>
            <input class="btn btn-success btn-lg" type="submit" value="Purchase">
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>window.PRACTICE_COUNTRIES = @json($countries);</script>
@endpush
