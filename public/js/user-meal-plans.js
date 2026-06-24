(function (window, document) {
    'use strict';

    var state = {
        groups: [],
        currentGroupId: null,
        plans: [],
        selectedPlan: null,
        selectedItem: null,
        portions: [],
        members: [],
        mealTypes: [],
        recipes: [],
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
        var alert = qs('[data-meal-plans-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-meal-plans-message]', root);
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

    function renderGroups(root) {
        var select = qs('[data-meal-plan-group]', root);
        if (!select) {
            return;
        }
        select.innerHTML = '<option value="">Grupo familiar</option>' + state.groups.map(function (group) {
            return option(group.name || ('Grupo #' + group.id), group.id, String(group.id) === String(state.currentGroupId));
        }).join('');
    }

    function renderOptions(root) {
        var mealType = qs('[data-meal-plan-meal-type]', root);
        var itemMealType = qs('[data-meal-plan-item-meal-type]', root);
        var recipe = qs('[data-meal-plan-recipe]', root);
        var itemRecipe = qs('[data-meal-plan-item-recipe]', root);
        var portionUser = qs('[data-meal-plan-portion-user]', root);
        var mealTypeOptions = '<option value="">Tipo de comida</option>' + state.mealTypes.map(function (item) {
                return option(item.name, item.id, false);
        }).join('');
        var recipeOptions = '<option value="">Receta opcional</option>' + state.recipes.map(function (item) {
                return option(item.name || item.nombre || ('Receta #' + item.id), item.id, false);
        }).join('');
        var memberOptions = '<option value="">Miembro del grupo</option>' + state.members.map(function (item) {
            return option((item.name || item.email || ('Usuario #' + item.user_id)), item.user_id, false);
        }).join('');
        if (mealType) {
            mealType.innerHTML = mealTypeOptions;
        }
        if (itemMealType) {
            itemMealType.innerHTML = mealTypeOptions;
        }
        if (recipe) {
            recipe.innerHTML = recipeOptions;
        }
        if (itemRecipe) {
            itemRecipe.innerHTML = recipeOptions;
        }
        if (portionUser) {
            portionUser.innerHTML = memberOptions;
        }
    }

    function periodLabel(value) {
        var labels = { daily: 'Diario', weekly: 'Semanal', monthly: 'Mensual' };
        return labels[value] || value;
    }

    function itemTitle(item) {
        if (item.recipe) {
            return item.recipe.name || item.recipe.nombre || ('Receta #' + item.recipe_id);
        }
        if (item.free_meal_description) {
            return item.free_meal_description;
        }
        if (item.is_eating_out) {
            return 'Comer afuera';
        }
        return 'Sin contenido';
    }

    function renderPlans(root, meta) {
        var body = qs('[data-meal-plan-body]', root);
        var count = qs('[data-meal-plan-count]', root);
        var page = qs('[data-meal-plan-page]', root);
        var prev = qs('[data-meal-plan-prev]', root);
        var next = qs('[data-meal-plan-next]', root);
        var period = qs('[data-meal-plan-period]', root);
        var visiblePlans = state.plans.filter(function (plan) {
            return !period || !period.value || plan.period_type === period.value;
        });

        if (count) {
            count.textContent = visiblePlans.length + ' planes visibles';
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
            body.innerHTML = '<tr><td colspan="5" class="muted">Necesitas seleccionar un grupo familiar.</td></tr>';
            return;
        }
        if (!visiblePlans.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay planes para los filtros seleccionados.</td></tr>';
            return;
        }
        body.innerHTML = visiblePlans.map(function (plan) {
            var items = plan.items || [];
            return '<tr>' +
                '<td><strong>' + escapeHtml(periodLabel(plan.period_type)) + '</strong><br><span class="muted">#' + escapeHtml(plan.id) + '</span></td>' +
                '<td>' + escapeHtml(plan.start_date) + '<br>' + escapeHtml(plan.end_date) + '</td>' +
                '<td>' + escapeHtml(plan.status) + '</td>' +
                '<td>' + escapeHtml(items.length) + ' comidas</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-meal-plan-show="' + escapeHtml(plan.id) + '">Ver</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-meal-plan-edit="' + escapeHtml(plan.id) + '">Editar</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-meal-plan-delete="' + escapeHtml(plan.id) + '">Eliminar</button></td>' +
                '</tr>';
        }).join('');
    }

    function renderDetail(root, plan) {
        var target = qs('[data-meal-plan-detail]', root);
        if (!target) {
            return;
        }
        if (!plan) {
            target.className = 'muted';
            target.textContent = 'Selecciona un plan para ver sus comidas.';
            return;
        }
        var items = plan.items || [];
        var rows = items.length ? items.map(function (item) {
            return '<tr>' +
                '<td>' + escapeHtml(item.date) + '</td>' +
                '<td>' + escapeHtml(item.meal_type ? item.meal_type.name : item.meal_type_id) + '</td>' +
                '<td>' + escapeHtml(itemTitle(item)) + '</td>' +
                '<td>' + escapeHtml(item.servings_total) + '</td>' +
                '<td>' + escapeHtml(item.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-meal-plan-item-portions="' + escapeHtml(item.id) + '">Porciones</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-meal-plan-item-edit="' + escapeHtml(item.id) + '">Editar</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-meal-plan-item-delete="' + escapeHtml(item.id) + '">Eliminar</button></td>' +
                '</tr>';
        }).join('') : '<tr><td colspan="6" class="muted">El plan no tiene comidas cargadas.</td></tr>';

        target.className = '';
        target.innerHTML = '<div class="table-line"><span>Periodo</span><strong>' + escapeHtml(periodLabel(plan.period_type)) + '</strong></div>' +
            '<div class="table-line"><span>Rango</span><strong>' + escapeHtml(plan.start_date) + ' / ' + escapeHtml(plan.end_date) + '</strong></div>' +
            '<div class="table-line"><span>Modo</span><strong>' + escapeHtml(plan.mode) + '</strong></div>' +
            '<div style="overflow:auto;margin-top:12px"><table class="web-table"><thead><tr><th>Fecha</th><th>Comida</th><th>Detalle</th><th>Porciones</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function renderPortions(root) {
        var panel = qs('[data-meal-plan-portions-panel]', root);
        var title = qs('[data-meal-plan-portions-title]', root);
        if (!panel) {
            return;
        }
        if (!state.selectedItem) {
            panel.className = 'muted';
            panel.textContent = 'Selecciona una comida para gestionar porciones.';
            if (title) {
                title.textContent = 'Porciones por persona';
            }
            return;
        }
        if (title) {
            title.textContent = 'Porciones: ' + itemTitle(state.selectedItem);
        }
        var rows = state.portions.length ? state.portions.map(function (portion) {
            var userName = portion.user ? (portion.user.name || portion.user.email) : ('Usuario #' + portion.user_id);
            return '<tr>' +
                '<td>' + escapeHtml(userName) + '</td>' +
                '<td>' + escapeHtml(portion.portion_factor) + '</td>' +
                '<td>' + escapeHtml(portion.servings) + '</td>' +
                '<td>' + escapeHtml(portion.notes) + '</td>' +
                '<td><button type="button" class="btn-secondary-web btn-sm" data-meal-plan-portion-edit="' + escapeHtml(portion.id) + '">Editar</button></td>' +
                '</tr>';
        }).join('') : '<tr><td colspan="5" class="muted">No hay porciones personalizadas para esta comida.</td></tr>';
        panel.className = '';
        panel.innerHTML = '<div style="overflow:auto"><table class="web-table"><thead><tr><th>Miembro</th><th>Factor</th><th>Porciones</th><th>Notas</th><th>Acciones</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function resetForm(root) {
        var form = qs('[data-meal-plan-form]', root);
        var title = qs('[data-meal-plan-form-title]', root);
        if (!form) {
            return;
        }
        form.reset();
        form.elements.id.value = '';
        form.elements.period_type.value = 'weekly';
        if (title) {
            title.textContent = 'Crear plan';
        }
    }

    function resetPortionForm(root) {
        var form = qs('[data-meal-plan-portion-form]', root);
        var title = qs('[data-meal-plan-portion-form-title]', root);
        if (!form) {
            return;
        }
        form.reset();
        form.elements.portion_id.value = '';
        form.elements.portion_factor.value = '';
        if (title) {
            title.textContent = 'Asignar porcion';
        }
    }

    function resetItemForm(root) {
        var form = qs('[data-meal-plan-item-form]', root);
        var title = qs('[data-meal-plan-item-form-title]', root);
        if (!form) {
            return;
        }
        form.reset();
        form.elements.item_id.value = '';
        if (state.selectedPlan && state.selectedPlan.start_date) {
            form.elements.date.value = state.selectedPlan.start_date;
        }
        if (title) {
            title.textContent = 'Agregar comida al calendario';
        }
    }

    function buildGenerationPayload(form) {
        return {
            period_type: form.elements.period_type.value,
            start_date: form.elements.start_date.value,
            end_date: form.elements.end_date.value,
        };
    }

    function fillForm(root, plan) {
        var form = qs('[data-meal-plan-form]', root);
        var title = qs('[data-meal-plan-form-title]', root);
        if (!form || !plan) {
            return;
        }
        var firstItem = (plan.items || [])[0] || {};
        form.elements.id.value = plan.id;
        form.elements.period_type.value = plan.period_type || 'weekly';
        form.elements.start_date.value = plan.start_date || '';
        form.elements.end_date.value = plan.end_date || '';
        form.elements.mode.value = plan.mode || '';
        form.elements.item_date.value = firstItem.date || plan.start_date || '';
        form.elements.meal_type_id.value = firstItem.meal_type_id || '';
        form.elements.recipe_id.value = firstItem.recipe_id || '';
        form.elements.free_meal_description.value = firstItem.free_meal_description || '';
        form.elements.servings_total.value = firstItem.servings_total || '';
        form.elements.notes.value = firstItem.notes || '';
        form.elements.is_eating_out.checked = !!firstItem.is_eating_out;
        if (title) {
            title.textContent = 'Editar plan #' + plan.id;
        }
    }

    function fillItemForm(root, item) {
        var form = qs('[data-meal-plan-item-form]', root);
        var title = qs('[data-meal-plan-item-form-title]', root);
        if (!form || !item) {
            return;
        }
        form.elements.item_id.value = item.id || '';
        form.elements.date.value = item.date || '';
        form.elements.meal_type_id.value = item.meal_type_id || '';
        form.elements.recipe_id.value = item.recipe_id || '';
        form.elements.free_meal_description.value = item.free_meal_description || '';
        form.elements.servings_total.value = item.servings_total || '';
        form.elements.notes.value = item.notes || '';
        form.elements.is_eating_out.checked = !!item.is_eating_out;
        if (title) {
            title.textContent = 'Editar comida #' + item.id;
        }
    }

    function fillPortionForm(root, portion) {
        var form = qs('[data-meal-plan-portion-form]', root);
        var title = qs('[data-meal-plan-portion-form-title]', root);
        if (!form || !portion) {
            return;
        }
        form.elements.portion_id.value = portion.id || '';
        form.elements.user_id.value = portion.user_id || '';
        form.elements.portion_factor.value = portion.portion_factor || '';
        form.elements.servings.value = portion.servings || '';
        form.elements.notes.value = portion.notes || '';
        if (title) {
            title.textContent = 'Editar porcion #' + portion.id;
        }
    }

    function buildItemPayload(form) {
        var data = {
            date: form.elements.date.value,
            meal_type_id: Number(form.elements.meal_type_id.value),
            is_eating_out: form.elements.is_eating_out.checked,
        };
        if (form.elements.recipe_id.value) {
            data.recipe_id = Number(form.elements.recipe_id.value);
        }
        if (form.elements.free_meal_description.value.trim()) {
            data.free_meal_description = form.elements.free_meal_description.value.trim();
        }
        if (form.elements.servings_total.value !== '') {
            data.servings_total = Number(form.elements.servings_total.value);
        }
        if (form.elements.notes.value.trim()) {
            data.notes = form.elements.notes.value.trim();
        }
        return data;
    }

    function buildPortionPayload(form) {
        var data = {};
        if (!form.elements.portion_id.value) {
            data.user_id = Number(form.elements.user_id.value);
        }
        if (form.elements.portion_factor.value !== '') {
            data.portion_factor = Number(form.elements.portion_factor.value);
        }
        if (form.elements.servings.value !== '') {
            data.servings = Number(form.elements.servings.value);
        }
        if (form.elements.notes.value.trim()) {
            data.notes = form.elements.notes.value.trim();
        }
        return data;
    }

    function buildPayload(form) {
        var data = {
            period_type: form.elements.period_type.value,
            start_date: form.elements.start_date.value,
            end_date: form.elements.end_date.value,
        };
        if (form.elements.mode.value.trim()) {
            data.mode = form.elements.mode.value.trim();
        }
        if (form.elements.item_date.value && form.elements.meal_type_id.value) {
            var item = {
                date: form.elements.item_date.value,
                meal_type_id: Number(form.elements.meal_type_id.value),
                is_eating_out: form.elements.is_eating_out.checked,
            };
            if (form.elements.recipe_id.value) {
                item.recipe_id = Number(form.elements.recipe_id.value);
            }
            if (form.elements.free_meal_description.value.trim()) {
                item.free_meal_description = form.elements.free_meal_description.value.trim();
            }
            if (form.elements.servings_total.value !== '') {
                item.servings_total = Number(form.elements.servings_total.value);
            }
            if (form.elements.notes.value.trim()) {
                item.notes = form.elements.notes.value.trim();
            }
            data.items = [item];
        }
        return data;
    }

    function buildListQuery(root) {
        var params = new URLSearchParams();
        var status = qs('[data-meal-plan-status]', root);
        var from = qs('[data-meal-plan-from]', root);
        var to = qs('[data-meal-plan-to]', root);
        params.set('page', state.page);
        params.set('per_page', 20);
        if (status && status.value) {
            params.set('status', status.value);
        }
        if (from && from.value) {
            params.set('date_from', from.value);
        }
        if (to && to.value) {
            params.set('date_to', to.value);
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
                    renderPlans(root, {});
                    showMessage(root, 'warning', 'Necesitas un grupo familiar para planificar comidas.');
                    return null;
                }
                return loadMembers(root).then(function () {
                    return loadPlans(root);
                });
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function loadMembers(root) {
        if (!state.currentGroupId) {
            state.members = [];
            renderOptions(root);
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/members'))
            .then(function (response) {
                state.members = response.data || [];
                renderOptions(root);
            })
            .catch(function () {
                state.members = [];
                renderOptions(root);
            });
    }

    function loadCatalogs(root) {
        return Promise.all([
            window.CCApi.request(api('/meal-types')).catch(function () { return { data: [] }; }),
            window.CCApi.request(api('/recipes?per_page=100')).catch(function () { return { data: [] }; }),
        ]).then(function (responses) {
            state.mealTypes = responses[0].data || [];
            state.recipes = responses[1].data || [];
            renderOptions(root);
        });
    }

    function loadPlans(root) {
        clearMessage(root);
        if (!state.currentGroupId) {
            renderPlans(root, {});
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/meal-plans?' + buildListQuery(root)))
            .then(function (response) {
                state.plans = response.data || [];
                state.page = response.meta ? response.meta.current_page : state.page;
                state.lastPage = response.meta ? response.meta.last_page : 1;
                renderPlans(root, response.meta || {});
                if (state.selectedPlan) {
                    var refreshed = state.plans.find(function (plan) {
                        return String(plan.id) === String(state.selectedPlan.id);
                    });
                    if (refreshed) {
                        state.selectedPlan = refreshed;
                        renderDetail(root, refreshed);
                    }
                }
            })
            .catch(function (error) {
                state.plans = [];
                renderPlans(root, {});
                handleError(root, error);
            });
    }

    function loadPlan(root, id) {
        if (!state.currentGroupId) {
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/meal-plans/' + encodeURIComponent(id)))
            .then(function (response) {
                state.selectedPlan = response.data || null;
                state.selectedItem = null;
                state.portions = [];
                renderDetail(root, state.selectedPlan);
                renderPortions(root);
                resetItemForm(root);
                resetPortionForm(root);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function loadPortions(root, itemId) {
        if (!state.currentGroupId || !state.selectedPlan || !state.selectedPlan.id) {
            return Promise.resolve();
        }
        var item = (state.selectedPlan.items || []).find(function (candidate) {
            return String(candidate.id) === String(itemId);
        });
        if (!item) {
            showMessage(root, 'warning', 'No se encontro la comida seleccionada.');
            return Promise.resolve();
        }
        state.selectedItem = item;
        resetPortionForm(root);
        return window.CCApi.request(groupPath('/meal-plans/' + encodeURIComponent(state.selectedPlan.id) + '/items/' + encodeURIComponent(itemId) + '/portions'))
            .then(function (response) {
                state.portions = response.data || [];
                renderPortions(root);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function saveItem(root, form) {
        if (!state.currentGroupId || !state.selectedPlan || !state.selectedPlan.id) {
            showMessage(root, 'warning', 'Selecciona un plan para agregar comidas.');
            return Promise.resolve();
        }
        var itemId = form.elements.item_id.value;
        var path = '/meal-plans/' + encodeURIComponent(state.selectedPlan.id) + '/items' + (itemId ? '/' + encodeURIComponent(itemId) : '');
        return window.CCApi.request(groupPath(path), {
            method: itemId ? 'PATCH' : 'POST',
            body: buildItemPayload(form),
        }).then(function () {
            showMessage(root, 'success', itemId ? 'Comida actualizada.' : 'Comida agregada.');
            resetItemForm(root);
            return loadPlan(root, state.selectedPlan.id).then(function () {
                return loadPlans(root);
            });
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteItem(root, itemId) {
        if (!state.currentGroupId || !state.selectedPlan || !state.selectedPlan.id || !itemId || !window.confirm('Eliminar esta comida del plan?')) {
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/meal-plans/' + encodeURIComponent(state.selectedPlan.id) + '/items/' + encodeURIComponent(itemId)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Comida eliminada.');
            if (state.selectedItem && String(state.selectedItem.id) === String(itemId)) {
                state.selectedItem = null;
                state.portions = [];
                renderPortions(root);
                resetPortionForm(root);
            }
            resetItemForm(root);
            return loadPlan(root, state.selectedPlan.id).then(function () {
                return loadPlans(root);
            });
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function savePortion(root, form) {
        if (!state.currentGroupId || !state.selectedPlan || !state.selectedPlan.id || !state.selectedItem || !state.selectedItem.id) {
            showMessage(root, 'warning', 'Selecciona una comida para asignar porciones.');
            return Promise.resolve();
        }
        var portionId = form.elements.portion_id.value;
        var path = '/meal-plans/' + encodeURIComponent(state.selectedPlan.id) + '/items/' + encodeURIComponent(state.selectedItem.id) + '/portions' + (portionId ? '/' + encodeURIComponent(portionId) : '');
        return window.CCApi.request(groupPath(path), {
            method: portionId ? 'PATCH' : 'POST',
            body: buildPortionPayload(form),
        }).then(function () {
            showMessage(root, 'success', portionId ? 'Porcion actualizada.' : 'Porcion asignada.');
            resetPortionForm(root);
            return loadPortions(root, state.selectedItem.id);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function savePlan(root, form) {
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return Promise.resolve();
        }
        var id = form.elements.id.value;
        var path = '/meal-plans' + (id ? '/' + encodeURIComponent(id) : '');
        return window.CCApi.request(groupPath(path), {
            method: id ? 'PATCH' : 'POST',
            body: buildPayload(form),
        }).then(function (response) {
            showMessage(root, 'success', id ? 'Plan actualizado.' : 'Plan creado.');
            state.selectedPlan = response.data || null;
            renderDetail(root, state.selectedPlan);
            resetForm(root);
            return loadPlans(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function generatePlan(root, form) {
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return Promise.resolve();
        }
        var submit = qs('[data-meal-plan-generate-submit]', root);
        if (submit) {
            submit.disabled = true;
        }
        return window.CCApi.request(groupPath('/meal-plans/generate'), {
            method: 'POST',
            body: buildGenerationPayload(form),
        }).then(function (response) {
            showMessage(root, 'success', 'Menu sugerido generado. Revisalo y aprobalo si esta correcto.');
            state.selectedPlan = response.data || null;
            renderDetail(root, state.selectedPlan);
            return loadPlans(root);
        }).catch(function (error) {
            handleError(root, error);
        }).finally(function () {
            if (submit) {
                submit.disabled = false;
            }
        });
    }

    function approveSelected(root) {
        if (!state.currentGroupId || !state.selectedPlan || !state.selectedPlan.id) {
            showMessage(root, 'warning', 'Selecciona un plan pendiente.');
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/meal-plans/' + encodeURIComponent(state.selectedPlan.id) + '/approve'), {
            method: 'POST',
        }).then(function (response) {
            showMessage(root, 'success', 'Plan aprobado.');
            state.selectedPlan = response.data || null;
            renderDetail(root, state.selectedPlan);
            return loadPlans(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function regenerateSelected(root) {
        var form = qs('[data-meal-plan-generate-form]', root);
        if (!state.currentGroupId || !state.selectedPlan || !state.selectedPlan.id) {
            showMessage(root, 'warning', 'Selecciona un plan no aprobado.');
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/meal-plans/' + encodeURIComponent(state.selectedPlan.id) + '/regenerate'), {
            method: 'POST',
            body: form ? buildGenerationPayload(form) : {},
        }).then(function (response) {
            showMessage(root, 'success', 'Plan regenerado.');
            state.selectedPlan = response.data || null;
            renderDetail(root, state.selectedPlan);
            return loadPlans(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deletePlan(root, id) {
        if (!state.currentGroupId || !id || !window.confirm('Eliminar este plan de comidas?')) {
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/meal-plans/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Plan eliminado.');
            state.selectedPlan = null;
            renderDetail(root, null);
            return loadPlans(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        var groupSelect = qs('[data-meal-plan-group]', root);
        var form = qs('[data-meal-plan-form]', root);
        var itemForm = qs('[data-meal-plan-item-form]', root);
        var portionForm = qs('[data-meal-plan-portion-form]', root);
        var generateForm = qs('[data-meal-plan-generate-form]', root);
        var detail = qs('[data-meal-plan-detail]', root);
        var portionsPanel = qs('[data-meal-plan-portions-panel]', root);
        ['[data-meal-plan-period]', '[data-meal-plan-status]', '[data-meal-plan-from]', '[data-meal-plan-to]'].forEach(function (selector) {
            var field = qs(selector, root);
            if (field) {
                field.addEventListener('change', function () {
                    state.page = 1;
                    loadPlans(root);
                });
            }
        });
        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                state.currentGroupId = groupSelect.value || null;
                state.page = 1;
                state.selectedPlan = null;
                state.selectedItem = null;
                state.portions = [];
                state.members = [];
                renderDetail(root, null);
                renderPortions(root);
                resetItemForm(root);
                resetPortionForm(root);
                loadMembers(root).then(function () {
                    return loadPlans(root);
                });
            });
        }
        qs('[data-meal-plan-refresh]', root).addEventListener('click', function () {
            loadPlans(root);
        });
        qs('[data-meal-plan-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                state.page -= 1;
                loadPlans(root);
            }
        });
        qs('[data-meal-plan-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                state.page += 1;
                loadPlans(root);
            }
        });
        qs('[data-meal-plan-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-meal-plan-item-reset]', root).addEventListener('click', function () {
            resetItemForm(root);
        });
        qs('[data-meal-plan-portion-reset]', root).addEventListener('click', function () {
            resetPortionForm(root);
        });
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                savePlan(root, form);
            });
        }
        if (itemForm) {
            itemForm.addEventListener('submit', function (event) {
                event.preventDefault();
                saveItem(root, itemForm);
            });
        }
        if (portionForm) {
            portionForm.addEventListener('submit', function (event) {
                event.preventDefault();
                savePortion(root, portionForm);
            });
        }
        if (generateForm) {
            generateForm.addEventListener('submit', function (event) {
                event.preventDefault();
                generatePlan(root, generateForm);
            });
        }
        qs('[data-meal-plan-approve]', root).addEventListener('click', function () {
            approveSelected(root);
        });
        qs('[data-meal-plan-regenerate]', root).addEventListener('click', function () {
            regenerateSelected(root);
        });
        qs('[data-meal-plan-body]', root).addEventListener('click', function (event) {
            var show = event.target.closest('[data-meal-plan-show]');
            var edit = event.target.closest('[data-meal-plan-edit]');
            var remove = event.target.closest('[data-meal-plan-delete]');
            if (show) {
                loadPlan(root, show.getAttribute('data-meal-plan-show'));
            }
            if (edit) {
                var id = edit.getAttribute('data-meal-plan-edit');
                var plan = state.plans.find(function (item) {
                    return String(item.id) === String(id);
                });
                if (plan) {
                    state.selectedPlan = plan;
                    renderDetail(root, plan);
                    fillForm(root, plan);
                    resetItemForm(root);
                }
            }
            if (remove) {
                deletePlan(root, remove.getAttribute('data-meal-plan-delete'));
            }
        });
        if (detail) {
            detail.addEventListener('click', function (event) {
                var editItem = event.target.closest('[data-meal-plan-item-edit]');
                var deleteItemButton = event.target.closest('[data-meal-plan-item-delete]');
                var portionsButton = event.target.closest('[data-meal-plan-item-portions]');
                if (portionsButton) {
                    loadPortions(root, portionsButton.getAttribute('data-meal-plan-item-portions'));
                }
                if (editItem) {
                    var itemId = editItem.getAttribute('data-meal-plan-item-edit');
                    var item = ((state.selectedPlan || {}).items || []).find(function (candidate) {
                        return String(candidate.id) === String(itemId);
                    });
                    if (item) {
                        fillItemForm(root, item);
                    }
                }
                if (deleteItemButton) {
                    deleteItem(root, deleteItemButton.getAttribute('data-meal-plan-item-delete'));
                }
            });
        }
        if (portionsPanel) {
            portionsPanel.addEventListener('click', function (event) {
                var editPortion = event.target.closest('[data-meal-plan-portion-edit]');
                if (!editPortion) {
                    return;
                }
                var portionId = editPortion.getAttribute('data-meal-plan-portion-edit');
                var portion = state.portions.find(function (candidate) {
                    return String(candidate.id) === String(portionId);
                });
                if (portion) {
                    fillPortionForm(root, portion);
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-meal-plans]');
        if (!root) {
            return;
        }
        bind(root);
        Promise.all([loadCatalogs(root), loadGroups(root)]);
    });
})(window, document);
