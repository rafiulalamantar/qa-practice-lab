@extends('client.auth.frame', ['page' => 'register'])

@section('auth')
<div id="register-success" class="text-center" hidden>
    <h1 class="headcolor h3">Account Created Successfully</h1>
    <a href="{{ url('/client/auth/login') }}" class="btn btn-lab mt-3">Login</a>
</div>
<div id="register-panel">
    <h1 class="login-title h3 mb-4">Register</h1>
    <form id="register-form" novalidate>
        <div class="row g-3">
            <div class="col-sm-6">
                <label for="firstName" class="form-label">First Name</label>
                <input id="firstName" name="firstName" formcontrolname="firstName" class="form-control" placeholder="First Name" required>
                <div class="invalid-feedback">*First Name is required</div>
            </div>
            <div class="col-sm-6">
                <label for="lastName" class="form-label">Last Name</label>
                <input id="lastName" name="lastName" formcontrolname="lastName" class="form-control" placeholder="Last Name" required>
                <div class="invalid-feedback">*Last Name is required</div>
            </div>
            <div class="col-sm-6">
                <label for="userEmail" class="form-label">Email</label>
                <input id="userEmail" name="userEmail" type="email" formcontrolname="userEmail" class="form-control" placeholder="email@example.com" required>
                <div class="invalid-feedback">*Enter a valid email</div>
            </div>
            <div class="col-sm-6">
                <label for="userMobile" class="form-label">Phone Number</label>
                <input id="userMobile" name="userMobile" formcontrolname="userMobile" class="form-control" placeholder="enter your number" inputmode="numeric" maxlength="10" required>
                <div class="invalid-feedback">*Phone Number must be 10 digit</div>
            </div>
            <div class="col-sm-6">
                <label for="occupation" class="form-label">Occupation</label>
                <select id="occupation" name="occupation" formcontrolname="occupation" class="form-select">
                    <option value="">Choose your occupation</option>
                    @foreach (['Doctor', 'Student', 'Engineer', 'Scientist'] as $occupation)
                        <option value="{{ $occupation }}">{{ $occupation }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6">
                <span class="form-label d-block">Gender</span>
                @foreach (['Male', 'Female'] as $gender)
                    <div class="form-check form-check-inline mt-2">
                        <input class="form-check-input" type="radio" name="gender" id="gender{{ $gender }}" formcontrolname="gender" value="{{ $gender }}">
                        <label class="form-check-label" for="gender{{ $gender }}">{{ $gender }}</label>
                    </div>
                @endforeach
            </div>
            <div class="col-sm-6">
                <label for="userPassword" class="form-label">Password</label>
                <input id="userPassword" name="userPassword" type="password" formcontrolname="userPassword" class="form-control" placeholder="Password" required>
                <div class="form-text">8+ chars with upper, lower, number and symbol.</div>
            </div>
            <div class="col-sm-6">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <input id="confirmPassword" name="confirmPassword" type="password" formcontrolname="confirmPassword" class="form-control" placeholder="Confirm Password" required>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ageCheck" name="required" formcontrolname="required">
                    <label class="form-check-label" for="ageCheck">I am 18 year or Older</label>
                    <div class="invalid-feedback">*Please check above checkbox</div>
                </div>
            </div>
        </div>
        <input id="login" type="submit" class="btn btn-lab w-100 mt-4" value="Register">
    </form>
    <p class="small mt-3 mb-0">Already have an account? <a class="text-reset fw-semibold" href="{{ url('/client/auth/login') }}">Login here</a></p>
</div>
@endsection
