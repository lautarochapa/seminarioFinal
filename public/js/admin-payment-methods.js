(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        total: 0,
        editId: null,
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

    function showMessage(root, type, message) {
        var el = qs('[data-payment-methods-message]', root);
        if (!el) {
            return;
        }

        el.textContent = message;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-payment-methods-message]', root);
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
            return 'No tenes permiso para gestionar metodos de pago.';
        }
        if (error && error.status === 404) {
            return 'Metodo de pago inexistente.';
        }
        if (error && error.status === 409) {
            return 'Ya existe o hay un conflicto con ese metodo.';
        }
        if (error && error.status === 422) {
            return 'Revisa los datos del metodo.';
        }
        return fallback || 'No se pudo completar la operacion.';
    }

    function statusChip(status) {
        return status === 'active'
            ? '<span style="background:#e7f7f2;color:#04ac85;padding:2px 8px;border-radius:50px;font-size:12px">Activo</span>'
            : '<span style="background:#fdecea;color:#b33a3a;padding:2px 8px;border-radius:50px;font-size:12px">Inactivo</span>';
    }

    function typeLabel(type) {
        return typeLabels[type] || type || '-';
    }

    function fetchMethods(root, page) {
        clearMessage(root);
        state.page = page || 1;

        var params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', 20);

        var search = (qs('[data-payment-methods-search]', root).value || '').trim();
        var type = qs('[data-payment-methods-filter-type]', root).value;
        var status = qs('[data-payment-methods-filter-status]', root).value;

        if (search) {
            params.set('search', search);
        }
        if (type) {
            params.set('type', type);
        }
        if (status) {
            params.set('status', status);
        }

        qs('[data-payment-methods-body]', root).innerHTML = '<tr><td colspan="5" class="muted">Cargando metodos...</td></tr>';

        return window.CCApi.request(endpoint('/admin/payment-methods?' + params.toString()))
            .then(function (response) {
                var rows = response.data || [];
                state.total = response.meta ? response.meta.total : rows.length;
                state.lastPage = response.meta ? response.meta.last_page : 1;
                state.page = response.meta ? response.meta.current_page : state.page;
                renderTable(root, rows);
                qs('[data-payment-methods-count]', root).textContent = state.total + ' metodos';
                qs('[data-payment-methods-page]', root).textContent = 'Pagina ' + state.page + ' de ' + state.lastPage;
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudieron cargar los metodos.'));
                qs('[data-payment-methods-body]', root).innerHTML = '<tr><td colspan="5" class="muted">Error al cargar.</td></tr>';
            });
    }

    function renderTable(root, rows) {
        var tbody = qs('[data-payment-methods-body]', root);
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="muted">No se encontraron metodos.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (row) {
            var actions = '<button type="button" class="btn-ghost btn-sm" data-payment-method-edit="' + row.id + '">Editar</button> ';
            if (row.status === 'active') {
                actions += '<button type="button" class="btn-ghost btn-sm" style="color:var(--danger)" data-payment-method-delete="' + row.id + '">Desactivar</button>';
            } else {
                actions += '<button type="button" class="btn-ghost btn-sm" data-payment-method-restore="' + row.id + '">Restaurar</button>';
            }

            return '<tr>' +
                '<td><strong>' + escapeHtml(row.name) + '</strong></td>' +
                '<td>' + escapeHtml(typeLabel(row.type)) + '</td>' +
                '<td>' + escapeHtml(row.issuer) + '</td>' +
                '<td>' + statusChip(row.status) + '</td>' +
                '<td style="white-space:nowrap">' + actions + '</td>' +
                '</tr>';
        }).join('');
    }

    function resetForm(root) {
        state.editId = null;
        var form = qs('[data-payment-method-form]', root);
        form.reset();
        form.elements.id.value = '';
        qs('[data-payment-method-form-title]', root).textContent = 'Nuevo metodo';
        qs('[data-payment-method-submit]', root).textContent = 'Guardar metodo';
    }

    function setEditMode(root, row) {
        state.editId = row.id;
        var form = qs('[data-payment-method-form]', root);
        form.elements.id.value = row.id;
        form.elements.name.value = row.name || '';
        form.elements.type.value = row.type || '';
        form.elements.issuer.value = row.issuer || '';
        qs('[data-payment-method-form-title]', root).textContent = 'Editar metodo';
        qs('[data-payment-method-submit]', root).textContent = 'Guardar cambios';
    }

    function payload(root) {
        var form = qs('[data-payment-method-form]', root);
        var data = {};
        ['name', 'type', 'issuer'].forEach(function (key) {
            var value = (form.elements[key].value || '').trim();
            if (value !== '') {
                data[key] = value;
            }
        });
        return data;
    }

    function saveMethod(root) {
        clearMessage(root);
        var form = qs('[data-payment-method-form]', root);
        var button = qs('[data-payment-method-submit]', root);
        var id = form.elements.id.value;
        var request = id
            ? window.CCApi.request(endpoint('/admin/payment-methods/' + encodeURIComponent(id)), { method: 'PATCH', body: payload(root) })
            : window.CCApi.request(endpoint('/admin/payment-methods'), { method: 'POST', body: payload(root) });

        button.disabled = true;
        button.textContent = 'Guardando...';

        request.then(function () {
            showMessage(root, 'success', id ? 'Metodo actualizado.' : 'Metodo creado.');
            resetForm(root);
            return fetchMethods(root, state.page);
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error, 'No se pudo guardar el metodo.'));
        }).then(function () {
            button.disabled = false;
            button.textContent = state.editId ? 'Guardar cambios' : 'Guardar metodo';
        });
    }

    function loadMethod(root, id) {
        window.CCApi.request(endpoint('/admin/payment-methods/' + encodeURIComponent(id)))
            .then(function (response) {
                setEditMode(root, response.data || response);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo cargar el metodo.'));
            });
    }

    function deleteMethod(root, id) {
        if (!window.confirm('Desactivar este metodo de pago?')) {
            return;
        }

        window.CCApi.request(endpoint('/admin/payment-methods/' + encodeURIComponent(id)), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Metodo desactivado.');
                return fetchMethods(root, state.page);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo desactivar el metodo.'));
            });
    }

    function restoreMethod(root, id) {
        window.CCApi.request(endpoint('/admin/payment-methods/' + encodeURIComponent(id) + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Metodo restaurado.');
                return fetchMethods(root, state.page);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo restaurar el metodo.'));
            });
    }

    function bind(root) {
        qs('[data-payment-method-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            saveMethod(root);
        });
        qs('[data-payment-method-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-payment-methods-refresh]', root).addEventListener('click', function () {
            fetchMethods(root, 1);
        });
        qs('[data-payment-methods-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                fetchMethods(root, state.page - 1);
            }
        });
        qs('[data-payment-methods-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                fetchMethods(root, state.page + 1);
            }
        });
        ['[data-payment-methods-filter-type]', '[data-payment-methods-filter-status]'].forEach(function (selector) {
            qs(selector, root).addEventListener('change', function () {
                fetchMethods(root, 1);
            });
        });
        qs('[data-payment-methods-search]', root).addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                fetchMethods(root, 1);
            }
        });
        root.addEventListener('click', function (event) {
            var edit = event.target.closest('[data-payment-method-edit]');
            var remove = event.target.closest('[data-payment-method-delete]');
            var restore = event.target.closest('[data-payment-method-restore]');
            if (edit) {
                loadMethod(root, edit.getAttribute('data-payment-method-edit'));
            } else if (remove) {
                deleteMethod(root, remove.getAttribute('data-payment-method-delete'));
            } else if (restore) {
                restoreMethod(root, restore.getAttribute('data-payment-method-restore'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-payment-methods]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        fetchMethods(root, 1);
    });
})(window, document);
