(function (window, document) {
    'use strict';

    var state = {
        groups: [],
        currentGroupId: null,
        lists: [],
        selectedList: null,
        mealPlans: [],
        ingredients: [],
        products: [],
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

    function api(path) {
        return '/api/v1' + path;
    }

    function groupPath(path) {
        return api('/family-groups/' + encodeURIComponent(state.currentGroupId) + path);
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-shopping-lists-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-shopping-lists-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = '';
        alert.className = 'alert';
        alert.style.display = 'none';
    }

    function apiErrorMessage(error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        if (error.status === 401) {
            return 'La sesion vencio. Inicia sesion nuevamente.';
        }
        if (error.status === 403) {
            return 'No tenes permiso para esta accion.';
        }
        if (error.status === 404) {
            return 'No se encontro la lista solicitada.';
        }
        if (error.status === 409) {
            return apiError.message || 'La operacion tiene un conflicto.';
        }
        if (error.status === 422) {
            return apiError.message || 'Revisa los datos ingresados.';
        }
        return apiError.message || error.message || 'No se pudo completar la operacion.';
    }

    function handleError(root, error) {
        showMessage(root, 'danger', apiErrorMessage(error));
    }

    function option(label, value, selected) {
        return '<option value="' + escapeHtml(value) + '"' + (selected ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
    }

    function sourceLabel(value) {
        var labels = { manual: 'Manual', meal_plan: 'Planificacion', history: 'Historico' };
        return labels[value] || value;
    }

    function statusLabel(value) {
        var labels = { draft: 'Borrador', active: 'Lista para comprar', in_progress: 'En compra', completed: 'Completada', cancelled: 'Cancelada' };
        return labels[value] || value;
    }

    function itemStatusLabel(value) {
        var labels = { pending: 'Pendiente', purchased: 'Comprado', skipped: 'Omitido', cancelled: 'Cancelado' };
        return labels[value] || value;
    }

    function unitLabel(unit) {
        if (!unit) {
            return '';
        }
        return unit.symbol || unit.code || unit.name || '';
    }

    function itemName(item) {
        if (item.product) {
            return item.product.name || ('Producto #' + item.product.id);
        }
        if (item.ingredient) {
            return item.ingredient.name || ('Ingrediente #' + item.ingredient.id);
        }
        return item.display_name || item.free_text_name || ('Articulo #' + item.id);
    }

    function catalogName(item) {
        return item.name || item.normalized_name || item.code || ('#' + item.id);
    }

    function catalogUnitName(unit) {
        return [unit.symbol || unit.code || '', unit.name || ''].filter(Boolean).join(' - ') || ('#' + unit.id);
    }

    function renderGroups(root) {
        var select = qs('[data-shopping-list-group]', root);
        if (!select) {
            return;
        }
        select.innerHTML = '<option value="">Grupo familiar</option>' + state.groups.map(function (group) {
            return option(group.name || ('Grupo #' + group.id), group.id, String(group.id) === String(state.currentGroupId));
        }).join('');
    }

    function renderMealPlans(root) {
        var select = qs('[data-shopping-list-plan]', root);
        var generateSelect = qs('[data-shopping-list-generate-plan]', root);
        var options = state.mealPlans.map(function (plan) {
            return option('#' + plan.id + ' - ' + text(plan.start_date) + ' / ' + text(plan.end_date), plan.id, false);
        }).join('');
        if (select) {
            select.innerHTML = '<option value="">Plan asociado opcional</option>' + options;
        }
        if (generateSelect) {
            generateSelect.innerHTML = '<option value="">Plan para generar lista</option>' + options;
        }
    }

    function renderItemCatalogs(root) {
        var ingredientSelect = qs('[data-shopping-list-item-ingredient]', root);
        var productSelect = qs('[data-shopping-list-item-product]', root);
        var unitSelect = qs('[data-shopping-list-item-unit]', root);
        if (ingredientSelect) {
            ingredientSelect.innerHTML = '<option value="">Ingrediente opcional</option>' + state.ingredients.map(function (ingredient) {
                return option(catalogName(ingredient), ingredient.id, false);
            }).join('');
        }
        if (productSelect) {
            productSelect.innerHTML = '<option value="">Producto opcional</option>' + state.products.map(function (product) {
                return option(catalogName(product), product.id, false);
            }).join('');
        }
        if (unitSelect) {
            unitSelect.innerHTML = '<option value="">Unidad</option>' + state.units.map(function (unit) {
                return option(catalogUnitName(unit), unit.id, false);
            }).join('');
        }
    }

    function renderLists(root, meta) {
        var body = qs('[data-shopping-list-body]', root);
        var count = qs('[data-shopping-list-count]', root);
        var page = qs('[data-shopping-list-page]', root);
        var prev = qs('[data-shopping-list-prev]', root);
        var next = qs('[data-shopping-list-next]', root);
        if (count) {
            count.textContent = (meta && meta.total !== undefined ? meta.total : state.lists.length) + ' listas';
        }
        if (page) {
            page.textContent = 'Pagina ' + state.page + ' de ' + state.lastPage;
        }
        if (prev) {
            prev.disabled = state.page <= 1;
        }
        if (next) {
            next.disabled = state.page >= state.lastPage;
        }
        if (!body) {
            return;
        }
        if (!state.currentGroupId) {
            body.innerHTML = '<tr><td colspan="6" class="muted">Selecciona un grupo familiar.</td></tr>';
            return;
        }
        if (!state.lists.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay listas para los filtros seleccionados.</td></tr>';
            return;
        }
        body.innerHTML = state.lists.map(function (list) {
            return '<tr>' +
                '<td>#' + escapeHtml(list.id) + '</td>' +
                '<td>' + escapeHtml(sourceLabel(list.source_type)) + '</td>' +
                '<td>' + escapeHtml(statusLabel(list.status)) + '</td>' +
                '<td>' + escapeHtml(list.meal_plan_id ? ('#' + list.meal_plan_id) : '-') + '</td>' +
                '<td>' + escapeHtml(list.estimated_total) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-shopping-list-show="' + escapeHtml(list.id) + '">Ver</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-shopping-list-edit="' + escapeHtml(list.id) + '">Editar</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-shopping-list-delete="' + escapeHtml(list.id) + '">Eliminar</button></td>' +
                '</tr>';
        }).join('');
    }

    function renderDetail(root, list) {
        var target = qs('[data-shopping-list-detail]', root);
        if (!target) {
            return;
        }
        if (!list) {
            target.className = 'muted';
            target.textContent = 'Selecciona una lista para ver sus items.';
            return;
        }
        var items = list.items || [];
        var rows = items.length ? items.map(function (item) {
            return '<tr>' +
                '<td>' + escapeHtml(itemName(item)) + '</td>' +
                '<td>' + escapeHtml(item.quantity) + ' ' + escapeHtml(unitLabel(item.unit)) + '</td>' +
                '<td>' + escapeHtml(item.estimated_price) + '</td>' +
                '<td>' + escapeHtml(item.actual_price) + '</td>' +
                '<td>' + escapeHtml(itemStatusLabel(item.status)) + '</td>' +
                '<td>' + escapeHtml(item.notes) + '</td>' +
                '<td><button type="button" class="btn-secondary-web btn-sm" data-shopping-list-item-edit="' + escapeHtml(item.id) + '">Editar</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-shopping-list-item-delete="' + escapeHtml(item.id) + '">Eliminar</button></td>' +
                '</tr>';
        }).join('') : '<tr><td colspan="7" class="muted">La lista no tiene items cargados.</td></tr>';
        var purchased = items.filter(function (item) { return item.status === 'purchased'; });
        var canComplete = (list.status === 'active' || list.status === 'in_progress') && purchased.length > 0;
        target.className = '';
        target.innerHTML = '<div class="table-line"><span>Lista</span><strong>#' + escapeHtml(list.id) + '</strong></div>' +
            '<div class="table-line"><span>Origen</span><strong>' + escapeHtml(sourceLabel(list.source_type)) + '</strong></div>' +
            '<div class="table-line"><span>Estado</span><strong>' + escapeHtml(statusLabel(list.status)) + '</strong></div>' +
            '<div class="table-line"><span>Total estimado</span><strong>' + escapeHtml(list.estimated_total) + '</strong></div>' +
            renderLifecycleActions(list) +
            (list.status === 'in_progress' ? '<p class="muted">' + purchased.length + ' de ' + items.length + ' articulos comprados.</p>' : '') +
            '<div style="overflow:auto;margin-top:12px"><table class="web-table"><thead><tr><th>Item</th><th>Cantidad</th><th>Estimado</th><th>Real</th><th>Estado</th><th>Notas</th><th>Acciones</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
            '<div data-alt-panel></div>' +
            '<div data-compare-panel></div>' +
            (canComplete ? renderCompletePanel(purchased) : '');
        if (window.ShoppingAlternatives && state.currentGroupId) {
            var altPanel = qs('[data-alt-panel]', target);
            if (altPanel) { window.ShoppingAlternatives.mount(altPanel, state.currentGroupId, list.id); }
        }
        if (window.ShoppingCompare && state.currentGroupId) {
            var comparePanel = qs('[data-compare-panel]', target);
            if (comparePanel) { window.ShoppingCompare.mount(comparePanel, state.currentGroupId, list.id); }
        }
    }

    function itemCanAutoAssociate(item) {
        return !!item.product;
    }

    function renderLifecycleActions(list) {
        if (list.status === 'draft') {
            return '<div class="table-line"><button type="button" class="btn-main btn-sm" data-shopping-list-confirm>Confirmar lista</button></div>';
        }
        if (list.status === 'active') {
            return '<div class="table-line"><button type="button" class="btn-main btn-sm" data-shopping-list-start>Comenzar compra</button></div>';
        }
        if (list.status === 'in_progress') {
            return '<div class="table-line"><button type="button" class="btn-secondary-web btn-sm" data-shopping-list-pause>Pausar compra</button></div>';
        }
        return '';
    }

    function transitionList(root, action, nextBody) {
        if (!state.currentGroupId || !state.selectedList) {
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/shopping-lists/' + encodeURIComponent(state.selectedList.id) + action), {
            method: nextBody === undefined ? 'POST' : 'PATCH',
            body: nextBody,
        }).then(function () {
            return loadList(root, state.selectedList.id).then(function () {
                return loadLists(root);
            });
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderCompletePanel(purchasedItems) {
        var rows = purchasedItems.map(function (item) {
            var autoAssociable = itemCanAutoAssociate(item);
            var disabled = !!item.ingredient && !item.product;
            var note = disabled
                ? '<span class="muted"> - asocia un producto desde Mi cocina para poder sumarlo al stock</span>'
                : (!autoAssociable ? '<span class="muted"> - se creara como producto pendiente de revision</span>' : '');
            return '<label class="table-line" style="align-items:center">' +
                '<input type="checkbox" data-complete-item-checkbox value="' + escapeHtml(item.id) + '"' +
                (autoAssociable ? ' checked' : '') + (disabled ? ' disabled' : '') + ' /> ' +
                '<span>' + escapeHtml(itemName(item)) + ' (' + escapeHtml(item.quantity) + ' ' + escapeHtml(unitLabel(item.unit)) + ')' + note + '</span>' +
                '</label>';
        }).join('');
        return '<div class="card" style="margin-top:16px" data-complete-panel>' +
            '<h3>Finalizar compra</h3>' +
            '<p class="muted">Compraste ' + purchasedItems.length + ' articulo(s). Elegi cuales agregar a Mi cocina.</p>' +
            rows +
            '<button type="button" class="btn-main" data-shopping-list-complete style="margin-top:12px">Finalizar y actualizar Mi cocina</button>' +
            '</div>';
    }

    function completePurchase(root) {
        if (!state.currentGroupId || !state.selectedList) {
            return Promise.resolve();
        }
        var panel = qs('[data-complete-panel]', root);
        var checked = panel ? Array.prototype.slice.call(panel.querySelectorAll('[data-complete-item-checkbox]:checked')) : [];
        var itemsById = {};
        (state.selectedList.items || []).forEach(function (item) { itemsById[item.id] = item; });

        var payloadItems = checked.map(function (checkbox) {
            var id = Number(checkbox.value);
            var item = itemsById[id];
            if (item && itemCanAutoAssociate(item)) {
                return { shopping_list_item_id: id, add_to_stock: true };
            }
            return {
                shopping_list_item_id: id,
                add_to_stock: true,
                create_pending_product: true,
                name: (item && (item.free_text_name || item.display_name)) || 'Articulo',
            };
        });

        return window.CCApi.request(groupPath('/shopping-lists/' + encodeURIComponent(state.selectedList.id) + '/complete'), {
            method: 'POST',
            body: { items: payloadItems },
        }).then(function (response) {
            var summary = response.data || {};
            showMessage(root, 'success', 'Compra finalizada. Agregamos ' + (summary.items_added_to_stock_count || 0) + ' producto(s) a Mi cocina.');
            return loadList(root, state.selectedList.id).then(function () {
                return loadLists(root);
            });
        }).catch(function (error) {
            if (error.status === 409) {
                showMessage(root, 'warning', 'Esta lista ya habia sido finalizada.');
                return loadList(root, state.selectedList.id).then(function () {
                    return loadLists(root);
                });
            }
            handleError(root, error);
        });
    }

    function resetForm(root) {
        var form = qs('[data-shopping-list-form]', root);
        var title = qs('[data-shopping-list-form-title]', root);
        if (!form) {
            return;
        }
        form.reset();
        form.elements.id.value = '';
        form.elements.source_type.value = 'manual';
        form.elements.status.value = 'draft';
        if (title) {
            title.textContent = 'Crear lista';
        }
    }

    function fillForm(root, list) {
        var form = qs('[data-shopping-list-form]', root);
        var title = qs('[data-shopping-list-form-title]', root);
        if (!form || !list) {
            return;
        }
        form.elements.id.value = list.id;
        form.elements.source_type.value = list.source_type || 'manual';
        form.elements.meal_plan_id.value = list.meal_plan_id || '';
        form.elements.status.value = list.status || 'draft';
        form.elements.optimization_mode.value = list.optimization_mode || '';
        if (title) {
            title.textContent = 'Editar lista #' + list.id;
        }
    }

    function resetItemForm(root) {
        var form = qs('[data-shopping-list-item-form]', root);
        var title = qs('[data-shopping-list-item-form-title]', root);
        if (!form) {
            return;
        }
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'pending';
        if (title) {
            title.textContent = 'Agregar articulo';
        }
    }

    function fillItemForm(root, item) {
        var form = qs('[data-shopping-list-item-form]', root);
        var title = qs('[data-shopping-list-item-form-title]', root);
        if (!form || !item) {
            return;
        }
        form.elements.id.value = item.id;
        form.elements.free_text_name.value = item.free_text_name || '';
        form.elements.ingredient_id.value = item.ingredient ? item.ingredient.id : '';
        form.elements.product_id.value = item.product ? item.product.id : '';
        form.elements.quantity.value = item.quantity || '';
        form.elements.unit_id.value = item.unit ? item.unit.id : '';
        form.elements.estimated_price.value = item.estimated_price || '';
        form.elements.actual_price.value = item.actual_price || '';
        form.elements.status.value = item.status || 'pending';
        form.elements.notes.value = item.notes || '';
        if (title) {
            title.textContent = 'Editar articulo';
        }
    }

    function buildPayload(form) {
        var data = {
            source_type: form.elements.source_type.value,
            status: form.elements.status.value,
        };
        if (form.elements.meal_plan_id.value) {
            data.meal_plan_id = Number(form.elements.meal_plan_id.value);
        }
        if (form.elements.optimization_mode.value.trim()) {
            data.optimization_mode = form.elements.optimization_mode.value.trim();
        }
        return data;
    }

    function buildItemPayload(form) {
        var data = {};
        if (form.elements.ingredient_id.value) {
            data.ingredient_id = Number(form.elements.ingredient_id.value);
        }
        if (form.elements.product_id.value) {
            data.product_id = Number(form.elements.product_id.value);
        }
        if (form.elements.free_text_name.value.trim()) {
            data.free_text_name = form.elements.free_text_name.value.trim();
        }
        if (form.elements.quantity.value) {
            data.quantity = Number(form.elements.quantity.value);
        }
        if (form.elements.unit_id.value) {
            data.unit_id = Number(form.elements.unit_id.value);
        }
        if (form.elements.estimated_price.value !== '') {
            data.estimated_price = Number(form.elements.estimated_price.value);
        }
        if (form.elements.actual_price.value !== '') {
            data.actual_price = Number(form.elements.actual_price.value);
        }
        if (form.elements.status.value) {
            data.status = form.elements.status.value;
        }
        if (form.elements.notes.value.trim()) {
            data.notes = form.elements.notes.value.trim();
        }
        return data;
    }

    function buildQuery(root) {
        var params = new URLSearchParams();
        var status = qs('[data-shopping-list-status]', root);
        var source = qs('[data-shopping-list-source]', root);
        params.set('page', state.page);
        params.set('per_page', 20);
        if (status && status.value) {
            params.set('status', status.value);
        }
        if (source && source.value) {
            params.set('source_type', source.value);
        }
        return params.toString();
    }

    function loadGroups(root) {
        return window.CCApi.request(api('/family-groups'))
            .then(function (response) {
                state.groups = response.data || [];
                if (!state.currentGroupId && state.groups.length) {
                    state.currentGroupId = state.groups[0].id;
                }
                renderGroups(root);
                if (!state.currentGroupId) {
                    renderLists(root, {});
                    showMessage(root, 'warning', 'Necesitas un grupo familiar para gestionar listas.');
                    return null;
                }
                return loadMealPlans(root).then(function () {
                    return loadLists(root);
                });
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function loadMealPlans(root) {
        if (!state.currentGroupId) {
            state.mealPlans = [];
            renderMealPlans(root);
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/meal-plans?per_page=100'))
            .then(function (response) {
                state.mealPlans = response.data || [];
                renderMealPlans(root);
            })
            .catch(function () {
                state.mealPlans = [];
                renderMealPlans(root);
            });
    }

    function readCollection(response) {
        return response && response.data ? response.data : [];
    }

    function loadItemCatalogs(root) {
        return Promise.all([
            window.CCApi.request(api('/ingredients?per_page=100')).catch(function () { return { data: [] }; }),
            window.CCApi.request(api('/products?per_page=100')).catch(function () { return { data: [] }; }),
            window.CCApi.request(api('/units?per_page=100')).catch(function () { return { data: [] }; }),
        ]).then(function (responses) {
            state.ingredients = readCollection(responses[0]);
            state.products = readCollection(responses[1]);
            state.units = readCollection(responses[2]);
            renderItemCatalogs(root);
        });
    }

    function loadLists(root) {
        clearMessage(root);
        if (!state.currentGroupId) {
            renderLists(root, {});
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/shopping-lists?' + buildQuery(root)))
            .then(function (response) {
                state.lists = response.data || [];
                state.page = response.meta ? response.meta.current_page : state.page;
                state.lastPage = response.meta ? response.meta.last_page : 1;
                renderLists(root, response.meta || {});
            })
            .catch(function (error) {
                state.lists = [];
                renderLists(root, {});
                handleError(root, error);
            });
    }

    function loadList(root, id) {
        if (!state.currentGroupId) {
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/shopping-lists/' + encodeURIComponent(id)))
            .then(function (response) {
                state.selectedList = response.data || null;
                renderDetail(root, state.selectedList);
                resetItemForm(root);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function loadItems(root) {
        if (!state.currentGroupId || !state.selectedList) {
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/shopping-lists/' + encodeURIComponent(state.selectedList.id) + '/items'))
            .then(function (response) {
                state.selectedList.items = response.data || [];
                renderDetail(root, state.selectedList);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function saveList(root, form) {
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return Promise.resolve();
        }
        var id = form.elements.id.value;
        return window.CCApi.request(groupPath('/shopping-lists' + (id ? '/' + encodeURIComponent(id) : '')), {
            method: id ? 'PATCH' : 'POST',
            body: buildPayload(form),
        }).then(function (response) {
            showMessage(root, 'success', id ? 'Lista actualizada.' : 'Lista creada.');
            state.selectedList = response.data || null;
            renderDetail(root, state.selectedList);
            resetForm(root);
            resetItemForm(root);
            return loadLists(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteList(root, id) {
        if (!state.currentGroupId || !id || !window.confirm('Eliminar esta lista de compras?')) {
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/shopping-lists/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function (response) {
            showMessage(root, 'success', 'Lista eliminada.');
            state.selectedList = response.data || null;
            renderDetail(root, state.selectedList);
            resetItemForm(root);
            return loadLists(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function generateFromMealPlan(root, form) {
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return Promise.resolve();
        }
        var mealPlanId = form.elements.meal_plan_id.value;
        if (!mealPlanId) {
            showMessage(root, 'warning', 'Selecciona un plan para generar la lista.');
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/shopping-lists/generate-from-meal-plan'), {
            method: 'POST',
            body: { meal_plan_id: Number(mealPlanId) },
        }).then(function (response) {
            showMessage(root, 'success', 'Lista generada desde menu.');
            state.selectedList = response.data || null;
            renderDetail(root, state.selectedList);
            resetItemForm(root);
            return loadLists(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function generateFromHistory(root, form) {
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return Promise.resolve();
        }
        var data = {};
        if (form.elements.date_from.value) {
            data.date_from = form.elements.date_from.value;
        }
        if (form.elements.date_to.value) {
            data.date_to = form.elements.date_to.value;
        }
        return window.CCApi.request(groupPath('/shopping-lists/generate-from-history'), {
            method: 'POST',
            body: data,
        }).then(function (response) {
            showMessage(root, 'success', 'Lista generada desde historico.');
            state.selectedList = response.data || null;
            renderDetail(root, state.selectedList);
            resetItemForm(root);
            return loadLists(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function saveItem(root, form) {
        if (!state.currentGroupId || !state.selectedList) {
            showMessage(root, 'warning', 'Selecciona una lista antes de cargar items.');
            return Promise.resolve();
        }
        var id = form.elements.id.value;
        return window.CCApi.request(groupPath('/shopping-lists/' + encodeURIComponent(state.selectedList.id) + '/items' + (id ? '/' + encodeURIComponent(id) : '')), {
            method: id ? 'PATCH' : 'POST',
            body: buildItemPayload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Item actualizado.' : 'Item agregado.');
            resetItemForm(root);
            return loadItems(root).then(function () {
                return loadLists(root);
            });
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteItem(root, id) {
        if (!state.currentGroupId || !state.selectedList || !id || !window.confirm('Eliminar este item?')) {
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/shopping-lists/' + encodeURIComponent(state.selectedList.id) + '/items/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Item eliminado.');
            resetItemForm(root);
            return loadItems(root).then(function () {
                return loadLists(root);
            });
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        var groupSelect = qs('[data-shopping-list-group]', root);
        var form = qs('[data-shopping-list-form]', root);
        var itemForm = qs('[data-shopping-list-item-form]', root);
        var generatePlanForm = qs('[data-shopping-list-generate-plan-form]', root);
        var generateHistoryForm = qs('[data-shopping-list-generate-history-form]', root);
        ['[data-shopping-list-status]', '[data-shopping-list-source]'].forEach(function (selector) {
            var field = qs(selector, root);
            if (field) {
                field.addEventListener('change', function () {
                    state.page = 1;
                    loadLists(root);
                });
            }
        });
        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                state.currentGroupId = groupSelect.value || null;
                state.page = 1;
                state.selectedList = null;
                renderDetail(root, null);
                resetForm(root);
                resetItemForm(root);
                loadMealPlans(root).then(function () {
                    return loadLists(root);
                });
            });
        }
        qs('[data-shopping-list-refresh]', root).addEventListener('click', function () {
            loadLists(root);
        });
        qs('[data-shopping-list-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                state.page -= 1;
                loadLists(root);
            }
        });
        qs('[data-shopping-list-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                state.page += 1;
                loadLists(root);
            }
        });
        qs('[data-shopping-list-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                saveList(root, form);
            });
        }
        if (itemForm) {
            itemForm.addEventListener('submit', function (event) {
                event.preventDefault();
                saveItem(root, itemForm);
            });
        }
        qs('[data-shopping-list-item-reset]', root).addEventListener('click', function () {
            resetItemForm(root);
        });
        if (generatePlanForm) {
            generatePlanForm.addEventListener('submit', function (event) {
                event.preventDefault();
                generateFromMealPlan(root, generatePlanForm);
            });
        }
        if (generateHistoryForm) {
            generateHistoryForm.addEventListener('submit', function (event) {
                event.preventDefault();
                generateFromHistory(root, generateHistoryForm);
            });
        }
        qs('[data-shopping-list-body]', root).addEventListener('click', function (event) {
            var show = event.target.closest('[data-shopping-list-show]');
            var edit = event.target.closest('[data-shopping-list-edit]');
            var remove = event.target.closest('[data-shopping-list-delete]');
            if (show) {
                loadList(root, show.getAttribute('data-shopping-list-show'));
            }
            if (edit) {
                var id = edit.getAttribute('data-shopping-list-edit');
                var list = state.lists.find(function (candidate) {
                    return String(candidate.id) === String(id);
                });
                if (list) {
                    state.selectedList = list;
                    renderDetail(root, list);
                    resetItemForm(root);
                    fillForm(root, list);
                }
            }
            if (remove) {
                deleteList(root, remove.getAttribute('data-shopping-list-delete'));
            }
        });
        qs('[data-shopping-list-detail]', root).addEventListener('click', function (event) {
            var edit = event.target.closest('[data-shopping-list-item-edit]');
            var remove = event.target.closest('[data-shopping-list-item-delete]');
            var complete = event.target.closest('[data-shopping-list-complete]');
            var confirmBtn = event.target.closest('[data-shopping-list-confirm]');
            var startBtn = event.target.closest('[data-shopping-list-start]');
            var pauseBtn = event.target.closest('[data-shopping-list-pause]');
            if (edit && state.selectedList) {
                var editId = edit.getAttribute('data-shopping-list-item-edit');
                var item = (state.selectedList.items || []).find(function (candidate) {
                    return String(candidate.id) === String(editId);
                });
                fillItemForm(root, item);
            }
            if (remove) {
                deleteItem(root, remove.getAttribute('data-shopping-list-item-delete'));
            }
            if (complete) {
                complete.disabled = true;
                completePurchase(root).then(function () {
                    complete.disabled = false;
                });
            }
            if (confirmBtn) {
                confirmBtn.disabled = true;
                transitionList(root, '', { status: 'active' }).then(function () {
                    showMessage(root, 'success', 'Lista confirmada. Ya podes comenzar la compra.');
                });
            }
            if (startBtn) {
                startBtn.disabled = true;
                transitionList(root, '/start').then(function () {
                    showMessage(root, 'success', 'Compra iniciada.');
                });
            }
            if (pauseBtn) {
                pauseBtn.disabled = true;
                transitionList(root, '', { status: 'active' }).then(function () {
                    showMessage(root, 'success', 'Compra pausada.');
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-shopping-lists]');
        if (!root) {
            return;
        }
        bind(root);
        loadItemCatalogs(root).then(function () {
            return loadGroups(root);
        });

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (event) {
                event.preventDefault();
                resetForm(root);
                var form = qs('[data-shopping-list-form]', root);
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    var first = form.querySelector('select,input:not([type=hidden])');
                    if (first) { first.focus(); }
                }
            });
        }
    });
})(window, document);
