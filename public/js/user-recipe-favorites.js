(function (window, document) {
    'use strict';

    var root = null;

    var TAB_ORDER = ['favoritos', 'historial'];
    var TAB_LABELS = { favoritos: 'Favoritos', historial: 'Historial' };

    var state = {
        activeTab: 'favoritos',
        loading:   false,
        tabs: {
            favoritos: { results: [], page: 1, lastPage: 1, total: 0, loaded: false },
            historial: { results: [], page: 1, lastPage: 1, total: 0, loaded: false },
        },
        selectedItem: null, // {type: 'favorite'|'cook_log', data: {...}}
    };

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined) { return ''; }
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function fmtMinutes(m) {
        var n = parseInt(m, 10);
        if (!n) { return '—'; }
        if (n < 60) { return n + ' min'; }
        var h = Math.floor(n / 60); var rm = n % 60;
        return rm ? h + 'h ' + rm + 'min' : h + 'h';
    }

    function diffLabel(d) {
        if (d === 'easy')   { return 'Fácil'; }
        if (d === 'medium') { return 'Media'; }
        if (d === 'hard')   { return 'Difícil'; }
        return d || '';
    }

    function fmtDate(iso) {
        if (!iso) { return '—'; }
        try {
            var d = new Date(iso);
            return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch (e) { return iso; }
    }

    function fmtDateTime(iso) {
        if (!iso) { return '—'; }
        try {
            var d = new Date(iso);
            return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
                ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
        } catch (e) { return iso; }
    }

    // ─── Render tabs ─────────────────────────────────────────────────────────────

    function renderTabs() {
        var el = qs('[data-fav-tabs]', root);
        if (!el) { return; }
        el.innerHTML = TAB_ORDER.map(function (key) {
            return '<button type="button" class="audit-tab' + (key === state.activeTab ? ' active' : '') + '" data-fav-tab="' + key + '">' +
                escapeHtml(TAB_LABELS[key]) + '</button>';
        }).join('');
    }

    // ─── Render cards ─────────────────────────────────────────────────────────────

    function renderFavoriteCard(fav) {
        var r = fav.recipe || {};
        var totalTime = (parseInt(r.prep_time_minutes, 10) || 0) + (parseInt(r.cook_time_minutes, 10) || 0);
        return '<div data-fav-card data-id="' + escapeHtml(fav.id) + '" data-type="favorite" ' +
            'style="border:1px solid #dde3e8;border-radius:8px;padding:12px;cursor:pointer;background:#fff;transition:box-shadow 0.15s" ' +
            'onmouseover="this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.08)\'" onmouseout="this.style.boxShadow=\'\'">' +
            '<div style="font-size:14px;font-weight:900;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(r.name || '—') + '</div>' +
            '<div style="font-size:12px;color:#697681;margin-bottom:6px">' +
            (totalTime ? fmtMinutes(totalTime) + ' · ' : '') +
            (r.difficulty ? escapeHtml(diffLabel(r.difficulty)) + ' · ' : '') +
            (r.servings ? r.servings + ' porc.' : '') +
            '</div>' +
            '<div style="font-size:11px;color:#697681">Guardado el ' + fmtDate(fav.favorited_at) + '</div>' +
            (r.is_official ? '<span style="display:inline-block;margin-top:5px;background:#e7f7f2;color:#04ac85;border-radius:4px;padding:2px 7px;font-size:11px">Oficial</span>' : '') +
            '</div>';
    }

    function renderCookLogCard(log) {
        var r = log.recipe || {};
        return '<div data-fav-card data-id="' + escapeHtml(log.id) + '" data-type="cook_log" ' +
            'style="border:1px solid #dde3e8;border-radius:8px;padding:12px;cursor:pointer;background:#fff;transition:box-shadow 0.15s" ' +
            'onmouseover="this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.08)\'" onmouseout="this.style.boxShadow=\'\'">' +
            '<div style="font-size:14px;font-weight:900;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(r.name || '—') + '</div>' +
            '<div style="font-size:12px;color:#697681;margin-bottom:4px">' +
            escapeHtml(log.servings) + ' porci' + (log.servings === 1 ? 'ón' : 'ones') + ' · ' + fmtDateTime(log.cooked_at) +
            '</div>' +
            (log.stock_discounted
                ? '<span style="display:inline-block;background:#e7f7f2;color:#04ac85;border-radius:4px;padding:2px 7px;font-size:11px">Stock descontado</span>'
                : '<span style="display:inline-block;background:#f0f4f8;color:#697681;border-radius:4px;padding:2px 7px;font-size:11px">Sin descuento</span>') +
            '</div>';
    }

    // ─── Render list ─────────────────────────────────────────────────────────────

    function renderList() {
        var listEl  = qs('[data-fav-list]', root);
        var paginEl = qs('[data-fav-pagination]', root);
        var pageEl  = qs('[data-fav-page]', root);
        var prevBtn = qs('[data-fav-prev]', root);
        var nextBtn = qs('[data-fav-next]', root);
        if (!listEl) { return; }

        if (state.loading) {
            listEl.innerHTML = '<p class="muted" style="font-size:13px">Cargando...</p>';
            if (paginEl) { paginEl.style.display = 'none'; }
            return;
        }

        var tab = state.tabs[state.activeTab];
        if (!tab.results.length) {
            listEl.innerHTML = '<p class="muted" style="font-size:13px">' +
                (state.activeTab === 'favoritos'
                    ? 'No tenés recetas guardadas como favoritas todavía.'
                    : 'Todavía no registraste ninguna receta cocinada.') +
                '</p>';
            if (paginEl) { paginEl.style.display = 'none'; }
            return;
        }

        if (state.activeTab === 'favoritos') {
            listEl.innerHTML = tab.results.map(renderFavoriteCard).join('');
        } else {
            listEl.innerHTML = tab.results.map(renderCookLogCard).join('');
        }

        if (paginEl) {
            if (tab.lastPage <= 1) {
                paginEl.style.display = 'none';
            } else {
                paginEl.style.display = '';
                if (pageEl) { pageEl.textContent = 'Pág ' + tab.page + ' / ' + tab.lastPage; }
                if (prevBtn) { prevBtn.disabled = tab.page <= 1; }
                if (nextBtn) { nextBtn.disabled = tab.page >= tab.lastPage; }
            }
        }
    }

    // ─── Render detail ────────────────────────────────────────────────────────────

    function renderFavoriteDetail(fav) {
        var detailEl = qs('[data-fav-detail]', root);
        if (!detailEl) { return; }

        var r = fav.recipe || {};
        var totalTime = (parseInt(r.prep_time_minutes, 10) || 0) + (parseInt(r.cook_time_minutes, 10) || 0);

        detailEl.innerHTML =
            '<h2 style="font-size:15px;font-weight:900;margin:0 0 10px">' + escapeHtml(r.name || '—') + '</h2>' +
            '<table style="width:100%;border-collapse:collapse;font-size:13px">' +
            (r.difficulty ? '<tr><td style="padding:5px 0;color:#697681">Dificultad</td><td style="padding:5px 0;font-weight:700">' + escapeHtml(diffLabel(r.difficulty)) + '</td></tr>' : '') +
            (r.prep_time_minutes ? '<tr><td style="padding:5px 0;color:#697681">Preparación</td><td style="padding:5px 0">' + fmtMinutes(r.prep_time_minutes) + '</td></tr>' : '') +
            (r.cook_time_minutes ? '<tr><td style="padding:5px 0;color:#697681">Cocción</td><td style="padding:5px 0">' + fmtMinutes(r.cook_time_minutes) + '</td></tr>' : '') +
            (totalTime ? '<tr><td style="padding:5px 0;color:#697681">Tiempo total</td><td style="padding:5px 0">' + fmtMinutes(totalTime) + '</td></tr>' : '') +
            (r.servings ? '<tr><td style="padding:5px 0;color:#697681">Porciones</td><td style="padding:5px 0">' + escapeHtml(r.servings) + '</td></tr>' : '') +
            '<tr><td style="padding:5px 0;color:#697681">Guardado</td><td style="padding:5px 0">' + fmtDate(fav.favorited_at) + '</td></tr>' +
            '</table>' +
            (r.is_official ? '<div style="margin-top:8px"><span style="background:#e7f7f2;color:#04ac85;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:700">Receta oficial</span></div>' : '') +
            '<div data-fav-actions-panel></div>';

        if (window.RecipeFavoritesActions) {
            var actPanel = qs('[data-fav-actions-panel]', detailEl);
            if (actPanel) {
                window.RecipeFavoritesActions.mount(actPanel, r.id, r.servings || 1);
            }
        }
    }

    function renderCookLogDetail(log) {
        var detailEl = qs('[data-fav-detail]', root);
        if (!detailEl) { return; }

        var r = log.recipe || {};

        detailEl.innerHTML =
            '<h2 style="font-size:15px;font-weight:900;margin:0 0 10px">' + escapeHtml(r.name || '—') + '</h2>' +
            '<table style="width:100%;border-collapse:collapse;font-size:13px">' +
            '<tr><td style="padding:5px 0;color:#697681">Porciones</td><td style="padding:5px 0;font-weight:700">' + escapeHtml(log.servings) + '</td></tr>' +
            '<tr><td style="padding:5px 0;color:#697681">Cocinada el</td><td style="padding:5px 0">' + fmtDateTime(log.cooked_at) + '</td></tr>' +
            '<tr><td style="padding:5px 0;color:#697681">Stock</td><td style="padding:5px 0">' +
            (log.stock_discounted
                ? '<span style="color:#04ac85;font-weight:700">Descontado</span>'
                : '<span style="color:#697681">Sin descuento</span>') +
            '</td></tr>' +
            (log.family_group_id ? '<tr><td style="padding:5px 0;color:#697681">Grupo familiar</td><td style="padding:5px 0">#' + escapeHtml(log.family_group_id) + '</td></tr>' : '') +
            (log.notes ? '<tr><td colspan="2" style="padding:8px 0 0;color:#697681;font-size:12px">' + escapeHtml(log.notes) + '</td></tr>' : '') +
            '</table>' +
            (r.is_official ? '<div style="margin-top:8px"><span style="background:#e7f7f2;color:#04ac85;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:700">Receta oficial</span></div>' : '');
    }

    // ─── API ─────────────────────────────────────────────────────────────────────

    function showMsg(type, text) {
        var msgEl = qs('[data-fav-message]', root);
        if (!msgEl) { return; }
        msgEl.textContent = text;
        msgEl.style.display = 'block';
        msgEl.style.background = type === 'ok' ? '#e7f7f2' : '#f7e7e7';
        msgEl.style.color      = type === 'ok' ? '#04ac85' : '#b33a3a';
        if (type === 'ok') { setTimeout(function () { msgEl.style.display = 'none'; }, 4000); }
    }

    function hideMsg() {
        var msgEl = qs('[data-fav-message]', root);
        if (msgEl) { msgEl.style.display = 'none'; }
    }

    function fetchTab(tabKey) {
        state.loading = true;
        hideMsg();
        renderList();

        var url = tabKey === 'favoritos'
            ? endpoint('/users/me/favorite-recipes?page=' + state.tabs[tabKey].page + '&per_page=12')
            : endpoint('/users/me/cooked-recipes?page='   + state.tabs[tabKey].page + '&per_page=12');

        window.CCApi.request(url)
            .then(function (res) {
                var tab = state.tabs[tabKey];
                tab.results  = res.data || [];
                var meta = res.meta || {};
                tab.total    = meta.total || tab.results.length;
                tab.lastPage = meta.last_page || 1;
                tab.loaded   = true;
                state.loading = false;
                if (state.activeTab === tabKey) { renderList(); }
            })
            .catch(function (err) {
                state.tabs[tabKey].loaded = true;
                state.loading = false;
                renderList();
                var status = err && err.status;
                if (status !== 401) {
                    showMsg('err', (err && err.payload && err.payload.error && err.payload.error.message) || 'Error al cargar.');
                }
            });
    }

    // ─── Events ──────────────────────────────────────────────────────────────────

    function switchTab(tabKey) {
        state.activeTab    = tabKey;
        state.selectedItem = null;

        renderTabs();
        hideMsg();

        var detailEl = qs('[data-fav-detail]', root);
        if (detailEl) {
            detailEl.innerHTML = '<p class="muted" style="font-size:13px">Seleccioná una receta para ver el detalle.</p>';
        }

        if (!state.tabs[tabKey].loaded) {
            fetchTab(tabKey);
        } else {
            renderList();
        }
    }

    function bindEvents() {
        if (!root) { return; }

        root.addEventListener('click', function (e) {
            var t = e.target;

            var tabBtn = t.closest('[data-fav-tab]');
            if (tabBtn) { switchTab(tabBtn.getAttribute('data-fav-tab')); return; }

            var card = t.closest('[data-fav-card]');
            if (card) {
                var itemId   = card.getAttribute('data-id');
                var itemType = card.getAttribute('data-type');
                var tab      = state.tabs[state.activeTab];
                var item     = tab.results.filter(function (r) { return String(r.id) === String(itemId); })[0];
                if (item) {
                    state.selectedItem = { type: itemType, data: item };
                    if (itemType === 'favorite') {
                        renderFavoriteDetail(item);
                    } else {
                        renderCookLogDetail(item);
                    }
                }
                return;
            }

            if (t.closest('[data-fav-prev]')) {
                var ctab = state.tabs[state.activeTab];
                if (ctab.page > 1) { ctab.page--; ctab.loaded = false; fetchTab(state.activeTab); }
                return;
            }
            if (t.closest('[data-fav-next]')) {
                var ntab = state.tabs[state.activeTab];
                if (ntab.page < ntab.lastPage) { ntab.page++; ntab.loaded = false; fetchTab(state.activeTab); }
                return;
            }
        });
    }

    // ─── Boot ────────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        root = qs('[data-user-fav]');
        if (!root) { return; }

        renderTabs();
        bindEvents();
        fetchTab('favoritos');
    });

})(window, document);
