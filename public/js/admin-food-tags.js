(function (window, document) {
    'use strict';

    var state = {
        tags: [],
        publicTags: [],
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
        var alert = qs('[data-food-tags-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-food-tags-message]', root);
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

    function fetchTags(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-food-tags-search]', root);
        var type = qs('[data-food-tags-type]', root);
        var status = qs('[data-food-tags-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'name');
        params.set('order', 'asc');

        if (search && search.value) {
            params.set('search', search.value);
        }
        if (type && type.value) {
            params.set('type', type.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/food-tags?' + params.toString()))
            .then(function (response) {
                state.tags = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderTags(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderTags(root, meta) {
        var body = qs('[data-food-tags-body]', root);
        var count = qs('[data-food-tags-count]', root);
        var page = qs('[data-food-tags-page]', root);

        if (count) {
            count.textContent = (meta.total || state.tags.length) + ' tags';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }
        if (!state.tags.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay tags alimentarios cargados.</td></tr>';
            return;
        }

        body.innerHTML = state.tags.map(function (tag) {
            var action = tag.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-food-tag-delete="' + tag.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-food-tag-restore="' + tag.id + '">Restaurar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(tag.code) + '</strong></td>' +
                '<td>' + escapeHtml(tag.name) + '</td>' +
                '<td>' + escapeHtml(tag.type) + '</td>' +
                '<td>' + escapeHtml(tag.description) + '</td>' +
                '<td>' + escapeHtml(tag.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-food-tag-edit="' + tag.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function fetchPublicTags(root) {
        var params = new URLSearchParams();
        var search = qs('[data-food-tags-public-search]', root);

        params.set('per_page', '100');
        params.set('sort', 'name');
        params.set('order', 'asc');
        if (search && search.value) {
            params.set('search', search.value);
        }

        return window.CCApi.request(endpoint('/food-tags?' + params.toString()))
            .then(function (response) {
                state.publicTags = response.data || [];
                renderPublicTags(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderPublicTags(root, meta) {
        var target = qs('[data-food-tags-public-results]', root);
        var count = qs('[data-food-tags-public-count]', root);

        if (count) {
            count.textContent = (meta.total || state.publicTags.length) + ' tags activos';
        }
        if (!state.publicTags.length) {
            target.innerHTML = '<span class="muted">No hay tags activos para mostrar.</span>';
            return;
        }

        target.innerHTML = state.publicTags.map(function (tag) {
            return '<span class="chip">' + escapeHtml(tag.name) + (tag.type ? ' · ' + escapeHtml(tag.type) : '') + '</span>';
        }).join('');
    }

    function payload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id' || value === '') {
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function resetForm(root) {
        var form = qs('[data-food-tag-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-food-tag-form-title]', root).textContent = 'Nuevo tag alimentario';
    }

    function fillForm(root, tag) {
        var form = qs('[data-food-tag-form]', root);
        form.elements.id.value = tag.id;
        form.elements.code.value = tag.code || '';
        form.elements.name.value = tag.name || '';
        form.elements.type.value = tag.type || '';
        form.elements.description.value = tag.description || '';
        form.elements.status.value = tag.status || 'active';
        qs('[data-food-tag-form-title]', root).textContent = 'Editar tag #' + tag.id;
    }

    function saveTag(root, form) {
        clearMessage(root);
        var id = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path = id ? '/admin/food-tags/' + encodeURIComponent(id) : '/admin/food-tags';

        return window.CCApi.request(endpoint(path), {
            method: method,
            body: payload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Tag actualizado.' : 'Tag creado.');
            resetForm(root);
            return Promise.all([fetchTags(root, state.page), fetchPublicTags(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteTag(root, id) {
        if (!window.confirm('Eliminar este tag alimentario?')) {
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/food-tags/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Tag eliminado.');
            return Promise.all([fetchTags(root, state.page), fetchPublicTags(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function restoreTag(root, id) {
        if (!id) {
            showMessage(root, 'danger', 'Indica el ID del tag a restaurar.');
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/food-tags/' + encodeURIComponent(id) + '/restore'), {
            method: 'PATCH',
        }).then(function () {
            showMessage(root, 'success', 'Tag restaurado.');
            var input = qs('[data-food-tag-restore-id]', root);
            if (input) {
                input.value = '';
            }
            return Promise.all([fetchTags(root, state.page), fetchPublicTags(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        qs('[data-food-tags-refresh]', root).addEventListener('click', function () {
            fetchTags(root, 1);
        });
        qs('[data-food-tags-public-refresh]', root).addEventListener('click', function () {
            fetchPublicTags(root);
        });
        qs('[data-food-tags-prev]', root).addEventListener('click', function () {
            fetchTags(root, Math.max(1, state.page - 1));
        });
        qs('[data-food-tags-next]', root).addEventListener('click', function () {
            fetchTags(root, Math.min(state.lastPage, state.page + 1));
        });
        qs('[data-food-tag-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-food-tag-restore-submit]', root).addEventListener('click', function () {
            restoreTag(root, qs('[data-food-tag-restore-id]', root).value);
        });
        qs('[data-food-tag-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            saveTag(root, event.currentTarget);
        });
        qs('[data-food-tags-body]', root).addEventListener('click', function (event) {
            var edit = event.target.closest('[data-food-tag-edit]');
            var remove = event.target.closest('[data-food-tag-delete]');
            var restore = event.target.closest('[data-food-tag-restore]');

            if (edit) {
                var id = parseInt(edit.getAttribute('data-food-tag-edit'), 10);
                var tag = state.tags.find(function (item) {
                    return item.id === id;
                });
                if (tag) {
                    fillForm(root, tag);
                }
            }
            if (remove) {
                deleteTag(root, remove.getAttribute('data-food-tag-delete'));
            }
            if (restore) {
                restoreTag(root, restore.getAttribute('data-food-tag-restore'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-food-tags]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        resetForm(root);
        fetchTags(root, 1);
        fetchPublicTags(root);
    });
})(window, document);
