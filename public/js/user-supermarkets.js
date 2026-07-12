(function (window, document) {
    'use strict';

    var state = {
        chains: [],
        filteredChains: [],
        selectedId: null,
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined || v === '') { return ''; }
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function showMessage(root, type, msg) {
        var el = qs('[data-supermarkets-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-supermarkets-message]', root);
        if (!el) { return; }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function formatDate(str) {
        if (!str) { return '-'; }
        try {
            return new Date(str).toLocaleDateString('es-AR', { year: 'numeric', month: 'long', day: 'numeric' });
        } catch (e) {
            return str;
        }
    }

    function applySearch(root) {
        var query = (qs('[data-supermarkets-search]', root).value || '').toLowerCase().trim();
        state.filteredChains = query
            ? state.chains.filter(function (c) { return c.name.toLowerCase().indexOf(query) !== -1; })
            : state.chains.slice();
        renderList(root);
    }

    function loadChains(root) {
        clearMessage(root);
        var listEl = qs('[data-supermarkets-list]', root);
        listEl.innerHTML = '<p class="muted">Cargando supermercados...</p>';

        window.CCApi.request(endpoint('/supermarkets'))
            .then(function (r) {
                state.chains         = r.data || [];
                state.filteredChains = state.chains.slice();
                renderList(root);
            })
            .catch(function (err) {
                var status = err.status || 0;
                var msg;
                if (status === 401) {
                    msg = 'Tu sesión venció. Por favor iniciá sesión nuevamente.';
                } else if (status === 403) {
                    msg = 'No tenés permiso para ver supermercados.';
                } else {
                    msg = (err.payload && err.payload.error && err.payload.error.message) || 'Error al cargar supermercados.';
                }
                showMessage(root, 'danger', msg);
                listEl.innerHTML = '';
            });
    }

    function renderList(root) {
        var listEl   = qs('[data-supermarkets-list]', root);
        var countEl  = qs('[data-supermarkets-count]', root);
        var chains   = state.filteredChains;

        countEl.textContent = chains.length + (chains.length === 1 ? ' cadena' : ' cadenas');

        if (!chains.length) {
            listEl.innerHTML = '<p class="muted">No se encontraron supermercados.</p>';
            return;
        }

        listEl.innerHTML = chains.map(function (c) {
            var isSelected = String(c.id) === String(state.selectedId);
            var websiteEl = c.website_url
                ? '<span class="muted" style="font-size:12px">' + escapeHtml(c.website_url) + '</span>'
                : '';
            return '<div style="border:1px solid var(--line);border-radius:8px;padding:14px;cursor:pointer;background:' +
                (isSelected ? 'var(--green-soft)' : '#fff') + '" data-supermarket-card="' + c.id + '">' +
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px">' +
                '<strong>' + escapeHtml(c.name) + '</strong>' +
                '<span style="background:#e7f7f2;color:#04ac85;padding:2px 8px;border-radius:50px;font-size:11px">Activo</span>' +
                '</div>' +
                (websiteEl ? '<div style="margin-top:4px">' + websiteEl + '</div>' : '') +
                '</div>';
        }).join('');
    }

    function showDetail(root, chainId) {
        state.selectedId = chainId;
        renderList(root);

        var detailEl = qs('[data-supermarkets-detail]', root);
        detailEl.innerHTML = '<p class="muted">Cargando detalle...</p>';

        window.CCApi.request(endpoint('/supermarkets/' + chainId))
            .then(function (r) {
                var c = r.data;
                var websiteHtml = c.website_url
                    ? '<a href="' + escapeHtml(c.website_url) + '" target="_blank" rel="noopener noreferrer" style="color:var(--green);word-break:break-all">' + escapeHtml(c.website_url) + '</a>'
                    : '<span class="muted">-</span>';
                detailEl.innerHTML =
                    '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">' +
                    '<div style="width:48px;height:48px;border-radius:8px;background:#e7f7f2;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">🛒</div>' +
                    '<div><strong style="font-size:18px">' + escapeHtml(c.name) + '</strong>' +
                    '<div><span style="background:#e7f7f2;color:#04ac85;padding:2px 8px;border-radius:50px;font-size:11px">Activo</span></div>' +
                    '</div>' +
                    '</div>' +
                    '<div class="table-line"><span class="muted">Sitio web</span><span>' + websiteHtml + '</span></div>' +
                    '<div class="table-line"><span class="muted">Registrado</span><strong>' + escapeHtml(formatDate(c.created_at)) + '</strong></div>' +
                    '<div style="margin-top:16px">' +
                    '<a href="/web/catalog" class="btn-secondary-web btn-sm">Ver catálogo de productos</a>' +
                    '</div>';
            })
            .catch(function (err) {
                var status = err.status || 0;
                var msg;
                if (status === 404) {
                    msg = 'Este supermercado ya no está disponible.';
                } else if (status === 401) {
                    msg = 'Tu sesión venció. Por favor iniciá sesión nuevamente.';
                } else {
                    msg = (err.payload && err.payload.error && err.payload.error.message) || 'Error al cargar el detalle.';
                }
                detailEl.innerHTML = '<p class="muted">' + escapeHtml(msg) + '</p>';
            });
    }

    function bind(root) {
        qs('[data-supermarkets-refresh]', root).addEventListener('click', function () {
            loadChains(root);
        });

        qs('[data-supermarkets-search]', root).addEventListener('input', function () {
            applySearch(root);
        });

        qs('[data-supermarkets-search]', root).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { applySearch(root); }
        });

        qs('[data-supermarkets-list]', root).addEventListener('click', function (e) {
            var card = e.target.closest('[data-supermarket-card]');
            if (card) {
                showDetail(root, card.getAttribute('data-supermarket-card'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-supermarkets]');
        if (!root || !window.CCApi) { return; }
        bind(root);
        loadChains(root);

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var refreshBtn = qs('[data-supermarkets-refresh]', root);
                if (refreshBtn) { refreshBtn.click(); }
            });
        }

        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = '/web/catalog';
            });
        }
    });
})(window, document);
