@extends('client.auth.frame', ['page' => 'login'])

@section('auth')
<h1 class="login-title h3 mb-4">Log in</h1>
<form id="login-form" novalidate>
    <div class="mb-3">
        <label for="userEmail" class="form-label">Email</label>
        <input id="userEmail" name="userEmail" type="email" formcontrolname="userEmail" class="form-control" placeholder="email@example.com" required>
        <div class="invalid-feedback">*Email is required</div>
    </div>
    <div class="mb-3">
        <label for="userPassword" class="form-label">Password</label>
        <input id="userPassword" name="userPassword" type="password" formcontrolname="userPassword" class="form-control" placeholder="enter your password" required>
        <div class="invalid-feedback">*Password is required</div>
    </div>
    <input id="login" type="submit" class="btn btn-lab w-100 login-btn" value="Login">
</form>
<div class="d-flex justify-content-between mt-3 small">
    <a class="forgot-password-link" href="{{ url('/client/auth/password-new') }}">Forgot password?</a>
    <span>Don't have an account? <a class="text-reset fw-semibold" href="{{ url('/client/auth/register') }}">Register here</a></span>
</div>
<div class="demo-creds p-3 mt-4">
    <strong>Demo account</strong> (shared, read-only password):<br>
    <span id="demo-email">{{ config('practice.demo_email') }}</span> / <span id="demo-password">{{ config('practice.demo_password') }}</span>
    <div class="text-secondary mt-1">Register your own account for private carts and orders.</div>
</div>
@endsection
