(function (window, document) {
    'use strict';

    var state = {
        el: null,
        recipeId: null,
        canRecalculate: false,
        nutrition: null,
        loading: false,
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

    var NUTRIENTS = [
        { key: 'calories',      label: 'Calorías',      unit: 'kcal' },
        { key: 'protein',       label: 'Proteínas',     unit: 'g'    },
        { key: 'carbohydrates', label: 'Carbohidratos', unit: 'g'    },
        { key: 'fat',           label: 'Grasas',        unit: 'g'    },
        { key: 'sodium',        label: 'Sodio',         unit: 'mg'   },
        { key: 'sugar',         label: 'Azúcar',        unit: 'g'    },
        { key: 'fiber',         label: 'Fibra',         unit: 'g'    },
    ];

    var STATUS_META = {
        complete:        { label: 'Completo',                    bg: '#e7f7f2', color: '#04ac85' },
        partial:         { label: 'Parcial',                     bg: '#fff8e1', color: '#b88a00' },
        no_ingredients:  { label: 'Sin ingredientes con datos',  bg: '#f7e7e7', color: '#b33a3a' },
        pending:         { label: 'Pendiente',                   bg: '#f0f0f0', color: '#697681' },
    };

    // ─── Helpers ────────────────────────────────────────────────────────────────

    function fmtVal(v, unit) {
        if (v === null || v === undefined) { return '<span class="muted">—</span>'; }
        var n = parseFloat(v);
        var s = n % 1 === 0 ? String(parseInt(n, 10)) : parseFloat(n.toFixed(1)).toString();
        return escapeHtml(s + ' ' + unit);
    }

    function extractMsg(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message) || 'Error inesperado.';
    }

    // ─── Render ─────────────────────────────────────────────────────────────────

    function headerHtml() {
        var recalcBtn = state.canRecalculate
            ? '<button type="button" class="btn-ghost btn-sm" data-nutr-recalc>↺ Recalcular</button>'
            : '';
        return '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8">' +
            '<h3 style="margin:0;font-size:14px;font-weight:900">Información nutricional</h3>' +
            recalcBtn +
            '</div>';
    }

    function msgDivHtml() {
        return '<div data-nutr-msg style="display:none;font-size:12px;padding:5px 9px;border-radius:4px;margin-top:6px"></div>';
    }

    function renderContent() {
        var n = state.nutrition;

        if (!n) {
            return headerHtml() +
                '<p class="muted" style="font-size:13px;margin:6px 0">Sin datos nutricionales calculados.</p>' +
                msgDivHtml();
        }

        var meta  = STATUS_META[n.calculation_status] || STATUS_META.pending;
        var badge = '<span style="background:' + meta.bg + ';color:' + meta.color + ';border-radius:50px;padding:2px 8px;font-size:11px;font-weight:900">' +
            escapeHtml(meta.label) + '</span>';
        var dateSpan = n.calculated_at
            ? '<span class="muted" style="font-size:11px;margin-left:6px">' + escapeHtml(n.calculated_at.substring(0, 10)) + '</span>'
            : '';
        var statusRow = '<div style="margin:6px 0 8px;display:flex;align-items:center">' + badge + dateSpan + '</div>';

        if (n.calculation_status === 'no_ingredients') {
            return headerHtml() + statusRow +
                '<p class="muted" style="font-size:12px;margin:0">Los ingredientes de esta receta no tienen datos nutricionales cargados.</p>' +
                msgDivHtml();
        }

        var thead =
            '<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:2px">' +
            '<thead><tr>' +
            '<th style="text-align:left;color:#697681;font-weight:600;padding:3px 0;border-bottom:1px solid #edf2ee"></th>' +
            '<th style="text-align:right;color:#697681;font-weight:600;padding:3px 6px 3px 0;border-bottom:1px solid #edf2ee">Total</th>' +
            '<th style="text-align:right;color:#697681;font-weight:600;padding:3px 0;border-bottom:1px solid #edf2ee">Por porción</th>' +
            '</tr></thead><tbody>';

        var rows = NUTRIENTS.map(function (nutr) {
            return '<tr>' +
                '<td style="padding:5px 0;border-bottom:1px solid #f5f5f5">' + nutr.label + '</td>' +
                '<td style="padding:5px 6px 5px 0;border-bottom:1px solid #f5f5f5;text-align:right">' + fmtVal(n[nutr.key + '_total'], nutr.unit) + '</td>' +
                '<td style="padding:5px 0;border-bottom:1px solid #f5f5f5;text-align:right">' + fmtVal(n[nutr.key + '_per_serving'], nutr.unit) + '</td>' +
                '</tr>';
        }).join('');

        return headerHtml() + statusRow + thead + rows + '</tbody></table>' + msgDivHtml();
    }

    function render() {
        if (!state.el) { return; }

        if (state.loading) {
            state.el.innerHTML =
                '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8;color:#697681;font-size:13px">Calculando nutrición...</div>';
            return;
        }

        state.el.innerHTML = renderContent();
        bindEvents(state.el);
    }

    // ─── Feedback ───────────────────────────────────────────────────────────────

    function showMsg(type, text) {
        var el = qs('[data-nutr-msg]', state.el);
        if (!el) { return; }
        el.textContent = text;
        el.style.display = 'block';
        el.style.background = type === 'ok' ? '#e7f7f2' : '#f7e7e7';
        el.style.color      = type === 'ok' ? '#04ac85' : '#b33a3a';
        if (type === 'ok') { setTimeout(function () { if (el) { el.style.display = 'none'; } }, 4000); }
    }

    // ─── API calls ──────────────────────────────────────────────────────────────

    function fetchNutrition() {
        state.loading = true;
        render();

        window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/nutrition'))
            .then(function (res) {
                state.nutrition = res.data || null;
                state.loading = false;
                render();
            })
            .catch(function (err) {
                state.nutrition = null;
                state.loading = false;
                render();
                var status = err && err.status;
                if (status && status !== 404) {
                    showMsg('err', extractMsg(err));
                }
            });
    }

    function recalculate() {
        var btn = qs('[data-nutr-recalc]', state.el);
        if (btn) { btn.disabled = true; btn.textContent = 'Calculando...'; }

        window.CCApi.request(endpoint('/admin/recipes/' + state.recipeId + '/recalculate-nutrition'), { method: 'POST' })
            .then(function () {
                return window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/nutrition'));
            })
            .then(function (res) {
                state.nutrition = res.data || null;
                render();
                showMsg('ok', 'Nutrición recalculada correctamente.');
            })
            .catch(function (err) {
                render();
                showMsg('err', extractMsg(err));
            });
    }

    // ─── Events ─────────────────────────────────────────────────────────────────

    function bindEvents(el) {
        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-nutr-recalc]')) { recalculate(); }
        });
    }

    // ─── Public API ─────────────────────────────────────────────────────────────

    function mount(containerEl, recipeId, canRecalculate) {
        state.el             = containerEl;
        state.recipeId       = recipeId;
        state.canRecalculate = !!canRecalculate;
        state.nutrition      = null;
        state.loading        = false;

        fetchNutrition();
    }

    window.RecipeNutrition = { mount: mount };

})(window, document);
