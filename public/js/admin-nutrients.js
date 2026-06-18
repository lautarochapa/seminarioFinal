(function (window, document) {
    'use strict';

    var state = {
        nutrients: [],
        units: [],
        ingredients: [],
        products: [],
        ingredientNutrients: [],
        productNutrients: [],
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
        var alert = qs('[data-nutrients-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-nutrients-message]', root);
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

    function option(label, value, selected) {
        return '<option value="' + escapeHtml(value) + '"' + (selected ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
    }

    function unitLabel(unit) {
        if (!unit) {
            return '-';
        }
        return unit.name + (unit.symbol ? ' (' + unit.symbol + ')' : '');
    }

    function nutrientLabel(nutrient) {
        if (!nutrient) {
            return '-';
        }
        var unit = nutrient.unit ? ' - ' + unitLabel(nutrient.unit) : '';
        return nutrient.name + unit;
    }

    function selectedId(selector, root) {
        var element = qs(selector, root);
        return element && element.value ? element.value : null;
    }

    function numericOrNull(value) {
        return value === '' || value === null || value === undefined ? null : Number(value);
    }

    function fetchLookups(root) {
        return Promise.all([
            window.CCApi.request(endpoint('/units?per_page=100&sort=name')),
            window.CCApi.request(endpoint('/admin/ingredients?per_page=100&status=active&sort=name&order=asc')),
            window.CCApi.request(endpoint('/admin/products?per_page=100&status=active&sort=name&order=asc')),
        ]).then(function (responses) {
            state.units = responses[0].data || [];
            state.ingredients = responses[1].data || [];
            state.products = responses[2].data || [];
            renderLookups(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderLookups(root) {
        var unitFilter = qs('[data-nutrients-unit]', root);
        var unitSelect = qs('[data-nutrient-unit-select]', root);
        var ingredientSelect = qs('[data-nutrient-ingredient-select]', root);
        var productSelect = qs('[data-nutrient-product-select]', root);

        if (unitFilter) {
            var currentUnitFilter = unitFilter.value;
            unitFilter.innerHTML = '<option value="">Todas las unidades</option>' + state.units.map(function (unit) {
                return option(unitLabel(unit), unit.id, false);
            }).join('');
            unitFilter.value = currentUnitFilter;
        }

        if (unitSelect) {
            var currentUnit = unitSelect.value;
            unitSelect.innerHTML = '<option value="">Unidad</option>' + state.units.map(function (unit) {
                return option(unitLabel(unit), unit.id, false);
            }).join('');
            unitSelect.value = currentUnit;
        }

        if (ingredientSelect) {
            var currentIngredient = ingredientSelect.value;
            ingredientSelect.innerHTML = '<option value="">Seleccionar ingrediente</option>' + state.ingredients.map(function (ingredient) {
                return option(ingredient.name, ingredient.id, false);
            }).join('');
            ingredientSelect.value = currentIngredient;
        }

        if (productSelect) {
            var currentProduct = productSelect.value;
            productSelect.innerHTML = '<option value="">Seleccionar producto</option>' + state.products.map(function (product) {
                return option(product.name, product.id, false);
            }).join('');
            productSelect.value = currentProduct;
        }
    }

    function renderNutrientSelects(root) {
        var selects = [
            qs('[data-ingredient-nutrient-select]', root),
            qs('[data-product-nutrient-select]', root),
        ];

        selects.forEach(function (select) {
            if (!select) {
                return;
            }
            var current = select.value;
            select.innerHTML = '<option value="">Nutriente</option>' + state.nutrients
                .filter(function (nutrient) { return nutrient.status === 'active'; })
                .map(function (nutrient) {
                    return option(nutrientLabel(nutrient), nutrient.id, false);
                }).join('');
            select.value = current;
        });
    }

    function fetchNutrients(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-nutrients-search]', root);
        var unit = qs('[data-nutrients-unit]', root);
        var status = qs('[data-nutrients-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'name');
        params.set('order', 'asc');

        if (search && search.value) {
            params.set('search', search.value);
        }
        if (unit && unit.value) {
            params.set('unit_id', unit.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/nutrients?' + params.toString()))
            .then(function (response) {
                state.nutrients = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderNutrients(root, response.meta || {});
                renderNutrientSelects(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderNutrients(root, meta) {
        var body = qs('[data-nutrients-body]', root);
        var counter = qs('[data-nutrients-count]', root);
        var page = qs('[data-nutrients-page]', root);

        if (counter) {
            counter.textContent = (meta.total || state.nutrients.length) + ' nutrientes';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }
        if (!body) {
            return;
        }
        if (!state.nutrients.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay nutrientes cargados.</td></tr>';
            return;
        }

        body.innerHTML = state.nutrients.map(function (nutrient) {
            var inactive = nutrient.status !== 'active';
            var action = inactive
                ? '<button type="button" class="btn-ghost btn-sm" data-nutrient-restore="' + nutrient.id + '">Restaurar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-nutrient-delete="' + nutrient.id + '">Eliminar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(nutrient.code) + '</strong></td>' +
                '<td>' + escapeHtml(nutrient.name) + '</td>' +
                '<td>' + escapeHtml(unitLabel(nutrient.unit)) + '</td>' +
                '<td>' + escapeHtml(nutrient.description) + '</td>' +
                '<td>' + escapeHtml(nutrient.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-nutrient-edit="' + nutrient.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function nutrientPayload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = entry[1];
            if (key === 'id' || value === '') {
                return;
            }
            data[key] = key === 'unit_id' ? parseInt(value, 10) : value;
        });
        return data;
    }

    function resetNutrientForm(root) {
        var form = qs('[data-nutrient-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-nutrient-form-title]', root).textContent = 'Nuevo nutriente';
    }

    function fillNutrientForm(root, nutrient) {
        var form = qs('[data-nutrient-form]', root);
        form.elements.id.value = nutrient.id;
        form.elements.code.value = nutrient.code || '';
        form.elements.name.value = nutrient.name || '';
        form.elements.unit_id.value = nutrient.unit_id || '';
        form.elements.description.value = nutrient.description || '';
        form.elements.status.value = nutrient.status || 'active';
        qs('[data-nutrient-form-title]', root).textContent = 'Editar nutriente #' + nutrient.id;
    }

    function saveNutrient(root, form) {
        var id = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path = id ? '/admin/nutrients/' + id : '/admin/nutrients';

        clearMessage(root);
        return window.CCApi.request(endpoint(path), {
            method: method,
            body: nutrientPayload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Nutriente actualizado.' : 'Nutriente creado.');
            resetNutrientForm(root);
            return fetchNutrients(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteNutrient(root, id) {
        if (!window.confirm('Eliminar este nutriente?')) {
            return;
        }
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/nutrients/' + id), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Nutriente eliminado.');
                fetchNutrients(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function restoreNutrient(root, id) {
        if (!id) {
            showMessage(root, 'warning', 'Ingresa un ID para restaurar.');
            return;
        }
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/nutrients/' + id + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Nutriente restaurado.');
                fetchNutrients(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function relationRows(items, editable) {
        if (!items.length) {
            return '<tr><td colspan="' + (editable ? '4' : '4') + '" class="muted">Sin valores nutricionales cargados.</td></tr>';
        }

        return items.map(function (item) {
            var nutrient = item.nutrient || {};
            if (editable) {
                return '<tr>' +
                    '<td>' + escapeHtml(nutrientLabel(nutrient)) + '</td>' +
                    '<td>' + escapeHtml(item.amount_per_100g) + '</td>' +
                    '<td>' + escapeHtml(item.source) + '</td>' +
                    '<td><button type="button" class="btn-main btn-sm" data-ingredient-nutrient-edit="' + item.nutrient_id + '">Editar</button></td>' +
                    '</tr>';
            }

            return '<tr>' +
                '<td>' + escapeHtml(nutrientLabel(nutrient)) + '</td>' +
                '<td>' + escapeHtml(item.amount_per_100g) + '</td>' +
                '<td>' + escapeHtml(item.amount_per_serving) + ' / ' + escapeHtml(item.serving_size) + '</td>' +
                '<td>' + escapeHtml(item.source) + '</td>' +
                '</tr>';
        }).join('');
    }

    function fetchIngredientNutrients(root) {
        var ingredientId = selectedId('[data-nutrient-ingredient-select]', root);
        var body = qs('[data-ingredient-nutrients-body]', root);
        if (!ingredientId) {
            state.ingredientNutrients = [];
            body.innerHTML = '<tr><td colspan="4" class="muted">Selecciona un ingrediente.</td></tr>';
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/ingredients/' + ingredientId + '/nutrients'))
            .then(function (response) {
                state.ingredientNutrients = response.data || [];
                body.innerHTML = relationRows(state.ingredientNutrients, true);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function ingredientNutrientPayload(form, includeNutrient) {
        var data = {
            amount_per_100g: Number(form.elements.amount_per_100g.value),
            status: form.elements.status.value || 'active',
        };
        if (includeNutrient) {
            data.nutrient_id = parseInt(form.elements.nutrient_id.value, 10);
        }
        if (form.elements.source.value !== '') {
            data.source = form.elements.source.value;
        }
        return data;
    }

    function saveIngredientNutrient(root, form) {
        var ingredientId = selectedId('[data-nutrient-ingredient-select]', root);
        var editNutrientId = form.dataset.editNutrientId;
        if (!ingredientId) {
            showMessage(root, 'warning', 'Selecciona un ingrediente.');
            return;
        }

        var method = editNutrientId ? 'PATCH' : 'POST';
        var path = editNutrientId
            ? '/admin/ingredients/' + ingredientId + '/nutrients/' + editNutrientId
            : '/admin/ingredients/' + ingredientId + '/nutrients';

        clearMessage(root);
        window.CCApi.request(endpoint(path), {
            method: method,
            body: ingredientNutrientPayload(form, !editNutrientId),
        }).then(function () {
            showMessage(root, 'success', editNutrientId ? 'Valor nutricional actualizado.' : 'Valor nutricional agregado.');
            form.reset();
            form.dataset.editNutrientId = '';
            form.elements.status.value = 'active';
            fetchIngredientNutrients(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function fillIngredientRelationForm(root, nutrientId) {
        var relation = state.ingredientNutrients.filter(function (item) {
            return String(item.nutrient_id) === String(nutrientId);
        })[0];
        var form = qs('[data-ingredient-nutrient-form]', root);
        if (!relation) {
            return;
        }
        form.dataset.editNutrientId = relation.nutrient_id;
        form.elements.nutrient_id.value = relation.nutrient_id;
        form.elements.amount_per_100g.value = relation.amount_per_100g || '';
        form.elements.source.value = relation.source || '';
        form.elements.status.value = relation.status || 'active';
    }

    function fetchProductNutrients(root) {
        var productId = selectedId('[data-nutrient-product-select]', root);
        var body = qs('[data-product-nutrients-body]', root);
        if (!productId) {
            state.productNutrients = [];
            body.innerHTML = '<tr><td colspan="4" class="muted">Selecciona un producto.</td></tr>';
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/products/' + productId + '/nutrients'))
            .then(function (response) {
                state.productNutrients = response.data || [];
                body.innerHTML = relationRows(state.productNutrients, false);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function productNutrientPayload(form) {
        var data = {
            nutrient_id: parseInt(form.elements.nutrient_id.value, 10),
            amount_per_100g: numericOrNull(form.elements.amount_per_100g.value),
            amount_per_serving: numericOrNull(form.elements.amount_per_serving.value),
            serving_size: numericOrNull(form.elements.serving_size.value),
            status: form.elements.status.value || 'active',
        };
        if (form.elements.source.value !== '') {
            data.source = form.elements.source.value;
        }
        Object.keys(data).forEach(function (key) {
            if (data[key] === null) {
                delete data[key];
            }
        });
        return data;
    }

    function saveProductNutrient(root, form) {
        var productId = selectedId('[data-nutrient-product-select]', root);
        if (!productId) {
            showMessage(root, 'warning', 'Selecciona un producto.');
            return;
        }

        clearMessage(root);
        window.CCApi.request(endpoint('/admin/products/' + productId + '/nutrients'), {
            method: 'POST',
            body: productNutrientPayload(form),
        }).then(function () {
            showMessage(root, 'success', 'Valor nutricional de producto agregado.');
            form.reset();
            form.elements.status.value = 'active';
            fetchProductNutrients(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        var nutrientForm = qs('[data-nutrient-form]', root);
        var ingredientForm = qs('[data-ingredient-nutrient-form]', root);
        var productForm = qs('[data-product-nutrient-form]', root);

        qs('[data-nutrients-refresh]', root).addEventListener('click', function () {
            fetchNutrients(root, 1);
        });
        qs('[data-nutrients-search]', root).addEventListener('input', function () {
            fetchNutrients(root, 1);
        });
        qs('[data-nutrients-unit]', root).addEventListener('change', function () {
            fetchNutrients(root, 1);
        });
        qs('[data-nutrients-status]', root).addEventListener('change', function () {
            fetchNutrients(root, 1);
        });
        qs('[data-nutrients-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                fetchNutrients(root, state.page - 1);
            }
        });
        qs('[data-nutrients-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                fetchNutrients(root, state.page + 1);
            }
        });
        qs('[data-nutrient-reset]', root).addEventListener('click', function () {
            resetNutrientForm(root);
        });
        qs('[data-nutrient-restore-submit]', root).addEventListener('click', function () {
            restoreNutrient(root, qs('[data-nutrient-restore-id]', root).value);
        });
        qs('[data-ingredient-nutrients-refresh]', root).addEventListener('click', function () {
            fetchIngredientNutrients(root);
        });
        qs('[data-product-nutrients-refresh]', root).addEventListener('click', function () {
            fetchProductNutrients(root);
        });
        qs('[data-nutrient-ingredient-select]', root).addEventListener('change', function () {
            fetchIngredientNutrients(root);
        });
        qs('[data-nutrient-product-select]', root).addEventListener('change', function () {
            fetchProductNutrients(root);
        });

        nutrientForm.addEventListener('submit', function (event) {
            event.preventDefault();
            saveNutrient(root, nutrientForm);
        });
        ingredientForm.addEventListener('submit', function (event) {
            event.preventDefault();
            saveIngredientNutrient(root, ingredientForm);
        });
        productForm.addEventListener('submit', function (event) {
            event.preventDefault();
            saveProductNutrient(root, productForm);
        });

        root.addEventListener('click', function (event) {
            var target = event.target;
            var editId = target.getAttribute('data-nutrient-edit');
            var deleteId = target.getAttribute('data-nutrient-delete');
            var restoreId = target.getAttribute('data-nutrient-restore');
            var relationEditId = target.getAttribute('data-ingredient-nutrient-edit');

            if (editId) {
                var nutrient = state.nutrients.filter(function (item) {
                    return String(item.id) === String(editId);
                })[0];
                if (nutrient) {
                    fillNutrientForm(root, nutrient);
                }
            }
            if (deleteId) {
                deleteNutrient(root, deleteId);
            }
            if (restoreId) {
                restoreNutrient(root, restoreId);
            }
            if (relationEditId) {
                fillIngredientRelationForm(root, relationEditId);
            }
        });

        fetchLookups(root).then(function () {
            fetchNutrients(root, 1);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-nutrients]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
    });
})(window, document);
