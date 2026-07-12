(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        alerts: [],
        sources: [],
        selected: null,
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
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

    function endpoint(path) {
        return '/api/v1' + path;
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-scraping-alerts-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-scraping-alerts-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = '';
        alert.className = 'alert';
        alert.style.display = 'none';
    }

    function errorMessage(error) {
        var payload = error && error.payload ? error.payload : {};
        var apiError = payload.error || {};
        if (apiError.message) {
            return apiError.message;
        }
        if (error && error.status === 401) {
            return 'Sesion vencida. Inicia sesion nuevamente.';
        }
        if (error && error.status === 403) {
            return 'Solo super admin puede gestionar alertas de scraping.';
        }
        if (error && error.status === 404) {
            return 'La alerta solicitada no existe.';
        }
        if (error && error.status === 409) {
            return 'La alerta ya fue resuelta o no admite esta accion.';
        }
        if (error && error.status === 422) {
            return 'Revisa los filtros o notas de resolucion.';
        }
        return (error && error.message) || 'No se pudo completar la operacion.';
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function statusChip(status) {
        var danger = status === 'open' ? ' danger' : '';
        return '<span class="chip' + danger + '">' + escapeHtml(status) + '</span>';
    }

    function dateLabel(value) {
        if (!value) {
            return '-';
        }
        return String(value).replace('T', ' ').replace('.000000Z', '').replace('Z', '');
    }

    function sourceName(alert) {
        return alert.source ? alert.source.name : alert.source_id;
    }

    function renderSources(root) {
        var select = qs('[data-alerts-source]', root);
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '<option value="">Todas las fuentes</option>' + state.sources.map(function (source) {
            return option(source.name || source.code || ('Fuente #' + source.id), source.id);
        }).join('');
        select.value = current;
    }

    function loadSources(root) {
        return window.CCApi.request(endpoint('/admin/scraping/sources?per_page=100'))
            .then(function (response) {
                state.sources = response.data || [];
                renderSources(root);
            }).catch(function (error) {
                showMessage(root, 'danger', errorMessage(error));
            });
    }

    function alertParams(root) {
        var params = new URLSearchParams();
        var values = [
            ['status', qs('[data-alerts-status]', root).value],
            ['severity', qs('[data-alerts-severity]', root).value],
            ['alert_type', qs('[data-alerts-type]', root).value],
            ['source_id', qs('[data-alerts-source]', root).value],
            ['scraping_job_id', qs('[data-alerts-job]', root).value],
            ['date_from', qs('[data-alerts-from]', root).value],
            ['date_to', qs('[data-alerts-to]', root).value],
        ];
        values.forEach(function (entry) {
            if (entry[1]) {
                params.set(entry[0], entry[1]);
            }
        });
        return params;
    }

    function loadReport(root) {
        var params = alertParams(root);
        return window.CCApi.request(endpoint('/admin/reports/scraping-errors?' + params.toString()))
            .then(function (response) {
                renderReport(root, response.data || {});
            }).catch(function (error) {
                showMessage(root, 'danger', errorMessage(error));
                qs('[data-scraping-alerts-summary]', root).innerHTML = '<p class="muted">No se pudo cargar el reporte.</p>';
            });
    }

    function renderLine(label, value) {
        return '<div class="line"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
    }

    function renderPairs(items, keyName, empty) {
        if (!items || !items.length) {
            return '<p class="muted">' + escapeHtml(empty) + '</p>';
        }
        return items.map(function (item) {
            var label = item[keyName] || item.source_name || item.date || 'Sin dato';
            return renderLine(label, item.count);
        }).join('');
    }

    function renderReport(root, report) {
        var status = report.by_status || {};
        qs('[data-scraping-alerts-summary]', root).innerHTML =
            renderLine('Total alertas', report.total_alerts || 0) +
            renderLine('Abiertas', status.open || 0) +
            renderLine('Resueltas', status.resolved || 0) +
            renderLine('Jobs fallidos', report.failed_jobs || 0);
        qs('[data-scraping-alerts-severity]', root).innerHTML = renderPairs(report.by_severity, 'severity', 'Sin alertas por severidad.');
        qs('[data-scraping-alerts-source-report]', root).innerHTML = renderPairs(report.by_source, 'source_name', 'Sin alertas por fuente.');
        qs('[data-scraping-alerts-evolution]', root).innerHTML = renderPairs(report.evolution || report.by_date, 'date', 'Sin evolucion disponible.');
    }

    function loadAlerts(root, page) {
        var params = alertParams(root);
        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '20');

        qs('[data-alerts-body]', root).innerHTML = '<tr><td colspan="7" class="muted">Cargando alertas...</td></tr>';
        return window.CCApi.request(endpoint('/admin/scraping/alerts?' + params.toString()))
            .then(function (response) {
                state.alerts = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderAlerts(root, response.meta || {});
            }).catch(function (error) {
                qs('[data-alerts-body]', root).innerHTML = '<tr><td colspan="7" class="muted">Error al cargar.</td></tr>';
                showMessage(root, 'danger', errorMessage(error));
            });
    }

    function renderAlerts(root, meta) {
        var body = qs('[data-alerts-body]', root);
        qs('[data-alerts-count]', root).textContent = (meta.total || state.alerts.length) + ' alertas';
        qs('[data-alerts-page]', root).textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);

        if (!state.alerts.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay alertas para los filtros seleccionados.</td></tr>';
            return;
        }

        body.innerHTML = state.alerts.map(function (alert) {
            var canResolve = alert.status === 'open';
            var resolver = alert.resolver ? (alert.resolver.name + ' / ' + alert.resolver.email) : '-';
            return '<tr>' +
                '<td><strong>' + escapeHtml(alert.alert_type) + '</strong><br><span class="muted">' + escapeHtml(alert.message) + '</span><br><span class="muted">' + escapeHtml(dateLabel(alert.created_at)) + '</span></td>' +
                '<td>' + escapeHtml(sourceName(alert)) + '</td>' +
                '<td>#' + escapeHtml(alert.scraping_job_id) + '<br><span class="muted">' + escapeHtml(alert.job ? alert.job.status : '-') + '</span></td>' +
                '<td>' + escapeHtml(alert.severity) + '</td>' +
                '<td>' + statusChip(alert.status) + '</td>' +
                '<td><span class="muted">' + escapeHtml(resolver) + '</span><br><span class="muted">' + escapeHtml(dateLabel(alert.resolved_at)) + '</span></td>' +
                '<td><button type="button" class="btn-ghost btn-sm" data-alert-view="' + alert.id + '">Detalle</button> ' +
                    (canResolve ? '<button type="button" class="btn-main btn-sm" data-alert-resolve="' + alert.id + '">Resolver</button>' : '') +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function selectAlert(root, id, openForm) {
        var alert = state.alerts.filter(function (item) {
            return String(item.id) === String(id);
        })[0];
        state.selected = alert || null;
        if (!alert) {
            qs('[data-alert-detail]', root).innerHTML = '<p class="muted">Alerta no encontrada en la pagina actual.</p>';
            qs('[data-alert-resolve-form]', root).style.display = 'none';
            return;
        }
        qs('[data-alert-detail]', root).innerHTML =
            renderLine('ID', '#' + alert.id) +
            renderLine('Tipo', alert.alert_type) +
            renderLine('Severidad', alert.severity) +
            renderLine('Estado', alert.status) +
            renderLine('Fuente', sourceName(alert)) +
            renderLine('Job', alert.scraping_job_id || '-') +
            '<div style="margin-top:10px"><strong>Mensaje</strong><p class="muted">' + escapeHtml(alert.message) + '</p></div>';
        qs('[data-alert-resolve-form]', root).style.display = alert.status === 'open' ? 'block' : 'none';
        if (openForm && alert.status !== 'open') {
            showMessage(root, 'danger', 'La alerta seleccionada ya no esta abierta.');
        }
    }

    function resolveSelected(root) {
        if (!state.selected || !state.selected.id) {
            showMessage(root, 'danger', 'Selecciona una alerta abierta.');
            return;
        }
        if (state.selected.status !== 'open') {
            showMessage(root, 'danger', 'La alerta seleccionada ya esta resuelta.');
            return;
        }
        clearMessage(root);
        var form = qs('[data-alert-resolve-form]', root);
        var notes = form.elements.resolution_notes.value.trim();
        var body = {};
        if (notes) {
            body.resolution_notes = notes;
        }
        window.CCApi.request(endpoint('/admin/scraping/alerts/' + encodeURIComponent(state.selected.id) + '/resolve'), {
            method: 'PATCH',
            body: body,
        }).then(function (response) {
            showMessage(root, 'success', 'Alerta resuelta.');
            state.selected = response.data;
            form.reset();
            loadAlerts(root, state.page);
            loadReport(root);
            qs('[data-alert-resolve-form]', root).style.display = 'none';
            qs('[data-alert-detail]', root).innerHTML = '<p class="muted">Alerta resuelta.</p>';
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error));
        });
    }

    function reloadAll(root, page) {
        clearMessage(root);
        return Promise.all([
            loadAlerts(root, page || 1),
            loadReport(root),
        ]);
    }

    function bind(root) {
        ['[data-alerts-status]', '[data-alerts-severity]', '[data-alerts-type]', '[data-alerts-source]', '[data-alerts-from]', '[data-alerts-to]'].forEach(function (selector) {
            qs(selector, root).addEventListener('change', function () {
                reloadAll(root, 1);
            });
        });
        qs('[data-alerts-job]', root).addEventListener('input', function () {
            reloadAll(root, 1);
        });
        qs('[data-alerts-refresh]', root).addEventListener('click', function () {
            reloadAll(root, 1);
        });
        qs('[data-alerts-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                reloadAll(root, state.page - 1);
            }
        });
        qs('[data-alerts-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                reloadAll(root, state.page + 1);
            }
        });
        qs('[data-alerts-body]', root).addEventListener('click', function (event) {
            var view = event.target.closest('[data-alert-view]');
            var resolve = event.target.closest('[data-alert-resolve]');
            if (view) {
                selectAlert(root, view.getAttribute('data-alert-view'), false);
            }
            if (resolve) {
                selectAlert(root, resolve.getAttribute('data-alert-resolve'), true);
            }
        });
        qs('[data-alert-resolve-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            resolveSelected(root);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-scraping-alerts]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
        loadSources(root).then(function () {
            reloadAll(root, 1);
        });
    });
})(window, document);
