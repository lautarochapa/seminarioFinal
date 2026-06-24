(function (window, document) {
    'use strict';

    var state = {
        el: null,
        recipeId: null,
        canEdit: false,
        steps: [],
        editingId: null,
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

    function formatMinutes(m) {
        if (!m || m <= 0) { return ''; }
        if (m < 60) { return m + ' min'; }
        var h = Math.floor(m / 60);
        var r = m % 60;
        return r ? h + 'h ' + r + 'min' : h + 'h';
    }

    // ─── Row renderers ──────────────────────────────────────────────────────────

    function renderReadRow(step) {
        var timeSpan = step.estimated_minutes
            ? ' <span class="muted" style="font-size:11px;white-space:nowrap">(' + formatMinutes(step.estimated_minutes) + ')</span>'
            : '';

        var actions = state.canEdit
            ? '<div style="display:flex;gap:4px;flex-shrink:0">' +
              '<button type="button" class="btn-ghost btn-sm" data-step-edit="' + step.id + '">Editar</button>' +
              '<button type="button" class="btn-ghost btn-sm" data-step-delete="' + step.id + '" style="color:#b33a3a">✕</button>' +
              '</div>'
            : '';

        return '<div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #edf2ee">' +
            '<span style="flex-shrink:0;width:22px;height:22px;background:#e7f7f2;color:#04ac85;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:900">' +
            escapeHtml(step.step_number) + '</span>' +
            '<div style="flex:1;min-width:0;font-size:13px">' +
            escapeHtml(step.description) + timeSpan +
            '</div>' +
            actions +
            '</div>';
    }

    function renderEditRow(step) {
        return '<div style="background:#f9fafb;border-radius:6px;padding:10px;margin:4px 0;border:1px solid #dde3e8" data-step-edit-row="' + step.id + '">' +
            '<div style="font-size:11px;color:#697681;margin-bottom:6px">Editando paso ' + step.step_number + '</div>' +
            '<textarea class="form-control" name="edit_desc" rows="2" style="margin-bottom:6px;resize:vertical">' + escapeHtml(step.description) + '</textarea>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px">' +
            '<input class="form-control" name="edit_step_number" type="number" min="1" value="' + step.step_number + '" placeholder="Nro. paso">' +
            '<input class="form-control" name="edit_minutes" type="number" min="1" value="' + escapeHtml(step.estimated_minutes || '') + '" placeholder="Minutos (opc.)">' +
            '</div>' +
            '<div style="display:flex;gap:6px">' +
            '<button type="button" class="btn-main btn-sm" data-step-save="' + step.id + '">Guardar</button>' +
            '<button type="button" class="btn-ghost btn-sm" data-step-cancel>Cancelar</button>' +
            '</div>' +
            '</div>';
    }

    function renderAddForm() {
        return '<div style="background:#f9fafb;border-radius:6px;padding:10px;margin-top:8px;border:1px solid #dde3e8">' +
            '<textarea class="form-control" data-step-new-desc rows="2" placeholder="Descripción del paso..." style="margin-bottom:6px;resize:vertical"></textarea>' +
            '<input class="form-control" data-step-new-minutes type="number" min="1" placeholder="Duración en minutos (opcional)" style="margin-bottom:8px">' +
            '<button type="button" class="btn-main btn-sm" data-step-submit>Agregar paso</button>' +
            '</div>';
    }

    // ─── Render ─────────────────────────────────────────────────────────────────

    function renderListHtml() {
        if (!state.steps.length) {
            return '<p class="muted" style="font-size:13px;margin:6px 0">Sin pasos cargados.</p>';
        }
        var sorted = state.steps.slice().sort(function (a, b) {
            return (a.step_number || 0) - (b.step_number || 0);
        });
        return sorted.map(function (step) {
            return state.editingId === step.id ? renderEditRow(step) : renderReadRow(step);
        }).join('');
    }

    function render() {
        if (!state.el) { return; }

        var headerHtml =
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid #dde3e8">' +
            '<h3 style="margin:0;font-size:14px;font-weight:900" data-step-count>Pasos (' + state.steps.length + ')</h3>' +
            (state.canEdit ? '<button type="button" class="btn-ghost btn-sm" data-step-toggle>+ Agregar</button>' : '') +
            '</div>';

        var listHtml = '<div data-step-list>' + renderListHtml() + '</div>';

        var msgHtml = '<div data-step-msg style="display:none;font-size:12px;padding:5px 9px;border-radius:4px;margin-top:6px"></div>';

        var addHtml = state.canEdit
            ? '<div data-step-add-wrap style="display:none">' + renderAddForm() + '</div>'
            : '';

        state.el.innerHTML = headerHtml + listHtml + msgHtml + addHtml;
        bindEvents(state.el);
    }

    function refreshList() {
        var listEl  = qs('[data-step-list]', state.el);
        var countEl = qs('[data-step-count]', state.el);
        if (listEl)  { listEl.innerHTML = renderListHtml(); }
        if (countEl) { countEl.textContent = 'Pasos (' + state.steps.length + ')'; }
    }

    // ─── Feedback ───────────────────────────────────────────────────────────────

    function showMsg(type, text) {
        var el = qs('[data-step-msg]', state.el);
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

    // ─── CRUD ───────────────────────────────────────────────────────────────────

    function addStep() {
        var descEl    = qs('[data-step-new-desc]', state.el);
        var minutesEl = qs('[data-step-new-minutes]', state.el);
        var btn       = qs('[data-step-submit]', state.el);

        var desc = descEl ? descEl.value.trim() : '';
        if (!desc) { showMsg('err', 'La descripción del paso es obligatoria.'); return; }

        if (btn) { btn.disabled = true; }

        var body = { description: desc };
        var mins = minutesEl && minutesEl.value ? parseInt(minutesEl.value, 10) : null;
        if (mins && mins > 0) { body.estimated_minutes = mins; }

        window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/steps'), { method: 'POST', body: body })
            .then(function (res) {
                state.steps.push(res.data);
                if (descEl)    { descEl.value = ''; }
                if (minutesEl) { minutesEl.value = ''; }
                var addWrap = qs('[data-step-add-wrap]', state.el);
                if (addWrap) { addWrap.style.display = 'none'; }
                refreshList();
                showMsg('ok', 'Paso agregado.');
            })
            .catch(function (err) { showMsg('err', extractMsg(err)); })
            .then(function () { if (btn) { btn.disabled = false; } });
    }

    function saveEdit(id) {
        var rowEl = qs('[data-step-edit-row="' + id + '"]', state.el);
        if (!rowEl) { return; }

        var descEl    = rowEl.querySelector('[name=edit_desc]');
        var stepNumEl = rowEl.querySelector('[name=edit_step_number]');
        var minsEl    = rowEl.querySelector('[name=edit_minutes]');
        var btn       = rowEl.querySelector('[data-step-save]');

        var desc = descEl ? descEl.value.trim() : '';
        if (!desc) { showMsg('err', 'La descripción no puede estar vacía.'); return; }

        if (btn) { btn.disabled = true; }

        var body = { description: desc };
        if (stepNumEl && stepNumEl.value) {
            var n = parseInt(stepNumEl.value, 10);
            if (n >= 1) { body.step_number = n; }
        }
        var mins = minsEl && minsEl.value ? parseInt(minsEl.value, 10) : null;
        body.estimated_minutes = (mins && mins > 0) ? mins : null;

        window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/steps/' + id), { method: 'PATCH', body: body })
            .then(function (res) {
                for (var i = 0; i < state.steps.length; i++) {
                    if (String(state.steps[i].id) === String(id)) {
                        state.steps[i] = res.data;
                        break;
                    }
                }
                state.editingId = null;
                refreshList();
                showMsg('ok', 'Paso actualizado.');
            })
            .catch(function (err) { showMsg('err', extractMsg(err)); })
            .then(function () { if (btn) { btn.disabled = false; } });
    }

    function deleteStep(id) {
        if (!window.confirm('¿Eliminar este paso de la receta?')) { return; }

        window.CCApi.request(endpoint('/recipes/' + state.recipeId + '/steps/' + id), { method: 'DELETE' })
            .then(function () {
                state.steps = state.steps.filter(function (s) { return String(s.id) !== String(id); });
                refreshList();
                showMsg('ok', 'Paso eliminado.');
            })
            .catch(function (err) { showMsg('err', extractMsg(err)); });
    }

    // ─── Events ─────────────────────────────────────────────────────────────────

    function bindEvents(el) {
        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-step-toggle]')) {
                var wrap = qs('[data-step-add-wrap]', el);
                if (wrap) { wrap.style.display = wrap.style.display === 'none' ? '' : 'none'; }
                return;
            }

            if (e.target.closest('[data-step-submit]')) { addStep(); return; }

            var editBtn = e.target.closest('[data-step-edit]');
            if (editBtn) {
                state.editingId = parseInt(editBtn.getAttribute('data-step-edit'), 10);
                refreshList();
                return;
            }

            var saveBtn = e.target.closest('[data-step-save]');
            if (saveBtn) { saveEdit(saveBtn.getAttribute('data-step-save')); return; }

            if (e.target.closest('[data-step-cancel]')) { state.editingId = null; refreshList(); return; }

            var delBtn = e.target.closest('[data-step-delete]');
            if (delBtn) { deleteStep(delBtn.getAttribute('data-step-delete')); return; }
        });
    }

    // ─── Public API ─────────────────────────────────────────────────────────────

    function mount(containerEl, recipeId, canEdit, initialSteps) {
        state.el        = containerEl;
        state.recipeId  = recipeId;
        state.canEdit   = !!canEdit;
        state.steps     = (initialSteps || []).slice();
        state.editingId = null;

        render();
    }

    window.RecipeSteps = { mount: mount };

})(window, document);
