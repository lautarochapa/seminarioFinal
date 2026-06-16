(function (window, document) {
    'use strict';

    var state = {
        auditPage: 1,
        auditLastPage: 1,
        loginPage: 1,
        loginLastPage: 1,
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function text(value) {
        return value === null || value === undefined || value === '' ? '-' : String(value);
    }

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return text(value);
        }

        return date.toLocaleString('es-AR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatJson(value) {
        if (!value || (typeof value === 'object' && Object.keys(value).length === 0)) {
            return '<span class="muted">Sin datos</span>';
        }

        var pretty = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
        return '<pre class="audit-json">' + escapeHtml(pretty) + '</pre>';
    }

    function buildQuery(params) {
        var clean = {};
        Object.keys(params || {}).forEach(function (key) {
            var value = params[key];
            if (value !== null && value !== undefined && value !== '') {
                clean[key] = value;
            }
        });

        return new URLSearchParams(clean).toString();
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-audit-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-audit-message]', root);
        if (alert) {
            alert.textContent = '';
            alert.className = 'alert';
            alert.style.display = 'none';
        }
    }

    function handleError(root, error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        showMessage(root, 'danger', apiError.message || error.message || 'No se pudo cargar la auditoria.');
    }

    function loadAudit(root) {
        clearMessage(root);
        var query = buildQuery({
            search: valueOf('[data-audit-search]', root),
            resource: valueOf('[data-audit-resource]', root),
            user_id: valueOf('[data-audit-user]', root),
            date_from: valueOf('[data-audit-from]', root),
            date_to: valueOf('[data-audit-to]', root),
            page: state.auditPage,
            per_page: 25,
            sort: 'created_at',
            order: 'desc',
        });

        return window.CCApi.request('/admin/audit-logs?' + query)
            .then(function (response) {
                state.auditLastPage = (response.meta && response.meta.last_page) || 1;
                renderAudit(root, response.data || [], response.meta || {});
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function loadLoginLogs(root) {
        clearMessage(root);
        var query = buildQuery({
            search: valueOf('[data-login-search]', root),
            success: valueOf('[data-login-success]', root),
            date_from: valueOf('[data-login-from]', root),
            date_to: valueOf('[data-login-to]', root),
            page: state.loginPage,
            per_page: 25,
            sort: 'created_at',
            order: 'desc',
        });

        return window.CCApi.request('/admin/login-logs?' + query)
            .then(function (response) {
                state.loginLastPage = (response.meta && response.meta.last_page) || 1;
                renderLoginLogs(root, response.data || [], response.meta || {});
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function loadResourceAudit(root) {
        clearMessage(root);
        var resource = valueOf('[data-resource-name]', root);
        var id = valueOf('[data-resource-id]', root);

        if (!resource || !id) {
            showMessage(root, 'warning', 'Ingresá un recurso y un ID para consultar el historial.');
            return Promise.resolve();
        }

        var query = buildQuery({ per_page: 50, sort: 'created_at', order: 'desc' });

        return window.CCApi.request('/admin/' + encodeURIComponent(resource) + '/' + encodeURIComponent(id) + '/audit?' + query)
            .then(function (response) {
                renderResourceAudit(root, response.data || [], response.meta || {});
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function valueOf(selector, root) {
        var input = qs(selector, root);
        return input ? input.value : '';
    }

    function renderAudit(root, items, meta) {
        var body = qs('[data-audit-body]', root);
        var counter = qs('[data-audit-count]', root);
        var page = qs('[data-audit-page]', root);

        counter.textContent = ((meta && meta.total) || items.length) + ' eventos';
        page.textContent = 'Pagina ' + (meta.current_page || state.auditPage) + ' de ' + state.auditLastPage;

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay eventos para los filtros seleccionados.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            var user = item.user ? item.user.name + ' · ' + item.user.email : 'Sistema';
            return '<tr>' +
                '<td>' + escapeHtml(formatDate(item.created_at)) + '</td>' +
                '<td>' + escapeHtml(user) + '</td>' +
                '<td><strong>' + escapeHtml(item.action) + '</strong></td>' +
                '<td>' + escapeHtml(item.resource) + '<br><span class="muted">#' + escapeHtml(item.resource_id) + '</span></td>' +
                '<td>' + formatJson(item.before) + '</td>' +
                '<td>' + formatJson(item.after) + '</td>' +
                '<td>' + escapeHtml(item.ip) + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderLoginLogs(root, items, meta) {
        var body = qs('[data-login-body]', root);
        var counter = qs('[data-login-count]', root);
        var page = qs('[data-login-page]', root);

        counter.textContent = ((meta && meta.total) || items.length) + ' accesos';
        page.textContent = 'Pagina ' + (meta.current_page || state.loginPage) + ' de ' + state.loginLastPage;

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay accesos para los filtros seleccionados.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            var user = item.user ? item.user.name : '-';
            var result = item.success
                ? '<span class="chip">Exitoso</span>'
                : '<span class="chip danger">Fallido</span>';
            return '<tr>' +
                '<td>' + escapeHtml(formatDate(item.created_at)) + '</td>' +
                '<td><strong>' + escapeHtml(item.email) + '</strong></td>' +
                '<td>' + escapeHtml(user) + '</td>' +
                '<td>' + result + '</td>' +
                '<td>' + escapeHtml(item.failure_reason) + '</td>' +
                '<td>' + escapeHtml(item.ip) + '</td>' +
                '<td>' + escapeHtml(item.user_agent) + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderResourceAudit(root, items, meta) {
        var body = qs('[data-resource-body]', root);
        var counter = qs('[data-resource-count]', root);

        counter.textContent = ((meta && meta.total) || items.length) + ' eventos';

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay historial para ese recurso.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            var user = item.user ? item.user.name + ' · ' + item.user.email : 'Sistema';
            return '<tr>' +
                '<td>' + escapeHtml(formatDate(item.created_at)) + '</td>' +
                '<td>' + escapeHtml(user) + '</td>' +
                '<td><strong>' + escapeHtml(item.action) + '</strong></td>' +
                '<td>' + formatJson(item.before) + '</td>' +
                '<td>' + formatJson(item.after) + '</td>' +
                '<td>' + escapeHtml(item.ip) + '</td>' +
                '</tr>';
        }).join('');
    }

    function setTab(root, tab) {
        qsa('[data-audit-tab]', root).forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-audit-tab') === tab);
        });

        qsa('[data-audit-panel]', root).forEach(function (panel) {
            panel.style.display = panel.getAttribute('data-audit-panel') === tab ? '' : 'none';
        });

        if (tab === 'audit') {
            loadAudit(root);
        } else if (tab === 'login') {
            loadLoginLogs(root);
        }
    }

    function debounce(fn, wait) {
        var timeout;
        return function () {
            clearTimeout(timeout);
            timeout = setTimeout(fn, wait);
        };
    }

    function bind(root) {
        qsa('[data-audit-tab]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                setTab(root, button.getAttribute('data-audit-tab'));
            });
        });

        qs('[data-audit-refresh]', root).addEventListener('click', function () {
            state.auditPage = 1;
            loadAudit(root);
        });
        qs('[data-login-refresh]', root).addEventListener('click', function () {
            state.loginPage = 1;
            loadLoginLogs(root);
        });
        qs('[data-resource-refresh]', root).addEventListener('click', function () {
            loadResourceAudit(root);
        });

        qsa('[data-audit-search], [data-audit-resource], [data-audit-user], [data-audit-from], [data-audit-to]', root).forEach(function (input) {
            input.addEventListener('input', debounce(function () {
                state.auditPage = 1;
                loadAudit(root);
            }, 350));
        });
        qsa('[data-login-search], [data-login-success], [data-login-from], [data-login-to]', root).forEach(function (input) {
            input.addEventListener('input', debounce(function () {
                state.loginPage = 1;
                loadLoginLogs(root);
            }, 350));
            input.addEventListener('change', function () {
                state.loginPage = 1;
                loadLoginLogs(root);
            });
        });

        qs('[data-audit-prev]', root).addEventListener('click', function () {
            if (state.auditPage > 1) {
                state.auditPage -= 1;
                loadAudit(root);
            }
        });
        qs('[data-audit-next]', root).addEventListener('click', function () {
            if (state.auditPage < state.auditLastPage) {
                state.auditPage += 1;
                loadAudit(root);
            }
        });
        qs('[data-login-prev]', root).addEventListener('click', function () {
            if (state.loginPage > 1) {
                state.loginPage -= 1;
                loadLoginLogs(root);
            }
        });
        qs('[data-login-next]', root).addEventListener('click', function () {
            if (state.loginPage < state.loginLastPage) {
                state.loginPage += 1;
                loadLoginLogs(root);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-audit]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        loadAudit(root);
    });
})(window, document);
