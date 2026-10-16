/* GiftAura - small enhancements for the customer site.
   The site works without JavaScript; nothing here is used for security. */
(function () {
    'use strict';

    // Mobile navigation toggle
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
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
                setTimeout(function () { button.disabled = true; }, 0);
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

    // Re-enable buttons when the page is restored with the browser's Back button
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[type="submit"]:disabled').forEach(function (b) { b.disabled = false; });
    });
})();
