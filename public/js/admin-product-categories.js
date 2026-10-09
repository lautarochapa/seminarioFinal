(function (window, document) {
    'use strict';

    function label(value, group) {
        return window.CCAdminLabels ? window.CCAdminLabels.get(value, group) : text(value);
    }

    var state = {
        categories: [],
        tree: [],
        flatTree: [],
        meta: {},
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

    function categoryNameById(id, parent) {
        if (!id) {
            return '-';
        }
        var treeCategory = state.flatTree.find(function (item) {
            return String(item.id) === String(id);
        });
        if (treeCategory) {
            return treeCategory.path;
        }
        if (parent && String(parent.id) === String(id) && parent.name) {
            return parent.name;
        }
        var category = state.categories.find(function (item) {
            return String(item.id) === String(id);
        });
        return category ? category.name : '#' + id;
    }

    function option(label, value, disabled) {
        return '<option value="' + escapeHtml(value) + '"' + (disabled ? ' disabled' : '') + '>' + escapeHtml(label) + '</option>';
    }

    function flattenTree(nodes, names, ancestorIds) {
        var rows = [];
        nodes.forEach(function (category) {
            var id = String(category.id);
            if (ancestorIds.indexOf(id) !== -1) {
                return;
            }
            var path = names.concat([category.name]);
            rows.push({ id: category.id, path: path.join(' > '), ancestorIds: ancestorIds });
            rows = rows.concat(flattenTree(category.children || [], path, ancestorIds.concat([id])));
        });
        return rows;
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
                state.meta = response.meta || {};
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderCategories(root, state.meta);
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
                '<td>' + escapeHtml(categoryNameById(category.parent_id, category.parent)) + '</td>' +
                '<td>' + escapeHtml(category.description) + '</td>' +
                '<td>' + escapeHtml(category.children_count) + '</td>' +
                '<td>' + escapeHtml(category.products_count) + '</td>' +
                '<td>' + escapeHtml(label(category.status)) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-product-category-edit="' + category.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderParentOptions(root, selectedId) {
        var select = qs('[data-product-category-parent]', root);
        var form = qs('[data-product-category-form]', root);
        var current = selectedId === undefined ? select.value : String(selectedId || '');
        var editingId = String(form.elements.id.value);
        var activeCategories = state.flatTree.filter(function (category) {
            return String(category.id) !== editingId && category.ancestorIds.indexOf(editingId) === -1;
        });

        select.innerHTML = '<option value="">Categoria raiz</option>' + activeCategories.map(function (category) {
            return option(category.path, category.id);
        }).join('');
        if (current && !activeCategories.some(function (category) { return String(category.id) === current; })) {
            var editingCategory = state.categories.find(function (category) { return String(category.id) === editingId; });
            select.insertAdjacentHTML('beforeend', option(categoryNameById(current, editingCategory && editingCategory.parent) + ' (no disponible)', current, true));
        }
        select.value = current;
    }

    function fetchTree(root) {
        return window.CCApi.request(endpoint('/product-categories'))
            .then(function (response) {
                state.tree = response.data || [];
                state.flatTree = flattenTree(state.tree, [], []);
                renderTree(root);
                renderParentOptions(root);
                renderCategories(root, state.meta);
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
            if (key === 'parent_id') {
                data[key] = value === '' ? null : parseInt(value, 10);
                return;
            }
            if (key === 'id' || value === '') {
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function resetForm(root) {
        var form = qs('[data-product-category-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-product-category-form-title]', root).textContent = 'Nueva categoria';
        renderParentOptions(root, '');
    }

    function fillForm(root, category) {
        var form = qs('[data-product-category-form]', root);
        form.elements.id.value = category.id;
        form.elements.name.value = category.name || '';
        form.elements.description.value = category.description || '';
        form.elements.status.value = category.status || 'active';
        qs('[data-product-category-form-title]', root).textContent = 'Editar categoria #' + category.id;
        renderParentOptions(root, category.parent_id);
        if (window.CCUI) { window.CCUI.reveal(form); }
    }

    function saveCategory(root, form) {
        clearMessage(root);
        var parentOption = form.elements.parent_id.selectedOptions[0];
        if (!parentOption || parentOption.disabled) {
            showMessage(root, 'danger', 'La categoria padre seleccionada no esta disponible. Elegi otra categoria o Categoria raiz.');
            return Promise.resolve();
        }
        var id = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path = id ? '/admin/product-categories/' + encodeURIComponent(id) : '/admin/product-categories';

        return window.CCApi.request(endpoint(path), {
            method: method,
            body: payload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Categoria actualizada.' : 'Categoria creada.');
            if (window.CCUI) { window.CCUI.close(form); }
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
            Promise.all([fetchCategories(root, 1), fetchTree(root)]);
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

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                resetForm(root);
                var form = qs('[data-product-category-form]', root);
                if (form) {
                    if (window.CCUI) { window.CCUI.reveal(form); }
                    else { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                    var first = form.querySelector('input:not([type=hidden]),select,textarea');
                    if (first) { first.focus(); }
                }
            });
        }
        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showMessage(root, 'info', 'Accion no disponible en esta version.');
            });
        }
    });
})(window, document);
