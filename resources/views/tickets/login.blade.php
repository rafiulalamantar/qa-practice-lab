@extends('tickets.auth', ['page' => 'login'])

@section('tt-title', 'Sign in')

@section('auth')
<div class="tt-auth-icon" aria-hidden="true"><i class="bi bi-ticket-perforated"></i></div>
<h2 class="h3 text-center mb-1">Welcome back</h2>
<p class="text-secondary text-center mb-4">Sign in to TicketTrail to continue</p>
<div class="alert alert-danger py-2 small d-flex gap-2" role="alert" id="login-error" data-testid="login-error" hidden></div>
<form id="login-form" novalidate>
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <div class="tt-input-icon">
            <i class="bi bi-envelope"></i>
            <input id="email" name="email" type="email" class="form-control" placeholder="you@email.com" autocomplete="email" data-testid="login-email">
            <div class="invalid-feedback" data-error-for="email"></div>
        </div>
    </div>
    <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <div class="tt-input-icon">
            <i class="bi bi-lock"></i>
            <input id="password" name="password" type="password" class="form-control" placeholder="••••••" autocomplete="current-password" data-testid="login-password">
            <div class="invalid-feedback" data-error-for="password"></div>
        </div>
    </div>
    <button type="submit" class="btn btn-tt btn-lg w-100" id="login-btn" data-testid="login-btn">Sign In</button>
</form>
<p class="text-center text-secondary mt-4 mb-0">Don't have an account? <a href="{{ url('/tickets/register') }}" class="fw-semibold" data-testid="register-link">Register</a></p>
<div class="tt-divider my-4">or try the demo</div>
<div class="tt-demo">
    <div class="d-flex align-items-center gap-2 fw-semibold text-dark mb-1"><i class="bi bi-person-badge text-tt"></i> Shared demo account</div>
    <code id="demo-email">{{ config('practice.demo_email') }}</code> / <code id="demo-password">{{ config('practice.demo_password') }}</code>
    <div class="text-secondary small mt-2">Other learners use it too. Register your own account for clean, private test data.
        Your own account is deleted {{ config('practice.account_lifetime_days') }} days after registration; the demo account always stays.</div>
</div>
@endsection
