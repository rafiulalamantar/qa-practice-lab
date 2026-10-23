/*
 * Challenge mode ("chaos") for every practice page.
 *
 *   ?chaos=1          turn on (remembered in localStorage + cookie)
 *   ?chaos=0          turn off
 *   ?seed=1234        replay a specific random run
 *
 * Declarative hooks, applied on DOMContentLoaded when enabled:
 *   data-chaos-id        element id gets a random suffix   (robust locators)
 *   data-chaos-delay     element is hidden then revealed   (explicit waits)
 *   data-chaos-shuffle   child rows of a <tbody> shuffled  (no index locators)
 */
(function () {
    var STORAGE_KEY = 'practice.chaos';
    var params = new URLSearchParams(location.search);

    function store(get, value) {
        try {
            if (get) return localStorage.getItem(STORAGE_KEY);
            if (value === null) localStorage.removeItem(STORAGE_KEY); else localStorage.setItem(STORAGE_KEY, value);
        } catch (e) { return null; }
    }

    if (params.get('chaos') === '1') store(false, '1');
    if (params.get('chaos') === '0') store(false, null);

    var enabled = store(true) === '1';
    document.cookie = 'practice_chaos=' + (enabled ? '1' : '0') + '; path=/; SameSite=Lax';

    var seed = parseInt(params.get('seed'), 10);
    if (isNaN(seed)) seed = Math.floor(Math.random() * 100000);

    // mulberry32: tiny seeded PRNG so a run can be replayed with ?seed=
    var state = seed >>> 0;
    function random() {
        state = (state + 0x6D2B79F5) >>> 0;
        var t = state;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    }

    function between(min, max) { return Math.floor(min + random() * (max - min)); }

    var Chaos = {
        enabled: enabled,
        seed: seed,
        random: random,
        between: between,
        /** Run fn now, or after a random 0.5-3 s delay in challenge mode. */
        later: function (fn, min, max) {
            if (!enabled) return fn();
            setTimeout(fn, between(min || 500, max || 3000));
        },
        suffix: function () { return random().toString(36).slice(2, 7); },
        shuffle: function (arr) {
            for (var i = arr.length - 1; i > 0; i--) {
                var j = Math.floor(random() * (i + 1));
                var tmp = arr[i]; arr[i] = arr[j]; arr[j] = tmp;
            }
            return arr;
        }
    };
    window.Chaos = Chaos;

    document.addEventListener('DOMContentLoaded', function () {
        if (!enabled) return;

        var banner = document.getElementById('chaos-banner');
        if (banner) {
            banner.hidden = false;
            document.getElementById('chaos-seed').textContent = seed;
        }

        document.querySelectorAll('[data-chaos-id]').forEach(function (el) {
            if (el.id) el.id = el.id + '-' + Chaos.suffix();
        });

        document.querySelectorAll('[data-chaos-shuffle]').forEach(function (tbody) {
            var rows = Array.prototype.filter.call(tbody.children, function (r) { return !r.querySelector('th'); });
            Chaos.shuffle(rows).forEach(function (r) { tbody.appendChild(r); });
        });

        document.querySelectorAll('[data-chaos-delay]').forEach(function (el) {
            el.style.visibility = 'hidden';
            Chaos.later(function () { el.style.visibility = ''; });
        });
    });
})();
