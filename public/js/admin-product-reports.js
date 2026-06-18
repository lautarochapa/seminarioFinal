(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        total: 0,
        reports: [],
    };

    var TYPE_LABELS = {
        incorrect_price:        'Precio incorrecto',
        incorrect_product_data: 'Datos incorrectos',
        incorrect_nutrition:    'Nutrición incorrecta',
        duplicate_product:      'Producto duplicado',
        other:                  'Otro',
    };

    var STATUS_LABELS = {
        open:     'Pendiente',
        resolved: 'Resuelto',
        rejected: 'Rechazado',
    };

    function qs(selector, root) { return (root || document).querySelector(selector); }

    function text(v) { return (v === null || v === undefined || v === '') ? '-' : String(v); }

    function escapeHtml(v) {
        return text(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function buildParams(obj) {
        var p = new URLSearchParams();
        Object.keys(obj).forEach(function (k) {
            if (obj[k] !== '' && obj[k] !== null && obj[k] !== undefined) {
                p.set(k, obj[k]);
            }
        });
        return p.toString();
    }

    function showMessage(root, type, msg) {
        var el = qs('[data-reports-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-reports-message]', root);
        if (!el) { return; }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function apiError(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message)
            || (err && err.message)
            || 'Error de conexión.';
    }

    function statusChip(status) {
        var label = STATUS_LABELS[status] || status;
        var color = status === 'open' ? '#b36a00' : status === 'resolved' ? '#0a6b46' : '#b33a3a';
        var bg    = status === 'open' ? '#fff3e0' : status === 'resolved' ? '#e7f7f2' : '#f7e7e7';
        return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:900;background:' +
            bg + ';color:' + color + '">' + escapeHtml(label) + '</span>';
    }

    function formatDate(iso) {
        if (!iso) { return '-'; }
        try {
            var d = new Date(iso);
            return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
                ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
        } catch (e) { return text(iso); }
    }

    // ── FETCH ─────────────────────────────────────────────────────────────────

    function fetchReports(root, page) {
        clearMessage(root);
        state.page = page || 1;

        var statusSel = qs('[data-reports-filter-status]', root);
        var typeSel   = qs('[data-reports-filter-type]', root);
        var fromEl    = qs('[data-reports-filter-from]', root);
        var toEl      = qs('[data-reports-filter-to]', root);

        var params = { per_page: 20, page: state.page };
        if (statusSel && statusSel.value) { params.status = statusSel.value; }
        if (typeSel && typeSel.value)     { params.type = typeSel.value; }
        if (fromEl && fromEl.value)       { params.created_from = fromEl.value; }
        if (toEl && toEl.value)           { params.created_to = toEl.value; }

        var tbody = qs('[data-reports-body]', root);
        if (tbody) { tbody.innerHTML = '<tr><td colspan="6" class="muted">Cargando...</td></tr>'; }

        window.CCApi.request(endpoint('/admin/product-reports?' + buildParams(params)))
            .then(function (r) {
                state.total    = (r.meta && r.meta.total)     || 0;
                state.lastPage = (r.meta && r.meta.last_page) || 1;
                state.reports  = r.data || [];
                renderTable(root, state.reports);
                updatePagination(root);
                var countEl = qs('[data-reports-count]', root);
                if (countEl) { countEl.textContent = state.total + ' reportes'; }
            })
            .catch(function (err) {
                var status = err.status || 0;
                if (status === 401) { showMessage(root, 'danger', 'Sesión vencida. Volvé a iniciar sesión.'); }
                else if (status === 403) { showMessage(root, 'danger', 'Sin permiso para ver reportes.'); }
                else { showMessage(root, 'danger', apiError(err)); }
                if (tbody) { tbody.innerHTML = '<tr><td colspan="6" class="muted">Error al cargar reportes.</td></tr>'; }
            });
    }

    function renderTable(root, reports) {
        var tbody = qs('[data-reports-body]', root);
        if (!tbody) { return; }

        if (!reports.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="muted">Sin reportes para los filtros aplicados.</td></tr>';
            return;
        }

        tbody.innerHTML = reports.map(function (r) {
            var productName = (r.product && r.product.name) || '#' + r.id;
            var userName    = (r.user && r.user.name) || '-';
            var typeLabel   = TYPE_LABELS[r.report_type] || r.report_type;
            return '<tr>' +
                '<td>' + escapeHtml(productName) + '</td>' +
                '<td>' + escapeHtml(typeLabel) + '</td>' +
                '<td>' + statusChip(r.status) + '</td>' +
                '<td>' + escapeHtml(userName) + '</td>' +
                '<td style="white-space:nowrap;font-size:12px">' + escapeHtml(formatDate(r.created_at)) + '</td>' +
                '<td><button type="button" class="btn-ghost btn-sm" data-reports-view="' + r.id + '">Ver</button></td>' +
            '</tr>';
        }).join('');
    }

    function updatePagination(root) {
        var prev  = qs('[data-reports-prev]', root);
        var next  = qs('[data-reports-next]', root);
        var label = qs('[data-reports-page]', root);
        if (prev)  { prev.disabled  = state.page <= 1; }
        if (next)  { next.disabled  = state.page >= state.lastPage; }
        if (label) { label.textContent = 'Pagina ' + state.page + ' / ' + state.lastPage; }
    }

    // ── DETALLE ───────────────────────────────────────────────────────────────

    function showDetail(root, reportId) {
        var detail = qs('[data-reports-detail]', root);
        if (!detail) { return; }

        var report = state.reports.filter(function (x) { return x.id === reportId; })[0];
        if (!report) {
            detail.innerHTML = '<p class="muted">Reporte no encontrado. Actualizá el listado.</p>';
            return;
        }
        renderDetail(root, detail, report);
    }

    function renderDetail(root, detail, report) {
        var productName  = (report.product  && report.product.name)  || '-';
        var productId    = (report.product  && report.product.id)     || null;
        var userName     = (report.user     && report.user.name)      || '-';
        var userEmail    = (report.user     && report.user.email)     || '-';
        var resolverName = (report.resolver && report.resolver.name)  || '-';
        var typeLabel    = TYPE_LABELS[report.report_type]             || report.report_type;

        var resolveHtml = '';
        if (report.status === 'open') {
            resolveHtml =
                '<div style="margin-top:14px;border-top:1px solid #eee;padding-top:14px">' +
                '<h3 style="font-size:13px;font-weight:900;margin:0 0 10px">Resolver reporte</h3>' +
                '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
                '<button type="button" class="btn-main btn-sm" data-reports-resolve="' + report.id + '" data-reports-resolve-status="resolved">Marcar resuelto</button>' +
                '<button type="button" class="btn-ghost btn-sm" data-reports-resolve="' + report.id + '" data-reports-resolve-status="rejected">Rechazar</button>' +
                '</div>' +
                '<div data-reports-resolve-message class="alert" style="display:none;margin-top:8px"></div>' +
                '</div>';
        }

        detail.innerHTML =
            '<h2 style="font-size:16px;margin:0 0 12px">' + escapeHtml(productName) + '</h2>' +
            (productId ? '<p style="margin:0 0 10px"><a href="/admin-web/products" class="muted" style="font-size:12px">Ver en catálogo</a></p>' : '') +
            '<div class="table-line"><span class="muted">Tipo</span><strong>'        + escapeHtml(typeLabel) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Estado</span>'              + statusChip(report.status) + '</div>' +
            '<div class="table-line"><span class="muted">Descripción</span><span style="font-size:13px">' + escapeHtml(report.description || 'Sin descripción') + '</span></div>' +
            '<div class="table-line"><span class="muted">Usuario</span><strong>'     + escapeHtml(userName) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Email</span><span style="font-size:12px;font-family:monospace">' + escapeHtml(userEmail) + '</span></div>' +
            '<div class="table-line"><span class="muted">Fecha</span><strong>'       + escapeHtml(formatDate(report.created_at)) + '</strong></div>' +
            (report.resolved_at
                ? '<div class="table-line"><span class="muted">Resuelto por</span><strong>' + escapeHtml(resolverName) + '</strong></div>' +
                  '<div class="table-line"><span class="muted">Fecha resolución</span><strong>' + escapeHtml(formatDate(report.resolved_at)) + '</strong></div>'
                : '') +
            resolveHtml;
    }

    function resolveReport(root, reportId, status) {
        var msgEl = qs('[data-reports-resolve-message]', root);
        if (msgEl) { msgEl.style.display = 'none'; }

        var btns = root.querySelectorAll('[data-reports-resolve="' + reportId + '"]');
        btns.forEach(function (b) { b.disabled = true; });

        window.CCApi.request(endpoint('/admin/product-reports/' + reportId + '/resolve'), {
            method: 'PATCH',
            body: { status: status },
        })
        .then(function () {
            fetchReports(root, state.page);
            var detail = qs('[data-reports-detail]', root);
            if (detail) {
                detail.innerHTML = '<p style="color:#0a6b46;font-weight:900">Reporte ' +
                    (status === 'resolved' ? 'marcado como resuelto' : 'rechazado') +
                    ' correctamente.</p>';
            }
        })
        .catch(function (err) {
            btns.forEach(function (b) { b.disabled = false; });
            var code   = err.status || 0;
            var detail = qs('[data-reports-detail]', root);
            if (code === 409) {
                if (msgEl) { msgEl.textContent = 'El reporte ya fue procesado.'; msgEl.className = 'alert alert-danger'; msgEl.style.display = 'block'; }
                fetchReports(root, state.page);
            } else if (code === 404) {
                if (msgEl) { msgEl.textContent = 'Reporte no encontrado.'; msgEl.className = 'alert alert-danger'; msgEl.style.display = 'block'; }
            } else {
                if (msgEl) { msgEl.textContent = apiError(err); msgEl.className = 'alert alert-danger'; msgEl.style.display = 'block'; }
            }
        });
    }

    // ── BIND ─────────────────────────────────────────────────────────────────

    function bind(root) {
        root.addEventListener('click', function (e) {
            var t = e.target;

            if (t.dataset.hasOwnProperty('reportsRefresh')) { fetchReports(root, 1); return; }
            if (t.dataset.hasOwnProperty('reportsPrev') && !t.disabled) { fetchReports(root, state.page - 1); return; }
            if (t.dataset.hasOwnProperty('reportsNext') && !t.disabled) { fetchReports(root, state.page + 1); return; }

            if (t.dataset.reportsView) {
                showDetail(root, parseInt(t.dataset.reportsView, 10));
                return;
            }

            if (t.dataset.reportsResolve) {
                resolveReport(root, parseInt(t.dataset.reportsResolve, 10), t.dataset.reportsResolveStatus);
                return;
            }
        });

        [
            '[data-reports-filter-status]',
            '[data-reports-filter-type]',
            '[data-reports-filter-from]',
            '[data-reports-filter-to]',
        ].forEach(function (sel) {
            var el = qs(sel, root);
            if (el) { el.addEventListener('change', function () { fetchReports(root, 1); }); }
        });
    }

    // ── INIT ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-product-reports]');
        if (!root || !window.CCApi) { return; }

        bind(root);
        fetchReports(root, 1);
    });
})(window, document);
