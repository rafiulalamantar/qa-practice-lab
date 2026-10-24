@extends('layouts.base')

@section('title', 'Practice Page')

@push('head')
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
@endpush

@section('body')
<header class="practice-header">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
        <a href="{{ url('/') }}" class="fw-bold text-decoration-none fs-5">QA Practice Lab</a>
        <nav class="d-flex gap-2">
            <a href="{{ url('/') }}" class="btn btn-primary btn-sm">Home</a>
            <a href="{{ url('/practice') }}" class="btn btn-primary btn-sm">Practice</a>
            <a href="{{ url('/client/auth/login') }}" class="btn btn-primary btn-sm">Login</a>
            <a href="{{ url('/client/auth/register') }}" class="btn btn-primary btn-sm">Signup</a>
        </nav>
    </div>
</header>

<main class="container py-4" id="top">
    <h1 class="mb-3">Practice Page</h1>

    <div class="practice-block">
        <div id="radio-btn-example">
            <fieldset>
                <legend>Radio Button Example</legend>
                @foreach (['radio1', 'radio2', 'radio3'] as $i => $radio)
                    <label for="{{ $radio }}">
                        <input id="{{ $radio }}" value="{{ $radio }}" name="radioButton" class="radioButton" type="radio"> Radio{{ $i + 1 }}
                    </label>
                @endforeach
            </fieldset>
        </div>

        <div id="select-class-example">
            <fieldset>
                <legend>Suggestion Class Example</legend>
                <input type="text" id="autocomplete" class="inputs" placeholder="Type to Select Countries" data-chaos-id>
            </fieldset>
        </div>

        <div>
            <fieldset>
                <legend>Dropdown Example</legend>
                <select id="dropdown-class-example" name="dropdown-class-example" class="form-select">
                    <option value="">Select</option>
                    <option value="option1">Option1</option>
                    <option value="option2">Option2</option>
                    <option value="option3">Option3</option>
                </select>
            </fieldset>
        </div>

        <div id="checkbox-example">
            <fieldset>
                <legend>Checkbox Example</legend>
                @foreach ([1, 2, 3] as $n)
                    <label for="checkBoxOption{{ $n }}">
                        <input id="checkBoxOption{{ $n }}" value="option{{ $n }}" name="checkBoxOption{{ $n }}" type="checkbox"> Option{{ $n }}
                    </label>
                @endforeach
            </fieldset>
        </div>
    </div>

    <div class="practice-block">
        <div>
            <fieldset>
                <legend>Switch Window Example</legend>
                <button id="openwindow" class="btn-style class1" type="button">Open Window</button>
            </fieldset>
        </div>
        <div>
            <fieldset>
                <legend>Switch Tab Example</legend>
                <a id="opentab" class="btn-style class1 class2" href="{{ url('/practice/window?from=tab') }}" target="_blank">Open Tab</a>
            </fieldset>
        </div>
        <div>
            <fieldset>
                <legend>Switch To Alert Example</legend>
                <input id="name" name="enter-name" class="inputs" placeholder="Enter Your Name" type="text">
                <input id="alertbtn" class="btn-style" value="Alert" type="submit">
                <input id="confirmbtn" class="btn-style" value="Confirm" type="submit">
                <p id="confirm-result" class="small text-secondary mt-2 mb-0" aria-live="polite"></p>
            </fieldset>
        </div>
    </div>

    <div class="practice-block">
        <div style="flex-basis: 420px">
            <fieldset>
                <legend>Web Table Example</legend>
                <table id="product" name="courses" class="table-display">
                    <tbody data-chaos-shuffle>
                        <tr><th>Instructor</th><th>Course</th><th>Price</th></tr>
                        @foreach ($courses as [$instructor, $course, $price])
                            <tr><td>{{ $instructor }}</td><td>{{ $course }}</td><td>{{ $price }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </fieldset>
        </div>
        <div style="flex-basis: 380px">
            <fieldset class="mb-3" style="height:auto">
                <legend>Element Displayed Example</legend>
                <input id="hide-textbox" class="btn-style class2" value="Hide" type="submit">
                <input id="show-textbox" class="btn-style class2" value="Show" type="submit">
                <input id="displayed-text" name="show-hide" class="inputs displayed-class mt-3" placeholder="Hide/Show Example" type="text">
            </fieldset>
            <fieldset style="height:auto" data-chaos-delay>
                <legend>Web Table Fixed header</legend>
                <div class="tableFixHead">
                    <table id="product-fixed" class="table-fixed">
                        <thead><tr><th>Name</th><th>Position</th><th>City</th><th>Amount</th></tr></thead>
                        <tbody data-chaos-shuffle>
                            @foreach ($staff as [$who, $position, $city, $amount])
                                <tr><td>{{ $who }}</td><td>{{ $position }}</td><td>{{ $city }}</td><td>{{ $amount }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="totalAmount">Total Amount Collected: {{ collect($staff)->sum(3) }}</div>
            </fieldset>
        </div>
    </div>

    <div class="practice-block">
        <div>
            <fieldset style="padding-bottom: 70px">
                <legend>Mouse Hover Example</legend>
                <div class="mouse-hover">
                    <button id="mousehover" class="btn btn-primary" type="button">Mouse Hover</button>
                    <div class="mouse-hover-content">
                        <a href="#top">Top</a>
                        <a href="">Reload</a>
                    </div>
                </div>
            </fieldset>
        </div>
    </div>

    <div class="practice-block">
        <fieldset>
            <legend>iFrame Example</legend>
            <iframe id="courses-iframe" name="iframe-name" src="{{ url('/practice/frame') }}" style="width: 100%; height: 520px; border: 0" title="Courses"></iframe>
        </fieldset>
    </div>
</main>

<footer class="bg-light border-top py-4 mt-4">
    <div class="container" id="gf-BIG">
        <div class="row small">
            @foreach (['Discount Coupons' => ['PRACTICE10', 'QA25', 'FREESHIP'], 'Latest News' => ['Challenge mode', 'API reference', 'New products'], 'Modules' => ['Automation Practice', 'ProtoShop', 'ShopSphere'], 'Social Media' => ['Facebook', 'Twitter', 'YouTube']] as $heading => $links)
                <div class="col-6 col-md-3 mb-3">
                    <h3 class="h6"><a href="#">{{ $heading }}</a></h3>
                    <ul class="list-unstyled mb-0">
                        @foreach ($links as $link)
                            <li><a href="#" class="text-secondary">{{ $link }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <p class="text-center text-secondary small mb-0">QA Practice Lab &mdash; for learning test automation.</p>
    </div>
</footer>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>window.PRACTICE_COUNTRIES = @json($countries);</script>
<script src="{{ \App\Support\VersionedAsset::url('assets/js/practice.js') }}"></script>
@endpush
