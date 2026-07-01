(function (window, document) {
    'use strict';

    var state = {
        notifications: [],
        page: 1,
        lastPage: 1,
        total: 0,
        unreadCount: 0,
        loading: false,
        typeFilter: '',
        readFilter: '',
        preferences: null,
        prefsLoading: false,
        prefsSaving: false,
    };

    var NOTIF_TYPES = [
        { value: 'stock_low',           label: 'Bajo stock',           icon: '📦', bg: '#fff3e0', color: '#b35c00' },
        { value: 'stock_expiry',        label: 'Vencimiento',          icon: '⏰', bg: '#f7e7e7', color: '#b33a3a' },
        { value: 'menu_today',          label: 'Menú del día',         icon: '🍽',  bg: '#e7f7f2', color: '#04ac85' },
        { value: 'budget_warning',      label: 'Alerta presupuesto',   icon: '💰', bg: '#fff3e0', color: '#b35c00' },
        { value: 'budget_exceeded',     label: 'Presupuesto superado', icon: '🚨', bg: '#f7e7e7', color: '#b33a3a' },
        { value: 'scraping',            label: 'Actualización precios',icon: '🔄', bg: '#e7f3ff', color: '#1a5fb4' },
        { value: 'purchase_confirmed',  label: 'Compra confirmada',    icon: '✅', bg: '#e7f7f2', color: '#04ac85' },
        { value: 'supplement',          label: 'Suplemento',           icon: '💊', bg: '#f3e7ff', color: '#6a1fb4' },
        { value: 'general',             label: 'General',              icon: '🔔', bg: '#f0f0f0', color: '#555'    },
    ];

    var PREF_CHANNELS = [
        { key: 'app',   label: 'App' },
        { key: 'email', label: 'Email' },
        { key: 'push',  label: 'Push' },
    ];

    function qs(sel, root) { return (root || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v == null) { return ''; }
        return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function endpoint(path) { return path; }

    function errMsg(err) {
        if (err && err.message) { return err.message; }
        return 'Ocurrió un error inesperado.';
    }

    function typeInfo(type) {
        return NOTIF_TYPES.find(function (t) { return t.value === type; }) ||
            { label: type || 'General', icon: '🔔', bg: '#f0f0f0', color: '#555' };
    }

    function typeBadge(type) {
        var t = typeInfo(type);
        return '<span style="background:' + t.bg + ';color:' + t.color + ';border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;white-space:nowrap">' +
            t.icon + ' ' + escapeHtml(t.label) + '</span>';
    }

    function showMsg(root, type, msg) {
        var el = qs('[data-notif-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearMsg(root) {
        var el = qs('[data-notif-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function showPrefsMsg(root, type, msg) {
        var el = qs('[data-prefs-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearPrefsMsg(root) {
        var el = qs('[data-prefs-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function updateUnreadBadge(root) {
        var badge = qs('[data-notif-unread-count]', root);
        if (!badge) { return; }
        badge.textContent = state.unreadCount > 0 ? state.unreadCount + ' sin leer' : 'Todo leído';
        badge.style.background = state.unreadCount > 0 ? '#f7e7e7' : '#e7f7f2';
        badge.style.color      = state.unreadCount > 0 ? '#b33a3a' : '#04ac85';
    }

    function renderList(root) {
        var listEl  = qs('[data-notif-list]', root);
        var countEl = qs('[data-notif-count]', root);
        var pageEl  = qs('[data-notif-page]', root);
        var prevBtn = qs('[data-notif-prev]', root);
        var nextBtn = qs('[data-notif-next]', root);

        if (countEl) { countEl.textContent = state.total + ' notificación' + (state.total !== 1 ? 'es' : ''); }
        if (pageEl)  { pageEl.textContent = 'Pág. ' + state.page + ' / ' + state.lastPage; }
        if (prevBtn) { prevBtn.disabled = state.loading || state.page <= 1; }
        if (nextBtn) { nextBtn.disabled = state.loading || state.page >= state.lastPage; }
        updateUnreadBadge(root);

        if (!listEl) { return; }

        if (state.loading) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:16px 0;text-align:center">Cargando notificaciones...</p>';
            return;
        }
        if (!state.notifications.length) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:16px 0;text-align:center">No hay notificaciones para los filtros seleccionados.</p>';
            return;
        }

        listEl.innerHTML = state.notifications.map(function (n) {
            var t    = typeInfo(n.type);
            var isRead = !!n.read_at;
            var date = n.created_at ? n.created_at.substring(0, 16).replace('T', ' ') : '-';
            return '<div style="display:flex;gap:10px;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f0f0f0;' +
                (isRead ? 'opacity:0.65' : 'background:linear-gradient(90deg,#f7fbff 0,transparent 6px)') + '">' +
                '<span style="font-size:22px;line-height:1;flex-shrink:0;margin-top:2px">' + t.icon + '</span>' +
                '<div style="flex:1;min-width:0">' +
                '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:3px">' +
                typeBadge(n.type) +
                '<span style="font-size:11px;color:#66746b">' + escapeHtml(date) + '</span>' +
                (isRead ? '<span style="font-size:10px;color:#66746b;background:#f0f0f0;border-radius:999px;padding:1px 7px">Leída</span>' : '<span style="width:7px;height:7px;border-radius:50%;background:#2f80ed;display:inline-block;flex-shrink:0"></span>') +
                '</div>' +
                '<div style="font-size:14px;font-weight:' + (isRead ? '400' : '700') + ';margin-bottom:2px">' + escapeHtml(n.title || '') + '</div>' +
                '<div style="font-size:13px;color:#44524a">' + escapeHtml(n.message || n.body || '') + '</div>' +
                '</div>' +
                (!isRead
                    ? '<button type="button" class="btn-secondary-web btn-sm" data-notif-read="' + escapeHtml(String(n.id)) + '" style="flex-shrink:0;font-size:11px;white-space:nowrap">Marcar leída</button>'
                    : '') +
                '</div>';
        }).join('');
    }

    function loadNotifications(root) {
        state.loading = true;
        clearMsg(root);
        renderList(root);

        var params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', 20);
        if (state.typeFilter) { params.set('type', state.typeFilter); }
        if (state.readFilter) { params.set('read', state.readFilter); }

        window.CCApi.request(endpoint('/api/v1/notifications?' + params.toString()))
            .then(function (response) {
                state.notifications = response.data || [];
                state.total         = (response.meta && response.meta.total) || state.notifications.length;
                state.page          = (response.meta && response.meta.current_page) || state.page;
                state.lastPage      = (response.meta && response.meta.last_page) || 1;
                state.loading       = false;
                renderList(root);
            })
            .catch(function (err) {
                state.notifications = [];
                state.loading       = false;
                renderList(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function loadUnreadCount(root) {
        window.CCApi.request(endpoint('/api/v1/notifications/unread-count'))
            .then(function (response) {
                state.unreadCount = (response.data && response.data.count != null) ? response.data.count : (response.count || 0);
                updateUnreadBadge(root);
            })
            .catch(function () {});
    }

    function markRead(root, id) {
        var btn = qs('[data-notif-read="' + id + '"]', root);
        if (btn) { btn.disabled = true; }

        window.CCApi.request(endpoint('/api/v1/notifications/' + encodeURIComponent(id) + '/read'), { method: 'PATCH' })
            .then(function () {
                var n = state.notifications.find(function (x) { return String(x.id) === String(id); });
                if (n) { n.read_at = new Date().toISOString(); }
                if (state.unreadCount > 0) { state.unreadCount -= 1; }
                renderList(root);
            })
            .catch(function (err) {
                if (btn) { btn.disabled = false; }
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function markAllRead(root) {
        var btn = qs('[data-notif-read-all]', root);
        if (btn) { btn.disabled = true; btn.textContent = 'Marcando...'; }

        window.CCApi.request(endpoint('/api/v1/notifications/read-all'), { method: 'PATCH' })
            .then(function () {
                state.notifications.forEach(function (n) { n.read_at = n.read_at || new Date().toISOString(); });
                state.unreadCount = 0;
                if (btn) { btn.disabled = false; btn.textContent = 'Marcar todo leído'; }
                renderList(root);
            })
            .catch(function (err) {
                if (btn) { btn.disabled = false; btn.textContent = 'Marcar todo leído'; }
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function renderPreferences(root) {
        var container = qs('[data-prefs-grid]', root);
        if (!container) { return; }

        if (state.prefsLoading) {
            container.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">Cargando preferencias...</p>';
            return;
        }
        if (!state.preferences) {
            container.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">No se pudieron cargar las preferencias.</p>';
            return;
        }

        var prefs = state.preferences;
        container.innerHTML = '<table style="width:100%;border-collapse:collapse;font-size:13px">' +
            '<thead><tr>' +
            '<th style="text-align:left;padding:6px 4px;color:#66746b;font-size:11px;text-transform:uppercase;border-bottom:1px solid #dde6df">Tipo</th>' +
            PREF_CHANNELS.map(function (ch) {
                return '<th style="text-align:center;padding:6px 4px;color:#66746b;font-size:11px;text-transform:uppercase;border-bottom:1px solid #dde6df">' + ch.label + '</th>';
            }).join('') +
            '</tr></thead><tbody>' +
            NOTIF_TYPES.map(function (t) {
                var typePref = (prefs[t.value] || {});
                return '<tr>' +
                    '<td style="padding:8px 4px;border-bottom:1px solid #f0f0f0">' +
                    '<span style="margin-right:4px">' + t.icon + '</span>' + escapeHtml(t.label) +
                    '</td>' +
                    PREF_CHANNELS.map(function (ch) {
                        var checked = typePref[ch.key] !== false;
                        return '<td style="text-align:center;padding:8px 4px;border-bottom:1px solid #f0f0f0">' +
                            '<input type="checkbox" data-pref-type="' + escapeHtml(t.value) + '" data-pref-channel="' + escapeHtml(ch.key) + '"' +
                            (checked ? ' checked' : '') +
                            ' style="width:16px;height:16px;cursor:pointer">' +
                            '</td>';
                    }).join('') +
                    '</tr>';
            }).join('') +
            '</tbody></table>';
    }

    function loadPreferences(root) {
        state.prefsLoading = true;
        renderPreferences(root);

        window.CCApi.request(endpoint('/api/v1/users/me/notification-preferences'))
            .then(function (response) {
                state.preferences  = response.data || response || {};
                state.prefsLoading = false;
                renderPreferences(root);
            })
            .catch(function () {
                state.prefsLoading = false;
                renderPreferences(root);
            });
    }

    function savePreferences(root) {
        var payload = {};
        NOTIF_TYPES.forEach(function (t) {
            payload[t.value] = {};
            PREF_CHANNELS.forEach(function (ch) {
                var cb = qs('[data-pref-type="' + t.value + '"][data-pref-channel="' + ch.key + '"]', root);
                payload[t.value][ch.key] = cb ? cb.checked : true;
            });
        });

        state.prefsSaving = true;
        var saveBtn = qs('[data-prefs-save]', root);
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Guardando...'; }
        clearPrefsMsg(root);

        window.CCApi.request(endpoint('/api/v1/users/me/notification-preferences'), { method: 'PATCH', body: payload })
            .then(function (response) {
                state.preferences  = response.data || response || payload;
                state.prefsSaving  = false;
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Guardar preferencias'; }
                showPrefsMsg(root, 'success', 'Preferencias guardadas correctamente.');
            })
            .catch(function (err) {
                state.prefsSaving = false;
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Guardar preferencias'; }
                showPrefsMsg(root, 'danger', errMsg(err));
            });
    }

    function bind(root) {
        var typeFilter = qs('[data-notif-type-filter]', root);
        if (typeFilter) {
            typeFilter.addEventListener('change', function () {
                state.typeFilter = typeFilter.value;
                state.page = 1;
                loadNotifications(root);
            });
        }

        var readFilter = qs('[data-notif-read-filter]', root);
        if (readFilter) {
            readFilter.addEventListener('change', function () {
                state.readFilter = readFilter.value;
                state.page = 1;
                loadNotifications(root);
            });
        }

        var readAllBtn = qs('[data-notif-read-all]', root);
        if (readAllBtn) {
            readAllBtn.addEventListener('click', function () { markAllRead(root); });
        }

        var refreshBtn = qs('[data-notif-refresh]', root);
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                state.page = 1;
                loadNotifications(root);
                loadUnreadCount(root);
            });
        }

        var prevBtn = qs('[data-notif-prev]', root);
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (state.page > 1) { state.page -= 1; loadNotifications(root); }
            });
        }

        var nextBtn = qs('[data-notif-next]', root);
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (state.page < state.lastPage) { state.page += 1; loadNotifications(root); }
            });
        }

        var prefsSaveBtn = qs('[data-prefs-save]', root);
        if (prefsSaveBtn) {
            prefsSaveBtn.addEventListener('click', function () { savePreferences(root); });
        }

        root.addEventListener('click', function (event) {
            var readBtn = event.target.closest('[data-notif-read]');
            if (readBtn) { markRead(root, readBtn.getAttribute('data-notif-read')); }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-notifications]');
        if (!root) { return; }
        bind(root);
        loadNotifications(root);
        loadUnreadCount(root);
        loadPreferences(root);

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var readAllBtn = qs('[data-notif-read-all]', root);
                if (readAllBtn) { readAllBtn.click(); }
            });
        }

        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var prefsGrid = qs('[data-prefs-grid]', root);
                if (prefsGrid) { prefsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        }
    });
})(window, document);
