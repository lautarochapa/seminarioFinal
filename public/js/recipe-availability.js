(function (window, document) {
    'use strict';

    var state = {
        el: null,
        recipeId: null,
        familyGroups: [],
        selectedGroupId: null,
        availData: null,
        loading: false,
        missingVisible: false,
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
        possible:        { label: 'Podés cocinar esta receta',          bg: '#e7f7f2', color: '#04ac85', icon: '✓' },
        almost_possible: { label: 'Podés cocinar con menos porciones',  bg: '#fff8e1', color: '#b88a00', icon: '~' },
        not_possible:    { label: 'No podés cocinar esta receta',       bg: '#f7e7e7', color: '#b33a3a', icon: '✕' },
    };

    // ─── Helpers ────────────────────────────────────────────────────────────────

    function fmtQty(n) {
        var f = parseFloat(n);
        if (isNaN(f)) { return '0'; }
        return f % 1 === 0 ? String(parseInt(f, 10)) : String(parseFloat(f.toFixed(2)));
    }

    function barColor(pct) {
        if (pct >= 100) { return '#04ac85'; }
        if (pct >= 50)  { return '#b88a00'; }
        return '#b33a3a';
    }

    function extractMsg(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message) || 'Error inesperado.';
    }

    // ─── Render ─────────────────────────────────────────────────────────────────

    function renderGroupSelector() {
        if (state.familyGroups.length <= 1) { return ''; }
        return '<select data-avail-group class="form-control" style="margin-bottom:8px;font-size:12px">' +
            state.familyGroups.map(function (g) {
                return '<option value="' + g.id + '"' + (String(g.id) === String(state.selectedGroupId) ? ' selected' : '') + '>' +
                    escapeHtml(g.name) + '</option>';
            }).join('') +
            '</select>';
    }

    function renderContent() {
        var header =
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8">' +
            '<h3 style="margin:0;font-size:14px;font-weight:900">Disponibilidad con mi stock</h3>' +
            '</div>';

        var msgDiv = '<div data-avail-msg style="display:none;font-size:12px;padding:5px 9px;border-radius:4px;margin-top:6px"></div>';

        if (!state.familyGroups.length) {
            return header +
                '<p class="muted" style="font-size:13px;margin:6px 0">Configurá un grupo familiar para ver disponibilidad.</p>' +
                msgDiv;
        }

        var groupSelector = renderGroupSelector();

        var d = state.availData;
        if (!d) {
            return header + groupSelector +
                '<p class="muted" style="font-size:13px;margin:6px 0">Sin datos de disponibilidad.</p>' +
                msgDiv;
        }

        // Status block
        var meta = STATUS_META[d.status] || STATUS_META.not_possible;
        var possibleServings  = d.possible_servings !== undefined ? d.possible_servings : null;
        var requestedServings = d.requested_servings !== undefined ? d.requested_servings : (d.servings || null);
        var covPct = d.coverage_percentage !== null && d.coverage_percentage !== undefined
            ? Math.round(parseFloat(d.coverage_percentage))
            : null;

        var servingsLine = '';
        if (possibleServings !== null && requestedServings) {
            servingsLine = '<div style="font-size:12px;margin-top:3px;opacity:0.85">' +
                escapeHtml(possibleServings) + ' de ' + escapeHtml(requestedServings) + ' porciones' +
                (covPct !== null ? ' (' + covPct + '%)' : '') +
                '</div>';
        }

        var statusBlock =
            '<div style="background:' + meta.bg + ';color:' + meta.color + ';border-radius:6px;padding:9px 12px;margin-bottom:8px">' +
            '<strong style="font-size:13px">' + meta.icon + ' ' + escapeHtml(meta.label) + '</strong>' +
            servingsLine +
            '</div>';

        // Ingredients breakdown
        var ingredients = d.ingredients || [];
        var missingItems = ingredients.filter(function (i) { return !i.is_available; });
        var availCount   = ingredients.length - missingItems.length;

        var ingrSummary = ingredients.length
            ? '<div style="font-size:12px;color:#697681;margin-bottom:6px">' +
              escapeHtml(availCount) + ' de ' + escapeHtml(ingredients.length) + ' ingredientes disponibles</div>'
            : '';

        var missingSection = '';
        if (missingItems.length) {
            var toggleLabel = state.missingVisible
                ? '▲ Ocultar faltantes'
                : '▼ Ver ingredientes faltantes (' + missingItems.length + ')';

            var missingList = '';
            if (state.missingVisible) {
                missingList = '<div style="margin-top:4px">' +
                    missingItems.map(function (item) {
                        var reqQty   = item.required_quantity !== null && item.required_quantity !== undefined
                            ? parseFloat(item.required_quantity) : null;
                        var availQty = item.available_quantity !== null && item.available_quantity !== undefined
                            ? parseFloat(item.available_quantity) : null;
                        var itemCov  = item.coverage_percentage !== null && item.coverage_percentage !== undefined
                            ? Math.round(parseFloat(item.coverage_percentage)) : 0;
                        var reqUnit  = escapeHtml(item.required_unit || '');
                        var availUnit = escapeHtml(item.available_unit || item.required_unit || '');

                        var qtyLine = '';
                        if (availQty !== null && reqQty !== null) {
                            qtyLine = 'Tenés ' + fmtQty(availQty) + ' ' + availUnit +
                                ' de ' + fmtQty(reqQty) + ' ' + reqUnit + ' necesarios';
                        } else if (reqQty !== null) {
                            qtyLine = 'Necesitás ' + fmtQty(reqQty) + ' ' + reqUnit;
                        }

                        var barW = Math.min(100, Math.max(0, itemCov));
                        var bar = '<div style="background:#eee;border-radius:3px;height:4px;margin-top:4px">' +
                            '<div style="background:' + barColor(itemCov) + ';height:4px;border-radius:3px;width:' + barW + '%"></div>' +
                            '</div>';

                        return '<div style="padding:6px 0;border-bottom:1px solid #f5f5f5">' +
                            '<div style="display:flex;justify-content:space-between;align-items:baseline;font-size:12px">' +
                            '<strong>' + escapeHtml(item.ingredient_name || '-') + '</strong>' +
                            '<span style="color:' + barColor(itemCov) + ';font-size:11px">' + itemCov + '%</span>' +
                            '</div>' +
                            (qtyLine ? '<div class="muted" style="font-size:11px;margin-top:2px">' + qtyLine + '</div>' : '') +
                            bar +
                            '</div>';
                    }).join('') +
                    '</div>';
            }

            missingSection =
                '<button type="button" data-avail-toggle class="btn-ghost btn-sm" style="width:100%;text-align:left;font-size:12px;margin-bottom:2px">' +
                toggleLabel + '</button>' + missingList;
        }

        // Suggestion for almost_possible
        var suggestion = '';
        if (d.status === 'almost_possible' && possibleServings !== null && possibleServings > 0) {
            suggestion =
                '<div style="background:#fff8e1;border-radius:6px;padding:7px 10px;margin-top:8px;font-size:12px;color:#b88a00">' +
                'Con tu stock podés hacer <strong>' + escapeHtml(possibleServings) +
                ' porci' + (possibleServings === 1 ? 'ón' : 'ones') + '</strong> de esta receta.' +
                '</div>';
        }

        return header + groupSelector + statusBlock + ingrSummary + missingSection + suggestion + msgDiv;
    }

    function render() {
        if (!state.el) { return; }

        if (state.loading) {
            state.el.innerHTML =
                '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8;color:#697681;font-size:13px">Verificando disponibilidad...</div>';
            return;
        }

        state.el.innerHTML = renderContent();
        bindEvents(state.el);
    }

    // ─── Feedback ───────────────────────────────────────────────────────────────

    function showMsg(type, text) {
        var el = qs('[data-avail-msg]', state.el);
        if (!el) { return; }
        el.textContent = text;
        el.style.display = 'block';
        el.style.background = type === 'ok' ? '#e7f7f2' : '#f7e7e7';
        el.style.color      = type === 'ok' ? '#04ac85' : '#b33a3a';
        if (type === 'ok') { setTimeout(function () { if (el) { el.style.display = 'none'; } }, 4000); }
    }

    // ─── API calls ──────────────────────────────────────────────────────────────

    function fetchAvailability() {
        state.loading = true;
        render();

        var url = endpoint('/recipes/' + state.recipeId + '/availability');
        if (state.selectedGroupId) {
            url += '?family_group_id=' + encodeURIComponent(state.selectedGroupId);
        }

        window.CCApi.request(url)
            .then(function (res) {
                state.availData = res.data || null;
                state.loading = false;
                render();
            })
            .catch(function (err) {
                state.availData = null;
                state.loading = false;
                render();
                var status = err && err.status;
                if (status && status !== 404 && status !== 403) {
                    showMsg('err', extractMsg(err));
                }
            });
    }

    function loadGroupsThenFetch() {
        state.loading = true;
        render();

        window.CCApi.request(endpoint('/family-groups?per_page=20'))
            .then(function (res) {
                state.familyGroups = res.data || [];
                if (state.familyGroups.length) {
                    state.selectedGroupId = state.familyGroups[0].id;
                    fetchAvailability();
                } else {
                    state.loading = false;
                    render();
                }
            })
            .catch(function () {
                state.familyGroups = [];
                state.loading = false;
                render();
            });
    }

    // ─── Events ─────────────────────────────────────────────────────────────────

    function bindEvents(el) {
        var sel = qs('[data-avail-group]', el);
        if (sel) {
            sel.addEventListener('change', function () {
                state.selectedGroupId  = sel.value;
                state.availData        = null;
                state.missingVisible   = false;
                fetchAvailability();
            });
        }

        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-avail-toggle]')) {
                state.missingVisible = !state.missingVisible;
                render();
            }
        });
    }

    // ─── Public API ─────────────────────────────────────────────────────────────

    function mount(containerEl, recipeId) {
        state.el             = containerEl;
        state.recipeId       = recipeId;
        state.familyGroups   = [];
        state.selectedGroupId = null;
        state.availData      = null;
        state.loading        = false;
        state.missingVisible = false;

        loadGroupsThenFetch();
    }

    window.RecipeAvailability = { mount: mount };

})(window, document);
