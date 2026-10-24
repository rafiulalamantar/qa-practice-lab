@extends('tickets.auth', ['page' => 'login'])

@section('tt-title', 'Sign in')

@section('auth')
<h2 class="h4 text-center mb-1">Sign in to TicketTrail</h2>
<p class="text-secondary text-center mb-4">Enter your credentials to continue</p>
<div class="alert alert-danger py-2 small" role="alert" id="login-error" data-testid="login-error" hidden></div>
<form id="login-form" novalidate>
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input id="email" name="email" type="email" class="form-control" placeholder="you@email.com" autocomplete="email" data-testid="login-email">
        <div class="invalid-feedback" data-error-for="email"></div>
    </div>
    <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <input id="password" name="password" type="password" class="form-control" placeholder="••••••" autocomplete="current-password" data-testid="login-password">
        <div class="invalid-feedback" data-error-for="password"></div>
    </div>
    <button type="submit" class="btn btn-tt w-100" id="login-btn" data-testid="login-btn">Sign In</button>
</form>
<p class="text-center text-secondary mt-4 mb-0">Don't have an account? <a href="{{ url('/tickets/register') }}" data-testid="register-link">Register</a></p>
<div class="demo-creds p-3 mt-4 small">
    <strong>Shared demo account:</strong> <span id="demo-email">{{ config('practice.demo_email') }}</span> / <span id="demo-password">{{ config('practice.demo_password') }}</span>
    <div class="text-secondary mt-1">Other learners use it too. Register your own account for clean, private test data.
        Your own account is deleted {{ config('practice.account_lifetime_days') }} days after registration; the demo account always stays.</div>
</div>
@endsection
