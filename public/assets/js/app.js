/**
 * Sunrise Films - authentication UI behaviour.
 *
 * The only scripted behaviour the auth spec asks for is the password
 * visibility toggle (s4.2): the field is hidden by default, the eye icon
 * reveals it, and clicking again hides it.
 */
(function () {
    'use strict';

    function bindPasswordToggles() {
        var toggles = document.querySelectorAll('.js-password-toggle');

        Array.prototype.forEach.call(toggles, function (toggle) {
            toggle.addEventListener('click', function () {
                var input = document.getElementById(toggle.getAttribute('data-target'));

                if (!input) {
                    return;
                }

                var reveal = input.type === 'password';

                input.type = reveal ? 'text' : 'password';

                var open = toggle.querySelector('.js-eye-open');
                var closed = toggle.querySelector('.js-eye-closed');

                if (open && closed) {
                    open.classList.toggle('hidden', reveal);
                    closed.classList.toggle('hidden', !reveal);
                }

                toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');

                // Keep the caret where the user left it.
                input.focus();

                if (typeof input.setSelectionRange === 'function') {
                    var end = input.value.length;
                    input.setSelectionRange(end, end);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindPasswordToggles);
    } else {
        bindPasswordToggles();
    }
})();
