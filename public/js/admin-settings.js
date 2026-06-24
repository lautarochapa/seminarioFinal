(function (window, document) {
    'use strict';

    var state = {
        settings: [],
        loading: false,
        page: 1,
        lastPage: 1,
        total: 0,
        search: '',
        editingKey: null,
        editValue: '',
        savingKey: null,
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

    function showMsg(root, type, msg) {
        var el = qs('[data-settings-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearMsg(root) {
        var el = qs('[data-settings-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    var TYPE_LABELS = { string: 'Texto', integer: 'Entero', boolean: 'Booleano', json: 'JSON', date: 'Fecha' };

    function isRedacted(val) {
        return val === '[REDACTED]';
    }

    function displayValueHtml(setting) {
        var val = setting.value;
        if (val === null || val === undefined) { return '<span class="muted">—</span>'; }
        if (isRedacted(val)) {
            return '<span style="font-size:12px;color:#b33a3a;letter-spacing:1px">🔒 REDACTED</span>';
        }
        if (setting.type === 'boolean') {
            var boolVal = val === true || val === 'true' || val === 1 || val === '1';
            return '<span style="font-size:11px;font-weight:700;color:' + (boolVal ? '#04ac85' : '#697681') + ';background:' + (boolVal ? '#e7f7f2' : '#f0f3f5') + ';border-radius:999px;padding:2px 8px">' + (boolVal ? 'Sí' : 'No') + '</span>';
        }
        if (setting.type === 'json') {
            try {
                var str = typeof val === 'string' ? val : JSON.stringify(val, null, 2);
                return '<code style="font-size:11px;white-space:pre-wrap;word-break:break-all;max-width:320px;display:inline-block">' + escapeHtml(str.length > 120 ? str.slice(0, 120) + '...' : str) + '</code>';
            } catch (e) {
                return escapeHtml(String(val));
            }
        }
        return '<span style="font-size:13px">' + escapeHtml(String(val)) + '</span>';
    }

    function editInputHtml(setting) {
        var val = setting.value;
        var rawVal = (val === true || val === 'true' || val === 1 || val === '1') ? 'true' : 'false';

        switch (setting.type) {
            case 'boolean':
                return '<select class="form-control" style="font-size:13px;min-width:90px" data-edit-input>' +
                    '<option value="1"' + (rawVal === 'true' ? ' selected' : '') + '>Sí</option>' +
                    '<option value="0"' + (rawVal !== 'true' ? ' selected' : '') + '>No</option>' +
                    '</select>';
            case 'integer':
                return '<input class="form-control" type="number" step="1" style="font-size:13px;min-width:100px" data-edit-input value="' + escapeHtml(val !== null && val !== undefined ? String(val) : '') + '">';
            case 'json':
                var jsonStr = '';
                if (val !== null && val !== undefined) {
                    try { jsonStr = typeof val === 'string' ? val : JSON.stringify(val, null, 2); } catch (e) { jsonStr = String(val); }
                }
                return '<textarea class="form-control" rows="3" style="font-size:12px;font-family:monospace;min-width:240px;resize:vertical" data-edit-input>' + escapeHtml(jsonStr) + '</textarea>';
            case 'date':
                return '<input class="form-control" type="date" style="font-size:13px;min-width:140px" data-edit-input value="' + escapeHtml(val !== null && val !== undefined ? String(val) : '') + '">';
            default: // string
                return '<input class="form-control" type="text" style="font-size:13px;min-width:180px" data-edit-input value="' + escapeHtml(val !== null && val !== undefined ? String(val) : '') + '">';
        }
    }

    function groupSettings(settings) {
        var groups = {};
        settings.forEach(function (s) {
            var dot = s.key.indexOf('.');
            var group = dot > -1 ? s.key.slice(0, dot) : 'general';
            if (!groups[group]) { groups[group] = []; }
            groups[group].push(s);
        });
        return groups;
    }

    function renderSettings(root) {
        var container = qs('[data-settings-container]', root);
        var countEl   = qs('[data-settings-count]', root);
        var prevBtn   = qs('[data-settings-prev]', root);
        var nextBtn   = qs('[data-settings-next]', root);
        var pageEl    = qs('[data-settings-page]', root);

        if (countEl) { countEl.textContent = state.total + ' configuracion' + (state.total !== 1 ? 'es' : ''); }
        if (pageEl)  { pageEl.textContent = 'Pagina ' + state.page; }
        if (prevBtn) { prevBtn.disabled = state.loading || state.page <= 1; }
        if (nextBtn) { nextBtn.disabled = state.loading || state.page >= state.lastPage; }

        if (!container) { return; }

        if (state.loading) {
            container.innerHTML = '<p class="muted" style="text-align:center;padding:24px 0">Cargando configuraciones...</p>';
            return;
        }
        if (!state.settings.length) {
            container.innerHTML = '<p class="muted" style="text-align:center;padding:24px 0">Sin configuraciones.</p>';
            return;
        }

        var groups = groupSettings(state.settings);
        var html = '';

        Object.keys(groups).sort().forEach(function (group) {
            var items = groups[group];
            html += '<div style="margin-bottom:18px">' +
                '<div style="font-size:11px;font-weight:900;color:#697681;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;padding:4px 0;border-bottom:1px solid #edf1f4">' + escapeHtml(group) + '</div>' +
                '<table class="admin-table"><thead><tr>' +
                '<th style="width:22%">Clave</th>' +
                '<th style="width:8%">Tipo</th>' +
                '<th style="width:32%">Valor</th>' +
                '<th>Descripción</th>' +
                '<th style="width:6%">Público</th>' +
                '<th style="width:8%"></th>' +
                '</tr></thead><tbody>';

            items.forEach(function (s) {
                var isEditing = state.editingKey === s.key;
                var isSaving  = state.savingKey === s.key;
                var redacted  = isRedacted(s.value);
                var typeLabel = TYPE_LABELS[s.type] || s.type || '-';

                html += '<tr data-settings-row="' + escapeHtml(s.key) + '">';

                // Key
                html += '<td><code style="font-size:12px">' + escapeHtml(s.key) + '</code></td>';

                // Type
                html += '<td><span style="font-size:10px;color:#697681;background:#edf1f4;border-radius:999px;padding:1px 7px">' + escapeHtml(typeLabel) + '</span></td>';

                // Value
                if (isEditing) {
                    html += '<td>' + editInputHtml(s) + '</td>';
                } else {
                    html += '<td>' + displayValueHtml(s) + '</td>';
                }

                // Description
                html += '<td style="font-size:12px;color:#697681">' + escapeHtml(s.description || '') + '</td>';

                // is_public
                html += '<td style="text-align:center">' +
                    (s.is_public ? '<span style="color:#04ac85;font-size:14px">✓</span>' : '<span style="color:#dde3e8;font-size:14px">—</span>') +
                    '</td>';

                // Actions
                if (redacted) {
                    html += '<td><span style="font-size:11px;color:#b33a3a">🔒</span></td>';
                } else if (isEditing) {
                    html += '<td style="white-space:nowrap">' +
                        '<button type="button" class="btn-main btn-sm" style="font-size:11px;margin-right:3px' + (isSaving ? ';opacity:.6' : '') + '" data-settings-save="' + escapeHtml(s.key) + '"' + (isSaving ? ' disabled' : '') + '>' + (isSaving ? '...' : 'Guardar') + '</button>' +
                        '<button type="button" class="btn-ghost btn-sm" style="font-size:11px" data-settings-cancel>✕</button>' +
                        '</td>';
                } else {
                    html += '<td>' +
                        '<button type="button" class="btn-ghost btn-sm" style="font-size:11px" data-settings-edit="' + escapeHtml(s.key) + '">Editar</button>' +
                        '</td>';
                }

                html += '</tr>';
            });

            html += '</tbody></table></div>';
        });

        container.innerHTML = html;
    }

    function loadSettings(root) {
        state.loading = true;
        clearMsg(root);
        renderSettings(root);

        var params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', 100);
        if (state.search) { params.set('search', state.search); }

        window.CCApi.request(endpoint('/api/v1/admin/settings?' + params.toString()))
            .then(function (res) {
                state.settings = res.data || [];
                state.total    = (res.meta && res.meta.total) || state.settings.length;
                state.page     = (res.meta && res.meta.current_page) || state.page;
                state.lastPage = (res.meta && res.meta.last_page) || 1;
                state.loading  = false;
                renderSettings(root);
            })
            .catch(function (err) {
                state.settings = []; state.loading = false;
                renderSettings(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function startEdit(root, key) {
        var s = state.settings.find(function (x) { return x.key === key; });
        if (!s || isRedacted(s.value)) { return; }
        state.editingKey = key;
        renderSettings(root);
        var row = qs('[data-settings-row="' + key + '"]', root);
        if (row) {
            var input = qs('[data-edit-input]', row);
            if (input) { input.focus(); }
        }
    }

    function cancelEdit(root) {
        state.editingKey = null;
        renderSettings(root);
    }

    function saveEdit(root, key) {
        var row = qs('[data-settings-row="' + key + '"]', root);
        if (!row) { return; }
        var input = qs('[data-edit-input]', row);
        if (!input) { return; }

        var s = state.settings.find(function (x) { return x.key === key; });
        if (!s) { return; }

        var rawValue = input.value;
        var payload;

        switch (s.type) {
            case 'boolean':
                payload = { value: rawValue === '1' ? true : false };
                break;
            case 'integer':
                payload = { value: parseInt(rawValue, 10) };
                break;
            default:
                payload = { value: rawValue };
        }

        state.savingKey = key;
        renderSettings(root);

        window.CCApi.request(endpoint('/api/v1/admin/settings/' + encodeURIComponent(key)), { method: 'PATCH', body: payload })
            .then(function (res) {
                var updated = (res.data) || res;
                var idx = state.settings.findIndex(function (x) { return x.key === key; });
                if (idx > -1 && updated) { state.settings[idx] = updated; }
                state.savingKey  = null;
                state.editingKey = null;
                renderSettings(root);
                showMsg(root, 'success', 'Configuración "' + key + '" actualizada.');
            })
            .catch(function (err) {
                state.savingKey = null;
                renderSettings(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function bind(root) {
        var searchInput = qs('[data-settings-search]', root);
        if (searchInput) {
            var timer;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    state.search = searchInput.value; state.page = 1; loadSettings(root);
                }, 350);
            });
        }

        var refreshBtn = qs('[data-settings-refresh]', root);
        if (refreshBtn) { refreshBtn.addEventListener('click', function () { state.page = 1; loadSettings(root); }); }

        var prevBtn = qs('[data-settings-prev]', root);
        if (prevBtn) { prevBtn.addEventListener('click', function () { if (state.page > 1) { state.page -= 1; loadSettings(root); } }); }

        var nextBtn = qs('[data-settings-next]', root);
        if (nextBtn) { nextBtn.addEventListener('click', function () { if (state.page < state.lastPage) { state.page += 1; loadSettings(root); } }); }

        root.addEventListener('click', function (event) {
            var editBtn   = event.target.closest('[data-settings-edit]');
            var saveBtn   = event.target.closest('[data-settings-save]');
            var cancelBtn = event.target.closest('[data-settings-cancel]');

            if (editBtn)   { startEdit(root, editBtn.getAttribute('data-settings-edit')); return; }
            if (saveBtn)   { saveEdit(root, saveBtn.getAttribute('data-settings-save')); return; }
            if (cancelBtn) { cancelEdit(root); }
        });

        root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && state.editingKey) { cancelEdit(root); return; }
            if (event.key === 'Enter' && state.editingKey) {
                var input = event.target.closest('[data-edit-input]');
                if (input && input.tagName !== 'TEXTAREA') {
                    event.preventDefault();
                    saveEdit(root, state.editingKey);
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-admin-settings]');
        if (!root) { return; }
        bind(root);
        loadSettings(root);
    });
})(window, document);
