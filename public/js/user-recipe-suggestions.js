(function (window, document) {
    'use strict';

    var root = null;

    // ─── Constants ───────────────────────────────────────────────────────────────

    var TAB_ORDER = ['suggestions', 'available', 'almost_available', 'by_expiring', 'by_budget', 'by_objectives'];

    var TAB_META = {
        suggestions:      { label: 'Sugerencias',        needsGroup: false },
        available:        { label: 'Puedo cocinar',      needsGroup: true  },
        almost_available: { label: 'Casi posibles',      needsGroup: true  },
        by_expiring:      { label: 'Por vencer',         needsGroup: true  },
        by_budget:        { label: 'Por presupuesto',    needsGroup: true  },
        by_objectives:    { label: 'Por objetivos',      needsGroup: true  },
    };

    var REASON_LABELS = {
        available_with_stock:        'Con tu stock',
        almost_available_with_stock: 'Casi posible',
        uses_expiring_ingredients:   'Ingredientes por vencer',
        official_recipe:             'Receta oficial',
        within_budget:               'Dentro del presupuesto',
        matches_objectives:          'Según objetivos',
    };

    var AVAIL_META = {
        possible:        { bg: '#e7f7f2', color: '#04ac85', icon: '✓' },
        almost_possible: { bg: '#fff8e1', color: '#b88a00', icon: '~' },
        not_possible:    { bg: '#f7e7e7', color: '#b33a3a', icon: '✕' },
    };

    // ─── State ───────────────────────────────────────────────────────────────────

    var state = {
        familyGroups:    [],
        selectedGroupId: null,
        activeTab:       'suggestions',
        loading:         false,
        expiringDays:    7,
        maxBudget:       '',
        tabs: {
            suggestions:      { results: [], page: 1, lastPage: 1, total: 0, loaded: false, note: '' },
            available:        { results: [], page: 1, lastPage: 1, total: 0, loaded: false, note: '' },
            almost_available: { results: [], page: 1, lastPage: 1, total: 0, loaded: false, note: '' },
            by_expiring:      { results: [], page: 1, lastPage: 1, total: 0, loaded: false, note: '' },
            by_budget:        { results: [], page: 1, lastPage: 1, total: 0, loaded: false, note: '' },
            by_objectives:    { results: [], page: 1, lastPage: 1, total: 0, loaded: false, note: '' },
        },
        selectedRecipe: null,
    };

    // ─── Helpers ─────────────────────────────────────────────────────────────────

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

    function fmtMinutes(m) {
        var n = parseInt(m, 10);
        if (!n) { return '—'; }
        if (n < 60) { return n + ' min'; }
        var h = Math.floor(n / 60);
        var rm = n % 60;
        return rm ? h + 'h ' + rm + 'min' : h + 'h';
    }

    function diffLabel(d) {
        if (d === 'easy')   { return 'Fácil'; }
        if (d === 'medium') { return 'Media'; }
        if (d === 'hard')   { return 'Difícil'; }
        return d || '';
    }

    function buildPath(tabKey) {
        var tab = state.tabs[tabKey];
        var gid = state.selectedGroupId;
        var common = 'page=' + tab.page + '&per_page=12';

        if (tabKey === 'suggestions') {
            var u = '/recipes/suggestions?' + common;
            if (gid) { u += '&family_group_id=' + encodeURIComponent(gid); }
            return u;
        }
        if (tabKey === 'available') {
            return '/family-groups/' + gid + '/recipes/available?' + common;
        }
        if (tabKey === 'almost_available') {
            return '/family-groups/' + gid + '/recipes/almost-available?' + common;
        }
        if (tabKey === 'by_expiring') {
            return '/family-groups/' + gid + '/recipes/by-expiring-stock?days=' + state.expiringDays + '&' + common;
        }
        if (tabKey === 'by_budget') {
            var bu = '/family-groups/' + gid + '/recipes/by-budget?' + common;
            if (state.maxBudget) { bu += '&max_cost=' + encodeURIComponent(state.maxBudget); }
            return bu;
        }
        if (tabKey === 'by_objectives') {
            return '/family-groups/' + gid + '/recipes/by-objectives?' + common;
        }
        return '';
    }

    // ─── Render ──────────────────────────────────────────────────────────────────

    function renderTabs() {
        var el = qs('[data-sugg-tabs]', root);
        if (!el) { return; }
        el.innerHTML = TAB_ORDER.map(function (key) {
            var meta = TAB_META[key];
            var active = key === state.activeTab;
            return '<button type="button" class="audit-tab' + (active ? ' active' : '') + '" data-sugg-tab="' + key + '">' +
                escapeHtml(meta.label) + '</button>';
        }).join('');
    }

    function renderGroupSelector() {
        var sel = qs('[data-sugg-group]', root);
        if (!sel) { return; }
        if (state.familyGroups.length <= 1) {
            sel.style.display = 'none';
            return;
        }
        sel.style.display = '';
        sel.innerHTML = state.familyGroups.map(function (g) {
            return '<option value="' + g.id + '"' + (String(g.id) === String(state.selectedGroupId) ? ' selected' : '') + '>' +
                escapeHtml(g.name) + '</option>';
        }).join('');
    }

    function renderExtraControls() {
        var el = qs('[data-sugg-extra]', root);
        if (!el) { return; }

        if (state.activeTab === 'by_expiring') {
            el.innerHTML =
                '<div style="display:flex;align-items:center;gap:8px">' +
                '<label style="font-size:12px;color:#697681">Vencen en los próximos:</label>' +
                '<input type="number" class="form-control" data-sugg-days min="1" max="30" value="' + state.expiringDays + '" style="max-width:70px;font-size:13px">' +
                '<span style="font-size:12px;color:#697681">días</span>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-sugg-days-apply>Actualizar</button>' +
                '</div>';
            return;
        }

        if (state.activeTab === 'by_budget') {
            el.innerHTML =
                '<div style="display:flex;align-items:center;gap:8px">' +
                '<label style="font-size:12px;color:#697681">Presupuesto máx.:</label>' +
                '<input type="number" class="form-control" data-sugg-budget min="0" step="0.01" value="' + escapeHtml(state.maxBudget) + '" placeholder="Usar presupuesto del mes" style="max-width:160px;font-size:13px">' +
                '<button type="button" class="btn-secondary-web btn-sm" data-sugg-budget-apply>Aplicar</button>' +
                '</div>';
            return;
        }

        el.innerHTML = '';
    }

    function renderNote() {
        var msgEl = qs('[data-sugg-message]', root);
        if (!msgEl) { return; }
        var note = state.tabs[state.activeTab].note;
        if (note) {
            msgEl.textContent = note;
            msgEl.style.display = 'block';
            msgEl.style.background = '#f0f4ff';
            msgEl.style.color = '#2f5fc4';
        } else {
            msgEl.style.display = 'none';
        }
    }

    function availBadgeHtml(r) {
        if (!r.availability) { return ''; }
        var meta = AVAIL_META[r.availability];
        if (!meta) { return ''; }
        var label = meta.icon;
        if (r.availability === 'possible' && r.max_possible_servings !== null && r.max_possible_servings !== undefined) {
            label += ' ' + r.max_possible_servings + ' porc.';
        } else if (r.availability === 'almost_possible' && r.coverage_percentage !== null && r.coverage_percentage !== undefined) {
            label += ' ' + Math.round(parseFloat(r.coverage_percentage)) + '%';
        }
        return '<span style="display:inline-block;background:' + meta.bg + ';color:' + meta.color + ';border-radius:4px;padding:2px 7px;font-size:11px;font-weight:700;margin-right:4px">' +
            escapeHtml(label) + '</span>';
    }

    function scoreBadgeHtml(r) {
        if (r.score === null || r.score === undefined) { return ''; }
        return '<span style="display:inline-block;background:#f0f4f8;color:#697681;border-radius:4px;padding:2px 7px;font-size:11px;margin-right:4px">' +
            '★ ' + r.score + '</span>';
    }

    function expiringBadgeHtml(r) {
        if (!r.expiring_ingredients) { return ''; }
        return '<span style="display:inline-block;background:#fff3e0;color:#b85c00;border-radius:4px;padding:2px 7px;font-size:11px">Por vencer: ' + r.expiring_ingredients + '</span>';
    }

    function renderCard(r) {
        var totalTime = (parseInt(r.prep_time_minutes, 10) || 0) + (parseInt(r.cook_time_minutes, 10) || 0);
        var timeStr = totalTime ? fmtMinutes(totalTime) : '';
        var diff = r.difficulty ? diffLabel(r.difficulty) : '';

        var badges = availBadgeHtml(r) + scoreBadgeHtml(r) + expiringBadgeHtml(r);
        if (r.is_official) {
            badges += '<span style="display:inline-block;background:#e7f7f2;color:#04ac85;border-radius:4px;padding:2px 7px;font-size:11px">Oficial</span>';
        }

        return '<div data-sugg-card data-id="' + r.id + '" ' +
            'style="border:1px solid #dde3e8;border-radius:8px;padding:13px;cursor:pointer;background:#fff;transition:box-shadow 0.15s" ' +
            'onmouseover="this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.08)\'" onmouseout="this.style.boxShadow=\'\'">' +
            '<div style="font-size:14px;font-weight:900;margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(r.name) + '</div>' +
            '<div style="display:flex;gap:10px;font-size:12px;color:#697681;margin-bottom:6px">' +
            (timeStr ? '<span>⏱ ' + timeStr + '</span>' : '') +
            (r.servings ? '<span>' + escapeHtml(r.servings) + ' porc.</span>' : '') +
            (diff ? '<span>' + escapeHtml(diff) + '</span>' : '') +
            '</div>' +
            (badges ? '<div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:3px">' + badges + '</div>' : '') +
            '</div>';
    }

    function renderList() {
        var listEl = qs('[data-sugg-list]', root);
        if (!listEl) { return; }

        if (state.loading) {
            listEl.innerHTML = '<p class="muted" style="font-size:13px">Cargando...</p>';
            renderPagination();
            return;
        }

        var tab = state.tabs[state.activeTab];
        var needsGroup = TAB_META[state.activeTab].needsGroup;

        if (needsGroup && !state.selectedGroupId) {
            listEl.innerHTML =
                '<p class="muted" style="font-size:13px">Configurá un grupo familiar para ver estas recomendaciones.</p>';
            renderPagination();
            return;
        }

        if (!tab.results.length) {
            listEl.innerHTML =
                '<p class="muted" style="font-size:13px">No hay recomendaciones disponibles para esta selección.</p>';
            renderPagination();
            return;
        }

        listEl.innerHTML = tab.results.map(renderCard).join('');
        renderPagination();
    }

    function renderPagination() {
        var paginEl = qs('[data-sugg-pagination]', root);
        var pageEl  = qs('[data-sugg-page]', root);
        var prevBtn = qs('[data-sugg-prev]', root);
        var nextBtn = qs('[data-sugg-next]', root);
        var tab = state.tabs[state.activeTab];

        if (!paginEl) { return; }

        if (!tab.results.length || tab.lastPage <= 1) {
            paginEl.style.display = 'none';
            return;
        }

        paginEl.style.display = '';
        if (pageEl) { pageEl.textContent = 'Pág ' + tab.page + ' / ' + tab.lastPage; }
        if (prevBtn) { prevBtn.disabled = tab.page <= 1; }
        if (nextBtn) { nextBtn.disabled = tab.page >= tab.lastPage; }
    }

    function renderDetail(r) {
        var detailEl = qs('[data-sugg-detail]', root);
        if (!detailEl) { return; }

        var totalTime = (parseInt(r.prep_time_minutes, 10) || 0) + (parseInt(r.cook_time_minutes, 10) || 0);

        var availBlock = '';
        if (r.availability) {
            var am = AVAIL_META[r.availability];
            if (am) {
                var availLabel = r.availability === 'possible' ? 'Podés cocinar esta receta' :
                    (r.availability === 'almost_possible' ? 'Podés cocinar con menos porciones' : 'Stock insuficiente');
                availBlock =
                    '<div style="background:' + am.bg + ';color:' + am.color + ';border-radius:6px;padding:8px 11px;margin-bottom:10px;font-size:13px;font-weight:700">' +
                    am.icon + ' ' + escapeHtml(availLabel) +
                    (r.max_possible_servings !== null && r.max_possible_servings !== undefined
                        ? '<div style="font-size:11px;font-weight:400;margin-top:2px">' + r.max_possible_servings + ' porciones posibles</div>' : '') +
                    '</div>';
            }
        }

        var reasonsBlock = '';
        if (r.reasons && r.reasons.length) {
            reasonsBlock =
                '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px">' +
                r.reasons.map(function (rc) {
                    var lbl = REASON_LABELS[rc] || rc;
                    return '<span style="background:#f0f4f8;color:#697681;border-radius:999px;padding:3px 8px;font-size:11px">' +
                        escapeHtml(lbl) + '</span>';
                }).join('') +
                '</div>';
        }

        var scoreBlock = (r.score !== null && r.score !== undefined)
            ? '<div style="font-size:12px;color:#697681;margin-top:6px">Puntuación: <strong>' + r.score + '</strong></div>'
            : '';

        var expiringBlock = (r.expiring_ingredients)
            ? '<div style="font-size:12px;color:#b85c00;margin-top:4px">Ingredientes por vencer: <strong>' + r.expiring_ingredients + '</strong></div>'
            : '';

        detailEl.innerHTML =
            '<h2 style="font-size:15px;font-weight:900;margin:0 0 10px">' + escapeHtml(r.name) + '</h2>' +
            availBlock +
            '<table style="width:100%;border-collapse:collapse;font-size:13px">' +
            (r.difficulty ? '<tr><td style="padding:5px 0;color:#697681">Dificultad</td><td style="padding:5px 0;font-weight:700">' + escapeHtml(diffLabel(r.difficulty)) + '</td></tr>' : '') +
            (r.prep_time_minutes ? '<tr><td style="padding:5px 0;color:#697681">Preparación</td><td style="padding:5px 0">' + fmtMinutes(r.prep_time_minutes) + '</td></tr>' : '') +
            (r.cook_time_minutes ? '<tr><td style="padding:5px 0;color:#697681">Cocción</td><td style="padding:5px 0">' + fmtMinutes(r.cook_time_minutes) + '</td></tr>' : '') +
            (totalTime ? '<tr><td style="padding:5px 0;color:#697681">Tiempo total</td><td style="padding:5px 0">' + fmtMinutes(totalTime) + '</td></tr>' : '') +
            (r.servings ? '<tr><td style="padding:5px 0;color:#697681">Porciones</td><td style="padding:5px 0">' + escapeHtml(r.servings) + '</td></tr>' : '') +
            '</table>' +
            scoreBlock + expiringBlock + reasonsBlock +
            (r.is_official ? '<div style="margin-top:8px"><span style="background:#e7f7f2;color:#04ac85;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:700">Receta oficial</span></div>' : '') +
            '<div style="margin-top:14px">' +
            '<a href="/web/recipes" style="font-size:13px;color:#04ac85;text-decoration:underline">Ver en Mis recetas</a>' +
            '</div>';
    }

    // ─── Feedback ────────────────────────────────────────────────────────────────

    function showErr(text) {
        var msgEl = qs('[data-sugg-message]', root);
        if (!msgEl) { return; }
        msgEl.textContent = text;
        msgEl.style.display = 'block';
        msgEl.style.background = '#f7e7e7';
        msgEl.style.color = '#b33a3a';
    }

    // ─── API ─────────────────────────────────────────────────────────────────────

    function fetchTab(tabKey) {
        var meta = TAB_META[tabKey];

        if (meta.needsGroup && !state.selectedGroupId) {
            state.tabs[tabKey].loaded = true;
            renderList();
            return;
        }

        var path = buildPath(tabKey);
        if (!path) { return; }

        state.loading = true;
        qs('[data-sugg-message]', root) && (qs('[data-sugg-message]', root).style.display = 'none');
        renderList();

        window.CCApi.request(endpoint(path))
            .then(function (res) {
                var tab = state.tabs[tabKey];
                tab.results  = res.data || [];
                var meta2 = res.meta || {};
                tab.total    = meta2.total || tab.results.length;
                tab.lastPage = meta2.last_page || 1;
                tab.note     = meta2.note || '';
                tab.loaded   = true;
                state.loading = false;
                if (state.activeTab === tabKey) {
                    renderNote();
                    renderList();
                }
            })
            .catch(function (err) {
                var tab = state.tabs[tabKey];
                tab.results  = [];
                tab.loaded   = true;
                state.loading = false;
                if (state.activeTab === tabKey) {
                    renderList();
                    var status = err && err.status;
                    if (status === 403) {
                        showErr('No tenés acceso a este grupo familiar.');
                    } else if (status === 404) {
                        showErr('Grupo familiar no encontrado.');
                    } else if (status !== 401) {
                        showErr((err && err.payload && err.payload.error && err.payload.error.message) || 'Error al cargar recomendaciones.');
                    }
                }
            });
    }

    function reloadCurrentTab() {
        var tabKey = state.activeTab;
        state.tabs[tabKey].loaded = false;
        fetchTab(tabKey);
    }

    function switchTab(tabKey) {
        state.activeTab = tabKey;
        state.selectedRecipe = null;

        renderTabs();
        renderExtraControls();

        var msgEl = qs('[data-sugg-message]', root);
        if (msgEl) { msgEl.style.display = 'none'; }

        var detailEl = qs('[data-sugg-detail]', root);
        if (detailEl) {
            detailEl.innerHTML = '<p class="muted" style="font-size:13px">Seleccioná una receta para ver el detalle.</p>';
        }

        var tab = state.tabs[tabKey];
        if (!tab.loaded) {
            fetchTab(tabKey);
        } else {
            renderNote();
            renderList();
        }
    }

    function loadGroupsThenFetch() {
        window.CCApi.request(endpoint('/family-groups?per_page=20'))
            .then(function (res) {
                state.familyGroups = res.data || [];
                if (state.familyGroups.length) {
                    state.selectedGroupId = state.familyGroups[0].id;
                }
                renderGroupSelector();
                fetchTab(state.activeTab);
            })
            .catch(function () {
                state.familyGroups    = [];
                state.selectedGroupId = null;
                renderGroupSelector();
                fetchTab(state.activeTab);
            });
    }

    // ─── Events ──────────────────────────────────────────────────────────────────

    function resetAllTabs() {
        TAB_ORDER.forEach(function (k) {
            state.tabs[k] = { results: [], page: 1, lastPage: 1, total: 0, loaded: false, note: '' };
        });
    }

    function bindEvents() {
        if (!root) { return; }

        root.addEventListener('click', function (e) {
            var t = e.target;

            // Tab switch
            var tabBtn = t.closest('[data-sugg-tab]');
            if (tabBtn) {
                switchTab(tabBtn.getAttribute('data-sugg-tab'));
                return;
            }

            // Card click → detail
            var card = t.closest('[data-sugg-card]');
            if (card) {
                var rid = card.getAttribute('data-id');
                var tab = state.tabs[state.activeTab];
                var recipe = tab.results.filter(function (r) { return String(r.id) === String(rid); })[0];
                if (recipe) {
                    state.selectedRecipe = recipe;
                    renderDetail(recipe);
                }
                return;
            }

            // Pagination
            if (t.closest('[data-sugg-prev]')) {
                var ctab = state.tabs[state.activeTab];
                if (ctab.page > 1) {
                    ctab.page--;
                    ctab.loaded = false;
                    fetchTab(state.activeTab);
                }
                return;
            }
            if (t.closest('[data-sugg-next]')) {
                var ntab = state.tabs[state.activeTab];
                if (ntab.page < ntab.lastPage) {
                    ntab.page++;
                    ntab.loaded = false;
                    fetchTab(state.activeTab);
                }
                return;
            }

            // Expiring days apply
            if (t.closest('[data-sugg-days-apply]')) {
                var daysInput = qs('[data-sugg-days]', root);
                if (daysInput) {
                    var d = parseInt(daysInput.value, 10);
                    if (d >= 1 && d <= 30) {
                        state.expiringDays = d;
                        resetAllTabs();
                        reloadCurrentTab();
                    }
                }
                return;
            }

            // Budget apply
            if (t.closest('[data-sugg-budget-apply]')) {
                var budgetInput = qs('[data-sugg-budget]', root);
                if (budgetInput) {
                    state.maxBudget = budgetInput.value;
                    state.tabs['by_budget'].loaded = false;
                    state.tabs['by_budget'].page = 1;
                    fetchTab('by_budget');
                }
                return;
            }
        });

        // Group selector change
        root.addEventListener('change', function (e) {
            var sel = e.target.closest('[data-sugg-group]');
            if (sel) {
                state.selectedGroupId = sel.value;
                resetAllTabs();
                switchTab(state.activeTab);
            }
        });
    }

    // ─── Boot ────────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        root = qs('[data-recipe-sugg]');
        if (!root) { return; }

        renderTabs();
        renderExtraControls();
        bindEvents();
        loadGroupsThenFetch();
    });

})(window, document);
