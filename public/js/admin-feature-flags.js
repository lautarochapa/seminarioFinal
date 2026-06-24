(function (window, document) {
    'use strict';

    var state = {
        flags: [],
        loading: false,
        saving: {},  // key -> true/false
        error: null,
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
        var el = qs('[data-ff-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
        if (type === 'success') { setTimeout(function () { el.style.display = 'none'; }, 3000); }
    }

    function groupFlags(flags) {
        var groups = {};
        flags.forEach(function (f) {
            var dot   = f.key.indexOf('.');
            var group = dot > -1 ? f.key.slice(0, dot) : 'general';
            if (!groups[group]) { groups[group] = []; }
            groups[group].push(f);
        });
        return groups;
    }

    var GROUP_LABELS = {
        module:  'Módulos',
        auth:    'Autenticación',
        general: 'General',
        ai:      'Inteligencia artificial',
        mail:    'Correo',
        scraping:'Scraping',
    };

    function flagCardHtml(f) {
        var isSaving = !!state.saving[f.key];
        var enabled  = !!f.enabled;
        var toggleBg = enabled ? '#04ac85' : '#c5cdd3';
        var dot      = enabled ? 'calc(100% - 20px)' : '2px';

        return '<div style="background:#fff;border:1px solid ' + (enabled ? 'rgba(4,172,133,.35)' : '#dde3e8') + ';border-radius:10px;padding:16px 18px;display:flex;align-items:flex-start;gap:14px;' + (isSaving ? 'opacity:.65' : '') + '">' +

            // Toggle
            '<button type="button" data-ff-toggle="' + escapeHtml(f.key) + '" aria-label="' + (enabled ? 'Desactivar' : 'Activar') + '" ' + (isSaving ? 'disabled ' : '') +
            'style="flex-shrink:0;margin-top:2px;width:42px;height:24px;border-radius:999px;border:none;cursor:' + (isSaving ? 'wait' : 'pointer') + ';' +
            'background:' + toggleBg + ';position:relative;transition:background .18s">' +
            '<span style="position:absolute;top:2px;left:' + dot + ';width:20px;height:20px;border-radius:50%;background:#fff;transition:left .18s;box-shadow:0 1px 3px rgba(0,0,0,.25)"></span>' +
            '</button>' +

            // Info
            '<div style="flex:1;min-width:0">' +
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px">' +
            '<span style="font-size:14px;font-weight:900">' + escapeHtml(f.name || f.key) + '</span>' +
            '<code style="font-size:11px;color:#697681;background:#f0f3f5;border-radius:4px;padding:1px 6px">' + escapeHtml(f.key) + '</code>' +
            '<span style="font-size:11px;font-weight:700;color:' + (enabled ? '#04ac85' : '#697681') + ';background:' + (enabled ? '#e7f7f2' : '#f0f3f5') + ';border-radius:999px;padding:1px 8px">' +
            (isSaving ? '...' : (enabled ? 'Activo' : 'Inactivo')) + '</span>' +
            '</div>' +
            (f.description ? '<div style="font-size:12px;color:#697681;line-height:1.4">' + escapeHtml(f.description) + '</div>' : '') +
            '</div>' +
            '</div>';
    }

    function renderFlags(root) {
        var container = qs('[data-ff-container]', root);
        var countEl   = qs('[data-ff-count]', root);
        if (countEl) { countEl.textContent = state.flags.length + ' flag' + (state.flags.length !== 1 ? 's' : ''); }
        if (!container) { return; }

        if (state.loading) {
            container.innerHTML = '<p class="muted" style="text-align:center;padding:32px 0">Cargando feature flags...</p>';
            return;
        }
        if (!state.flags.length) {
            container.innerHTML = '<p class="muted" style="text-align:center;padding:32px 0">No hay feature flags definidos.</p>';
            return;
        }

        var groups  = groupFlags(state.flags);
        var html    = '';

        Object.keys(groups).sort().forEach(function (group) {
            var items     = groups[group];
            var groupLabel = GROUP_LABELS[group] || (group.charAt(0).toUpperCase() + group.slice(1));

            html += '<div style="margin-bottom:22px">' +
                '<div style="font-size:11px;font-weight:900;color:#697681;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #edf1f4">' +
                escapeHtml(groupLabel) + '</div>' +
                '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:10px">';

            items.forEach(function (f) { html += flagCardHtml(f); });
            html += '</div></div>';
        });

        container.innerHTML = html;
    }

    function loadFlags(root) {
        state.loading = true;
        renderFlags(root);

        window.CCApi.request(endpoint('/api/v1/admin/feature-flags'))
            .then(function (res) {
                state.flags   = res.data || [];
                state.loading = false;
                renderFlags(root);
            })
            .catch(function (err) {
                state.flags   = [];
                state.loading = false;
                renderFlags(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function toggleFlag(root, key) {
        var flag = state.flags.find(function (f) { return f.key === key; });
        if (!flag || state.saving[key]) { return; }

        var newEnabled = !flag.enabled;
        state.saving[key] = true;
        renderFlags(root);

        window.CCApi.request(endpoint('/api/v1/admin/feature-flags/' + encodeURIComponent(key)), {
            method: 'PATCH',
            body: { enabled: newEnabled },
        })
            .then(function (res) {
                var updated = res.data || res;
                var idx = state.flags.findIndex(function (f) { return f.key === key; });
                if (idx > -1 && updated) { state.flags[idx] = updated; }
                state.saving[key] = false;
                renderFlags(root);
                showMsg(root, 'success', '"' + (flag.name || key) + '" ' + (newEnabled ? 'activado' : 'desactivado') + '.');
            })
            .catch(function (err) {
                state.saving[key] = false;
                renderFlags(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function bind(root) {
        var refreshBtn = qs('[data-ff-refresh]', root);
        if (refreshBtn) { refreshBtn.addEventListener('click', function () { loadFlags(root); }); }

        root.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-ff-toggle]');
            if (btn) { toggleFlag(root, btn.getAttribute('data-ff-toggle')); }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-admin-feature-flags]');
        if (!root) { return; }
        bind(root);
        loadFlags(root);
    });
})(window, document);
