/* TicketTrail front end. Every page is a thin Blade shell; all data comes from
   the /api/tickets REST API using the token kept in localStorage (tt_token,
   tt_user), so learners can log in through the API and inject the session.

   Reusable building blocks (data table, autosave, validation, shortcuts,
   debounce/throttle) live in tickets-kit.js as window.TTKit. */
(function () {
    var TT = window.TT;
    var Kit = window.TTKit;
    var el = Kit.el;
    var append = Kit.append;
    var FLASH_KEY = 'tt.flash';
    var CATEGORY_ICON = { Conference: 'bi-mic', Concert: 'bi-music-note-beamed', Sports: 'bi-trophy', Workshop: 'bi-tools', Festival: 'bi-stars' };
    var REFRESH_COOLDOWN = 5000;

    // ---------- helpers ----------

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function go(path) { location.href = TT.base + path; }
    function lastSegment() { return decodeURIComponent(location.pathname.split('/').filter(Boolean).pop()); }
    function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }

    function money(n) {
        n = Number(n);
        return n === 0 ? 'Free' : '৳' + n.toLocaleString('en-US', { maximumFractionDigits: 2 });
    }

    function icon(name, extra) { return el('i', { 'class': 'bi ' + name + (extra ? ' ' + extra : ''), 'aria-hidden': 'true' }); }

    function dateParts(iso) {
        var d = new Date(iso);
        return {
            day: d.toLocaleString('en-US', { day: 'numeric' }),
            month: d.toLocaleString('en-US', { month: 'short' }),
            time: d.toLocaleString('en-US', { hour: 'numeric', minute: '2-digit' })
        };
    }

    function formatDate(iso, withTime) {
        var opts = { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' };
        if (withTime !== false) { opts.hour = 'numeric'; opts.minute = '2-digit'; }
        return new Date(iso).toLocaleString('en-US', opts);
    }

    /** ISO string -> value for <input type="datetime-local"> in local time. */
    function toLocalInput(iso) {
        var d = new Date(iso);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    }

    var session = {
        get token() { try { return localStorage.getItem('tt_token'); } catch (e) { return null; } },
        get user() { try { return JSON.parse(localStorage.getItem('tt_user')) || {}; } catch (e) { return {}; } },
        save: function (data) {
            try {
                localStorage.setItem('tt_token', data.token);
                localStorage.setItem('tt_user', JSON.stringify(data.user));
                if (TT.tokenMinutes) { localStorage.setItem('tt_token_expires', String(Date.now() + TT.tokenMinutes * 60000)); }
            } catch (e) { /* storage blocked */ }
        },
        clear: function () {
            try { ['tt_token', 'tt_user', 'tt_token_expires'].forEach(function (k) { localStorage.removeItem(k); }); } catch (e) { }
        }
    };

    /** Per-user localStorage key, so two accounts in one browser never share drafts or settings. */
    function userKey(name) { return 'tt.' + name + '.' + (session.user.id || session.user.email || 'anon'); }

    /**
     * toast(message, type, { title, action: { label, onClick }, duration })
     * The message text is always in .toast-message so assertions stay simple.
     */
    function toast(message, type, opts) {
        type = type || 'success';
        opts = opts || {};
        var same = $all('#toast-container .toast-' + type).filter(function (t) {
            var m = t.querySelector('.toast-message');
            return m && m.textContent === message;
        })[0];
        if (same) return same;
        var box = el('div', { 'class': 'toast-item toast-' + type, role: 'alert', 'data-testid': 'toast' });
        box.appendChild(icon({ success: 'bi-check-circle-fill', error: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' }[type]));
        var text = el('div', { 'class': 'toast-text' });
        if (opts.title) text.appendChild(el('strong', { 'class': 'toast-title', 'data-testid': 'toast-title' }, opts.title));
        text.appendChild(el('div', { 'class': 'toast-message' }, message));
        box.appendChild(text);
        if (opts.action) {
            var action = el('button', { type: 'button', 'class': 'toast-action', 'data-testid': 'toast-action' }, opts.action.label);
            action.addEventListener('click', function (e) { e.stopPropagation(); box.remove(); opts.action.onClick(); });
            box.appendChild(action);
        }
        box.addEventListener('click', function () { box.remove(); });
        $('#toast-container').appendChild(box);
        setTimeout(function () { box.remove(); }, opts.duration || (opts.action ? 7000 : 4000));
        return box;
    }

    function flash(message, type) {
        try { sessionStorage.setItem(FLASH_KEY, JSON.stringify({ message: message, type: type })); } catch (e) { }
    }

    function showFlash() {
        try {
            var f = JSON.parse(sessionStorage.getItem(FLASH_KEY));
            sessionStorage.removeItem(FLASH_KEY);
            if (f) toast(f.message, f.type);
        } catch (e) { }
    }

    function errorText(data) {
        if (data && data.details && data.details.length) return data.details[0].message;
        return (data && data.error) || 'Something went wrong';
    }

    // ---------- network & graceful recovery ----------

    var network = { online: navigator.onLine !== false, waiters: [] };
    var NETWORK_ERROR = 'Network error, please retry';
    var OFFLINE_ERROR = 'You’re offline. Your details are kept; try again once you reconnect.';

    function whenOnline() {
        return network.online ? Promise.resolve() : new Promise(function (resolve) { network.waiters.push(resolve); });
    }

    function initNetwork() {
        var banner = $('#network-banner');
        function setOnline(online) {
            var changed = network.online !== online;
            network.online = online;
            if (banner) banner.hidden = online;
            document.documentElement.classList.toggle('tt-is-offline', !online);
            if (online) {
                var waiting = network.waiters.splice(0);
                waiting.forEach(function (resolve) { resolve(); });
                if (changed) {
                    toast(waiting.length ? 'You’re back online. Loading what was waiting.' : 'You’re back online.', 'success', { title: 'Reconnected' });
                }
            }
        }
        window.addEventListener('offline', function () { setOnline(false); });
        window.addEventListener('online', function () { setOnline(true); });
        if (!network.online) setOnline(false);
    }

    function initGlobalErrors() {
        var shown = false;
        function report() {
            if (shown) return;
            shown = true;
            toast('Something went wrong on this page. Your data is safe.', 'error', {
                title: 'Unexpected error',
                action: { label: 'Reload', onClick: function () { location.reload(); } }
            });
        }
        window.addEventListener('unhandledrejection', report);
        window.addEventListener('error', function (e) {
            if (e.filename && e.filename.indexOf('/assets/js/tickets') !== -1) report();
        });
    }

    /**
     * fetch wrapper: JSON in/out, bearer token, 401 handling.
     * Each call sends exactly one request, so request counts stay
     * predictable for tests. Reads made while the browser is offline wait
     * until it reconnects; writes made offline fail straight away, so nothing
     * is ever booked twice. A failed request resolves with status 0 and
     * data.error set, and shows one toast unless opts.quiet is set.
     */
    function api(method, path, body, opts) {
        opts = opts || {};
        var isRead = method === 'GET';
        var headers = { 'Accept': 'application/json' };
        if (body) headers['Content-Type'] = 'application/json';
        if (session.token) headers['Authorization'] = 'Bearer ' + session.token;
        if (window.Chaos && Chaos.enabled) headers['X-Chaos-Mode'] = '1';

        function send() {
            return fetch(TT.api + path, { method: method, headers: headers, body: body ? JSON.stringify(body) : undefined })
                .then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function (result) {
                    if (result.status === 401 && !opts.allow401) {
                        session.clear();
                        flash('Your session has expired. Please sign in again.', 'error');
                        go('/login');
                        return new Promise(function () { });
                    }
                    return result;
                }, function () {
                    if (!opts.quiet) toast(NETWORK_ERROR, 'error');
                    return { ok: false, status: 0, data: { error: NETWORK_ERROR }, offline: !network.online };
                });
        }

        if (!network.online) {
            if (isRead) return whenOnline().then(send);
            if (!opts.quiet) toast(OFFLINE_ERROR, 'error', { title: 'Not sent' });
            return Promise.resolve({ ok: false, status: 0, data: { error: OFFLINE_ERROR }, offline: true });
        }
        return send();
    }

    function busy(button, isBusy, label) {
        if (!button) return;
        if (isBusy) {
            button.dataset.label = button.textContent;
            button.textContent = label || 'Please wait…';
        } else if (button.dataset.label) {
            button.textContent = button.dataset.label;
        }
        button.disabled = isBusy;
    }

    // ---------- forms ----------

    function clearErrors(form) {
        $all('.is-invalid', form).forEach(function (i) { i.classList.remove('is-invalid'); });
        $all('[data-error-for]', form).forEach(function (f) { f.textContent = ''; });
    }

    function setError(form, field, message) {
        var input = form.elements[field];
        if (input && input.classList) input.classList.add('is-invalid');
        var feedback = $('[data-error-for="' + field + '"]', form);
        if (feedback) feedback.textContent = message;
    }

    /** Show API validation details next to fields; returns true if any matched. */
    function showApiErrors(form, data) {
        var matched = false;
        (data.details || []).forEach(function (d) {
            if ($('[data-error-for="' + d.field + '"]', form)) { setError(form, d.field, d.message); matched = true; }
        });
        return matched;
    }

    function focusFirstError(form) {
        var bad = $('.is-invalid', form);
        if (bad && bad.focus) bad.focus();
    }

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var RULES = {
        email: function (v) { return EMAIL_RE.test(v.trim()) ? '' : 'Enter a valid email'; },
        required: function (v) { return String(v).trim() === '' ? 'This field is required' : ''; }
    };

    /** Shows the "Restore / Discard" banner for a pending autosaved draft. */
    function draftBanner(banner, saver, onRestore) {
        var draft = saver.pending();
        if (!banner || !draft) return;
        $('[data-draft-time]', banner).textContent = Kit.clock(draft.savedAt);
        banner.hidden = false;
        $('[data-draft-restore]', banner).addEventListener('click', function () {
            saver.restore();
            banner.hidden = true;
            if (onRestore) onRestore();
            toast('Draft restored');
        });
        $('[data-draft-discard]', banner).addEventListener('click', function () {
            saver.clear();
            banner.hidden = true;
        });
    }

    // ---------- shared widgets ----------

    var confirmModal;
    /**
     * Bootstrap confirm dialog.
     * opts: { title, description, confirmLabel, impact: [lines], phrase: 'TYPE ME', optimistic }
     * Without `optimistic` the dialog stays open while `action` runs; with it,
     * the dialog closes at once and `action` updates the UI before the API answers.
     */
    function confirmAction(opts, action) {
        var root = $('#confirm-dialog');
        confirmModal = confirmModal || new bootstrap.Modal(root);
        $('#confirm-dialog-title').textContent = opts.title;
        $('#confirm-dialog-description').textContent = opts.description;

        var impact = $('#confirm-dialog-impact');
        impact.innerHTML = '';
        (opts.impact || []).forEach(function (line) { impact.appendChild(el('li', {}, line)); });
        impact.hidden = !(opts.impact && opts.impact.length);

        var old = $('#confirm-dialog-yes');
        var yes = old.cloneNode(false);
        yes.textContent = opts.confirmLabel || 'Yes';
        old.replaceWith(yes);

        var phraseBox = $('#confirm-dialog-phrase');
        var phraseInput = $('#confirm-dialog-input');
        phraseBox.hidden = !opts.phrase;
        phraseInput.value = '';
        phraseInput.classList.remove('is-valid');
        if (opts.phrase) {
            $('#confirm-dialog-expected').textContent = opts.phrase;
            yes.disabled = true;
            phraseInput.oninput = function () {
                var match = phraseInput.value.trim() === opts.phrase;
                yes.disabled = !match;
                phraseInput.classList.toggle('is-valid', match);
            };
            phraseInput.onkeydown = function (e) { if (e.key === 'Enter' && !yes.disabled) yes.click(); };
        }

        root.addEventListener('shown.bs.modal', function onShown() {
            root.removeEventListener('shown.bs.modal', onShown);
            (opts.phrase ? phraseInput : $('#confirm-dialog-no')).focus();
        });

        yes.addEventListener('click', function () {
            if (opts.optimistic) {
                confirmModal.hide();
                action();
                return;
            }
            busy(yes, true, 'Working…');
            Promise.resolve(action()).then(function () { confirmModal.hide(); });
        });
        confirmModal.show();
    }

    function pager(nav, onChange) {
        var state = { page: 1, totalPages: 1 };
        $('[data-page-prev]', nav).addEventListener('click', function () { if (state.page > 1) onChange(state.page - 1); });
        $('[data-page-next]', nav).addEventListener('click', function () { if (state.page < state.totalPages) onChange(state.page + 1); });
        return function update(p) {
            state.page = p.page;
            state.totalPages = Math.max(1, p.totalPages);
            nav.hidden = p.totalPages <= 1;
            $('[data-page-label]', nav).textContent = 'Page ' + p.page + ' of ' + state.totalPages;
            $('[data-page-prev]', nav).disabled = p.page <= 1;
            $('[data-page-next]', nav).disabled = p.page >= state.totalPages;
        };
    }

    function imageFor(event) { return event.imageUrl || TT.images[event.category]; }

    function seatsText(available) {
        if (available === 0) return { text: 'SOLD OUT', cls: 'is-out' };
        if (available <= 10) return { text: available + ' seat' + (available > 1 ? 's' : '') + ' left!', cls: 'is-low' };
        return { text: available.toLocaleString('en-US') + ' seats available', cls: 'is-ok' };
    }

    function seatBar(e, small) {
        var state = seatsText(e.availableSeats).cls;
        var bar = el('div', { 'class': (small ? 'tt-mini-bar ' : 'tt-seatbar ') + state, 'aria-hidden': 'true' });
        var pct = e.totalSeats ? Math.max(e.availableSeats ? 4 : 100, Math.round(e.availableSeats / e.totalSeats * 100)) : 0;
        bar.appendChild(el('span', { style: 'width:' + pct + '%' }));
        return bar;
    }

    function categoryBadge(category) {
        var badge = el('span', { 'class': 'tt-cat-badge tt-cat tt-cat-' + category });
        append(badge, icon(CATEGORY_ICON[category] || 'bi-tag'), document.createTextNode(category));
        return badge;
    }

    function eventCard(e) {
        var soldOut = e.availableSeats === 0;
        var url = TT.base + '/events/' + e.id;
        var col = el('div', { 'class': 'col-sm-6 col-lg-4' });
        var card = el('article', { 'class': 'tt-event-card h-100' + (soldOut ? ' is-sold-out' : ''), 'data-testid': 'event-card', 'data-event-id': e.id });

        var media = el('a', { 'class': 'tt-media', href: url, tabindex: '-1', 'aria-hidden': 'true' });
        var img = el('img', { src: imageFor(e), alt: '', loading: 'lazy' });
        img.addEventListener('error', function () { img.src = TT.images[e.category]; }, { once: true });
        var top = el('div', { 'class': 'tt-media-top' });
        top.appendChild(categoryBadge(e.category));
        if (e.isStatic) {
            var featured = append(el('span', { 'class': 'tt-chip tt-chip-featured', 'data-testid': 'featured-badge' }), icon('bi-star-fill'), document.createTextNode('Featured'));
            top.appendChild(featured);
        }
        var parts = dateParts(e.eventDate);
        var stub = append(el('div', { 'class': 'tt-date-stub' }), el('b', {}, parts.day), el('small', {}, parts.month));
        append(media, img, top, stub);
        if (soldOut) media.appendChild(append(el('div', { 'class': 'tt-sold-out' }), el('span', {}, 'SOLD OUT')));

        var body = el('div', { 'class': 'tt-event-body' });
        var title = el('h3');
        title.appendChild(el('a', { href: url, 'data-testid': 'event-title' }, e.title));
        var when = append(el('div', { 'class': 'tt-meta-line' }), icon('bi-calendar-event'), el('span', {}, formatDate(e.eventDate)));
        var where = append(el('div', { 'class': 'tt-meta-line' }), icon('bi-geo-alt'), el('span', {}, e.venue + ', ' + e.city));
        var seats = seatsText(e.availableSeats);
        append(body, title, when, where, seatBar(e),
            el('div', { 'class': 'tt-seats ' + seats.cls, 'data-testid': 'event-seats' }, seats.text));

        var foot = el('div', { 'class': 'tt-event-foot' });
        var price = append(el('div'), el('div', { 'class': 'tt-price-label' }, 'Price'), el('div', { 'class': 'tt-price', 'data-testid': 'event-price' }, money(e.price)));
        var book = el('a', { 'class': 'btn btn-tt' + (soldOut ? ' disabled' : ''), href: url, 'data-testid': 'book-now-btn', 'aria-disabled': soldOut ? 'true' : 'false' }, soldOut ? 'Sold Out' : 'Book Now');
        append(foot, price, book);
        body.appendChild(foot);
        append(card, media, body);
        col.appendChild(card);
        return col;
    }

    function skelLine(width) { return el('span', { 'class': 'tt-skel-line', style: 'width:' + width + '%' }); }

    /** Card-shaped placeholders while events load. */
    function skeletons(container, n) {
        container.innerHTML = '';
        for (var i = 0; i < n; i++) {
            var card = el('div', { 'class': 'tt-skeleton', 'data-testid': 'skeleton-card', 'aria-hidden': 'true' });
            append(card, el('div', { 'class': 'tt-skel-block tt-skel-media' }),
                append(el('div', { 'class': 'tt-skel-body' }), skelLine(80), skelLine(60), skelLine(45), el('span', { 'class': 'tt-skel-line tt-skel-bar' }), append(el('div', { 'class': 'tt-skel-foot' }), skelLine(30), el('span', { 'class': 'tt-skel-pill' }))));
            container.appendChild(append(el('div', { 'class': 'col-sm-6 col-lg-4' }), card));
        }
    }

    /** Ticket-shaped placeholders while bookings load. */
    function ticketSkeletons(container, n) {
        container.innerHTML = '';
        for (var i = 0; i < n; i++) {
            container.appendChild(append(el('div', { 'class': 'tt-ticket tt-skeleton tt-skel-ticket', 'data-testid': 'skeleton-ticket', 'aria-hidden': 'true' }),
                el('div', { 'class': 'tt-skel-block tt-skel-stub' }),
                append(el('div', { 'class': 'tt-skel-body flex-grow-1' }), skelLine(25), skelLine(55), skelLine(70))));
        }
    }

    /** Builds a CSV file. Text cells that a spreadsheet would run as a formula get a leading ' (CSV injection). */
    function downloadCsv(filename, rows) {
        var csv = rows.map(function (row) {
            return row.map(function (cell) {
                var s = String(cell === null || cell === undefined ? '' : cell);
                if (typeof cell === 'string' && /^[=+\-@\t\r]/.test(s)) s = "'" + s;
                return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
            }).join(',');
        }).join('\n');
        var link = el('a', { href: URL.createObjectURL(new Blob([csv], { type: 'text/csv' })), download: filename });
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    // ---------- plan tier & usage ----------

    var plan = {
        get: function () { return Kit.store.get(userKey('plan'), 'free') === 'pro' ? 'pro' : 'free'; },
        set: function (value) { Kit.store.set(userKey('plan'), value); applyPlan(); }
    };

    function applyPlan() {
        var pro = plan.get() === 'pro';
        document.documentElement.setAttribute('data-plan', pro ? 'pro' : 'free');
        $all('#plan-badge, [data-plan-label]').forEach(function (badge) {
            badge.textContent = pro ? 'Pro' : 'Free';
            badge.setAttribute('data-plan', pro ? 'pro' : 'free');
            badge.classList.toggle('tt-plan-pro', pro);
        });
        $all('[data-pro-tag]').forEach(function (tag) { tag.hidden = pro; });
        $all('[data-pro-feature]').forEach(function (b) { b.classList.toggle('is-locked', !pro); b.setAttribute('aria-disabled', pro ? 'false' : 'true'); });
        $all('[data-upgrade-label]').forEach(function (l) { l.textContent = pro ? 'Manage plan' : 'Upgrade to Pro'; });
        var confirm = $('#upgrade-confirm');
        if (confirm) confirm.textContent = pro ? 'Switch back to Free (demo)' : 'Switch to Pro (demo)';
        $all('[data-plan-card]').forEach(function (card) { card.classList.toggle('is-current', card.getAttribute('data-plan-card') === (pro ? 'pro' : 'free')); });
    }

    var upgradeModal;
    function openUpgrade(reason) {
        var root = $('#upgrade-dialog');
        if (!root) return;
        upgradeModal = upgradeModal || new bootstrap.Modal(root);
        $('#upgrade-reason').textContent = reason || (plan.get() === 'pro' ? 'You’re on Pro. Thanks for supporting TicketTrail!' : 'You’re on the Free plan.');
        upgradeModal.show();
    }

    function initPlan() {
        applyPlan();
        $all('[data-open-upgrade]').forEach(function (b) { b.addEventListener('click', function () { openUpgrade(); }); });
        var confirm = $('#upgrade-confirm');
        if (confirm) {
            confirm.addEventListener('click', function () {
                var next = plan.get() === 'pro' ? 'free' : 'pro';
                plan.set(next);
                upgradeModal.hide();
                toast(next === 'pro' ? 'You’re on Pro (demo). CSV export and Analytics are unlocked.' : 'Switched back to the Free plan.', next === 'pro' ? 'success' : 'info');
            });
        }
        // Capture phase: a locked feature never reaches its own click handler.
        document.addEventListener('click', function (e) {
            var feature = e.target.closest && e.target.closest('[data-pro-feature]');
            if (!feature) return;
            if (plan.get() !== 'pro') {
                e.preventDefault();
                e.stopImmediatePropagation();
                openUpgrade(feature.getAttribute('data-pro-feature') + ' is a Pro feature. Upgrade to unlock it.');
            } else if (feature.getAttribute('data-pro-feature') === 'Analytics') {
                toast('Analytics is coming soon to Pro.', 'info');
            }
        }, true);
    }

    /** Usage each page already loads itself; usage.fill() fetches the rest. */
    var PAGE_USAGE = { bookings: ['bookings'], 'admin-events': ['events'], 'admin-bookings': ['bookings'] };

    var usage = {
        reported: {},
        report: function (kind, used) {
            usage.reported[kind] = true;
            var max = TT.limits[kind];
            var pct = Math.min(100, Math.round(used / max * 100));
            var state = used >= max ? 'full' : pct >= 75 ? 'warn' : 'ok';
            $all('[data-usage="' + kind + '"]').forEach(function (meter) {
                meter.setAttribute('data-state', state);
                $('[data-usage-text]', meter).textContent = used + ' / ' + max;
                $('.tt-meter-bar > span', meter).style.width = pct + '%';
            });
            var count = $('#side-count-' + kind);
            if (count) count.textContent = used;
            usage.note();
        },
        note: function () {
            var note = $('[data-usage-note]');
            if (!note) return;
            var full = $all('.tt-meter[data-state="full"]').map(function (m) { return m.getAttribute('data-usage'); });
            var warn = $all('.tt-meter[data-state="warn"]').length;
            note.textContent = full.length
                ? 'Limit reached: your next ' + (full[0] === 'bookings' ? 'booking' : 'event') + ' replaces the oldest one.'
                : warn ? 'You’re close to a limit.' : 'Plenty of room in your sandbox.';
            note.setAttribute('data-state', full.length ? 'full' : warn ? 'warn' : 'ok');
        },
        /** Fetch whatever the current page did not already report. */
        fill: function () {
            if (!$('[data-usage]')) return;
            var own = PAGE_USAGE[TT.page] || [];
            if (own.indexOf('bookings') === -1) {
                api('GET', '/bookings?limit=1', null, { quiet: true }).then(function (r) { if (r.ok && !usage.reported.bookings) usage.report('bookings', r.data.pagination.total); });
            }
            if (own.indexOf('events') === -1) {
                api('GET', '/events?limit=100', null, { quiet: true }).then(function (r) {
                    if (r.ok && !usage.reported.events) usage.report('events', r.data.data.filter(function (e) { return !e.isStatic; }).length);
                });
            }
        }
    };

    // ---------- navigation chrome ----------

    /** Personal details saved in this browser, removed on logout (shared machines). */
    var PERSONAL_KEYS = ['profile', 'draft.booking', 'draft.event', 'invites'];

    function logout() {
        PERSONAL_KEYS.forEach(function (name) {
            try { localStorage.removeItem(userKey(name)); } catch (e) { }
        });
        session.clear();
        flash('You have been logged out', 'info');
        go('/login');
    }

    function initNav() {
        var email = $('#user-email-display');
        if (email) email.textContent = session.user.email || '';
        var avatar = $('#user-avatar');
        if (avatar) avatar.textContent = (session.user.email || '?').charAt(0);
        var active = { home: 'home', events: 'events', 'event-details': 'events', bookings: 'bookings', 'booking-details': 'bookings', 'admin-events': 'admin', 'admin-bookings': 'admin', referrals: 'referrals' }[TT.page];
        $all('[data-nav]').forEach(function (a) {
            if (a.getAttribute('data-nav') === active) { a.classList.add('active'); a.setAttribute('aria-current', 'page'); }
        });
        $all('[data-tab]').forEach(function (a) {
            var tab = a.getAttribute('data-tab');
            if (tab === active || (tab === 'admin' && active === 'referrals')) { a.classList.add('active'); if (a.tagName === 'A') a.setAttribute('aria-current', 'page'); }
        });
        var logoutBtn = $('#logout-btn');
        if (logoutBtn) logoutBtn.addEventListener('click', logout);
        $all('[data-logout]').forEach(function (b) { b.addEventListener('click', logout); });

        if (/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) {
            $all('[data-mod-key]').forEach(function (k) { k.textContent = '⌘ K'; });
        }
    }

    function initSidebar() {
        var side = $('#tt-sidebar');
        if (!side) return;
        $all('[data-side]', side).forEach(function (a) {
            if (a.getAttribute('data-side') === TT.page) { a.classList.add('active'); a.setAttribute('aria-current', 'page'); }
        });
        var toggle = $('#sidebar-toggle');
        function sync() {
            var collapsed = document.documentElement.classList.contains('tt-sidebar-collapsed');
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.title = (collapsed ? 'Expand' : 'Collapse') + ' sidebar ( [ )';
            $('.tt-side-label', toggle).textContent = collapsed ? 'Expand' : 'Collapse';
        }
        toggle.addEventListener('click', toggleSidebar);
        document.addEventListener('tt:sidebar', sync);
        sync();
    }

    function toggleSidebar() {
        if (!$('#tt-sidebar')) return;
        var collapsed = document.documentElement.classList.toggle('tt-sidebar-collapsed');
        Kit.store.set('tt.sidebar.collapsed', collapsed);
        document.dispatchEvent(new Event('tt:sidebar'));
    }

    function initBackToTop() {
        var button = $('#back-to-top');
        if (!button) return;
        var nav = $('#tt-navbar');
        // Scroll fires dozens of times a second; a throttle keeps this cheap.
        function update() {
            button.hidden = window.scrollY < 600;
            if (nav) nav.classList.toggle('is-scrolled', window.scrollY > 8);
        }
        var onScroll = Kit.throttle(update, 150);
        var trailing;
        window.addEventListener('scroll', function () {
            onScroll();
            // One trailing update so the final scroll position is never missed.
            clearTimeout(trailing);
            trailing = setTimeout(update, 200);
        }, { passive: true });
        button.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    }

    // ---------- command palette & shortcuts ----------

    var paletteModal;
    var shortcutsModal;

    function openShortcuts() {
        var root = $('#shortcuts-dialog');
        if (!root) return;
        shortcutsModal = shortcutsModal || new bootstrap.Modal(root);
        shortcutsModal.show();
    }

    function openPalette() {
        var root = $('#command-palette');
        if (!root) return;
        paletteModal = paletteModal || new bootstrap.Modal(root);
        paletteModal.show();
    }

    function togglePalette() {
        var root = $('#command-palette');
        if (root && root.classList.contains('show')) paletteModal.hide();
        else openPalette();
    }

    function initPalette() {
        var root = $('#command-palette');
        if (!root) return;
        var input = $('#palette-input');
        var list = $('#palette-results');
        var spinner = $('#palette-spinner');
        var items = [];
        var activeIndex = 0;
        var eventResults = [];
        var searchSeq = 0;

        var commands = [
            { group: 'Pages', label: 'Home', icon: 'bi-house', keys: 'g h', run: function () { go(''); } },
            { group: 'Pages', label: 'Events', icon: 'bi-calendar-event', keys: 'g e', run: function () { go('/events'); } },
            { group: 'Pages', label: 'My Bookings', icon: 'bi-ticket-perforated', keys: 'g b', run: function () { go('/bookings'); } },
            { group: 'Pages', label: 'Manage Events', icon: 'bi-calendar2-plus', keys: 'g m', run: function () { go('/admin/events'); } },
            { group: 'Pages', label: 'Manage Bookings', icon: 'bi-receipt', keys: 'g o', run: function () { go('/admin/bookings'); } },
            { group: 'Pages', label: 'Refer & Earn', icon: 'bi-gift', keys: 'g r', run: function () { go('/referrals'); } },
            { group: 'Pages', label: 'API Docs (Swagger)', icon: 'bi-braces', run: function () { window.open(TT.base + '/api-docs', '_blank', 'noopener'); } },
            { group: 'Actions', label: 'Create a new event', icon: 'bi-plus-lg', keys: 'n', run: function () { go('/admin/events?new=1'); } },
            { group: 'Actions', label: 'Edit my preferences', icon: 'bi-person-gear', run: function () { openOnboarding(); } },
            { group: 'Actions', label: 'Compare plans', icon: 'bi-rocket-takeoff', run: function () { openUpgrade(); } },
            { group: 'Actions', label: 'Keyboard shortcuts', icon: 'bi-keyboard', keys: '?', run: openShortcuts },
            { group: 'Actions', label: 'Log out', icon: 'bi-box-arrow-right', run: logout }
        ];
        TT.categories = Object.keys(CATEGORY_ICON);
        TT.categories.forEach(function (c) {
            commands.push({ group: 'Categories', label: c + ' events', icon: CATEGORY_ICON[c], run: function () { go('/events?category=' + encodeURIComponent(c)); } });
        });

        function matches(label, query) {
            var hay = label.toLowerCase();
            return query.toLowerCase().split(/\s+/).filter(Boolean).every(function (word) { return hay.indexOf(word) !== -1; });
        }

        function render() {
            var query = input.value.trim();
            items = commands.filter(function (c) { return !query || matches(c.label, query); });
            if (!query) items = items.filter(function (c) { return c.group !== 'Categories'; });
            items = eventResults.concat(items);
            activeIndex = Math.min(activeIndex, Math.max(0, items.length - 1));
            list.innerHTML = '';
            var lastGroup = null;
            items.forEach(function (item, i) {
                if (item.group !== lastGroup) {
                    list.appendChild(el('li', { 'class': 'tt-palette-group', role: 'presentation' }, item.group));
                    lastGroup = item.group;
                }
                var li = el('li', { id: 'palette-opt-' + i, role: 'option', 'class': 'tt-palette-item', 'data-testid': 'palette-item', 'aria-selected': i === activeIndex ? 'true' : 'false' });
                append(li, icon(item.icon), append(el('span', { 'class': 'tt-palette-label' }, item.label), item.sub ? el('small', {}, item.sub) : null));
                if (item.keys) li.appendChild(el('kbd', {}, item.keys));
                li.addEventListener('mousemove', function () { if (activeIndex !== i) { activeIndex = i; highlight(); } });
                li.addEventListener('click', function () { run(i); });
                list.appendChild(li);
            });
            if (!items.length) {
                list.appendChild(el('li', { 'class': 'tt-palette-empty', 'data-testid': 'palette-empty' }, query.length >= 2 && spinner.hidden ? 'No results for “' + query + '”' : 'Keep typing…'));
            }
            highlight();
        }

        function highlight() {
            $all('.tt-palette-item', list).forEach(function (li, i) { li.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false'); });
            var active = $('#palette-opt-' + activeIndex);
            input.setAttribute('aria-activedescendant', active ? active.id : '');
            if (active) active.scrollIntoView({ block: 'nearest' });
        }

        function run(i) {
            var item = items[i];
            if (!item) return;
            paletteModal.hide();
            item.run();
        }

        // Debounced: one API search after the user pauses, not one per key.
        var searchEvents = Kit.debounce(function (query) {
            var seq = ++searchSeq;
            api('GET', '/events?limit=5&search=' + encodeURIComponent(query), null, { quiet: true }).then(function (r) {
                if (seq !== searchSeq) return;
                spinner.hidden = true;
                eventResults = r.ok ? r.data.data.map(function (e) {
                    return { group: 'Events', label: e.title, sub: formatDate(e.eventDate, false) + ' · ' + e.city, icon: CATEGORY_ICON[e.category] || 'bi-calendar-event', run: function () { go('/events/' + e.id); } };
                }) : [];
                render();
            });
        }, 250);

        input.addEventListener('input', function () {
            var query = input.value.trim();
            activeIndex = 0;
            eventResults = [];
            searchSeq++;
            if (query.length >= 2) { spinner.hidden = false; searchEvents(query); } else { spinner.hidden = true; searchEvents.cancel(); }
            render();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = (activeIndex + 1) % Math.max(1, items.length); highlight(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = (activeIndex - 1 + items.length) % Math.max(1, items.length); highlight(); }
            else if (e.key === 'Enter') { e.preventDefault(); run(activeIndex); }
        });
        root.addEventListener('show.bs.modal', function () { input.value = ''; eventResults = []; activeIndex = 0; spinner.hidden = true; render(); });
        root.addEventListener('shown.bs.modal', function () { input.focus(); });
        $all('[data-open-palette]').forEach(function (b) { b.addEventListener('click', openPalette); });
        $all('[data-open-shortcuts]').forEach(function (b) { b.addEventListener('click', openShortcuts); });
    }

    /** Throttled "Refresh" buttons, keyed by page, so the r shortcut can reach them. */
    var refreshers = {};

    function initShortcuts() {
        if (!$('#command-palette')) return;
        Kit.shortcuts({
            'mod+k': togglePalette,
            '/': function () {
                var search = $('#search');
                if (TT.page === 'events' && search) { search.focus(); search.select(); } else openPalette();
            },
            '?': openShortcuts,
            'g h': function () { go(''); },
            'g e': function () { go('/events'); },
            'g b': function () { go('/bookings'); },
            'g m': function () { go('/admin/events'); },
            'g o': function () { go('/admin/bookings'); },
            'g r': function () { go('/referrals'); },
            'n': function () {
                if (TT.page === 'admin-events') { $('#admin-event-form').scrollIntoView({ behavior: 'smooth', block: 'start' }); $('#title').focus(); } else go('/admin/events?new=1');
            },
            'r': function () { if (refreshers[TT.page]) refreshers[TT.page].trigger(); },
            '[': toggleSidebar
        });
    }

    // ---------- onboarding ----------

    var profile = {
        get: function () { return Kit.store.get(userKey('profile'), {}) || {}; },
        save: function (data) { Kit.store.set(userKey('profile'), data); document.dispatchEvent(new Event('tt:profile')); }
    };

    var onboardingModal;
    function openOnboarding() {
        var root = $('#onboarding-dialog');
        if (!root) return;
        onboardingModal = onboardingModal || new bootstrap.Modal(root);
        root.dispatchEvent(new Event('tt:reset'));
        onboardingModal.show();
    }

    function initOnboarding() {
        var root = $('#onboarding-dialog');
        if (!root) return;
        var form = $('#onboarding-form');
        var step = 1;
        var STEPS = 3;
        var checks = {
            1: function () {
                var ok = true;
                var name = form.elements.name.value.trim();
                var phone = form.elements.phone.value.trim();
                if (name.length < 2) { setError(form, 'name', 'Enter your name (at least 2 characters)'); ok = false; }
                if (phone && phone.replace(/\D/g, '').length < 10) { setError(form, 'phone', 'Enter a valid 10-digit phone or leave it empty'); ok = false; }
                return ok;
            },
            2: function () {
                if (form.elements.city.value.trim().length < 2) { setError(form, 'city', 'Enter your city'); return false; }
                return true;
            },
            3: function () {
                if (!$all('input[name="interests"]:checked', form).length) { setError(form, 'interests', 'Pick at least one interest'); return false; }
                return true;
            }
        };

        function show(n) {
            step = n;
            $all('[data-step]', form).forEach(function (s) { s.hidden = Number(s.getAttribute('data-step')) !== n; });
            $('#onboarding-step-label').textContent = 'Step ' + n + ' of ' + STEPS;
            $('#onboarding-progress').style.width = Math.round(n / STEPS * 100) + '%';
            $('#onboarding-back').hidden = n === 1;
            $('#onboarding-next').textContent = n === STEPS ? 'Finish' : 'Next';
            $('#onboarding-title').textContent = ['Let’s personalise TicketTrail', 'Where are you based?', 'What do you enjoy?'][n - 1];
        }

        function syncChips() {
            $all('input[name="interests"]', form).forEach(function (box) { box.closest('label').classList.toggle('is-active', box.checked); });
        }

        root.addEventListener('tt:reset', function () {
            var p = profile.get();
            clearErrors(form);
            form.elements.name.value = p.name || '';
            form.elements.phone.value = p.phone || '';
            form.elements.city.value = p.city || '';
            $all('input[name="interests"]', form).forEach(function (box) { box.checked = (p.interests || []).indexOf(box.value) !== -1; });
            syncChips();
            show(1);
        });
        root.addEventListener('shown.bs.modal', function () { var first = $('[data-step="' + step + '"] input', form); if (first) first.focus(); });
        form.addEventListener('change', function (e) { if (e.target.name === 'interests') { syncChips(); clearErrors(form); } });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors(form);
            if (!checks[step]()) { focusFirstError(form); return; }
            if (step < STEPS) { show(step + 1); $('[data-step="' + step + '"] input', form).focus(); return; }
            profile.save({
                name: form.elements.name.value.trim(),
                phone: form.elements.phone.value.trim(),
                city: form.elements.city.value.trim(),
                interests: $all('input[name="interests"]:checked', form).map(function (b) { return b.value; }),
                completedAt: Date.now()
            });
            onboardingModal.hide();
            toast('Preferences saved. We’ll use them to pick events for you.');
        });
        $('#onboarding-back').addEventListener('click', function () { clearErrors(form); show(Math.max(1, step - 1)); });
        $('#onboarding-skip').addEventListener('click', function () { onboardingModal.hide(); });
        $all('[data-open-onboarding]').forEach(function (b) { b.addEventListener('click', openOnboarding); });
    }

    var pages = {};

    // ---------- auth ----------

    pages.login = function () {
        var form = $('#login-form');
        var alertBox = $('#login-error');
        Kit.smartValidation(form, {
            email: RULES.email,
            password: function (v) { return v ? '' : 'Password is required'; }
        }, { emailFields: ['email'] });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors(form);
            alertBox.hidden = true;
            var email = form.elements.email.value.trim();
            var password = form.elements.password.value;
            var ok = true;
            if (!EMAIL_RE.test(email)) { setError(form, 'email', 'Enter a valid email'); ok = false; }
            if (!password) { setError(form, 'password', 'Password is required'); ok = false; }
            if (!ok) return focusFirstError(form);

            var btn = $('#login-btn');
            busy(btn, true, 'Signing in…');
            api('POST', '/auth/login', { email: email, password: password }, { allow401: true }).then(function (r) {
                busy(btn, false);
                if (!r.ok) {
                    if (r.status === 0) return;
                    alertBox.textContent = r.status === 404
                        ? 'No account found for this email. Accounts are deleted ' + TT.accountDays + ' days after registration, so please register again.'
                        : errorText(r.data);
                    alertBox.hidden = false;
                    return;
                }
                session.save(r.data);
                flash('Welcome back!');
                go('');
            });
        });
    };

    var REFERRAL_RE = /^TT[0-9A-Z]{4,10}$/;

    pages.register = function () {
        var form = $('#register-form');
        var alertBox = $('#register-error');
        var rules = {
            length: function (v) { return v.length >= 8; },
            upper: function (v) { return /[A-Z]/.test(v); },
            number: function (v) { return /\d/.test(v); },
            symbol: function (v) { return /[^A-Za-z0-9]/.test(v); }
        };
        var PASSWORD_MESSAGE = 'Password must be at least 8 characters with an uppercase letter, a number and a symbol';

        var ref = (new URLSearchParams(location.search).get('ref') || '').trim().toUpperCase();
        var banner = $('#referral-banner');
        if (ref && banner) {
            var valid = REFERRAL_RE.test(ref);
            banner.innerHTML = '';
            banner.setAttribute('data-valid', valid ? 'true' : 'false');
            append(banner, icon(valid ? 'bi-gift-fill' : 'bi-exclamation-triangle-fill'),
                el('span', {}, valid ? 'Invited by a friend: code ' + ref + ' applied. You’ll both get ৳200 credit.' : 'The referral code “' + ref + '” doesn’t look right. You can still register.'));
            banner.hidden = false;
        }

        form.elements.password.addEventListener('input', function () {
            var v = form.elements.password.value;
            $all('[data-rule]', form).forEach(function (li) { li.classList.toggle('is-met', rules[li.getAttribute('data-rule')](v)); });
        });

        Kit.smartValidation(form, {
            email: RULES.email,
            password: function (v) { return Object.keys(rules).every(function (k) { return rules[k](v); }) ? '' : PASSWORD_MESSAGE; },
            confirmPassword: function (v, f) { return v && v === f.elements.password.value ? '' : 'Passwords do not match'; }
        }, { dependents: { password: ['confirmPassword'] }, emailFields: ['email'] });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors(form);
            alertBox.hidden = true;
            var email = form.elements.email.value.trim();
            var password = form.elements.password.value;
            var ok = true;
            if (!EMAIL_RE.test(email)) { setError(form, 'email', 'Enter a valid email'); ok = false; }
            var failed = Object.keys(rules).filter(function (k) { return !rules[k](password); });
            if (failed.length) { setError(form, 'password', PASSWORD_MESSAGE); ok = false; }
            if (password !== form.elements.confirmPassword.value) { setError(form, 'confirmPassword', 'Passwords do not match'); ok = false; }
            if (!ok) return focusFirstError(form);

            var btn = $('#register-btn');
            busy(btn, true, 'Creating account…');
            api('POST', '/auth/register', { email: email, password: password }).then(function (r) {
                busy(btn, false);
                if (!r.ok) {
                    if (r.status === 0) return;
                    if (!showApiErrors(form, r.data)) { alertBox.textContent = errorText(r.data); alertBox.hidden = false; }
                    return;
                }
                session.save(r.data);
                if (ref && REFERRAL_RE.test(ref)) Kit.store.set(userKey('referredBy'), ref);
                flash('Account created! Welcome to TicketTrail.');
                go('');
            });
        });
    };

    // ---------- home & events ----------

    pages.home = function () {
        var grid = $('#featured-events');
        var events = [];
        var bookingsTotal = null;
        skeletons(grid, 3);
        $('.tt-stats').classList.add('is-loading');

        function renderOnboarding() {
            var card = $('#onboarding-card');
            var p = profile.get();
            var done = {
                profile: !!p.name,
                city: !!p.city,
                interests: !!(p.interests && p.interests.length),
                booking: bookingsTotal > 0 || Kit.store.get(userKey('booked'), false)
            };
            var count = Object.keys(done).filter(function (k) { return done[k]; }).length;
            var pct = Math.round(count / 4 * 100);
            $all('[data-check]', card).forEach(function (li) { li.classList.toggle('is-done', done[li.getAttribute('data-check')]); });
            $('#onboarding-percent').textContent = pct + '%';
            $('#onboarding-ring').style.strokeDasharray = pct + ' 100';
            card.hidden = count === 4 || Kit.store.get(userKey('onboardingDismissed'), false);
        }

        function renderForYou() {
            var p = profile.get();
            var section = $('#for-you-section');
            var interests = p.interests || [];
            var city = (p.city || '').toLowerCase();
            if (!interests.length && !city) { section.hidden = true; return; }
            var picks = events.filter(function (e) {
                return e.availableSeats > 0 && (interests.indexOf(e.category) !== -1 || e.city.toLowerCase() === city);
            }).sort(function (a, b) {
                return (b.city.toLowerCase() === city) - (a.city.toLowerCase() === city) || new Date(a.eventDate) - new Date(b.eventDate);
            }).slice(0, 3);
            var box = $('#for-you-events');
            box.innerHTML = '';
            picks.forEach(function (e) { box.appendChild(eventCard(e)); });
            if (!picks.length) {
                box.appendChild(append(el('div', { 'class': 'col-12' }), append(el('div', { 'class': 'tt-card tt-empty py-4' }),
                    el('p', { 'class': 'text-secondary mb-0' }, 'Nothing matches your preferences right now. Try adding more interests.'))));
            }
            var reasons = [];
            if (interests.length) reasons.push('you like ' + interests.join(', '));
            if (p.city) reasons.push('you’re in ' + p.city);
            $('#for-you-reason').textContent = 'Because ' + reasons.join(' and ') + '.';
            section.hidden = false;
        }

        $('#onboarding-dismiss').addEventListener('click', function () {
            Kit.store.set(userKey('onboardingDismissed'), true);
            $('#onboarding-card').hidden = true;
            toast('Setup hidden. Reopen it any time from the command palette.', 'info');
        });
        document.addEventListener('tt:profile', function () { renderOnboarding(); renderForYou(); });

        api('GET', '/events?limit=100').then(function (r) {
            grid.innerHTML = '';
            grid.removeAttribute('aria-busy');
            $('.tt-stats').classList.remove('is-loading');
            if (!r.ok) {
                grid.appendChild(append(el('div', { 'class': 'col-12' }), append(el('div', { 'class': 'tt-empty', 'data-testid': 'featured-error' }),
                    append(el('div', { 'class': 'tt-empty-icon', style: 'background:#fdecec;color:#e5484d' }), icon('bi-wifi-off')),
                    el('h3', { 'class': 'h6' }, 'Couldn’t load featured events'),
                    append(el('button', { 'class': 'btn btn-sm btn-outline-secondary', type: 'button' }, 'Retry')))));
                $('[data-testid="featured-error"] button', grid).addEventListener('click', function () { pages.home.reload(); });
                return toast(errorText(r.data), 'error');
            }
            events = r.data.data;
            events.filter(function (e) { return e.isStatic; }).slice(0, 3).forEach(function (e) { grid.appendChild(eventCard(e)); });

            var cities = {};
            var seats = 0;
            events.forEach(function (e) { cities[e.city.toLowerCase()] = true; seats += e.availableSeats; });
            $('#stat-events').textContent = r.data.pagination.total;
            $('#stat-cities').textContent = Object.keys(cities).length;
            $('#stat-seats').textContent = seats.toLocaleString('en-US');
            $all('[data-category]').forEach(function (tile) {
                var n = events.filter(function (e) { return e.category === tile.getAttribute('data-category'); }).length;
                $('[data-category-count]', tile).textContent = n + ' event' + (n === 1 ? '' : 's');
            });
            renderForYou();
        });
        api('GET', '/bookings?limit=1').then(function (r) {
            if (r.ok) {
                bookingsTotal = r.data.pagination.total;
                $('#stat-bookings').textContent = bookingsTotal;
            }
            renderOnboarding();
        });
    };
    pages.home.reload = function () { location.reload(); };

    var EVENT_FILTERS = ['search', 'category', 'city', 'minPrice', 'maxPrice', 'from', 'to', 'sort', 'availableOnly'];
    var FILTER_LABELS = { search: 'Search', category: 'Category', city: 'City', minPrice: 'Min', maxPrice: 'Max', from: 'From', to: 'To', sort: 'Sort', availableOnly: 'Seats' };

    pages.events = function () {
        var form = $('#event-filters');
        var grid = $('#events-grid');
        var status = $('#search-status');
        var params = new URLSearchParams(location.search);
        var requestSeq = 0;

        function fieldValue(name) {
            var input = form.elements[name];
            if (input.type === 'checkbox') return input.checked ? '1' : '';
            return input.value.trim();
        }

        EVENT_FILTERS.forEach(function (k) {
            var v = params.get(k);
            if (!v) return;
            if (form.elements[k].type === 'checkbox') form.elements[k].checked = v === '1' || v === 'true';
            else form.elements[k].value = v;
        });
        if (['minPrice', 'maxPrice', 'from', 'to', 'sort', 'availableOnly'].some(function (k) { return params.get(k); })) {
            $('#advanced-filters').classList.add('show');
            $('#advanced-filters-btn').setAttribute('aria-expanded', 'true');
        }
        var updatePager = pager($('#events-pager'), load);

        function setStatus(state, text) {
            status.setAttribute('data-state', state);
            status.textContent = text;
        }

        /** Client-side check before calling the API with contradictory filters. */
        function filterProblem() {
            var min = fieldValue('minPrice');
            var max = fieldValue('maxPrice');
            if (min !== '' && max !== '' && Number(max) < Number(min)) return 'Max price must be at least the min price';
            if (fieldValue('from') && fieldValue('to') && fieldValue('to') < fieldValue('from')) return '“To” date must be on or after the “From” date';
            return '';
        }

        function renderActiveFilters() {
            var box = $('#active-filters');
            box.innerHTML = '';
            var active = EVENT_FILTERS.filter(function (k) { return fieldValue(k) !== ''; });
            active.forEach(function (k) {
                var input = form.elements[k];
                var value = input.tagName === 'SELECT' ? input.options[input.selectedIndex].text : k === 'availableOnly' ? 'Hide sold out' : fieldValue(k);
                if (k === 'minPrice' || k === 'maxPrice') value = '৳' + Number(fieldValue(k)).toLocaleString('en-US');
                var chip = el('button', { type: 'button', 'class': 'tt-filter-chip', 'data-testid': 'active-filter-chip', 'data-filter': k, 'aria-label': 'Remove filter ' + FILTER_LABELS[k] + ': ' + value });
                append(chip, el('span', { 'class': 'text-secondary' }, FILTER_LABELS[k] + ':'), document.createTextNode(' ' + value + ' '), icon('bi-x-lg'));
                chip.addEventListener('click', function () {
                    if (input.type === 'checkbox') input.checked = false; else input.value = '';
                    syncChips();
                    load(1);
                });
                box.appendChild(chip);
            });
            if (active.length > 1) {
                var all = el('button', { type: 'button', 'class': 'btn btn-link btn-sm p-0 fw-semibold', 'data-testid': 'clear-all-chips' }, 'Clear all');
                all.addEventListener('click', function () { form.reset(); });
                box.appendChild(all);
            }
            box.hidden = !active.length;
        }

        function load(page) {
            page = page || 1;
            search.cancel();
            var problem = filterProblem();
            $('#filters-error').textContent = problem;
            renderActiveFilters();
            if (problem) { setStatus('error', ''); return; }

            var query = new URLSearchParams({ page: page, limit: 9 });
            EVENT_FILTERS.forEach(function (k) { var v = fieldValue(k); if (v) query.set(k, v); });
            var shown = new URLSearchParams(query);
            shown.delete('limit');
            if (page === 1) shown.delete('page');
            history.replaceState(null, '', location.pathname + (shown.toString() ? '?' + shown : ''));
            // The address bar keeps plain dates; the API gets the visitor's own day as exact instants.
            if (query.get('from')) query.set('from', new Date(query.get('from') + 'T00:00:00').toISOString());
            if (query.get('to')) query.set('to', new Date(query.get('to') + 'T23:59:59.999').toISOString());

            var seq = ++requestSeq;
            skeletons(grid, 3);
            grid.setAttribute('aria-busy', 'true');
            $('#events-error').hidden = true;
            $('#events-empty').hidden = true;
            setStatus('loading', 'Searching…');
            api('GET', '/events?' + query).then(function (r) {
                // Ignore answers to requests the user has already moved past.
                if (seq !== requestSeq) return;
                grid.innerHTML = '';
                grid.removeAttribute('aria-busy');
                if (!r.ok) {
                    setStatus('error', '');
                    if (r.status === 400) { $('#filters-error').textContent = errorText(r.data); $('#events-pager').hidden = true; return; }
                    $('#events-error').hidden = false;
                    $('#events-pager').hidden = true;
                    return;
                }
                var p = r.data.pagination;
                r.data.data.forEach(function (e) { grid.appendChild(eventCard(e)); });
                $('#events-empty').hidden = p.total > 0;
                $('#events-count').textContent = p.total + ' event' + (p.total === 1 ? '' : 's') + ' found';
                setStatus('done', fieldValue('search') ? 'Showing results for “' + fieldValue('search') + '”' : '');
                updatePager(p);
            });
        }

        // Debounced: typing fires one request after a 400 ms pause, not one per key.
        var search = Kit.debounce(function () { load(1); }, 400);

        function syncChips() {
            $all('[data-chip]').forEach(function (chip) {
                var on = chip.getAttribute('data-chip') === form.elements.category.value;
                chip.classList.toggle('is-active', on);
                chip.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }
        $all('[data-chip]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                form.elements.category.value = chip.getAttribute('data-chip');
                syncChips();
                load(1);
            });
        });
        syncChips();

        function isInstant(target) { return target.tagName === 'SELECT' || target.type === 'checkbox' || target.type === 'date'; }

        form.addEventListener('input', function (e) {
            if (isInstant(e.target)) return;
            syncChips();
            setStatus('waiting', 'Waiting for you to stop typing…');
            search();
        });
        form.addEventListener('change', function (e) {
            if (!isInstant(e.target)) return;
            syncChips();
            load(1);
        });
        form.addEventListener('reset', function () { setTimeout(function () { syncChips(); load(1); }, 0); });
        form.addEventListener('submit', function (e) { e.preventDefault(); load(1); });
        $('#events-error [data-retry]').addEventListener('click', function () { load(1); });
        $('#empty-clear-filters').addEventListener('click', function () { form.reset(); });
        load(parseInt(params.get('page'), 10) || 1);
    };

    pages['event-details'] = function () {
        var id = lastSegment();
        var form = $('#booking-form');
        var event;
        var quantity = 1;

        function maxQty() { return Math.min(TT.limits.perBooking, event.availableSeats); }

        function renderSeats() {
            var seats = seatsText(event.availableSeats);
            $('#event-seats').textContent = seats.text;
            $('#event-seats').className = 'tt-seats ' + seats.cls;
            var bar = seatBar(event);
            bar.id = 'event-seatbar';
            bar.classList.add('mt-4');
            $('#event-seatbar').replaceWith(bar);
            $('#event-capacity').textContent = event.availableSeats.toLocaleString('en-US') + ' of ' + event.totalSeats.toLocaleString('en-US') + ' seats left';
            var soldOut = event.availableSeats === 0;
            $('#sold-out-msg').hidden = !soldOut;
            form.hidden = soldOut;
            $('#mobile-book-btn').textContent = soldOut ? 'Sold out' : 'Book tickets';
            $('#mobile-book-btn').classList.toggle('disabled', soldOut);
        }

        function renderQuantity() {
            var max = maxQty();
            $('#ticket-count').textContent = quantity;
            $('#qty-minus').disabled = quantity <= 1;
            $('#qty-plus').disabled = quantity >= max;
            $('#qty-max').textContent = '(max ' + max + ')';
            $('#total-breakdown').textContent = money(event.price) + ' × ' + quantity + ' ticket' + (quantity > 1 ? 's' : '');
            $('#total-subtotal').textContent = money(event.price * quantity);
            $('#booking-total').textContent = money(event.price * quantity);
        }

        var validator = Kit.smartValidation(form, {
            customerName: function (v) { return v.trim().length < 2 ? 'Enter your full name' : ''; },
            customerEmail: RULES.email,
            customerPhone: function (v) { return v.replace(/\D/g, '').length < 10 ? 'Enter a valid 10-digit phone' : ''; }
        }, { emailFields: ['customerEmail'] });

        var saver = Kit.autosave(form, { key: userKey('draft.booking'), fields: ['customerName', 'customerPhone'], status: $('#booking-autosave') });

        function load() {
            $('#event-skeleton').hidden = false;
            $('#event-load-error').hidden = true;
            api('GET', '/events/' + encodeURIComponent(id)).then(function (r) {
                $('#event-skeleton').hidden = true;
                if (!r.ok) {
                    if (r.status === 0 || r.status >= 500) { $('#event-load-error').hidden = false; return; }
                    $('#event-not-found').hidden = false;
                    $('#crumb-title').textContent = 'Not found';
                    return;
                }
                event = r.data.data;
                document.title = event.title + ' · TicketTrail';
                $('#crumb-title').textContent = event.title;
                $('#event-image').src = imageFor(event);
                $('#event-image').addEventListener('error', function () { $('#event-image').src = TT.images[event.category]; }, { once: true });
                $('#event-category').replaceWith(Object.assign(categoryBadge(event.category), { id: 'event-category' }));
                $('#event-category').setAttribute('data-testid', 'event-category');
                $('#tile-time').textContent = dateParts(event.eventDate).time;
                $('#tile-city').textContent = event.city;
                $('#event-featured').hidden = !event.isStatic;
                $('#event-title').textContent = event.title;
                $('#event-date').textContent = formatDate(event.eventDate);
                $('#event-venue').textContent = event.venue;
                $('#event-city').textContent = event.city;
                $('#event-description').textContent = event.description || 'No description provided.';
                $('#event-price').textContent = money(event.price);
                $('#mobile-price').textContent = money(event.price);
                form.elements.customerEmail.value = session.user.email || '';
                renderSeats();
                renderQuantity();
                $('#event-detail').hidden = false;
                draftBanner($('#booking-draft'), saver);
                var p = profile.get();
                $('#use-profile-btn').hidden = !p.name;
            });
        }

        $('#event-load-error [data-retry]').addEventListener('click', load);
        $('#use-profile-btn').addEventListener('click', function () {
            var p = profile.get();
            form.elements.customerName.value = p.name || '';
            if (p.phone) form.elements.customerPhone.value = p.phone;
            validator.check('customerName');
            if (p.phone) validator.check('customerPhone');
            toast('Filled in from your saved details', 'info');
        });
        $('#mobile-book-btn').addEventListener('click', function (e) {
            e.preventDefault();
            if (event && event.availableSeats === 0) return;
            $('#booking-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(function () { form.elements.customerName.focus({ preventScroll: true }); }, 400);
        });

        $('#qty-minus').addEventListener('click', function () { quantity = Math.max(1, quantity - 1); renderQuantity(); });
        $('#qty-plus').addEventListener('click', function () { quantity = Math.min(maxQty(), quantity + 1); renderQuantity(); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors(form);
            var data = {
                eventId: event.id,
                customerName: form.elements.customerName.value.trim(),
                customerEmail: form.elements.customerEmail.value.trim(),
                customerPhone: form.elements.customerPhone.value.trim(),
                quantity: quantity
            };
            var ok = true;
            if (data.customerName.length < 2) { setError(form, 'customerName', 'Enter your full name'); ok = false; }
            if (!EMAIL_RE.test(data.customerEmail)) { setError(form, 'customerEmail', 'Enter a valid email'); ok = false; }
            if (data.customerPhone.replace(/\D/g, '').length < 10) { setError(form, 'customerPhone', 'Enter a valid 10-digit phone'); ok = false; }
            if (quantity < 1 || quantity > maxQty()) { setError(form, 'quantity', 'Quantity must be 1–' + maxQty()); ok = false; }
            if (!ok) return focusFirstError(form);

            var btn = $('#confirm-booking-btn');
            busy(btn, true, 'Booking…');
            api('POST', '/bookings', data).then(function (r) {
                busy(btn, false);
                if (!r.ok) {
                    if (r.status === 0) return;
                    if (!showApiErrors(form, r.data)) toast(errorText(r.data), 'error');
                    else focusFirstError(form);
                    return;
                }
                var b = r.data.data;
                saver.clear();
                $('#booking-draft').hidden = true;
                Kit.store.set(userKey('booked'), true);
                toast(r.data.message);
                if (b.event) { event = b.event; renderSeats(); }
                $('#booking-ref').textContent = b.bookingRef;
                $('#success-name').textContent = b.customerName;
                $('#success-qty').textContent = b.quantity;
                $('#success-total').textContent = money(b.totalPrice);
                form.hidden = true;
                $('#sold-out-msg').hidden = true;
                $('#booking-success').hidden = false;
            });
        });

        load();
    };

    // ---------- bookings ----------

    pages.bookings = function () {
        var list = $('#bookings-list');
        var current = 1;
        var total = 0;
        var updatePager = pager($('#bookings-pager'), load);

        function setTotal(n) {
            total = n;
            $('#bookings-total').textContent = n;
            $('#clear-bookings-btn').disabled = n === 0;
            usage.report('bookings', n);
        }

        function card(b) {
            var e = b.event || {};
            var box = el('article', { 'class': 'tt-ticket tt-cat tt-cat-' + (e.category || 'none'), 'data-testid': 'booking-card', 'data-booking-id': b.id });
            var stub = el('div', { 'class': 'tt-ticket-stub' });
            if (e.eventDate) {
                var parts = dateParts(e.eventDate);
                append(stub, icon(CATEGORY_ICON[e.category] || 'bi-ticket-perforated'), el('b', {}, parts.day), el('small', {}, parts.month));
            } else {
                stub.appendChild(icon('bi-ticket-perforated'));
            }

            var main = el('div', { 'class': 'tt-ticket-main' });
            var meta = el('div', { 'class': 'tt-ticket-meta' });
            append(meta,
                e.eventDate ? append(el('span'), icon('bi-clock'), document.createTextNode(formatDate(e.eventDate))) : null,
                append(el('span', { 'data-testid': 'booking-quantity' }), icon('bi-ticket-perforated'), document.createTextNode(b.quantity + ' ticket' + (b.quantity > 1 ? 's' : ''))),
                e.city ? append(el('span'), icon('bi-geo-alt'), document.createTextNode(e.city)) : null);
            append(main,
                el('span', { 'class': 'tt-ref', 'data-testid': 'booking-ref' }, b.bookingRef),
                el('h2', { 'data-testid': 'booking-event-title' }, e.title || 'Event removed'),
                meta);

            var side = el('div', { 'class': 'tt-ticket-side' });
            append(side,
                append(el('div', { 'class': 'text-md-end' }),
                    el('div', { 'class': 'tt-ticket-total', 'data-testid': 'booking-total' }, money(b.totalPrice)),
                    el('span', { 'class': 'tt-status', 'data-testid': 'booking-status' }, b.status)));

            var actions = el('div', { 'class': 'd-flex gap-2' });
            var view = el('a', { 'class': 'btn btn-sm btn-tt-ghost', href: TT.base + '/bookings/' + b.id, 'data-testid': 'view-booking-btn' }, 'View Details');
            var cancel = el('button', { 'class': 'btn btn-sm btn-outline-danger', type: 'button', 'data-testid': 'cancel-booking-btn' }, 'Cancel Booking');
            cancel.addEventListener('click', function () {
                confirmAction({
                    title: 'Cancel this booking?',
                    description: 'This will cancel ' + b.bookingRef + ' and release ' + b.quantity + ' seat(s) back to the event. This cannot be undone.',
                    confirmLabel: 'Yes, cancel it',
                    optimistic: true
                }, function () {
                    // Optimistic: the ticket disappears now; it comes back if the API refuses.
                    box.classList.add('is-leaving');
                    var hideTimer = setTimeout(function () { box.hidden = true; }, 250);
                    setTotal(total - 1);
                    return api('DELETE', '/bookings/' + b.id, null, { quiet: true }).then(function (r) {
                        if (!r.ok) {
                            clearTimeout(hideTimer);
                            box.hidden = false;
                            box.classList.remove('is-leaving');
                            setTotal(total + 1);
                            toast(errorText(r.data), 'error', { title: r.status === 0 ? b.bookingRef + ' not cancelled. It has been put back.' : 'Not cancelled' });
                            return;
                        }
                        toast(r.data.message);
                        load(current, { silent: true });
                    });
                });
            });
            append(actions, view, cancel);
            side.appendChild(actions);
            return append(box, stub, main, side);
        }

        function load(page, opts) {
            opts = opts || {};
            current = page || 1;
            list.setAttribute('aria-busy', 'true');
            $('#bookings-error').hidden = true;
            if (!opts.silent) {
                $('#bookings-empty').hidden = true;
                ticketSkeletons(list, 3);
            }
            return api('GET', '/bookings?limit=5&page=' + current).then(function (r) {
                list.innerHTML = '';
                list.removeAttribute('aria-busy');
                if (!r.ok) { $('#bookings-error').hidden = false; $('#bookings-pager').hidden = true; return; }
                var p = r.data.pagination;
                // Deleting the last booking on a page: step back a page.
                if (!r.data.data.length && current > 1) return load(current - 1, opts);
                r.data.data.forEach(function (b) { list.appendChild(card(b)); });
                setTotal(p.total);
                $('#bookings-empty').hidden = p.total > 0;
                updatePager(p);
            });
        }

        $('#clear-bookings-btn').addEventListener('click', function () {
            confirmAction({
                title: 'Clear all bookings?',
                description: 'Every booking in your sandbox will be cancelled and its seats released.',
                impact: [plural(total, 'booking') + ' will be cancelled', 'All their seats go back to the events', 'This cannot be undone'],
                confirmLabel: 'Yes, clear all'
            }, function () {
                return api('DELETE', '/bookings').then(function (r) {
                    if (!r.ok) return toast(errorText(r.data), 'error');
                    toast(r.data.message);
                    load(1);
                });
            });
        });
        $('#bookings-error [data-retry]').addEventListener('click', function () { load(current); });
        refreshers.bookings = Kit.throttledButton($('#refresh-bookings-btn'), REFRESH_COOLDOWN, function () {
            load(current).then(function () { toast('Bookings refreshed', 'info'); });
        });
        load(1);
    };

    pages['booking-details'] = function () {
        var id = lastSegment();

        function load() {
            $('#booking-skeleton').hidden = false;
            $('#booking-load-error').hidden = true;
            api('GET', '/bookings/' + encodeURIComponent(id)).then(function (r) {
                $('#booking-skeleton').hidden = true;
                if (!r.ok) {
                    if (r.status === 0 || r.status >= 500) { $('#booking-load-error').hidden = false; return; }
                    $('#booking-not-found').hidden = false;
                    return;
                }
                render(r.data.data);
            });
        }

        function render(b) {
            var e = b.event || {};
            document.title = b.bookingRef + ' · TicketTrail';
            $('#detail-event-title').textContent = e.title || 'Event removed';
            $('#detail-ref').textContent = b.bookingRef;
            $('#detail-status').textContent = b.status;
            $('#detail-event-date').textContent = e.eventDate ? formatDate(e.eventDate) : '—';
            $('#detail-event-venue').textContent = e.venue || '—';
            $('#detail-event-city').textContent = e.city || '—';
            $('#detail-event-category').textContent = e.category || '—';
            $('#detail-name').textContent = b.customerName;
            $('#detail-email').textContent = b.customerEmail;
            $('#detail-phone').textContent = b.customerPhone;
            $('#detail-qty').textContent = b.quantity;
            $('#detail-unit-price').textContent = e.price !== undefined ? money(e.price) : '—';
            $('#detail-total').textContent = money(b.totalPrice);
            $('#detail-created').textContent = formatDate(b.createdAt);
            $('#detail-id').textContent = b.id;
            $('#booking-detail').hidden = false;

            // A deliberately slow check: practise waiting for a result, not sleeping.
            $('#check-refund-btn').addEventListener('click', function () {
                $('#check-refund-btn').hidden = true;
                $('#refund-spinner').hidden = false;
                var delay = window.Chaos && Chaos.enabled ? Chaos.between(2000, 7000) : 4000;
                setTimeout(function () {
                    var eligible = b.quantity === 1;
                    var result = $('#refund-result');
                    result.className = 'tt-refund-result ' + (eligible ? 'is-eligible' : 'is-ineligible');
                    result.setAttribute('data-eligible', eligible ? 'true' : 'false');
                    result.innerHTML = '';
                    append(result,
                        icon(eligible ? 'bi-check-circle-fill' : 'bi-x-circle-fill'),
                        append(el('span'),
                            el('strong', {}, eligible ? 'Eligible for refund.' : 'Not eligible for refund.'),
                            document.createTextNode(eligible ? ' Single-ticket bookings qualify for a full refund.' : ' Group bookings (' + b.quantity + ' tickets) are non-refundable.')));
                    $('#refund-spinner').hidden = true;
                    result.hidden = false;
                }, delay);
            });

            $('#cancel-booking-btn').addEventListener('click', function () {
                confirmAction({
                    title: 'Cancel this booking?',
                    description: 'Cancelling ' + b.bookingRef + ' will release ' + b.quantity + ' seat(s) back to the event. This cannot be undone.',
                    confirmLabel: 'Yes, cancel it'
                }, function () {
                    return api('DELETE', '/bookings/' + b.id).then(function (res) {
                        if (!res.ok) return toast(errorText(res.data), 'error');
                        flash(res.data.message);
                        go('/bookings');
                    });
                });
            });
        }

        $('#booking-load-error [data-retry]').addEventListener('click', load);
        load();
    };

    // ---------- manage ----------

    pages['admin-events'] = function () {
        var form = $('#admin-event-form');
        var editing = null;
        var current = 1;
        var FIELDS = ['title', 'description', 'category', 'venue', 'city', 'eventDate', 'price', 'totalSeats', 'imageUrl'];

        var table = Kit.dataTable({
            table: $('#admin-events-table'),
            body: $('#admin-events-body'),
            pager: $('#admin-events-pager'),
            empty: $('#admin-events-empty'),
            pageSize: 10,
            pageSizeSelect: $('#events-page-size'),
            storageKey: 'tt.table.admin-events',
            onPage: function (page) { load({ page: page }); },
            rowId: function (e) { return e.id; },
            sorters: {
                title: function (e) { return e.title.toLowerCase(); },
                date: function (e) { return Date.parse(e.eventDate); },
                price: function (e) { return Number(e.price); },
                seats: function (e) { return e.availableSeats; }
            },
            renderRow: row
        });

        var required = function (v) { return RULES.required(v); };
        var validator = Kit.smartValidation(form, {
            title: function (v) { return required(v) || (v.trim().length < 3 ? 'Title must be at least 3 characters' : ''); },
            category: required,
            venue: required,
            city: required,
            eventDate: function (v) { return required(v) || (new Date(v) <= new Date() ? 'Event date must be in the future' : ''); },
            price: function (v) { return required(v) || (Number(v) < 0 ? 'Price cannot be negative' : ''); },
            totalSeats: function (v) { return required(v) || (!(Number(v) >= 1 && Number.isInteger(Number(v))) ? 'Seats must be a whole number of at least 1' : ''); }
        });

        var saver = Kit.autosave(form, {
            key: userKey('draft.event'),
            fields: FIELDS,
            status: $('#event-autosave'),
            enabled: function () { return !editing; }
        });
        draftBanner($('#event-draft'), saver);

        function resetForm() {
            editing = null;
            form.reset();
            clearErrors(form);
            validator.reset();
            $('#event-form-title').textContent = 'Add New Event';
            form.classList.remove('is-editing');
            $('#add-event-btn').textContent = 'Add Event';
            $('#cancel-edit-btn').hidden = true;
        }

        function startEdit(e) {
            editing = e;
            clearErrors(form);
            validator.reset();
            $('#event-draft').hidden = true;
            ['title', 'description', 'category', 'venue', 'city', 'price', 'totalSeats', 'imageUrl'].forEach(function (k) {
                form.elements[k].value = e[k] === null || e[k] === undefined ? '' : e[k];
            });
            form.elements.eventDate.value = toLocalInput(e.eventDate);
            $('#event-form-title').textContent = 'Edit Event';
            form.classList.add('is-editing');
            $('#add-event-btn').textContent = 'Update Event';
            $('#cancel-edit-btn').hidden = false;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            form.elements.title.focus();
        }

        function row(e) {
            var tr = el('tr', { 'data-testid': 'event-table-row', 'data-event-id': e.id });
            var thumb = el('img', { 'class': 'tt-thumb', src: imageFor(e), alt: '' });
            thumb.addEventListener('error', function () { thumb.src = TT.images[e.category]; }, { once: true });
            var info = el('div', { 'style': 'min-width:0' });
            var name = el('div', { 'class': 'd-flex align-items-center gap-2 flex-wrap' });
            name.appendChild(el('a', { href: TT.base + '/events/' + e.id, 'class': 'tt-row-title' }, e.title));
            var tags = el('div', { 'class': 'd-flex align-items-center gap-1 flex-wrap mt-1' });
            tags.appendChild(categoryBadge(e.category));
            if (e.isStatic) tags.appendChild(append(el('span', { 'class': 'tt-chip tt-chip-featured' }), icon('bi-star-fill'), document.createTextNode('Featured')));
            append(info, name, el('div', { 'class': 'tt-row-sub text-truncate', style: 'max-width:260px' }, e.venue + ' · ' + e.city), tags);
            var title = append(el('td'), append(el('div', { 'class': 'd-flex align-items-center gap-3' }), thumb, info));
            var seatState = seatsText(e.availableSeats).cls;
            var seats = append(el('td'),
                el('div', { 'class': 'tt-seats ' + seatState }, e.availableSeats + '/' + e.totalSeats),
                seatBar(e, true));
            var actions = el('td', { 'class': 'text-nowrap text-end' });
            if (e.isStatic) {
                actions.appendChild(append(el('span', { 'class': 'tt-readonly' }), icon('bi-lock'), document.createTextNode('Read-only')));
            } else {
                var edit = append(el('button', { 'class': 'btn btn-sm btn-tt-ghost me-1', type: 'button', 'data-testid': 'edit-event-btn' }), icon('bi-pencil', 'me-1'), document.createTextNode('Edit'));
                var del = append(el('button', { 'class': 'btn btn-sm btn-outline-danger', type: 'button', 'data-testid': 'delete-event-btn' }), icon('bi-trash3', 'me-1'), document.createTextNode('Delete'));
                edit.addEventListener('click', function () { startEdit(e); });
                del.addEventListener('click', function () {
                    var booked = e.totalSeats - e.availableSeats;
                    confirmAction({
                        title: 'Delete this event?',
                        description: '"' + e.title + '" and all of its bookings will be permanently deleted.',
                        impact: booked ? [plural(booked, 'booked seat') + ' will be cancelled with it'] : [],
                        confirmLabel: 'Yes, delete',
                        optimistic: true
                    }, function () {
                        var removed = table.remove([e.id]);
                        return api('DELETE', '/events/' + e.id, null, { quiet: true }).then(function (r) {
                            if (!r.ok) {
                                table.restore(removed);
                                return toast(errorText(r.data), 'error', { title: r.status === 0 ? 'Not deleted. “' + e.title + '” has been put back.' : 'Not deleted' });
                            }
                            toast(r.data.message);
                            if (editing && editing.id === e.id) resetForm();
                            load({ silent: true });
                        });
                    });
                });
                append(actions, edit, del);
            }
            return append(tr,
                title,
                el('td', { 'class': 'small text-nowrap' }, new Date(e.eventDate).toLocaleString('en-US', { day: 'numeric', month: 'short', year: 'numeric' })),
                el('td', { 'class': 'fw-bold text-dark text-nowrap' }, money(e.price)),
                seats,
                actions);
        }

        /** Same requests as before the data table: one page (limit=10&page=N) plus the KPI list. */
        function load(opts) {
            opts = opts || {};
            current = opts.page || current;
            $('#admin-events-error').hidden = true;
            if (!opts.silent) table.loading(5);
            var pageRequest = api('GET', '/events?limit=' + table.size() + '&page=' + current).then(function (r) {
                if (!r.ok) {
                    table.setRows([], { page: 1, total: 0, totalPages: 1 });
                    $('#admin-events-empty').hidden = true;
                    $('#admin-events-error').hidden = false;
                    return;
                }
                var p = r.data.pagination;
                if (!r.data.data.length && current > 1) return load({ page: current - 1, silent: opts.silent });
                table.setRows(r.data.data, p);
                $('#admin-events-count').textContent = p.total + ' events';
            });
            api('GET', '/events?limit=100').then(function (r) {
                if (!r.ok) return;
                var all = r.data.data;
                var custom = all.filter(function (e) { return !e.isStatic; }).length;
                $('#kpi-total').textContent = r.data.pagination.total;
                $('#kpi-featured').textContent = all.length - custom;
                $('#kpi-custom').textContent = custom;
                $('#kpi-soldout').textContent = all.filter(function (e) { return e.availableSeats === 0; }).length;
                usage.report('events', custom);
            });
            return pageRequest;
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            clearErrors(form);
            var f = form.elements;
            var ok = true;
            ['title', 'category', 'venue', 'city', 'eventDate', 'price', 'totalSeats'].forEach(function (k) {
                if (f[k].value.trim() === '') { setError(form, k, 'This field is required'); ok = false; }
            });
            if (f.title.value.trim() && f.title.value.trim().length < 3) { setError(form, 'title', 'Title must be at least 3 characters'); ok = false; }
            if (f.eventDate.value && new Date(f.eventDate.value) <= new Date()) { setError(form, 'eventDate', 'Event date must be in the future'); ok = false; }
            if (f.price.value !== '' && Number(f.price.value) < 0) { setError(form, 'price', 'Price cannot be negative'); ok = false; }
            if (f.totalSeats.value !== '' && !(Number(f.totalSeats.value) >= 1 && Number.isInteger(Number(f.totalSeats.value)))) { setError(form, 'totalSeats', 'Seats must be a whole number of at least 1'); ok = false; }
            if (!ok) return focusFirstError(form);

            var body = {
                title: f.title.value.trim(),
                description: f.description.value.trim() || null,
                category: f.category.value,
                venue: f.venue.value.trim(),
                city: f.city.value.trim(),
                eventDate: new Date(f.eventDate.value).toISOString(),
                price: Number(f.price.value),
                totalSeats: Number(f.totalSeats.value),
                imageUrl: f.imageUrl.value.trim() || null
            };
            var btn = $('#add-event-btn');
            busy(btn, true, editing ? 'Saving…' : 'Adding…');
            var request = editing ? api('PUT', '/events/' + editing.id, body) : api('POST', '/events', body);
            request.then(function (r) {
                busy(btn, false);
                if (!r.ok) {
                    if (r.status === 0) return;
                    if (!showApiErrors(form, r.data)) toast(errorText(r.data), 'error');
                    else focusFirstError(form);
                    return;
                }
                toast(r.data.message);
                var wasEditing = !!editing;
                if (!wasEditing) saver.clear();
                $('#event-draft').hidden = true;
                resetForm();
                load({ page: wasEditing ? current : 1, silent: true });
            });
        });
        $('#cancel-edit-btn').addEventListener('click', resetForm);
        $('#admin-events-error [data-retry]').addEventListener('click', function () { load(); });
        refreshers['admin-events'] = Kit.throttledButton($('#refresh-events-btn'), REFRESH_COOLDOWN, function () {
            load().then(function () { toast('Events refreshed', 'info'); });
        });

        if (new URLSearchParams(location.search).get('new') === '1') {
            history.replaceState(null, '', location.pathname);
            setTimeout(function () { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); form.elements.title.focus(); }, 50);
        }
        load();
    };

    pages['admin-bookings'] = function () {
        var filter = $('#event-filter');
        var modal;
        var current = 1;
        var allBookings = [];

        api('GET', '/events?limit=100').then(function (r) {
            if (!r.ok) return;
            r.data.data.forEach(function (e) { filter.appendChild(el('option', { value: e.id }, e.title)); });
        });

        var table = Kit.dataTable({
            table: $('#admin-bookings-table'),
            body: $('#admin-bookings-body'),
            pager: $('#admin-bookings-pager'),
            empty: $('#admin-bookings-empty'),
            pageSize: 10,
            pageSizeSelect: $('#bookings-page-size'),
            storageKey: 'tt.table.admin-bookings',
            onPage: function (page) { load({ page: page }); },
            rowId: function (b) { return b.id; },
            selectable: function () { return true; },
            onSelectionChange: selectionChanged,
            sorters: {
                ref: function (b) { return b.bookingRef; },
                event: function (b) { return b.event ? b.event.title.toLowerCase() : ''; },
                customer: function (b) { return b.customerName.toLowerCase(); },
                quantity: function (b) { return b.quantity; },
                total: function (b) { return Number(b.totalPrice); },
                created: function (b) { return Date.parse(b.createdAt); }
            },
            renderRow: row
        });

        function showBooking(b) {
            modal = modal || new bootstrap.Modal($('#booking-modal'));
            $('#booking-modal-title').textContent = 'Booking — ' + b.bookingRef;
            var dl = $('#booking-modal-body');
            dl.innerHTML = '';
            [['Event', b.event ? b.event.title : '—'], ['Customer', b.customerName], ['Email', b.customerEmail], ['Phone', b.customerPhone],
                ['Tickets', b.quantity], ['Total', money(b.totalPrice)], ['Status', b.status], ['Booked on', formatDate(b.createdAt)]].forEach(function (pair) {
                append(dl, el('dt', {}, pair[0]), el('dd', {}, String(pair[1])));
            });
            modal.show();
        }

        /** Optimistically remove bookings, then confirm with the API. */
        function cancelBookings(ids) {
            var removed = table.remove(ids);
            var request = ids.length === 1
                ? api('DELETE', '/bookings/' + ids[0], null, { quiet: true }).then(function (r) { return r.ok ? { ok: true, message: r.data.message, notFound: [] } : r; })
                : api('POST', '/bookings/bulk-cancel', { ids: ids }, { quiet: true }).then(function (r) { return r.ok ? { ok: true, message: r.data.message, notFound: r.data.notFound } : r; });
            return request.then(function (result) {
                if (!result.ok) {
                    table.restore(removed);
                    toast(errorText(result.data), 'error', { title: result.status === 0 ? 'Nothing was cancelled. The bookings have been put back.' : 'Not cancelled' });
                    return;
                }
                toast(result.message);
                if (result.notFound.length) toast(plural(result.notFound.length, 'booking') + ' had already been cancelled.', 'info');
                load({ silent: true });
            });
        }

        function row(b) {
            var tr = el('tr', { 'data-testid': 'booking-table-row', 'data-booking-id': b.id });
            var view = append(el('button', { 'class': 'btn btn-sm btn-tt-ghost me-1', type: 'button', 'data-testid': 'view-booking-btn' }), icon('bi-eye', 'me-1'), document.createTextNode('View'));
            var cancel = append(el('button', { 'class': 'btn btn-sm btn-outline-danger', type: 'button', 'data-testid': 'cancel-booking-btn' }), icon('bi-x-circle', 'me-1'), document.createTextNode('Cancel'));
            view.addEventListener('click', function () { showBooking(b); });
            cancel.addEventListener('click', function () {
                confirmAction({
                    title: 'Cancel this booking?',
                    description: 'This will cancel ' + b.bookingRef + ' and release ' + b.quantity + ' seat(s) back to the event.',
                    confirmLabel: 'Yes, cancel it',
                    optimistic: true
                }, function () { return cancelBookings([b.id]); });
            });
            var customer = append(el('td'), el('div', { 'class': 'tt-row-title' }, b.customerName), el('div', { 'class': 'tt-row-sub' }, b.customerEmail));
            var eventCell = el('td');
            if (b.event) {
                var thumb = el('img', { 'class': 'tt-thumb', src: imageFor(b.event), alt: '' });
                thumb.addEventListener('error', function () { thumb.src = TT.images[b.event.category]; }, { once: true });
                eventCell.appendChild(append(el('div', { 'class': 'd-flex align-items-center gap-3' }), thumb,
                    append(el('div'), el('div', { 'class': 'tt-row-title' }, b.event.title), el('div', { 'class': 'tt-row-sub' }, b.event.city))));
            } else {
                eventCell.textContent = '—';
            }
            return append(tr,
                append(el('td'), el('span', { 'class': 'tt-ref' }, b.bookingRef)),
                eventCell,
                customer,
                el('td', { 'class': 'text-center fw-semibold' }, b.quantity),
                el('td', { 'class': 'fw-bold text-dark' }, money(b.totalPrice)),
                el('td', { 'class': 'small text-nowrap' }, formatDate(b.createdAt)),
                append(el('td', { 'class': 'text-nowrap text-end' }), view, cancel));
        }

        function selectionChanged(ids) {
            var bar = $('#bulk-bar');
            bar.hidden = ids.length === 0;
            $('#bulk-count').textContent = ids.length;
            document.body.classList.toggle('has-bulkbar', ids.length > 0);

            var note = $('#select-all-note');
            var total = table.total();
            var onPage = $all('#admin-bookings-body [data-testid="row-checkbox"]');
            var pageAll = onPage.length > 0 && onPage.every(function (b) { return b.checked; });
            note.innerHTML = '';
            if (ids.length === total && total > onPage.length) {
                append(note, document.createTextNode('All ' + total + ' bookings are selected. '), linkButton('Clear selection', function () { table.clearSelection(); }));
                note.hidden = false;
            } else if (pageAll && total > onPage.length) {
                append(note, document.createTextNode('All ' + onPage.length + ' bookings on this page are selected. '), linkButton('Select all ' + total + ' bookings', function () { table.selectAll(matching().map(function (b) { return b.id; })); }));
                note.hidden = false;
            } else {
                note.hidden = true;
            }
        }

        function linkButton(label, onClick) {
            var b = el('button', { type: 'button', 'class': 'btn btn-link btn-sm p-0 fw-semibold align-baseline', 'data-testid': 'select-all-link' }, label);
            b.addEventListener('click', onClick);
            return b;
        }

        /** Every booking that matches the event filter (from the KPI list), for select-all and export. */
        function matching() {
            return allBookings.filter(function (b) { return !filter.value || String(b.eventId) === filter.value; });
        }

        /** Same requests as before the data table: one page (page=N&limit=10) plus the KPI list. */
        function load(opts) {
            opts = opts || {};
            current = opts.page || current;
            $('#admin-bookings-error').hidden = true;
            if (!opts.silent) table.loading(4);
            var query = new URLSearchParams({ page: current, limit: table.size() });
            if (filter.value) query.set('eventId', filter.value);
            var filtered = !!filter.value;
            $('#clear-event-filter-btn').hidden = !filtered;
            $('#admin-bookings-empty [data-empty-hint]').textContent = filtered ? 'No bookings for this event.' : 'Book an event and it will show up here.';
            var pageRequest = api('GET', '/bookings?' + query).then(function (r) {
                if (!r.ok) {
                    table.setRows([], { page: 1, total: 0, totalPages: 1 });
                    $('#admin-bookings-empty').hidden = true;
                    $('#admin-bookings-error').hidden = false;
                    return;
                }
                if (!r.data.data.length && current > 1) return load({ page: current - 1, silent: opts.silent });
                table.setRows(r.data.data, r.data.pagination);
            });
            api('GET', '/bookings?limit=100').then(function (r) {
                if (!r.ok) return;
                allBookings = r.data.data;
                kpis(r.data);
            });
            return pageRequest;
        }

        function kpis(data) {
            var all = data.data;
            var events = {};
            var tickets = 0;
            var spent = 0;
            all.forEach(function (b) { tickets += b.quantity; spent += b.totalPrice; events[b.eventId] = true; });
            $('#kpi-bookings').textContent = data.pagination.total;
            $('#kpi-tickets').textContent = tickets;
            $('#kpi-revenue').textContent = money(spent);
            $('#kpi-events').textContent = Object.keys(events).length;
            usage.report('bookings', data.pagination.total);
        }

        $('#bulk-cancel-btn').addEventListener('click', function () {
            var ids = table.selected();
            var known = {};
            allBookings.concat(table.rows()).forEach(function (b) { known[b.id] = b; });
            var chosen = ids.map(function (id) { return known[id]; }).filter(Boolean);
            var refs = chosen.slice(0, 5).map(function (b) { return b.bookingRef + ' · ' + (b.event ? b.event.title : 'Event removed'); });
            if (chosen.length > 5) refs.push('…and ' + (chosen.length - 5) + ' more');
            var seats = chosen.reduce(function (n, b) { return n + b.quantity; }, 0);
            confirmAction({
                title: 'Cancel ' + plural(ids.length, 'booking') + '?',
                description: plural(seats, 'seat') + ' will be released. This cannot be undone.',
                impact: refs,
                phrase: 'CANCEL ' + ids.length,
                confirmLabel: 'Cancel ' + plural(ids.length, 'booking'),
                optimistic: true
            }, function () { return cancelBookings(ids); });
        });
        $('#bulk-clear-btn').addEventListener('click', function () { table.clearSelection(); });
        $('#export-csv-btn').addEventListener('click', function () {
            var rows = [['Reference', 'Event', 'Customer', 'Email', 'Phone', 'Tickets', 'Total (BDT)', 'Status', 'Booked on']];
            matching().forEach(function (b) {
                rows.push([b.bookingRef, b.event ? b.event.title : '', b.customerName, b.customerEmail, b.customerPhone, b.quantity, b.totalPrice, b.status, b.createdAt]);
            });
            downloadCsv('tickettrail-bookings.csv', rows);
            toast('Exported ' + plural(rows.length - 1, 'booking') + ' to CSV');
        });
        $('#clear-event-filter-btn').addEventListener('click', function () { filter.value = ''; table.clearSelection(); load({ page: 1 }); });
        $('#admin-bookings-error [data-retry]').addEventListener('click', function () { load(); });
        filter.addEventListener('change', function () { table.clearSelection(); load({ page: 1 }); });
        refreshers['admin-bookings'] = Kit.throttledButton($('#refresh-admin-bookings-btn'), REFRESH_COOLDOWN, function () {
            load().then(function () { toast('Bookings refreshed', 'info'); });
        });
        load();
    };

    // ---------- refer & earn ----------

    var TIERS = [
        { id: 'starter', name: 'Starter', min: 0, rate: 200 },
        { id: 'bronze', name: 'Bronze', min: 3, rate: 250 },
        { id: 'silver', name: 'Silver', min: 5, rate: 300 },
        { id: 'gold', name: 'Gold', min: 10, rate: 400 }
    ];
    var RESEND_COOLDOWN = 30000;
    var MAX_PENDING = 10;

    /** A stable, readable code per account, e.g. TT0RS7. */
    function referralCode(user) {
        var seed = Number(user.id) || String(user.email || 'guest').split('').reduce(function (h, c) { return (h * 31 + c.charCodeAt(0)) % 1000003; }, 7);
        return 'TT' + (seed * 7919 + 46656).toString(36).toUpperCase().slice(-6).padStart(4, '0');
    }

    pages.referrals = function () {
        var code = referralCode(session.user);
        var link = TT.base + '/register?ref=' + code;
        var key = userKey('invites');
        var invites = Kit.store.get(key, []) || [];
        var tickTimer;

        $('#referral-code').textContent = code;
        $('#referral-link').value = link;
        var message = 'Join me on TicketTrail and we both get ৳200 booking credit: ' + link;
        $('#share-whatsapp').href = 'https://wa.me/?text=' + encodeURIComponent(message);
        $('#share-x').href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(message);
        $('#share-email').href = 'mailto:?subject=' + encodeURIComponent('৳200 off your next event') + '&body=' + encodeURIComponent(message);

        var copyBtn = $('#copy-referral-btn');
        var copyTimer;
        copyBtn.addEventListener('click', function () {
            function done() {
                copyBtn.innerHTML = '';
                append(copyBtn, icon('bi-check2', 'me-1'), el('span', {}, 'Copied!'));
                copyBtn.setAttribute('data-copied', 'true');
                toast('Referral link copied');
                clearTimeout(copyTimer);
                copyTimer = setTimeout(function () {
                    copyBtn.innerHTML = '';
                    append(copyBtn, icon('bi-clipboard', 'me-1'), el('span', {}, 'Copy link'));
                    copyBtn.removeAttribute('data-copied');
                }, 2000);
            }
            function fallback() {
                var input = $('#referral-link');
                input.select();
                try { document.execCommand('copy'); done(); } catch (e) { toast('Press Ctrl+C to copy the selected link', 'info'); }
            }
            if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(link).then(done, fallback);
            else fallback();
        });

        function save() { Kit.store.set(key, invites); }
        function joined() { return invites.filter(function (i) { return i.status === 'joined'; }).length; }
        function tierFor(n) { return TIERS.filter(function (t) { return n >= t.min; }).pop(); }

        function renderStats() {
            var n = joined();
            var tier = tierFor(n);
            var next = TIERS[TIERS.indexOf(tier) + 1];
            $('#ref-sent').textContent = invites.length;
            $('#ref-joined').textContent = n;
            $('#ref-credit').textContent = '৳' + (n * tier.rate).toLocaleString('en-US');
            var badge = $('#affiliate-tier');
            badge.textContent = tier.name;
            badge.setAttribute('data-tier', tier.id);
            $('#affiliate-next').textContent = next ? (next.min - n) + ' more to ' + next.name : 'Top tier reached';
            $('#affiliate-progress').style.width = (next ? Math.round((n - tier.min) / (next.min - tier.min) * 100) : 100) + '%';
            $all('.tt-tiers li').forEach(function (li) {
                var t = TIERS.filter(function (x) { return x.id === li.getAttribute('data-tier'); })[0];
                li.classList.toggle('is-done', n >= t.min);
                li.classList.toggle('is-current', t === tier);
            });
        }

        function render() {
            var body = $('#invites-body');
            body.innerHTML = '';
            var cooling = false;
            invites.slice().sort(function (a, b) { return b.sentAt - a.sentAt; }).forEach(function (inv) {
                var tr = el('tr', { 'data-testid': 'invite-row', 'data-email': inv.email });
                var statusBadge = el('span', { 'class': 'tt-invite-status is-' + inv.status, 'data-testid': 'invite-status' },
                    { sending: 'Sending…', pending: 'Pending', joined: 'Joined' }[inv.status]);
                var actions = el('td', { 'class': 'text-end text-nowrap' });
                if (inv.status === 'pending') {
                    var left = Math.ceil((RESEND_COOLDOWN - (Date.now() - (inv.resentAt || inv.sentAt))) / 1000);
                    var resend = el('button', { type: 'button', 'class': 'btn btn-sm btn-tt-ghost me-1', 'data-testid': 'resend-invite-btn' }, left > 0 ? 'Resend (' + left + 's)' : 'Resend');
                    // Throttled per invite: one resend every 30 seconds.
                    resend.disabled = left > 0;
                    if (left > 0) cooling = true;
                    resend.addEventListener('click', function () {
                        inv.resentAt = Date.now();
                        save();
                        render();
                        toast('Invite resent to ' + inv.email, 'info');
                    });
                    var simulate = el('button', { type: 'button', 'class': 'btn btn-sm btn-outline-secondary me-1', 'data-testid': 'simulate-signup-btn' }, 'Simulate sign-up');
                    simulate.addEventListener('click', function () {
                        var before = tierFor(joined());
                        inv.status = 'joined';
                        save();
                        render();
                        var after = tierFor(joined());
                        toast(inv.email + ' joined. You earned ৳' + after.rate + '.', 'success', after !== before ? { title: 'You reached ' + after.name + '!' } : undefined);
                    });
                    append(actions, resend, simulate);
                }
                if (inv.status !== 'sending') {
                    var remove = el('button', { type: 'button', 'class': 'btn btn-sm btn-outline-danger', 'data-testid': 'remove-invite-btn', 'aria-label': 'Remove invite for ' + inv.email }, 'Remove');
                    remove.addEventListener('click', function () {
                        confirmAction({
                            title: 'Remove this invite?',
                            description: inv.email + ' will be removed from your list.' + (inv.status === 'joined' ? ' Credit you earned from it is removed too.' : ''),
                            confirmLabel: 'Yes, remove',
                            optimistic: true
                        }, function () {
                            invites = invites.filter(function (i) { return i !== inv; });
                            save();
                            render();
                            toast('Invite removed', 'info', { action: { label: 'Undo', onClick: function () { invites.push(inv); save(); render(); } } });
                        });
                    });
                    actions.appendChild(remove);
                }
                append(tr,
                    append(el('td'), el('span', { 'class': 'fw-semibold text-dark' }, inv.email)),
                    el('td', { 'class': 'small text-nowrap' }, formatDate(new Date(inv.sentAt).toISOString())),
                    append(el('td'), statusBadge),
                    actions);
                body.appendChild(tr);
            });
            $('#invites-empty').hidden = invites.length > 0;
            $('#invites-table').hidden = invites.length === 0;
            renderStats();
            clearTimeout(tickTimer);
            if (cooling) tickTimer = setTimeout(render, 1000);
        }

        var form = $('#invite-form');
        var validator = Kit.smartValidation(form, {
            email: function (v) {
                var value = v.trim().toLowerCase();
                if (!EMAIL_RE.test(value)) return 'Enter a valid email';
                if (value === String(session.user.email || '').toLowerCase()) return 'You can’t invite yourself';
                if (invites.some(function (i) { return i.email === value; })) return 'You already invited this email';
                if (invites.filter(function (i) { return i.status !== 'joined'; }).length >= MAX_PENDING) return 'You can have at most ' + MAX_PENDING + ' pending invites';
                return '';
            }
        }, { emailFields: ['email'] });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validator.validateAll()) return;
            var invite = { email: form.elements.email.value.trim().toLowerCase(), sentAt: Date.now(), status: 'sending' };
            // Optimistic: the row shows straight away while the "send" finishes.
            invites.push(invite);
            render();
            form.reset();
            validator.reset();
            setTimeout(function () {
                invite.status = 'pending';
                save();
                render();
                toast('Invite sent to ' + invite.email);
            }, 600);
        });

        invites.forEach(function (i) { if (i.status === 'sending') i.status = 'pending'; });
        render();
    };

    // ---------- boot ----------

    document.addEventListener('DOMContentLoaded', function () {
        initNetwork();
        initGlobalErrors();
        if (TT.page === 'not-found') return;

        var isAuthPage = TT.page === 'login' || TT.page === 'register';
        if (isAuthPage && session.token) return go('');
        if (!isAuthPage && !session.token) {
            flash('Please sign in to continue', 'error');
            return go('/login');
        }
        showFlash();
        initNav();
        initPlan();
        initSidebar();
        initPalette();
        initShortcuts();
        initOnboarding();
        initBackToTop();
        if (pages[TT.page]) pages[TT.page]();
        usage.fill();
    });
})();
