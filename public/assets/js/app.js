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

    /**
     * Clickable table rows: tables list rows for tasks, customers and
     * employees carry a data-href on <tr> so the whole row opens the
     * record, while clicks on an actual link, button or form inside the
     * row (edit/status/delete actions) keep their own behaviour.
     */
    function bindClickableRows() {
        document.addEventListener('click', function (event) {
            var row = event.target.closest('tr[data-href]');

            if (!row) {
                return;
            }

            if (event.target.closest('a, button, input, select, textarea, label, form')) {
                return;
            }

            window.location.href = row.getAttribute('data-href');
        });
    }

    /**
     * Live search suggestions: a [data-suggest] wrapper around the <input>
     * carries an empty <ul data-suggest-list>. As the user types, matching
     * records are fetched from the panel's own "/suggest" endpoint. By
     * default each result is a link that jumps straight to that record
     * (the Customer, Employee, Work etc. search boxes); a wrapper marked
     * data-suggest-mode="fill" instead fills the picked value straight into
     * the input without navigating, for filter fields like the Reports
     * search boxes that only feed a form.
     */
    function bindSearchSuggestions() {
        var containers = document.querySelectorAll('[data-suggest]');

        Array.prototype.forEach.call(containers, function (container) {
            var input = container.querySelector('[data-suggest-input]');
            var list  = container.querySelector('[data-suggest-list]');
            var url   = container.getAttribute('data-suggest-url');
            var fill  = container.getAttribute('data-suggest-mode') === 'fill';

            if (!input || !list || !url) {
                return;
            }

            var debounceTimer = null;
            var activeRequest = null;

            function closeList() {
                list.innerHTML = '';
                list.classList.add('hidden');
            }

            function renderResults(results) {
                list.innerHTML = '';

                if (!results || results.length === 0) {
                    closeList();

                    return;
                }

                results.forEach(function (item) {
                    var li   = document.createElement('li');
                    var link = document.createElement(fill ? 'button' : 'a');

                    if (fill) {
                        link.type = 'button';
                    } else {
                        link.href = item.url;
                    }

                    link.className = 'block w-full px-3.5 py-2 text-left text-sm hover:bg-slate-50';

                    var name = document.createElement('p');
                    name.className = 'font-medium text-ink';
                    name.textContent = item.name;
                    link.appendChild(name);

                    if (item.sub) {
                        var sub = document.createElement('p');
                        sub.className = 'text-xs text-slate-500';
                        sub.textContent = item.sub;
                        link.appendChild(sub);
                    }

                    if (fill) {
                        link.addEventListener('click', function () {
                            input.value = item.name;
                            closeList();
                            input.focus();
                        });
                    }

                    li.appendChild(link);
                    list.appendChild(li);
                });

                list.classList.remove('hidden');
            }

            function fetchSuggestions(query) {
                if (activeRequest && typeof activeRequest.abort === 'function') {
                    activeRequest.abort();
                }

                var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                activeRequest = controller;

                fetch(url + '?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json' },
                    signal: controller ? controller.signal : undefined,
                })
                    .then(function (response) {
                        return response.ok ? response.json() : { results: [] };
                    })
                    .then(function (data) {
                        renderResults(data.results);
                    })
                    .catch(function () {
                        // A dropped request or network hiccup just leaves the
                        // list closed; the search box still submits normally.
                    });
            }

            input.addEventListener('input', function () {
                var query = input.value.trim();

                window.clearTimeout(debounceTimer);

                if (query.length === 0) {
                    closeList();

                    return;
                }

                debounceTimer = window.setTimeout(function () {
                    fetchSuggestions(query);
                }, 200);
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeList();
                }
            });

            document.addEventListener('click', function (event) {
                if (!container.contains(event.target)) {
                    closeList();
                }
            });
        });
    }

    /**
     * Add Task: selecting a project fills the description in from that
     * project's own description, so the same work isn't typed out twice.
     * It only overwrites the field while it's still empty or still holds
     * the value that was auto-filled in - once the admin/manager edits it
     * themselves, switching projects again leaves their wording alone.
     */
    function bindProjectDescriptionAutofill() {
        var select = document.querySelector('[data-project-description-source]');
        var target = document.querySelector('[data-project-description-target]');

        if (!select || !target) {
            return;
        }

        var lastAutofilled = null;

        select.addEventListener('change', function () {
            var option      = select.options[select.selectedIndex];
            var description = option ? (option.getAttribute('data-description') || '') : '';

            if (target.value.trim() === '' || target.value === lastAutofilled) {
                target.value    = description;
                lastAutofilled  = description;
            }
        });
    }

    /**
     * Record Payment: picking a customer narrows the project list to that
     * customer's projects, and picking a project shows its total/collected/
     * outstanding figures and drops the outstanding balance into the amount
     * field - editable for a partial payment, the same way the description
     * autofill above stays out of the way of a manual edit.
     */
    function bindPaymentProjectPicker() {
        var customerSelect = document.querySelector('[data-payment-customer-filter]');
        var projectSelect  = document.querySelector('[data-payment-project-select]');

        if (!projectSelect) {
            return;
        }

        var summaryBox         = document.querySelector('[data-payment-project-summary]');
        var summaryTotal       = document.querySelector('[data-summary-total]');
        var summaryCollected   = document.querySelector('[data-summary-collected]');
        var summaryOutstanding = document.querySelector('[data-summary-outstanding]');
        var amountField        = document.querySelector('[data-payment-amount-target]');
        var lastAutofilledAmount = null;

        function formatMoney(value) {
            var amount = parseFloat(value);

            if (isNaN(amount)) {
                amount = 0;
            }

            return '₹' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function updateSummary() {
            var option = projectSelect.options[projectSelect.selectedIndex];

            if (!option || !option.value) {
                if (summaryBox) {
                    summaryBox.hidden = true;
                }

                return;
            }

            var total       = option.getAttribute('data-total') || '0';
            var collected   = option.getAttribute('data-collected') || '0';
            var outstanding = option.getAttribute('data-outstanding') || '0';

            if (summaryBox && summaryTotal && summaryCollected && summaryOutstanding) {
                summaryTotal.textContent       = formatMoney(total);
                summaryCollected.textContent   = formatMoney(collected);
                summaryOutstanding.textContent = formatMoney(outstanding);
                summaryBox.hidden = false;
            }

            if (amountField && (amountField.value.trim() === '' || amountField.value === lastAutofilledAmount)) {
                amountField.value    = outstanding;
                lastAutofilledAmount = outstanding;
            }
        }

        function filterProjectsByCustomer() {
            if (!customerSelect) {
                return;
            }

            var customerId = customerSelect.value;
            var options    = projectSelect.querySelectorAll('option[data-customer]');

            Array.prototype.forEach.call(options, function (option) {
                var matches = customerId === '' || option.getAttribute('data-customer') === customerId;

                option.hidden   = !matches;
                option.disabled = !matches;
            });

            var selected = projectSelect.options[projectSelect.selectedIndex];

            if (selected && selected.disabled) {
                projectSelect.value = '';
                updateSummary();
            }
        }

        if (customerSelect) {
            customerSelect.addEventListener('change', filterProjectsByCustomer);
            filterProjectsByCustomer();
        }

        projectSelect.addEventListener('change', updateSummary);
        updateSummary();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindPasswordToggles();
            bindClickableRows();
            bindSearchSuggestions();
            bindProjectDescriptionAutofill();
            bindPaymentProjectPicker();
        });
    } else {
        bindPasswordToggles();
        bindClickableRows();
        bindSearchSuggestions();
        bindProjectDescriptionAutofill();
        bindPaymentProjectPicker();
    }
})();
