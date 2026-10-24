/* TicketTrail front end. Every page is a thin Blade shell; all data comes from
   the /api/tickets REST API using the token kept in localStorage (tt_token,
   tt_user), so learners can log in through the API and inject the session. */
(function () {
    var TT = window.TT;
    var FLASH_KEY = 'tt.flash';
    var CATEGORY_ICON = { Conference: 'bi-mic', Concert: 'bi-music-note-beamed', Sports: 'bi-trophy', Workshop: 'bi-tools', Festival: 'bi-stars' };

    // ---------- helpers ----------

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function go(path) { location.href = TT.base + path; }
    function lastSegment() { return decodeURIComponent(location.pathname.split('/').filter(Boolean).pop()); }

    function money(n) {
        n = Number(n);
        return n === 0 ? 'Free' : '\u09F3' + n.toLocaleString('en-US', { maximumFractionDigits: 2 });
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

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function append(parent) {
        Array.prototype.slice.call(arguments, 1).forEach(function (child) { if (child) parent.appendChild(child); });
        return parent;
    }

    var session = {
        get token() { try { return localStorage.getItem('tt_token'); } catch (e) { return null; } },
        get user() { try { return JSON.parse(localStorage.getItem('tt_user')) || {}; } catch (e) { return {}; } },
        save: function (data) {
            try {
                localStorage.setItem('tt_token', data.token);
                localStorage.setItem('tt_user', JSON.stringify(data.user));
            } catch (e) { /* storage blocked */ }
        },
        clear: function () {
            try { localStorage.removeItem('tt_token'); localStorage.removeItem('tt_user'); } catch (e) { }
        }
    };

    function toast(message, type) {
        type = type || 'success';
        var box = el('div', { 'class': 'toast-item toast-' + type, role: 'alert', 'data-testid': 'toast' });
        box.appendChild(icon({ success: 'bi-check-circle-fill', error: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' }[type]));
        box.appendChild(el('div', { 'class': 'toast-message' }, message));
        box.addEventListener('click', function () { box.remove(); });
        $('#toast-container').appendChild(box);
        setTimeout(function () { box.remove(); }, 4000);
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

    /** fetch wrapper: JSON in/out, bearer token, 401 handling. */
    function api(method, path, body, opts) {
        opts = opts || {};
        var headers = { 'Accept': 'application/json' };
        if (body) headers['Content-Type'] = 'application/json';
        if (session.token) headers['Authorization'] = 'Bearer ' + session.token;
        if (window.Chaos && Chaos.enabled) headers['X-Chaos-Mode'] = '1';

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
            })
            .catch(function () {
                toast('Network error, please retry', 'error');
                return { ok: false, status: 0, data: {} };
            });
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

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // ---------- shared widgets ----------

    var confirmModal;
    /** Bootstrap confirm dialog; `action` returns a promise, the dialog stays open while it runs. */
    function confirmAction(opts, action) {
        var root = $('#confirm-dialog');
        confirmModal = confirmModal || new bootstrap.Modal(root);
        $('#confirm-dialog-title').textContent = opts.title;
        $('#confirm-dialog-description').textContent = opts.description;
        var old = $('#confirm-dialog-yes');
        var yes = old.cloneNode(false);
        yes.textContent = opts.confirmLabel || 'Yes';
        old.replaceWith(yes);
        yes.addEventListener('click', function () {
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

    function skeletons(container, n) {
        container.innerHTML = '';
        for (var i = 0; i < n; i++) {
            container.appendChild(append(el('div', { 'class': 'col-sm-6 col-lg-4' }), el('div', { 'class': 'tt-skeleton' })));
        }
    }

    // ---------- navigation chrome ----------

    function initNav() {
        var email = $('#user-email-display');
        if (email) email.textContent = session.user.email || '';
        var avatar = $('#user-avatar');
        if (avatar) avatar.textContent = (session.user.email || '?').charAt(0);
        var active = { home: 'home', events: 'events', 'event-details': 'events', bookings: 'bookings', 'booking-details': 'bookings', 'admin-events': 'admin', 'admin-bookings': 'admin' }[TT.page];
        $all('[data-nav]').forEach(function (a) {
            if (a.getAttribute('data-nav') === active) { a.classList.add('active'); a.setAttribute('aria-current', 'page'); }
        });
        var logout = $('#logout-btn');
        if (logout) {
            logout.addEventListener('click', function () {
                session.clear();
                flash('You have been logged out', 'info');
                go('/login');
            });
        }
    }

    var pages = {};

    // ---------- auth ----------

    pages.login = function () {
        var form = $('#login-form');
        var alertBox = $('#login-error');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors(form);
            alertBox.hidden = true;
            var email = form.elements.email.value.trim();
            var password = form.elements.password.value;
            var ok = true;
            if (!EMAIL_RE.test(email)) { setError(form, 'email', 'Enter a valid email'); ok = false; }
            if (!password) { setError(form, 'password', 'Password is required'); ok = false; }
            if (!ok) return;

            var btn = $('#login-btn');
            busy(btn, true, 'Signing in…');
            api('POST', '/auth/login', { email: email, password: password }, { allow401: true }).then(function (r) {
                busy(btn, false);
                if (!r.ok) {
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

    pages.register = function () {
        var form = $('#register-form');
        var alertBox = $('#register-error');
        var rules = {
            length: function (v) { return v.length >= 8; },
            upper: function (v) { return /[A-Z]/.test(v); },
            number: function (v) { return /\d/.test(v); },
            symbol: function (v) { return /[^A-Za-z0-9]/.test(v); }
        };

        form.elements.password.addEventListener('input', function () {
            var v = form.elements.password.value;
            $all('[data-rule]', form).forEach(function (li) { li.classList.toggle('is-met', rules[li.getAttribute('data-rule')](v)); });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors(form);
            alertBox.hidden = true;
            var email = form.elements.email.value.trim();
            var password = form.elements.password.value;
            var ok = true;
            if (!EMAIL_RE.test(email)) { setError(form, 'email', 'Enter a valid email'); ok = false; }
            var failed = Object.keys(rules).filter(function (k) { return !rules[k](password); });
            if (failed.length) { setError(form, 'password', 'Password must be at least 8 characters with an uppercase letter, a number and a symbol'); ok = false; }
            if (password !== form.elements.confirmPassword.value) { setError(form, 'confirmPassword', 'Passwords do not match'); ok = false; }
            if (!ok) return;

            var btn = $('#register-btn');
            busy(btn, true, 'Creating account…');
            api('POST', '/auth/register', { email: email, password: password }).then(function (r) {
                busy(btn, false);
                if (!r.ok) {
                    if (!showApiErrors(form, r.data)) { alertBox.textContent = errorText(r.data); alertBox.hidden = false; }
                    return;
                }
                session.save(r.data);
                flash('Account created! Welcome to TicketTrail.');
                go('');
            });
        });
    };

    // ---------- home & events ----------

    pages.home = function () {
        var grid = $('#featured-events');
        skeletons(grid, 3);
        api('GET', '/events?limit=100').then(function (r) {
            grid.innerHTML = '';
            grid.removeAttribute('aria-busy');
            if (!r.ok) return toast(errorText(r.data), 'error');
            var events = r.data.data;
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
        });
        api('GET', '/bookings?limit=1').then(function (r) {
            if (r.ok) $('#stat-bookings').textContent = r.data.pagination.total;
        });
    };

    pages.events = function () {
        var form = $('#event-filters');
        var grid = $('#events-grid');
        var params = new URLSearchParams(location.search);
        var timer;
        ['search', 'category', 'city'].forEach(function (k) { if (params.get(k)) form.elements[k].value = params.get(k); });
        var updatePager = pager($('#events-pager'), load);

        function load(page) {
            page = page || 1;
            var query = new URLSearchParams({ page: page, limit: 9 });
            ['search', 'category', 'city'].forEach(function (k) { var v = form.elements[k].value.trim(); if (v) query.set(k, v); });
            var shown = new URLSearchParams(query);
            shown.delete('limit');
            if (page === 1) shown.delete('page');
            history.replaceState(null, '', location.pathname + (shown.toString() ? '?' + shown : ''));

            skeletons(grid, 3);
            grid.setAttribute('aria-busy', 'true');
            $('#events-error').hidden = true;
            api('GET', '/events?' + query).then(function (r) {
                grid.innerHTML = '';
                grid.removeAttribute('aria-busy');
                if (!r.ok) { $('#events-error').hidden = false; $('#events-pager').hidden = true; return; }
                var p = r.data.pagination;
                r.data.data.forEach(function (e) { grid.appendChild(eventCard(e)); });
                $('#events-empty').hidden = p.total > 0;
                $('#events-count').textContent = p.total + ' event' + (p.total === 1 ? '' : 's') + ' found';
                updatePager(p);
            });
        }

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

        form.addEventListener('input', function () { syncChips(); clearTimeout(timer); timer = setTimeout(function () { load(1); }, 400); });
        form.addEventListener('reset', function () { setTimeout(function () { syncChips(); load(1); }, 0); });
        form.addEventListener('submit', function (e) { e.preventDefault(); load(1); });
        $('#events-error [data-retry]').addEventListener('click', function () { load(1); });
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

        api('GET', '/events/' + encodeURIComponent(id)).then(function (r) {
            if (!r.ok) { $('#event-not-found').hidden = false; $('#crumb-title').textContent = 'Not found'; return; }
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
            form.elements.customerEmail.value = session.user.email || '';
            renderSeats();
            renderQuantity();
            $('#event-detail').hidden = false;
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
            if (!ok) return;

            var btn = $('#confirm-booking-btn');
            busy(btn, true, 'Booking…');
            api('POST', '/bookings', data).then(function (r) {
                busy(btn, false);
                if (!r.ok) {
                    if (!showApiErrors(form, r.data)) toast(errorText(r.data), 'error');
                    return;
                }
                var b = r.data.data;
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
    };

    // ---------- bookings ----------

    pages.bookings = function () {
        var list = $('#bookings-list');
        var current = 1;
        var updatePager = pager($('#bookings-pager'), load);

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
                    confirmLabel: 'Yes, cancel it'
                }, function () {
                    return api('DELETE', '/bookings/' + b.id).then(function (r) {
                        if (!r.ok) return toast(errorText(r.data), 'error');
                        toast(r.data.message);
                        load(current);
                    });
                });
            });
            append(actions, view, cancel);
            side.appendChild(actions);
            return append(box, stub, main, side);
        }

        function load(page) {
            current = page || 1;
            list.setAttribute('aria-busy', 'true');
            $('#bookings-error').hidden = true;
            api('GET', '/bookings?limit=5&page=' + current).then(function (r) {
                list.innerHTML = '';
                list.removeAttribute('aria-busy');
                if (!r.ok) { $('#bookings-error').hidden = false; return; }
                var p = r.data.pagination;
                // Deleting the last booking on a page: step back a page.
                if (!r.data.data.length && current > 1) return load(current - 1);
                r.data.data.forEach(function (b) { list.appendChild(card(b)); });
                $('#bookings-total').textContent = p.total;
                $('#bookings-empty').hidden = p.total > 0;
                $('#clear-bookings-btn').disabled = p.total === 0;
                updatePager(p);
            });
        }

        $('#clear-bookings-btn').addEventListener('click', function () {
            confirmAction({
                title: 'Clear all bookings?',
                description: 'Every booking in your sandbox will be cancelled and its seats released.',
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
        load(1);
    };

    pages['booking-details'] = function () {
        var id = lastSegment();
        api('GET', '/bookings/' + encodeURIComponent(id)).then(function (r) {
            if (!r.ok) { $('#booking-not-found').hidden = false; return; }
            var b = r.data.data;
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
        });
    };

    // ---------- manage ----------

    pages['admin-events'] = function () {
        var form = $('#admin-event-form');
        var tbody = $('#admin-events-body');
        var editing = null;
        var current = 1;
        var updatePager = pager($('#admin-events-pager'), load);

        function resetForm() {
            editing = null;
            form.reset();
            clearErrors(form);
            $('#event-form-title').textContent = 'Add New Event';
            form.classList.remove('is-editing');
            $('#add-event-btn').textContent = 'Add Event';
            $('#cancel-edit-btn').hidden = true;
        }

        function startEdit(e) {
            editing = e;
            clearErrors(form);
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
                    confirmAction({
                        title: 'Delete this event?',
                        description: '"' + e.title + '" and all of its bookings will be permanently deleted.',
                        confirmLabel: 'Yes, delete'
                    }, function () {
                        return api('DELETE', '/events/' + e.id).then(function (r) {
                            if (!r.ok) return toast(errorText(r.data), 'error');
                            toast(r.data.message);
                            if (editing && editing.id === e.id) resetForm();
                            load(current);
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

        function load(page) {
            current = page || 1;
            api('GET', '/events?limit=10&page=' + current).then(function (r) {
                tbody.innerHTML = '';
                if (!r.ok) return toast(errorText(r.data), 'error');
                var p = r.data.pagination;
                if (!r.data.data.length && current > 1) return load(current - 1);
                r.data.data.forEach(function (e) { tbody.appendChild(row(e)); });
                $('#admin-events-count').textContent = p.total + ' events';
                $('#admin-events-empty').hidden = p.total > 0;
                updatePager(p);
            });
            api('GET', '/events?limit=100').then(function (r) {
                if (!r.ok) return;
                var all = r.data.data;
                $('#kpi-total').textContent = r.data.pagination.total;
                $('#kpi-featured').textContent = all.filter(function (e) { return e.isStatic; }).length;
                $('#kpi-custom').textContent = all.filter(function (e) { return !e.isStatic; }).length;
                $('#kpi-soldout').textContent = all.filter(function (e) { return e.availableSeats === 0; }).length;
            });
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
            if (!ok) return;

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
                    if (!showApiErrors(form, r.data)) toast(errorText(r.data), 'error');
                    return;
                }
                toast(r.data.message);
                var wasEditing = !!editing;
                resetForm();
                load(wasEditing ? current : 1);
            });
        });
        $('#cancel-edit-btn').addEventListener('click', resetForm);
        load(1);
    };

    pages['admin-bookings'] = function () {
        var tbody = $('#admin-bookings-body');
        var filter = $('#event-filter');
        var current = 1;
        var modal;
        var updatePager = pager($('#admin-bookings-pager'), load);

        api('GET', '/events?limit=100').then(function (r) {
            if (!r.ok) return;
            r.data.data.forEach(function (e) { filter.appendChild(el('option', { value: e.id }, e.title)); });
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

        function row(b) {
            var tr = el('tr', { 'data-testid': 'booking-table-row', 'data-booking-id': b.id });
            var view = append(el('button', { 'class': 'btn btn-sm btn-tt-ghost me-1', type: 'button', 'data-testid': 'view-booking-btn' }), icon('bi-eye', 'me-1'), document.createTextNode('View'));
            var cancel = append(el('button', { 'class': 'btn btn-sm btn-outline-danger', type: 'button', 'data-testid': 'cancel-booking-btn' }), icon('bi-x-circle', 'me-1'), document.createTextNode('Cancel'));
            view.addEventListener('click', function () { showBooking(b); });
            cancel.addEventListener('click', function () {
                confirmAction({
                    title: 'Cancel this booking?',
                    description: 'This will cancel ' + b.bookingRef + ' and release ' + b.quantity + ' seat(s) back to the event.',
                    confirmLabel: 'Yes, cancel it'
                }, function () {
                    return api('DELETE', '/bookings/' + b.id).then(function (r) {
                        if (!r.ok) return toast(errorText(r.data), 'error');
                        toast(r.data.message);
                        load(current);
                    });
                });
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

        function load(page) {
            current = page || 1;
            var query = new URLSearchParams({ page: current, limit: 10 });
            if (filter.value) query.set('eventId', filter.value);
            api('GET', '/bookings?' + query).then(function (r) {
                tbody.innerHTML = '';
                if (!r.ok) return toast(errorText(r.data), 'error');
                var p = r.data.pagination;
                if (!r.data.data.length && current > 1) return load(current - 1);
                r.data.data.forEach(function (b) { tbody.appendChild(row(b)); });
                $('#admin-bookings-empty').hidden = p.total > 0;
                updatePager(p);
            });
            api('GET', '/bookings?limit=100').then(function (r) {
                if (!r.ok) return;
                var all = r.data.data;
                var events = {};
                var tickets = 0;
                var spent = 0;
                all.forEach(function (b) { tickets += b.quantity; spent += b.totalPrice; events[b.eventId] = true; });
                $('#kpi-bookings').textContent = r.data.pagination.total;
                $('#kpi-tickets').textContent = tickets;
                $('#kpi-revenue').textContent = money(spent);
                $('#kpi-events').textContent = Object.keys(events).length;
            });
        }

        filter.addEventListener('change', function () { load(1); });
        load(1);
    };

    // ---------- boot ----------

    document.addEventListener('DOMContentLoaded', function () {
        var isAuthPage = TT.page === 'login' || TT.page === 'register';
        if (isAuthPage && session.token) return go('');
        if (!isAuthPage && !session.token) {
            flash('Please sign in to continue', 'error');
            return go('/login');
        }
        showFlash();
        initNav();
        if (pages[TT.page]) pages[TT.page]();
    });
})();
