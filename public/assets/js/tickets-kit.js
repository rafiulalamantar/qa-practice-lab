/* TicketTrail UI kit: small, dependency-free building blocks shared by the
   TicketTrail pages (tickets.js). Exposed as window.TTKit.

   - debounce / throttle / throttledButton
   - store            (localStorage that never throws)
   - dataTable        (sortable, paginated, selectable table)
   - autosave         (form drafts kept on this device)
   - smartValidation  (validate on blur, then live; email typo hints)
   - shortcuts        (single keys, "g h" sequences and mod+k) */
(function () {
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

    // ---------- timing ----------

    /** Runs `fn` once the calls stop for `wait` ms. */
    function debounce(fn, wait) {
        var timer;
        function debounced() {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
        }
        debounced.cancel = function () { clearTimeout(timer); };
        return debounced;
    }

    /** Leading-edge throttle: runs at most once per `wait` ms. Returns false when a call is dropped. */
    function throttle(fn, wait) {
        var last = 0;
        function throttled() {
            var now = Date.now();
            if (now - last < wait) return false;
            last = now;
            fn.apply(this, arguments);
            return true;
        }
        throttled.remaining = function () { return Math.max(0, wait - (Date.now() - last)); };
        return throttled;
    }

    /**
     * A button whose action is throttled: after a click it shows a countdown
     * badge (data-testid="cooldown") and ignores clicks until it ends.
     */
    function throttledButton(button, wait, action) {
        var run = throttle(action, wait);
        var badge = el('span', { 'class': 'tt-cooldown', 'data-testid': 'cooldown', 'aria-hidden': 'true' });
        var timer;

        function tick() {
            var left = Math.ceil(run.remaining() / 1000);
            if (left <= 0) {
                badge.remove();
                button.disabled = false;
                button.removeAttribute('data-cooling');
                return;
            }
            badge.textContent = left + 's';
            if (!badge.parentNode) button.appendChild(badge);
            timer = setTimeout(tick, 250);
        }

        function trigger() {
            if (!run()) return false;
            clearTimeout(timer);
            button.disabled = true;
            button.setAttribute('data-cooling', 'true');
            tick();
            return true;
        }

        button.addEventListener('click', trigger);
        return { trigger: trigger };
    }

    // ---------- storage ----------

    var store = {
        get: function (key, fallback) {
            try {
                var raw = localStorage.getItem(key);
                return raw === null ? fallback : JSON.parse(raw);
            } catch (e) { return fallback; }
        },
        set: function (key, value) {
            try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch (e) { return false; }
        },
        remove: function (key) {
            try { localStorage.removeItem(key); } catch (e) { }
        }
    };

    function clock(ts) {
        return new Date(ts).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    // ---------- reusable data table ----------

    /**
     * Sortable, paginated, selectable table.
     *
     * opts: {
     *   table, body, pager (nav with data-page-prev/next/label), empty,
     *   pageSize, pageSizeSelect, storageKey,
     *   sorters: { key: row => comparable }, defaultSort: { key, dir },
     *   renderRow: row => <tr>, rowId: row => id,
     *   selectable: row => bool (omit for no selection), onSelectionChange: ids => void,
     *   onPage: (page, size) => void   // server paging: fetch, then call setRows(rows, pagination)
     * }
     * Header cells with data-sort="key" become sort buttons. With onPage, sorting
     * applies to the rows of the current page. Selection checkboxes sit inside the
     * first cell, so column positions stay the same as a plain table.
     */
    function dataTable(opts) {
        var saved = {};
        try { saved = (opts.storageKey && JSON.parse(sessionStorage.getItem(opts.storageKey))) || {}; } catch (e) { }
        var state = {
            rows: [],
            page: 1,
            size: saved.size || opts.pageSize || 10,
            sort: saved.sort || opts.defaultSort || null,
            selected: {},
            remote: !!opts.onPage,
            total: 0,
            totalPages: 1
        };
        var headRow = opts.table.querySelector('thead tr');
        var headCheck;

        if (opts.selectable) {
            headCheck = el('input', { type: 'checkbox', 'class': 'form-check-input tt-row-check', 'aria-label': 'Select all rows on this page', 'data-testid': 'select-all-rows' });
            headRow.firstElementChild.insertBefore(headCheck, headRow.firstElementChild.firstChild);
            headCheck.addEventListener('change', function () {
                pageRows().filter(opts.selectable).forEach(function (row) {
                    if (headCheck.checked) state.selected[opts.rowId(row)] = true;
                    else delete state.selected[opts.rowId(row)];
                });
                render();
                selectionChanged();
            });
        }

        Array.prototype.forEach.call(headRow.querySelectorAll('th[data-sort]'), function (th) {
            var key = th.getAttribute('data-sort');
            var button = el('button', { type: 'button', 'class': 'tt-sort', 'data-testid': 'sort-' + key });
            Array.prototype.slice.call(th.childNodes).forEach(function (node) { if (node !== headCheck) button.appendChild(node); });
            button.appendChild(el('i', { 'class': 'bi tt-sort-icon', 'aria-hidden': 'true' }));
            th.appendChild(button);
            button.addEventListener('click', function () {
                var dir = state.sort && state.sort.key === key && state.sort.dir === 'asc' ? 'desc' : 'asc';
                state.sort = { key: key, dir: dir };
                if (!state.remote) state.page = 1;
                remember();
                render();
            });
        });

        if (opts.pageSizeSelect) {
            opts.pageSizeSelect.value = String(state.size);
            opts.pageSizeSelect.addEventListener('change', function () {
                state.size = Number(opts.pageSizeSelect.value) || 10;
                remember();
                goTo(1);
            });
        }

        if (opts.pager) {
            opts.pager.querySelector('[data-page-prev]').addEventListener('click', function () { if (state.page > 1) goTo(state.page - 1); });
            opts.pager.querySelector('[data-page-next]').addEventListener('click', function () { if (state.page < totalPages()) goTo(state.page + 1); });
        }

        function goTo(page) {
            if (state.remote) { opts.onPage(page, state.size); return; }
            state.page = page;
            render();
        }

        /** Saved for this session only, so every new browser session starts with the default order. */
        function remember() {
            if (!opts.storageKey) return;
            try { sessionStorage.setItem(opts.storageKey, JSON.stringify({ size: state.size, sort: state.sort })); } catch (e) { }
        }

        function sortedRows() {
            var rows = state.rows.slice();
            var sorter = state.sort && opts.sorters && opts.sorters[state.sort.key];
            if (!sorter) return rows;
            var factor = state.sort.dir === 'desc' ? -1 : 1;
            return rows.sort(function (a, b) {
                var x = sorter(a);
                var y = sorter(b);
                if (typeof x === 'string') return x.localeCompare(y) * factor;
                return (x > y ? 1 : x < y ? -1 : 0) * factor;
            });
        }

        function totalPages() { return state.remote ? state.totalPages : Math.max(1, Math.ceil(state.rows.length / state.size)); }

        function pageRows() {
            if (state.remote) return sortedRows();
            var start = (state.page - 1) * state.size;
            return sortedRows().slice(start, start + state.size);
        }

        function labels() {
            return Array.prototype.map.call(headRow.children, function (th) { return th.textContent.trim(); });
        }

        function render() {
            if (!state.remote) state.page = Math.min(state.page, totalPages());
            var names = labels();
            opts.body.innerHTML = '';
            pageRows().forEach(function (row) {
                var tr = opts.renderRow(row);
                if (opts.selectable && opts.selectable(row)) {
                    var id = opts.rowId(row);
                    var box = el('input', { type: 'checkbox', 'class': 'form-check-input tt-row-check', 'aria-label': 'Select row', 'data-testid': 'row-checkbox', value: id });
                    box.checked = !!state.selected[id];
                    box.addEventListener('change', function () {
                        if (box.checked) state.selected[id] = true;
                        else delete state.selected[id];
                        tr.classList.toggle('is-selected', box.checked);
                        syncHeadCheck();
                        selectionChanged();
                    });
                    tr.classList.toggle('is-selected', box.checked);
                    tr.firstElementChild.insertBefore(box, tr.firstElementChild.firstChild);
                }
                Array.prototype.forEach.call(tr.children, function (td, i) { if (names[i]) td.setAttribute('data-label', names[i]); });
                opts.body.appendChild(tr);
            });
            opts.table.removeAttribute('aria-busy');
            if (opts.empty) opts.empty.hidden = api.total() > 0;

            Array.prototype.forEach.call(headRow.querySelectorAll('th[data-sort]'), function (th) {
                var active = state.sort && state.sort.key === th.getAttribute('data-sort');
                th.setAttribute('aria-sort', active ? (state.sort.dir === 'asc' ? 'ascending' : 'descending') : 'none');
                th.querySelector('.tt-sort-icon').className = 'bi tt-sort-icon ' + (active ? (state.sort.dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up');
            });

            if (opts.pager) {
                var pages = totalPages();
                opts.pager.hidden = pages <= 1;
                opts.pager.querySelector('[data-page-label]').textContent = 'Page ' + state.page + ' of ' + pages;
                opts.pager.querySelector('[data-page-prev]').disabled = state.page <= 1;
                opts.pager.querySelector('[data-page-next]').disabled = state.page >= pages;
            }
            syncHeadCheck();
        }

        function syncHeadCheck() {
            if (!headCheck) return;
            var selectable = pageRows().filter(opts.selectable);
            var on = selectable.filter(function (row) { return state.selected[opts.rowId(row)]; }).length;
            headCheck.checked = selectable.length > 0 && on === selectable.length;
            headCheck.indeterminate = on > 0 && on < selectable.length;
            headCheck.disabled = selectable.length === 0;
        }

        function selectionChanged() {
            if (opts.onSelectionChange) opts.onSelectionChange(api.selected());
        }

        var api = {
            /** Local mode: all rows. Server mode: one page of rows plus the API's pagination object. */
            setRows: function (rows, pagination) {
                state.rows = rows.slice();
                if (pagination) {
                    state.remote = true;
                    state.page = pagination.page;
                    state.total = pagination.total;
                    state.totalPages = Math.max(1, pagination.totalPages);
                } else {
                    var ids = {};
                    rows.forEach(function (row) { ids[opts.rowId(row)] = true; });
                    Object.keys(state.selected).forEach(function (id) { if (!ids[id]) delete state.selected[id]; });
                }
                render();
                selectionChanged();
            },
            rows: function () { return state.rows.slice(); },
            total: function () { return state.remote ? state.total : state.rows.length; },
            page: function () { return state.page; },
            size: function () { return state.size; },
            /** Shimmering placeholder rows while data loads. */
            loading: function (count) {
                var cols = headRow.children.length;
                opts.body.innerHTML = '';
                opts.table.setAttribute('aria-busy', 'true');
                if (opts.empty) opts.empty.hidden = true;
                for (var i = 0; i < (count || 4); i++) {
                    var tr = el('tr', { 'class': 'tt-skeleton-row', 'data-testid': 'skeleton-row', 'aria-hidden': 'true' });
                    for (var c = 0; c < cols; c++) tr.appendChild(append(el('td'), el('span', { 'class': 'tt-skel-line', style: 'width:' + (40 + ((i + c) * 17) % 50) + '%' })));
                    opts.body.appendChild(tr);
                }
            },
            selected: function () { return Object.keys(state.selected).map(function (id) { return isNaN(id) ? id : Number(id); }); },
            /** Select every row, or the given ids (e.g. all ids across server pages). */
            selectAll: function (ids) {
                (ids || state.rows.filter(opts.selectable).map(opts.rowId)).forEach(function (id) { state.selected[id] = true; });
                render();
                selectionChanged();
            },
            clearSelection: function () { state.selected = {}; render(); selectionChanged(); },
            /** Optimistically drop rows; returns them so they can be put back. */
            remove: function (ids) {
                var drop = {};
                ids.forEach(function (id) { drop[id] = true; delete state.selected[id]; });
                var removed = state.rows.filter(function (row) { return drop[opts.rowId(row)]; });
                state.rows = state.rows.filter(function (row) { return !drop[opts.rowId(row)]; });
                state.total = Math.max(0, state.total - removed.length);
                render();
                selectionChanged();
                return removed;
            },
            restore: function (rows) {
                state.rows = state.rows.concat(rows);
                state.total += rows.length;
                render();
                selectionChanged();
            },
            render: render
        };
        return api;
    }

    // ---------- autosave ----------

    /**
     * Keeps a draft of `fields` in localStorage while the user types.
     * The draft is never applied on its own: call restore() (e.g. from a
     * "Restore draft" button) so automated tests always start from a clean form.
     */
    function autosave(form, opts) {
        var status = opts.status;
        var delay = opts.delay || 800;

        function values() {
            var data = {};
            opts.fields.forEach(function (name) { data[name] = form.elements[name] ? form.elements[name].value : ''; });
            return data;
        }

        function isBlank(data) {
            return Object.keys(data).every(function (k) { return String(data[k]).trim() === '' || (opts.ignoreDefaults && opts.ignoreDefaults[k] === data[k]); });
        }

        function setStatus(state, text) {
            if (!status) return;
            status.setAttribute('data-state', state);
            status.textContent = text;
        }

        var save = debounce(function () {
            var data = values();
            if (isBlank(data)) {
                store.remove(opts.key);
                setStatus('idle', '');
                return;
            }
            var savedAt = Date.now();
            if (store.set(opts.key, { values: data, savedAt: savedAt })) {
                setStatus('saved', (navigator.onLine === false ? 'Saved on this device · ' : 'Draft saved · ') + clock(savedAt));
            } else {
                setStatus('error', 'Couldn’t save draft');
            }
        }, delay);

        function onEdit() {
            if (opts.enabled && !opts.enabled()) return;
            setStatus('saving', 'Saving draft…');
            save();
        }

        form.addEventListener('input', onEdit);
        form.addEventListener('change', onEdit);

        return {
            /** The stored draft, if any: { values, savedAt }. */
            pending: function () {
                var draft = store.get(opts.key, null);
                return draft && draft.values && !isBlank(draft.values) ? draft : null;
            },
            restore: function () {
                var draft = this.pending();
                if (!draft) return null;
                Object.keys(draft.values).forEach(function (name) { if (form.elements[name]) form.elements[name].value = draft.values[name]; });
                setStatus('saved', 'Draft restored · ' + clock(draft.savedAt));
                return draft;
            },
            clear: function () {
                save.cancel();
                store.remove(opts.key);
                setStatus('idle', '');
            },
            clock: clock
        };
    }

    // ---------- smart form validation ----------

    var EMAIL_DOMAINS = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com', 'live.com', 'proton.me', 'example.com'];

    function distance(a, b) {
        var row = [];
        for (var j = 0; j <= b.length; j++) row[j] = j;
        for (var i = 1; i <= a.length; i++) {
            var prev = row[0];
            row[0] = i;
            for (j = 1; j <= b.length; j++) {
                var tmp = row[j];
                row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + (a[i - 1] === b[j - 1] ? 0 : 1));
                prev = tmp;
            }
        }
        return row[b.length];
    }

    /** "ada@gmial.com" -> "ada@gmail.com"; null when nothing close. */
    function suggestEmail(value) {
        var at = value.lastIndexOf('@');
        if (at < 1) return null;
        var domain = value.slice(at + 1).toLowerCase();
        if (!domain || EMAIL_DOMAINS.indexOf(domain) !== -1) return null;
        var best = null;
        EMAIL_DOMAINS.forEach(function (candidate) {
            var d = distance(domain, candidate);
            if (d > 0 && d <= 2 && (!best || d < best.d)) best = { d: d, domain: candidate };
        });
        return best ? value.slice(0, at + 1) + best.domain : null;
    }

    /**
     * Live validation layered on top of a form's own submit checks.
     * rules: { fieldName: (value, form) => '' | 'error message' }
     * opts.dependents: { password: ['confirmPassword'] } re-checks linked fields.
     * opts.emailFields: field names that get a "Did you mean …?" hint.
     */
    function smartValidation(form, rules, opts) {
        opts = opts || {};
        var touched = {};

        function feedback(name) { return form.querySelector('[data-error-for="' + name + '"]'); }

        function check(name) {
            var input = form.elements[name];
            if (!input || !rules[name]) return true;
            var message = rules[name](input.value, form) || '';
            input.classList.toggle('is-invalid', !!message);
            input.classList.toggle('is-valid', !message && input.value.trim() !== '');
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
            var box = feedback(name);
            if (box) {
                box.textContent = message;
                if (box.id) input.setAttribute('aria-describedby', box.id);
            }
            return !message;
        }

        Object.keys(rules).forEach(function (name) {
            var input = form.elements[name];
            if (!input) return;
            input.addEventListener('blur', function () {
                if (input.value.trim() === '' && !touched[name]) return;
                touched[name] = true;
                check(name);
            });
            input.addEventListener('input', function () {
                if (touched[name]) check(name);
                (opts.dependents && opts.dependents[name] || []).forEach(function (dep) { if (touched[dep]) check(dep); });
            });
        });

        (opts.emailFields || []).forEach(function (name) {
            var input = form.elements[name];
            if (!input) return;
            var hint = el('div', { 'class': 'tt-suggest', 'data-testid': 'email-suggestion', hidden: '' });
            var fix = el('button', { type: 'button', 'class': 'btn btn-link p-0 align-baseline', 'data-testid': 'email-suggestion-btn' });
            append(hint, el('i', { 'class': 'bi bi-magic', 'aria-hidden': 'true' }), document.createTextNode(' Did you mean '), fix, document.createTextNode('?'));
            input.closest('.mb-3, .mb-4, .tt-field') ? input.closest('.mb-3, .mb-4, .tt-field').appendChild(hint) : input.parentNode.appendChild(hint);
            function update() {
                var suggestion = suggestEmail(input.value.trim());
                hint.hidden = !suggestion;
                fix.textContent = suggestion || '';
            }
            input.addEventListener('blur', update);
            input.addEventListener('input', function () { if (!hint.hidden) update(); });
            fix.addEventListener('click', function () {
                input.value = fix.textContent;
                hint.hidden = true;
                touched[name] = true;
                check(name);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            });
        });

        return {
            check: check,
            /** Checks every field, focuses the first invalid one; returns true when all pass. */
            validateAll: function () {
                var firstBad = null;
                Object.keys(rules).forEach(function (name) {
                    touched[name] = true;
                    if (!check(name) && !firstBad) firstBad = form.elements[name];
                });
                if (firstBad) firstBad.focus();
                return !firstBad;
            },
            focusFirstInvalid: function () {
                var bad = form.querySelector('.is-invalid');
                if (bad && bad.focus) bad.focus();
            },
            reset: function () {
                touched = {};
                Array.prototype.forEach.call(form.querySelectorAll('.is-valid, .is-invalid'), function (i) { i.classList.remove('is-valid', 'is-invalid'); i.removeAttribute('aria-invalid'); });
                Array.prototype.forEach.call(form.querySelectorAll('[data-testid="email-suggestion"]'), function (h) { h.hidden = true; });
            }
        };
    }

    // ---------- keyboard shortcuts ----------

    function isTyping(target) {
        if (!target) return false;
        var tag = target.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable;
    }

    /**
     * bindings: { 'mod+k': fn, '/': fn, '?': fn, 'g h': fn, ... }
     * Plain keys are ignored while typing in a field; mod+ combos always work.
     */
    function shortcuts(bindings) {
        var pending = null;
        var pendingTimer;

        document.addEventListener('keydown', function (e) {
            var key = e.key && e.key.length === 1 ? e.key.toLowerCase() : e.key;
            if ((e.metaKey || e.ctrlKey) && !e.altKey && key === 'k' && bindings['mod+k']) {
                e.preventDefault();
                bindings['mod+k'](e);
                return;
            }
            if (e.metaKey || e.ctrlKey || e.altKey || isTyping(e.target)) return;
            if (document.querySelector('.modal.show')) return;
            if (e.key === '?') key = '?';

            if (pending) {
                var combo = pending + ' ' + key;
                pending = null;
                clearTimeout(pendingTimer);
                if (bindings[combo]) { e.preventDefault(); bindings[combo](e); }
                return;
            }
            var startsSequence = Object.keys(bindings).some(function (b) { return b.indexOf(key + ' ') === 0; });
            if (startsSequence) {
                pending = key;
                pendingTimer = setTimeout(function () { pending = null; }, 1200);
                return;
            }
            if (bindings[key]) { e.preventDefault(); bindings[key](e); }
        });
    }

    window.TTKit = {
        el: el,
        append: append,
        debounce: debounce,
        throttle: throttle,
        throttledButton: throttledButton,
        store: store,
        clock: clock,
        dataTable: dataTable,
        autosave: autosave,
        suggestEmail: suggestEmail,
        smartValidation: smartValidation,
        shortcuts: shortcuts
    };
})();
