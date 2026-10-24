/* TicketTrail front end. Every page is a thin Blade shell; all data comes from
   the /api/tickets REST API using the token kept in localStorage (tt_token,
   tt_user), so learners can log in through the API and inject the session. */
(function () {
    var TT = window.TT;
    var FLASH_KEY = 'tt.flash';
    var CATEGORY_STYLE = { Conference: 'primary', Concert: 'danger', Sports: 'success', Workshop: 'info', Festival: 'warning' };

    // ---------- helpers ----------

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function go(path) { location.href = TT.base + path; }
    function lastSegment() { return decodeURIComponent(location.pathname.split('/').filter(Boolean).pop()); }

    function money(n) {
        n = Number(n);
        return n === 0 ? 'Free' : '\u09F3' + n.toLocaleString('en-US', { maximumFractionDigits: 2 });
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
        var box = el('div', { 'class': 'toast-item toast-' + (type || 'success'), role: 'alert', 'data-testid': 'toast' });
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
        if (available === 0) return { text: 'SOLD OUT', cls: 'text-danger' };
        if (available <= 10) return { text: available + ' seat' + (available > 1 ? 's' : '') + ' left!', cls: 'text-warning-emphasis' };
        return { text: available.toLocaleString('en-US') + ' seats available', cls: 'text-success' };
    }

    function categoryBadge(category) {
        return el('span', { 'class': 'badge rounded-pill text-bg-' + (CATEGORY_STYLE[category] || 'secondary') }, category);
    }

    function eventCard(e) {
        var soldOut = e.availableSeats === 0;
        var col = el('div', { 'class': 'col-sm-6 col-lg-4' });
        var card = el('article', { 'class': 'tt-event-card h-100' + (soldOut ? ' is-sold-out' : ''), 'data-testid': 'event-card', 'data-event-id': e.id });

        var media = el('div', { 'class': 'tt-event-media' });
        var img = el('img', { src: imageFor(e), alt: '', loading: 'lazy' });
        img.addEventListener('error', function () { img.src = TT.images[e.category]; }, { once: true });
        var badges = append(el('div', { 'class': 'tt-badges' }), categoryBadge(e.category));
        if (e.isStatic) badges.appendChild(el('span', { 'class': 'badge rounded-pill bg-success', 'data-testid': 'featured-badge' }, 'Featured'));
        append(media, img, badges);
        if (soldOut) media.appendChild(append(el('div', { 'class': 'tt-sold-out' }), el('span', {}, 'SOLD OUT')));

        var body = el('div', { 'class': 'p-3 d-flex flex-column flex-grow-1' });
        var title = el('h3', { 'class': 'h6 mb-2' });
        title.appendChild(el('a', { href: TT.base + '/events/' + e.id, 'class': 'text-reset text-decoration-none', 'data-testid': 'event-title' }, e.title));
        var seats = seatsText(e.availableSeats);
        append(body,
            title,
            el('div', { 'class': 'small text-secondary' }, '📅 ' + formatDate(e.eventDate)),
            el('div', { 'class': 'small text-secondary mb-3' }, '📍 ' + e.venue + ', ' + e.city)
        );
        var footer = el('div', { 'class': 'mt-auto d-flex justify-content-between align-items-end' });
        var price = append(el('div'),
            el('div', { 'class': 'fw-bold text-tt', 'data-testid': 'event-price' }, money(e.price)),
            el('div', { 'class': 'small fw-semibold ' + seats.cls, 'data-testid': 'event-seats' }, seats.text));
        var book = el('a', { 'class': 'btn btn-sm btn-tt' + (soldOut ? ' disabled' : ''), href: TT.base + '/events/' + e.id, 'data-testid': 'book-now-btn', 'aria-disabled': soldOut ? 'true' : 'false' }, soldOut ? 'Sold Out' : 'Book Now');
        append(footer, price, book);
        body.appendChild(footer);
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
            r.data.data.filter(function (e) { return e.isStatic; }).slice(0, 3).forEach(function (e) { grid.appendChild(eventCard(e)); });
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

        form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { load(1); }, 400); });
        form.addEventListener('reset', function () { setTimeout(function () { load(1); }, 0); });
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
            $('#event-seats').className = 'fw-semibold ' + seats.cls;
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
            $('#event-category').textContent = event.category;
            $('#event-category').className = 'badge rounded-pill text-bg-' + (CATEGORY_STYLE[event.category] || 'secondary');
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
            var box = el('article', { 'class': 'tt-booking-card p-3 p-md-4', 'data-testid': 'booking-card', 'data-booking-id': b.id });
            var top = el('div', { 'class': 'd-flex flex-wrap justify-content-between gap-2' });
            var left = el('div');
            append(left,
                el('span', { 'class': 'tt-ref', 'data-testid': 'booking-ref' }, b.bookingRef),
                el('h2', { 'class': 'h5 mt-2 mb-1', 'data-testid': 'booking-event-title' }, e.title || 'Event removed'));
            var meta = el('div', { 'class': 'small text-secondary d-flex flex-wrap gap-3' });
            append(meta,
                e.eventDate ? el('span', {}, '📅 ' + formatDate(e.eventDate)) : null,
                el('span', { 'data-testid': 'booking-quantity' }, '🎫 ' + b.quantity + ' ticket' + (b.quantity > 1 ? 's' : '')),
                e.city ? el('span', {}, '📍 ' + e.city) : null);
            left.appendChild(meta);
            var right = el('div', { 'class': 'text-md-end' });
            append(right,
                el('div', { 'class': 'fs-5 fw-bold text-tt', 'data-testid': 'booking-total' }, money(b.totalPrice)),
                el('span', { 'class': 'badge text-bg-success text-capitalize', 'data-testid': 'booking-status' }, b.status));
            append(top, left, right);

            var actions = el('div', { 'class': 'd-flex gap-2 mt-3' });
            var view = el('a', { 'class': 'btn btn-sm btn-outline-secondary', href: TT.base + '/bookings/' + b.id, 'data-testid': 'view-booking-btn' }, 'View Details');
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
            return append(box, top, actions);
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
                    result.className = 'alert small mb-0 ' + (eligible ? 'alert-success' : 'alert-danger');
                    result.setAttribute('data-eligible', eligible ? 'true' : 'false');
                    result.innerHTML = '';
                    append(result,
                        el('strong', {}, eligible ? 'Eligible for refund.' : 'Not eligible for refund.'),
                        document.createTextNode(eligible ? ' Single-ticket bookings qualify for a full refund.' : ' Group bookings (' + b.quantity + ' tickets) are non-refundable.'));
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
            $('#add-event-btn').textContent = 'Update Event';
            $('#cancel-edit-btn').hidden = false;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            form.elements.title.focus();
        }

        function row(e) {
            var tr = el('tr', { 'data-testid': 'event-table-row', 'data-event-id': e.id });
            var title = el('td', { 'class': 'fw-semibold' });
            title.appendChild(el('a', { href: TT.base + '/events/' + e.id, 'class': 'text-reset' }, e.title));
            if (e.isStatic) title.appendChild(el('span', { 'class': 'badge bg-success-subtle text-success-emphasis ms-2' }, 'Featured'));
            var seats = el('td', { 'class': 'fw-semibold ' + (e.availableSeats === 0 ? 'text-danger' : e.availableSeats <= 10 ? 'text-warning-emphasis' : 'text-success') }, e.availableSeats + '/' + e.totalSeats);
            var actions = el('td', { 'class': 'text-nowrap' });
            if (e.isStatic) {
                actions.appendChild(el('span', { 'class': 'small text-secondary fst-italic' }, 'Read-only'));
            } else {
                var edit = el('button', { 'class': 'btn btn-sm btn-outline-primary me-1', type: 'button', 'data-testid': 'edit-event-btn' }, 'Edit');
                var del = el('button', { 'class': 'btn btn-sm btn-outline-danger', type: 'button', 'data-testid': 'delete-event-btn' }, 'Delete');
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
                append(el('td'), categoryBadge(e.category)),
                el('td', { 'class': 'small text-nowrap' }, formatDate(e.eventDate, false)),
                el('td', {}, e.city),
                el('td', {}, money(e.price)),
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
            var view = el('button', { 'class': 'btn btn-sm btn-outline-secondary me-1', type: 'button', 'data-testid': 'view-booking-btn' }, 'View');
            var cancel = el('button', { 'class': 'btn btn-sm btn-outline-danger', type: 'button', 'data-testid': 'cancel-booking-btn' }, 'Cancel');
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
            var customer = append(el('td'), el('div', { 'class': 'fw-semibold' }, b.customerName), el('div', { 'class': 'small text-secondary' }, b.customerEmail));
            return append(tr,
                append(el('td'), el('span', { 'class': 'tt-ref' }, b.bookingRef)),
                el('td', {}, b.event ? b.event.title : '—'),
                customer,
                el('td', { 'class': 'text-center fw-semibold' }, b.quantity),
                el('td', { 'class': 'fw-semibold' }, money(b.totalPrice)),
                el('td', { 'class': 'small text-nowrap' }, formatDate(b.createdAt)),
                append(el('td', { 'class': 'text-nowrap' }), view, cancel));
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
