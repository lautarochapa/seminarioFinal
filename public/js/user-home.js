(function (window, document) {
    'use strict';

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
        if (!actions.length) {
            actionPanel.textContent = 'No tenes pendientes importantes por ahora.';
            return;
        }
        actionPanel.className = '';
        actionPanel.innerHTML = actions.map(function (action) {
            return '<div class="table-line"><span>' + action.message + '</span><strong>Hoy</strong></div>';
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-home]');
        if (!root || !window.CCApi) {
            return;
        }
        window.CCApi.request('/api/v1/users/me/home-summary')
            .then(function (response) {
                render(root, response.data || {});
            })
            .catch(function (error) {
                var message = error && error.status === 403
                    ? 'No tenes permiso para ver este inicio.'
                    : 'No pudimos cargar el resumen de tu cocina. Revisa tu conexion e intenta nuevamente.';
                showMessage(root, 'danger', message);
            });
    });
})(window, document);
