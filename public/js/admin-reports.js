(function (window, document) {
    'use strict';

    var state = {
        activeReport: 'users-active',
        dateFrom: '',
        dateTo: '',
        loading: false,
        data: null,
    };

    var REPORTS = [
        { key: 'users-active',            label: 'Usuarios activos',        icon: '👤' },
        { key: 'products-pending-review',  label: 'Productos por revisar',   icon: '📦' },
        { key: 'recipes-pending-review',   label: 'Recetas por revisar',     icon: '🍳' },
        { key: 'scraping-errors',          label: 'Errores scraping',        icon: '⚠' },
        { key: 'price-variations',         label: 'Variación de precios',    icon: '📈' },
        { key: 'most-used-recipes',        label: 'Recetas más usadas',      icon: '🏆' },
        { key: 'most-searched-products',   label: 'Productos más buscados',  icon: '🔍' },
        { key: 'supermarket-price-status', label: 'Estado precios supermercado', icon: '🏪' },
    ];

    var REPORT_COLS = {
        'users-active': [
            { key: 'user_id',       label: 'ID' },
            { key: 'name',          label: 'Nombre' },
            { key: 'email',         label: 'Email' },
            { key: 'role',          label: 'Rol' },
            { key: 'last_login_at', label: 'Último acceso' },
            { key: 'actions_count', label: 'Acciones',   fmt: 'int' },
            { key: 'status',        label: 'Estado' },
        ],
        'products-pending-review': [
            { key: 'product_id',  label: 'ID' },
            { key: 'name',        label: 'Nombre' },
            { key: 'brand',       label: 'Marca' },
            { key: 'category',    label: 'Categoría' },
            { key: 'source',      label: 'Origen' },
            { key: 'created_at',  label: 'Creado' },
            { key: 'submitted_by',label: 'Enviado por' },
        ],
        'recipes-pending-review': [
            { key: 'recipe_id',   label: 'ID' },
            { key: 'title',       label: 'Título' },
            { key: 'category',    label: 'Categoría' },
            { key: 'source',      label: 'Origen' },
            { key: 'created_at',  label: 'Creado' },
            { key: 'submitted_by',label: 'Enviado por' },
        ],
        'scraping-errors': [
            { key: 'supermarket', label: 'Supermercado' },
            { key: 'error_type',  label: 'Tipo error' },
            { key: 'message',     label: 'Mensaje' },
            { key: 'url',         label: 'URL' },
            { key: 'occurred_at', label: 'Ocurrió' },
            { key: 'retries',     label: 'Reintentos', fmt: 'int' },
        ],
        'price-variations': [
            { key: 'product_name',    label: 'Producto' },
            { key: 'supermarket',     label: 'Supermercado' },
            { key: 'price_before',    label: 'Precio anterior', fmt: 'money' },
            { key: 'price_after',     label: 'Precio actual',   fmt: 'money' },
            { key: 'variation_pct',   label: 'Variación %',     fmt: 'pct' },
            { key: 'detected_at',     label: 'Detectado' },
        ],
        'most-used-recipes': [
            { key: 'rank',         label: '#',            fmt: 'int' },
            { key: 'recipe_name',  label: 'Receta' },
            { key: 'category',     label: 'Categoría' },
            { key: 'times_cooked', label: 'Veces cocinada', fmt: 'int' },
            { key: 'unique_users', label: 'Usuarios únicos', fmt: 'int' },
            { key: 'avg_rating',   label: 'Rating prom.',   fmt: 'num' },
        ],
        'most-searched-products': [
            { key: 'rank',          label: '#',              fmt: 'int' },
            { key: 'product_name',  label: 'Producto' },
            { key: 'category',      label: 'Categoría' },
            { key: 'search_count',  label: 'Búsquedas',      fmt: 'int' },
            { key: 'unique_users',  label: 'Usuarios únicos', fmt: 'int' },
            { key: 'found_rate_pct',label: '% encontrado',    fmt: 'pct' },
        ],
        'supermarket-price-status': [
            { key: 'supermarket',     label: 'Supermercado' },
            { key: 'branch',          label: 'Sucursal' },
            { key: 'products_total',  label: 'Productos',      fmt: 'int' },
            { key: 'prices_ok',       label: 'Precios ok',     fmt: 'int' },
            { key: 'prices_outdated', label: 'Desactualizados', fmt: 'int' },
            { key: 'last_scraped_at', label: 'Último scraping' },
            { key: 'freshness_pct',   label: 'Frescura %',     fmt: 'pct' },
        ],
    };

    function qs(sel, root) { return (root || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v == null) { return ''; }
        return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmt(n)      { return Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fmtInt(n)   { return Number(n).toLocaleString('es-AR'); }
    function fmtMoney(n) { return '$ ' + fmt(n); }
    function fmtPct(n)   { return fmt(n) + ' %'; }

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

    function showMsg(root, type, msg) {
        var el = qs('[data-admin-report-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearMsg(root) {
        var el = qs('[data-admin-report-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function renderTabs(root) {
        var el = qs('[data-admin-report-tabs]', root);
        if (!el) { return; }
        el.innerHTML = REPORTS.map(function (r) {
            var active = r.key === state.activeReport;
            return '<button type="button" data-admin-report-tab="' + escapeHtml(r.key) + '" style="' +
                'border:1px solid ' + (active ? 'rgba(4,172,133,.8)' : '#dde3e8') + ';' +
                'background:' + (active ? 'rgba(4,172,133,.8)' : '#fff') + ';' +
                'color:' + (active ? '#fff' : '#24252a') + ';' +
                'border-radius:50px;padding:7px 13px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;margin-bottom:4px">' +
                escapeHtml(r.icon) + ' ' + escapeHtml(r.label) +
                '</button>';
        }).join('');
    }

    function renderMetrics(root) {
        var el = qs('[data-admin-report-metrics]', root);
        if (!el || !state.data) { if (el) { el.innerHTML = ''; } return; }

        var summary = state.data.summary || state.data.meta_summary || {};
        var keys = Object.keys(summary).filter(function (k) {
            return typeof summary[k] === 'number' || typeof summary[k] === 'string';
        }).slice(0, 4);

        if (!keys.length) { el.innerHTML = ''; return; }

        el.innerHTML = '<div class="metric-row" style="margin-bottom:14px">' +
            keys.map(function (k) {
                var val = summary[k];
                var disp = typeof val === 'number'
                    ? (Number.isInteger(val) ? fmtInt(val) : fmt(val))
                    : escapeHtml(String(val));
                var label = k.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
                return '<div class="metric"><strong>' + disp + '</strong><span>' + escapeHtml(label) + '</span></div>';
            }).join('') +
            '</div>';
    }

    function cellStyle(col, raw) {
        if (col.fmt === 'money' && typeof raw === 'number' && raw < 0) { return 'color:#b33a3a'; }
        if (col.fmt === 'pct'   && typeof raw === 'number') {
            if (raw >= 90) { return 'color:#b33a3a;font-weight:700'; }
            if (raw >= 70) { return 'color:#b35c00;font-weight:700'; }
        }
        return '';
    }

    function renderContent(root) {
        var contentEl = qs('[data-admin-report-content]', root);
        if (!contentEl) { return; }

        if (state.loading) {
            contentEl.innerHTML = '<p style="font-size:13px;color:#66746b;text-align:center;padding:32px 0">Cargando reporte...</p>';
            return;
        }
        if (!state.data) {
            contentEl.innerHTML = '<p style="font-size:13px;color:#66746b;text-align:center;padding:32px 0">Seleccioná un reporte y hacé clic en "Generar" para ver los datos.</p>';
            return;
        }

        var rep  = REPORTS.find(function (r) { return r.key === state.activeReport; });
        var cols = REPORT_COLS[state.activeReport] || [];
        var rows = state.data.data || state.data.items || state.data.rows || [];

        if (!cols.length && rows.length) {
            cols = Object.keys(rows[0]).slice(0, 8).map(function (k) {
                return { key: k, label: k.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }) };
            });
        }

        var tableHtml;
        if (!rows.length) {
            tableHtml = '<p style="font-size:13px;color:#66746b;text-align:center;padding:16px 0">No hay datos para el período seleccionado.</p>';
        } else {
            tableHtml = '<div style="overflow-x:auto">' +
                '<table class="web-table">' +
                '<thead><tr>' + cols.map(function (c) { return '<th>' + escapeHtml(c.label) + '</th>'; }).join('') + '</tr></thead>' +
                '<tbody>' +
                rows.map(function (row) {
                    return '<tr>' + cols.map(function (c) {
                        var raw = row[c.key];
                        var style = cellStyle(c, raw);
                        return '<td' + (style ? ' style="' + style + '"' : '') + '>' + fmtCell(raw, c.fmt) + '</td>';
                    }).join('') + '</tr>';
                }).join('') +
                '</tbody></table>' +
                '<p style="font-size:11px;color:#66746b;margin:6px 0 0">' + fmtInt(rows.length) + ' registros</p>' +
                '</div>';
        }

        contentEl.innerHTML =
            '<div data-admin-report-metrics></div>' +
            '<div class="panel" style="padding:14px">' +
            '<h3 style="font-size:14px;font-weight:900;margin:0 0 10px">' + (rep ? rep.icon + ' ' + escapeHtml(rep.label) : '') + '</h3>' +
            tableHtml +
            '</div>';

        renderMetrics(root);
    }

    function loadReport(root) {
        state.loading = true;
        state.data    = null;
        clearMsg(root);
        renderContent(root);

        var params = new URLSearchParams();
        if (state.dateFrom) { params.set('from', state.dateFrom); }
        if (state.dateTo)   { params.set('to',   state.dateTo); }
        var qs2 = params.toString() ? '?' + params.toString() : '';

        window.CCApi.request(endpoint('/api/v1/admin/reports/' + encodeURIComponent(state.activeReport) + qs2))
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

    function bind(root) {
        var fromInput = qs('[data-admin-report-from]', root);
        if (fromInput) { fromInput.addEventListener('change', function () { state.dateFrom = fromInput.value; }); }

        var toInput = qs('[data-admin-report-to]', root);
        if (toInput) { toInput.addEventListener('change', function () { state.dateTo = toInput.value; }); }

        var generateBtn = qs('[data-admin-report-generate]', root);
        if (generateBtn) { generateBtn.addEventListener('click', function () { loadReport(root); }); }

        var tabsEl = qs('[data-admin-report-tabs]', root);
        if (tabsEl) {
            tabsEl.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-admin-report-tab]');
                if (!btn) { return; }
                state.activeReport = btn.getAttribute('data-admin-report-tab');
                state.data = null;
                renderTabs(root);
                renderContent(root);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-reports]');
        if (!root) { return; }
        bind(root);
        renderTabs(root);
        renderContent(root);
    });
})(window, document);
