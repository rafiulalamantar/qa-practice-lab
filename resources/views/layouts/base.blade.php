<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'QA Practice Lab')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    @stack('head')
    <link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('assets/css/app.css') }}">
    @stack('styles')
    <script src="{{ \App\Support\VersionedAsset::url('assets/js/chaos.js') }}"></script>
</head>
<body class="@yield('body-class')">
    <div id="chaos-banner" class="chaos-banner" hidden>
        Challenge mode is ON &middot; seed <strong id="chaos-seed"></strong>
        &middot; <a href="?chaos=0" id="chaos-off">turn off</a>
    </div>
    @yield('body')
    {{-- Pages loaded inside an iframe or popup set this so learners' assertions on them stay unchanged. --}}
    @sectionMissing('hide-site-footer')
        @include('partials.site-footer')
    @endif
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
