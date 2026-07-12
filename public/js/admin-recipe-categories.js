(function (window, document) {
    'use strict';

    var state = {
        categories: [],
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

    function showMessage(root, type, message) {
        var alert = qs('[data-recipe-categories-message]', root);
        if (!alert) { return; }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-recipe-categories-message]', root);
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

    function endpoint(path) {
        return '/api/v1' + path;
    }

    function formData(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = entry[1];
            if (key === '_token' || key === 'id') { return; }
            if (value === '') {
                if (key === 'parent_id' || key === 'description') {
                    data[key] = null;
                }
                return;
            }
            if (key === 'parent_id') {
                data[key] = parseInt(value, 10);
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function categoryName(id) {
        var found = state.categories.find(function (c) {
            return String(c.id) === String(id);
        });
        return found ? found.name : null;
    }

    function fetchCategories(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-recipe-categories-search]', root);
        var status = qs('[data-recipe-categories-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');

        if (search && search.value) { params.set('search', search.value); }
        if (status && status.value) { params.set('status', status.value); }

        return window.CCApi.request(endpoint('/admin/recipe-categories?' + params.toString()))
            .then(function (response) {
                state.categories = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderTable(root, response.meta || {});
                renderParentOptions(root);
            })
            .catch(function (error) { handleError(root, error); });
    }

    function fetchTree(root) {
        return window.CCApi.request(endpoint('/recipe-categories'))
            .then(function (response) {
                renderTree(root, response.data || []);
            })
            .catch(function (error) { handleError(root, error); });
    }

    function renderTable(root, meta) {
        var body = qs('[data-recipe-categories-body]', root);
        var counter = qs('[data-recipe-categories-count]', root);
        var page = qs('[data-recipe-categories-page]', root);

        if (counter) {
            counter.textContent = (meta.total || state.categories.length) + ' categorias';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }

        if (!state.categories.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay categorias cargadas.</td></tr>';
            return;
        }

        body.innerHTML = state.categories.map(function (cat) {
            var deleted = cat.deleted_at ? '<span class="chip danger">eliminada</span>' : '';
            var action = cat.deleted_at
                ? '<button type="button" class="btn-ghost btn-sm" data-recipe-category-restore="' + cat.id + '">Restaurar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-recipe-category-delete="' + cat.id + '">Eliminar</button>';
            var parent = cat.parent_id ? (categoryName(cat.parent_id) || ('#' + cat.parent_id)) : 'Raiz';
            var recipes = (cat.recipes_count || 0) + ' recetas';

            return '<tr>' +
                '<td><strong>' + escapeHtml(cat.name) + '</strong>' +
                (cat.description ? '<br><span class="muted">' + escapeHtml(cat.description) + '</span>' : '') +
                '</td>' +
                '<td>' + escapeHtml(parent) + '</td>' +
                '<td>' + escapeHtml(cat.status) + ' ' + deleted + '</td>' +
                '<td>' + escapeHtml(recipes) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-recipe-category-edit="' + cat.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderParentOptions(root) {
        var select = qs('[data-recipe-category-parent]', root);
        var currentId = qs('[data-recipe-category-form]', root).elements.id.value;
        var currentValue = select.value;

        select.innerHTML = '<option value="">Sin categoria padre</option>' + state.categories
            .filter(function (c) {
                return !c.deleted_at && c.status === 'active' && String(c.id) !== String(currentId);
            })
            .map(function (c) {
                return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
            }).join('');

        if (currentValue) { select.value = currentValue; }
    }

    function renderTree(root, nodes) {
        var target = qs('[data-recipe-categories-tree]', root);
        if (!nodes.length) {
            target.innerHTML = '<span class="muted">No hay categorias activas para mostrar.</span>';
            return;
        }
        target.innerHTML = '<ul class="tree-panel">' + nodes.map(renderTreeNode).join('') + '</ul>';
    }

    function renderTreeNode(node) {
        var children = node.children && node.children.length
            ? '<ul>' + node.children.map(renderTreeNode).join('') + '</ul>'
            : '';
        return '<li>' +
            '<div class="tree-node"><div><strong>' + escapeHtml(node.name) + '</strong></div>' +
            '<span class="chip">' + ((node.children || []).length) + ' hijas</span></div>' +
            children +
            '</li>';
    }

    function fillForm(root, cat) {
        var form = qs('[data-recipe-category-form]', root);
        qs('[data-recipe-category-form-title]', root).textContent = 'Editar categoria';
        form.elements.id.value = cat.id;
        form.elements.name.value = cat.name || '';
        form.elements.description.value = cat.description || '';
        form.elements.status.value = cat.status || 'active';
        renderParentOptions(root);
        form.elements.parent_id.value = cat.parent_id || '';
    }

    function resetForm(root) {
        var form = qs('[data-recipe-category-form]', root);
        form.reset();
        form.elements.id.value = '';
        qs('[data-recipe-category-form-title]', root).textContent = 'Nueva categoria';
        renderParentOptions(root);
    }

    function bind(root) {
        var form = qs('[data-recipe-category-form]', root);

        qs('[data-recipe-categories-refresh]', root).addEventListener('click', function () {
            fetchCategories(root, 1);
        });
        qs('[data-recipe-categories-tree-refresh]', root).addEventListener('click', function () {
            fetchTree(root);
        });
        qs('[data-recipe-categories-search]', root).addEventListener('input', debounce(function () {
            fetchCategories(root, 1);
        }, 300));
        qs('[data-recipe-categories-status]', root).addEventListener('change', function () {
            fetchCategories(root, 1);
        });
        qs('[data-recipe-categories-prev]', root).addEventListener('click', function () {
            if (state.page > 1) { fetchCategories(root, state.page - 1); }
        });
        qs('[data-recipe-categories-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) { fetchCategories(root, state.page + 1); }
        });
        qs('[data-recipe-category-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-recipe-category-restore-submit]', root).addEventListener('click', function () {
            var input = qs('[data-recipe-category-restore-id]', root);
            var id = input ? input.value : '';
            if (!id) {
                showMessage(root, 'danger', 'Ingresá el ID de una categoria eliminada.');
                return;
            }
            window.CCApi.request(endpoint('/admin/recipe-categories/' + id + '/restore'), { method: 'PATCH' })
                .then(function () {
                    if (input) { input.value = ''; }
                    showMessage(root, 'success', 'Categoria restaurada correctamente.');
                    return fetchCategories(root, state.page);
                })
                .then(function () { return fetchTree(root); })
                .catch(function (error) { handleError(root, error); });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);
            var submitBtn = form.querySelector('[type=submit]');
            if (submitBtn) { submitBtn.disabled = true; }

            var id = form.elements.id.value;
            var method = id ? 'PATCH' : 'POST';
            var path = '/admin/recipe-categories' + (id ? '/' + id : '');

            window.CCApi.request(endpoint(path), { method: method, body: formData(form) })
                .then(function () {
                    resetForm(root);
                    showMessage(root, 'success', id ? 'Categoria actualizada correctamente.' : 'Categoria creada correctamente.');
                    return fetchCategories(root, state.page);
                })
                .then(function () { return fetchTree(root); })
                .catch(function (error) { handleError(root, error); })
                .then(function () {
                    if (submitBtn) { submitBtn.disabled = false; }
                });
        });

        root.addEventListener('click', function (event) {
            var editId = event.target.getAttribute('data-recipe-category-edit');
            var deleteId = event.target.getAttribute('data-recipe-category-delete');
            var restoreId = event.target.getAttribute('data-recipe-category-restore');

            if (editId) {
                var cat = state.categories.find(function (c) { return String(c.id) === String(editId); });
                if (cat) { fillForm(root, cat); }
            }

            if (deleteId) {
                if (!window.confirm('¿Eliminar esta categoria?')) { return; }
                window.CCApi.request(endpoint('/admin/recipe-categories/' + deleteId), { method: 'DELETE' })
                    .then(function () {
                        showMessage(root, 'success', 'Categoria eliminada correctamente.');
                        return fetchCategories(root, state.page);
                    })
                    .then(function () { return fetchTree(root); })
                    .catch(function (error) { handleError(root, error); });
            }

            if (restoreId) {
                window.CCApi.request(endpoint('/admin/recipe-categories/' + restoreId + '/restore'), { method: 'PATCH' })
                    .then(function () {
                        showMessage(root, 'success', 'Categoria restaurada correctamente.');
                        return fetchCategories(root, state.page);
                    })
                    .then(function () { return fetchTree(root); })
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
        var root = qs('[data-admin-recipe-categories]');
        if (!root || !window.CCApi) { return; }

        bind(root);
        fetchCategories(root, 1).then(function () {
            return fetchTree(root);
        });
    });
})(window, document);
