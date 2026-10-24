@extends('layouts.base')

@section('hide-site-footer', true)

@section('title', 'QA Practice Lab - New Window')

@section('body')
<main class="container py-5">
    <p class="text-uppercase small fw-bold text-secondary mb-1" id="opened-from">Opened from: {{ request('from', 'direct') }}</p>
    <h1>Welcome to the child window</h1>
    <p class="lead">Switch your driver's focus here, assert the heading, then switch back to the parent page.</p>
    <ul id="mentors" class="list-group mb-4" style="max-width: 420px">
        <li class="list-group-item">mentor@practice.test</li>
        <li class="list-group-item">Access all modules free of charge</li>
        <li class="list-group-item">Job support for every learner</li>
    </ul>
    <p class="red">Please email us at <a href="mailto:mentor@practice.test">mentor@practice.test</a> with below template to receive response</p>
    <button class="btn btn-secondary" type="button" onclick="window.close()">Close this window</button>
</main>
@endsection
