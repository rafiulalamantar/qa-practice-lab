/* ShopSphere front end. Every page is a thin Blade shell; all data comes from
   the /api/ecom REST API using the token kept in localStorage, so learners
   can log in through the API and inject the token, or mock any response. */
(function () {
    var SHOP = window.SHOP;
    var FLASH_KEY = 'shop.flash';
    var ORDERS_KEY = 'shop.lastOrders';
    var pending = 0;

    // ---------- helpers ----------

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function money(n) { return '$ ' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }); }
    function go(path) { location.href = SHOP.base + path; }
    function lastSegment() { return decodeURIComponent(location.pathname.split('/').filter(Boolean).pop()); }

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
        if (text !== undefined) node.textContent = text;
        return node;
    }

    var session = {
        get token() { try { return localStorage.getItem('token'); } catch (e) { return null; } },
        get userId() { try { return localStorage.getItem('userId'); } catch (e) { return null; } },
        get email() { try { return localStorage.getItem('userEmail'); } catch (e) { return null; } },
        save: function (data) {
            try {
                localStorage.setItem('token', data.token);
                localStorage.setItem('userId', data.userId);
                localStorage.setItem('userEmail', data.email);
            } catch (e) { /* storage blocked */ }
        },
        clear: function () {
            try { ['token', 'userId', 'userEmail'].forEach(function (k) { localStorage.removeItem(k); }); } catch (e) { }
        }
    };

    function toast(message, type) {
        var box = el('div', { 'class': 'toast-item toast-' + (type || 'success'), role: 'alert', 'aria-label': message });
        box.appendChild(el('div', { 'class': 'toast-message' }, message));
        box.addEventListener('click', function () { box.remove(); });
        $('#toast-container').appendChild(box);
        setTimeout(function () { box.remove(); }, 3500);
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

    function loading(delta) {
        pending = Math.max(0, pending + delta);
        $('#loader').hidden = pending === 0;
    }

    function firstError(body) {
        if (body && body.errors) {
            var key = Object.keys(body.errors)[0];
            return body.errors[key][0];
        }
        return (body && body.message) || 'Something went wrong';
    }

    /** fetch wrapper: JSON in/out, auth header, loader, 401 handling. */
    function api(method, path, body, opts) {
        opts = opts || {};
        var headers = { 'Accept': 'application/json' };
        if (body) headers['Content-Type'] = 'application/json';
        if (session.token) headers['Authorization'] = session.token;
        if (Chaos.enabled) headers['X-Chaos-Mode'] = '1';

        loading(1);
        return fetch(SHOP.api + path, { method: method, headers: headers, body: body ? JSON.stringify(body) : undefined })
            .then(function (res) {
                if (opts.blob && res.ok) return res.blob().then(function (b) { return { ok: true, status: res.status, blob: b }; });
                return res.json().catch(function () { return {}; }).then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (result) {
                if (result.status === 401 && !opts.allow401) {
                    session.clear();
                    flash('Session expired, please login again', 'error');
                    go('/auth/login');
                    return new Promise(function () { });
                }
                return result;
            })
            .catch(function () {
                toast('Network error, please retry', 'error');
                return { ok: false, status: 0, data: {} };
            })
            .finally(function () { loading(-1); });
    }

    function refreshCartCount() {
        api('GET', '/user/get-cart-count').then(function (r) {
            if (r.ok && $('#cart-count')) $('#cart-count').textContent = r.data.count;
        });
    }

    function addToCart(productId) {
        return api('POST', '/user/add-to-cart', { productId: Number(productId) }).then(function (r) {
            if (!r.ok) return toast(firstError(r.data), 'error');
            toast(r.data.message);
            $('#cart-count').textContent = r.data.count;
        });
    }

    function markInvalid(form, rules) {
        var ok = true;
        Object.keys(rules).forEach(function (name) {
            var input = form.elements[name];
            var valid = rules[name](input);
            input.classList.toggle('is-invalid', !valid);
            ok = ok && valid;
        });
        return ok;
    }

    function formData(form) {
        var data = {};
        $all('input[name], select[name]', form).forEach(function (input) {
            if ((input.type === 'radio' || input.type === 'checkbox') && !input.checked) return;
            data[input.name] = input.value.trim();
        });
        return data;
    }

    // ---------- navigation chrome ----------

    function initNav() {
        $all('[data-nav]').forEach(function (btn) {
            btn.addEventListener('click', function () { go('/dashboard/' + btn.getAttribute('data-nav')); });
        });
        var signOut = $('#sign-out');
        if (signOut) {
            signOut.addEventListener('click', function () {
                api('POST', '/auth/logout', null, { allow401: true }).then(function () {
                    session.clear();
                    flash('Logout Successfully');
                    go('/auth/login');
                });
            });
        }
    }

    // ---------- auth pages ----------

    var pages = {};

    pages.login = function () {
        if (session.token) return go('/dashboard/dash');
        var form = $('#login-form');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var valid = markInvalid(form, {
                userEmail: function (i) { return i.value.trim() !== ''; },
                userPassword: function (i) { return i.value !== ''; }
            });
            if (!valid) return;
            var data = { userEmail: form.elements.userEmail.value.trim(), userPassword: form.elements.userPassword.value };
            api('POST', '/auth/login', data, { allow401: true }).then(function (r) {
                if (!r.ok) return toast(firstError(r.data), 'error');
                session.save({ token: r.data.token, userId: r.data.userId, email: data.userEmail });
                flash(r.data.message);
                go('/dashboard/dash');
            });
        });
    };

    pages.register = function () {
        var form = $('#register-form');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var valid = markInvalid(form, {
                firstName: function (i) { return i.value.trim() !== ''; },
                lastName: function (i) { return i.value.trim() !== ''; },
                userEmail: function (i) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(i.value.trim()); },
                userMobile: function (i) { return /^\d{10}$/.test(i.value.trim()); },
                required: function (i) { return i.checked; }
            });
            if (!valid) return;
            var data = formData(form);
            delete data.required;
            data.userPassword = form.elements.userPassword.value;
            data.confirmPassword = form.elements.confirmPassword.value;
            if (data.userPassword !== data.confirmPassword) {
                return toast('Password and Confirm Password must match with each other.', 'error');
            }
            api('POST', '/auth/register', data).then(function (r) {
                if (!r.ok) return toast(firstError(r.data), 'error');
                toast(r.data.message);
                $('#register-panel').hidden = true;
                $('#register-success').hidden = false;
            });
        });
    };

    pages['password-new'] = function () {
        var form = $('#password-form');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var data = formData(form);
            data.userPassword = form.elements.userPassword.value;
            data.confirmPassword = form.elements.confirmPassword.value;
            if (!data.userEmail) return toast('Please Enter Email', 'error');
            if (!data.userPassword) return toast('Please Enter New password', 'error');
            api('POST', '/auth/password-new', data).then(function (r) {
                if (!r.ok) return toast(firstError(r.data), 'error');
                flash(r.data.message);
                go('/auth/login');
            });
        });
    };

    // ---------- dashboard pages ----------

    pages.dash = function () {
        var form = $('#filters');
        var timer;

        function filters() {
            var body = {};
            var name = form.elements.search.value.trim();
            if (name) body.productName = name;
            if (form.elements.minPrice.value !== '') body.minPrice = Number(form.elements.minPrice.value);
            if (form.elements.maxPrice.value !== '') body.maxPrice = Number(form.elements.maxPrice.value);
            ['productCategory', 'productSubCategory', 'productFor'].forEach(function (field) {
                var values = $all('input[name="' + field + '"]:checked', form).map(function (i) { return i.value; });
                if (values.length) body[field] = values;
            });
            return body;
        }

        function render(products) {
            var grid = $('#products');
            grid.innerHTML = '';
            $('#res').textContent = 'Showing ' + products.length + ' results';
            $('#no-products').hidden = products.length > 0;
            products.forEach(function (p) {
                var col = el('div', { 'class': 'col-sm-6 col-xl-4 mb-1' });
                var card = el('div', { 'class': 'card h-100' });
                var img = el('img', { 'class': 'product-img card-img-top', src: p.productImage, alt: p.productName });
                var bodyEl = el('div', { 'class': 'card-body d-flex flex-column' });
                var title = el('h5');
                title.appendChild(el('b', {}, p.productName));
                bodyEl.appendChild(title);
                bodyEl.appendChild(el('div', { 'class': 'text-muted mb-3' }, money(p.productPrice)));
                var actions = el('div', { 'class': 'mt-auto d-flex gap-2' });
                var view = el('button', { 'class': 'btn btn-outline-secondary', type: 'button' }, 'View');
                var add = el('button', { 'class': 'btn btn-lab w-10 rounded', type: 'button' }, ' Add To Cart');
                view.addEventListener('click', function () { go('/dashboard/product-details/' + p._id); });
                add.addEventListener('click', function () { addToCart(p._id); });
                actions.appendChild(view);
                actions.appendChild(add);
                bodyEl.appendChild(actions);
                card.appendChild(img);
                card.appendChild(bodyEl);
                col.appendChild(card);
                grid.appendChild(col);
            });
        }

        function load() {
            api('POST', '/product/get-all-products', filters()).then(function (r) {
                if (!r.ok) return toast(firstError(r.data), 'error');
                render(r.data.data);
            });
        }

        form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 400); });
        form.addEventListener('reset', function () { setTimeout(load, 0); });
        form.addEventListener('submit', function (e) { e.preventDefault(); load(); });
        load();
    };

    pages['product-details'] = function () {
        var id = lastSegment();
        api('GET', '/product/get-product-detail/' + encodeURIComponent(id)).then(function (r) {
            if (!r.ok) return toast(firstError(r.data), 'error');
            var p = r.data.data;
            $('#detail-image').src = p.productImage;
            $('#detail-image').alt = p.productName;
            $('#detail-name').textContent = p.productName;
            $('#detail-price').textContent = money(p.productPrice);
            $('#detail-meta').textContent = [p.productCategory, p.productSubCategory, p.productFor].join(' · ');
            $('#detail-description').textContent = p.productDescription;
            $('#product-detail').hidden = false;
            $('#detail-add').addEventListener('click', function () { addToCart(p._id); });
        });
    };

    pages.cart = function () {
        function load() {
            api('GET', '/user/get-cart-products').then(function (r) {
                if (!r.ok) return toast(firstError(r.data), 'error');
                var products = r.data.products;
                $('#cart-count').textContent = r.data.count;
                $('#cart-empty').hidden = products.length > 0;
                $('#cart-content').hidden = products.length === 0;

                var list = $('#cart-items');
                list.innerHTML = '';
                var total = 0;
                products.forEach(function (p) {
                    total += p.productPrice * p.quantity;
                    var li = el('li', { 'class': 'items' });
                    var section = el('div', { 'class': 'cartSection p-3 d-flex flex-wrap gap-3 align-items-center' });
                    section.appendChild(el('img', { src: p.productImage, alt: p.productName }));
                    var info = el('div', { 'class': 'flex-grow-1' });
                    info.appendChild(el('p', { 'class': 'itemNumber small text-secondary mb-1' }, '#' + p._id));
                    info.appendChild(el('h3', { 'class': 'h6 mb-1' }, p.productName));
                    info.appendChild(el('p', { 'class': 'mb-0' }, 'MRP ' + money(p.productPrice)));
                    info.appendChild(el('p', { 'class': 'small text-secondary mb-0' }, 'Quantity: ' + p.quantity));
                    section.appendChild(info);
                    var prodTotal = el('div', { 'class': 'prodTotal' });
                    prodTotal.appendChild(el('p', { 'class': 'fw-bold mb-2' }, money(p.productPrice * p.quantity)));
                    var buy = el('button', { 'class': 'btn btn-primary btn-sm me-2', type: 'button' }, 'Buy Now');
                    var del = el('button', { 'class': 'btn btn-danger btn-sm', type: 'button', 'aria-label': 'Remove ' + p.productName }, 'Delete');
                    buy.addEventListener('click', function () { go('/dashboard/order?product=' + p._id); });
                    del.addEventListener('click', function () {
                        api('DELETE', '/user/remove-from-cart/' + p._id).then(function (res) {
                            if (!res.ok) return toast(firstError(res.data), 'error');
                            toast(res.data.message);
                            load();
                        });
                    });
                    prodTotal.appendChild(buy);
                    prodTotal.appendChild(del);
                    section.appendChild(prodTotal);
                    li.appendChild(section);
                    list.appendChild(li);
                });
                $('#cart-subtotal').textContent = money(total);
                $('#cart-total').textContent = money(total);
            });
        }
        $('#checkout').addEventListener('click', function () { go('/dashboard/order'); });
        load();
    };

    pages.order = function () {
        var params = new URLSearchParams(location.search);
        var single = params.get('product');
        var items = [];
        var discount = 0;
        var coupon = null;

        $('#shipping-email').textContent = session.email || '';

        function renderSummary() {
            var list = $('#order-items');
            list.innerHTML = '';
            var subtotal = 0;
            items.forEach(function (p) {
                subtotal += p.productPrice;
                var li = el('li', { 'class': 'item__details d-flex justify-content-between gap-2' });
                li.appendChild(el('span', { 'class': 'item__title' }, p.productName));
                li.appendChild(el('span', { 'class': 'item__price' }, money(p.productPrice)));
                list.appendChild(li);
            });
            var off = subtotal * discount / 100;
            $('#order-subtotal').textContent = money(subtotal);
            $('#order-discount').textContent = '-' + money(off);
            $('#order-total').textContent = money(subtotal - off);
        }

        // An order line is created per product, so a cart line with
        // quantity 3 becomes three orders, mirroring the API contract.
        var source = single
            ? api('GET', '/product/get-product-detail/' + encodeURIComponent(single)).then(function (r) { return r.ok ? [r.data.data] : []; })
            : api('GET', '/user/get-cart-products').then(function (r) {
                if (!r.ok) return [];
                var lines = [];
                r.data.products.forEach(function (p) { for (var i = 0; i < p.quantity; i++) lines.push(p); });
                return lines;
            });

        source.then(function (lines) {
            items = lines;
            if (!items.length) {
                flash('No Product in Your Cart', 'error');
                return go('/dashboard/cart');
            }
            renderSummary();
        });

        $('#apply-coupon').addEventListener('click', function () {
            var code = $('#coupon').value.trim();
            var result = $('#coupon-result');
            if (!code) return toast('Please Enter Coupon', 'error');
            api('POST', '/order/apply-coupon', { couponCode: code }).then(function (r) {
                result.className = 'small mt-1 mb-0 ' + (r.ok ? 'text-success' : 'text-danger');
                result.textContent = r.ok ? '* Coupon Applied' : '* Invalid Coupon';
                discount = r.ok ? r.data.discountPercent : 0;
                coupon = r.ok ? code : null;
                renderSummary();
            });
        });

        // Country typeahead
        var input = $('#country');
        var results = $('#country-results');
        var timer;
        var active = -1;

        function close() { results.hidden = true; results.innerHTML = ''; active = -1; }
        function choose(name) { input.value = name; close(); }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var term = input.value.trim().toLowerCase();
            if (!term) return close();
            timer = setTimeout(function () {
                results.innerHTML = '';
                SHOP.countries.filter(function (c) { return c.toLowerCase().indexOf(term) !== -1; }).slice(0, 12).forEach(function (c) {
                    var btn = el('button', { type: 'button', 'class': 'ta-item list-group-item list-group-item-action' });
                    btn.appendChild(el('span', { 'class': 'ng-star-inserted' }, c));
                    btn.addEventListener('click', function () { choose(c); });
                    results.appendChild(btn);
                });
                active = -1;
                results.hidden = results.children.length === 0;
            }, Chaos.enabled ? Chaos.between(600, 2500) : 300);
        });

        input.addEventListener('keydown', function (e) {
            var options = $all('button', results);
            if (results.hidden || !options.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options.forEach(function (o, i) { o.classList.toggle('active', i === active); });
            } else if (e.key === 'Enter' && active >= 0) {
                e.preventDefault();
                choose(options[active].textContent);
            } else if (e.key === 'Escape') {
                close();
            }
        });

        document.addEventListener('click', function (e) { if (!results.contains(e.target) && e.target !== input) close(); });

        $('#place-order').addEventListener('click', function () {
            var country = input.value.trim();
            if (SHOP.countries.indexOf(country) === -1) return toast('Please Enter Full Shipping Information', 'error');
            if (!items.length) return toast('No Product in Your Cart', 'error');
            var body = { orders: items.map(function (p) { return { country: country, productOrderedId: Number(p._id) }; }) };
            if (coupon) body.couponCode = coupon;
            api('POST', '/order/create-order', body).then(function (r) {
                if (!r.ok) return toast(firstError(r.data), 'error');
                try { sessionStorage.setItem(ORDERS_KEY, JSON.stringify(r.data.orders)); } catch (e) { }
                flash(r.data.message);
                go('/dashboard/thanks');
            });
        });
    };

    pages.thanks = function () {
        var ids = [];
        try { ids = JSON.parse(sessionStorage.getItem(ORDERS_KEY)) || []; } catch (e) { }
        var cell = $('#order-ids');
        if (!ids.length) cell.textContent = 'No recent order. Visit the Orders History Page.';
        ids.forEach(function (id) {
            cell.appendChild(el('label', { 'class': 'order-id d-block ng-star-inserted' }, ' | ' + id + ' | '));
        });

        $('#download-csv').addEventListener('click', function () {
            api('GET', '/order/download-csv', null, { blob: true }).then(function (r) {
                if (!r.ok) return toast('Download failed', 'error');
                var a = el('a', { href: URL.createObjectURL(r.blob), download: 'order-invoice_' + (session.email || 'orders') + '.csv' });
                document.body.appendChild(a);
                a.click();
                a.remove();
            });
        });
    };

    pages.myorders = function () {
        function load() {
            api('GET', '/order/get-orders-for-customer/' + encodeURIComponent(session.userId)).then(function (r) {
                if (!r.ok) return toast(firstError(r.data), 'error');
                var orders = r.data.data;
                $('#no-orders').hidden = orders.length > 0;
                $('#orders-wrap').hidden = orders.length === 0;
                var tbody = $('#orders-body');
                tbody.innerHTML = '';
                orders.forEach(function (o) {
                    var tr = el('tr', { 'class': 'ng-star-inserted' });
                    tr.appendChild(el('th', { scope: 'row' }, o._id));
                    var imgTd = el('td');
                    imgTd.appendChild(el('img', { src: o.productImage, alt: o.productName, style: 'width:56px', 'class': 'rounded' }));
                    tr.appendChild(imgTd);
                    tr.appendChild(el('td', {}, o.productName));
                    tr.appendChild(el('td', {}, money(o.orderPrice)));
                    tr.appendChild(el('td', {}, new Date(o.createdAt).toLocaleString()));
                    var viewTd = el('td');
                    var view = el('button', { 'class': 'btn btn-primary btn-sm', type: 'button' }, 'View');
                    view.addEventListener('click', function () { go('/dashboard/order-details/' + o._id); });
                    viewTd.appendChild(view);
                    tr.appendChild(viewTd);
                    var delTd = el('td');
                    var del = el('button', { 'class': 'btn btn-danger btn-sm', type: 'button' }, 'Delete');
                    del.addEventListener('click', function () {
                        api('DELETE', '/order/delete-order/' + o._id).then(function (res) {
                            if (!res.ok) return toast(firstError(res.data), 'error');
                            toast(res.data.message);
                            load();
                        });
                    });
                    delTd.appendChild(del);
                    tr.appendChild(delTd);
                    tbody.appendChild(tr);
                });
            });
        }
        load();
    };

    pages['order-details'] = function () {
        api('GET', '/order/get-orders-details?id=' + encodeURIComponent(lastSegment())).then(function (r) {
            if (!r.ok) {
                $('#order-error').textContent = firstError(r.data);
                $('#order-error').hidden = false;
                return;
            }
            var o = r.data.data;
            $('#detail-order-id').textContent = o._id;
            $('#detail-email').textContent = o.orderBy;
            $('#detail-country').textContent = o.country;
            $('#detail-date').textContent = new Date(o.createdAt).toLocaleString();
            $('#detail-image').src = o.productImage;
            $('#detail-product').textContent = o.productName;
            $('#detail-price').textContent = money(o.orderPrice);
            $('#order-summary').hidden = false;
        });
    };

    // ---------- boot ----------

    document.addEventListener('DOMContentLoaded', function () {
        var isAuthPage = ['login', 'register', 'password-new'].indexOf(SHOP.page) !== -1;
        if (!isAuthPage && !session.token) {
            flash('Please login to continue', 'error');
            return go('/auth/login');
        }
        showFlash();
        initNav();
        if (!isAuthPage && SHOP.page !== 'cart') refreshCartCount();
        if (pages[SHOP.page]) pages[SHOP.page]();
    });
})();
