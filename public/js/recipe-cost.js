(function (window, document) {
    'use strict';

    var state = {
        el: null,
        recipeId: null,
        canRecalculate: false,
        costData: null,
        loading: false,
        itemsVisible: false,
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined) { return ''; }
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    var STATUS_META = {
        complete:       { label: 'Completo',          bg: '#e7f7f2', color: '#04ac85' },
        partial:        { label: 'Parcial',           bg: '#fff8e1', color: '#b88a00' },
        no_ingredients: { label: 'Sin ingredientes',  bg: '#f7e7e7', color: '#b33a3a' },
        no_prices:      { label: 'Sin precios',       bg: '#f0f0f0', color: '#697681' },
    };

    var SOURCE_LABELS = { supermarket: 'supermercado', stock: 'stock' };

    // ─── Formatting ─────────────────────────────────────────────────────────────

    function fmtCost(v, currency) {
        if (v === null || v === undefined) { return '—'; }
        var n = parseFloat(v);
        if (isNaN(n)) { return '—'; }
        var parts = n.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        var formatted = parts[0] + ',' + parts[1];
        if (currency === 'MIXED') { return formatted + ' *'; }
        if (currency === 'ARS')   { return '$ ' + formatted; }
        if (currency === 'USD')   { return 'US$ ' + formatted; }
        return formatted;
    }

    function fmtItemCost(item, currency) {
        var cost = item.item_cost !== undefined ? item.item_cost : item.cost;
        if (cost === null || cost === undefined) { return '<span class="muted">sin precio</span>'; }
        return fmtCost(cost, currency);
    }

    function fmtQty(q) {
        var n = parseFloat(q);
        if (isNaN(n)) { return ''; }
        return (n % 1 === 0 ? String(parseInt(n, 10)) : String(parseFloat(n.toFixed(2))));
    }

    // ─── Render ─────────────────────────────────────────────────────────────────

    function renderContent() {
        var d = state.costData;
        var currency = d ? (d.currency || null) : null;

        var recalcBtn = state.canRecalculate
            ? '<button type="button" class="btn-ghost btn-sm" data-cost-recalc>↺ Recalcular</button>'
            : '';

        var header =
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8">' +
            '<h3 style="margin:0;font-size:14px;font-weight:900">Costo estimado</h3>' +
            recalcBtn +
            '</div>';

        var msgDiv = '<div data-cost-msg style="display:none;font-size:12px;padding:5px 9px;border-radius:4px;margin-top:6px"></div>';

        if (!d) {
            return header +
                '<p class="muted" style="font-size:13px;margin:6px 0">Sin datos de costo calculados.</p>' +
                msgDiv;
        }

        var meta = STATUS_META[d.status] || STATUS_META.no_prices;
        var badge =
            '<span style="background:' + meta.bg + ';color:' + meta.color + ';border-radius:50px;padding:2px 8px;font-size:11px;font-weight:900">' +
            escapeHtml(meta.label) + '</span>';
        var dateStr = d.calculated_at || d.snapshot_at || null;
        var dateSpan = dateStr
            ? '<span class="muted" style="font-size:11px;margin-left:6px">' + escapeHtml(String(dateStr).substring(0, 10)) + '</span>'
            : '';
        var statusRow = '<div style="margin:6px 0 8px;display:flex;align-items:center">' + badge + dateSpan + '</div>';

        if (d.status === 'no_ingredients') {
            return header + statusRow +
                '<p class="muted" style="font-size:12px;margin:0">La receta no tiene ingredientes para calcular el costo.</p>' +
                msgDiv;
        }

        var totalCost = d.estimated_total_cost !== undefined ? d.estimated_total_cost : d.total_cost;
        var perServ   = d.estimated_cost_per_serving !== undefined ? d.estimated_cost_per_serving : d.cost_per_serving;

        var costCards;
        if (d.status === 'no_prices') {
            costCards = '<p class="muted" style="font-size:12px;margin:0 0 8px">No hay precios disponibles para calcular el costo.</p>';
        } else {
            costCards =
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">' +
                '<div style="background:#f9fafb;border-radius:6px;padding:9px 10px;text-align:center">' +
                '<div class="muted" style="font-size:11px;margin-bottom:3px">Total receta</div>' +
                '<strong style="font-size:14px">' + fmtCost(totalCost, currency) + '</strong>' +
                '</div>' +
                '<div style="background:#f9fafb;border-radius:6px;padding:9px 10px;text-align:center">' +
                '<div class="muted" style="font-size:11px;margin-bottom:3px">Por porción</div>' +
                '<strong style="font-size:14px">' + fmtCost(perServ, currency) + '</strong>' +
                '</div>' +
                '</div>';
            if (currency === 'MIXED') {
                costCards += '<p class="muted" style="font-size:11px;margin:0 0 8px">* Los ingredientes tienen diferentes monedas.</p>';
            }
        }

        // Items breakdown
        var items = d.items || [];
        var itemsHtml = '';
        if (items.length) {
            var toggleTxt = state.itemsVisible ? '▲ Ocultar desglose' : '▼ Desglose por ingrediente';
            var toggleBtn = '<button type="button" class="btn-ghost btn-sm" data-cost-toggle style="width:100%;text-align:left;font-size:12px;margin-bottom:2px">' + toggleTxt + '</button>';

            var itemsList = '';
            if (state.itemsVisible) {
                itemsList = '<div style="margin-top:4px">' +
                    items.map(function (item) {
                        var ingName  = escapeHtml(item.ingredient_name || '-');
                        var qty      = item.quantity ? fmtQty(item.quantity) + ' ' : '';
                        var unit     = escapeHtml(item.unit_name || '');
                        var src      = item.price_source ? SOURCE_LABELS[item.price_source] || item.price_source : null;
                        var srcSpan  = src ? ' <span class="muted" style="font-size:10px">(' + escapeHtml(src) + ')</span>' : '';
                        var hasPrc   = item.has_price !== undefined ? item.has_price : (item.item_cost !== null && item.item_cost !== undefined);
                        var rowColor = hasPrc ? '' : 'color:#b33a3a;';
                        return '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:6px;padding:4px 0;border-bottom:1px solid #f5f5f5;font-size:12px;' + rowColor + '">' +
                            '<span>' + qty + unit + ' ' + ingName + srcSpan + '</span>' +
                            '<span style="white-space:nowrap">' + fmtItemCost(item, currency) + '</span>' +
                            '</div>';
                    }).join('') +
                    '</div>';
            }
            itemsHtml = toggleBtn + itemsList;
        }

        return header + statusRow + costCards + itemsHtml + msgDiv;
    }

    function render() {
        if (!state.el) { return; }

        if (state.loading) {
            state.el.innerHTML =
                '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8;color:#697681;font-size:13px">Calculando costo...</div>';
            return;
        }

        state.el.innerHTML = renderContent();
        bindEvents(state.el);
    }

    // ─── Feedback ───────────────────────────────────────────────────────────────

    function showMsg(type, text) {
        var el = qs('[data-cost-msg]', state.el);
        if (!el) { return; }
        el.textContent = text;
        el.style.display = 'block';
        el.style.background = type === 'ok' ? '#e7f7f2' : '#f7e7e7';
        el.style.color      = type === 'ok' ? '#04ac85' : '#b33a3a';
        if (type === 'ok') { setTimeout(function () { if (el) { el.style.display = 'none'; } }, 4000); }
    }

    function extractMsg(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message) || 'Error inesperado.';
    }

    // ─── API calls ──────────────────────────────────────────────────────────────

    function fetchCost() {
        state.loading = true;
        render();

        window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/cost'))
            .then(function (res) {
                state.costData = res.data || null;
                state.loading = false;
                render();
            })
            .catch(function (err) {
                state.costData = null;
                state.loading = false;
                render();
                var status = err && err.status;
                if (status && status !== 404 && status !== 403) {
                    showMsg('err', extractMsg(err));
                }
            });
    }

    function recalculate() {
        var btn = qs('[data-cost-recalc]', state.el);
        if (btn) { btn.disabled = true; btn.textContent = 'Calculando...'; }

        window.CCApi.request(endpoint('/admin/recipes/' + state.recipeId + '/recalculate-cost'), { method: 'POST' })
            .then(function () {
                return window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/cost'));
            })
            .then(function (res) {
                state.costData = res.data || null;
                render();
                showMsg('ok', 'Costo recalculado correctamente.');
            })
            .catch(function (err) {
                render();
                showMsg('err', extractMsg(err));
            });
    }

    // ─── Events ─────────────────────────────────────────────────────────────────

    function bindEvents(el) {
        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-cost-recalc]'))   { recalculate(); return; }
            if (e.target.closest('[data-cost-toggle]')) {
                state.itemsVisible = !state.itemsVisible;
                render();
                return;
            }
        });
    }

    // ─── Public API ─────────────────────────────────────────────────────────────

    function mount(containerEl, recipeId, canRecalculate) {
        state.el             = containerEl;
        state.recipeId       = recipeId;
        state.canRecalculate = !!canRecalculate;
        state.costData       = null;
        state.loading        = false;
        state.itemsVisible   = false;

        fetchCost();
    }

    window.RecipeCost = { mount: mount };

})(window, document);
