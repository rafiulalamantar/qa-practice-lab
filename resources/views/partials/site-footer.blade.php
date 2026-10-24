{{-- Author credits and licence, shown at the bottom of every page (see layouts/base). --}}
@php($author = config('practice.author'))
<footer class="site-footer" id="site-footer" data-testid="site-footer">
    <span>&copy; {{ date('Y') }} QA Practice Lab &middot; Built by <strong id="footer-author" data-testid="footer-author">{{ $author['name'] }}</strong></span>
    <nav class="site-footer-links" aria-label="Author links">
        @if ($author['github'])
            <a href="{{ $author['github'] }}" target="_blank" rel="noopener" id="footer-github" data-testid="footer-github">GitHub</a>
        @endif
        @if ($author['linkedin'])
            <a href="{{ $author['linkedin'] }}" target="_blank" rel="noopener" id="footer-linkedin" data-testid="footer-linkedin">LinkedIn</a>
        @endif
        <a href="{{ url('/license') }}" target="_blank" rel="noopener" id="footer-license" data-testid="footer-license">MIT License</a>
        @if ($author['coffee'])
            <a href="{{ $author['coffee'] }}" target="_blank" rel="noopener" class="site-footer-coffee" id="footer-coffee" data-testid="footer-coffee">&#9749; Buy me a coffee</a>
        @endif
    </nav>
</footer>
