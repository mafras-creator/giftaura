/* GiftAura - small enhancements for the admin area.
   All permission checks happen on the server; nothing here is security. */
(function () {
    'use strict';

    // Sidebar toggle on small screens
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var sidebar = document.getElementById('admin-sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                sidebar.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Close flash messages
    document.querySelectorAll('[data-dismiss]').forEach(function (button) {
        button.addEventListener('click', function () {
            var alert = button.closest('.alert');
            if (alert) { alert.remove(); }
        });
    });

    // Confirmation before dangerous actions: <form data-confirm="...">
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });
    // Filters that reload the page as soon as a value is chosen
    document.querySelectorAll('select[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) { select.form.submit(); }
        });
    });

    // Preview an image before uploading it (the real checks happen on the server)
    document.querySelectorAll('input[type="file"][data-image-preview]').forEach(function (input) {
        var preview = input.parentElement.querySelector('[data-preview-target]');
        input.addEventListener('change', function () {
            if (!preview || !input.files || !input.files[0]) { return; }
            var file = input.files[0];
            if (file.size > 2 * 1024 * 1024) {
                window.alert('This image is larger than 2 MB. Please choose a smaller image.');
                input.value = '';
                preview.classList.add('is-hidden');
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('is-hidden');
            };
            reader.readAsDataURL(file);
        });
    });

    // Prevent double submission
    document.querySelectorAll('form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) { return; }
            var button = form.querySelector('button[type="submit"]');
            if (button) { setTimeout(function () {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    if (button.hasAttribute('data-loading-text')) {
                        button.setAttribute('data-original-text', button.textContent);
                        button.textContent = button.getAttribute('data-loading-text');
                    }
                }, 0); }
        });
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[type="submit"]:disabled').forEach(function (b) {
            b.disabled = false;
            b.removeAttribute('aria-busy');
            if (b.hasAttribute('data-original-text')) { b.textContent = b.getAttribute('data-original-text'); }
        });
    });
    // Categories form: button says what will be created
    var categorySubmit = document.querySelector('[data-category-submit]');
    var parentSelect = document.getElementById('parent_id');
    if (categorySubmit && parentSelect) {
        parentSelect.addEventListener('change', function () {
            categorySubmit.textContent = parentSelect.value ? 'Add subcategory' : 'Add main category';
        });
    }
    // Several photos at once: check the number and size, show small previews
    document.querySelectorAll('input[type="file"][data-images-preview]').forEach(function (input) {
        var strip = input.parentElement.querySelector('[data-preview-strip]');
        var max = parseInt(input.getAttribute('data-max') || '4', 10);
        input.addEventListener('change', function () {
            if (strip) { strip.innerHTML = ''; }
            var files = Array.prototype.slice.call(input.files || []);
            if (files.length > max) {
                window.alert('Please choose at most ' + max + ' photo(s).');
                input.value = '';
                return;
            }
            var tooBig = files.filter(function (f) { return f.size > 2 * 1024 * 1024; });
            if (tooBig.length) {
                window.alert(tooBig.map(function (f) { return f.name; }).join(', ') + ': larger than 2 MB. Please choose smaller photos.');
                input.value = '';
                return;
            }
            files.forEach(function (file, index) {
                if (!strip) { return; }
                var img = document.createElement('img');
                img.alt = '';
                img.width = 80;
                img.height = 80;
                img.className = 'preview-strip__img' + (index === 0 ? ' is-main' : '');
                img.title = index === 0 ? 'Main photo' : file.name;
                strip.appendChild(img);
                var reader = new FileReader();
                reader.onload = function (e) { img.src = e.target.result; };
                reader.readAsDataURL(file);
            });
        });
    });

    // Product form: show the personalisation details only when the box is ticked.
    document.querySelectorAll('[data-custom-box]').forEach(function (box) {
        var toggle = box.querySelector('[data-custom-toggle]');
        var details = box.querySelector('[data-custom-details]');
        if (!toggle || !details) { return; }
        var sync = function () { details.hidden = !toggle.checked; };
        toggle.addEventListener('change', sync);
        sync();
    });

    // Invoice: Print button
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });

    // New in-shop sale: live line totals, subtotal, total and change (a preview only;
    // the server reads the real prices and checks the stock again when saving).
    var saleForm = document.querySelector('[data-sale-form]');
    if (saleForm) {
        var body = saleForm.querySelector('[data-sale-lines]');
        var addButton = saleForm.querySelector('[data-sale-add-line]');
        var methodSelect = saleForm.querySelector('[data-sale-method]');
        var cashField = saleForm.querySelector('[data-sale-cash]');
        var toCents = function (text) {
            var clean = String(text || '').replace(/[,\s]/g, '');
            if (!/^\d{1,7}(\.\d{1,2})?$/.test(clean)) { return null; }
            return Math.round(parseFloat(clean) * 100);
        };
        var show = function (cents) {
            var rupees = (cents / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return 'Rs. ' + rupees;
        };
        var recalc = function () {
            var subtotal = 0;
            body.querySelectorAll('[data-sale-line]').forEach(function (row) {
                var select = row.querySelector('[data-sale-product]');
                var qtyInput = row.querySelector('[data-sale-qty]');
                var out = row.querySelector('[data-sale-line-total]');
                var option = select.options[select.selectedIndex];
                var price = option ? toCents(option.getAttribute('data-price')) : null;
                var stock = option ? parseInt(option.getAttribute('data-stock') || '0', 10) : 0;
                var qty = parseInt(qtyInput.value || '0', 10);
                if (select.value && qtyInput.value === '') { qtyInput.value = '1'; qty = 1; }
                qtyInput.max = select.value ? String(Math.min(stock, 999)) : '999';
                row.classList.toggle('is-over', !!select.value && qty > stock);
                if (select.value && price !== null && qty > 0) {
                    out.textContent = show(price * qty);
                    subtotal += price * qty;
                } else {
                    out.textContent = '—';
                }
            });
            var discount = toCents(saleForm.querySelector('[data-sale-discount]').value) || 0;
            var total = Math.max(0, subtotal - discount);
            saleForm.querySelector('[data-sale-subtotal]').textContent = show(subtotal);
            saleForm.querySelector('[data-sale-discount-show]').textContent = discount > 0 ? '− ' + show(discount) : show(0);
            saleForm.querySelector('[data-sale-total]').textContent = show(total);
            var isCash = methodSelect.value === 'cash';
            if (cashField) { cashField.hidden = !isCash; }
            var received = isCash ? toCents(saleForm.querySelector('[data-sale-received]').value) : null;
            var changeRow = saleForm.querySelector('[data-sale-change-row]');
            changeRow.hidden = received === null;
            if (received !== null) {
                saleForm.querySelector('[data-sale-change]').textContent = received >= total ? show(received - total) : 'Not enough cash';
            }
        };
        if (addButton) {
            addButton.hidden = false;
            addButton.addEventListener('click', function () {
                var rows = body.querySelectorAll('[data-sale-line]');
                if (rows.length >= 30) { return; }
                var copy = rows[rows.length - 1].cloneNode(true);
                var n = rows.length;
                copy.querySelectorAll('select, input').forEach(function (field) {
                    var base = field.id.replace(/-\d+$/, '');
                    field.id = base + '-' + n;
                    if (field.tagName === 'SELECT') { field.selectedIndex = 0; } else { field.value = ''; }
                });
                copy.querySelectorAll('label').forEach(function (label) {
                    var target = label.getAttribute('for').replace(/-\d+$/, '');
                    label.setAttribute('for', target + '-' + n);
                    label.textContent = label.textContent.replace(/\d+$/, String(n + 1));
                });
                body.appendChild(copy);
                copy.querySelector('select').focus();
                recalc();
            });
        }
        saleForm.addEventListener('input', recalc);
        saleForm.addEventListener('change', recalc);
        recalc();
    }
})();
