(function (window, document) {
    'use strict';

    var state = {
        mealTypes: [],
        catalog: [],
        page: 1,
        lastPage: 1,
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

    function endpoint(path) {
        return '/api/v1' + path;
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-meal-types-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-meal-types-message]', root);
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

    function payload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id' || value === '') {
                return;
            }
            if (key === 'sort_order') {
                data[key] = Number(value);
                return;
            }
            data[key] = value;
        });
        if (data.code) {
            data.code = String(data.code).toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
        }
        return data;
    }

    function fetchMealTypes(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-meal-types-search]', root);
        var status = qs('[data-meal-types-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'sort_order');
        params.set('order', 'asc');

        if (search && search.value) {
            params.set('search', search.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/meal-types?' + params.toString()))
            .then(function (response) {
                state.mealTypes = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderMealTypes(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderMealTypes(root, meta) {
        var body = qs('[data-meal-types-body]', root);
        var count = qs('[data-meal-types-count]', root);
        var page = qs('[data-meal-types-page]', root);

        if (count) {
            count.textContent = (meta.total || state.mealTypes.length) + ' tipos';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }
        if (!body) {
            return;
        }
        if (!state.mealTypes.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay tipos de comida cargados.</td></tr>';
            return;
        }

        body.innerHTML = state.mealTypes.map(function (item) {
            var action = item.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-meal-type-delete="' + item.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-meal-type-restore="' + item.id + '">Restaurar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(item.code) + '</strong></td>' +
                '<td>' + escapeHtml(item.name) + '</td>' +
                '<td>' + escapeHtml(item.sort_order) + '</td>' +
                '<td>' + escapeHtml(item.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-meal-type-edit="' + item.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function fetchCatalog(root) {
        return window.CCApi.request(endpoint('/meal-types'))
            .then(function (response) {
                state.catalog = response.data || [];
                renderCatalog(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderCatalog(root) {
        var target = qs('[data-meal-types-public-results]', root);
        var count = qs('[data-meal-types-public-count]', root);
        if (count) {
            count.textContent = state.catalog.length + ' tipos activos';
        }
        if (!target) {
            return;
        }
        if (!state.catalog.length) {
            target.innerHTML = '<span class="muted">No hay tipos activos para mostrar.</span>';
            return;
        }
        target.innerHTML = state.catalog.map(function (item) {
            return '<span class="chip">' + escapeHtml(item.sort_order) + ' · ' + escapeHtml(item.name) + '</span>';
        }).join('');
    }

    function resetForm(root) {
        var form = qs('[data-meal-type-form]', root);
        if (!form) {
            return;
        }
        form.reset();
        form.elements.id.value = '';
        form.elements.sort_order.value = '0';
        form.elements.status.value = 'active';
        qs('[data-meal-type-form-title]', root).textContent = 'Nuevo tipo de comida';
    }

    function fillForm(root, item) {
        var form = qs('[data-meal-type-form]', root);
        if (!form) {
            return;
        }
        form.elements.id.value = item.id;
        form.elements.code.value = item.code || '';
        form.elements.name.value = item.name || '';
        form.elements.sort_order.value = item.sort_order || 0;
        form.elements.status.value = item.status || 'active';
        qs('[data-meal-type-form-title]', root).textContent = 'Editar tipo #' + item.id;
    }

    function saveMealType(root, form) {
        clearMessage(root);
        var id = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path = id ? '/admin/meal-types/' + encodeURIComponent(id) : '/admin/meal-types';

        return window.CCApi.request(endpoint(path), {
            method: method,
            body: payload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Tipo actualizado.' : 'Tipo creado.');
            resetForm(root);
            return Promise.all([fetchMealTypes(root, state.page), fetchCatalog(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteMealType(root, id) {
        if (!window.confirm('Eliminar este tipo de comida?')) {
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/meal-types/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Tipo eliminado.');
            return Promise.all([fetchMealTypes(root, state.page), fetchCatalog(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function restoreMealType(root, id) {
        if (!id) {
            showMessage(root, 'danger', 'Indica el ID del tipo a restaurar.');
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/meal-types/' + encodeURIComponent(id) + '/restore'), {
            method: 'PATCH',
        }).then(function () {
            showMessage(root, 'success', 'Tipo restaurado.');
            var input = qs('[data-meal-type-restore-id]', root);
            if (input) {
                input.value = '';
            }
            return Promise.all([fetchMealTypes(root, state.page), fetchCatalog(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        qs('[data-meal-types-refresh]', root).addEventListener('click', function () {
            fetchMealTypes(root, 1);
        });
        qs('[data-meal-types-public-refresh]', root).addEventListener('click', function () {
            fetchCatalog(root);
        });
        qs('[data-meal-types-prev]', root).addEventListener('click', function () {
            fetchMealTypes(root, Math.max(1, state.page - 1));
        });
        qs('[data-meal-types-next]', root).addEventListener('click', function () {
            fetchMealTypes(root, Math.min(state.lastPage, state.page + 1));
        });
        qs('[data-meal-type-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-meal-type-restore-submit]', root).addEventListener('click', function () {
            restoreMealType(root, qs('[data-meal-type-restore-id]', root).value);
        });
        qs('[data-meal-type-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            saveMealType(root, event.currentTarget);
        });
        qs('[data-meal-types-body]', root).addEventListener('click', function (event) {
            var edit = event.target.closest('[data-meal-type-edit]');
            var remove = event.target.closest('[data-meal-type-delete]');
            var restore = event.target.closest('[data-meal-type-restore]');

            if (edit) {
                var id = parseInt(edit.getAttribute('data-meal-type-edit'), 10);
                var item = state.mealTypes.find(function (mealType) {
                    return mealType.id === id;
                });
                if (item) {
                    fillForm(root, item);
                }
            }
            if (remove) {
                deleteMealType(root, remove.getAttribute('data-meal-type-delete'));
            }
            if (restore) {
                restoreMealType(root, restore.getAttribute('data-meal-type-restore'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-meal-types]');
        if (!root) {
            return;
        }
        bind(root);
        Promise.all([fetchMealTypes(root, 1), fetchCatalog(root)]);
    });
})(window, document);
