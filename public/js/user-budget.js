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

        // Table + current card actions (delegated from root)
        root.addEventListener('click', function (event) {
            var summBtn = event.target.closest('[data-budget-summary]');
            var editBtn = event.target.closest('[data-budget-edit]');
            var delBtn = event.target.closest('[data-budget-delete]');

            if (summBtn) {
                loadSummary(root, summBtn.getAttribute('data-budget-summary'));
                return;
            }
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
        loadGroups(root).then(function () {
            if (state.currentGroupId) {
                return Promise.all([loadBudgets(root), loadCurrent(root)]);
            }
        });
    });
})(window, document);
