(function (window, document) {
    'use strict';

    var state = {
        currentType: 'dietary-restrictions',
        items: [],
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function text(value) {
        return value === null || value === undefined || value === '' ? '-' : String(value);
    }

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-health-preferences-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-health-preferences-message]', root);
        if (alert) {
            alert.textContent = '';
            alert.className = 'alert';
            alert.style.display = 'none';
        }
    }

    function handleError(root, error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        showMessage(root, 'danger', apiError.message || error.message || 'No se pudo completar la operacion.');
    }

    function formData(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            if (entry[0] !== '_token' && entry[1] !== '') {
                data[entry[0]] = entry[1];
            }
        });
        return data;
    }

    function fetchItems(root) {
        var params = new URLSearchParams();
        var search = qs('[data-health-search]', root);
        var deleted = qs('[data-health-deleted]', root);

        params.set('per_page', '50');
        if (search && search.value) {
            params.set('search', search.value);
        }
        if (deleted && deleted.checked) {
            params.set('include_deleted', '1');
        }

        return window.CCApi.request('/api/v1/admin/' + state.currentType + '?' + params.toString()).then(function (response) {
            state.items = response.data || [];
            renderItems(root, response.meta || {});
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderItems(root, meta) {
        var body = qs('[data-health-body]', root);
        var counter = qs('[data-health-count]', root);
        if (counter) {
            counter.textContent = (meta.total || state.items.length) + ' items';
        }

        if (!state.items.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay items cargados.</td></tr>';
            return;
        }

        body.innerHTML = state.items.map(function (item) {
            var deleted = item.deleted_at ? '<span class="chip danger">eliminado</span>' : '';
            var action = item.deleted_at
                ? '<button type="button" class="btn-ghost btn-sm" data-health-restore="' + item.id + '">Restaurar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-health-delete="' + item.id + '">Eliminar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(item.code) + '</strong></td>' +
                '<td>' + escapeHtml(item.name) + '</td>' +
                '<td>' + escapeHtml(item.description) + '</td>' +
                '<td>' + escapeHtml(item.status) + ' ' + deleted + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-health-edit="' + item.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function setTab(root, type) {
        state.currentType = type;
        qsa('[data-health-tab]', root).forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-health-tab') === type);
        });
        qs('[data-health-form-title]', root).textContent = 'Nuevo item';
        qs('[data-health-form]', root).reset();
        fetchItems(root);
    }

    function bind(root) {
        var form = qs('[data-health-form]', root);
        var title = qs('[data-health-form-title]', root);

        qsa('[data-health-tab]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                setTab(root, button.getAttribute('data-health-tab'));
            });
        });

        qs('[data-health-refresh]', root).addEventListener('click', function () {
            fetchItems(root);
        });
        qs('[data-health-search]', root).addEventListener('input', debounce(function () {
            fetchItems(root);
        }, 300));
        qs('[data-health-deleted]', root).addEventListener('change', function () {
            fetchItems(root);
        });

        qs('[data-health-reset]', root).addEventListener('click', function () {
            form.reset();
            form.elements.id.value = '';
            title.textContent = 'Nuevo item';
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);

            var id = form.elements.id.value;
            var endpoint = '/api/v1/admin/' + state.currentType + (id ? '/' + id : '');
            var method = id ? 'PATCH' : 'POST';

            window.CCApi.request(endpoint, { method: method, body: formData(form) })
                .then(function () {
                    form.reset();
                    form.elements.id.value = '';
                    title.textContent = 'Nuevo item';
                    showMessage(root, 'success', id ? 'Item actualizado correctamente.' : 'Item creado correctamente.');
                    return fetchItems(root);
                })
                .catch(function (error) { handleError(root, error); });
        });

        root.addEventListener('click', function (event) {
            var editId = event.target.getAttribute('data-health-edit');
            var deleteId = event.target.getAttribute('data-health-delete');
            var restoreId = event.target.getAttribute('data-health-restore');

            if (editId) {
                var item = state.items.find(function (row) { return String(row.id) === String(editId); });
                if (!item) {
                    return;
                }
                form.elements.id.value = item.id;
                form.elements.code.value = item.code || '';
                form.elements.name.value = item.name || '';
                form.elements.description.value = item.description || '';
                form.elements.status.value = item.status || 'active';
                title.textContent = 'Editar item';
            }

            if (deleteId) {
                window.CCApi.request('/api/v1/admin/' + state.currentType + '/' + deleteId, { method: 'DELETE' })
                    .then(function () {
                        showMessage(root, 'success', 'Item eliminado correctamente.');
                        return fetchItems(root);
                    })
                    .catch(function (error) { handleError(root, error); });
            }

            if (restoreId) {
                window.CCApi.request('/api/v1/admin/' + state.currentType + '/' + restoreId + '/restore', { method: 'PATCH' })
                    .then(function () {
                        showMessage(root, 'success', 'Item restaurado correctamente.');
                        return fetchItems(root);
                    })
                    .catch(function (error) { handleError(root, error); });
            }
        });
    }

    function debounce(fn, wait) {
        var timeout;
        return function () {
            clearTimeout(timeout);
            timeout = setTimeout(fn, wait);
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-health-preferences]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        fetchItems(root);
    });
})(window, document);
