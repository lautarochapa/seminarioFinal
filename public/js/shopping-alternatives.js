(function (window, document) {
    'use strict';

    var REASON_LABELS = {
        cheaper: 'Más barato',
        healthier: 'Más saludable',
        same_ingredient: 'Mismo ingrediente',
        preferred_brand: 'Marca preferida',
    };

    var REASON_STYLES = {
        cheaper: 'background:#e7f7e7;color:#2a7a2a',
        healthier: 'background:#e7f3ff;color:#1a5fb4',
        same_ingredient: 'background:#f0f0f0;color:#555',
        preferred_brand: 'background:#fff3e0;color:#b35c00',
    };

    function escapeHtml(v) {
        return (v === null || v === undefined ? '' : String(v))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function reasonBadge(reason) {
        var label = REASON_LABELS[reason] || reason;
        var style = REASON_STYLES[reason] || 'background:#eee;color:#444';
        return '<span style="' + style + ';border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;margin-right:3px">' + escapeHtml(label) + '</span>';
    }

    function priceDiffHtml(diff) {
        if (diff === null || diff === undefined) { return ''; }
        var num = parseFloat(diff);
        if (isNaN(num)) { return ''; }
        var color = num < 0 ? '#2a7a2a' : num > 0 ? '#b33a3a' : '#555';
        var sign = num > 0 ? '+' : '';
        return '<span style="color:' + color + ';font-weight:700;font-size:12px;white-space:nowrap">' + sign + escapeHtml(num.toFixed(2)) + '</span>';
    }

    function renderAlternative(alt, itemId) {
        var productName = alt.product
            ? (alt.product.name || ('#' + alt.product.id))
            : ('#' + (alt.product_id || '?'));
        var reasons = Array.isArray(alt.reasons) ? alt.reasons : (alt.reason ? [alt.reason] : []);
        var isSelected = !!alt.is_selected;
        var selectedStyle = isSelected ? 'background:#e7f7f2;border-left:3px solid #04ac85;' : '';
        return '<div style="' + selectedStyle + 'padding:8px 10px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:8px;flex-wrap:wrap">' +
            '<span style="flex:1;min-width:120px;font-size:13px;font-weight:' + (isSelected ? '700' : '400') + '">' +
            escapeHtml(productName) +
            (isSelected ? ' <span style="color:#04ac85;font-size:11px">(Seleccionado)</span>' : '') +
            '</span>' +
            (reasons.length ? '<span>' + reasons.map(reasonBadge).join('') + '</span>' : '') +
            (alt.estimated_price !== null && alt.estimated_price !== undefined
                ? '<span style="font-size:12px;color:#555">$' + escapeHtml(parseFloat(alt.estimated_price).toFixed(2)) + '</span>'
                : '') +
            (alt.price_diff !== null && alt.price_diff !== undefined ? priceDiffHtml(alt.price_diff) : '') +
            (!isSelected
                ? '<button type="button" style="background:#04ac85;color:#fff;border:0;border-radius:4px;padding:4px 10px;font-size:12px;cursor:pointer;white-space:nowrap"' +
                  ' data-alt-select-item="' + escapeHtml(String(itemId)) + '"' +
                  ' data-alt-select-product="' + escapeHtml(String(alt.product_id)) + '">' +
                  'Seleccionar</button>'
                : '') +
            '</div>';
    }

    function renderItemRow(itemData, expanded) {
        var itemId = itemData.item_id;
        var alts = itemData.alternatives || [];
        if (!alts.length) { return ''; }
        var isOpen = !!expanded[itemId];
        return '<div style="border:1px solid #dde6df;border-radius:6px;margin-bottom:8px;overflow:hidden">' +
            '<button type="button"' +
            ' style="width:100%;background:#fafdfb;border:0;border-bottom:' + (isOpen ? '1px solid #dde6df' : '0') + ';text-align:left;padding:10px 12px;cursor:pointer;display:flex;justify-content:space-between;align-items:center"' +
            ' data-alt-toggle="' + escapeHtml(String(itemId)) + '">' +
            '<span style="font-size:13px;font-weight:700">' + escapeHtml(itemData.item_name || ('Item #' + itemId)) + '</span>' +
            '<span style="font-size:12px;color:#66746b">' + alts.length + ' alternativa' + (alts.length !== 1 ? 's' : '') + ' ' + (isOpen ? '▲' : '▼') + '</span>' +
            '</button>' +
            (isOpen ? '<div>' + alts.map(function (alt) { return renderAlternative(alt, itemId); }).join('') + '</div>' : '') +
            '</div>';
    }

    window.ShoppingAlternatives = {
        mount: function (containerEl, groupId, listId) {
            if (!containerEl || !groupId || !listId) { return; }

            var state = {
                groupId: groupId,
                listId: listId,
                data: null,
                loading: false,
                expanded: {},
            };

            function basePath() {
                return '/api/v1/family-groups/' + encodeURIComponent(state.groupId) +
                    '/shopping-lists/' + encodeURIComponent(state.listId);
            }

            function errMsg(err) {
                return (err && err.payload && err.payload.error && err.payload.error.message)
                    ? err.payload.error.message
                    : (err && err.message) || 'Error inesperado.';
            }

            function render() {
                if (state.loading) {
                    containerEl.innerHTML = '<div style="margin-top:14px;padding-top:14px;border-top:1px solid #dde6df;font-size:13px;color:#66746b">Cargando alternativas...</div>';
                    return;
                }

                var header = '<div style="margin-top:14px;padding-top:14px;border-top:1px solid #dde6df">' +
                    '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">' +
                    '<h4 style="margin:0;font-size:14px;font-weight:900">Alternativas de compra</h4>' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-alt-load>' +
                    (state.data ? 'Actualizar' : 'Ver alternativas') +
                    '</button></div>';

                if (!state.data) {
                    containerEl.innerHTML = header +
                        '<p style="font-size:12px;color:#66746b;margin:0">Sugerencias de productos equivalentes por precio, salud o marca preferida.</p>' +
                        '</div>';
                    return;
                }

                var items = state.data.filter(function (i) { return (i.alternatives || []).length > 0; });
                containerEl.innerHTML = header +
                    (items.length === 0
                        ? '<p style="font-size:13px;color:#66746b;margin:0">No hay alternativas disponibles para los items de esta lista.</p>'
                        : items.map(function (i) { return renderItemRow(i, state.expanded); }).join('')) +
                    '</div>';
            }

            function load() {
                state.loading = true;
                render();
                window.CCApi.request(basePath() + '/alternatives')
                    .then(function (response) {
                        state.data = Array.isArray(response.data) ? response.data : [];
                        state.loading = false;
                        render();
                    })
                    .catch(function (err) {
                        state.loading = false;
                        containerEl.innerHTML = '<div style="margin-top:14px;padding-top:14px;border-top:1px solid #dde6df">' +
                            '<p style="font-size:13px;color:#b33a3a;margin:0">' + escapeHtml(errMsg(err)) +
                            ' <button type="button" class="btn-secondary-web btn-sm" style="margin-left:8px" data-alt-load>Reintentar</button></p></div>';
                    });
            }

            function selectAlternative(itemId, productId) {
                window.CCApi.request(
                    basePath() + '/items/' + encodeURIComponent(itemId) + '/select-alternative',
                    { method: 'POST', body: { product_id: Number(productId) } }
                ).then(function () {
                    if (state.data) {
                        state.data.forEach(function (itemData) {
                            if (String(itemData.item_id) === String(itemId)) {
                                (itemData.alternatives || []).forEach(function (alt) {
                                    alt.is_selected = String(alt.product_id) === String(productId);
                                });
                            }
                        });
                    }
                    render();
                }).catch(function (err) {
                    alert(errMsg(err));
                });
            }

            containerEl.addEventListener('click', function (event) {
                var loadBtn = event.target.closest('[data-alt-load]');
                var toggle = event.target.closest('[data-alt-toggle]');
                var select = event.target.closest('[data-alt-select-item]');

                if (loadBtn) { load(); return; }
                if (toggle) {
                    var id = toggle.getAttribute('data-alt-toggle');
                    state.expanded[id] = !state.expanded[id];
                    render();
                    return;
                }
                if (select) {
                    selectAlternative(
                        select.getAttribute('data-alt-select-item'),
                        select.getAttribute('data-alt-select-product')
                    );
                }
            });

            render();
        },
    };
})(window, document);
