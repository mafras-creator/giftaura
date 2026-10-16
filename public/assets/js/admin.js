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
})();
