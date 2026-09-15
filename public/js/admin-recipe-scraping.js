(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        selectedJobId: null,
    };

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

    function request(path, options) {
        if (!window.CCApi || typeof window.CCApi.request !== 'function') {
            return Promise.reject({ status: 500, payload: { error: { message: 'Cliente API no disponible.' } } });
        }
        return window.CCApi.request(endpoint(path), options || {});
    }

    function showMessage(root, type, message) {
        var el = qs('[data-recipe-scraping-message]', root);
        if (!el) {
            return;
        }
        el.textContent = message;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-recipe-scraping-message]', root);
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
            return 'No tenes permiso para scraping de recetas.';
        }
        if (error && error.status === 404) {
            return 'Job inexistente.';
        }
        if (error && error.status === 409) {
            return 'Ya existe un job activo o la accion no es valida.';
        }
        if (error && error.status === 422) {
            return 'La configuracion del job no es valida.';
        }
        return fallback || 'No se pudo completar la operacion.';
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
        } else if (status === 'cancelled') {
            color = '#8a6d3b';
            bg = '#fff7df';
        }
        return '<span style="background:' + bg + ';color:' + color + ';padding:2px 8px;border-radius:50px;font-size:12px">' + escapeHtml(status) + '</span>';
    }

    function getCollection(payload) {
        if (!payload || !payload.data) {
            return [];
        }
        return Array.isArray(payload.data) ? payload.data : [];
    }

    function params(job) {
        return job && job.parameters && typeof job.parameters === 'object' ? job.parameters : {};
    }

    function maxPages(job) {
        var parameters = params(job);
        return parameters.max_pages || parameters.pages || '-';
    }

    function searchTerm(job) {
        var parameters = params(job);
        return parameters.search_term || null;
    }

    function paramsLabel(job) {
        var label = 'Max paginas: ' + escapeHtml(maxPages(job));
        var term = searchTerm(job);
        if (term) {
            label += '<br>Buscar: ' + escapeHtml(term);
        }
        return label;
    }

    function summary(job) {
        return [
            'Encontradas: ' + escapeHtml(job.total_found || 0),
            'Creadas: ' + escapeHtml(job.total_created || 0),
            'Actualizadas: ' + escapeHtml(job.total_updated || 0),
            'Revision: ' + escapeHtml(job.total_pending_review || 0),
        ].join('<br>');
    }

    function sourceName(job) {
        if (job.source && job.source.name) {
            return job.source.name;
        }
        if (job.source && job.source.code) {
            return job.source.code;
        }
        return 'Cookpad';
    }

    function renderRows(root, jobs) {
        var body = qs('[data-recipe-scraping-jobs-body]', root);
        if (!body) {
            return;
        }
        if (!jobs.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay jobs para los filtros seleccionados.</td></tr>';
            return;
        }
        body.innerHTML = jobs.map(function (job) {
            var canRetry = job.status === 'failed' || job.status === 'cancelled';
            return '<tr>' +
                '<td>#' + escapeHtml(job.id) + '<br><span class="muted">' + escapeHtml(job.job_type) + '</span></td>' +
                '<td>' + escapeHtml(sourceName(job)) + '</td>' +
                '<td>' + statusChip(job.status) + '</td>' +
                '<td>' + paramsLabel(job) + '</td>' +
                '<td>' + summary(job) + '</td>' +
                '<td><span class="muted">Creado</span><br>' + escapeHtml(dateLabel(job.created_at)) + '<br><span class="muted">Fin</span><br>' + escapeHtml(dateLabel(job.finished_at)) + '</td>' +
                '<td><button type="button" class="btn-ghost btn-sm" data-recipe-scraping-show="' + escapeHtml(job.id) + '">Ver</button> ' +
                (canRetry ? '<button type="button" class="btn-main btn-sm" data-recipe-scraping-retry="' + escapeHtml(job.id) + '">Retry</button>' : '') +
                '</td>' +
                '</tr>';
        }).join('');
    }

    function renderMeta(root, payload) {
        var meta = payload && payload.meta ? payload.meta : {};
        state.page = Number(meta.current_page || state.page || 1);
        state.lastPage = Number(meta.last_page || 1);
        var count = qs('[data-recipe-scraping-count]', root);
        var page = qs('[data-recipe-scraping-page]', root);
        var prev = qs('[data-recipe-scraping-prev]', root);
        var next = qs('[data-recipe-scraping-next]', root);
        if (count) {
            count.textContent = (meta.total || 0) + ' jobs';
        }
        if (page) {
            page.textContent = 'Pagina ' + state.page + ' de ' + state.lastPage;
        }
        if (prev) {
            prev.disabled = state.page <= 1;
        }
        if (next) {
            next.disabled = state.page >= state.lastPage;
        }
    }

    function renderDetail(root, job) {
        var el = qs('[data-recipe-scraping-detail]', root);
        if (!el) {
            return;
        }
        if (!job) {
            el.className = 'muted';
            el.textContent = 'Selecciona un job.';
            return;
        }
        el.className = '';
        el.innerHTML = '<div class="grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">' +
            '<div class="line"><span>ID</span><strong>#' + escapeHtml(job.id) + '</strong></div>' +
            '<div class="line"><span>Fuente</span><strong>' + escapeHtml(sourceName(job)) + '</strong></div>' +
            '<div class="line"><span>Estado</span><strong>' + statusChip(job.status) + '</strong></div>' +
            '<div class="line"><span>Paginas</span><strong>' + escapeHtml(maxPages(job)) + '</strong></div>' +
            '</div>' +
            (searchTerm(job) ? '<div class="line"><span>Buscar</span><strong>' + escapeHtml(searchTerm(job)) + '</strong></div>' : '') +
            '<div style="margin-top:12px">' + summary(job) + '</div>' +
            '<div class="line"><span>Inicio</span><strong>' + escapeHtml(dateLabel(job.started_at)) + '</strong></div>' +
            '<div class="line"><span>Fin</span><strong>' + escapeHtml(dateLabel(job.finished_at)) + '</strong></div>' +
            '<div class="line"><span>Error</span><strong>' + escapeHtml(job.error_message) + '</strong></div>' +
            '<p class="muted" style="margin-top:12px">No hay endpoint especifico de logs para scraping de recetas. El detalle muestra el estado, parametros, totales y error controlado del job.</p>';
    }

    function buildQuery(root) {
        var params = new URLSearchParams();
        var status = qs('[data-recipe-scraping-status]', root);
        params.set('page', state.page);
        params.set('per_page', 20);
        if (status && status.value) {
            params.set('status', status.value);
        }
        return params.toString();
    }

    function hasActiveJob(jobs) {
        return (jobs || []).some(function (job) {
            return job.status === 'pending' || job.status === 'running';
        });
    }

    function updateSubmitAvailability(root, jobs, statusFilterValue) {
        var submit = qs('[data-recipe-scraping-submit]', root);
        var hint = qs('[data-recipe-scraping-active-hint]', root);
        if (!submit) {
            return;
        }
        if (statusFilterValue) {
            // Con un filtro de estado aplicado esta lista no representa
            // todos los jobs: no se puede confiar en ella para bloquear el
            // boton, asi que se deja habilitado (el backend igual rechaza
            // con 409 si hay un job activo).
            submit.disabled = false;
            if (hint) {
                hint.style.display = 'none';
            }
            return;
        }
        var active = hasActiveJob(jobs);
        submit.disabled = active;
        if (hint) {
            hint.style.display = active ? 'block' : 'none';
        }
    }

    function loadJobs(root) {
        var body = qs('[data-recipe-scraping-jobs-body]', root);
        if (body) {
            body.innerHTML = '<tr><td colspan="7" class="muted">Cargando jobs...</td></tr>';
        }
        var statusFilter = qs('[data-recipe-scraping-status]', root);
        return request('/admin/recipes/scraping/jobs?' + buildQuery(root))
            .then(function (payload) {
                var jobs = getCollection(payload);
                renderRows(root, jobs);
                renderMeta(root, payload);
                updateSubmitAvailability(root, jobs, statusFilter ? statusFilter.value : '');
                return payload;
            })
            .catch(function (error) {
                if (body) {
                    body.innerHTML = '<tr><td colspan="7" class="muted">' + escapeHtml(errorMessage(error)) + '</td></tr>';
                }
                showMessage(root, 'danger', errorMessage(error));
            });
    }

    function loadJob(root, id) {
        state.selectedJobId = id;
        renderDetail(root, null);
        return request('/admin/recipes/scraping/jobs/' + encodeURIComponent(id))
            .then(function (payload) {
                renderDetail(root, payload.data);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error));
            });
    }

    function createJob(root, form) {
        var submit = qs('[data-recipe-scraping-submit]', root);
        var maxPages = Number(form.max_pages.value || 1);
        var term = form.search_term ? form.search_term.value.trim() : '';
        var body = { max_pages: maxPages };
        if (term) {
            body.search_term = term;
        }
        clearMessage(root);
        if (submit) {
            submit.disabled = true;
        }
        return request('/admin/recipes/scraping/jobs', {
            method: 'POST',
            body: body,
        }).then(function (payload) {
            showMessage(root, 'success', 'Job Cookpad encolado.');
            if (payload.data && payload.data.id) {
                state.selectedJobId = payload.data.id;
                renderDetail(root, payload.data);
            }
            state.page = 1;
            return loadJobs(root);
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error));
        }).finally(function () {
            if (submit) {
                submit.disabled = false;
            }
        });
    }

    function retryJob(root, id) {
        clearMessage(root);
        return request('/admin/recipes/scraping/jobs/' + encodeURIComponent(id) + '/retry', {
            method: 'POST',
        }).then(function (payload) {
            showMessage(root, 'success', 'Retry encolado.');
            if (payload.data && payload.data.id) {
                state.selectedJobId = payload.data.id;
                renderDetail(root, payload.data);
            }
            state.page = 1;
            return loadJobs(root);
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error));
        });
    }

    function bind(root) {
        var form = qs('[data-recipe-scraping-form]', root);
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                createJob(root, form);
            });
        }
        var status = qs('[data-recipe-scraping-status]', root);
        if (status) {
            status.addEventListener('change', function () {
                state.page = 1;
                loadJobs(root);
            });
        }
        var refresh = qs('[data-recipe-scraping-refresh]', root);
        if (refresh) {
            refresh.addEventListener('click', function () {
                loadJobs(root);
            });
        }
        var prev = qs('[data-recipe-scraping-prev]', root);
        if (prev) {
            prev.addEventListener('click', function () {
                if (state.page > 1) {
                    state.page -= 1;
                    loadJobs(root);
                }
            });
        }
        var next = qs('[data-recipe-scraping-next]', root);
        if (next) {
            next.addEventListener('click', function () {
                if (state.page < state.lastPage) {
                    state.page += 1;
                    loadJobs(root);
                }
            });
        }
        root.addEventListener('click', function (event) {
            var showButton = event.target.closest('[data-recipe-scraping-show]');
            var retryButton = event.target.closest('[data-recipe-scraping-retry]');
            if (showButton) {
                loadJob(root, showButton.getAttribute('data-recipe-scraping-show'));
            }
            if (retryButton) {
                retryJob(root, retryButton.getAttribute('data-recipe-scraping-retry'));
            }
        });

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (event) {
                event.preventDefault();
                var runForm = qs('[data-recipe-scraping-form]', root);
                if (!runForm) {
                    return;
                }
                runForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var firstField = runForm.querySelector('input:not([type=hidden])');
                if (firstField) {
                    firstField.focus();
                }
            });
        }

        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (event) {
                event.preventDefault();
                highlightJobsSection(root);
            });
        }
    }

    function highlightJobsSection(root) {
        var section = qs('[data-recipe-scraping-jobs-section]', root);
        if (!section) {
            return;
        }
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        section.classList.add('highlight-focus');
        window.setTimeout(function () {
            section.classList.remove('highlight-focus');
        }, 1500);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-recipe-scraping]');
        if (!root) {
            return;
        }
        bind(root);
        loadJobs(root);
    });
})(window, document);
