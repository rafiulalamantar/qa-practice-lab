@extends('client.auth.frame', ['page' => 'password-new'])

@section('auth')
<h1 class="login-title h3 mb-2">Forgot Password</h1>
<p class="text-secondary small mb-4">Enter the email and mobile number you registered with, then choose a new password.</p>
<form id="password-form" novalidate>
    <div class="mb-3">
        <label for="userEmail" class="form-label">Email</label>
        <input id="userEmail" name="userEmail" type="email" class="form-control" placeholder="Enter your email address" required>
    </div>
    <div class="mb-3">
        <label for="userMobile" class="form-label">Registered Phone Number</label>
        <input id="userMobile" name="userMobile" class="form-control" placeholder="enter your number" inputmode="numeric" maxlength="10" required>
    </div>
    <div class="mb-3">
        <label for="userPassword" class="form-label">Password</label>
        <input id="userPassword" name="userPassword" type="password" class="form-control" placeholder="Enter New Password" required>
    </div>
    <div class="mb-3">
        <label for="confirmPassword" class="form-label">Confirm Password</label>
        <input id="confirmPassword" name="confirmPassword" type="password" class="form-control" placeholder="Confirm Password" required>
    </div>
    <button type="submit" class="btn btn-lab w-100">Save New Password</button>
</form>
<p class="small mt-3 mb-0"><a href="{{ url('/client/auth/login') }}">Back to login</a></p>
@endsection
