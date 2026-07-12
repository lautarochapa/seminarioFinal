(function (window, document) {
    'use strict';

    var state = {
        objectives: [],
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
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
        var alert = qs('[data-objectives-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-objectives-message]', root);
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

    function fetchObjectives(root) {
        var params = new URLSearchParams();
        var search = qs('[data-objectives-search]', root);
        var deleted = qs('[data-objectives-deleted]', root);

        params.set('per_page', '50');
        if (search && search.value) {
            params.set('search', search.value);
        }
        if (deleted && deleted.checked) {
            params.set('include_deleted', '1');
        }

        return window.CCApi.request('/api/v1/admin/objectives?' + params.toString()).then(function (response) {
            state.objectives = response.data || [];
            renderObjectives(root, response.meta || {});
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderObjectives(root, meta) {
        var body = qs('[data-objectives-body]', root);
        var counter = qs('[data-objectives-count]', root);

        if (counter) {
            counter.textContent = (meta.total || state.objectives.length) + ' objetivos';
        }

        if (!state.objectives.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay objetivos cargados.</td></tr>';
            return;
        }

        body.innerHTML = state.objectives.map(function (objective) {
            var deleted = objective.deleted_at ? '<span class="chip danger">eliminado</span>' : '';
            var action = objective.deleted_at
                ? '<button type="button" class="btn-ghost btn-sm" data-objective-restore="' + objective.id + '">Restaurar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-objective-delete="' + objective.id + '">Eliminar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(objective.code) + '</strong></td>' +
                '<td>' + escapeHtml(objective.name) + '</td>' +
                '<td>' + escapeHtml(objective.description) + '</td>' +
                '<td>' + escapeHtml(objective.status) + ' ' + deleted + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-objective-edit="' + objective.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function bind(root) {
        var form = qs('[data-objective-form]', root);
        var title = qs('[data-objective-form-title]', root);

        qs('[data-objectives-refresh]', root).addEventListener('click', function () {
            fetchObjectives(root);
        });
        qs('[data-objectives-search]', root).addEventListener('input', debounce(function () {
            fetchObjectives(root);
        }, 300));
        qs('[data-objectives-deleted]', root).addEventListener('change', function () {
            fetchObjectives(root);
        });

        qs('[data-objective-reset]', root).addEventListener('click', function () {
            form.reset();
            form.elements.id.value = '';
            title.textContent = 'Nuevo objetivo';
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);

            var id = form.elements.id.value;
            var method = id ? 'PATCH' : 'POST';
            var endpoint = '/api/v1/admin/objectives' + (id ? '/' + id : '');

            window.CCApi.request(endpoint, { method: method, body: formData(form) })
                .then(function () {
                    form.reset();
                    form.elements.id.value = '';
                    title.textContent = 'Nuevo objetivo';
                    showMessage(root, 'success', id ? 'Objetivo actualizado correctamente.' : 'Objetivo creado correctamente.');
                    return fetchObjectives(root);
                })
                .catch(function (error) { handleError(root, error); });
        });

        root.addEventListener('click', function (event) {
            var editId = event.target.getAttribute('data-objective-edit');
            var deleteId = event.target.getAttribute('data-objective-delete');
            var restoreId = event.target.getAttribute('data-objective-restore');

            if (editId) {
                var objective = state.objectives.find(function (item) { return String(item.id) === String(editId); });
                if (!objective) {
                    return;
                }
                form.elements.id.value = objective.id;
                form.elements.code.value = objective.code || '';
                form.elements.name.value = objective.name || '';
                form.elements.description.value = objective.description || '';
                form.elements.status.value = objective.status || 'active';
                title.textContent = 'Editar objetivo';
            }

            if (deleteId) {
                window.CCApi.request('/api/v1/admin/objectives/' + deleteId, { method: 'DELETE' })
                    .then(function () {
                        showMessage(root, 'success', 'Objetivo eliminado correctamente.');
                        return fetchObjectives(root);
                    })
                    .catch(function (error) { handleError(root, error); });
            }

            if (restoreId) {
                window.CCApi.request('/api/v1/admin/objectives/' + restoreId + '/restore', { method: 'PATCH' })
                    .then(function () {
                        showMessage(root, 'success', 'Objetivo restaurado correctamente.');
                        return fetchObjectives(root);
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
        var root = qs('[data-admin-objectives]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        fetchObjectives(root);
    });
})(window, document);
