(function (window, document) {
    'use strict';

    var state = {
        ingredients: [],
        categories: [],
        units: [],
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
        var alert = qs('[data-ingredients-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-ingredients-message]', root);
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
            var key = entry[0];
            var value = entry[1];
            if (key === '_token' || key === 'id') {
                return;
            }
            if (key === 'category_id' || key === 'base_unit_id') {
                data[key] = value === '' ? null : parseInt(value, 10);
                return;
            }
            if (value !== '') {
                data[key] = value;
            }
        });

        data.is_generic = !!form.elements.is_generic.checked;
        data.is_preparation = !!form.elements.is_preparation.checked;
        data.is_supplement = !!form.elements.is_supplement.checked;

        return data;
    }

    function fetchLookups(root) {
        return Promise.all([
            window.CCApi.request(endpoint('/admin/ingredient-categories?per_page=100&status=active&sort=name')),
            window.CCApi.request(endpoint('/units?per_page=100&sort=name')),
        ]).then(function (responses) {
            state.categories = responses[0].data || [];
            state.units = responses[1].data || [];
            renderLookups(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderLookups(root) {
        var categorySelects = [
            qs('[data-ingredients-category]', root),
            qs('[data-ingredient-category-select]', root),
        ];
        var unitSelect = qs('[data-ingredient-unit-select]', root);

        categorySelects.forEach(function (select, index) {
            if (!select) {
                return;
            }
            var first = index === 0 ? 'Todas las categorias' : 'Sin categoria';
            var current = select.value;
            select.innerHTML = '<option value="">' + first + '</option>' + state.categories.map(function (category) {
                return '<option value="' + category.id + '">' + escapeHtml(category.name) + '</option>';
            }).join('');
            select.value = current;
        });

        if (unitSelect) {
            var unitCurrent = unitSelect.value;
            unitSelect.innerHTML = '<option value="">Sin unidad base</option>' + state.units.map(function (unit) {
                var symbol = unit.symbol ? ' (' + unit.symbol + ')' : '';
                return '<option value="' + unit.id + '">' + escapeHtml(unit.name + symbol) + '</option>';
            }).join('');
            unitSelect.value = unitCurrent;
        }
    }

    function fetchIngredients(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-ingredients-search]', root);
        var category = qs('[data-ingredients-category]', root);
        var status = qs('[data-ingredients-status]', root);
        var kind = qs('[data-ingredients-kind]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'name');
        params.set('order', 'asc');

        if (search && search.value) {
            params.set('search', search.value);
        }
        if (category && category.value) {
            params.set('category_id', category.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }
        if (kind && kind.value) {
            params.set(kind.value, '1');
        }

        return window.CCApi.request(endpoint('/admin/ingredients?' + params.toString()))
            .then(function (response) {
                state.ingredients = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderIngredients(root, response.meta || {});
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function renderIngredients(root, meta) {
        var body = qs('[data-ingredients-body]', root);
        var counter = qs('[data-ingredients-count]', root);
        var page = qs('[data-ingredients-page]', root);

        if (counter) {
            counter.textContent = (meta.total || state.ingredients.length) + ' ingredientes';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }

        if (!state.ingredients.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay ingredientes cargados.</td></tr>';
            return;
        }

        body.innerHTML = state.ingredients.map(function (ingredient) {
            var flags = [];
            if (ingredient.is_generic) {
                flags.push('generico');
            }
            if (ingredient.is_preparation) {
                flags.push('preparacion');
            }
            if (ingredient.is_supplement) {
                flags.push('suplemento');
            }
            var deleted = ingredient.deleted_at ? '<span class="chip danger">eliminado</span>' : '';
            var action = ingredient.deleted_at
                ? '<button type="button" class="btn-ghost btn-sm" data-ingredient-restore="' + ingredient.id + '">Restaurar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-ingredient-delete="' + ingredient.id + '">Eliminar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(ingredient.name) + '</strong><br><span class="muted">' + escapeHtml(ingredient.normalized_name) + '</span><br><span class="muted">' + escapeHtml(ingredient.description) + '</span></td>' +
                '<td>' + escapeHtml(ingredient.category ? ingredient.category.name : '-') + '</td>' +
                '<td>' + escapeHtml(ingredient.base_unit ? ingredient.base_unit.name : '-') + '</td>' +
                '<td>' + (flags.length ? flags.map(function (flag) { return '<span class="chip">' + escapeHtml(flag) + '</span>'; }).join(' ') : '-') + '</td>' +
                '<td>' + escapeHtml(ingredient.status) + ' ' + deleted + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-ingredient-edit="' + ingredient.id + '">Editar</button> <button type="button" class="btn-ghost btn-sm" data-ingredient-view="' + ingredient.id + '">Ver detalle</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function resetForm(root) {
        var form = qs('[data-ingredient-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-ingredient-form-title]', root).textContent = 'Nuevo ingrediente';
    }

    function fillForm(root, ingredient) {
        var form = qs('[data-ingredient-form]', root);
        form.elements.id.value = ingredient.id;
        form.elements.name.value = ingredient.name || '';
        form.elements.description.value = ingredient.description || '';
        form.elements.category_id.value = ingredient.category_id || '';
        form.elements.base_unit_id.value = ingredient.base_unit_id || '';
        form.elements.is_generic.checked = !!ingredient.is_generic;
        form.elements.is_preparation.checked = !!ingredient.is_preparation;
        form.elements.is_supplement.checked = !!ingredient.is_supplement;
        form.elements.status.value = ingredient.status || 'active';
        qs('[data-ingredient-form-title]', root).textContent = 'Editar ingrediente';
    }

    function fetchPublicIngredients(root) {
        var params = new URLSearchParams();
        var search = qs('[data-ingredient-public-search]', root);
        params.set('per_page', '10');
        params.set('sort', 'name');
        if (search && search.value) {
            params.set('search', search.value);
        }

        return window.CCApi.request(endpoint('/ingredients?' + params.toString()))
            .then(function (response) {
                renderPublicResults(root, response.data || []);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function renderPublicResults(root, items) {
        var target = qs('[data-ingredient-public-results]', root);
        if (!items.length) {
            target.innerHTML = '<span class="muted">No se encontraron ingredientes activos.</span>';
            return;
        }

        target.innerHTML = '<div class="chips">' + items.map(function (ingredient) {
            return '<button type="button" class="btn-ghost btn-sm" data-ingredient-public-view="' + ingredient.id + '">' + escapeHtml(ingredient.name) + '</button>';
        }).join('') + '</div>';
    }

    function loadPublicDetail(root, id) {
        return Promise.all([
            window.CCApi.request(endpoint('/ingredients/' + id)),
            window.CCApi.request(endpoint('/ingredients/' + id + '/nutrition')),
            window.CCApi.request(endpoint('/ingredients/' + id + '/equivalences')),
        ]).then(function (responses) {
            renderDetail(root, responses[0].data);
            renderNutrition(root, responses[1].data || []);
            renderEquivalences(root, responses[2].data || []);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderDetail(root, ingredient) {
        var target = qs('[data-ingredient-detail]', root);
        target.innerHTML = '<div class="line"><span class="muted">Nombre</span><strong>' + escapeHtml(ingredient.name) + '</strong></div>' +
            '<div class="line"><span class="muted">Categoria</span><strong>' + escapeHtml(ingredient.category ? ingredient.category.name : '-') + '</strong></div>' +
            '<div class="line"><span class="muted">Unidad base</span><strong>' + escapeHtml(ingredient.base_unit ? ingredient.base_unit.name : '-') + '</strong></div>' +
            '<div class="line"><span class="muted">Descripcion</span><strong>' + escapeHtml(ingredient.description) + '</strong></div>';
    }

    function renderNutrition(root, items) {
        var body = qs('[data-ingredient-nutrition]', root);
        if (!items.length) {
            body.innerHTML = '<tr><td colspan="3" class="muted">Sin datos nutricionales cargados.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            var nutrient = item.nutrient || {};
            var unit = nutrient.unit && nutrient.unit.symbol ? ' ' + nutrient.unit.symbol : '';
            return '<tr>' +
                '<td>' + escapeHtml(nutrient.name || item.nutrient_id) + '</td>' +
                '<td>' + escapeHtml(item.amount_per_100g) + escapeHtml(unit) + '</td>' +
                '<td>' + escapeHtml(item.source) + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderEquivalences(root, items) {
        var target = qs('[data-ingredient-equivalences]', root);
        if (!items.length) {
            target.innerHTML = '<span class="muted">No hay equivalencias activas.</span>';
            return;
        }

        target.innerHTML = items.map(function (item) {
            var targetIngredient = item.target_ingredient || {};
            return '<div class="line"><span>' + escapeHtml(targetIngredient.name || '-') + '<br><span class="muted">' + escapeHtml(item.reason) + '</span></span><strong>' + escapeHtml(item.equivalence_type) + ' x ' + escapeHtml(item.conversion_factor) + '</strong></div>';
        }).join('');
    }

    function bind(root) {
        var form = qs('[data-ingredient-form]', root);

        qs('[data-ingredients-refresh]', root).addEventListener('click', function () {
            fetchIngredients(root, 1);
        });
        qs('[data-ingredients-search]', root).addEventListener('input', debounce(function () {
            fetchIngredients(root, 1);
        }, 300));
        qs('[data-ingredients-category]', root).addEventListener('change', function () {
            fetchIngredients(root, 1);
        });
        qs('[data-ingredients-status]', root).addEventListener('change', function () {
            fetchIngredients(root, 1);
        });
        qs('[data-ingredients-kind]', root).addEventListener('change', function () {
            fetchIngredients(root, 1);
        });
        qs('[data-ingredients-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                fetchIngredients(root, state.page - 1);
            }
        });
        qs('[data-ingredients-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                fetchIngredients(root, state.page + 1);
            }
        });
        qs('[data-ingredient-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-ingredient-public-refresh]', root).addEventListener('click', function () {
            fetchPublicIngredients(root);
        });
        qs('[data-ingredient-public-search]', root).addEventListener('input', debounce(function () {
            fetchPublicIngredients(root);
        }, 300));
        qs('[data-ingredient-restore-submit]', root).addEventListener('click', function () {
            var input = qs('[data-ingredient-restore-id]', root);
            var id = input ? input.value : '';
            if (!id) {
                showMessage(root, 'danger', 'Ingresá el ID de un ingrediente eliminado.');
                return;
            }
            window.CCApi.request(endpoint('/admin/ingredients/' + id + '/restore'), { method: 'PATCH' })
                .then(function () {
                    input.value = '';
                    showMessage(root, 'success', 'Ingrediente restaurado correctamente.');
                    return fetchIngredients(root, state.page);
                })
                .catch(function (error) { handleError(root, error); });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);
            var id = form.elements.id.value;
            var method = id ? 'PATCH' : 'POST';
            var path = '/admin/ingredients' + (id ? '/' + id : '');

            window.CCApi.request(endpoint(path), { method: method, body: formData(form) })
                .then(function () {
                    resetForm(root);
                    showMessage(root, 'success', id ? 'Ingrediente actualizado correctamente.' : 'Ingrediente creado correctamente.');
                    return fetchIngredients(root, state.page);
                })
                .catch(function (error) { handleError(root, error); });
        });

        root.addEventListener('click', function (event) {
            var editId = event.target.getAttribute('data-ingredient-edit');
            var deleteId = event.target.getAttribute('data-ingredient-delete');
            var restoreId = event.target.getAttribute('data-ingredient-restore');
            var viewId = event.target.getAttribute('data-ingredient-view') || event.target.getAttribute('data-ingredient-public-view');

            if (editId) {
                var ingredient = state.ingredients.find(function (item) {
                    return String(item.id) === String(editId);
                });
                if (ingredient) {
                    fillForm(root, ingredient);
                }
            }

            if (deleteId) {
                window.CCApi.request(endpoint('/admin/ingredients/' + deleteId), { method: 'DELETE' })
                    .then(function () {
                        showMessage(root, 'success', 'Ingrediente eliminado correctamente.');
                        return fetchIngredients(root, state.page);
                    })
                    .catch(function (error) { handleError(root, error); });
            }

            if (restoreId) {
                window.CCApi.request(endpoint('/admin/ingredients/' + restoreId + '/restore'), { method: 'PATCH' })
                    .then(function () {
                        showMessage(root, 'success', 'Ingrediente restaurado correctamente.');
                        return fetchIngredients(root, state.page);
                    })
                    .catch(function (error) { handleError(root, error); });
            }

            if (viewId) {
                loadPublicDetail(root, viewId);
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
        var root = qs('[data-admin-ingredients]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        fetchLookups(root).then(function () {
            return fetchIngredients(root, 1);
        }).then(function () {
            return fetchPublicIngredients(root);
        });
    });
})(window, document);
