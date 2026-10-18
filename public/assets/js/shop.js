/* GiftAura - small enhancements for the customer site.
   The site works without JavaScript; nothing here is used for security. */
(function () {
    'use strict';

    // "All categories" menu: open/close, close with Escape or a click outside
    var catToggle = document.querySelector('[data-catmenu-toggle]');
    var catPanel = document.getElementById('catmenu-panel');
    if (catToggle && catPanel) {
        var setCatMenu = function (open) {
            catPanel.hidden = !open;
            catToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        catToggle.addEventListener('click', function () { setCatMenu(catPanel.hidden); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !catPanel.hidden) { setCatMenu(false); catToggle.focus(); }
        });
        document.addEventListener('click', function (event) {
            if (!catPanel.hidden && !catPanel.contains(event.target) && !catToggle.contains(event.target)) { setCatMenu(false); }
        });
    }

    // Homepage banner slider
    var slider = document.querySelector('[data-slider]');
    if (slider) {
        var slides = slider.querySelectorAll('[data-slide]');
        var dots = slider.querySelectorAll('[data-slide-to]');
        var current = 0;
        var timer = null;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var show = function (index) {
            if (!slides.length) { return; }
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                var active = i === current;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                slide.querySelectorAll('a, button').forEach(function (el) { el.tabIndex = active ? 0 : -1; });
            });
            dots.forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === current);
                if (i === current) { dot.setAttribute('aria-current', 'true'); } else { dot.removeAttribute('aria-current'); }
            });
        };
        var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
        var start = function () {
            stop();
            if (!reduceMotion && slides.length > 1) { timer = setInterval(function () { show(current + 1); }, 6000); }
        };
        var prev = slider.querySelector('[data-slide-prev]');
        var next = slider.querySelector('[data-slide-next]');
        if (prev) { prev.addEventListener('click', function () { show(current - 1); start(); }); }
        if (next) { next.addEventListener('click', function () { show(current + 1); start(); }); }
        dots.forEach(function (dot) {
            dot.addEventListener('click', function () { show(parseInt(dot.getAttribute('data-slide-to'), 10)); start(); });
        });
        slider.addEventListener('mouseenter', stop);
        slider.addEventListener('mouseleave', start);
        slider.addEventListener('focusin', stop);
        slider.addEventListener('focusout', start);
        show(0);
        start();
    }

    // Show / hide password fields
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!input) { return; }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
        });
    });

    // Close flash messages
    document.querySelectorAll('[data-dismiss]').forEach(function (button) {
        button.addEventListener('click', function () {
            var alert = button.closest('.alert');
            if (alert) { alert.remove(); }
        });
    });

    // Ask for confirmation before dangerous actions: <form data-confirm="Are you sure?">
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // Prevent double submission: disable the submit button after the first click
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) { return; }
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                setTimeout(function () {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    if (button.hasAttribute('data-loading-text')) {
                        button.setAttribute('data-original-text', button.textContent);
                        button.textContent = button.getAttribute('data-loading-text');
                    }
                }, 0);
            }
        });
    });
    // Shop filters: show/hide on small screens
    var filtersToggle = document.querySelector('[data-filters-toggle]');
    var filters = document.getElementById('shop-filters');
    if (filtersToggle && filters) {
        filtersToggle.addEventListener('click', function () {
            var open = filters.classList.toggle('is-open');
            filtersToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Sort menu: reload the list as soon as a new order is chosen
    document.querySelectorAll('select[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) { select.form.submit(); }
        });
    });

    // Product gallery: clicking a thumbnail shows it as the main image
    var mainImage = document.getElementById('gallery-main');
    document.querySelectorAll('[data-gallery-src]').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            if (!mainImage) { return; }
            mainImage.src = thumb.getAttribute('data-gallery-src');
            mainImage.alt = thumb.getAttribute('data-gallery-alt') || '';
            document.querySelectorAll('[data-gallery-src]').forEach(function (t) { t.classList.remove('is-active'); });
            thumb.classList.add('is-active');
        });
    });

    // Quantity stepper (+ / - buttons next to a number field)
    document.querySelectorAll('.stepper').forEach(function (stepper) {
        var input = stepper.querySelector('input');
        stepper.querySelectorAll('[data-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                var min = parseInt(input.min || '1', 10);
                var max = parseInt(input.max || '99', 10);
                var value = (parseInt(input.value, 10) || min) + parseInt(button.getAttribute('data-step'), 10);
                input.value = Math.min(max, Math.max(min, value));
            });
        });
    });

    // Checkout: show the new-address fields only when "new address" is chosen
    var newAddress = document.querySelector('[data-new-address]');
    var choices = document.querySelectorAll('[data-address-choice]');
    if (newAddress && choices.length) {
        var syncAddress = function () {
            var selected = document.querySelector('[data-address-choice]:checked');
            newAddress.classList.toggle('is-hidden', !!selected && selected.value !== 'new');
        };
        choices.forEach(function (c) { c.addEventListener('change', syncAddress); });
        syncAddress();
    }

    // Re-enable buttons when the page is restored with the browser's Back button
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[type="submit"]:disabled').forEach(function (b) {
            b.disabled = false;
            b.removeAttribute('aria-busy');
            if (b.hasAttribute('data-original-text')) { b.textContent = b.getAttribute('data-original-text'); }
        });
    });

    // Personalisation box: live "characters left" counter.
    document.querySelectorAll('textarea[data-char-count]').forEach(function (box) {
        var counter = box.parentNode.querySelector('[data-char-counter]');
        var max = parseInt(box.getAttribute('maxlength'), 10) || 0;
        if (!counter || !max) { return; }
        var update = function () {
            var left = max - box.value.length;
            counter.textContent = left + ' of ' + max + ' characters left';
            counter.classList.toggle('is-low', left <= 10);
        };
        box.addEventListener('input', update);
        update();
    });
})();
