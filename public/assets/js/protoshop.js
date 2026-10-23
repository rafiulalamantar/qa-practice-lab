/* ProtoShop: a purely client-side shop. The cart lives in localStorage so
   this module needs no server state at all. */
(function () {
    var CART_KEY = 'protoshop.cart';
    var products = window.PROTOSHOP_PRODUCTS || [];

    function readCart() {
        try { return JSON.parse(localStorage.getItem(CART_KEY)) || {}; } catch (e) { return {}; }
    }
    function writeCart(cart) {
        try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch (e) { /* private mode */ }
        renderCount();
    }
    function cartQuantity(cart) {
        return Object.keys(cart).reduce(function (sum, id) { return sum + cart[id]; }, 0);
    }
    function money(n) { return '$' + n.toFixed(2); }
    function findProduct(id) { return products.find(function (p) { return String(p.id) === String(id); }); }

    function renderCount() {
        var count = cartQuantity(readCart());
        document.querySelectorAll('[data-cart-count]').forEach(function (el) { el.textContent = count; });
    }

    function initDismiss() {
        document.querySelectorAll('[data-dismiss-alert]').forEach(function (btn) {
            btn.addEventListener('click', function () { btn.closest('.alert').hidden = true; });
        });
    }

    function initForm() {
        var form = document.getElementById('practice-form');
        if (!form) return;

        var bound = document.querySelectorAll('[data-bind="name"]');
        bound.forEach(function (input) {
            input.addEventListener('input', function () {
                bound.forEach(function (other) { if (other !== input) other.value = input.value; });
                validate(false);
            });
        });

        var touched = {};
        function showError(field, message) {
            var box = form.querySelector('[data-error-for="' + field + '"]');
            box.textContent = message || '';
            box.hidden = !message || !touched[field];
        }
        function validate(force) {
            if (force) touched.name = touched.email = true;
            var name = form.elements.name.value.trim();
            var email = form.elements.email.value.trim();
            var nameError = !name ? 'Name is required' : (name.length < 2 ? 'Name should be at least 2 characters' : '');
            var emailError = !email ? 'Email is required' : '';
            showError('name', nameError);
            showError('email', emailError);
            return !nameError && !emailError;
        }
        ['name', 'email'].forEach(function (field) {
            form.elements[field].addEventListener('blur', function () { touched[field] = true; validate(false); });
            form.elements[field].addEventListener('input', function () { validate(false); });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!validate(true)) return;
            Chaos.later(function () {
                document.getElementById('form-success').hidden = false;
            }, 500, 2500);
        });
    }

    function initShop() {
        document.querySelectorAll('[data-add]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cart = readCart();
                var id = btn.getAttribute('data-add');
                cart[id] = (cart[id] || 0) + 1;
                writeCart(cart);
            });
        });
    }

    function initCheckout() {
        var tbody = document.getElementById('cart-rows');
        if (!tbody) return;

        function render() {
            var cart = readCart();
            var total = 0;
            tbody.innerHTML = '';
            Object.keys(cart).forEach(function (id) {
                var p = findProduct(id);
                if (!p) return;
                var line = p.price * cart[id];
                total += line;
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><div class="media d-flex gap-3 align-items-center">' +
                    '<img src="' + p.imageUrl + '" alt="" style="width:72px" class="rounded">' +
                    '<div class="media-body"><h4 class="media-heading h6 mb-0"><a href="#"></a></h4>' +
                    '<h5 class="small text-secondary mb-0">by ' + p.brand + '</h5>' +
                    '<span>Status: </span><span class="text-success"><strong>In Stock</strong></span></div></div></td>' +
                    '<td style="width:110px"><input type="number" min="1" max="10" class="form-control" value="' + cart[id] + '" aria-label="Quantity"></td>' +
                    '<td class="text-center"><strong>' + money(p.price) + '</strong></td>' +
                    '<td class="text-center"><strong>' + money(line) + '</strong></td>' +
                    '<td class="text-end"><button type="button" class="btn btn-danger">Remove</button></td>';
                tr.querySelector('.media-heading a').textContent = p.name;
                tr.querySelector('input').addEventListener('change', function (e) {
                    var c = readCart();
                    c[id] = Math.max(1, Math.min(10, parseInt(e.target.value, 10) || 1));
                    writeCart(c);
                    render();
                });
                tr.querySelector('.btn-danger').addEventListener('click', function () {
                    var c = readCart();
                    delete c[id];
                    writeCart(c);
                    render();
                });
                tbody.appendChild(tr);
            });
            document.getElementById('cart-total').textContent = money(total);
            document.getElementById('empty-cart').hidden = tbody.children.length > 0;
        }
        render();
    }

    function initDelivery() {
        var form = document.getElementById('delivery-form');
        if (!form) return;

        var input = document.getElementById('country');
        var box = document.getElementById('country-suggestions');
        var list = box.querySelector('ul');
        var error = document.getElementById('delivery-error');
        var countries = window.PRACTICE_COUNTRIES || [];
        var pending;

        input.addEventListener('input', function () {
            clearTimeout(pending);
            var term = input.value.trim().toLowerCase();
            if (term.length < 3) { box.hidden = true; return; }
            // Suggestions always arrive late, like a real lookup service.
            pending = setTimeout(function () {
                list.innerHTML = '';
                countries.filter(function (c) { return c.toLowerCase().indexOf(term) !== -1; }).forEach(function (c) {
                    var li = document.createElement('li');
                    var a = document.createElement('a');
                    a.textContent = c;
                    a.addEventListener('click', function () { input.value = c; box.hidden = true; });
                    li.appendChild(a);
                    list.appendChild(li);
                });
                box.hidden = list.children.length === 0;
            }, Chaos.enabled ? Chaos.between(1000, 3000) : 700);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var message = '';
            if (countries.indexOf(input.value) === -1) message = 'Please choose a delivery location from the suggestions.';
            else if (!document.getElementById('checkbox2').checked) message = 'Please accept the term & Conditions.';
            error.textContent = message;
            error.hidden = !message;
            if (message) return;
            writeCart({});
            document.getElementById('purchase-success').hidden = false;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderCount();
        initDismiss();
        initForm();
        initShop();
        initCheckout();
        initDelivery();
    });
})();
