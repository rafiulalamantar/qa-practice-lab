@extends('layouts.base')

@section('hide-site-footer', true)

@section('title', 'QA Practice Lab - Courses')

@section('body')
<nav class="navbar navbar-expand bg-dark navbar-dark px-3">
    <span class="navbar-brand">Course Catalogue</span>
    <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="#courses">Courses</a></li>
        <li class="nav-item"><a class="nav-link" href="#mentorship">Mentorship</a></li>
        <li class="nav-item"><a class="nav-link" href="#job-support">Job Support</a></li>
    </ul>
</nav>
<main class="container py-4">
    <h2 id="courses" class="h4">Popular courses</h2>
    <div class="row g-3">
        @foreach ($courses as [$instructor, $course, $price])
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 course-card">
                    <div class="card-body">
                        <h3 class="h6 card-title">{{ $course }}</h3>
                        <p class="text-secondary small mb-1">{{ $instructor }}</p>
                        <span class="badge text-bg-success price">${{ $price }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <section id="mentorship" class="mt-5">
        <h2 class="h4">Mentorship</h2>
        <div class="d-flex gap-3 flex-wrap">
            <div class="pricing-title border rounded p-3"><h3 class="h6">BRONZE</h3><p class="mb-0">$99</p></div>
            <div class="pricing-title border rounded p-3"><h3 class="h6">SILVER</h3><p class="mb-0">$199</p></div>
            <div class="pricing-title border rounded p-3"><h3 class="h6">PLATINUM</h3><p class="mb-0">$299</p></div>
        </div>
    </section>
    <section id="job-support" class="mt-5">
        <h2 class="h4">Job Support</h2>
        <p class="text-secondary">Over <span class="fw-bold text-dark" id="subscribers">13,522</span> learners have used this lab.</p>
    </section>
</main>
@endsection
