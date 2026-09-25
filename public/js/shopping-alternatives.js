(function (window, document) {
    'use strict';

    var REASON_LABELS = {
        cheaper: 'Más barato', equivalent: 'Equivalente', healthier: 'Más saludable',
        same_ingredient: 'Mismo ingrediente', preferred_brand: 'Marca preferida',
    };

    function escapeHtml(value) {
        return (value === null || value === undefined ? '' : String(value))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function number(value) {
        if (value === null || value === undefined || value === '') return null;
        var parsed = Number(value);
        return isFinite(parsed) ? parsed : null;
    }

    function money(value) {
        var amount = number(value);
        return amount === null ? '' : '$' + amount.toFixed(2);
    }

    function offerId(alt) {
        var id = number(alt.supermarket_product_id);
        return id !== null && id > 0 && Math.floor(id) === id ? id : null;
    }

    function canSelect(item, options) {
        return options.canSelect !== false && item.can_select !== false
            && ['completed', 'cancelled'].indexOf(item.list_status) === -1
            && (!item.status || item.status === 'pending');
    }

    function renderAlternative(alt, item, state, options) {
        var product = alt.product || {};
        var unit = alt.purchase_unit || (item.unit && Number(item.unit.id) === Number(alt.purchase_unit_id) ? item.unit : null);
        var unitLabel = unit ? (unit.symbol || unit.code || 'unidades') : 'unidades';
        var quantity = number(alt.purchase_quantity);
        var size = number(product.net_quantity);
        var contentUnit = product.package_unit;
        var details = quantity !== null ? escapeHtml(quantity + ' ' + unitLabel) : '';
        if (money(alt.price)) details += ' · ' + escapeHtml(money(alt.price)) + ' por ' + escapeHtml(unitLabel);
        if (money(alt.estimated_subtotal)) details += ' · Total ' + escapeHtml(money(alt.estimated_subtotal));
        if (size !== null && contentUnit && unit && (unit.code === 'package' || unit.symbol === 'paq')) {
            details += ' · ' + escapeHtml(size + ' ' + (contentUnit.symbol || contentUnit.code) + ' por paquete');
        }
        var selected = !!alt.is_selected;
        var id = offerId(alt);
        return '<div style="padding:8px 10px;border-bottom:1px solid #f0f0f0">' +
            '<strong>' + escapeHtml(product.name || ('Producto #' + product.id)) + '</strong>' +
            (selected ? ' <span>(Seleccionado)</span>' : '') +
            (details ? '<p style="margin:4px 0;font-size:12px">' + details + '</p>' : '') +
            (alt.reason && !selected ? '<span style="font-size:12px;margin-right:8px">' + escapeHtml(REASON_LABELS[alt.reason] || alt.reason) + '</span>' : '') +
            (!selected && id !== null && canSelect(item, options)
                ? '<button type="button" class="btn-secondary-web btn-sm" data-alt-select-item="' + escapeHtml(item.item_id) +
                  '" data-alt-select-offer="' + id + '"' + (state.selecting ? ' disabled' : '') + '>Seleccionar</button>'
                : '') +
            '</div>';
    }

    function renderItemRow(item, state, options) {
        var alts = item.alternatives || [];
        if (!alts.length) return '';
        var open = !!state.expanded[item.item_id];
        var name = item.item_name || (item.product && item.product.name) || (item.ingredient && item.ingredient.name) || ('Item #' + item.item_id);
        return '<div style="border:1px solid #dde6df;border-radius:6px;margin-bottom:8px;overflow:hidden">' +
            '<button type="button" class="btn-secondary-web" style="width:100%;text-align:left" data-alt-toggle="' + escapeHtml(item.item_id) + '">' +
            escapeHtml(name) + ' · ' + alts.length + ' alternativa' + (alts.length === 1 ? '' : 's') + ' ' + (open ? '▲' : '▼') + '</button>' +
            (open ? '<div>' + alts.map(function (alt) { return renderAlternative(alt, item, state, options); }).join('') + '</div>' : '') +
            '</div>';
    }

    window.ShoppingAlternatives = {
        mount: function (containerEl, groupId, listId, options) {
            if (!containerEl || !groupId || !listId) return;
            options = options || {};
            if (containerEl._shoppingAlternativesDispose) containerEl._shoppingAlternativesDispose();
            var state = { data: null, loading: false, selecting: false, expanded: {}, error: null, notice: null, disposed: false };

            function active() {
                return !state.disposed && containerEl.isConnected;
            }

            function basePath() {
                return '/api/v1/family-groups/' + encodeURIComponent(groupId) + '/shopping-lists/' + encodeURIComponent(listId);
            }

            function errMsg(error) {
                return (error && error.payload && error.payload.error && error.payload.error.message)
                    || (error && error.message) || 'No se pudo completar la operación.';
            }

            function render() {
                if (!active()) return;
                var header = '<div style="margin-top:14px;padding-top:14px;border-top:1px solid #dde6df">' +
                    '<h4 style="margin:0 0 8px">Alternativas de compra</h4>' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-alt-load' + (state.loading || state.selecting ? ' disabled' : '') + '>' +
                    (state.loading ? 'Cargando...' : (state.data ? 'Actualizar' : 'Ver alternativas')) + '</button>';
                var message = state.error ? '<p role="alert" style="color:#b33a3a">' + escapeHtml(state.error) + '</p>' : '';
                if (state.notice) message += '<p role="status">' + escapeHtml(state.notice) + '</p>';
                var items = (state.data || []).filter(function (item) { return (item.alternatives || []).length; });
                containerEl.innerHTML = header + message + (state.data
                    ? (items.length ? items.map(function (item) { return renderItemRow(item, state, options); }).join('')
                        : '<p>No hay alternativas disponibles para los items de esta lista.</p>')
                    : '<p>Sugerencias de presentaciones y productos equivalentes con precio disponible.</p>') + '</div>';
            }

            function load() {
                if (!active() || state.loading || state.selecting) return Promise.resolve();
                state.loading = true; state.error = null; state.notice = null; render();
                return window.CCApi.request(basePath() + '/alternatives').then(function (response) {
                    if (!active()) return;
                    state.data = Array.isArray(response.data) ? response.data : [];
                    state.loading = false; render();
                }).catch(function (error) {
                    if (!active()) return;
                    state.loading = false; state.error = errMsg(error); render();
                });
            }

            function selectAlternative(itemId, selectedOfferId) {
                if (!active() || state.loading || state.selecting) return;
                var item = (state.data || []).find(function (candidate) { return String(candidate.item_id) === String(itemId); });
                var alt = item && (item.alternatives || []).find(function (candidate) { return String(offerId(candidate)) === String(selectedOfferId); });
                if (!item || !alt || offerId(alt) === null || !canSelect(item, options)) return;
                state.selecting = true; state.error = null; state.notice = null; render();
                window.CCApi.request(basePath() + '/items/' + encodeURIComponent(itemId) + '/select-alternative', {
                    method: 'POST', body: { supermarket_product_id: offerId(alt) },
                }).then(function (response) {
                    if (!active()) return;
                    var updated = response.data;
                    alt.is_selected = true;
                    if (updated) {
                        alt.price = updated.estimated_price;
                        alt.purchase_quantity = updated.quantity;
                        alt.purchase_unit = updated.unit;
                        alt.estimated_subtotal = updated.estimated_subtotal;
                    }
                    state.notice = 'Alternativa seleccionada.'; render();
                    if (options.onSelected) {
                        return Promise.resolve().then(function () {
                            return options.onSelected(updated);
                        }).then(function () {
                            if (!active()) return;
                            state.selecting = false; render();
                        }).catch(function () {
                            if (!active()) return;
                            state.selecting = false;
                            state.error = 'La alternativa se seleccionó, pero no pudimos actualizar el detalle. Volvé a cargar la lista.';
                            render();
                        });
                    }
                    state.selecting = false;
                    return load();
                }).catch(function (error) {
                    if (!active()) return;
                    state.selecting = false; state.error = errMsg(error); render();
                });
            }

            function onClick(event) {
                var loadBtn = event.target.closest('[data-alt-load]');
                var toggle = event.target.closest('[data-alt-toggle]');
                var select = event.target.closest('[data-alt-select-item]');
                if (loadBtn) { load(); return; }
                if (toggle) {
                    var id = toggle.getAttribute('data-alt-toggle');
                    state.expanded[id] = !state.expanded[id]; render(); return;
                }
                if (select) selectAlternative(select.getAttribute('data-alt-select-item'), select.getAttribute('data-alt-select-offer'));
            }

            function dispose() {
                state.disposed = true;
                containerEl.removeEventListener('click', onClick);
            }
            containerEl._shoppingAlternativesDispose = dispose;
            containerEl.addEventListener('click', onClick);
            if (window.CCPage && window.CCPage.onDispose) window.CCPage.onDispose(dispose);
            render();
        },
    };
})(window, document);
