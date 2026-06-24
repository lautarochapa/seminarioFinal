(function (window, document) {
    'use strict';

    var state = {
        groups: [],
        currentGroupId: null,
        activeReport: 'stock',
        dateFrom: '',
        dateTo: '',
        loading: false,
        data: null,
    };

    var REPORTS = [
        { key: 'stock',            label: 'Stock',              icon: '📦' },
        { key: 'stock-value',      label: 'Valor stock',        icon: '💲' },
        { key: 'expiring-products',label: 'Por vencer',         icon: '⏰' },
        { key: 'waste',            label: 'Desperdicio',        icon: '🗑' },
        { key: 'purchases',        label: 'Compras',            icon: '🛒' },
        { key: 'budget',           label: 'Presupuesto',        icon: '💰' },
        { key: 'budget-vs-actual', label: 'Presup. vs real',    icon: '📊' },
        { key: 'recipes-cooked',   label: 'Recetas cocinadas',  icon: '🍳' },
        { key: 'nutrition-estimate',label: 'Nutrición estimada',icon: '🥗' },
    ];

    // Column definitions per report key: array of { key, label, fmt? }
    var REPORT_COLS = {
        'stock': [
            { key: 'product_name',   label: 'Producto' },
            { key: 'category',       label: 'Categoría' },
            { key: 'quantity',       label: 'Cantidad',  fmt: 'num' },
            { key: 'unit',           label: 'Unidad' },
            { key: 'location',       label: 'Ubicación' },
            { key: 'expiry_date',    label: 'Vencimiento' },
        ],
        'stock-value': [
            { key: 'category',       label: 'Categoría' },
            { key: 'product_count',  label: 'Productos',    fmt: 'int' },
            { key: 'total_quantity', label: 'Cantidad total',fmt: 'num' },
            { key: 'estimated_value',label: 'Valor estimado',fmt: 'money' },
        ],
        'expiring-products': [
            { key: 'product_name',   label: 'Producto' },
            { key: 'quantity',       label: 'Cantidad',     fmt: 'num' },
            { key: 'unit',           label: 'Unidad' },
            { key: 'expiry_date',    label: 'Vence' },
            { key: 'days_until_expiry', label: 'Días restantes', fmt: 'int' },
            { key: 'location',       label: 'Ubicación' },
        ],
        'waste': [
            { key: 'product_name',   label: 'Producto' },
            { key: 'category',       label: 'Categoría' },
            { key: 'quantity',       label: 'Cantidad',     fmt: 'num' },
            { key: 'unit',           label: 'Unidad' },
            { key: 'estimated_value',label: 'Valor estimado',fmt: 'money' },
            { key: 'reason',         label: 'Motivo' },
            { key: 'date',           label: 'Fecha' },
        ],
        'purchases': [
            { key: 'date',           label: 'Fecha' },
            { key: 'product_name',   label: 'Producto' },
            { key: 'category',       label: 'Categoría' },
            { key: 'quantity',       label: 'Cantidad',     fmt: 'num' },
            { key: 'unit_price',     label: 'Precio unit.', fmt: 'money' },
            { key: 'total',          label: 'Total',        fmt: 'money' },
            { key: 'supermarket',    label: 'Supermercado' },
        ],
        'budget': [
            { key: 'month',          label: 'Mes' },
            { key: 'year',           label: 'Año' },
            { key: 'amount',         label: 'Presupuesto',  fmt: 'money' },
            { key: 'spent',          label: 'Gastado',      fmt: 'money' },
            { key: 'reserved',       label: 'Reservado',    fmt: 'money' },
            { key: 'available',      label: 'Disponible',   fmt: 'money' },
        ],
        'budget-vs-actual': [
            { key: 'category',       label: 'Categoría' },
            { key: 'planned',        label: 'Planificado',  fmt: 'money' },
            { key: 'actual',         label: 'Real',         fmt: 'money' },
            { key: 'difference',     label: 'Diferencia',   fmt: 'money' },
            { key: 'pct_used',       label: '% usado',      fmt: 'pct' },
        ],
        'recipes-cooked': [
            { key: 'recipe_name',    label: 'Receta' },
            { key: 'category',       label: 'Categoría' },
            { key: 'times_cooked',   label: 'Veces cocinada', fmt: 'int' },
            { key: 'last_cooked',    label: 'Última vez' },
            { key: 'avg_servings',   label: 'Porciones prom.', fmt: 'num' },
        ],
        'nutrition-estimate': [
            { key: 'date',           label: 'Fecha' },
            { key: 'meal_type',      label: 'Tipo comida' },
            { key: 'calories',       label: 'Calorías',     fmt: 'num' },
            { key: 'protein_g',      label: 'Proteínas (g)',fmt: 'num' },
            { key: 'carbs_g',        label: 'Carbohidratos (g)', fmt: 'num' },
            { key: 'fat_g',          label: 'Grasas (g)',   fmt: 'num' },
        ],
    };

    function qs(sel, root) { return (root || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v == null) { return ''; }
        return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmt(n) {
        return Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtInt(n) { return Number(n).toLocaleString('es-AR'); }

    function fmtMoney(n) { return '$ ' + fmt(n); }

    function fmtPct(n) { return fmt(n) + ' %'; }

    function fmtCell(value, fmtType) {
        if (value == null || value === '') { return '-'; }
        if (fmtType === 'money') { return fmtMoney(value); }
        if (fmtType === 'num')   { return fmt(value); }
        if (fmtType === 'int')   { return fmtInt(value); }
        if (fmtType === 'pct')   { return fmtPct(value); }
        return escapeHtml(String(value));
    }

    function endpoint(path) { return path; }

    function errMsg(err) {
        if (err && err.message) { return err.message; }
        return 'Ocurrió un error inesperado.';
    }

    function groupPath(extra) {
        return endpoint('/api/v1/family-groups/' + encodeURIComponent(state.currentGroupId) + extra);
    }

    function showMsg(root, type, msg) {
        var el = qs('[data-report-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearMsg(root) {
        var el = qs('[data-report-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function renderTabs(root) {
        var tabsEl = qs('[data-report-tabs]', root);
        if (!tabsEl) { return; }
        tabsEl.innerHTML = REPORTS.map(function (r) {
            var active = r.key === state.activeReport;
            return '<button type="button" data-report-tab="' + escapeHtml(r.key) + '" style="' +
                'border:1px solid ' + (active ? 'rgba(4,172,133,.8)' : '#dde6df') + ';' +
                'background:' + (active ? 'rgba(4,172,133,.8)' : '#fff') + ';' +
                'color:' + (active ? '#fff' : '#24252a') + ';' +
                'border-radius:50px;padding:7px 13px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;margin-bottom:4px">' +
                escapeHtml(r.icon) + ' ' + escapeHtml(r.label) +
                '</button>';
        }).join('');
    }

    function renderMetrics(root, data) {
        var metricsEl = qs('[data-report-metrics]', root);
        if (!metricsEl) { return; }

        var summary = data.summary || data.meta_summary || {};
        var keys = Object.keys(summary).filter(function (k) {
            return typeof summary[k] === 'number' || typeof summary[k] === 'string';
        }).slice(0, 4);

        if (!keys.length) { metricsEl.innerHTML = ''; return; }

        metricsEl.innerHTML = '<div class="metric-row" style="margin-bottom:14px">' +
            keys.map(function (k) {
                var val = summary[k];
                var displayVal = typeof val === 'number' ? (Number.isInteger(val) ? fmtInt(val) : fmt(val)) : escapeHtml(String(val));
                var label = k.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
                return '<div class="metric"><strong>' + displayVal + '</strong><span>' + escapeHtml(label) + '</span></div>';
            }).join('') +
            '</div>';
    }

    function renderTable(root, data) {
        var tableWrap = qs('[data-report-table]', root);
        if (!tableWrap) { return; }

        var cols = REPORT_COLS[state.activeReport] || [];
        var rows = data.data || data.items || data.rows || data.products || data.entries || [];

        // If no predefined cols, infer from first row
        if (!cols.length && rows.length) {
            cols = Object.keys(rows[0]).slice(0, 8).map(function (k) {
                return { key: k, label: k.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }) };
            });
        }

        if (!rows.length) {
            tableWrap.innerHTML = '<p style="font-size:13px;color:#66746b;margin:16px 0;text-align:center">No hay datos para el período seleccionado.</p>';
            return;
        }

        tableWrap.innerHTML = '<div style="overflow-x:auto">' +
            '<table class="web-table">' +
            '<thead><tr>' +
            cols.map(function (c) { return '<th>' + escapeHtml(c.label) + '</th>'; }).join('') +
            '</tr></thead>' +
            '<tbody>' +
            rows.map(function (row) {
                return '<tr>' +
                    cols.map(function (c) {
                        var raw = row[c.key];
                        var color = '';
                        if (c.fmt === 'money' && typeof raw === 'number') {
                            color = raw < 0 ? 'color:#b33a3a' : '';
                        }
                        return '<td style="' + color + '">' + fmtCell(raw, c.fmt) + '</td>';
                    }).join('') +
                    '</tr>';
            }).join('') +
            '</tbody>' +
            '</table>' +
            '<p style="font-size:11px;color:#66746b;margin:6px 0 0">' + fmtInt(rows.length) + ' registros</p>' +
            '</div>';
    }

    function renderContent(root) {
        var contentEl = qs('[data-report-content]', root);
        if (!contentEl) { return; }

        if (state.loading) {
            contentEl.innerHTML = '<p style="font-size:13px;color:#66746b;text-align:center;padding:32px 0">Cargando reporte...</p>';
            return;
        }

        if (!state.data) {
            contentEl.innerHTML = '<p style="font-size:13px;color:#66746b;text-align:center;padding:32px 0">Seleccioná un grupo y hacé clic en "Generar" para ver el reporte.</p>';
            return;
        }

        var rep = REPORTS.find(function (r) { return r.key === state.activeReport; });
        contentEl.innerHTML =
            '<div data-report-metrics></div>' +
            '<div class="panel" style="padding:14px">' +
            '<h3 style="font-size:14px;font-weight:900;margin:0 0 10px">' +
            (rep ? rep.icon + ' ' + rep.label : '') +
            '</h3>' +
            '<div data-report-table></div>' +
            '</div>';

        renderMetrics(root, state.data);
        renderTable(root, state.data);
    }

    function loadReport(root) {
        if (!state.currentGroupId) { showMsg(root, 'warning', 'Seleccioná un grupo familiar.'); return; }

        state.loading = true;
        state.data    = null;
        clearMsg(root);
        renderContent(root);

        var params = new URLSearchParams();
        if (state.dateFrom) { params.set('from', state.dateFrom); }
        if (state.dateTo)   { params.set('to',   state.dateTo); }
        var qs2 = params.toString() ? '?' + params.toString() : '';

        window.CCApi.request(groupPath('/reports/' + encodeURIComponent(state.activeReport) + qs2))
            .then(function (response) {
                state.data    = response;
                state.loading = false;
                renderContent(root);
            })
            .catch(function (err) {
                state.data    = null;
                state.loading = false;
                renderContent(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function loadGroups(root) {
        window.CCApi.request(endpoint('/api/v1/family-groups'))
            .then(function (response) {
                state.groups = response.data || [];
                var sel = qs('[data-report-group]', root);
                if (!sel) { return; }
                sel.innerHTML = '<option value="">Seleccionar grupo...</option>' +
                    state.groups.map(function (g) {
                        return '<option value="' + escapeHtml(String(g.id)) + '">' + escapeHtml(g.name || ('#' + g.id)) + '</option>';
                    }).join('');
                if (state.groups.length === 1) {
                    state.currentGroupId = String(state.groups[0].id);
                    sel.value = state.currentGroupId;
                }
            })
            .catch(function () {});
    }

    function bind(root) {
        var groupSel = qs('[data-report-group]', root);
        if (groupSel) {
            groupSel.addEventListener('change', function () {
                state.currentGroupId = groupSel.value || null;
                state.data = null;
                renderContent(root);
            });
        }

        var fromInput = qs('[data-report-from]', root);
        if (fromInput) {
            fromInput.addEventListener('change', function () { state.dateFrom = fromInput.value; });
        }

        var toInput = qs('[data-report-to]', root);
        if (toInput) {
            toInput.addEventListener('change', function () { state.dateTo = toInput.value; });
        }

        var generateBtn = qs('[data-report-generate]', root);
        if (generateBtn) {
            generateBtn.addEventListener('click', function () { loadReport(root); });
        }

        var tabsEl = qs('[data-report-tabs]', root);
        if (tabsEl) {
            tabsEl.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-report-tab]');
                if (!btn) { return; }
                state.activeReport = btn.getAttribute('data-report-tab');
                state.data = null;
                renderTabs(root);
                renderContent(root);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-reports]');
        if (!root) { return; }
        bind(root);
        renderTabs(root);
        renderContent(root);
        loadGroups(root);
    });
})(window, document);
