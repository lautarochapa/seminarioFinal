(function (window, document) {
    'use strict';

    function mountPage() {

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function setText(root, selector, value) {
        var node = qs(selector, root);
        if (node) {
            node.textContent = value;
        }
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-user-home-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function render(root, summary) {
        var stock = summary.stock || {};
        var recipes = summary.recipes || {};
        var actions = summary.actions || [];
        setText(root, '[data-home-products]', stock.products || 0);
        setText(root, '[data-home-expiring]', stock.expiring || 0);
        setText(root, '[data-home-low-stock]', stock.low_stock || 0);
        setText(root, '[data-home-recipes]', recipes.available || 0);

        var empty = qs('[data-home-empty]', root);
        if (empty) {
            empty.style.display = (stock.products || 0) > 0 ? 'none' : 'block';
        }

        var actionPanel = qs('[data-home-actions]', root);
        if (!actionPanel) {
            return;
        }
        actionPanel.className = '';
        if (!actions.length) {
            actionPanel.textContent = 'No tenes pendientes importantes por ahora.';
            return;
        }
        actionPanel.className = '';
        actionPanel.replaceChildren();
        actions.forEach(function (action) {
            var line = document.createElement('div');
            line.className = 'table-line';
            var message = document.createElement('span');
            message.textContent = action.message;
            var label = document.createElement('strong');
            label.textContent = 'Hoy';
            line.append(message, label);
            actionPanel.appendChild(line);
        });
    }

    function loadBudget(root) {
        var target = qs('[data-home-budget]', root);
        var select = qs('[data-home-budget-group]', root);
        if (!target || !select) { return; }
        var revision = 0;
        function money(value, currency) {
            return Number(value || 0).toLocaleString('es-AR', { style: 'currency', currency: currency || 'ARS' });
        }
        function current() {
            var request = ++revision;
            target.className = 'cc-loading';
            target.innerHTML = '<span class="cc-spinner" aria-hidden="true"></span>Cargando presupuesto...';
            if (!select.value) {
                target.className = 'muted'; target.textContent = 'Creá tu hogar para organizar el presupuesto.';
                return;
            }
            window.CCApi.request('/api/v1/family-groups/' + encodeURIComponent(select.value) + '/budgets/current')
                .then(function (response) {
                    if (request !== revision) { return; }
                    var budget = response.data;
                    target.className = '';
                    target.replaceChildren();
                    if (!budget) { target.textContent = 'Todavía no hay un presupuesto para este mes.'; return; }
                    var amount = document.createElement('strong');
                    amount.className = 'home-budget-amount'; amount.textContent = money(budget.total_amount, budget.currency);
                    var currency = document.createElement('span');
                    currency.className = 'muted'; currency.textContent = budget.currency || 'ARS';
                    var track = document.createElement('div'); track.className = 'home-budget-track';
                    var bar = document.createElement('span');
                    var pct = budget.total_amount > 0 ? Math.max(0, Math.min(100, budget.used_amount / budget.total_amount * 100)) : 0;
                    bar.style.width = pct + '%';
                    if (budget.available_amount < 0) { bar.style.background = '#b33a3a'; }
                    track.appendChild(bar);
                    var values = document.createElement('div'); values.className = 'home-budget-values';
                    var spent = document.createElement('span'); spent.textContent = 'Gastado: ' + money(budget.used_amount, budget.currency);
                    var available = document.createElement('strong'); available.textContent = 'Disponible: ' + money(budget.available_amount, budget.currency);
                    values.append(spent, available);
                    target.append(amount, currency, track, values);
                }).catch(function (error) {
                    if (request !== revision) { return; }
                    target.className = 'muted';
                    target.textContent = error.status === 404 ? 'Todavía no hay un presupuesto para este mes.' : 'No pudimos consultar el presupuesto. Podés reintentar desde Presupuestos.';
                });
        }
        select.addEventListener('change', current);
        window.CCApi.request('/api/v1/family-groups').then(function (response) {
            select.replaceChildren();
            (response.data || []).forEach(function (group) {
                var option = document.createElement('option');
                option.value = group.id; option.textContent = group.name;
                select.appendChild(option);
            });
            current();
        }).catch(function () {
            target.className = 'muted'; target.textContent = 'No pudimos cargar tus hogares.';
        });
    }

    (function initialize() {
        var root = qs('[data-user-home]');
        if (!root || !window.CCApi) {
            return;
        }
        loadBudget(root);
        window.CCApi.request('/api/v1/users/me/home-summary')
            .then(function (response) {
                render(root, response.data || {});
            })
            .catch(function (error) {
                var message = error && error.status === 403
                    ? 'No tenes permiso para ver este inicio.'
                    : 'No pudimos cargar el resumen de tu cocina. Revisa tu conexion e intenta nuevamente.';
                showMessage(root, 'danger', message);
                var actions = qs('[data-home-actions]', root);
                if (actions) { actions.className = 'muted'; actions.textContent = 'El resumen no está disponible.'; }
            });
    })();
    }
    if (window.CCPage) window.CCPage.register('user-home', mountPage);
    else document.addEventListener('DOMContentLoaded', mountPage);
})(window, document);
