(function (window, document) {
    'use strict';

    var state = {
        categories: [],
        tree: [],
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
        var alert = qs('[data-product-categories-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-product-categories-message]', root);
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

    function categoryNameById(id) {
        if (!id) {
            return '-';
        }
        var category = state.categories.find(function (item) {
            return String(item.id) === String(id);
        });
        return category ? category.name : '#' + id;
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function fetchCategories(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-product-categories-search]', root);
        var status = qs('[data-product-categories-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'name');
        params.set('order', 'asc');

        if (search && search.value) {
            params.set('search', search.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/product-categories?' + params.toString()))
            .then(function (response) {
                state.categories = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderCategories(root, response.meta || {});
                renderParentOptions(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderCategories(root, meta) {
        var body = qs('[data-product-categories-body]', root);
        var count = qs('[data-product-categories-count]', root);
        var page = qs('[data-product-categories-page]', root);

        if (count) {
            count.textContent = (meta.total || state.categories.length) + ' categorias';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }
        if (!state.categories.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay categorias cargadas.</td></tr>';
            return;
        }

        body.innerHTML = state.categories.map(function (category) {
            var action = category.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-product-category-delete="' + category.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-product-category-restore="' + category.id + '">Restaurar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(category.name) + '</strong></td>' +
                '<td>' + escapeHtml(categoryNameById(category.parent_id)) + '</td>' +
                '<td>' + escapeHtml(category.description) + '</td>' +
                '<td>' + escapeHtml(category.children_count) + '</td>' +
                '<td>' + escapeHtml(category.products_count) + '</td>' +
                '<td>' + escapeHtml(category.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-product-category-edit="' + category.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderParentOptions(root) {
        var select = qs('[data-product-category-parent]', root);
        var form = qs('[data-product-category-form]', root);
        var current = select.value;
        var editingId = form.elements.id.value;
        var activeCategories = state.categories.filter(function (category) {
            return category.status === 'active' && String(category.id) !== String(editingId);
        });

        select.innerHTML = '<option value="">Categoria raiz</option>' + activeCategories.map(function (category) {
            return option(category.name, category.id);
        }).join('');
        select.value = current;
    }

    function fetchTree(root) {
        return window.CCApi.request(endpoint('/product-categories'))
            .then(function (response) {
                state.tree = response.data || [];
                renderTree(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderTree(root) {
        var target = qs('[data-product-categories-tree]', root);
        if (!state.tree.length) {
            target.innerHTML = '<span class="muted">No hay categorias activas para mostrar.</span>';
            return;
        }
        target.innerHTML = '<ul class="tree-panel">' + state.tree.map(renderTreeNode).join('') + '</ul>';
    }

    function renderTreeNode(category) {
        var children = category.children || [];
        return '<li>' +
            '<div class="tree-node"><div><strong>' + escapeHtml(category.name) + '</strong><span>' + escapeHtml(category.description) + '</span></div></div>' +
            (children.length ? '<ul>' + children.map(renderTreeNode).join('') + '</ul>' : '') +
            '</li>';
    }

    function payload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id' || value === '') {
                return;
            }
            data[key] = key === 'parent_id' ? parseInt(value, 10) : value;
        });
        return data;
    }

    function resetForm(root) {
        var form = qs('[data-product-category-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-product-category-form-title]', root).textContent = 'Nueva categoria';
        renderParentOptions(root);
    }

    function fillForm(root, category) {
        var form = qs('[data-product-category-form]', root);
        form.elements.id.value = category.id;
        form.elements.name.value = category.name || '';
        form.elements.parent_id.value = category.parent_id || '';
        form.elements.description.value = category.description || '';
        form.elements.status.value = category.status || 'active';
        qs('[data-product-category-form-title]', root).textContent = 'Editar categoria #' + category.id;
        renderParentOptions(root);
        form.elements.parent_id.value = category.parent_id || '';
    }

    function saveCategory(root, form) {
        clearMessage(root);
        var id = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path = id ? '/admin/product-categories/' + encodeURIComponent(id) : '/admin/product-categories';

        return window.CCApi.request(endpoint(path), {
            method: method,
            body: payload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Categoria actualizada.' : 'Categoria creada.');
            resetForm(root);
            return Promise.all([fetchCategories(root, state.page), fetchTree(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteCategory(root, id) {
        if (!window.confirm('Eliminar esta categoria?')) {
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/product-categories/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Categoria eliminada.');
            return Promise.all([fetchCategories(root, state.page), fetchTree(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function restoreCategory(root, id) {
        if (!id) {
            showMessage(root, 'danger', 'Indica el ID de la categoria a restaurar.');
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/product-categories/' + encodeURIComponent(id) + '/restore'), {
            method: 'PATCH',
        }).then(function () {
            showMessage(root, 'success', 'Categoria restaurada.');
            var input = qs('[data-product-category-restore-id]', root);
            if (input) {
                input.value = '';
            }
            return Promise.all([fetchCategories(root, state.page), fetchTree(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        qs('[data-product-categories-refresh]', root).addEventListener('click', function () {
            fetchCategories(root, 1);
        });
        qs('[data-product-categories-tree-refresh]', root).addEventListener('click', function () {
            fetchTree(root);
        });
        qs('[data-product-categories-prev]', root).addEventListener('click', function () {
            fetchCategories(root, Math.max(1, state.page - 1));
        });
        qs('[data-product-categories-next]', root).addEventListener('click', function () {
            fetchCategories(root, Math.min(state.lastPage, state.page + 1));
        });
        qs('[data-product-category-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-product-category-restore-submit]', root).addEventListener('click', function () {
            restoreCategory(root, qs('[data-product-category-restore-id]', root).value);
        });
        qs('[data-product-category-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            saveCategory(root, event.currentTarget);
        });
        qs('[data-product-categories-body]', root).addEventListener('click', function (event) {
            var edit = event.target.closest('[data-product-category-edit]');
            var remove = event.target.closest('[data-product-category-delete]');
            var restore = event.target.closest('[data-product-category-restore]');

            if (edit) {
                var id = parseInt(edit.getAttribute('data-product-category-edit'), 10);
                var category = state.categories.find(function (item) {
                    return item.id === id;
                });
                if (category) {
                    fillForm(root, category);
                }
            }
            if (remove) {
                deleteCategory(root, remove.getAttribute('data-product-category-delete'));
            }
            if (restore) {
                restoreCategory(root, restore.getAttribute('data-product-category-restore'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-product-categories]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        resetForm(root);
        fetchCategories(root, 1);
        fetchTree(root);
    });
})(window, document);
