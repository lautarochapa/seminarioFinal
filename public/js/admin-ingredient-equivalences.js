(function (window, document) {
    'use strict';

    var state = {
        ingredients: [],
        equivalences: [],
        publicEquivalences: [],
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

    function ingredientName(ingredient) {
        return ingredient ? ingredient.name : '-';
    }

    function ingredientId(ingredient) {
        return ingredient && ingredient.id ? ingredient.id : '';
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-equivalences-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-equivalences-message]', root);
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

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function fetchIngredients(root) {
        return window.CCApi.request(endpoint('/admin/ingredients?per_page=100&status=active&sort=name&order=asc'))
            .then(function (response) {
                state.ingredients = response.data || [];
                renderIngredientSelects(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderIngredientSelects(root) {
        var selects = [
            [qs('[data-equivalences-source]', root), 'Ingrediente origen'],
            [qs('[data-equivalences-target]', root), 'Ingrediente destino'],
            [qs('[data-equivalence-source-select]', root), 'Ingrediente origen'],
            [qs('[data-equivalence-target-select]', root), 'Ingrediente destino'],
            [qs('[data-equivalence-public-ingredient]', root), 'Seleccionar ingrediente'],
        ];

        selects.forEach(function (pair) {
            var select = pair[0];
            var label = pair[1];
            if (!select) {
                return;
            }
            var current = select.value;
            select.innerHTML = '<option value="">' + label + '</option>' + state.ingredients.map(function (ingredient) {
                return option(ingredient.name, ingredient.id);
            }).join('');
            select.value = current;
        });
    }

    function fetchEquivalences(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-equivalences-search]', root);
        var source = qs('[data-equivalences-source]', root);
        var target = qs('[data-equivalences-target]', root);
        var type = qs('[data-equivalences-type]', root);
        var status = qs('[data-equivalences-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'id');
        params.set('order', 'asc');

        if (search && search.value) {
            params.set('search', search.value);
        }
        if (source && source.value) {
            params.set('source_ingredient_id', source.value);
        }
        if (target && target.value) {
            params.set('target_ingredient_id', target.value);
        }
        if (type && type.value) {
            params.set('equivalence_type', type.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/ingredient-equivalences?' + params.toString()))
            .then(function (response) {
                state.equivalences = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderEquivalences(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderEquivalences(root, meta) {
        var body = qs('[data-equivalences-body]', root);
        var count = qs('[data-equivalences-count]', root);
        var page = qs('[data-equivalences-page]', root);

        if (count) {
            count.textContent = (meta.total || state.equivalences.length) + ' equivalencias';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }
        if (!state.equivalences.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay equivalencias cargadas.</td></tr>';
            return;
        }

        body.innerHTML = state.equivalences.map(function (equivalence) {
            var action = equivalence.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-equivalence-delete="' + equivalence.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-equivalence-restore="' + equivalence.id + '">Restaurar</button>';

            return '<tr>' +
                '<td>' + escapeHtml(ingredientName(equivalence.source_ingredient)) + '</td>' +
                '<td>' + escapeHtml(ingredientName(equivalence.target_ingredient)) + '</td>' +
                '<td>' + escapeHtml(equivalence.equivalence_type) + '</td>' +
                '<td><strong>' + escapeHtml(equivalence.conversion_factor) + '</strong></td>' +
                '<td>' + escapeHtml(equivalence.reason) + '</td>' +
                '<td>' + escapeHtml(equivalence.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-equivalence-edit="' + equivalence.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function payload(form) {
        var data = {
            source_ingredient_id: parseInt(form.elements.source_ingredient_id.value, 10),
            target_ingredient_id: parseInt(form.elements.target_ingredient_id.value, 10),
            conversion_factor: Number(form.elements.conversion_factor.value),
            status: form.elements.status.value || 'active',
        };

        if (form.elements.equivalence_type.value !== '') {
            data.equivalence_type = form.elements.equivalence_type.value;
        } else {
            data.equivalence_type = null;
        }

        if (form.elements.reason.value !== '') {
            data.reason = form.elements.reason.value;
        } else {
            data.reason = null;
        }

        return data;
    }

    function resetForm(root) {
        var form = qs('[data-equivalence-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-equivalence-form-title]', root).textContent = 'Nueva equivalencia';
    }

    function fillForm(root, equivalence) {
        var form = qs('[data-equivalence-form]', root);
        form.elements.id.value = equivalence.id;
        form.elements.source_ingredient_id.value = ingredientId(equivalence.source_ingredient);
        form.elements.target_ingredient_id.value = ingredientId(equivalence.target_ingredient);
        form.elements.equivalence_type.value = equivalence.equivalence_type || '';
        form.elements.conversion_factor.value = equivalence.conversion_factor || '';
        form.elements.reason.value = equivalence.reason || '';
        form.elements.status.value = equivalence.status || 'active';
        qs('[data-equivalence-form-title]', root).textContent = 'Editar equivalencia #' + equivalence.id;
    }

    function save(root, form) {
        var id = form.elements.id.value;
        var path = id ? '/admin/ingredient-equivalences/' + id : '/admin/ingredient-equivalences';
        var method = id ? 'PATCH' : 'POST';

        clearMessage(root);
        window.CCApi.request(endpoint(path), {
            method: method,
            body: payload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Equivalencia actualizada.' : 'Equivalencia creada.');
            resetForm(root);
            fetchEquivalences(root);
            fetchPublicEquivalences(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function remove(root, id) {
        if (!window.confirm('Eliminar esta equivalencia?')) {
            return;
        }
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/ingredient-equivalences/' + id), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Equivalencia eliminada.');
                fetchEquivalences(root);
                fetchPublicEquivalences(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function restore(root, id) {
        if (!id) {
            showMessage(root, 'warning', 'Ingresa un ID de equivalencia.');
            return;
        }
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/ingredient-equivalences/' + id + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Equivalencia restaurada.');
                fetchEquivalences(root);
                fetchPublicEquivalences(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function fetchPublicEquivalences(root) {
        var select = qs('[data-equivalence-public-ingredient]', root);
        var results = qs('[data-equivalence-public-results]', root);
        var count = qs('[data-equivalence-public-count]', root);

        if (!select || !select.value) {
            state.publicEquivalences = [];
            if (count) {
                count.textContent = '0 opciones';
            }
            results.innerHTML = 'Selecciona un ingrediente para ver reemplazos activos.';
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/ingredients/' + select.value + '/equivalences'))
            .then(function (response) {
                state.publicEquivalences = response.data || [];
                renderPublicEquivalences(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderPublicEquivalences(root) {
        var results = qs('[data-equivalence-public-results]', root);
        var count = qs('[data-equivalence-public-count]', root);
        if (count) {
            count.textContent = state.publicEquivalences.length + ' opciones';
        }
        if (!state.publicEquivalences.length) {
            results.innerHTML = '<span class="muted">No hay sustituciones activas para este ingrediente.</span>';
            return;
        }

        results.innerHTML = '<div style="overflow:auto"><table class="admin-table">' +
            '<thead><tr><th>Reemplazo</th><th>Tipo</th><th>Factor</th><th>Motivo</th></tr></thead><tbody>' +
            state.publicEquivalences.map(function (equivalence) {
                return '<tr>' +
                    '<td>' + escapeHtml(ingredientName(equivalence.target_ingredient)) + '</td>' +
                    '<td>' + escapeHtml(equivalence.equivalence_type) + '</td>' +
                    '<td><strong>' + escapeHtml(equivalence.conversion_factor) + '</strong></td>' +
                    '<td>' + escapeHtml(equivalence.reason) + '</td>' +
                    '</tr>';
            }).join('') +
            '</tbody></table></div>';
    }

    function bind(root) {
        var form = qs('[data-equivalence-form]', root);

        ['[data-equivalences-search]', '[data-equivalences-type]'].forEach(function (selector) {
            qs(selector, root).addEventListener('input', function () {
                fetchEquivalences(root, 1);
            });
        });
        ['[data-equivalences-source]', '[data-equivalences-target]', '[data-equivalences-status]'].forEach(function (selector) {
            qs(selector, root).addEventListener('change', function () {
                fetchEquivalences(root, 1);
            });
        });

        qs('[data-equivalences-refresh]', root).addEventListener('click', function () {
            fetchEquivalences(root, 1);
        });
        qs('[data-equivalences-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                fetchEquivalences(root, state.page - 1);
            }
        });
        qs('[data-equivalences-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                fetchEquivalences(root, state.page + 1);
            }
        });
        qs('[data-equivalence-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-equivalence-restore-submit]', root).addEventListener('click', function () {
            restore(root, qs('[data-equivalence-restore-id]', root).value);
        });
        qs('[data-equivalence-public-refresh]', root).addEventListener('click', function () {
            fetchPublicEquivalences(root);
        });
        qs('[data-equivalence-public-ingredient]', root).addEventListener('change', function () {
            fetchPublicEquivalences(root);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            save(root, form);
        });

        root.addEventListener('click', function (event) {
            var target = event.target;
            var editId = target.getAttribute('data-equivalence-edit');
            var deleteId = target.getAttribute('data-equivalence-delete');
            var restoreId = target.getAttribute('data-equivalence-restore');

            if (editId) {
                var equivalence = state.equivalences.filter(function (item) {
                    return String(item.id) === String(editId);
                })[0];
                if (equivalence) {
                    fillForm(root, equivalence);
                }
            }
            if (deleteId) {
                remove(root, deleteId);
            }
            if (restoreId) {
                restore(root, restoreId);
            }
        });

        fetchIngredients(root).then(function () {
            fetchEquivalences(root, 1);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-ingredient-equivalences]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
    });
})(window, document);
