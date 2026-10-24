@extends('tickets.auth', ['page' => 'register'])

@section('tt-title', 'Register')

@section('auth')
<h2 class="h4 text-center mb-1">Create your account</h2>
<p class="text-secondary text-center mb-2">Get your own private TicketTrail sandbox</p>
<p class="small text-center text-secondary mb-4" id="account-expiry-note" data-testid="account-expiry-note">Accounts are deleted {{ config('practice.account_lifetime_days') }} days after registration. Register again after that.</p>
<div class="alert alert-danger py-2 small" role="alert" id="register-error" data-testid="register-error" hidden></div>
<form id="register-form" novalidate>
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input id="email" name="email" type="email" class="form-control" placeholder="you@email.com" autocomplete="email" data-testid="register-email">
        <div class="invalid-feedback" data-error-for="email"></div>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input id="password" name="password" type="password" class="form-control" placeholder="Min 8 chars, uppercase, number &amp; symbol" autocomplete="new-password" data-testid="register-password">
        <div class="invalid-feedback" data-error-for="password"></div>
        <ul class="tt-pw-rules small mt-2 mb-0" id="password-rules" data-testid="password-rules">
            <li data-rule="length">At least 8 characters</li>
            <li data-rule="upper">One uppercase letter</li>
            <li data-rule="number">One number</li>
            <li data-rule="symbol">One symbol</li>
        </ul>
    </div>
    <div class="mb-4">
        <label for="confirmPassword" class="form-label">Confirm Password</label>
        <input id="confirmPassword" name="confirmPassword" type="password" class="form-control" placeholder="Repeat your password" autocomplete="new-password" data-testid="register-confirm-password">
        <div class="invalid-feedback" data-error-for="confirmPassword"></div>
    </div>
    <button type="submit" class="btn btn-tt w-100" id="register-btn" data-testid="register-btn">Create Account</button>
</form>
<p class="text-center text-secondary mt-4 mb-0">Already have an account? <a href="{{ url('/tickets/login') }}" data-testid="login-link">Sign in</a></p>
@endsection
