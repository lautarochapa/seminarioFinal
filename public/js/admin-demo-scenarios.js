(function (window, document) {
    'use strict';

    var state = {
        scenarios: [],
        page: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        saving: false,
        statusFilter: '',
        search: '',
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v == null) { return ''; }
        return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function endpoint(path) { return path; }

    function errMsg(err) {
        return (err && err.message) ? err.message : 'Ocurrió un error inesperado.';
    }

    function showMsg(root, sel, type, msg) {
        var el = qs(sel, root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearMsg(root, sel) {
        var el = qs(sel, root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    var STATUS_LABELS = { active: 'Activo', inactive: 'Inactivo' };
    var STATUS_COLORS = { active: '#04ac85', inactive: '#697681' };

    function statusBadge(status) {
        var label = STATUS_LABELS[status] || status || '-';
        var color = STATUS_COLORS[status] || '#697681';
        return '<span style="font-size:11px;font-weight:700;color:' + color + ';background:' + color + '1a;border-radius:999px;padding:2px 8px">' + escapeHtml(label) + '</span>';
    }

    // ── List ──────────────────────────────────────────────────────────────────

    function renderList(root) {
        var tbody  = qs('[data-demo-body]', root);
        var countEl = qs('[data-demo-count]', root);
        var pageEl  = qs('[data-demo-page]', root);
        var prevBtn = qs('[data-demo-prev]', root);
        var nextBtn = qs('[data-demo-next]', root);

        if (countEl) { countEl.textContent = state.total + ' escenario' + (state.total !== 1 ? 's' : ''); }
        if (pageEl)  { pageEl.textContent = 'Pagina ' + state.page; }
        if (prevBtn) { prevBtn.disabled = state.loading || state.page <= 1; }
        if (nextBtn) { nextBtn.disabled = state.loading || state.page >= state.lastPage; }

        if (!tbody) { return; }
        if (state.loading) {
            tbody.innerHTML = '<tr><td colspan="6" class="muted">Cargando...</td></tr>';
            return;
        }
        if (!state.scenarios.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="muted">Sin escenarios.</td></tr>';
            return;
        }

        tbody.innerHTML = state.scenarios.map(function (s) {
            return '<tr>' +
                '<td><strong>' + escapeHtml(s.name || '') + '</strong></td>' +
                '<td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtml(s.description || '') + '</td>' +
                '<td><code style="font-size:12px">' + escapeHtml(s.route || '') + '</code></td>' +
                '<td>' + (s.demo_user_id ? '#' + escapeHtml(String(s.demo_user_id)) : '<span class="muted">-</span>') + '</td>' +
                '<td>' + statusBadge(s.status) + '</td>' +
                '<td style="white-space:nowrap">' +
                '<button type="button" class="btn-sm" style="margin-right:4px" data-demo-edit="' + escapeHtml(String(s.id)) + '">Editar</button>' +
                '<button type="button" class="btn-sm" style="color:#b33a3a" data-demo-delete="' + escapeHtml(String(s.id)) + '">✕</button>' +
                '</td>' +
                '</tr>';
        }).join('');
    }

    function loadScenarios(root) {
        state.loading = true;
        clearMsg(root, '[data-demo-message]');
        renderList(root);

        var params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', 20);
        if (state.statusFilter) { params.set('status', state.statusFilter); }
        if (state.search)       { params.set('search', state.search); }

        window.CCApi.request(endpoint('/api/v1/admin/demo-scenarios?' + params.toString()))
            .then(function (res) {
                state.scenarios = res.data || [];
                state.total     = (res.meta && res.meta.total) || state.scenarios.length;
                state.page      = (res.meta && res.meta.current_page) || state.page;
                state.lastPage  = (res.meta && res.meta.last_page) || 1;
                state.loading   = false;
                renderList(root);
            })
            .catch(function (err) {
                state.scenarios = []; state.loading = false;
                renderList(root);
                showMsg(root, '[data-demo-message]', 'danger', errMsg(err));
            });
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    function resetForm(root) {
        var form = qs('[data-demo-form]', root);
        if (form) { form.reset(); if (form.elements.id) { form.elements.id.value = ''; } }
        var titleEl = qs('[data-demo-form-title]', root);
        if (titleEl) { titleEl.textContent = 'Nuevo escenario'; }
        var saveBtn = qs('[data-demo-save]', root);
        if (saveBtn) { saveBtn.textContent = 'Crear'; }
        clearMsg(root, '[data-demo-form-message]');
    }

    function fillForm(root, s) {
        var form = qs('[data-demo-form]', root);
        if (!form) { return; }
        var f = form.elements;
        if (f.id)           { f.id.value = s.id; }
        if (f.name)         { f.name.value = s.name || ''; }
        if (f.description)  { f.description.value = s.description || ''; }
        if (f.route)        { f.route.value = s.route || ''; }
        if (f.demo_user_id) { f.demo_user_id.value = s.demo_user_id || ''; }
        if (f.status)       { f.status.value = s.status || 'active'; }
        var titleEl = qs('[data-demo-form-title]', root);
        if (titleEl) { titleEl.textContent = 'Editar: ' + (s.name || ''); }
        var saveBtn = qs('[data-demo-save]', root);
        if (saveBtn) { saveBtn.textContent = 'Actualizar'; }
        clearMsg(root, '[data-demo-form-message]');
        form.querySelector('[name="name"]') && form.querySelector('[name="name"]').focus();
    }

    function saveScenario(root, form) {
        var f = form.elements;
        var name = f.name ? f.name.value.trim() : '';
        if (!name) { showMsg(root, '[data-demo-form-message]', 'warning', 'El nombre es obligatorio.'); return; }

        var payload = { name: name };
        if (f.description && f.description.value.trim()) { payload.description = f.description.value.trim(); }
        if (f.route && f.route.value.trim())             { payload.route = f.route.value.trim(); }
        if (f.demo_user_id && f.demo_user_id.value)      { payload.demo_user_id = Number(f.demo_user_id.value); }
        if (f.status && f.status.value)                  { payload.status = f.status.value; }

        var isEdit = !!(f.id && f.id.value);
        var url    = isEdit ? endpoint('/api/v1/admin/demo-scenarios/' + encodeURIComponent(f.id.value)) : endpoint('/api/v1/admin/demo-scenarios');
        var method = isEdit ? 'PATCH' : 'POST';

        state.saving = true;
        var btn = qs('[data-demo-save]', root);
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
        clearMsg(root, '[data-demo-form-message]');

        window.CCApi.request(url, { method: method, body: payload })
            .then(function () {
                state.saving = false;
                if (btn) { btn.disabled = false; }
                resetForm(root);
                state.page = 1;
                loadScenarios(root);
                showMsg(root, '[data-demo-message]', 'success', 'Escenario guardado.');
            })
            .catch(function (err) {
                state.saving = false;
                if (btn) { btn.disabled = false; btn.textContent = isEdit ? 'Actualizar' : 'Crear'; }
                showMsg(root, '[data-demo-form-message]', 'danger', errMsg(err));
            });
    }

    function deleteScenario(root, id) {
        if (!window.confirm('¿Eliminar este escenario?')) { return; }
        window.CCApi.request(endpoint('/api/v1/admin/demo-scenarios/' + encodeURIComponent(id)), { method: 'DELETE' })
            .then(function () { state.page = 1; loadScenarios(root); })
            .catch(function (err) { showMsg(root, '[data-demo-message]', 'danger', errMsg(err)); });
    }

    // ── Bind ──────────────────────────────────────────────────────────────────

    function bind(root) {
        var searchInput = qs('[data-demo-search]', root);
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                state.search = searchInput.value; state.page = 1; loadScenarios(root);
            });
        }

        var statusSel = qs('[data-demo-status-filter]', root);
        if (statusSel) {
            statusSel.addEventListener('change', function () {
                state.statusFilter = statusSel.value; state.page = 1; loadScenarios(root);
            });
        }

        var prevBtn = qs('[data-demo-prev]', root);
        if (prevBtn) { prevBtn.addEventListener('click', function () { if (state.page > 1) { state.page -= 1; loadScenarios(root); } }); }

        var nextBtn = qs('[data-demo-next]', root);
        if (nextBtn) { nextBtn.addEventListener('click', function () { if (state.page < state.lastPage) { state.page += 1; loadScenarios(root); } }); }

        var refreshBtn = qs('[data-demo-refresh]', root);
        if (refreshBtn) { refreshBtn.addEventListener('click', function () { state.page = 1; loadScenarios(root); }); }

        var resetBtn = qs('[data-demo-reset]', root);
        if (resetBtn) { resetBtn.addEventListener('click', function () { resetForm(root); }); }

        var form = qs('[data-demo-form]', root);
        if (form) { form.addEventListener('submit', function (e) { e.preventDefault(); saveScenario(root, form); }); }

        root.addEventListener('click', function (event) {
            var editBtn = event.target.closest('[data-demo-edit]');
            var delBtn  = event.target.closest('[data-demo-delete]');
            if (editBtn) {
                var id = editBtn.getAttribute('data-demo-edit');
                var s  = state.scenarios.find(function (x) { return String(x.id) === String(id); });
                if (s) { fillForm(root, s); }
                return;
            }
            if (delBtn) { deleteScenario(root, delBtn.getAttribute('data-demo-delete')); }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-admin-demo-scenarios]');
        if (!root) { return; }
        bind(root);
        loadScenarios(root);
    });
})(window, document);
