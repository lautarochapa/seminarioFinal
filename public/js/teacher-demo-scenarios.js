(function (window, document) {
    'use strict';

    var state = {
        scenarios: [],
        loading: false,
        selected: null,
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

    // ── List ──────────────────────────────────────────────────────────────────

    function renderList(root) {
        var listEl  = qs('[data-demo-list]', root);
        var countEl = qs('[data-demo-count]', root);
        if (countEl) { countEl.textContent = state.scenarios.length + ' escenario' + (state.scenarios.length !== 1 ? 's' : ''); }
        if (!listEl) { return; }

        if (state.loading) {
            listEl.innerHTML = '<p style="font-size:13px;color:#716d64;text-align:center;padding:24px 0">Cargando escenarios...</p>';
            return;
        }
        if (!state.scenarios.length) {
            listEl.innerHTML = '<p style="font-size:13px;color:#716d64;text-align:center;padding:24px 0">Sin escenarios disponibles.</p>';
            return;
        }

        listEl.innerHTML = state.scenarios.map(function (s) {
            var isActive = state.selected && String(state.selected.id) === String(s.id);
            return '<div data-demo-card="' + escapeHtml(String(s.id)) + '" style="' +
                'padding:11px 12px;border-radius:8px;cursor:pointer;margin-bottom:6px;' +
                'background:' + (isActive ? '#e7f7f2' : '#f9f9f9') + ';' +
                'border:1px solid ' + (isActive ? 'rgba(4,172,133,.7)' : '#e3ded2') + '">' +
                '<div style="font-size:14px;font-weight:700;margin-bottom:3px">' + escapeHtml(s.name || '') + '</div>' +
                (s.description ? '<div style="font-size:12px;color:#716d64;line-height:1.4">' + escapeHtml(s.description) + '</div>' : '') +
                (s.route ? '<div style="font-size:11px;color:#04ac85;margin-top:4px;font-family:monospace">' + escapeHtml(s.route) + '</div>' : '') +
                '</div>';
        }).join('');
    }

    function renderDetail(root) {
        var detailEl = qs('[data-demo-detail]', root);
        if (!detailEl) { return; }

        if (!state.selected) {
            detailEl.innerHTML = '<p style="font-size:13px;color:#716d64;text-align:center;padding:32px 0">Seleccioná un escenario para ver su detalle.</p>';
            return;
        }

        var s = state.selected;
        var routeHtml = s.route
            ? '<div style="margin-top:14px">' +
              '<div style="font-size:11px;font-weight:700;color:#716d64;margin-bottom:4px">RUTA DE DEMO</div>' +
              '<code style="display:block;background:#f0f4f7;padding:8px 12px;border-radius:6px;font-size:14px;color:#04ac85">' + escapeHtml(s.route) + '</code>' +
              '<div style="margin-top:10px">' +
              '<a href="' + escapeHtml(s.route) + '" class="btn-main" style="font-size:13px;text-decoration:none" target="_blank">Abrir ruta →</a>' +
              '</div>' +
              '</div>'
            : '';

        detailEl.innerHTML =
            '<div style="margin-bottom:8px">' +
            '<div style="font-size:11px;font-weight:700;color:#716d64;margin-bottom:4px">ESCENARIO</div>' +
            '<div style="font-size:20px;font-weight:900">' + escapeHtml(s.name || '') + '</div>' +
            '</div>' +
            (s.description
                ? '<div style="margin-bottom:6px">' +
                  '<div style="font-size:11px;font-weight:700;color:#716d64;margin-bottom:4px">DESCRIPCIÓN</div>' +
                  '<div style="font-size:14px;line-height:1.5;color:#24252a">' + escapeHtml(s.description) + '</div>' +
                  '</div>'
                : '') +
            routeHtml;
    }

    function loadScenarios(root) {
        state.loading = true;
        renderList(root);
        renderDetail(root);

        window.CCApi.request(endpoint('/api/v1/demo-scenarios'))
            .then(function (res) {
                state.scenarios = res.data || [];
                state.loading   = false;
                renderList(root);
                renderDetail(root);
            })
            .catch(function (err) {
                state.scenarios = []; state.loading = false;
                renderList(root);
                showMsg(root, '[data-demo-message]', 'danger', errMsg(err));
            });
    }

    // ── Bind ──────────────────────────────────────────────────────────────────

    function bind(root) {
        root.addEventListener('click', function (event) {
            var card = event.target.closest('[data-demo-card]');
            if (!card) { return; }
            var id = card.getAttribute('data-demo-card');
            var s  = state.scenarios.find(function (x) { return String(x.id) === String(id); });
            if (s) {
                state.selected = s;
                renderList(root);
                renderDetail(root);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-teacher-demo-scenarios]');
        if (!root) { return; }
        bind(root);
        loadScenarios(root);
    });
})(window, document);
