(function (window, document) {
    'use strict';

    var state = {
        sources: [],
        chains: [],
        branches: [],
        cities: [],
        jobsPage: 1,
        jobsLastPage: 1,
        selectedJobId: null,
        pollTimer: null,
    };

    var finalStatuses = ['completed', 'failed', 'cancelled'];
    var runningStatuses = ['pending', 'running', 'cancel_requested'];

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function escapeHtml(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }
        return String(value)
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
        var el = qs('[data-scraping-message]', root);
        if (!el) {
            return;
        }
        el.textContent = message;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-scraping-message]', root);
        if (!el) {
            return;
        }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function errorMessage(error, fallback) {
        if (error && error.payload && error.payload.error && error.payload.error.message) {
            return error.payload.error.message;
        }
        if (error && error.status === 401) {
            return 'Sesion vencida. Inicia sesion nuevamente.';
        }
        if (error && error.status === 403) {
            return 'No tenes permiso para administrar scraping.';
        }
        if (error && error.status === 404) {
            return 'Fuente o job inexistente.';
        }
        if (error && error.status === 409) {
            return 'Transicion invalida o job ya activo.';
        }
        if (error && error.status === 422) {
            return 'Configuracion invalida.';
        }
        return fallback || 'No se pudo completar la operacion.';
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function dateLabel(value) {
        if (!value) {
            return '-';
        }
        var date = new Date(value);
        if (isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString('es-AR');
    }

    function statusChip(status) {
        var color = '#697681';
        var bg = '#eef2f5';
        if (status === 'completed') {
            color = '#04ac85';
            bg = '#e7f7f2';
        } else if (status === 'failed') {
            color = '#b33a3a';
            bg = '#fdecea';
        } else if (status === 'pending' || status === 'running') {
            color = '#2f80ed';
            bg = '#e8f0fe';
        } else if (status === 'cancelled' || status === 'cancel_requested') {
            color = '#8a6d3b';
            bg = '#fff7df';
        }
        return '<span style="background:' + bg + ';color:' + color + ';padding:2px 8px;border-radius:50px;font-size:12px">' + escapeHtml(status) + '</span>';
    }

    function branchName(branch) {
        if (!branch) {
            return '-';
        }
        var chain = branch.chain && branch.chain.name ? branch.chain.name + ' - ' : '';
        return chain + branch.name;
    }

    function filteredBranches(chainId) {
        return state.branches.filter(function (branch) {
            var branchChainId = branch.supermarket_chain_id || (branch.chain && branch.chain.id);
            return !chainId || String(branchChainId) === String(chainId);
        });
    }

    function renderSelects(root) {
        var sourceOptions = state.sources.map(function (source) {
            return option(source.name + ' (' + source.code + ')', source.id);
        }).join('');
        [
            '[data-scraping-job-source]',
            '[data-scraping-job-filter-source]',
        ].forEach(function (selector) {
            var select = qs(selector, root);
            if (!select) {
                return;
            }
            var first = selector.indexOf('filter') !== -1 ? 'Todas las fuentes' : 'Fuente';
            var current = select.value;
            select.innerHTML = '<option value="">' + first + '</option>' + sourceOptions;
            select.value = current;
        });

        qs('[data-scraping-source-city]', root).innerHTML = '<option value="">Ciudad opcional</option>' + state.cities.map(function (city) {
            return option(city.name + (city.province ? ' (' + city.province + ')' : ''), city.id);
        }).join('');

        qs('[data-scraping-job-chain]', root).innerHTML = '<option value="">Cadena opcional</option>' + state.chains.map(function (chain) {
            return option(chain.name, chain.id);
        }).join('');

        renderBranchSelect(root);
    }

    function renderBranchSelect(root) {
        var chainId = qs('[data-scraping-job-chain]', root).value;
        qs('[data-scraping-job-branch]', root).innerHTML = '<option value="">Sucursal opcional</option>' + filteredBranches(chainId).map(function (branch) {
            return option(branchName(branch), branch.id);
        }).join('');
    }

    function loadLookups(root) {
        return Promise.all([
            window.CCApi.request(endpoint('/admin/scraping/sources?per_page=100')),
            window.CCApi.request(endpoint('/supermarkets?per_page=100&sort=name&order=asc')),
            window.CCApi.request(endpoint('/admin/supermarket-branches?per_page=100&status=active')),
            window.CCApi.request(endpoint('/cities?per_page=100')),
        ]).then(function (responses) {
            state.sources = responses[0].data || [];
            state.chains = responses[1].data || [];
            state.branches = responses[2].data || [];
            state.cities = responses[3].data || [];
            renderSelects(root);
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error, 'No se pudieron cargar selectores.'));
        });
    }

    function loadSources(root) {
        clearMessage(root);
        var params = new URLSearchParams();
        params.set('per_page', 100);
        var search = (qs('[data-scraping-source-search]', root).value || '').trim();
        var status = qs('[data-scraping-source-status]', root).value;
        if (search) {
            params.set('search', search);
        }
        if (status) {
            params.set('status', status);
        }

        qs('[data-scraping-sources-body]', root).innerHTML = '<tr><td colspan="5" class="muted">Cargando fuentes...</td></tr>';

        return window.CCApi.request(endpoint('/admin/scraping/sources?' + params.toString()))
            .then(function (response) {
                state.sources = response.data || [];
                renderSelects(root);
                renderSources(root);
                qs('[data-scraping-source-count]', root).textContent = (response.meta ? response.meta.total : state.sources.length) + ' fuentes';
            }).catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudieron cargar las fuentes.'));
                qs('[data-scraping-sources-body]', root).innerHTML = '<tr><td colspan="5" class="muted">Error al cargar.</td></tr>';
            });
    }

    function renderSources(root) {
        var body = qs('[data-scraping-sources-body]', root);
        if (!state.sources.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay fuentes.</td></tr>';
            return;
        }
        body.innerHTML = state.sources.map(function (source) {
            var run = source.is_active && source.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-scraping-run-source="' + source.id + '">Ejecutar</button>'
                : '<span class="muted">Inactiva</span>';
            var url = source.base_url
                ? '<a href="' + escapeHtml(source.base_url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(source.base_url) + '</a>'
                : '-';
            return '<tr>' +
                '<td><strong>' + escapeHtml(source.name) + '</strong><br><span class="muted">' + escapeHtml(source.code) + '</span></td>' +
                '<td>' + escapeHtml(source.type) + '</td>' +
                '<td style="max-width:260px;overflow:hidden;text-overflow:ellipsis">' + url + '</td>' +
                '<td>' + (source.is_active ? 'Si' : 'No') + '<br>' + statusChip(source.status) + '</td>' +
                '<td>' + run + '</td>' +
                '</tr>';
        }).join('');
    }

    function createSource(root) {
        clearMessage(root);
        var form = qs('[data-scraping-source-form]', root);
        var button = qs('[data-scraping-source-submit]', root);
        var body = {
            code: (form.elements.code.value || '').trim(),
            name: (form.elements.name.value || '').trim(),
            type: form.elements.type.value,
            base_url: (form.elements.base_url.value || '').trim(),
            is_active: form.elements.is_active.checked,
        };
        if (form.elements.city_id.value) {
            body.city_id = parseInt(form.elements.city_id.value, 10);
        }
        button.disabled = true;
        button.textContent = 'Creando...';
        window.CCApi.request(endpoint('/admin/scraping/sources'), { method: 'POST', body: body })
            .then(function () {
                form.reset();
                form.elements.is_active.checked = true;
                showMessage(root, 'success', 'Fuente creada.');
                return loadSources(root);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo crear la fuente.'));
            })
            .then(function () {
                button.disabled = false;
                button.textContent = 'Crear fuente';
            });
    }

    function createJob(root, sourceId) {
        clearMessage(root);
        var form = qs('[data-scraping-job-form]', root);
        var button = qs('[data-scraping-job-submit]', root);
        var body = {
            source_id: parseInt(sourceId || form.elements.source_id.value, 10),
        };
        if (form.elements.supermarket_chain_id.value) {
            body.supermarket_chain_id = parseInt(form.elements.supermarket_chain_id.value, 10);
        }
        if (form.elements.supermarket_branch_id.value) {
            body.supermarket_branch_id = parseInt(form.elements.supermarket_branch_id.value, 10);
        }
        if (form.elements.max_pages.value) {
            body.max_pages = parseInt(form.elements.max_pages.value, 10);
        }
        if (form.elements.max_products && form.elements.max_products.value) {
            body.max_products = parseInt(form.elements.max_products.value, 10);
        }
        if (form.elements.delay_ms && form.elements.delay_ms.value !== '') {
            body.delay_ms = parseInt(form.elements.delay_ms.value, 10);
        }
        if (form.elements.dry_run && form.elements.dry_run.checked) {
            body.dry_run = true;
        }
        button.disabled = true;
        button.textContent = 'Ejecutando...';
        window.CCApi.request(endpoint('/admin/scraping/jobs'), { method: 'POST', body: body })
            .then(function (response) {
                showMessage(root, 'success', 'Job creado.');
                return loadJobs(root, 1).then(function () {
                    loadJobDetail(root, (response.data || response).id);
                });
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo crear el job.'));
            })
            .then(function () {
                button.disabled = false;
                button.textContent = 'Ejecutar';
            });
    }

    function loadJobs(root, page) {
        state.jobsPage = page || 1;
        var params = new URLSearchParams();
        params.set('page', state.jobsPage);
        params.set('per_page', 20);
        var sourceId = qs('[data-scraping-job-filter-source]', root).value;
        var status = qs('[data-scraping-job-filter-status]', root).value;
        if (sourceId) {
            params.set('source_id', sourceId);
        }
        if (status) {
            params.set('status', status);
        }
        qs('[data-scraping-jobs-body]', root).innerHTML = '<tr><td colspan="6" class="muted">Cargando jobs...</td></tr>';
        return window.CCApi.request(endpoint('/admin/scraping/jobs?' + params.toString()))
            .then(function (response) {
                var rows = response.data || [];
                state.jobsLastPage = response.meta ? response.meta.last_page : 1;
                state.jobsPage = response.meta ? response.meta.current_page : state.jobsPage;
                renderJobs(root, rows);
                qs('[data-scraping-job-count]', root).textContent = (response.meta ? response.meta.total : rows.length) + ' jobs';
                qs('[data-scraping-jobs-page]', root).textContent = 'Pagina ' + state.jobsPage + ' de ' + state.jobsLastPage;
            }).catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudieron cargar jobs.'));
                qs('[data-scraping-jobs-body]', root).innerHTML = '<tr><td colspan="6" class="muted">Error al cargar.</td></tr>';
            });
    }

    function renderJobs(root, rows) {
        var body = qs('[data-scraping-jobs-body]', root);
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay jobs.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (job) {
            return '<tr>' +
                '<td><strong>#' + job.id + '</strong><br><span class="muted">' + escapeHtml(job.job_type) + '</span></td>' +
                '<td>' + escapeHtml(job.source ? job.source.name : job.source_id) + '</td>' +
                '<td>' + statusChip(job.status) + '</td>' +
                '<td>Encontrados: ' + escapeHtml(job.total_found) + '<br>Pendientes: ' + escapeHtml(job.total_pending_review) + '</td>' +
                '<td><span class="muted">Inicio</span> ' + escapeHtml(dateLabel(job.started_at)) + '<br><span class="muted">Fin</span> ' + escapeHtml(dateLabel(job.finished_at)) + '</td>' +
                '<td style="white-space:nowrap">' + jobActions(job) + '</td>' +
                '</tr>';
        }).join('');
    }

    function jobActions(job) {
        var actions = '<button type="button" class="btn-ghost btn-sm" data-scraping-job-detail-btn="' + job.id + '">Detalle</button> ';
        if (job.status === 'failed' || job.status === 'cancelled') {
            actions += '<button type="button" class="btn-ghost btn-sm" data-scraping-job-retry="' + job.id + '">Retry</button> ';
        }
        if (job.status === 'pending' || job.status === 'running') {
            actions += '<button type="button" class="btn-ghost btn-sm" style="color:var(--danger)" data-scraping-job-cancel="' + job.id + '">Cancel</button>';
        }
        return actions;
    }

    function loadJobDetail(root, id, silent) {
        state.selectedJobId = parseInt(id, 10);
        if (!silent) {
            qs('[data-scraping-job-detail]', root).innerHTML = '<p class="muted">Cargando detalle...</p>';
        }
        return window.CCApi.request(endpoint('/admin/scraping/jobs/' + encodeURIComponent(id)))
            .then(function (response) {
                var job = response.data || response;
                renderJobDetail(root, job);
                loadLogs(root, id);
                managePolling(root, job);
            })
            .catch(function (error) {
                stopPolling();
                showMessage(root, 'danger', errorMessage(error, 'No se pudo cargar el job.'));
                qs('[data-scraping-job-detail]', root).innerHTML = '<p class="muted">Error al cargar detalle.</p>';
            });
    }

    function renderJobDetail(root, job) {
        var params = job.parameters ? JSON.stringify(job.parameters, null, 2) : '{}';
        var error = job.error_message ? '<div class="alert alert-danger" style="display:block">' + escapeHtml(job.error_message) + '</div>' : '';
        var dryRun = job.parameters && job.parameters.dry_run
            ? '<div class="alert alert-warning" style="display:block">DRY RUN: esta corrida no persiste candidatos, productos ni precios.</div>'
            : '';
        qs('[data-scraping-job-detail]', root).innerHTML =
            dryRun + error +
            '<div class="table-line"><span class="muted">Job</span><strong>#' + escapeHtml(job.id) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Fuente</span><strong>' + escapeHtml(job.source ? job.source.name : job.source_id) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Estado</span><strong>' + statusChip(job.status) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Encontrados</span><strong>' + escapeHtml(job.total_found) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Creados</span><strong>' + escapeHtml(job.total_created) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Actualizados</span><strong>' + escapeHtml(job.total_updated) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Pendientes revision</span><strong>' + escapeHtml(job.total_pending_review) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Inicio</span><strong>' + escapeHtml(dateLabel(job.started_at)) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Fin</span><strong>' + escapeHtml(dateLabel(job.finished_at)) + '</strong></div>' +
            '<pre class="audit-json" style="max-width:none;margin-top:10px">' + escapeHtml(params) + '</pre>';
    }

    function loadLogs(root, id) {
        var level = qs('[data-scraping-log-level]', root).value;
        var params = new URLSearchParams();
        params.set('per_page', 100);
        if (level) {
            params.set('level', level);
        }
        qs('[data-scraping-job-logs]', root).innerHTML = '<p class="muted">Cargando logs...</p>';
        return window.CCApi.request(endpoint('/admin/scraping/jobs/' + encodeURIComponent(id) + '/logs?' + params.toString()))
            .then(function (response) {
                var logs = response.data || [];
                if (!logs.length) {
                    qs('[data-scraping-job-logs]', root).innerHTML = '<p class="muted">Logs vacios.</p>';
                    return;
                }
                qs('[data-scraping-job-logs]', root).innerHTML = logs.map(function (log) {
                    var context = log.context ? '<pre class="audit-json" style="max-width:none;margin-top:6px">' + escapeHtml(JSON.stringify(log.context, null, 2)) + '</pre>' : '';
                    return '<div style="border-bottom:1px solid #edf1f4;padding:8px 0">' +
                        '<strong>' + escapeHtml(log.level) + '</strong> <span class="muted">' + escapeHtml(dateLabel(log.created_at)) + '</span><br>' +
                        '<span>' + escapeHtml(log.message) + '</span>' + context +
                        '</div>';
                }).join('');
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudieron cargar logs.'));
                qs('[data-scraping-job-logs]', root).innerHTML = '<p class="muted">Error al cargar logs.</p>';
            });
    }

    function retryJob(root, id) {
        window.CCApi.request(endpoint('/admin/scraping/jobs/' + encodeURIComponent(id) + '/retry'), { method: 'POST' })
            .then(function (response) {
                showMessage(root, 'success', 'Job reintentado.');
                return loadJobs(root, 1).then(function () {
                    loadJobDetail(root, (response.data || response).id);
                });
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo reintentar el job.'));
            });
    }

    function cancelJob(root, id) {
        if (!window.confirm('Cancelar este job de scraping?')) {
            return;
        }
        window.CCApi.request(endpoint('/admin/scraping/jobs/' + encodeURIComponent(id) + '/cancel'), { method: 'POST' })
            .then(function () {
                showMessage(root, 'success', 'Cancelacion enviada.');
                return loadJobs(root, state.jobsPage).then(function () {
                    loadJobDetail(root, id);
                });
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo cancelar el job.'));
            });
    }

    function managePolling(root, job) {
        stopPolling();
        if (runningStatuses.indexOf(job.status) === -1 || finalStatuses.indexOf(job.status) !== -1) {
            return;
        }
        state.pollTimer = window.setInterval(function () {
            if (document.hidden || !state.selectedJobId) {
                stopPolling();
                return;
            }
            loadJobDetail(root, state.selectedJobId, true);
            loadJobs(root, state.jobsPage);
        }, 8000);
    }

    function stopPolling() {
        if (state.pollTimer) {
            window.clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function bind(root) {
        qs('[data-scraping-source-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            createSource(root);
        });
        qs('[data-scraping-job-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            createJob(root);
        });
        qs('[data-scraping-job-chain]', root).addEventListener('change', function () {
            renderBranchSelect(root);
        });
        qs('[data-scraping-source-refresh]', root).addEventListener('click', function () {
            loadSources(root);
        });
        qs('[data-scraping-job-refresh]', root).addEventListener('click', function () {
            loadJobs(root, 1);
        });
        qs('[data-scraping-jobs-prev]', root).addEventListener('click', function () {
            if (state.jobsPage > 1) {
                loadJobs(root, state.jobsPage - 1);
            }
        });
        qs('[data-scraping-jobs-next]', root).addEventListener('click', function () {
            if (state.jobsPage < state.jobsLastPage) {
                loadJobs(root, state.jobsPage + 1);
            }
        });
        ['[data-scraping-job-filter-source]', '[data-scraping-job-filter-status]'].forEach(function (selector) {
            qs(selector, root).addEventListener('change', function () {
                loadJobs(root, 1);
            });
        });
        qs('[data-scraping-logs-refresh]', root).addEventListener('click', function () {
            if (state.selectedJobId) {
                loadLogs(root, state.selectedJobId);
            }
        });
        qs('[data-scraping-log-level]', root).addEventListener('change', function () {
            if (state.selectedJobId) {
                loadLogs(root, state.selectedJobId);
            }
        });
        root.addEventListener('click', function (event) {
            var runSource = event.target.closest('[data-scraping-run-source]');
            var detail = event.target.closest('[data-scraping-job-detail-btn]');
            var retry = event.target.closest('[data-scraping-job-retry]');
            var cancel = event.target.closest('[data-scraping-job-cancel]');
            if (runSource) {
                createJob(root, runSource.getAttribute('data-scraping-run-source'));
            } else if (detail) {
                loadJobDetail(root, detail.getAttribute('data-scraping-job-detail-btn'));
            } else if (retry) {
                retryJob(root, retry.getAttribute('data-scraping-job-retry'));
            } else if (cancel) {
                cancelJob(root, cancel.getAttribute('data-scraping-job-cancel'));
            }
        });
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
            } else if (state.selectedJobId) {
                loadJobDetail(root, state.selectedJobId, true);
            }
        });
        window.addEventListener('beforeunload', stopPolling);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-supermarket-scraping]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
        loadLookups(root).then(function () {
            loadSources(root);
            loadJobs(root, 1);
        });
    });
})(window, document);
