(function (window, document) {
    'use strict';

    var API_BASE = '/api/v1';

    var state = {
        groups: [],
        currentGroupId: null,
        locations: [],
        locationOptions: [],
        locationPage: 1,
        locationLastPage: 1,
        stock: [],
        stockPage: 1,
        stockLastPage: 1,
        movements: [],
        movementPage: 1,
        movementLastPage: 1,
        alerts: [],
        expiring: [],
        lowStock: [],
        rules: [],
        products: [],
        units: [],
        productSearchTimer: null,
        loading: false,
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
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
        var alert = qs('[data-stock-locations-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-stock-locations-message]', root);
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
            return 'No tenes permiso para esta accion en el grupo.';
        }
        if (error.status === 404) {
            return 'No se encontro el recurso solicitado.';
        }
        if (error.status === 409) {
            return apiError.message || 'La operacion tiene un conflicto con datos existentes.';
        }
        if (error.status === 422) {
            return apiError.message || 'Revisa los datos ingresados.';
        }
        return apiError.message || error.message || 'No se pudo completar la operacion.';
    }

    function handleError(root, error) {
        showMessage(root, 'danger', apiErrorMessage(error));
    }

    function endpoint(groupId, path) {
        return API_BASE + '/family-groups/' + encodeURIComponent(groupId) + path;
    }

    function formatMoney(currency, value) {
        var number = Number(value || 0);
        return (currency || 'ARS') + ' ' + number.toFixed(2);
    }

    function productLabel(product) {
        if (!product) {
            return '-';
        }
        return product.name || ('Producto #' + product.id);
    }

    function unitLabel(unit) {
        if (!unit) {
            return '';
        }
        return unit.symbol || unit.code || unit.name || '';
    }

    function locationLabel(location) {
        if (!location) {
            return 'Sin ubicacion';
        }
        return location.name || ('Ubicacion #' + location.id);
    }

    function movementTypeLabel(type) {
        var labels = {
            adjustment: 'Ajuste',
            consumption: 'Consumo',
            discard: 'Descarte',
            entry: 'Entrada',
            expiration: 'Vencimiento',
            recipe_consumption: 'Receta',
        };
        return labels[type] || type || '-';
    }

    function stockItemLabel(item) {
        if (!item) {
            return 'Selecciona item';
        }
        return productLabel(item.product) + ' - ' + locationLabel(item.location) + ' - ' +
            text(item.quantity) + ' ' + unitLabel(item.unit);
    }

    function getLocation(id) {
        return state.locations.filter(function (location) {
            return String(location.id) === String(id);
        })[0] || null;
    }

    function renderGroups(root) {
        var select = qs('[data-stock-group-select]', root);
        if (!select) {
            return;
        }

        if (!state.groups.length) {
            select.innerHTML = '<option value="">Sin grupo familiar activo</option>';
            return;
        }

        select.innerHTML = state.groups.map(function (group) {
            return '<option value="' + group.id + '"' + (String(group.id) === String(state.currentGroupId) ? ' selected' : '') + '>' +
                escapeHtml(group.name) +
                '</option>';
        }).join('');
    }

    function renderLocationOptions(root) {
        var optionHtml = state.locationOptions.map(function (location) {
            return '<option value="' + location.id + '">' + escapeHtml(locationLabel(location)) + '</option>';
        }).join('');

        qsa('[data-stock-filter-location], [data-stock-item-location]', root).forEach(function (select) {
            var current = select.value;
            var first = select.hasAttribute('data-stock-filter-location') ? '<option value="">Todas las ubicaciones</option>' : '<option value="">Sin ubicacion</option>';
            select.innerHTML = first + optionHtml;
            select.value = current;
        });
    }

    function renderProducts(root) {
        ['[data-stock-product-select]', '[data-stock-rule-product-select]'].forEach(function (selector) {
            var select = qs(selector, root);
            if (!select) {
                return;
            }
            var current = select.value;
            select.innerHTML = '<option value="">Selecciona producto</option>' + state.products.map(function (product) {
                return '<option value="' + product.id + '">' + escapeHtml(productLabel(product)) + '</option>';
            }).join('');
            select.value = current;
        });
    }

    function renderUnits(root) {
        ['[data-stock-unit-select]', '[data-stock-rule-unit-select]'].forEach(function (selector) {
            var select = qs(selector, root);
            if (!select) {
                return;
            }
            var current = select.value;
            select.innerHTML = '<option value="">Selecciona unidad</option>' + state.units.map(function (unit) {
                return '<option value="' + unit.id + '">' + escapeHtml(unit.name) + (unit.symbol ? ' (' + escapeHtml(unit.symbol) + ')' : '') + '</option>';
            }).join('');
            select.value = current;
        });
    }

    function renderMovementItemOptions(root) {
        var select = qs('[data-stock-movement-item-select]', root);
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '<option value="">Selecciona item</option>' + state.stock.map(function (item) {
            return '<option value="' + item.id + '">' + escapeHtml(stockItemLabel(item)) + '</option>';
        }).join('');
        select.value = current;
    }

    function renderLocationLoading(root) {
        var body = qs('[data-stock-locations-body]', root);
        if (body) {
            body.innerHTML = '<tr><td colspan="5" class="muted">Cargando ubicaciones...</td></tr>';
        }
    }

    function renderStockLoading(root) {
        var body = qs('[data-stock-body]', root);
        if (body) {
            body.innerHTML = '<tr><td colspan="6" class="muted">Cargando stock...</td></tr>';
        }
    }

    function renderMovementLoading(root) {
        var body = qs('[data-stock-movements-body]', root);
        if (body) {
            body.innerHTML = '<tr><td colspan="7" class="muted">Cargando movimientos...</td></tr>';
        }
    }

    function renderLocations(root) {
        var body = qs('[data-stock-locations-body]', root);
        var count = qs('[data-stock-locations-count]', root);
        var page = qs('[data-stock-locations-page]', root);
        var prev = qs('[data-stock-locations-prev]', root);
        var next = qs('[data-stock-locations-next]', root);

        if (count) {
            count.textContent = state.locations.length + (state.locations.length === 1 ? ' ubicacion' : ' ubicaciones');
        }
        if (page) {
            page.textContent = 'Pagina ' + state.locationPage + ' de ' + state.locationLastPage;
        }
        if (prev) {
            prev.disabled = state.locationPage <= 1 || state.loading;
        }
        if (next) {
            next.disabled = state.locationPage >= state.locationLastPage || state.loading;
        }
        renderLocationOptions(root);

        if (!body) {
            return;
        }
        if (!state.currentGroupId) {
            body.innerHTML = '<tr><td colspan="5" class="muted">Selecciona un grupo familiar.</td></tr>';
            return;
        }
        if (!state.locations.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay ubicaciones para mostrar.</td></tr>';
            return;
        }

        body.innerHTML = state.locations.map(function (location) {
            return '<tr>' +
                '<td><strong>' + escapeHtml(location.name) + '</strong></td>' +
                '<td>' + escapeHtml(location.type) + '</td>' +
                '<td><span class="chip">' + escapeHtml(location.status) + '</span></td>' +
                '<td>' + escapeHtml(location.updated_at || location.created_at) + '</td>' +
                '<td>' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-location-edit="' + location.id + '">Editar</button> ' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-location-delete="' + location.id + '">Eliminar</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function renderStock(root) {
        var body = qs('[data-stock-body]', root);
        var count = qs('[data-stock-count]', root);
        var page = qs('[data-stock-page]', root);
        var prev = qs('[data-stock-prev]', root);
        var next = qs('[data-stock-next]', root);

        if (count) {
            count.textContent = state.stock.length + (state.stock.length === 1 ? ' item' : ' items');
        }
        if (page) {
            page.textContent = 'Pagina ' + state.stockPage + ' de ' + state.stockLastPage;
        }
        if (prev) {
            prev.disabled = state.stockPage <= 1 || state.loading;
        }
        if (next) {
            next.disabled = state.stockPage >= state.stockLastPage || state.loading;
        }

        if (!body) {
            return;
        }
        if (!state.currentGroupId) {
            body.innerHTML = '<tr><td colspan="6" class="muted">Selecciona un grupo familiar.</td></tr>';
            return;
        }
        if (!state.stock.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay stock cargado para mostrar.</td></tr>';
            return;
        }

        body.innerHTML = state.stock.map(function (item) {
            return '<tr>' +
                '<td><strong>' + escapeHtml(productLabel(item.product)) + '</strong></td>' +
                '<td>' + escapeHtml(locationLabel(item.location)) + '</td>' +
                '<td>' + escapeHtml(item.quantity) + ' ' + escapeHtml(unitLabel(item.unit)) + '</td>' +
                '<td>' + escapeHtml(item.expiration_date) + '</td>' +
                '<td>' + escapeHtml(item.purchase_price) + '</td>' +
                '<td>' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-edit="' + item.id + '">Editar</button> ' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-movement-for="' + item.id + '">Mover</button> ' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-delete="' + item.id + '">Eliminar</button>' +
                '</td>' +
            '</tr>';
        }).join('');
        renderMovementItemOptions(root);
    }

    function renderMovements(root) {
        var body = qs('[data-stock-movements-body]', root);
        var count = qs('[data-stock-movements-count]', root);
        var page = qs('[data-stock-movements-page]', root);
        var prev = qs('[data-stock-movements-prev]', root);
        var next = qs('[data-stock-movements-next]', root);

        if (count) {
            count.textContent = state.movements.length + (state.movements.length === 1 ? ' movimiento' : ' movimientos');
        }
        if (page) {
            page.textContent = 'Pagina ' + state.movementPage + ' de ' + state.movementLastPage;
        }
        if (prev) {
            prev.disabled = state.movementPage <= 1 || state.loading;
        }
        if (next) {
            next.disabled = state.movementPage >= state.movementLastPage || state.loading;
        }
        if (!body) {
            return;
        }
        if (!state.currentGroupId) {
            body.innerHTML = '<tr><td colspan="7" class="muted">Selecciona un grupo familiar.</td></tr>';
            return;
        }
        if (!state.movements.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay movimientos para mostrar.</td></tr>';
            return;
        }
        body.innerHTML = state.movements.map(function (movement) {
            var unit = movement.unit || {};
            return '<tr>' +
                '<td>' + escapeHtml(movement.created_at) + '</td>' +
                '<td><span class="chip">' + escapeHtml(movementTypeLabel(movement.movement_type)) + '</span></td>' +
                '<td>' + escapeHtml(productLabel(movement.product)) + '</td>' +
                '<td>' + escapeHtml(locationLabel(movement.location)) + '</td>' +
                '<td>' + escapeHtml(movement.quantity) + ' ' + escapeHtml(unit.symbol || unit.code || unit.name) + '</td>' +
                '<td>' + escapeHtml(movement.reason) + '</td>' +
                '<td>' + escapeHtml(movement.user && (movement.user.name || movement.user.email)) + '</td>' +
            '</tr>';
        }).join('');
    }

    function renderStockItemsList(target, items, emptyMessage) {
        if (!target) {
            return;
        }
        if (!items.length) {
            target.innerHTML = '<p class="muted">' + escapeHtml(emptyMessage) + '</p>';
            return;
        }
        target.innerHTML = items.map(function (item) {
            return '<div class="table-line">' +
                '<span class="muted">' + escapeHtml(item.expiration_date || locationLabel(item.location)) + '</span>' +
                '<strong>' + escapeHtml(productLabel(item.product)) + ' - ' +
                    escapeHtml(item.quantity) + ' ' + escapeHtml(unitLabel(item.unit)) +
                '</strong>' +
            '</div>';
        }).join('');
    }

    function renderAlerts(root) {
        var target = qs('[data-stock-alerts-list]', root);
        var count = qs('[data-stock-alerts-count]', root);
        if (count) {
            count.textContent = state.alerts.length + (state.alerts.length === 1 ? ' alerta' : ' alertas');
        }
        if (!target) {
            return;
        }
        if (!state.currentGroupId) {
            target.innerHTML = '<p class="muted">Selecciona un grupo familiar.</p>';
            return;
        }
        if (!state.alerts.length) {
            target.innerHTML = '<p class="muted">No hay alertas para mostrar.</p>';
            return;
        }
        target.innerHTML = state.alerts.map(function (alert) {
            var action = alert.status === 'read'
                ? '<span class="chip">Leida</span>'
                : '<button type="button" class="btn-secondary-web btn-sm" data-stock-alert-read="' + alert.id + '">Marcar leida</button>';
            return '<div class="panel" style="padding:12px">' +
                '<div class="table-line"><span class="muted">' + escapeHtml(alert.alert_type) + ' / ' + escapeHtml(alert.severity) + '</span><strong>' + escapeHtml(alert.message) + '</strong></div>' +
                '<div class="table-line"><span class="muted">Producto</span><strong>' + escapeHtml(productLabel(alert.product)) + '</strong></div>' +
                '<div class="table-line"><span class="muted">Ubicacion</span><strong>' + escapeHtml(locationLabel(alert.location)) + '</strong></div>' +
                '<div style="margin-top:8px">' + action + '</div>' +
            '</div>';
        }).join('');
    }

    function renderExpiring(root) {
        renderStockItemsList(qs('[data-stock-expiring-list]', root), state.expiring, 'No hay productos proximos a vencer.');
    }

    function renderLowStock(root) {
        renderStockItemsList(qs('[data-stock-low-list]', root), state.lowStock, 'No hay productos bajo minimo.');
    }

    function renderRules(root) {
        var body = qs('[data-stock-rules-body]', root);
        if (!body) {
            return;
        }
        if (!state.currentGroupId) {
            body.innerHTML = '<tr><td colspan="5" class="muted">Selecciona un grupo familiar.</td></tr>';
            return;
        }
        if (!state.rules.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay reglas de minimo cargadas.</td></tr>';
            return;
        }
        body.innerHTML = state.rules.map(function (rule) {
            var target = rule.product ? productLabel(rule.product) : (rule.ingredient ? rule.ingredient.name : '-');
            return '<tr>' +
                '<td>' + escapeHtml(target) + '</td>' +
                '<td>' + escapeHtml(rule.minimum_quantity) + '</td>' +
                '<td>' + escapeHtml(unitLabel(rule.unit)) + '</td>' +
                '<td><span class="chip">' + escapeHtml(rule.status) + '</span></td>' +
                '<td>' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-rule-edit="' + rule.id + '">Editar</button> ' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-rule-delete="' + rule.id + '">Eliminar</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function renderSummary(root, summary) {
        var total = qs('[data-stock-summary-total]', root);
        var products = qs('[data-stock-summary-products]', root);
        var expiring = qs('[data-stock-summary-expiring]', root);

        if (total) {
            total.textContent = summary && summary.total_items !== undefined ? summary.total_items : 0;
        }
        if (products) {
            products.textContent = summary && summary.distinct_products !== undefined ? summary.distinct_products : 0;
        }
        if (expiring) {
            expiring.textContent = summary && summary.expiring_soon !== undefined ? summary.expiring_soon : 0;
        }
    }

    function renderValue(root, value) {
        var total = qs('[data-stock-value-total]', root);
        var count = qs('[data-stock-value-count]', root);
        if (total) {
            total.textContent = formatMoney(value ? value.currency : 'ARS', value ? value.total_value : 0);
        }
        if (count) {
            var items = value && value.valued_items !== undefined ? value.valued_items : 0;
            count.textContent = items + (items === 1 ? ' valorizado' : ' valorizados');
        }
    }

    function resetLocationForm(root) {
        var form = qs('[data-stock-location-form]', root);
        var title = qs('[data-stock-location-form-title]', root);
        var cancel = qs('[data-stock-location-cancel]', root);
        if (form) {
            form.reset();
            form.elements.id.value = '';
            form.elements.status.value = 'active';
        }
        if (title) {
            title.textContent = 'Nueva ubicacion';
        }
        if (cancel) {
            cancel.style.display = 'none';
        }
    }

    function resetStockForm(root) {
        var form = qs('[data-stock-item-form]', root);
        var title = qs('[data-stock-item-form-title]', root);
        var cancel = qs('[data-stock-item-cancel]', root);
        if (form) {
            form.reset();
            form.elements.id.value = '';
            form.elements.status.value = 'active';
        }
        if (title) {
            title.textContent = 'Cargar stock';
        }
        if (cancel) {
            cancel.style.display = 'none';
        }
    }

    function updateMovementModeVisibility(root) {
        var operation = qs('[data-stock-movement-operation]', root);
        var mode = qs('[data-stock-movement-mode]', root);
        if (!operation || !mode) {
            return;
        }
        var show = operation.value === 'adjust';
        mode.disabled = !show;
        mode.style.opacity = show ? '1' : '0.55';
    }

    function resetMovementForm(root) {
        var form = qs('[data-stock-movement-form]', root);
        var title = qs('[data-stock-movement-form-title]', root);
        if (form) {
            form.reset();
            form.elements.stock_item_id.value = '';
            form.elements.operation.value = 'adjust';
            form.elements.mode.value = 'set';
        }
        if (title) {
            title.textContent = 'Registrar movimiento';
        }
        updateMovementModeVisibility(root);
    }

    function resetRuleForm(root) {
        var form = qs('[data-stock-rule-form]', root);
        var title = qs('[data-stock-rule-form-title]', root);
        if (form) {
            form.reset();
            form.elements.id.value = '';
            form.elements.status.value = 'active';
        }
        if (title) {
            title.textContent = 'Regla de minimo';
        }
    }

    function fillLocationForm(root, location) {
        var form = qs('[data-stock-location-form]', root);
        var title = qs('[data-stock-location-form-title]', root);
        var cancel = qs('[data-stock-location-cancel]', root);
        if (!form || !location) {
            return;
        }
        form.elements.id.value = location.id;
        form.elements.name.value = location.name || '';
        form.elements.type.value = location.type || '';
        form.elements.status.value = location.status || 'active';
        if (title) {
            title.textContent = 'Editar ubicacion';
        }
        if (cancel) {
            cancel.style.display = 'inline-flex';
        }
    }

    function fillStockForm(root, item) {
        var form = qs('[data-stock-item-form]', root);
        var title = qs('[data-stock-item-form-title]', root);
        var cancel = qs('[data-stock-item-cancel]', root);
        if (!form || !item) {
            return;
        }
        if (item.product && !state.products.filter(function (p) { return String(p.id) === String(item.product_id); }).length) {
            state.products.push(item.product);
            renderProducts(root);
        }
        form.elements.id.value = item.id;
        form.elements.product_id.value = item.product_id || '';
        form.elements.stock_location_id.value = item.stock_location_id || '';
        form.elements.quantity.value = item.quantity || 0;
        form.elements.unit_id.value = item.unit_id || '';
        form.elements.expiration_date.value = item.expiration_date || '';
        form.elements.purchase_price.value = item.purchase_price === null || item.purchase_price === undefined ? '' : item.purchase_price;
        form.elements.status.value = item.status || 'active';
        if (title) {
            title.textContent = 'Editar stock';
        }
        if (cancel) {
            cancel.style.display = 'inline-flex';
        }
    }

    function fillMovementForm(root, item) {
        var form = qs('[data-stock-movement-form]', root);
        var title = qs('[data-stock-movement-form-title]', root);
        if (!form || !item) {
            return;
        }
        form.elements.stock_item_id.value = item.id;
        form.elements.stock_item_select.value = item.id;
        form.elements.quantity.value = '';
        form.elements.reason.value = '';
        if (title) {
            title.textContent = 'Movimiento: ' + stockItemLabel(item);
        }
        updateMovementModeVisibility(root);
    }

    function fillRuleForm(root, rule) {
        var form = qs('[data-stock-rule-form]', root);
        var title = qs('[data-stock-rule-form-title]', root);
        if (!form || !rule) {
            return;
        }
        form.elements.id.value = rule.id;
        form.elements.product_id.value = rule.product_id || '';
        form.elements.minimum_quantity.value = rule.minimum_quantity || 0;
        form.elements.unit_id.value = rule.unit_id || '';
        form.elements.status.value = rule.status || 'active';
        if (title) {
            title.textContent = 'Editar regla';
        }
    }

    function loadGroups(root) {
        clearMessage(root);
        return window.CCApi.request(API_BASE + '/family-groups')
            .then(function (response) {
                state.groups = response.data || [];
                state.currentGroupId = state.groups.length ? state.groups[0].id : null;
                renderGroups(root);
                if (!state.currentGroupId) {
                    renderLocations(root);
                    renderStock(root);
                    renderSummary(root, {});
                    renderValue(root, {});
                    showMessage(root, 'warning', 'Necesitas un grupo familiar para administrar stock.');
                    return null;
                }
                return reloadGroupData(root);
            })
            .catch(function (error) {
                state.groups = [];
                state.currentGroupId = null;
                renderGroups(root);
                renderLocations(root);
                renderStock(root);
                handleError(root, error);
            });
    }

    function loadLocations(root) {
        if (!state.currentGroupId) {
            renderLocations(root);
            return Promise.resolve();
        }
        renderLocationLoading(root);
        var params = new URLSearchParams();
        params.set('page', state.locationPage);
        params.set('per_page', 100);
        var status = qs('[data-stock-location-status]', root);
        if (status && status.value) {
            params.set('status', status.value);
        }
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-locations') + '?' + params.toString())
            .then(function (response) {
                state.locations = response.data || [];
                state.locationPage = response.meta ? response.meta.current_page : 1;
                state.locationLastPage = response.meta ? response.meta.last_page : 1;
                renderLocations(root);
            })
            .catch(function (error) {
                state.locations = [];
                renderLocations(root);
                handleError(root, error);
            });
    }

    function loadLocationOptions(root) {
        if (!state.currentGroupId) {
            state.locationOptions = [];
            renderLocationOptions(root);
            return Promise.resolve();
        }
        var params = new URLSearchParams();
        params.set('per_page', 100);
        params.set('status', 'active');
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-locations') + '?' + params.toString())
            .then(function (response) {
                state.locationOptions = response.data || [];
                renderLocationOptions(root);
            })
            .catch(function () {
                state.locationOptions = [];
                renderLocationOptions(root);
            });
    }

    function loadStock(root) {
        if (!state.currentGroupId) {
            renderStock(root);
            return Promise.resolve();
        }
        renderStockLoading(root);
        var params = new URLSearchParams();
        params.set('page', state.stockPage);
        params.set('per_page', 20);
        var location = qs('[data-stock-filter-location]', root);
        var expiry = qs('[data-stock-filter-expiry]', root);
        if (location && location.value) {
            params.set('stock_location_id', location.value);
        }
        if (expiry && expiry.value) {
            params.set('expires_before', expiry.value);
        }
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock') + '?' + params.toString())
            .then(function (response) {
                state.stock = response.data || [];
                state.stockPage = response.meta ? response.meta.current_page : 1;
                state.stockLastPage = response.meta ? response.meta.last_page : 1;
                renderStock(root);
            })
            .catch(function (error) {
                state.stock = [];
                renderStock(root);
                handleError(root, error);
            });
    }

    function loadMovements(root) {
        if (!state.currentGroupId) {
            renderMovements(root);
            return Promise.resolve();
        }
        renderMovementLoading(root);
        var params = new URLSearchParams();
        params.set('page', state.movementPage);
        params.set('per_page', 20);
        var type = qs('[data-stock-movement-filter-type]', root);
        var from = qs('[data-stock-movement-date-from]', root);
        var to = qs('[data-stock-movement-date-to]', root);
        if (type && type.value) {
            params.set('type', type.value);
        }
        if (from && from.value) {
            params.set('date_from', from.value);
        }
        if (to && to.value) {
            params.set('date_to', to.value);
        }
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-movements') + '?' + params.toString())
            .then(function (response) {
                state.movements = response.data || [];
                state.movementPage = response.meta ? response.meta.current_page : 1;
                state.movementLastPage = response.meta ? response.meta.last_page : 1;
                renderMovements(root);
            })
            .catch(function (error) {
                state.movements = [];
                renderMovements(root);
                handleError(root, error);
            });
    }

    function loadAlerts(root) {
        if (!state.currentGroupId) {
            renderAlerts(root);
            return Promise.resolve();
        }
        var params = new URLSearchParams();
        params.set('per_page', 20);
        var status = qs('[data-stock-alert-status]', root);
        var severity = qs('[data-stock-alert-severity]', root);
        if (status && status.value) {
            params.set('status', status.value);
        }
        if (severity && severity.value) {
            params.set('severity', severity.value);
        }
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-alerts') + '?' + params.toString())
            .then(function (response) {
                state.alerts = response.data || [];
                renderAlerts(root);
            })
            .catch(function (error) {
                state.alerts = [];
                renderAlerts(root);
                handleError(root, error);
            });
    }

    function loadExpiring(root) {
        if (!state.currentGroupId) {
            renderExpiring(root);
            return Promise.resolve();
        }
        var days = qs('[data-stock-expiring-days]', root);
        var params = new URLSearchParams();
        params.set('per_page', 20);
        params.set('days', days && days.value ? days.value : 7);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock/expiring') + '?' + params.toString())
            .then(function (response) {
                state.expiring = response.data || [];
                renderExpiring(root);
            })
            .catch(function (error) {
                state.expiring = [];
                renderExpiring(root);
                handleError(root, error);
            });
    }

    function loadLowStock(root) {
        if (!state.currentGroupId) {
            renderLowStock(root);
            return Promise.resolve();
        }
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock/low-stock?per_page=20'))
            .then(function (response) {
                state.lowStock = response.data || [];
                renderLowStock(root);
            })
            .catch(function (error) {
                state.lowStock = [];
                renderLowStock(root);
                handleError(root, error);
            });
    }

    function loadRules(root) {
        if (!state.currentGroupId) {
            renderRules(root);
            return Promise.resolve();
        }
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-minimum-rules?per_page=100'))
            .then(function (response) {
                state.rules = response.data || [];
                renderRules(root);
            })
            .catch(function (error) {
                state.rules = [];
                renderRules(root);
                handleError(root, error);
            });
    }

    function loadSummary(root) {
        if (!state.currentGroupId) {
            return Promise.resolve();
        }
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock/summary'))
            .then(function (response) {
                renderSummary(root, response.data || {});
            })
            .catch(function () {
                renderSummary(root, {});
            });
    }

    function loadValue(root) {
        if (!state.currentGroupId) {
            return Promise.resolve();
        }
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock/value'))
            .then(function (response) {
                renderValue(root, response.data || {});
            })
            .catch(function () {
                renderValue(root, {});
            });
    }

    function reloadGroupData(root) {
        clearMessage(root);
        return Promise.all([
            loadLocations(root),
            loadLocationOptions(root),
            loadStock(root),
            loadMovements(root),
            loadAlerts(root),
            loadExpiring(root),
            loadLowStock(root),
            loadRules(root),
            loadSummary(root),
            loadValue(root),
        ]);
    }

    function loadProducts(root, search) {
        var params = new URLSearchParams();
        params.set('per_page', 100);
        if (search) {
            params.set('search', search);
        }
        return window.CCApi.request(API_BASE + '/products?' + params.toString())
            .then(function (response) {
                state.products = response.data || [];
                renderProducts(root);
            })
            .catch(function (error) {
                state.products = [];
                renderProducts(root);
                handleError(root, error);
            });
    }

    function loadUnits(root) {
        return window.CCApi.request(API_BASE + '/units?per_page=100')
            .then(function (response) {
                state.units = response.data || [];
                renderUnits(root);
            })
            .catch(function (error) {
                state.units = [];
                renderUnits(root);
                handleError(root, error);
            });
    }

    function saveLocation(root, event) {
        event.preventDefault();
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return;
        }
        var form = event.currentTarget;
        var submit = qs('[data-stock-location-submit]', root);
        var id = form.elements.id.value;
        var body = {
            name: form.elements.name.value.trim(),
            type: form.elements.type.value.trim() || null,
            status: form.elements.status.value,
        };
        if (submit) {
            submit.disabled = true;
        }
        clearMessage(root);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-locations' + (id ? '/' + encodeURIComponent(id) : '')), {
            method: id ? 'PATCH' : 'POST',
            body: body,
        }).then(function () {
            resetLocationForm(root);
            showMessage(root, 'success', id ? 'Ubicacion actualizada.' : 'Ubicacion creada.');
            return reloadGroupData(root);
        }).catch(function (error) {
            handleError(root, error);
        }).then(function () {
            if (submit) {
                submit.disabled = false;
            }
        });
    }

    function saveStock(root, event) {
        event.preventDefault();
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return;
        }
        var form = event.currentTarget;
        var submit = qs('[data-stock-item-submit]', root);
        var id = form.elements.id.value;
        var body = {
            product_id: Number(form.elements.product_id.value),
            stock_location_id: form.elements.stock_location_id.value ? Number(form.elements.stock_location_id.value) : null,
            quantity: Number(form.elements.quantity.value),
            unit_id: Number(form.elements.unit_id.value),
            expiration_date: form.elements.expiration_date.value || null,
            purchase_price: form.elements.purchase_price.value === '' ? null : Number(form.elements.purchase_price.value),
            status: form.elements.status.value,
        };
        if (submit) {
            submit.disabled = true;
        }
        clearMessage(root);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock' + (id ? '/' + encodeURIComponent(id) : '')), {
            method: id ? 'PATCH' : 'POST',
            body: body,
        }).then(function () {
            resetStockForm(root);
            showMessage(root, 'success', id ? 'Stock actualizado.' : 'Stock cargado.');
            return Promise.all([loadStock(root), loadSummary(root), loadValue(root)]);
        }).catch(function (error) {
            handleError(root, error);
        }).then(function () {
            if (submit) {
                submit.disabled = false;
            }
        });
    }

    function deleteLocation(root, id) {
        if (!state.currentGroupId || !id || !window.confirm('Desactivar esta ubicacion?')) {
            return;
        }
        clearMessage(root);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-locations/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Ubicacion eliminada.');
            return reloadGroupData(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteStock(root, id) {
        if (!state.currentGroupId || !id || !window.confirm('Eliminar este item de stock?')) {
            return;
        }
        clearMessage(root);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Item de stock eliminado.');
            return Promise.all([loadStock(root), loadSummary(root), loadValue(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function saveMovement(root, event) {
        event.preventDefault();
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return;
        }
        var form = event.currentTarget;
        var submit = qs('[data-stock-movement-submit]', root);
        var itemId = form.elements.stock_item_id.value || form.elements.stock_item_select.value;
        var operation = form.elements.operation.value;
        var quantity = Number(form.elements.quantity.value);
        var reason = form.elements.reason.value.trim();

        if (!itemId) {
            showMessage(root, 'danger', 'Selecciona un item de stock.');
            return;
        }
        if (operation !== 'adjust' && quantity <= 0) {
            showMessage(root, 'danger', 'La cantidad debe ser mayor que cero.');
            return;
        }
        if ((operation === 'adjust' || operation === 'discard') && !reason) {
            showMessage(root, 'danger', 'El motivo es obligatorio para esta operacion.');
            return;
        }

        var body = {
            quantity: quantity,
            reason: reason || null,
        };
        if (operation === 'adjust') {
            body.mode = form.elements.mode.value;
        }

        if (submit) {
            submit.disabled = true;
        }
        clearMessage(root);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock/' + encodeURIComponent(itemId) + '/' + operation), {
            method: 'POST',
            body: body,
        }).then(function () {
            resetMovementForm(root);
            showMessage(root, 'success', 'Movimiento registrado.');
            return Promise.all([loadStock(root), loadMovements(root), loadSummary(root), loadValue(root)]);
        }).catch(function (error) {
            handleError(root, error);
        }).then(function () {
            if (submit) {
                submit.disabled = false;
            }
        });
    }

    function saveRule(root, event) {
        event.preventDefault();
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return;
        }
        var form = event.currentTarget;
        var submit = qs('[data-stock-rule-submit]', root);
        var id = form.elements.id.value;
        var body = {
            product_id: form.elements.product_id.value ? Number(form.elements.product_id.value) : null,
            minimum_quantity: Number(form.elements.minimum_quantity.value),
            unit_id: Number(form.elements.unit_id.value),
            status: form.elements.status.value,
        };
        if (!body.product_id) {
            showMessage(root, 'danger', 'Selecciona un producto para la regla.');
            return;
        }
        if (submit) {
            submit.disabled = true;
        }
        clearMessage(root);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-minimum-rules' + (id ? '/' + encodeURIComponent(id) : '')), {
            method: id ? 'PATCH' : 'POST',
            body: body,
        }).then(function () {
            resetRuleForm(root);
            showMessage(root, 'success', id ? 'Regla actualizada.' : 'Regla creada.');
            return Promise.all([loadRules(root), loadLowStock(root)]);
        }).catch(function (error) {
            handleError(root, error);
        }).then(function () {
            if (submit) {
                submit.disabled = false;
            }
        });
    }

    function deleteRule(root, id) {
        if (!state.currentGroupId || !id || !window.confirm('Eliminar esta regla de minimo?')) {
            return;
        }
        clearMessage(root);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-minimum-rules/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Regla eliminada.');
            return Promise.all([loadRules(root), loadLowStock(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function markAlertRead(root, id) {
        if (!state.currentGroupId || !id) {
            return;
        }
        clearMessage(root);
        return window.CCApi.request(endpoint(state.currentGroupId, '/stock-alerts/' + encodeURIComponent(id) + '/read'), {
            method: 'PATCH',
        }).then(function () {
            showMessage(root, 'success', 'Alerta marcada como leida.');
            return loadAlerts(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        var groupSelect = qs('[data-stock-group-select]', root);
        var locationStatus = qs('[data-stock-location-status]', root);
        var locationRefresh = qs('[data-stock-locations-refresh]', root);
        var locationForm = qs('[data-stock-location-form]', root);
        var locationCancel = qs('[data-stock-location-cancel]', root);
        var locationPrev = qs('[data-stock-locations-prev]', root);
        var locationNext = qs('[data-stock-locations-next]', root);
        var stockForm = qs('[data-stock-item-form]', root);
        var stockCancel = qs('[data-stock-item-cancel]', root);
        var stockRefresh = qs('[data-stock-refresh]', root);
        var stockPrev = qs('[data-stock-prev]', root);
        var stockNext = qs('[data-stock-next]', root);
        var stockLocationFilter = qs('[data-stock-filter-location]', root);
        var stockExpiryFilter = qs('[data-stock-filter-expiry]', root);
        var productSearch = qs('[data-stock-product-search]', root);
        var movementForm = qs('[data-stock-movement-form]', root);
        var movementCancel = qs('[data-stock-movement-cancel]', root);
        var movementOperation = qs('[data-stock-movement-operation]', root);
        var movementItemSelect = qs('[data-stock-movement-item-select]', root);
        var movementRefresh = qs('[data-stock-movements-refresh]', root);
        var movementType = qs('[data-stock-movement-filter-type]', root);
        var movementFrom = qs('[data-stock-movement-date-from]', root);
        var movementTo = qs('[data-stock-movement-date-to]', root);
        var movementPrev = qs('[data-stock-movements-prev]', root);
        var movementNext = qs('[data-stock-movements-next]', root);
        var alertStatus = qs('[data-stock-alert-status]', root);
        var alertSeverity = qs('[data-stock-alert-severity]', root);
        var alertsRefresh = qs('[data-stock-alerts-refresh]', root);
        var expiringRefresh = qs('[data-stock-expiring-refresh]', root);
        var lowRefresh = qs('[data-stock-low-refresh]', root);
        var ruleForm = qs('[data-stock-rule-form]', root);
        var ruleCancel = qs('[data-stock-rule-cancel]', root);

        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                state.currentGroupId = groupSelect.value || null;
                state.locationPage = 1;
                state.stockPage = 1;
                state.movementPage = 1;
                resetLocationForm(root);
                resetStockForm(root);
                resetMovementForm(root);
                resetRuleForm(root);
                reloadGroupData(root);
            });
        }
        if (locationStatus) {
            locationStatus.addEventListener('change', function () {
                state.locationPage = 1;
                loadLocations(root);
            });
        }
        if (locationRefresh) {
            locationRefresh.addEventListener('click', function () {
                loadLocations(root);
            });
        }
        if (locationForm) {
            locationForm.addEventListener('submit', function (event) {
                saveLocation(root, event);
            });
        }
        if (locationCancel) {
            locationCancel.addEventListener('click', function () {
                resetLocationForm(root);
                clearMessage(root);
            });
        }
        if (locationPrev) {
            locationPrev.addEventListener('click', function () {
                if (state.locationPage > 1) {
                    state.locationPage -= 1;
                    loadLocations(root);
                }
            });
        }
        if (locationNext) {
            locationNext.addEventListener('click', function () {
                if (state.locationPage < state.locationLastPage) {
                    state.locationPage += 1;
                    loadLocations(root);
                }
            });
        }
        if (stockForm) {
            stockForm.addEventListener('submit', function (event) {
                saveStock(root, event);
            });
        }
        if (stockCancel) {
            stockCancel.addEventListener('click', function () {
                resetStockForm(root);
                clearMessage(root);
            });
        }
        if (stockRefresh) {
            stockRefresh.addEventListener('click', function () {
                loadStock(root);
                loadSummary(root);
                loadValue(root);
            });
        }
        if (stockPrev) {
            stockPrev.addEventListener('click', function () {
                if (state.stockPage > 1) {
                    state.stockPage -= 1;
                    loadStock(root);
                }
            });
        }
        if (stockNext) {
            stockNext.addEventListener('click', function () {
                if (state.stockPage < state.stockLastPage) {
                    state.stockPage += 1;
                    loadStock(root);
                }
            });
        }
        if (stockLocationFilter) {
            stockLocationFilter.addEventListener('change', function () {
                state.stockPage = 1;
                loadStock(root);
            });
        }
        if (stockExpiryFilter) {
            stockExpiryFilter.addEventListener('change', function () {
                state.stockPage = 1;
                loadStock(root);
            });
        }
        if (productSearch) {
            productSearch.addEventListener('input', function () {
                window.clearTimeout(state.productSearchTimer);
                state.productSearchTimer = window.setTimeout(function () {
                    loadProducts(root, productSearch.value.trim());
                }, 250);
            });
        }
        if (movementForm) {
            movementForm.addEventListener('submit', function (event) {
                saveMovement(root, event);
            });
        }
        if (movementCancel) {
            movementCancel.addEventListener('click', function () {
                resetMovementForm(root);
                clearMessage(root);
            });
        }
        if (movementOperation) {
            movementOperation.addEventListener('change', function () {
                updateMovementModeVisibility(root);
            });
        }
        if (movementItemSelect) {
            movementItemSelect.addEventListener('change', function () {
                var itemId = movementItemSelect.value;
                var item = state.stock.filter(function (row) {
                    return String(row.id) === String(itemId);
                })[0] || null;
                if (item) {
                    fillMovementForm(root, item);
                }
            });
        }
        [movementType, movementFrom, movementTo].forEach(function (filter) {
            if (filter) {
                filter.addEventListener('change', function () {
                    state.movementPage = 1;
                    loadMovements(root);
                });
            }
        });
        if (movementRefresh) {
            movementRefresh.addEventListener('click', function () {
                loadMovements(root);
            });
        }
        if (movementPrev) {
            movementPrev.addEventListener('click', function () {
                if (state.movementPage > 1) {
                    state.movementPage -= 1;
                    loadMovements(root);
                }
            });
        }
        if (movementNext) {
            movementNext.addEventListener('click', function () {
                if (state.movementPage < state.movementLastPage) {
                    state.movementPage += 1;
                    loadMovements(root);
                }
            });
        }
        [alertStatus, alertSeverity].forEach(function (filter) {
            if (filter) {
                filter.addEventListener('change', function () {
                    loadAlerts(root);
                });
            }
        });
        if (alertsRefresh) {
            alertsRefresh.addEventListener('click', function () {
                loadAlerts(root);
            });
        }
        if (expiringRefresh) {
            expiringRefresh.addEventListener('click', function () {
                loadExpiring(root);
            });
        }
        if (lowRefresh) {
            lowRefresh.addEventListener('click', function () {
                loadLowStock(root);
            });
        }
        if (ruleForm) {
            ruleForm.addEventListener('submit', function (event) {
                saveRule(root, event);
            });
        }
        if (ruleCancel) {
            ruleCancel.addEventListener('click', function () {
                resetRuleForm(root);
                clearMessage(root);
            });
        }

        root.addEventListener('click', function (event) {
            var locationEdit = event.target.closest('[data-stock-location-edit]');
            var locationDelete = event.target.closest('[data-stock-location-delete]');
            var stockEdit = event.target.closest('[data-stock-edit]');
            var stockDelete = event.target.closest('[data-stock-delete]');
            var stockMovement = event.target.closest('[data-stock-movement-for]');
            var alertRead = event.target.closest('[data-stock-alert-read]');
            var ruleEdit = event.target.closest('[data-stock-rule-edit]');
            var ruleDelete = event.target.closest('[data-stock-rule-delete]');

            if (locationEdit) {
                fillLocationForm(root, getLocation(locationEdit.getAttribute('data-stock-location-edit')));
            }
            if (locationDelete) {
                deleteLocation(root, locationDelete.getAttribute('data-stock-location-delete'));
            }
            if (stockEdit) {
                var itemId = stockEdit.getAttribute('data-stock-edit');
                fillStockForm(root, state.stock.filter(function (item) {
                    return String(item.id) === String(itemId);
                })[0]);
            }
            if (stockDelete) {
                deleteStock(root, stockDelete.getAttribute('data-stock-delete'));
            }
            if (stockMovement) {
                var movementItemId = stockMovement.getAttribute('data-stock-movement-for');
                fillMovementForm(root, state.stock.filter(function (item) {
                    return String(item.id) === String(movementItemId);
                })[0]);
            }
            if (alertRead) {
                markAlertRead(root, alertRead.getAttribute('data-stock-alert-read'));
            }
            if (ruleEdit) {
                var ruleId = ruleEdit.getAttribute('data-stock-rule-edit');
                fillRuleForm(root, state.rules.filter(function (rule) {
                    return String(rule.id) === String(ruleId);
                })[0]);
            }
            if (ruleDelete) {
                deleteRule(root, ruleDelete.getAttribute('data-stock-rule-delete'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-stock-locations]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
        updateMovementModeVisibility(root);
        loadProducts(root, '');
        loadUnits(root);
        loadGroups(root);
    });
})(window, document);
