(function (window, document) {
    'use strict';

    var unitsCache = null;

    var state = {
        el: null,
        recipeId: null,
        canEdit: false,
        ingredients: [],
        editingId: null,
        selectedIngredient: null,
        searchTimeout: null,
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

    // ─── Units ─────────────────────────────────────────────────────────────────

    function loadUnits() {
        if (unitsCache) { return Promise.resolve(unitsCache); }
        return window.CCApi.request(endpoint('/units?per_page=100&sort=name&order=asc'))
            .then(function (res) {
                unitsCache = res.data || [];
                return unitsCache;
            })
            .catch(function () {
                unitsCache = [];
                return unitsCache;
            });
    }

    function unitOptions(selectedId) {
        return (unitsCache || []).map(function (u) {
            var label = escapeHtml(u.name) + (u.symbol ? ' (' + escapeHtml(u.symbol) + ')' : '');
            return '<option value="' + u.id + '"' + (String(u.id) === String(selectedId) ? ' selected' : '') + '>' + label + '</option>';
        }).join('');
    }

    // ─── Row renderers ──────────────────────────────────────────────────────────

    function formatQty(q) {
        var n = parseFloat(q);
        return n % 1 === 0 ? String(parseInt(n, 10)) : String(n);
    }

    function renderReadRow(ing) {
        var qty      = ing.quantity ? formatQty(ing.quantity) + ' ' : '';
        var unitName = ing.unit_name || '';
        var ingName  = ing.ingredient_name || '-';
        var optBadge = ing.is_optional
            ? ' <span class="muted" style="font-size:11px">(opc.)</span>'
            : '';
        var notesDiv = ing.notes
            ? '<div style="font-size:11px;color:#697681;margin-top:1px">' + escapeHtml(ing.notes) + '</div>'
            : '';

        var actions = state.canEdit
            ? '<div style="display:flex;gap:4px;flex-shrink:0">' +
              '<button type="button" class="btn-ghost btn-sm" data-ingr-edit="' + ing.id + '">Editar</button>' +
              '<button type="button" class="btn-ghost btn-sm" data-ingr-delete="' + ing.id + '" style="color:#b33a3a">✕</button>' +
              '</div>'
            : '';

        return '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:7px 0;border-bottom:1px solid #edf2ee">' +
            '<div style="flex:1;min-width:0;font-size:13px">' +
            '<strong>' + escapeHtml(qty + unitName) + '</strong> ' + escapeHtml(ingName) + optBadge +
            notesDiv +
            '</div>' +
            actions +
            '</div>';
    }

    function renderEditRow(ing) {
        var ingName = ing.ingredient_name || '-';
        return '<div style="background:#f9fafb;border-radius:6px;padding:10px;margin:4px 0;border:1px solid #dde3e8" data-ingr-edit-row="' + ing.id + '">' +
            '<div style="font-size:11px;color:#697681;margin-bottom:7px">Editando: <strong>' + escapeHtml(ingName) + '</strong></div>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:6px">' +
            '<input class="form-control" name="edit_qty" type="number" step="0.0001" min="0.0001" value="' + escapeHtml(formatQty(ing.quantity)) + '" placeholder="Cantidad">' +
            '<select class="form-control" name="edit_unit"><option value="">Unidad</option>' + unitOptions(ing.unit_id) + '</select>' +
            '</div>' +
            '<input class="form-control" name="edit_notes" type="text" value="' + escapeHtml(ing.notes || '') + '" placeholder="Notas (opcional)" style="margin-bottom:6px">' +
            '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:8px">' +
            '<input type="checkbox" name="edit_optional"' + (ing.is_optional ? ' checked' : '') + '> Opcional' +
            '</label>' +
            '<div style="display:flex;gap:6px">' +
            '<button type="button" class="btn-main btn-sm" data-ingr-save="' + ing.id + '">Guardar</button>' +
            '<button type="button" class="btn-ghost btn-sm" data-ingr-cancel>Cancelar</button>' +
            '</div>' +
            '</div>';
    }

    function renderAddForm() {
        return '<div style="background:#f9fafb;border-radius:6px;padding:10px;margin-top:8px;border:1px solid #dde3e8">' +
            '<div style="font-size:12px;color:#697681;margin-bottom:6px">Buscar ingrediente</div>' +
            '<input class="form-control" data-ingr-search placeholder="ej: tomate, arroz..." style="margin-bottom:6px" autocomplete="off">' +
            '<div data-ingr-results style="margin-bottom:4px"></div>' +
            '<div data-ingr-fields style="display:none">' +
            '<div style="font-size:12px;color:#697681;margin-bottom:6px">Ingrediente: <strong data-ingr-sel-name></strong> ' +
            '<button type="button" data-ingr-clear class="btn-ghost btn-sm" style="padding:2px 7px;font-size:11px">✕</button></div>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:6px">' +
            '<input class="form-control" data-ingr-qty type="number" step="0.0001" min="0.0001" placeholder="Cantidad">' +
            '<select class="form-control" data-ingr-unit><option value="">Unidad...</option>' + unitOptions(null) + '</select>' +
            '</div>' +
            '<input class="form-control" data-ingr-notes type="text" placeholder="Notas (opcional)" style="margin-bottom:6px">' +
            '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:8px">' +
            '<input type="checkbox" data-ingr-optional> Opcional' +
            '</label>' +
            '<button type="button" class="btn-main btn-sm" data-ingr-submit>Agregar</button>' +
            '</div>' +
            '<div data-ingr-msg style="display:none;font-size:12px;margin-top:7px;padding:5px 9px;border-radius:4px"></div>' +
            '</div>';
    }

    // ─── Render ─────────────────────────────────────────────────────────────────

    function renderListHtml() {
        if (!state.ingredients.length) {
            return '<p class="muted" style="font-size:13px;margin:6px 0">Sin ingredientes aún.</p>';
        }
        var sorted = state.ingredients.slice().sort(function (a, b) {
            return (a.sort_order || 0) - (b.sort_order || 0);
        });
        return sorted.map(function (ing) {
            return state.editingId === ing.id ? renderEditRow(ing) : renderReadRow(ing);
        }).join('');
    }

    function render() {
        if (!state.el) { return; }

        var headerHtml =
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding-top:14px;border-top:1px solid #dde3e8">' +
            '<h3 style="margin:0;font-size:14px;font-weight:900" data-ingr-count>Ingredientes (' + state.ingredients.length + ')</h3>' +
            (state.canEdit ? '<button type="button" class="btn-ghost btn-sm" data-ingr-toggle>+ Agregar</button>' : '') +
            '</div>';

        var listHtml = '<div data-ingr-list>' + renderListHtml() + '</div>';

        var addHtml = state.canEdit
            ? '<div data-ingr-add-wrap style="display:none">' + renderAddForm() + '</div>'
            : '';

        state.el.innerHTML = headerHtml + listHtml + addHtml;
        bindEvents(state.el);
    }

    function refreshList() {
        var listEl  = qs('[data-ingr-list]', state.el);
        var countEl = qs('[data-ingr-count]', state.el);
        if (listEl)  { listEl.innerHTML  = renderListHtml(); }
        if (countEl) { countEl.textContent = 'Ingredientes (' + state.ingredients.length + ')'; }
    }

    // ─── Feedback ───────────────────────────────────────────────────────────────

    function showMsg(type, text) {
        var el = qs('[data-ingr-msg]', state.el);
        if (!el) { return; }
        el.textContent = text;
        el.style.display = 'block';
        el.style.background = type === 'ok' ? '#e7f7f2' : '#f7e7e7';
        el.style.color      = type === 'ok' ? '#04ac85' : '#b33a3a';
        if (type === 'ok') { setTimeout(function () { if (el) { el.style.display = 'none'; } }, 3000); }
    }

    function extractMsg(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message) || 'Error inesperado.';
    }

    // ─── Ingredient search ──────────────────────────────────────────────────────

    function doSearch(query) {
        var resultsEl = qs('[data-ingr-results]', state.el);
        if (!resultsEl) { return; }

        if (!query || query.length < 2) { resultsEl.innerHTML = ''; return; }

        window.CCApi.request(endpoint('/ingredients?search=' + encodeURIComponent(query) + '&per_page=8&status=active'))
            .then(function (res) {
                var items   = res.data || [];
                var usedIds = state.ingredients.map(function (i) { return String(i.ingredient_id); });

                if (!items.length) {
                    resultsEl.innerHTML = '<p class="muted" style="font-size:12px;margin:4px 0">Sin resultados.</p>';
                    return;
                }

                resultsEl.innerHTML =
                    '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:4px">' +
                    items.map(function (item) {
                        if (usedIds.indexOf(String(item.id)) !== -1) {
                            return '<span style="background:#f0f0f0;color:#999;border-radius:50px;padding:3px 9px;font-size:12px">' + escapeHtml(item.name) + ' ✓</span>';
                        }
                        return '<button type="button" data-ingr-pick="' + item.id + '" data-ingr-pick-name="' + escapeHtml(item.name) + '"' +
                            ' style="background:#e7f7f2;color:#04ac85;border:none;border-radius:50px;padding:3px 9px;font-size:12px;cursor:pointer">' +
                            escapeHtml(item.name) + '</button>';
                    }).join('') +
                    '</div>';
            })
            .catch(function () {
                var el = qs('[data-ingr-results]', state.el);
                if (el) { el.innerHTML = '<p class="muted" style="font-size:12px">Error al buscar.</p>'; }
            });
    }

    function selectIngredient(id, name) {
        state.selectedIngredient = { id: id, name: name };
        var searchEl  = qs('[data-ingr-search]', state.el);
        var resultsEl = qs('[data-ingr-results]', state.el);
        var fieldsEl  = qs('[data-ingr-fields]', state.el);
        var selName   = qs('[data-ingr-sel-name]', state.el);

        if (searchEl)  { searchEl.value = ''; searchEl.disabled = true; }
        if (resultsEl) { resultsEl.innerHTML = ''; }
        if (fieldsEl)  { fieldsEl.style.display = ''; }
        if (selName)   { selName.textContent = name; }

        var qtyEl = qs('[data-ingr-qty]', state.el);
        if (qtyEl) { qtyEl.focus(); }
    }

    function clearSelection() {
        state.selectedIngredient = null;
        var searchEl  = qs('[data-ingr-search]', state.el);
        var resultsEl = qs('[data-ingr-results]', state.el);
        var fieldsEl  = qs('[data-ingr-fields]', state.el);

        if (searchEl)  { searchEl.disabled = false; searchEl.value = ''; searchEl.focus(); }
        if (resultsEl) { resultsEl.innerHTML = ''; }
        if (fieldsEl)  { fieldsEl.style.display = 'none'; }
    }

    // ─── CRUD ───────────────────────────────────────────────────────────────────

    function addIngredient() {
        if (!state.selectedIngredient) { return; }

        var qtyEl   = qs('[data-ingr-qty]', state.el);
        var unitEl  = qs('[data-ingr-unit]', state.el);
        var notesEl = qs('[data-ingr-notes]', state.el);
        var optEl   = qs('[data-ingr-optional]', state.el);
        var btn     = qs('[data-ingr-submit]', state.el);

        var qty = qtyEl ? parseFloat(qtyEl.value) : 0;
        if (!qty || qty <= 0) { showMsg('err', 'Ingresá una cantidad válida.'); return; }
        if (!unitEl || !unitEl.value) { showMsg('err', 'Seleccioná una unidad.'); return; }

        if (btn) { btn.disabled = true; }

        var body = {
            ingredient_id: parseInt(state.selectedIngredient.id, 10),
            quantity: qty,
            unit_id: parseInt(unitEl.value, 10),
        };
        var notes = notesEl ? notesEl.value.trim() : '';
        if (notes) { body.notes = notes; }
        if (optEl && optEl.checked) { body.is_optional = true; }

        window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/ingredients'), { method: 'POST', body: body })
            .then(function (res) {
                state.ingredients.push(res.data);
                clearSelection();
                var addWrap = qs('[data-ingr-add-wrap]', state.el);
                if (addWrap) { addWrap.style.display = 'none'; }
                refreshList();
                showMsg('ok', 'Ingrediente agregado.');
            })
            .catch(function (err) { showMsg('err', extractMsg(err)); })
            .then(function () { if (btn) { btn.disabled = false; } });
    }

    function saveEdit(id) {
        var rowEl = qs('[data-ingr-edit-row="' + id + '"]', state.el);
        if (!rowEl) { return; }

        var qtyEl   = rowEl.querySelector('[name=edit_qty]');
        var unitEl  = rowEl.querySelector('[name=edit_unit]');
        var notesEl = rowEl.querySelector('[name=edit_notes]');
        var optEl   = rowEl.querySelector('[name=edit_optional]');
        var btn     = rowEl.querySelector('[data-ingr-save]');

        var qty = qtyEl ? parseFloat(qtyEl.value) : 0;
        if (!qty || qty <= 0) { showMsg('err', 'Cantidad inválida.'); return; }

        if (btn) { btn.disabled = true; }

        var body = { quantity: qty };
        if (unitEl && unitEl.value) { body.unit_id = parseInt(unitEl.value, 10); }
        if (notesEl) { body.notes = notesEl.value.trim() || null; }
        body.is_optional = optEl ? optEl.checked : false;

        window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/ingredients/' + id), { method: 'PATCH', body: body })
            .then(function (res) {
                for (var i = 0; i < state.ingredients.length; i++) {
                    if (String(state.ingredients[i].id) === String(id)) {
                        state.ingredients[i] = res.data;
                        break;
                    }
                }
                state.editingId = null;
                refreshList();
                showMsg('ok', 'Guardado.');
            })
            .catch(function (err) { showMsg('err', extractMsg(err)); })
            .then(function () { if (btn) { btn.disabled = false; } });
    }

    function deleteIngredient(id) {
        if (!window.confirm('¿Quitar este ingrediente de la receta?')) { return; }

        window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/ingredients/' + id), { method: 'DELETE' })
            .then(function () {
                state.ingredients = state.ingredients.filter(function (i) { return String(i.id) !== String(id); });
                refreshList();
            })
            .catch(function (err) { showMsg('err', extractMsg(err)); });
    }

    // ─── Events ─────────────────────────────────────────────────────────────────

    function bindEvents(el) {
        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-ingr-toggle]')) {
                var wrap = qs('[data-ingr-add-wrap]', el);
                if (wrap) { wrap.style.display = wrap.style.display === 'none' ? '' : 'none'; }
                return;
            }

            var pick = e.target.closest('[data-ingr-pick]');
            if (pick) {
                selectIngredient(pick.getAttribute('data-ingr-pick'), pick.getAttribute('data-ingr-pick-name'));
                return;
            }

            if (e.target.closest('[data-ingr-clear]')) { clearSelection(); return; }

            if (e.target.closest('[data-ingr-submit]')) { addIngredient(); return; }

            var editBtn = e.target.closest('[data-ingr-edit]');
            if (editBtn) {
                state.editingId = parseInt(editBtn.getAttribute('data-ingr-edit'), 10);
                refreshList();
                return;
            }

            var saveBtn = e.target.closest('[data-ingr-save]');
            if (saveBtn) { saveEdit(saveBtn.getAttribute('data-ingr-save')); return; }

            if (e.target.closest('[data-ingr-cancel]')) { state.editingId = null; refreshList(); return; }

            var delBtn = e.target.closest('[data-ingr-delete]');
            if (delBtn) { deleteIngredient(delBtn.getAttribute('data-ingr-delete')); return; }
        });

        var searchInput = qs('[data-ingr-search]', el);
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(state.searchTimeout);
                var val = searchInput.value.trim();
                state.searchTimeout = setTimeout(function () { doSearch(val); }, 350);
            });
        }
    }

    // ─── Public API ─────────────────────────────────────────────────────────────

    function mount(containerEl, recipeId, canEdit, initialIngredients) {
        state.el                 = containerEl;
        state.recipeId           = recipeId;
        state.canEdit            = !!canEdit;
        state.ingredients        = (initialIngredients || []).slice();
        state.editingId          = null;
        state.selectedIngredient = null;
        state.searchTimeout      = null;

        loadUnits().then(function () { render(); });
    }

    window.RecipeIngredients = { mount: mount };

})(window, document);
