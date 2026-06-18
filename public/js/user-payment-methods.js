(function (window, document) {
    'use strict';

    var state = {
        catalog: [],
        selected: [],
    };

    var typeLabels = {
        credit_card: 'Tarjeta credito',
        debit_card: 'Tarjeta debito',
        bank_account: 'Cuenta bancaria',
        digital_wallet: 'Billetera digital',
        cash: 'Efectivo',
        other: 'Otro',
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function escapeHtml(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpoint(path) {
        return '/api/v1' + path;
    }

    function typeLabel(type) {
        return typeLabels[type] || type || '-';
    }

    function showMessage(root, type, message) {
        var el = qs('[data-user-payment-message]', root);
        if (!el) {
            return;
        }

        el.textContent = message;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-user-payment-message]', root);
        if (!el) {
            return;
        }

        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function errorMessage(error, fallback) {
        if (error && error.payload && error.payload.error && error.payload.error.message) {
            return error.payload.error.message;
        }
        if (error && error.status === 401) {
            return 'Sesion vencida. Inicia sesion nuevamente.';
        }
        if (error && error.status === 403) {
            return 'No tenes permiso para administrar tus metodos.';
        }
        if (error && error.status === 404) {
            return 'Metodo no encontrado.';
        }
        if (error && error.status === 409) {
            return 'Ya tenes asociado ese metodo.';
        }
        if (error && error.status === 422) {
            return 'Revisa los datos enviados.';
        }
        return fallback || 'No se pudo completar la operacion.';
    }

    function catalogLabel(method) {
        return method.name + ' - ' + typeLabel(method.type) + (method.issuer ? ' - ' + method.issuer : '');
    }

    function renderCatalog(root) {
        var target = qs('[data-user-payment-catalog]', root);
        var select = qs('[data-user-payment-select]', root);
        if (!state.catalog.length) {
            target.innerHTML = '<p class="muted">No hay metodos activos disponibles.</p>';
            select.innerHTML = '<option value="">Sin metodos disponibles</option>';
            return;
        }

        select.innerHTML = '<option value="">Selecciona un metodo</option>' + state.catalog.map(function (method) {
            return '<option value="' + method.id + '">' + escapeHtml(catalogLabel(method)) + '</option>';
        }).join('');

        target.innerHTML = state.catalog.map(function (method) {
            return '<div class="table-line">' +
                '<span><strong>' + escapeHtml(method.name) + '</strong><br><span class="muted">' + escapeHtml(method.issuer) + '</span></span>' +
                '<strong>' + escapeHtml(typeLabel(method.type)) + '</strong>' +
                '</div>';
        }).join('');
    }

    function renderSelected(root) {
        var body = qs('[data-user-payment-body]', root);
        if (!state.selected.length) {
            body.innerHTML = '<tr><td colspan="4" class="muted">Todavia no agregaste metodos de pago.</td></tr>';
            return;
        }

        body.innerHTML = state.selected.map(function (row) {
            var method = row.payment_method || {};
            return '<tr>' +
                '<td><strong>' + escapeHtml(method.name) + '</strong><br><span class="muted">' + escapeHtml(typeLabel(method.type)) + (method.issuer ? ' - ' + escapeHtml(method.issuer) : '') + '</span></td>' +
                '<td>' + escapeHtml(row.alias) + '</td>' +
                '<td>' + escapeHtml(row.status) + '</td>' +
                '<td><button type="button" class="btn-secondary-web btn-sm" style="color:#b33a3a" data-user-payment-delete="' + row.id + '">Quitar</button></td>' +
                '</tr>';
        }).join('');
    }

    function loadCatalog(root) {
        var type = qs('[data-user-payment-type]', root).value;
        var path = endpoint('/payment-methods') + (type ? '?type=' + encodeURIComponent(type) : '');
        qs('[data-user-payment-catalog]', root).innerHTML = '<p class="muted">Cargando catalogo...</p>';

        return window.CCApi.request(path)
            .then(function (response) {
                state.catalog = response.data || [];
                renderCatalog(root);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo cargar el catalogo.'));
                qs('[data-user-payment-catalog]', root).innerHTML = '<p class="muted">Error al cargar catalogo.</p>';
            });
    }

    function loadSelected(root) {
        qs('[data-user-payment-body]', root).innerHTML = '<tr><td colspan="4" class="muted">Cargando metodos...</td></tr>';

        return window.CCApi.request(endpoint('/users/me/payment-methods'))
            .then(function (response) {
                state.selected = response.data || [];
                renderSelected(root);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudieron cargar tus metodos.'));
                qs('[data-user-payment-body]', root).innerHTML = '<tr><td colspan="4" class="muted">Error al cargar.</td></tr>';
            });
    }

    function addMethod(root) {
        clearMessage(root);
        var form = qs('[data-user-payment-form]', root);
        var button = qs('[data-user-payment-submit]', root);
        var methodId = form.elements.payment_method_id.value;
        var alias = (form.elements.alias.value || '').trim();
        var body = {
            payment_method_id: parseInt(methodId, 10),
        };

        if (alias) {
            body.alias = alias;
        }

        button.disabled = true;
        button.textContent = 'Agregando...';

        window.CCApi.request(endpoint('/users/me/payment-methods'), { method: 'POST', body: body })
            .then(function () {
                showMessage(root, 'success', 'Metodo agregado.');
                form.reset();
                return loadSelected(root);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo agregar el metodo.'));
            })
            .then(function () {
                button.disabled = false;
                button.textContent = 'Agregar metodo';
            });
    }

    function removeMethod(root, id) {
        if (!window.confirm('Quitar este metodo de pago de tu perfil?')) {
            return;
        }

        window.CCApi.request(endpoint('/users/me/payment-methods/' + encodeURIComponent(id)), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Metodo quitado.');
                return loadSelected(root);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo quitar el metodo.'));
            });
    }

    function bind(root) {
        qs('[data-user-payment-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            addMethod(root);
        });
        qs('[data-user-payment-refresh]', root).addEventListener('click', function () {
            loadSelected(root);
        });
        qs('[data-user-payment-catalog-refresh]', root).addEventListener('click', function () {
            loadCatalog(root);
        });
        qs('[data-user-payment-type]', root).addEventListener('change', function () {
            loadCatalog(root);
        });
        root.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-user-payment-delete]');
            if (remove) {
                removeMethod(root, remove.getAttribute('data-user-payment-delete'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-payment-methods]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        loadCatalog(root);
        loadSelected(root);
    });
})(window, document);
