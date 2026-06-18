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

    function showMessage(root, type, message) {
        var alert = qs('[data-ingredient-categories-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-ingredient-categories-message]', root);
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

    function normalizeCode(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^a-z0-9_-]/g, '');
    }

    function formData(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = entry[1];
            if (key === '_token' || key === 'id') {
                return;
            }
            if (value === '') {
                if (key === 'parent_id' || key === 'description') {
                    data[key] = null;
                }
                return;
            }
            if (key === 'code') {
                data[key] = normalizeCode(value);
                return;
            }
            if (key === 'parent_id' || key === 'sort_order') {
                data[key] = parseInt(value, 10);
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function endpoint(path) {
        return '/api/v1' + path;
    }

    function fetchCategories(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-ingredient-categories-search]', root);
        var status = qs('[data-ingredient-categories-status]', root);

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

        return window.CCApi.request(endpoint('/admin/ingredient-categories?' + params.toString()))
            .then(function (response) {
                state.categories = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderCategories(root, response.meta || {});
                renderParentOptions(root);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function fetchTree(root) {
        return window.CCApi.request(endpoint('/ingredient-categories'))
            .then(function (response) {
                state.tree = response.data || [];
                renderTree(root);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function categoryName(id) {
        var found = state.categories.find(function (category) {
            return String(category.id) === String(id);
        });
        return found ? found.name : null;
    }

    function renderCategories(root, meta) {
        var body = qs('[data-ingredient-categories-body]', root);
        var counter = qs('[data-ingredient-categories-count]', root);
        var page = qs('[data-ingredient-categories-page]', root);

        if (counter) {
            counter.textContent = (meta.total || state.categories.length) + ' categorias';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }

        if (!state.categories.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay categorias cargadas.</td></tr>';
            return;
        }

        body.innerHTML = state.categories.map(function (category) {
            var deleted = category.deleted_at ? '<span class="chip danger">eliminada</span>' : '';
            var action = category.deleted_at
                ? '<button type="button" class="btn-ghost btn-sm" data-ingredient-category-restore="' + category.id + '">Restaurar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-ingredient-category-delete="' + category.id + '">Eliminar</button>';
            var parent = category.parent_id ? (categoryName(category.parent_id) || ('#' + category.parent_id)) : 'Raiz';
            var usage = (category.children_count || 0) + ' hijas / ' + (category.ingredients_count || 0) + ' ingredientes';

            return '<tr>' +
                '<td><strong>' + escapeHtml(category.name) + '</strong><br><span class="muted">' + escapeHtml(category.code) + '</span><br><span class="muted">' + escapeHtml(category.description) + '</span></td>' +
                '<td>' + escapeHtml(parent) + '</td>' +
                '<td>' + escapeHtml(category.sort_order) + '</td>' +
                '<td>' + escapeHtml(category.status) + ' ' + deleted + '</td>' +
                '<td>' + escapeHtml(usage) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-ingredient-category-edit="' + category.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderParentOptions(root) {
        var select = qs('[data-ingredient-category-parent]', root);
        var currentId = qs('[data-ingredient-category-form]', root).elements.id.value;
        var currentValue = select.value;

        select.innerHTML = '<option value="">Sin categoria padre</option>' + state.categories
            .filter(function (category) {
                return !category.deleted_at && category.status === 'active' && String(category.id) !== String(currentId);
            })
            .map(function (category) {
                return '<option value="' + category.id + '">' + escapeHtml(category.name) + '</option>';
            }).join('');

        if (currentValue) {
            select.value = currentValue;
        }
    }

    function renderTree(root) {
        var target = qs('[data-ingredient-categories-tree]', root);
        if (!state.tree.length) {
            target.innerHTML = '<span class="muted">No hay categorias activas para mostrar.</span>';
            return;
        }
        target.innerHTML = '<ul class="tree-panel">' + state.tree.map(renderTreeNode).join('') + '</ul>';
    }

    function renderTreeNode(node) {
        var children = node.children && node.children.length
            ? '<ul>' + node.children.map(renderTreeNode).join('') + '</ul>'
            : '';

        return '<li>' +
            '<div class="tree-node"><div><strong>' + escapeHtml(node.name) + '</strong><span>' + escapeHtml(node.code) + '</span></div><span class="chip">' + ((node.children || []).length) + ' hijas</span></div>' +
            children +
            '</li>';
    }

    function fillForm(root, category) {
        var form = qs('[data-ingredient-category-form]', root);
        qs('[data-ingredient-category-form-title]', root).textContent = 'Editar categoria';
        form.elements.id.value = category.id;
        form.elements.code.value = category.code || '';
        form.elements.name.value = category.name || '';
        form.elements.description.value = category.description || '';
        form.elements.parent_id.value = category.parent_id || '';
        form.elements.sort_order.value = category.sort_order === null || category.sort_order === undefined ? '' : category.sort_order;
        form.elements.status.value = category.status || 'active';
        renderParentOptions(root);
        form.elements.parent_id.value = category.parent_id || '';
    }

    function resetForm(root) {
        var form = qs('[data-ingredient-category-form]', root);
        form.reset();
        form.elements.id.value = '';
        qs('[data-ingredient-category-form-title]', root).textContent = 'Nueva categoria';
        renderParentOptions(root);
    }

    function bind(root) {
        var form = qs('[data-ingredient-category-form]', root);

        qs('[data-ingredient-categories-refresh]', root).addEventListener('click', function () {
            fetchCategories(root, 1);
        });
        qs('[data-ingredient-categories-tree-refresh]', root).addEventListener('click', function () {
            fetchTree(root);
        });
        qs('[data-ingredient-categories-search]', root).addEventListener('input', debounce(function () {
            fetchCategories(root, 1);
        }, 300));
        qs('[data-ingredient-categories-status]', root).addEventListener('change', function () {
            fetchCategories(root, 1);
        });
        qs('[data-ingredient-categories-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                fetchCategories(root, state.page - 1);
            }
        });
        qs('[data-ingredient-categories-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                fetchCategories(root, state.page + 1);
            }
        });
        qs('[data-ingredient-category-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-ingredient-category-restore-submit]', root).addEventListener('click', function () {
            var input = qs('[data-ingredient-category-restore-id]', root);
            var id = input ? input.value : '';
            if (!id) {
                showMessage(root, 'danger', 'Ingresá el ID de una categoria eliminada.');
                return;
            }

            window.CCApi.request(endpoint('/admin/ingredient-categories/' + id + '/restore'), { method: 'PATCH' })
                .then(function () {
                    if (input) {
                        input.value = '';
                    }
                    showMessage(root, 'success', 'Categoria restaurada correctamente.');
                    return fetchCategories(root, state.page);
                })
                .then(function () { return fetchTree(root); })
                .catch(function (error) { handleError(root, error); });
        });

        form.elements.code.addEventListener('blur', function () {
            form.elements.code.value = normalizeCode(form.elements.code.value);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);

            var id = form.elements.id.value;
            var method = id ? 'PATCH' : 'POST';
            var path = '/admin/ingredient-categories' + (id ? '/' + id : '');

            window.CCApi.request(endpoint(path), { method: method, body: formData(form) })
                .then(function () {
                    resetForm(root);
                    showMessage(root, 'success', id ? 'Categoria actualizada correctamente.' : 'Categoria creada correctamente.');
                    return fetchCategories(root, state.page);
                })
                .then(function () {
                    return fetchTree(root);
                })
                .catch(function (error) {
                    handleError(root, error);
                });
        });

        root.addEventListener('click', function (event) {
            var editId = event.target.getAttribute('data-ingredient-category-edit');
            var deleteId = event.target.getAttribute('data-ingredient-category-delete');
            var restoreId = event.target.getAttribute('data-ingredient-category-restore');

            if (editId) {
                var category = state.categories.find(function (item) {
                    return String(item.id) === String(editId);
                });
                if (category) {
                    fillForm(root, category);
                }
            }

            if (deleteId) {
                window.CCApi.request(endpoint('/admin/ingredient-categories/' + deleteId), { method: 'DELETE' })
                    .then(function () {
                        showMessage(root, 'success', 'Categoria eliminada correctamente.');
                        return fetchCategories(root, state.page);
                    })
                    .then(function () { return fetchTree(root); })
                    .catch(function (error) { handleError(root, error); });
            }

            if (restoreId) {
                window.CCApi.request(endpoint('/admin/ingredient-categories/' + restoreId + '/restore'), { method: 'PATCH' })
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
        var root = qs('[data-admin-ingredient-categories]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        fetchCategories(root, 1).then(function () {
            return fetchTree(root);
        });
    });
})(window, document);
