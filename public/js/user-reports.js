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
        personalReport: 'body-progress',
        personalData: null,
        personalLoading: false,
        exportLoading: false,
        exportId: null,
        exportPollTimer: null,
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

    var PERSONAL_REPORTS = [
        { key: 'body-progress',       label: 'Evolución corporal', icon: '⚖' },
        { key: 'objectives-progress', label: 'Progreso objetivos', icon: '🎯' },
    ];

    var PERSONAL_COLS = {
        'body-progress': [
            { key: 'date',           label: 'Fecha' },
            { key: 'weight_kg',      label: 'Peso (kg)',        fmt: 'num' },
            { key: 'height_cm',      label: 'Altura (cm)',      fmt: 'num' },
            { key: 'bmi',            label: 'IMC',              fmt: 'num' },
            { key: 'body_fat_pct',   label: 'Grasa corp. (%)',  fmt: 'num' },
            { key: 'muscle_mass_kg', label: 'Masa muscular (kg)', fmt: 'num' },
            { key: 'notes',          label: 'Notas' },
        ],
        'objectives-progress': [
            { key: 'objective_name', label: 'Objetivo' },
            { key: 'category',       label: 'Categoría' },
            { key: 'target_value',   label: 'Meta',             fmt: 'num' },
            { key: 'current_value',  label: 'Actual',           fmt: 'num' },
            { key: 'unit',           label: 'Unidad' },
            { key: 'progress_pct',   label: '% avance',         fmt: 'pct' },
            { key: 'status',         label: 'Estado' },
        ],
    };

    function renderPersonalTabs(root) {
        var el = qs('[data-personal-tabs]', root);
        if (!el) { return; }
        el.innerHTML = PERSONAL_REPORTS.map(function (r) {
            var active = r.key === state.personalReport;
            return '<button type="button" data-personal-tab="' + escapeHtml(r.key) + '" style="' +
                'border:1px solid ' + (active ? 'rgba(4,172,133,.8)' : '#dde6df') + ';' +
                'background:' + (active ? 'rgba(4,172,133,.8)' : '#fff') + ';' +
                'color:' + (active ? '#fff' : '#24252a') + ';' +
                'border-radius:50px;padding:7px 13px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap">' +
                escapeHtml(r.icon) + ' ' + escapeHtml(r.label) +
                '</button>';
        }).join('');
    }

    function renderPersonalContent(root) {
        var contentEl = qs('[data-personal-content]', root);
        if (!contentEl) { return; }

        if (state.personalLoading) {
            contentEl.innerHTML = '<p style="font-size:13px;color:#66746b;text-align:center;padding:24px 0">Cargando reporte...</p>';
            return;
        }
        if (!state.personalData) {
            contentEl.innerHTML = '<p style="font-size:13px;color:#66746b;text-align:center;padding:24px 0">Hacé clic en "Cargar" para ver el reporte.</p>';
            return;
        }

        var rep = PERSONAL_REPORTS.find(function (r) { return r.key === state.personalReport; });
        var cols = PERSONAL_COLS[state.personalReport] || [];
        var rows = state.personalData.data || state.personalData.items || state.personalData.rows || [];
        var summary = state.personalData.summary || state.personalData.meta_summary || {};
        var summaryKeys = Object.keys(summary).filter(function (k) { return typeof summary[k] === 'number'; }).slice(0, 4);

        var metricsHtml = summaryKeys.length
            ? '<div class="metric-row" style="margin-bottom:14px">' +
              summaryKeys.map(function (k) {
                  var val = summary[k];
                  var label = k.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
                  return '<div class="metric"><strong>' + (Number.isInteger(val) ? fmtInt(val) : fmt(val)) + '</strong><span>' + escapeHtml(label) + '</span></div>';
              }).join('') +
              '</div>'
            : '';

        var tableHtml;
        if (!rows.length) {
            tableHtml = '<p style="font-size:13px;color:#66746b;margin:16px 0;text-align:center">No hay datos para el período seleccionado.</p>';
        } else {
            var activeCols = cols.length ? cols : Object.keys(rows[0]).slice(0, 8).map(function (k) {
                return { key: k, label: k.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }) };
            });

            if (state.personalReport === 'objectives-progress') {
                tableHtml = '<div style="overflow-x:auto"><table class="web-table"><thead><tr>' +
                    activeCols.map(function (c) { return '<th>' + escapeHtml(c.label) + '</th>'; }).join('') +
                    '</tr></thead><tbody>' +
                    rows.map(function (row) {
                        return '<tr>' + activeCols.map(function (c) {
                            var raw = row[c.key];
                            var extra = '';
                            if (c.key === 'progress_pct' && raw != null) {
                                var pct = Math.min(100, Math.max(0, Number(raw)));
                                var barColor = pct >= 100 ? '#04ac85' : pct >= 60 ? '#2f80ed' : '#b35c00';
                                extra = '<div style="height:4px;border-radius:2px;background:#f0f0f0;margin-top:3px">' +
                                    '<div style="height:4px;border-radius:2px;background:' + barColor + ';width:' + pct + '%"></div></div>';
                            }
                            if (c.key === 'status') {
                                var s = String(raw || '');
                                var statusStyle = s === 'completed' ? 'background:#e7f7f2;color:#04ac85' : s === 'in_progress' ? 'background:#e7f3ff;color:#1a5fb4' : 'background:#f0f0f0;color:#555';
                                var statusLabel = s === 'completed' ? 'Completado' : s === 'in_progress' ? 'En progreso' : escapeHtml(s);
                                return '<td><span style="' + statusStyle + ';border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700">' + statusLabel + '</span></td>';
                            }
                            return '<td>' + fmtCell(raw, c.fmt) + extra + '</td>';
                        }).join('') + '</tr>';
                    }).join('') +
                    '</tbody></table>' +
                    '<p style="font-size:11px;color:#66746b;margin:6px 0 0">' + fmtInt(rows.length) + ' registros</p></div>';
            } else {
                tableHtml = '<div style="overflow-x:auto"><table class="web-table"><thead><tr>' +
                    activeCols.map(function (c) { return '<th>' + escapeHtml(c.label) + '</th>'; }).join('') +
                    '</tr></thead><tbody>' +
                    rows.map(function (row) {
                        return '<tr>' + activeCols.map(function (c) {
                            return '<td>' + fmtCell(row[c.key], c.fmt) + '</td>';
                        }).join('') + '</tr>';
                    }).join('') +
                    '</tbody></table>' +
                    '<p style="font-size:11px;color:#66746b;margin:6px 0 0">' + fmtInt(rows.length) + ' registros</p></div>';
            }
        }

        contentEl.innerHTML = metricsHtml +
            '<div class="panel" style="padding:14px">' +
            '<h3 style="font-size:14px;font-weight:900;margin:0 0 10px">' + (rep ? rep.icon + ' ' + rep.label : '') + '</h3>' +
            tableHtml +
            '</div>';
    }

    function loadPersonalReport(root) {
        state.personalLoading = true;
        state.personalData    = null;
        var msgEl = qs('[data-personal-message]', root);
        if (msgEl) { msgEl.style.display = 'none'; }
        renderPersonalContent(root);

        var params = new URLSearchParams();
        if (state.dateFrom) { params.set('from', state.dateFrom); }
        if (state.dateTo)   { params.set('to',   state.dateTo); }
        var qs2 = params.toString() ? '?' + params.toString() : '';

        window.CCApi.request(endpoint('/api/v1/users/me/reports/' + encodeURIComponent(state.personalReport) + qs2))
            .then(function (response) {
                state.personalData    = response;
                state.personalLoading = false;
                renderPersonalContent(root);
            })
            .catch(function (err) {
                state.personalData    = null;
                state.personalLoading = false;
                renderPersonalContent(root);
                if (msgEl) { msgEl.className = 'alert alert-danger'; msgEl.textContent = errMsg(err); msgEl.style.display = 'block'; }
            });
    }

    var EXPORT_STATUS_LABELS = {
        pending:    { text: 'En cola...',     color: '#66746b' },
        processing: { text: 'Procesando...',  color: '#1a5fb4' },
        completed:  { text: 'Listo.',         color: '#04ac85' },
        failed:     { text: 'Error al exportar.', color: '#b33a3a' },
    };

    function showExportMsg(root, type, msg) {
        var el = qs('[data-export-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearExportMsg(root) {
        var el = qs('[data-export-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function setExportStatus(root, status, downloadUrl) {
        var statusEl = qs('[data-export-status]', root);
        var textEl   = qs('[data-export-status-text]', root);
        var dlBtn    = qs('[data-export-download]', root);
        if (!statusEl) { return; }

        statusEl.style.display = '';
        var info = EXPORT_STATUS_LABELS[status] || { text: escapeHtml(status), color: '#66746b' };
        if (textEl) { textEl.textContent = info.text; textEl.style.color = info.color; }
        if (dlBtn) {
            if (status === 'completed' && downloadUrl) {
                dlBtn.href = downloadUrl;
                dlBtn.style.display = '';
            } else {
                dlBtn.style.display = 'none';
            }
        }
    }

    function stopExportPoll() {
        if (state.exportPollTimer) {
            clearInterval(state.exportPollTimer);
            state.exportPollTimer = null;
        }
    }

    function pollExport(root) {
        stopExportPoll();
        state.exportPollTimer = setInterval(function () {
            if (!state.exportId) { stopExportPoll(); return; }
            window.CCApi.request(endpoint('/api/v1/report-exports/' + encodeURIComponent(state.exportId)))
                .then(function (response) {
                    var job = response.data || response;
                    var status = job.status || 'pending';
                    setExportStatus(root, status, job.download_url || job.url || null);
                    if (status === 'completed' || status === 'failed') {
                        stopExportPoll();
                        state.exportLoading = false;
                        var btn = qs('[data-export-submit]', root);
                        if (btn) { btn.disabled = false; btn.textContent = 'Generar exportación'; }
                        if (status === 'failed') {
                            showExportMsg(root, 'danger', 'La exportación falló. Intentá de nuevo.');
                        }
                    }
                })
                .catch(function () {});
        }, 3000);
    }

    function triggerExport(root) {
        if (!state.currentGroupId) { showExportMsg(root, 'warning', 'Seleccioná un grupo familiar.'); return; }

        var formatSel  = qs('[data-export-format]', root);
        var reportSel  = qs('[data-export-report-type]', root);
        var format     = formatSel ? formatSel.value : 'xlsx';
        var reportType = reportSel ? reportSel.value : state.activeReport;

        var payload = { format: format, report_type: reportType };
        if (state.dateFrom) { payload.from = state.dateFrom; }
        if (state.dateTo)   { payload.to   = state.dateTo; }

        state.exportLoading = true;
        state.exportId      = null;
        stopExportPoll();
        clearExportMsg(root);

        var statusEl = qs('[data-export-status]', root);
        if (statusEl) { statusEl.style.display = 'none'; }
        var dlBtn = qs('[data-export-download]', root);
        if (dlBtn) { dlBtn.style.display = 'none'; }

        var btn = qs('[data-export-submit]', root);
        if (btn) { btn.disabled = true; btn.textContent = 'Generando...'; }

        window.CCApi.request(
            endpoint('/api/v1/family-groups/' + encodeURIComponent(state.currentGroupId) + '/reports/export'),
            { method: 'POST', body: payload }
        )
            .then(function (response) {
                var job = response.data || response;
                state.exportId = job.id;
                var status = job.status || 'pending';
                setExportStatus(root, status, job.download_url || job.url || null);
                if (status === 'completed') {
                    state.exportLoading = false;
                    if (btn) { btn.disabled = false; btn.textContent = 'Generar exportación'; }
                } else {
                    pollExport(root);
                }
            })
            .catch(function (err) {
                state.exportLoading = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Generar exportación'; }
                showExportMsg(root, 'danger', errMsg(err));
            });
    }

    function syncExportReportType(root) {
        var sel = qs('[data-export-report-type]', root);
        if (sel) { sel.value = state.activeReport; }
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
                syncExportReportType(root);
            });
        }

        root.addEventListener('click', function (event) {
            if (event.target.closest('[data-report-export-toggle]')) {
                var panel = qs('[data-export-panel]', root);
                if (panel) { panel.style.display = panel.style.display === 'none' ? '' : 'none'; }
                syncExportReportType(root);
                clearExportMsg(root);
            }
        });

        var exportBtn = qs('[data-export-submit]', root);
        if (exportBtn) {
            exportBtn.addEventListener('click', function () { triggerExport(root); });
        }

        var personalTabsEl = qs('[data-personal-tabs]', root);
        if (personalTabsEl) {
            personalTabsEl.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-personal-tab]');
                if (!btn) { return; }
                state.personalReport = btn.getAttribute('data-personal-tab');
                state.personalData   = null;
                renderPersonalTabs(root);
                renderPersonalContent(root);
            });
        }

        var personalGenerateBtn = qs('[data-personal-generate]', root);
        if (personalGenerateBtn) {
            personalGenerateBtn.addEventListener('click', function () { loadPersonalReport(root); });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-reports]');
        if (!root) { return; }
        bind(root);
        renderTabs(root);
        renderContent(root);
        renderPersonalTabs(root);
        renderPersonalContent(root);
        loadGroups(root);
    });
})(window, document);
