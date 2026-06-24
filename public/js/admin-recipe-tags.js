(function (window, document) {
    'use strict';

    var state = {
        tags: [],
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
        var alert = qs('[data-recipe-tags-message]', root);
        if (!alert) { return; }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-recipe-tags-message]', root);
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

    function buildPayload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id') { return; }
            if (value === '') {
                if (key === 'description' || key === 'type') {
                    data[key] = null;
                }
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function normalizeCode(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function fetchTags(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-recipe-tags-search]', root);
        var type   = qs('[data-recipe-tags-type]', root);
        var status = qs('[data-recipe-tags-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'name');
        params.set('order', 'asc');

        if (search && search.value) { params.set('search', search.value); }
        if (type && type.value)     { params.set('type', type.value); }
        if (status && status.value) { params.set('status', status.value); }

        return window.CCApi.request(endpoint('/admin/recipe-tags?' + params.toString()))
            .then(function (response) {
                state.tags     = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderTable(root, response.meta || {});
            })
            .catch(function (error) { handleError(root, error); });
    }

    function fetchCatalog(root) {
        return window.CCApi.request(endpoint('/recipe-tags?per_page=100&sort=name&order=asc'))
            .then(function (response) {
                renderCatalog(root, response.data || [], response.meta || {});
            })
            .catch(function (error) { handleError(root, error); });
    }

    function renderTable(root, meta) {
        var body    = qs('[data-recipe-tags-body]', root);
        var counter = qs('[data-recipe-tags-count]', root);
        var page    = qs('[data-recipe-tags-page]', root);

        if (counter) {
            counter.textContent = (meta.total || state.tags.length) + ' tags';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }

        if (!state.tags.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay tags cargados.</td></tr>';
            return;
        }

        body.innerHTML = state.tags.map(function (tag) {
            var action = tag.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-recipe-tag-delete="' + tag.id + '">Desactivar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-recipe-tag-restore="' + tag.id + '">Activar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(tag.code) + '</strong></td>' +
                '<td>' + escapeHtml(tag.name) + '</td>' +
                '<td>' + escapeHtml(tag.type) + '</td>' +
                '<td>' + escapeHtml(tag.description) + '</td>' +
                '<td>' + escapeHtml(tag.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-recipe-tag-edit="' + tag.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderCatalog(root, tags, meta) {
        var target  = qs('[data-recipe-tags-catalog]', root);
        var counter = qs('[data-recipe-tags-catalog-count]', root);

        if (counter) {
            counter.textContent = (meta.total || tags.length) + ' activos';
        }

        if (!tags.length) {
            target.innerHTML = '<span class="muted">No hay tags activos.</span>';
            return;
        }

        target.innerHTML = tags.map(function (tag) {
            return '<span class="chip" title="' + escapeHtml(tag.type || '') + '">' +
                escapeHtml(tag.name) +
                (tag.type ? ' <span class="muted">· ' + escapeHtml(tag.type) + '</span>' : '') +
                '</span>';
        }).join(' ');
    }

    function fillForm(root, tag) {
        var form = qs('[data-recipe-tag-form]', root);
        qs('[data-recipe-tag-form-title]', root).textContent = 'Editar tag #' + tag.id;
        form.elements.id.value          = tag.id;
        form.elements.code.value        = tag.code || '';
        form.elements.name.value        = tag.name || '';
        form.elements.description.value = tag.description || '';
        form.elements.type.value        = tag.type || '';
        form.elements.status.value      = tag.status || 'active';
    }

    function resetForm(root) {
        var form = qs('[data-recipe-tag-form]', root);
        form.reset();
        form.elements.id.value     = '';
        form.elements.status.value = 'active';
        qs('[data-recipe-tag-form-title]', root).textContent = 'Nuevo tag';
    }

    function bind(root) {
        var form = qs('[data-recipe-tag-form]', root);

        qs('[data-recipe-tags-refresh]', root).addEventListener('click', function () {
            fetchTags(root, 1);
        });
        qs('[data-recipe-tags-catalog-refresh]', root).addEventListener('click', function () {
            fetchCatalog(root);
        });
        qs('[data-recipe-tags-search]', root).addEventListener('input', debounce(function () {
            fetchTags(root, 1);
        }, 300));
        qs('[data-recipe-tags-type]', root).addEventListener('change', function () {
            fetchTags(root, 1);
        });
        qs('[data-recipe-tags-status]', root).addEventListener('change', function () {
            fetchTags(root, 1);
        });
        qs('[data-recipe-tags-prev]', root).addEventListener('click', function () {
            if (state.page > 1) { fetchTags(root, state.page - 1); }
        });
        qs('[data-recipe-tags-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) { fetchTags(root, state.page + 1); }
        });
        qs('[data-recipe-tag-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });

        form.elements.code.addEventListener('blur', function () {
            form.elements.code.value = normalizeCode(form.elements.code.value);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);
            var submitBtn = form.querySelector('[type=submit]');
            if (submitBtn) { submitBtn.disabled = true; }

            var id     = form.elements.id.value;
            var method = id ? 'PATCH' : 'POST';
            var path   = '/admin/recipe-tags' + (id ? '/' + id : '');

            window.CCApi.request(endpoint(path), { method: method, body: buildPayload(form) })
                .then(function () {
                    showMessage(root, 'success', id ? 'Tag actualizado correctamente.' : 'Tag creado correctamente.');
                    resetForm(root);
                    return Promise.all([fetchTags(root, state.page), fetchCatalog(root)]);
                })
                .catch(function (error) { handleError(root, error); })
                .then(function () {
                    if (submitBtn) { submitBtn.disabled = false; }
                });
        });

        root.addEventListener('click', function (event) {
            var editId    = event.target.getAttribute('data-recipe-tag-edit');
            var deleteId  = event.target.getAttribute('data-recipe-tag-delete');
            var restoreId = event.target.getAttribute('data-recipe-tag-restore');

            if (editId) {
                var tag = state.tags.find(function (t) { return String(t.id) === String(editId); });
                if (tag) { fillForm(root, tag); }
            }

            if (deleteId) {
                if (!window.confirm('¿Desactivar este tag? Las recetas que lo usan lo perderán.')) { return; }
                window.CCApi.request(endpoint('/admin/recipe-tags/' + deleteId), { method: 'DELETE' })
                    .then(function () {
                        showMessage(root, 'success', 'Tag desactivado correctamente.');
                        return Promise.all([fetchTags(root, state.page), fetchCatalog(root)]);
                    })
                    .catch(function (error) { handleError(root, error); });
            }

            if (restoreId) {
                window.CCApi.request(endpoint('/admin/recipe-tags/' + restoreId + '/restore'), { method: 'PATCH' })
                    .then(function () {
                        showMessage(root, 'success', 'Tag activado correctamente.');
                        return Promise.all([fetchTags(root, state.page), fetchCatalog(root)]);
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
        var root = qs('[data-admin-recipe-tags]');
        if (!root || !window.CCApi) { return; }

        resetForm(root);
        bind(root);
        fetchTags(root, 1);
        fetchCatalog(root);
    });
})(window, document);
