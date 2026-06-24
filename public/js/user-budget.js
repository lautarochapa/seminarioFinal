(function (window, document) {
    'use strict';

    var state = {
        groups: [],
        budgets: [],
        currentBudget: null,
        selectedBudget: null,
        currentGroupId: null,
        page: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        saving: false,
        summaryBudgetId: null,
        summary: null,
        projection: null,
        summaryLoading: false,
        projectionLoading: false,
        catsBudgetId: null,
        catsBudget: null,
        categories: [],
        catsLoading: false,
        catSaving: false,
        selectedCatId: null,
        movsBudgetId: null,
        movsBudget: null,
        movements: [],
        movsPage: 1,
        movsLastPage: 1,
        movsTotal: 0,
        movsLoading: false,
        movSaving: false,
        movsTypeFilter: '',
        alertsBudgetId: null,
        alertsBudget: null,
        alerts: [],
        alertsLoading: false,
    };

    function qs(sel, root) { return (root || document).querySelector(sel); }

    function escapeHtml(v) {
        return (v === null || v === undefined ? '' : String(v))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmt(val) {
        var n = parseFloat(val);
        return isNaN(n) ? '-' : '$' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    var MONTH_NAMES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    function monthLabel(budget) {
        return (MONTH_NAMES[budget.month] || budget.month) + ' ' + budget.year;
    }

    function groupPath(path) {
        return '/api/v1/family-groups/' + encodeURIComponent(state.currentGroupId) + path;
    }

    function showMsg(root, type, msg) {
        var el = qs('[data-budget-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearMsg(root) {
        var el = qs('[data-budget-message]', root);
        if (!el) { return; }
        el.style.display = 'none';
    }

    function showFormMsg(root, type, msg) {
        var el = qs('[data-budget-form-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearFormMsg(root) {
        var el = qs('[data-budget-form-message]', root);
        if (!el) { return; }
        el.style.display = 'none';
    }

    function errMsg(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message)
            ? err.payload.error.message
            : (err && err.message) || 'Error inesperado.';
    }

    function progressBar(spent, amount) {
        var s = parseFloat(spent) || 0;
        var a = parseFloat(amount) || 0;
        if (!a) { return ''; }
        var pct = Math.min(100, Math.round((s / a) * 100));
        var color = pct >= 90 ? '#b33a3a' : pct >= 70 ? '#b35c00' : '#04ac85';
        return '<div style="background:#eee;border-radius:999px;height:8px;margin-top:6px;overflow:hidden">' +
            '<div style="background:' + color + ';width:' + pct + '%;height:100%;border-radius:999px;transition:width .3s"></div>' +
            '</div>' +
            '<div style="display:flex;justify-content:space-between;font-size:11px;color:#66746b;margin-top:3px">' +
            '<span>Gastado ' + pct + '%</span>' +
            '<span>' + fmt(s) + ' / ' + fmt(a) + '</span>' +
            '</div>';
    }

    function renderCurrentBudget(root) {
        var el = qs('[data-budget-current]', root);
        if (!el) { return; }
        if (!state.currentBudget) {
            el.innerHTML = '<p class="muted" style="font-size:13px;margin:0">No hay presupuesto para el mes actual.</p>';
            return;
        }
        var b = state.currentBudget;
        var remaining = (parseFloat(b.amount) || 0) - (parseFloat(b.spent) || 0);
        var remainColor = remaining >= 0 ? '#04ac85' : '#b33a3a';
        el.innerHTML = '<div style="background:#e7f7f2;border:1px solid #04ac85;border-radius:8px;padding:14px">' +
            '<div style="font-size:11px;font-weight:700;color:#04ac85;text-transform:uppercase;margin-bottom:6px">Mes actual — ' + escapeHtml(monthLabel(b)) + '</div>' +
            '<div style="font-size:28px;font-weight:900;color:#24252a">' + escapeHtml(fmt(b.amount)) + '</div>' +
            (b.currency ? '<div style="font-size:11px;color:#66746b;margin-bottom:6px">' + escapeHtml(b.currency) + '</div>' : '') +
            progressBar(b.spent, b.amount) +
            '<div style="margin-top:10px;display:flex;justify-content:space-between;font-size:13px">' +
            '<span style="color:#66746b">Disponible</span>' +
            '<strong style="color:' + remainColor + '">' + escapeHtml(fmt(remaining)) + '</strong>' +
            '</div>' +
            (b.notes ? '<div style="margin-top:8px;font-size:12px;color:#66746b;border-top:1px solid #c0e8d8;padding-top:6px">' + escapeHtml(b.notes) + '</div>' : '') +
            '<div style="margin-top:10px;display:flex;gap:6px">' +
            '<button type="button" class="btn-main btn-sm" data-budget-summary="' + escapeHtml(String(b.id)) + '">Resumen</button>' +
            '<button type="button" class="btn-secondary-web btn-sm" data-budget-edit="' + escapeHtml(String(b.id)) + '">Editar</button>' +
            '</div>' +
            '</div>';
    }

    function renderList(root) {
        var tbody = qs('[data-budget-body]', root);
        var countEl = qs('[data-budget-count]', root);
        var pageEl = qs('[data-budget-page]', root);
        var prevBtn = qs('[data-budget-prev]', root);
        var nextBtn = qs('[data-budget-next]', root);

        if (countEl) { countEl.textContent = state.total + ' presupuesto' + (state.total !== 1 ? 's' : ''); }
        if (pageEl) { pageEl.textContent = 'Pág. ' + state.page + ' / ' + state.lastPage; }
        if (prevBtn) { prevBtn.disabled = state.page <= 1; }
        if (nextBtn) { nextBtn.disabled = state.page >= state.lastPage; }

        if (!tbody) { return; }
        if (!state.currentGroupId) {
            tbody.innerHTML = '<tr><td colspan="5" class="muted">Seleccioná un grupo familiar.</td></tr>';
            return;
        }
        if (!state.budgets.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="muted">No hay presupuestos registrados.</td></tr>';
            return;
        }

        var now = new Date();
        var nowMonth = now.getMonth() + 1;
        var nowYear = now.getFullYear();

        tbody.innerHTML = state.budgets.map(function (b) {
            var isCurrent = b.month === nowMonth && b.year === nowYear;
            var remaining = (parseFloat(b.amount) || 0) - (parseFloat(b.spent) || 0);
            var remainColor = remaining >= 0 ? '#2a7a2a' : '#b33a3a';
            return '<tr' + (isCurrent ? ' style="background:#f0fbf7"' : '') + '>' +
                '<td><strong>' + escapeHtml(monthLabel(b)) + '</strong>' +
                (isCurrent ? ' <span style="background:#04ac85;color:#fff;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700">Actual</span>' : '') +
                '</td>' +
                '<td>' + escapeHtml(fmt(b.amount)) + '</td>' +
                '<td>' + (b.spent !== undefined ? escapeHtml(fmt(b.spent)) : '-') + '</td>' +
                '<td style="color:' + remainColor + ';font-weight:700">' + escapeHtml(fmt(remaining)) + '</td>' +
                '<td>' +
                '<button type="button" class="btn-main btn-sm" data-budget-summary="' + escapeHtml(String(b.id)) + '" style="margin-right:4px">Resumen</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-budget-cats="' + escapeHtml(String(b.id)) + '" style="margin-right:4px">Categorías</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-budget-movs="' + escapeHtml(String(b.id)) + '" style="margin-right:4px">Movimientos</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-budget-alerts="' + escapeHtml(String(b.id)) + '" style="margin-right:4px">Alertas</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-budget-edit="' + escapeHtml(String(b.id)) + '">Editar</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-budget-delete="' + escapeHtml(String(b.id)) + '">Eliminar</button>' +
                '</td>' +
                '</tr>';
        }).join('');
    }

    function renderGroups(root) {
        var sel = qs('[data-budget-group]', root);
        if (!sel) { return; }
        sel.innerHTML = '<option value="">Seleccioná un grupo</option>' +
            state.groups.map(function (g) {
                return '<option value="' + escapeHtml(g.id) + '"' +
                    (String(g.id) === String(state.currentGroupId) ? ' selected' : '') + '>' +
                    escapeHtml(g.name || ('Grupo #' + g.id)) + '</option>';
            }).join('');
    }

    function resetForm(root) {
        var form = qs('[data-budget-form]', root);
        var title = qs('[data-budget-form-title]', root);
        if (form) {
            form.reset();
            if (form.elements.id) { form.elements.id.value = ''; }
            var now = new Date();
            if (form.elements.month) { form.elements.month.value = now.getMonth() + 1; }
            if (form.elements.year) { form.elements.year.value = now.getFullYear(); }
        }
        if (title) { title.textContent = 'Nuevo presupuesto'; }
        state.selectedBudget = null;
        clearFormMsg(root);
    }

    function fillForm(root, budget) {
        var form = qs('[data-budget-form]', root);
        var title = qs('[data-budget-form-title]', root);
        if (!form || !budget) { return; }
        if (form.elements.id) { form.elements.id.value = budget.id; }
        if (form.elements.month) { form.elements.month.value = budget.month; }
        if (form.elements.year) { form.elements.year.value = budget.year; }
        if (form.elements.amount) { form.elements.amount.value = budget.amount; }
        if (form.elements.currency) { form.elements.currency.value = budget.currency || ''; }
        if (form.elements.notes) { form.elements.notes.value = budget.notes || ''; }
        if (title) { title.textContent = 'Editar — ' + monthLabel(budget); }
        state.selectedBudget = budget;
        clearFormMsg(root);
    }

    function buildPayload(form) {
        var data = {};
        if (form.elements.month && form.elements.month.value) {
            data.month = Number(form.elements.month.value);
        }
        if (form.elements.year && form.elements.year.value) {
            data.year = Number(form.elements.year.value);
        }
        if (form.elements.amount && form.elements.amount.value !== '') {
            data.amount = parseFloat(form.elements.amount.value);
        }
        if (form.elements.currency && form.elements.currency.value.trim()) {
            data.currency = form.elements.currency.value.trim();
        }
        if (form.elements.notes && form.elements.notes.value.trim()) {
            data.notes = form.elements.notes.value.trim();
        }
        return data;
    }

    function loadGroups(root) {
        return window.CCApi.request('/api/v1/family-groups')
            .then(function (response) {
                state.groups = response.data || [];
                if (!state.currentGroupId && state.groups.length) {
                    state.currentGroupId = state.groups[0].id;
                }
                renderGroups(root);
            })
            .catch(function (err) { showMsg(root, 'danger', errMsg(err)); });
    }

    function loadCurrent(root) {
        if (!state.currentGroupId) {
            state.currentBudget = null;
            renderCurrentBudget(root);
            return Promise.resolve();
        }
        return window.CCApi.request(groupPath('/budgets/current'))
            .then(function (response) {
                state.currentBudget = response.data || null;
                renderCurrentBudget(root);
            })
            .catch(function () {
                state.currentBudget = null;
                renderCurrentBudget(root);
            });
    }

    function loadBudgets(root) {
        if (!state.currentGroupId) {
            state.budgets = [];
            renderList(root);
            return Promise.resolve();
        }
        clearMsg(root);
        return window.CCApi.request(groupPath('/budgets?page=' + state.page + '&per_page=12'))
            .then(function (response) {
                state.budgets = response.data || [];
                state.total = (response.meta && response.meta.total) || state.budgets.length;
                state.page = (response.meta && response.meta.current_page) || state.page;
                state.lastPage = (response.meta && response.meta.last_page) || 1;
                renderList(root);
            })
            .catch(function (err) {
                state.budgets = [];
                renderList(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function saveBudget(root, form) {
        if (!state.currentGroupId) {
            showFormMsg(root, 'warning', 'Seleccioná un grupo familiar.');
            return;
        }
        var payload = buildPayload(form);
        if (!payload.amount) {
            showFormMsg(root, 'warning', 'Ingresá el monto del presupuesto.');
            return;
        }
        var id = form.elements.id ? form.elements.id.value : '';
        state.saving = true;
        var btn = qs('[data-budget-save]', root);
        if (btn) { btn.disabled = true; }

        window.CCApi.request(groupPath('/budgets' + (id ? '/' + encodeURIComponent(id) : '')), {
            method: id ? 'PATCH' : 'POST',
            body: payload,
        }).then(function () {
            state.saving = false;
            if (btn) { btn.disabled = false; }
            showMsg(root, 'success', id ? 'Presupuesto actualizado.' : 'Presupuesto creado.');
            resetForm(root);
            return Promise.all([loadBudgets(root), loadCurrent(root)]);
        }).catch(function (err) {
            state.saving = false;
            if (btn) { btn.disabled = false; }
            showFormMsg(root, 'danger', errMsg(err));
        });
    }

    function deleteBudget(root, id) {
        if (!state.currentGroupId || !id) { return; }
        if (!window.confirm('¿Eliminar este presupuesto?')) { return; }
        window.CCApi.request(groupPath('/budgets/' + encodeURIComponent(id)), { method: 'DELETE' })
            .then(function () {
                showMsg(root, 'success', 'Presupuesto eliminado.');
                if (state.selectedBudget && String(state.selectedBudget.id) === String(id)) {
                    resetForm(root);
                }
                return Promise.all([loadBudgets(root), loadCurrent(root)]);
            })
            .catch(function (err) { showMsg(root, 'danger', errMsg(err)); });
    }

    function metricCard(label, value, color) {
        return '<div style="border:1px solid #dde6df;border-radius:8px;padding:12px;text-align:center">' +
            '<div style="font-size:11px;color:#66746b;text-transform:uppercase;font-weight:700;margin-bottom:4px">' + escapeHtml(label) + '</div>' +
            '<div style="font-size:18px;font-weight:900;color:' + (color || '#24252a') + '">' + escapeHtml(fmt(value)) + '</div>' +
            '</div>';
    }

    function renderSummaryPanel(root) {
        var panel = qs('[data-budget-summary-panel]', root);
        if (!panel) { return; }

        if (!state.summaryBudgetId) { panel.style.display = 'none'; return; }
        panel.style.display = '';

        var titleEl = qs('[data-budget-summary-title]', root);
        var msgEl = qs('[data-budget-summary-message]', root);
        var metricsEl = qs('[data-budget-summary-metrics]', root);
        var barsEl = qs('[data-budget-summary-bars]', root);
        var catsEl = qs('[data-budget-summary-categories]', root);

        if (msgEl) { msgEl.style.display = 'none'; }

        if (state.summaryLoading) {
            if (metricsEl) { metricsEl.innerHTML = '<div style="grid-column:1/-1;font-size:13px;color:#66746b">Cargando resumen...</div>'; }
            if (barsEl) { barsEl.innerHTML = ''; }
            if (catsEl) { catsEl.innerHTML = ''; }
            return;
        }

        var s = state.summary;
        if (!s) {
            if (msgEl) { msgEl.className = 'alert alert-danger'; msgEl.textContent = 'No se pudo cargar el resumen.'; msgEl.style.display = 'block'; }
            if (metricsEl) { metricsEl.innerHTML = ''; }
            return;
        }

        if (titleEl) { titleEl.textContent = 'Resumen — ' + (s.period || monthLabel({ month: s.month, year: s.year }) || '#' + state.summaryBudgetId); }

        var total = parseFloat(s.total_budget) || parseFloat(s.amount) || 0;
        var spent = parseFloat(s.spent_real) || parseFloat(s.spent) || 0;
        var reserved = parseFloat(s.planned_reserved) || 0;
        var availReal = s.available_real !== undefined ? parseFloat(s.available_real) : (total - spent);
        var availProj = s.available_projected !== undefined ? parseFloat(s.available_projected) : (total - spent - reserved);

        if (metricsEl) {
            metricsEl.innerHTML =
                metricCard('Presupuesto total', total, '#24252a') +
                metricCard('Gastado real', spent, spent > total ? '#b33a3a' : '#24252a') +
                metricCard('Reservado planificado', reserved, '#b35c00') +
                metricCard('Disponible real', availReal, availReal >= 0 ? '#04ac85' : '#b33a3a') +
                metricCard('Disponible proyectado', availProj, availProj >= 0 ? '#04ac85' : '#b33a3a');
        }

        if (barsEl) {
            var spentPct = total ? Math.min(100, Math.round((spent / total) * 100)) : 0;
            var reservedPct = total ? Math.min(100 - spentPct, Math.round((reserved / total) * 100)) : 0;
            barsEl.innerHTML = '<div style="margin-bottom:8px">' +
                '<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">' +
                '<span style="color:#66746b">Gastado</span><span style="font-weight:700">' + spentPct + '%</span></div>' +
                '<div style="background:#eee;border-radius:999px;height:10px;overflow:hidden">' +
                '<div style="background:#04ac85;width:' + spentPct + '%;height:100%;border-radius:999px"></div></div>' +
                '</div>' +
                (reserved > 0
                    ? '<div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">' +
                      '<span style="color:#66746b">Gastado + Reservado</span><span style="font-weight:700">' + (spentPct + reservedPct) + '%</span></div>' +
                      '<div style="background:#eee;border-radius:999px;height:10px;overflow:hidden;display:flex">' +
                      '<div style="background:#04ac85;width:' + spentPct + '%;height:100%"></div>' +
                      '<div style="background:#b35c00;width:' + reservedPct + '%;height:100%"></div>' +
                      '</div></div>'
                    : '');
        }

        if (catsEl) {
            var cats = s.by_category || s.categories || [];
            if (cats.length) {
                catsEl.innerHTML = '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #dde6df">' +
                    '<div style="font-size:12px;font-weight:700;color:#66746b;text-transform:uppercase;margin-bottom:8px">Por categoría</div>' +
                    cats.map(function (c) {
                        var pct = parseFloat(c.pct) || (total ? Math.round((parseFloat(c.spent) / total) * 100) : 0);
                        return '<div style="margin-bottom:6px">' +
                            '<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px">' +
                            '<span>' + escapeHtml(c.category || c.name || 'Sin categoría') + '</span>' +
                            '<span style="font-weight:700">' + escapeHtml(fmt(c.spent)) + ' (' + pct + '%)</span>' +
                            '</div>' +
                            '<div style="background:#eee;border-radius:999px;height:6px;overflow:hidden">' +
                            '<div style="background:#2f80ed;width:' + Math.min(100, pct) + '%;height:100%;border-radius:999px"></div>' +
                            '</div></div>';
                    }).join('') + '</div>';
            } else {
                catsEl.innerHTML = '';
            }
        }
    }

    function renderProjection(root) {
        var el = qs('[data-budget-projection-content]', root);
        if (!el) { return; }

        if (state.projectionLoading) {
            el.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">Calculando proyección...</p>';
            return;
        }

        var p = state.projection;
        if (!p) {
            el.innerHTML = '<p style="font-size:13px;color:#b33a3a;margin:0">No se pudo cargar la proyección.</p>';
            return;
        }

        var willExceed = p.at_current_rate_will_exceed || false;
        var exceedDate = p.exceed_date || null;
        var projSpend = p.projected_total_spend !== undefined ? parseFloat(p.projected_total_spend) : null;
        var projRemaining = p.projected_remaining !== undefined ? parseFloat(p.projected_remaining) : null;
        var dailyAvg = p.daily_average !== undefined ? parseFloat(p.daily_average) : null;
        var daysElapsed = p.days_elapsed !== undefined ? p.days_elapsed : null;
        var daysRemaining = p.days_remaining !== undefined ? p.days_remaining : null;

        el.innerHTML = (willExceed
            ? '<div style="background:#f7e7e7;border:1px solid #b33a3a;border-radius:6px;padding:10px 12px;margin-bottom:10px">' +
              '<div style="font-size:12px;font-weight:700;color:#b33a3a">⚠ Al ritmo actual se superará el presupuesto' +
              (exceedDate ? ' el ' + escapeHtml(exceedDate) : '') + '</div>' +
              '</div>'
            : '<div style="background:#e7f7f2;border:1px solid #04ac85;border-radius:6px;padding:10px 12px;margin-bottom:10px">' +
              '<div style="font-size:12px;font-weight:700;color:#04ac85">✓ El gasto actual está dentro del presupuesto</div>' +
              '</div>') +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' +
            (dailyAvg !== null ? '<div style="border:1px solid #dde6df;border-radius:6px;padding:10px;text-align:center"><div style="font-size:10px;color:#66746b;text-transform:uppercase;font-weight:700;margin-bottom:2px">Promedio diario</div><div style="font-size:16px;font-weight:900">' + escapeHtml(fmt(dailyAvg)) + '</div></div>' : '') +
            (projSpend !== null ? '<div style="border:1px solid #dde6df;border-radius:6px;padding:10px;text-align:center"><div style="font-size:10px;color:#66746b;text-transform:uppercase;font-weight:700;margin-bottom:2px">Gasto proyectado</div><div style="font-size:16px;font-weight:900;color:' + (willExceed ? '#b33a3a' : '#24252a') + '">' + escapeHtml(fmt(projSpend)) + '</div></div>' : '') +
            (daysElapsed !== null ? '<div style="border:1px solid #dde6df;border-radius:6px;padding:10px;text-align:center"><div style="font-size:10px;color:#66746b;text-transform:uppercase;font-weight:700;margin-bottom:2px">Días transcurridos</div><div style="font-size:16px;font-weight:900">' + escapeHtml(String(daysElapsed)) + '</div></div>' : '') +
            (daysRemaining !== null ? '<div style="border:1px solid #dde6df;border-radius:6px;padding:10px;text-align:center"><div style="font-size:10px;color:#66746b;text-transform:uppercase;font-weight:700;margin-bottom:2px">Días restantes</div><div style="font-size:16px;font-weight:900">' + escapeHtml(String(daysRemaining)) + '</div></div>' : '') +
            (projRemaining !== null ? '<div style="border:1px solid #dde6df;border-radius:6px;padding:10px;text-align:center;grid-column:1/-1"><div style="font-size:10px;color:#66746b;text-transform:uppercase;font-weight:700;margin-bottom:2px">Disponible proyectado al fin de mes</div><div style="font-size:20px;font-weight:900;color:' + (projRemaining >= 0 ? '#04ac85' : '#b33a3a') + '">' + escapeHtml(fmt(projRemaining)) + '</div></div>' : '') +
            '</div>';
    }

    function loadSummary(root, budgetId) {
        state.summaryBudgetId = budgetId;
        state.summary = null;
        state.projection = null;
        state.summaryLoading = true;
        state.projectionLoading = true;
        renderSummaryPanel(root);
        renderProjection(root);

        var budgetPath = groupPath('/budgets/' + encodeURIComponent(budgetId));

        window.CCApi.request(budgetPath + '/summary')
            .then(function (response) {
                state.summary = response.data || null;
                state.summaryLoading = false;
                renderSummaryPanel(root);
            })
            .catch(function () {
                state.summary = null;
                state.summaryLoading = false;
                renderSummaryPanel(root);
            });

        window.CCApi.request(budgetPath + '/projection')
            .then(function (response) {
                state.projection = response.data || null;
                state.projectionLoading = false;
                renderProjection(root);
            })
            .catch(function () {
                state.projection = null;
                state.projectionLoading = false;
                renderProjection(root);
            });

        var panel = qs('[data-budget-summary-panel]', root);
        if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    function reloadProjection(root) {
        if (!state.summaryBudgetId || !state.currentGroupId) { return; }
        state.projection = null;
        state.projectionLoading = true;
        renderProjection(root);
        window.CCApi.request(groupPath('/budgets/' + encodeURIComponent(state.summaryBudgetId) + '/projection'))
            .then(function (response) {
                state.projection = response.data || null;
                state.projectionLoading = false;
                renderProjection(root);
            })
            .catch(function () {
                state.projection = null;
                state.projectionLoading = false;
                renderProjection(root);
            });
    }

    function catBudgetPath(extra) {
        return groupPath('/budgets/' + encodeURIComponent(state.catsBudgetId) + '/categories' + (extra || ''));
    }

    function showCatsMsg(root, type, msg) {
        var el = qs('[data-budget-cats-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearCatsMsg(root) {
        var el = qs('[data-budget-cats-message]', root);
        if (!el) { return; }
        el.style.display = 'none';
    }

    function showCatFormMsg(root, type, msg) {
        var el = qs('[data-budget-cat-form-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearCatFormMsg(root) {
        var el = qs('[data-budget-cat-form-message]', root);
        if (!el) { return; }
        el.style.display = 'none';
    }

    function resetCatForm(root) {
        var form = qs('[data-budget-cat-form]', root);
        var title = qs('[data-budget-cat-form-title]', root);
        if (form) {
            form.reset();
            if (form.elements.id) { form.elements.id.value = ''; }
            if (form.elements.color) { form.elements.color.value = '#04ac85'; }
        }
        if (title) { title.textContent = 'Agregar categoría'; }
        state.selectedCatId = null;
        clearCatFormMsg(root);
    }

    function fillCatForm(root, cat) {
        var form = qs('[data-budget-cat-form]', root);
        var title = qs('[data-budget-cat-form-title]', root);
        if (!form || !cat) { return; }
        if (form.elements.id) { form.elements.id.value = cat.id; }
        if (form.elements.name) { form.elements.name.value = cat.name || ''; }
        if (form.elements.allocated_amount) { form.elements.allocated_amount.value = cat.allocated_amount || ''; }
        if (form.elements.color) { form.elements.color.value = cat.color || '#04ac85'; }
        if (title) { title.textContent = 'Editar: ' + (cat.name || 'categoría'); }
        state.selectedCatId = cat.id;
        clearCatFormMsg(root);
    }

    function renderCategoriesPanel(root) {
        var panel = qs('[data-budget-categories-panel]', root);
        if (!panel) { return; }
        if (!state.catsBudgetId) { panel.style.display = 'none'; return; }
        panel.style.display = '';

        var titleEl = qs('[data-budget-cats-title]', root);
        var allocEl = qs('[data-budget-cats-allocation]', root);
        var listEl = qs('[data-budget-cats-list]', root);

        var budget = state.catsBudget;
        if (titleEl) {
            titleEl.textContent = 'Categorías' + (budget ? ' — ' + monthLabel(budget) : '');
        }

        if (state.catsLoading) {
            if (listEl) { listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">Cargando categorías...</p>'; }
            if (allocEl) { allocEl.textContent = ''; }
            return;
        }

        var totalBudget = budget ? (parseFloat(budget.amount) || 0) : 0;
        var totalAlloc = state.categories.reduce(function (acc, c) { return acc + (parseFloat(c.allocated_amount) || 0); }, 0);
        var unalloc = totalBudget - totalAlloc;

        if (allocEl && totalBudget) {
            var allocPct = Math.min(100, Math.round((totalAlloc / totalBudget) * 100));
            var allocColor = totalAlloc > totalBudget ? '#b33a3a' : '#04ac85';
            allocEl.innerHTML =
                'Asignado: <strong style="color:' + allocColor + '">' + fmt(totalAlloc) + '</strong>' +
                ' de ' + fmt(totalBudget) +
                (unalloc >= 0
                    ? ' — <span style="color:#66746b">Sin asignar: ' + fmt(unalloc) + '</span>'
                    : ' <span style="color:#b33a3a">⚠ Excede el presupuesto en ' + fmt(-unalloc) + '</span>') +
                '<div style="background:#eee;border-radius:999px;height:6px;margin-top:4px;overflow:hidden">' +
                '<div style="background:' + allocColor + ';width:' + allocPct + '%;height:100%;border-radius:999px"></div></div>';
        } else if (allocEl) {
            allocEl.innerHTML = '';
        }

        if (!listEl) { return; }
        if (!state.categories.length) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">No hay categorías definidas. Agregá una abajo.</p>';
            return;
        }

        listEl.innerHTML = state.categories.map(function (cat) {
            var alloc = parseFloat(cat.allocated_amount) || 0;
            var spent = parseFloat(cat.spent) || 0;
            var avail = alloc - spent;
            var spentPct = alloc ? Math.min(100, Math.round((spent / alloc) * 100)) : 0;
            var barColor = spentPct >= 90 ? '#b33a3a' : spentPct >= 70 ? '#b35c00' : (cat.color || '#04ac85');
            var availColor = avail >= 0 ? '#2a7a2a' : '#b33a3a';
            var dot = cat.color
                ? '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + escapeHtml(cat.color) + ';margin-right:6px;flex-shrink:0"></span>'
                : '';
            return '<div style="border:1px solid #dde6df;border-radius:6px;padding:10px 12px;margin-bottom:8px">' +
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">' +
                '<div style="display:flex;align-items:center;font-size:13px;font-weight:700;min-width:0">' + dot + escapeHtml(cat.name || 'Sin nombre') + '</div>' +
                '<div style="display:flex;gap:4px;flex-shrink:0">' +
                '<button type="button" class="btn-secondary-web btn-sm" style="padding:3px 8px;font-size:11px" data-cat-edit="' + escapeHtml(String(cat.id)) + '">Editar</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" style="padding:3px 8px;font-size:11px" data-cat-delete="' + escapeHtml(String(cat.id)) + '">✕</button>' +
                '</div></div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px;font-size:11px;margin-bottom:6px">' +
                '<div><div style="color:#66746b">Asignado</div><div style="font-weight:700">' + escapeHtml(fmt(alloc)) + '</div></div>' +
                '<div><div style="color:#66746b">Gastado</div><div style="font-weight:700">' + escapeHtml(fmt(spent)) + '</div></div>' +
                '<div><div style="color:#66746b">Disponible</div><div style="font-weight:700;color:' + availColor + '">' + escapeHtml(fmt(avail)) + '</div></div>' +
                '</div>' +
                '<div style="background:#eee;border-radius:999px;height:6px;overflow:hidden">' +
                '<div style="background:' + barColor + ';width:' + spentPct + '%;height:100%;border-radius:999px"></div>' +
                '</div>' +
                '</div>';
        }).join('');
    }

    function loadCategories(root, budgetId) {
        var budget = state.budgets.find(function (b) { return String(b.id) === String(budgetId); });
        if (!budget && state.currentBudget && String(state.currentBudget.id) === String(budgetId)) {
            budget = state.currentBudget;
        }
        state.catsBudgetId = budgetId;
        state.catsBudget = budget || null;
        state.categories = [];
        state.catsLoading = true;
        state.selectedCatId = null;
        clearCatsMsg(root);
        resetCatForm(root);
        renderCategoriesPanel(root);

        window.CCApi.request(catBudgetPath())
            .then(function (response) {
                state.categories = response.data || [];
                state.catsLoading = false;
                renderCategoriesPanel(root);
            })
            .catch(function (err) {
                state.catsLoading = false;
                state.categories = [];
                renderCategoriesPanel(root);
                showCatsMsg(root, 'danger', errMsg(err));
            });

        var panel = qs('[data-budget-categories-panel]', root);
        if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    function saveCategory(root, form) {
        if (!state.catsBudgetId || !state.currentGroupId) { return; }
        var name = form.elements.name ? form.elements.name.value.trim() : '';
        var amount = form.elements.allocated_amount ? form.elements.allocated_amount.value : '';
        if (!name) { showCatFormMsg(root, 'warning', 'Ingresá el nombre de la categoría.'); return; }
        if (!amount) { showCatFormMsg(root, 'warning', 'Ingresá el monto asignado.'); return; }

        var id = form.elements.id ? form.elements.id.value : '';
        var payload = { name: name, allocated_amount: parseFloat(amount) };
        if (form.elements.color && form.elements.color.value) { payload.color = form.elements.color.value; }

        state.catSaving = true;
        var btn = qs('[data-budget-cat-save]', root);
        if (btn) { btn.disabled = true; }

        window.CCApi.request(catBudgetPath(id ? '/' + encodeURIComponent(id) : ''), {
            method: id ? 'PATCH' : 'POST',
            body: payload,
        }).then(function () {
            state.catSaving = false;
            if (btn) { btn.disabled = false; }
            showCatsMsg(root, 'success', id ? 'Categoría actualizada.' : 'Categoría creada.');
            resetCatForm(root);
            return loadCategories(root, state.catsBudgetId);
        }).catch(function (err) {
            state.catSaving = false;
            if (btn) { btn.disabled = false; }
            showCatFormMsg(root, 'danger', errMsg(err));
        });
    }

    function deleteCategory(root, catId) {
        if (!state.catsBudgetId || !catId) { return; }
        var cat = state.categories.find(function (c) { return String(c.id) === String(catId); });
        if (!window.confirm('¿Eliminar la categoría "' + (cat ? cat.name : catId) + '"?')) { return; }
        window.CCApi.request(catBudgetPath('/' + encodeURIComponent(catId)), { method: 'DELETE' })
            .then(function () {
                showCatsMsg(root, 'success', 'Categoría eliminada.');
                if (String(state.selectedCatId) === String(catId)) { resetCatForm(root); }
                return loadCategories(root, state.catsBudgetId);
            })
            .catch(function (err) { showCatsMsg(root, 'danger', errMsg(err)); });
    }

    var MOV_TYPE = {
        purchase:   { label: 'Compra real',   bg: '#f7e7e7', color: '#b33a3a' },
        planned:    { label: 'Planificada',   bg: '#fff3e0', color: '#b35c00' },
        reserve:    { label: 'Reserva',       bg: '#e7f3ff', color: '#1a5fb4' },
        release:    { label: 'Liberación',    bg: '#e7f7f2', color: '#04ac85' },
        adjustment: { label: 'Ajuste',        bg: '#f0f0f0', color: '#555'    },
    };

    function movTypeBadge(type) {
        var t = MOV_TYPE[type] || { label: type || '?', bg: '#f0f0f0', color: '#555' };
        return '<span style="background:' + t.bg + ';color:' + t.color + ';border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;white-space:nowrap">' + escapeHtml(t.label) + '</span>';
    }

    function movsBudgetPath(extra) {
        return groupPath('/budgets/' + encodeURIComponent(state.movsBudgetId) + (extra || ''));
    }

    function showMovsMsg(root, type, msg) {
        var el = qs('[data-budget-movs-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function showAdjMsg(root, type, msg) {
        var el = qs('[data-movs-adj-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearAdjMsg(root) {
        var el = qs('[data-movs-adj-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function renderAdjCategoryOptions(root) {
        var sel = qs('[data-movs-adj-category]', root);
        if (!sel) { return; }
        sel.innerHTML = '<option value="">Sin categoría</option>' +
            state.categories.map(function (c) {
                return '<option value="' + escapeHtml(c.id) + '">' + escapeHtml(c.name || ('#' + c.id)) + '</option>';
            }).join('');
    }

    function renderMovementsPanel(root) {
        var panel = qs('[data-budget-movements-panel]', root);
        if (!panel) { return; }
        if (!state.movsBudgetId) { panel.style.display = 'none'; return; }
        panel.style.display = '';

        var titleEl = qs('[data-budget-movs-title]', root);
        var listEl  = qs('[data-budget-movs-list]', root);
        var countEl = qs('[data-movs-count]', root);
        var pageEl  = qs('[data-movs-page]', root);
        var prevBtn = qs('[data-movs-prev]', root);
        var nextBtn = qs('[data-movs-next]', root);

        if (titleEl) {
            var b = state.movsBudget;
            titleEl.textContent = 'Movimientos' + (b ? ' — ' + monthLabel(b) : '');
        }
        if (countEl) { countEl.textContent = state.movsTotal + ' movimiento' + (state.movsTotal !== 1 ? 's' : ''); }
        if (pageEl)  { pageEl.textContent = 'Pág. ' + state.movsPage + ' / ' + state.movsLastPage; }
        if (prevBtn) { prevBtn.disabled = state.movsLoading || state.movsPage <= 1; }
        if (nextBtn) { nextBtn.disabled = state.movsLoading || state.movsPage >= state.movsLastPage; }

        if (!listEl) { return; }

        if (state.movsLoading) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">Cargando movimientos...</p>';
            return;
        }
        if (!state.movements.length) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">No hay movimientos para los filtros seleccionados.</p>';
            return;
        }

        listEl.innerHTML = state.movements.map(function (m) {
            var amount = parseFloat(m.amount);
            var isNeg  = amount < 0;
            var amtColor = isNeg ? '#b33a3a' : '#2a7a2a';
            var amtSign  = isNeg ? '' : '+';
            var date = m.created_at ? m.created_at.substring(0, 10) : '-';
            var catName = m.category_name || (m.category ? m.category.name : null);
            return '<div style="border-bottom:1px solid #f0f0f0;padding:9px 0;display:flex;gap:10px;align-items:flex-start">' +
                '<div style="flex:1;min-width:0">' +
                '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:3px">' +
                movTypeBadge(m.type) +
                (catName ? '<span style="font-size:11px;color:#66746b">' + escapeHtml(catName) + '</span>' : '') +
                '</div>' +
                '<div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtml(m.description || '-') + '</div>' +
                '<div style="font-size:11px;color:#66746b;margin-top:2px">' + escapeHtml(date) + '</div>' +
                '</div>' +
                '<div style="font-size:15px;font-weight:900;color:' + amtColor + ';white-space:nowrap;padding-top:2px">' +
                amtSign + escapeHtml(fmt(amount)) +
                '</div>' +
                '</div>';
        }).join('');
    }

    function loadMovements(root, budgetId) {
        var budget = state.budgets.find(function (b) { return String(b.id) === String(budgetId); });
        if (!budget && state.currentBudget && String(state.currentBudget.id) === String(budgetId)) {
            budget = state.currentBudget;
        }
        state.movsBudgetId = budgetId;
        state.movsBudget   = budget || null;
        state.movements    = [];
        state.movsPage     = 1;
        state.movsLoading  = true;
        renderMovementsPanel(root);
        renderAdjCategoryOptions(root);

        fetchMovements(root);

        var panel = qs('[data-budget-movements-panel]', root);
        if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    function fetchMovements(root) {
        if (!state.movsBudgetId || !state.currentGroupId) { return; }
        state.movsLoading = true;
        renderMovementsPanel(root);

        var params = new URLSearchParams();
        params.set('page', state.movsPage);
        params.set('per_page', 15);
        if (state.movsTypeFilter) { params.set('type', state.movsTypeFilter); }

        window.CCApi.request(movsBudgetPath('/movements?' + params.toString()))
            .then(function (response) {
                state.movements    = response.data || [];
                state.movsTotal    = (response.meta && response.meta.total) || state.movements.length;
                state.movsPage     = (response.meta && response.meta.current_page) || state.movsPage;
                state.movsLastPage = (response.meta && response.meta.last_page) || 1;
                state.movsLoading  = false;
                renderMovementsPanel(root);
            })
            .catch(function (err) {
                state.movements   = [];
                state.movsLoading = false;
                renderMovementsPanel(root);
                showMovsMsg(root, 'danger', errMsg(err));
            });
    }

    function saveAdjustment(root, form) {
        if (!state.movsBudgetId || !state.currentGroupId) { return; }
        var amount = form.elements.amount ? form.elements.amount.value : '';
        var description = form.elements.description ? form.elements.description.value.trim() : '';
        if (!amount) { showAdjMsg(root, 'warning', 'Ingresá el monto.'); return; }
        if (!description) { showAdjMsg(root, 'warning', 'Ingresá una descripción.'); return; }

        var payload = {
            type: form.elements.type ? (form.elements.type.value || 'adjustment') : 'adjustment',
            amount: parseFloat(amount),
            description: description,
        };
        if (form.elements.category_id && form.elements.category_id.value) {
            payload.category_id = Number(form.elements.category_id.value);
        }

        state.movSaving = true;
        var btn = qs('[data-movs-adj-save]', root);
        if (btn) { btn.disabled = true; }
        clearAdjMsg(root);

        window.CCApi.request(movsBudgetPath('/adjustments'), { method: 'POST', body: payload })
            .then(function () {
                state.movSaving = false;
                if (btn) { btn.disabled = false; }
                form.reset();
                state.movsPage = 1;
                fetchMovements(root);
            })
            .catch(function (err) {
                state.movSaving = false;
                if (btn) { btn.disabled = false; }
                showAdjMsg(root, 'danger', errMsg(err));
            });
    }

    var ALERT_SEVERITY = {
        warning: { label: 'Advertencia', bg: '#fff3e0', color: '#b35c00', icon: '⚠' },
        danger:  { label: 'Crítico',     bg: '#f7e7e7', color: '#b33a3a', icon: '🚨' },
        info:    { label: 'Info',        bg: '#e7f3ff', color: '#1a5fb4', icon: 'ℹ' },
    };

    function alertsBudgetPath(extra) {
        return groupPath('/budgets/' + encodeURIComponent(state.alertsBudgetId) + (extra || ''));
    }

    function renderAlertsPanel(root) {
        var panel = qs('[data-budget-alerts-panel]', root);
        if (!panel) { return; }
        if (!state.alertsBudgetId) { panel.style.display = 'none'; return; }
        panel.style.display = '';

        var titleEl = qs('[data-budget-alerts-title]', root);
        var listEl  = qs('[data-budget-alerts-list]', root);
        var msgEl   = qs('[data-budget-alerts-message]', root);

        if (titleEl) {
            var b = state.alertsBudget;
            titleEl.textContent = 'Alertas' + (b ? ' — ' + monthLabel(b) : '');
        }
        if (msgEl) { msgEl.style.display = 'none'; }
        if (!listEl) { return; }

        if (state.alertsLoading) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">Cargando alertas...</p>';
            return;
        }
        if (!state.alerts.length) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">No hay alertas para este presupuesto.</p>';
            return;
        }

        listEl.innerHTML = state.alerts.map(function (a) {
            var sev = ALERT_SEVERITY[a.severity] || ALERT_SEVERITY.info;
            var isRead = !!a.read_at;
            var date = a.triggered_at ? a.triggered_at.substring(0, 10) : (a.created_at ? a.created_at.substring(0, 10) : '-');
            return '<div style="border:1px solid ' + (isRead ? '#e8efe8' : sev.color) + ';border-radius:8px;padding:10px 12px;margin-bottom:8px;background:' + (isRead ? '#fafdfb' : sev.bg) + ';opacity:' + (isRead ? '0.7' : '1') + '">' +
                '<div style="display:flex;align-items:flex-start;gap:8px">' +
                '<span style="font-size:18px;line-height:1;flex-shrink:0">' + sev.icon + '</span>' +
                '<div style="flex:1;min-width:0">' +
                '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:3px">' +
                '<span style="font-size:11px;font-weight:700;color:' + sev.color + '">' + escapeHtml(sev.label) + '</span>' +
                '<span style="font-size:11px;color:#66746b">' + escapeHtml(date) + '</span>' +
                (isRead ? '<span style="font-size:10px;color:#66746b;background:#f0f0f0;border-radius:999px;padding:1px 7px">Leída</span>' : '') +
                '</div>' +
                '<div style="font-size:13px;font-weight:600;margin-bottom:4px">' + escapeHtml(a.type || '') + '</div>' +
                '<div style="font-size:13px;color:#24252a">' + escapeHtml(a.message || '') + '</div>' +
                (a.threshold_pct != null ? '<div style="font-size:11px;color:#66746b;margin-top:3px">Umbral: ' + escapeHtml(String(a.threshold_pct)) + '%</div>' : '') +
                '</div>' +
                (!isRead
                    ? '<button type="button" class="btn-secondary-web btn-sm" data-alert-read="' + escapeHtml(String(a.id)) + '" style="flex-shrink:0;font-size:11px;white-space:nowrap">Marcar leída</button>'
                    : '') +
                '</div>' +
                '</div>';
        }).join('');
    }

    function loadAlerts(root, budgetId) {
        var budget = state.budgets.find(function (b) { return String(b.id) === String(budgetId); });
        if (!budget && state.currentBudget && String(state.currentBudget.id) === String(budgetId)) {
            budget = state.currentBudget;
        }
        state.alertsBudgetId = budgetId;
        state.alertsBudget   = budget || null;
        state.alerts         = [];
        state.alertsLoading  = true;
        renderAlertsPanel(root);

        var panel = qs('[data-budget-alerts-panel]', root);
        if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }

        window.CCApi.request(alertsBudgetPath('/alerts'))
            .then(function (response) {
                state.alerts        = response.data || [];
                state.alertsLoading = false;
                renderAlertsPanel(root);
            })
            .catch(function (err) {
                state.alerts        = [];
                state.alertsLoading = false;
                renderAlertsPanel(root);
                var msgEl = qs('[data-budget-alerts-message]', root);
                if (msgEl) { msgEl.className = 'alert alert-danger'; msgEl.textContent = errMsg(err); msgEl.style.display = 'block'; }
            });
    }

    function markAlertRead(root, alertId) {
        if (!state.alertsBudgetId || !state.currentGroupId) { return; }
        var btn = qs('[data-alert-read="' + alertId + '"]', root);
        if (btn) { btn.disabled = true; }

        window.CCApi.request(alertsBudgetPath('/alerts/' + encodeURIComponent(alertId) + '/read'), { method: 'PATCH' })
            .then(function () {
                var alert = state.alerts.find(function (a) { return String(a.id) === String(alertId); });
                if (alert) { alert.read_at = new Date().toISOString(); }
                renderAlertsPanel(root);
            })
            .catch(function (err) {
                if (btn) { btn.disabled = false; }
                var msgEl = qs('[data-budget-alerts-message]', root);
                if (msgEl) { msgEl.className = 'alert alert-danger'; msgEl.textContent = errMsg(err); msgEl.style.display = 'block'; }
            });
    }

    function bind(root) {
        var groupSel = qs('[data-budget-group]', root);
        var form = qs('[data-budget-form]', root);
        var resetBtn = qs('[data-budget-reset]', root);
        var refreshBtn = qs('[data-budget-refresh]', root);
        var prevBtn = qs('[data-budget-prev]', root);
        var nextBtn = qs('[data-budget-next]', root);

        if (groupSel) {
            groupSel.addEventListener('change', function () {
                state.currentGroupId = groupSel.value || null;
                state.page = 1;
                state.budgets = [];
                state.currentBudget = null;
                renderList(root);
                renderCurrentBudget(root);
                resetForm(root);
                if (state.currentGroupId) {
                    Promise.all([loadBudgets(root), loadCurrent(root)]);
                }
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                Promise.all([loadBudgets(root), loadCurrent(root)]);
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (state.page > 1) { state.page--; loadBudgets(root); }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (state.page < state.lastPage) { state.page++; loadBudgets(root); }
            });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                saveBudget(root, form);
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () { resetForm(root); });
        }

        // Summary panel close
        var summaryCloseBtn = qs('[data-budget-summary-close]', root);
        if (summaryCloseBtn) {
            summaryCloseBtn.addEventListener('click', function () {
                state.summaryBudgetId = null;
                renderSummaryPanel(root);
            });
        }

        // Reload projection button
        var reloadProjBtn = qs('[data-budget-reload-projection]', root);
        if (reloadProjBtn) {
            reloadProjBtn.addEventListener('click', function () { reloadProjection(root); });
        }

        // Categories panel close
        var catCloseBtn = qs('[data-budget-cats-close]', root);
        if (catCloseBtn) {
            catCloseBtn.addEventListener('click', function () {
                state.catsBudgetId = null;
                renderCategoriesPanel(root);
            });
        }

        // Categories form
        var catForm = qs('[data-budget-cat-form]', root);
        if (catForm) {
            catForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveCategory(root, catForm);
            });
        }
        var catResetBtn = qs('[data-budget-cat-reset]', root);
        if (catResetBtn) {
            catResetBtn.addEventListener('click', function () { resetCatForm(root); });
        }

        // Movements panel close
        var movsCloseBtn = qs('[data-budget-movs-close]', root);
        if (movsCloseBtn) {
            movsCloseBtn.addEventListener('click', function () {
                state.movsBudgetId = null;
                renderMovementsPanel(root);
            });
        }

        // Movements type filter
        var movsTypeFilter = qs('[data-movs-type-filter]', root);
        if (movsTypeFilter) {
            movsTypeFilter.addEventListener('change', function () {
                state.movsTypeFilter = movsTypeFilter.value;
                state.movsPage = 1;
                if (state.movsBudgetId) { fetchMovements(root); }
            });
        }

        // Movements refresh button
        var movsRefreshBtn = qs('[data-movs-refresh]', root);
        if (movsRefreshBtn) {
            movsRefreshBtn.addEventListener('click', function () {
                state.movsPage = 1;
                if (state.movsBudgetId) { fetchMovements(root); }
            });
        }

        // Movements pagination
        var movsPrevBtn = qs('[data-movs-prev]', root);
        if (movsPrevBtn) {
            movsPrevBtn.addEventListener('click', function () {
                if (state.movsPage > 1) {
                    state.movsPage -= 1;
                    fetchMovements(root);
                }
            });
        }
        var movsNextBtn = qs('[data-movs-next]', root);
        if (movsNextBtn) {
            movsNextBtn.addEventListener('click', function () {
                if (state.movsPage < state.movsLastPage) {
                    state.movsPage += 1;
                    fetchMovements(root);
                }
            });
        }

        // Alerts panel close
        var alertsCloseBtn = qs('[data-budget-alerts-close]', root);
        if (alertsCloseBtn) {
            alertsCloseBtn.addEventListener('click', function () {
                state.alertsBudgetId = null;
                renderAlertsPanel(root);
            });
        }

        // Alerts refresh
        var alertsRefreshBtn = qs('[data-alerts-refresh]', root);
        if (alertsRefreshBtn) {
            alertsRefreshBtn.addEventListener('click', function () {
                if (state.alertsBudgetId) { loadAlerts(root, state.alertsBudgetId); }
            });
        }

        // Adjustment form
        var adjForm = qs('[data-budget-adj-form]', root);
        if (adjForm) {
            adjForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveAdjustment(root, adjForm);
            });
        }

        // Table + current card + categories list actions (delegated from root)
        root.addEventListener('click', function (event) {
            var summBtn    = event.target.closest('[data-budget-summary]');
            var catsBtn    = event.target.closest('[data-budget-cats]');
            var movsBtn    = event.target.closest('[data-budget-movs]');
            var alertsBtn  = event.target.closest('[data-budget-alerts]');
            var alertReadBtn = event.target.closest('[data-alert-read]');
            var editBtn    = event.target.closest('[data-budget-edit]');
            var delBtn     = event.target.closest('[data-budget-delete]');
            var catEditBtn = event.target.closest('[data-cat-edit]');
            var catDelBtn  = event.target.closest('[data-cat-delete]');

            if (summBtn)      { loadSummary(root, summBtn.getAttribute('data-budget-summary')); return; }
            if (catsBtn)      { loadCategories(root, catsBtn.getAttribute('data-budget-cats')); return; }
            if (movsBtn)      { loadMovements(root, movsBtn.getAttribute('data-budget-movs')); return; }
            if (alertsBtn)    { loadAlerts(root, alertsBtn.getAttribute('data-budget-alerts')); return; }
            if (alertReadBtn) { markAlertRead(root, alertReadBtn.getAttribute('data-alert-read')); return; }
            if (catEditBtn) {
                var cid = catEditBtn.getAttribute('data-cat-edit');
                var cat = state.categories.find(function (c) { return String(c.id) === String(cid); });
                if (cat) { fillCatForm(root, cat); }
                return;
            }
            if (catDelBtn) { deleteCategory(root, catDelBtn.getAttribute('data-cat-delete')); return; }
            if (editBtn) {
                var id = editBtn.getAttribute('data-budget-edit');
                var budget = state.budgets.find(function (b) { return String(b.id) === String(id); });
                if (!budget && state.currentBudget && String(state.currentBudget.id) === String(id)) {
                    budget = state.currentBudget;
                }
                if (budget) { fillForm(root, budget); }
            }
            if (delBtn) {
                deleteBudget(root, delBtn.getAttribute('data-budget-delete'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-budget]');
        if (!root) { return; }
        bind(root);
        renderSummaryPanel(root);
        renderCategoriesPanel(root);
        renderMovementsPanel(root);
        renderAlertsPanel(root);
        loadGroups(root).then(function () {
            if (state.currentGroupId) {
                return Promise.all([loadBudgets(root), loadCurrent(root)]);
            }
        });
    });
})(window, document);
