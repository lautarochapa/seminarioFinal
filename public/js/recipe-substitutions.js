(function (window, document) {
    'use strict';

    var state = {
        el:            null,
        recipeId:      null,
        data:          null,
        loading:       false,
        groupId:       null,
        groups:        [],
        groupsLoaded:  false,
        expanded:      {},   // { ingredientId: bool }
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined) { return ''; }
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function fmtQty(n) {
        var f = parseFloat(n);
        if (isNaN(f)) { return '0'; }
        return f % 1 === 0 ? String(parseInt(f, 10)) : String(parseFloat(f.toFixed(2)));
    }

    // ─── Render ──────────────────────────────────────────────────────────────────

    function renderAlternative(alt) {
        var stockBadge = '';
        if (alt.in_stock === true) {
            stockBadge = '<span style="background:#e7f7f2;color:#04ac85;border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;margin-left:5px">En stock</span>';
        } else if (alt.in_stock === false) {
            stockBadge = '<span style="background:#f7e7e7;color:#b33a3a;border-radius:4px;padding:1px 6px;font-size:10px;margin-left:5px">Sin stock</span>';
        }

        var recipeSpecificBadge = alt.recipe_specific
            ? '<span style="background:#f0f4ff;color:#2f5fc4;border-radius:4px;padding:1px 6px;font-size:10px;margin-left:5px">Sugerencia</span>'
            : '';

        var qtyLine = (alt.converted_quantity && alt.converted_unit_symbol)
            ? '<span style="color:#697681;font-size:11px;margin-left:6px">→ usar ' +
              escapeHtml(fmtQty(alt.converted_quantity)) + ' ' + escapeHtml(alt.converted_unit_symbol) + '</span>'
            : '';

        var reasonLine = alt.reason
            ? '<div style="font-size:11px;color:#697681;margin-top:2px;margin-left:12px;font-style:italic">' + escapeHtml(alt.reason) + '</div>'
            : '';

        return '<div style="padding:5px 0 3px;border-bottom:1px solid #f5f5f5">' +
            '<div style="display:flex;align-items:center;flex-wrap:wrap;font-size:12px">' +
            '<span style="color:#24252a;font-weight:600">' + escapeHtml(alt.ingredient_name) + '</span>' +
            qtyLine + stockBadge + recipeSpecificBadge +
            '</div>' +
            reasonLine +
            '</div>';
    }

    function renderIngredient(ingr) {
        var key      = String(ingr.ingredient_id);
        var isOpen   = !!state.expanded[key];
        var count    = ingr.alternatives ? ingr.alternatives.length : 0;
        var optional = ingr.is_optional
            ? '<span style="font-size:10px;color:#b88a00;margin-left:5px">(opcional)</span>'
            : '';

        var header =
            '<div data-subs-ingr="' + key + '" ' +
            'style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;cursor:' + (count ? 'pointer' : 'default') + ';border-bottom:1px solid #edf1f4">' +
            '<div style="font-size:13px;font-weight:700">' +
            escapeHtml(ingr.ingredient_name) + optional +
            '<span style="font-size:11px;color:#697681;font-weight:400;margin-left:6px">' +
            escapeHtml(fmtQty(ingr.quantity)) + ' ' + escapeHtml(ingr.unit_symbol) +
            '</span>' +
            '</div>' +
            (count
                ? '<span style="font-size:11px;color:#04ac85;font-weight:700">' +
                  (isOpen ? '▲ Ocultar' : count + ' alternativa' + (count === 1 ? '' : 's')) +
                  '</span>'
                : '<span style="font-size:11px;color:#b0b8bf">Sin alternativas</span>') +
            '</div>';

        var altList = '';
        if (isOpen && count) {
            altList = '<div style="padding:4px 0 4px 12px">' +
                ingr.alternatives.map(renderAlternative).join('') +
                '</div>';
        }

        return '<div>' + header + altList + '</div>';
    }

    function renderContent() {
        var d = state.data;

        var header =
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8;margin-bottom:6px">' +
            '<h3 style="margin:0;font-size:14px;font-weight:900">Sustituciones de ingredientes</h3>' +
            '</div>';

        var stockCtrl = '';
        if (!state.groupId) {
            stockCtrl =
                '<button type="button" data-subs-stock class="btn-secondary-web btn-sm" style="font-size:11px;margin-bottom:8px">' +
                'Ver disponibilidad en mi stock' +
                '</button>';
        } else {
            var gName = '';
            state.groups.forEach(function (g) { if (String(g.id) === String(state.groupId)) { gName = g.name; } });

            var groupSel = '';
            if (state.groups.length > 1) {
                groupSel = '<select data-subs-group class="form-control" style="font-size:11px;max-width:140px;padding:4px 6px;height:auto">' +
                    state.groups.map(function (g) {
                        return '<option value="' + g.id + '"' + (String(g.id) === String(state.groupId) ? ' selected' : '') + '>' +
                            escapeHtml(g.name) + '</option>';
                    }).join('') +
                    '</select>';
            } else {
                groupSel = '<span style="font-size:11px;color:#697681">' + escapeHtml(gName) + '</span>';
            }

            stockCtrl = '<div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">' +
                '<span style="font-size:11px;color:#697681">Stock:</span>' + groupSel +
                '</div>';
        }

        if (!d) {
            return header + stockCtrl + '<p class="muted" style="font-size:13px">Sin datos de sustituciones.</p>';
        }

        var ingredients = d.ingredients || [];
        if (!ingredients.length) {
            return header + stockCtrl + '<p class="muted" style="font-size:13px">Esta receta no tiene ingredientes.</p>';
        }

        var withAlts    = ingredients.filter(function (i) { return i.has_alternatives; }).length;
        var summary     = '<div style="font-size:12px;color:#697681;margin-bottom:8px">' +
            withAlts + ' de ' + ingredients.length + ' ingrediente' + (ingredients.length === 1 ? '' : 's') +
            ' tiene' + (withAlts === 1 ? '' : 'n') + ' alternativas' +
            '</div>';

        return header + stockCtrl + summary +
            '<div>' + ingredients.map(renderIngredient).join('') + '</div>';
    }

    function render() {
        if (!state.el) { return; }

        if (state.loading) {
            state.el.innerHTML =
                '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8;color:#697681;font-size:13px">Cargando sustituciones...</div>';
            return;
        }

        state.el.innerHTML = renderContent();
        bindEvents(state.el);
    }

    // ─── Events ──────────────────────────────────────────────────────────────────

    function bindEvents(el) {
        // Ingredient row toggle
        el.addEventListener('click', function (e) {
            var ingr = e.target.closest('[data-subs-ingr]');
            if (ingr) {
                var key = ingr.getAttribute('data-subs-ingr');
                var d   = state.data;
                if (!d) { return; }
                var item = (d.ingredients || []).filter(function (i) { return String(i.ingredient_id) === String(key); })[0];
                if (item && item.has_alternatives) {
                    state.expanded[key] = !state.expanded[key];
                    render();
                }
                return;
            }

            // "Ver con mi stock" button
            if (e.target.closest('[data-subs-stock]')) {
                loadGroupsThenFetch();
                return;
            }
        });

        // Group selector change
        var grpSel = qs('[data-subs-group]', el);
        if (grpSel) {
            grpSel.addEventListener('change', function () {
                state.groupId = grpSel.value || null;
                state.expanded = {};
                fetchSubstitutions();
            });
        }
    }

    // ─── API ─────────────────────────────────────────────────────────────────────

    function fetchSubstitutions() {
        state.loading = true;
        render();

        var url = endpoint('/recipes/' + state.recipeId + '/substitutions');
        if (state.groupId) { url += '?family_group_id=' + encodeURIComponent(state.groupId); }

        window.CCApi.request(url)
            .then(function (res) {
                state.data    = res.data || null;
                state.loading = false;
                render();
            })
            .catch(function (err) {
                state.data    = null;
                state.loading = false;
                var status = err && err.status;
                if (status === 404 || status === 403) {
                    // silent: recipe not found or not visible
                    state.el.innerHTML = '';
                } else {
                    render();
                }
            });
    }

    function loadGroupsThenFetch() {
        if (state.groupsLoaded) {
            if (state.groups.length) {
                state.groupId = state.groups[0].id;
                fetchSubstitutions();
            }
            return;
        }

        state.loading = true;
        render();

        window.CCApi.request(endpoint('/family-groups?per_page=20'))
            .then(function (res) {
                state.groups       = res.data || [];
                state.groupsLoaded = true;
                if (state.groups.length) {
                    state.groupId = state.groups[0].id;
                    fetchSubstitutions();
                } else {
                    state.loading = false;
                    render();
                }
            })
            .catch(function () {
                state.groups       = [];
                state.groupsLoaded = true;
                state.loading      = false;
                render();
            });
    }

    // ─── Public ──────────────────────────────────────────────────────────────────

    function mount(containerEl, recipeId) {
        state.el           = containerEl;
        state.recipeId     = recipeId;
        state.data         = null;
        state.loading      = false;
        state.groupId      = null;
        state.groups       = [];
        state.groupsLoaded = false;
        state.expanded     = {};

        fetchSubstitutions();
    }

    window.RecipeSubstitutions = { mount: mount };

})(window, document);
