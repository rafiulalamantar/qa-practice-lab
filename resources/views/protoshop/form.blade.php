@extends('protoshop.layout')

@section('content')
<h1 class="text-center">Form Practice</h1>
<h4 class="text-center text-secondary mb-4">by QA Practice Lab</h4>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div id="form-success" class="alert alert-success alert-dismissible" role="alert" hidden>
            <strong>Success!</strong> The Form has been submitted successfully!.
            <button type="button" class="btn-close" aria-label="Close" data-dismiss-alert></button>
        </div>

        <form id="practice-form" novalidate>
            <div class="mb-3">
                <label for="name-input">Name</label>
                <input class="form-control" id="name-input" name="name" type="text" minlength="2" required data-bind="name">
                <div class="alert alert-danger mt-2 py-1 px-2" data-error-for="name" hidden></div>
            </div>
            <div class="mb-3">
                <label for="email-input">Email</label>
                <input class="form-control" id="email-input" name="email" type="text" required>
                <div class="alert alert-danger mt-2 py-1 px-2" data-error-for="email" hidden></div>
            </div>
            <div class="mb-3">
                <label for="exampleInputPassword1">Password</label>
                <input class="form-control" id="exampleInputPassword1" placeholder="Password" type="password">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" id="exampleCheck1" type="checkbox">
                <label class="form-check-label" for="exampleCheck1">Check me out if you love ice cream!</label>
            </div>
            <div class="mb-3">
                <label for="exampleFormControlSelect1">Gender</label>
                <select class="form-select" id="exampleFormControlSelect1">
                    <option>Male</option>
                    <option>Female</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="d-block">Employment Status:</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" id="inlineRadio1" name="inlineRadioOptions" type="radio" value="option1">
                    <label class="form-check-label" for="inlineRadio1">Student</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" id="inlineRadio2" name="inlineRadioOptions" type="radio" value="option2">
                    <label class="form-check-label" for="inlineRadio2">Employed</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" id="inlineRadio3" name="inlineRadioOptions" type="radio" value="option3" disabled>
                    <label class="form-check-label" for="inlineRadio3">Entrepreneur (disabled)</label>
                </div>
            </div>
            <div class="mb-3">
                <label for="dateofBirth">Date of Birth</label>
                <input class="form-control" id="dateofBirth" name="bday" type="date" min="1900-01-01" max="2100-12-31">
            </div>
            <input class="btn btn-success" type="submit" value="Submit">
        </form>

        <h4 class="mt-5">Two-way Data Binding example:</h4>
        <input class="form-control" name="name" type="text" data-bind="name" aria-label="Two-way bound name">
    </div>
</div>
@endsection
