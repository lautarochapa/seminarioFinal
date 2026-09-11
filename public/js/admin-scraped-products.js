(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        candidates: [],
        selected: null,
        sources: [],
        jobs: [],
        products: [],
        ingredients: [],
        brands: [],
        categories: [],
        selectedIds: {},
        readinessFilter: '',
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
        var alert = qs('[data-scraped-products-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-scraped-products-message]', root);
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
            return 'No tenes permiso para validar productos scrapeados.';
        }
        if (error && error.status === 404) {
            return 'El candidato o recurso solicitado no existe.';
        }
        if (error && error.status === 409) {
            return 'La accion no es valida para el estado actual.';
        }
        if (error && error.status === 422) {
            return 'Revisa los datos enviados.';
        }
        return (error && error.message) || 'No se pudo completar la operacion.';
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function flattenTree(items, level) {
        var output = [];
        (items || []).forEach(function (item) {
            var copy = Object.assign({}, item);
            copy.level = level || 0;
            output.push(copy);
            output = output.concat(flattenTree(item.children || [], (level || 0) + 1));
        });
        return output;
    }

    function safeLink(url, label) {
        if (!url || !/^https?:\/\//i.test(url)) {
            return escapeHtml(label || url);
        }
        return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(label || url) + '</a>';
    }

    function statusChip(status) {
        var danger = status === 'rejected' ? ' danger' : '';
        return '<span class="chip' + danger + '">' + escapeHtml(status) + '</span>';
    }

    function sourceTypeChip(source) {
        if (!source) { return ''; }
        var type = source.type || '';
        if (type === 'web_scraper') {
            return '<span class="chip" style="background:#e7f0ff;color:#2f80ed;font-size:11px">REAL</span>';
        }
        return '<span class="chip" style="background:#f5f5f5;color:#888;font-size:11px">' + escapeHtml(type || 'otro') + '</span>';
    }

    function money(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }
        return '$ ' + Number(value).toFixed(2);
    }

    function entityLabel(entity) {
        if (!entity) {
            return '-';
        }
        return entity.name || entity.code || ('#' + entity.id);
    }

    function productLabel(product) {
        if (!product) {
            return '-';
        }
        return (product.name || product.nombre || ('Producto #' + product.id)) + ' #' + product.id;
    }

    function categoryLabel(category) {
        return (category.level ? Array(category.level + 1).join('- ') : '') + category.name;
    }

    function renderSelect(select, items, first, labeler) {
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '<option value="">' + escapeHtml(first) + '</option>' + (items || []).map(function (item) {
            var label = labeler ? labeler(item) : entityLabel(item);
            return option(label, item.id);
        }).join('');
        select.value = current;
    }

    function jobLabel(job) {
        if (!job) {
            return '-';
        }
        return 'Job #' + job.id + (job.status ? ' (' + job.status + ')' : '');
    }

    function renderLookups(root) {
        renderSelect(qs('[data-candidates-source]', root), state.sources, 'Todas las fuentes', entityLabel);
        renderSelect(qs('[data-candidates-job]', root), state.jobs, 'Todos los jobs', jobLabel);
        renderSelect(qs('[data-candidate-product]', root), state.products, 'Producto', productLabel);
        renderSelect(qs('[data-candidate-ingredient]', root), state.ingredients, 'Ingrediente', entityLabel);
        renderSelect(qs('[data-candidate-create-ingredient]', root), state.ingredients, 'Ingrediente opcional', entityLabel);
        renderSelect(qs('[data-candidate-brand]', root), state.brands, 'Marca opcional', entityLabel);
        renderSelect(qs('[data-candidate-category]', root), state.categories, 'Categoria opcional', categoryLabel);
    }

    function loadLookups(root) {
        return Promise.all([
            window.CCApi.request(endpoint('/admin/scraping/sources?per_page=100')),
            window.CCApi.request(endpoint('/admin/scraping/jobs?per_page=50')),
            window.CCApi.request(endpoint('/admin/products?per_page=100&status=active&sort=name&order=asc')),
            window.CCApi.request(endpoint('/admin/ingredients?per_page=100&status=active&sort=name&order=asc')),
            window.CCApi.request(endpoint('/brands?per_page=100&sort=name&order=asc')),
            window.CCApi.request(endpoint('/product-categories')),
        ]).then(function (responses) {
            state.sources = responses[0].data || [];
            state.jobs = responses[1].data || [];
            state.products = responses[2].data || [];
            state.ingredients = responses[3].data || [];
            state.brands = responses[4].data || [];
            state.categories = flattenTree(responses[5].data || []);
            renderLookups(root);
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error));
        });
    }

    function loadCandidates(root, page) {
        var params = new URLSearchParams();
        var search = (qs('[data-candidates-search]', root).value || '').trim();
        var status = qs('[data-candidates-status]', root).value;
        var source = qs('[data-candidates-source]', root).value;
        var job = qs('[data-candidates-job]', root).value;

        state.page = page || state.page || 1;
        state.selectedIds = {};
        params.set('page', String(state.page));
        params.set('per_page', '20');
        if (search) {
            params.set('search', search);
        }
        if (status) {
            params.set('review_status', status);
        }
        if (source) {
            params.set('source_id', source);
        }
        if (job) {
            params.set('scraping_job_id', job);
        }

        qs('[data-candidates-body]', root).innerHTML = '<tr><td colspan="8" class="muted">Cargando candidatos...</td></tr>';
        return window.CCApi.request(endpoint('/admin/scraping/product-candidates?' + params.toString()))
            .then(function (response) {
                state.candidates = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderCandidates(root, response.meta || {});
            }).catch(function (error) {
                qs('[data-candidates-body]', root).innerHTML = '<tr><td colspan="8" class="muted">Error al cargar.</td></tr>';
                showMessage(root, 'danger', errorMessage(error));
            });
    }

    function isReady(candidate) {
        return !!(candidate.enrichment && candidate.enrichment.ready_for_approval);
    }

    function visibleCandidates() {
        return state.candidates.filter(function (candidate) {
            if (state.readinessFilter === 'ready') {
                return isReady(candidate);
            }
            if (state.readinessFilter === 'review') {
                return !isReady(candidate);
            }
            return true;
        });
    }

    function readinessBadgeSmall(candidate) {
        return isReady(candidate)
            ? '<span class="chip" style="background:#e3f6e5;color:#1e8e3e;font-size:11px">Listo</span>'
            : '<span class="chip" style="background:#fff0f0;color:#c0392b;font-size:11px">Revision</span>';
    }

    function updateBulkButton(root) {
        var count = Object.keys(state.selectedIds).length;
        var button = qs('[data-candidates-bulk-approve]', root);
        button.textContent = 'Aprobar seleccion (' + count + ')';
        button.disabled = count === 0;
    }

    function renderCandidates(root, meta) {
        var body = qs('[data-candidates-body]', root);
        qs('[data-candidates-count]', root).textContent = (meta.total || state.candidates.length) + ' candidatos';
        qs('[data-candidates-page]', root).textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);

        var rows = visibleCandidates();

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="8" class="muted">No hay candidatos para revisar.</td></tr>';
            updateBulkButton(root);
            return;
        }

        body.innerHTML = rows.map(function (candidate) {
            var product = candidate.suggested_product ? productLabel(candidate.suggested_product) : 'Sin producto';
            var ingredient = candidate.suggested_ingredient ? entityLabel(candidate.suggested_ingredient) : 'Sin ingrediente';
            var isFinal = candidate.review_status === 'approved' || candidate.review_status === 'rejected';
            var isPending = candidate.review_status === 'pending';
            var checkbox = isPending
                ? '<input type="checkbox" data-candidate-select="' + candidate.id + '"' + (state.selectedIds[candidate.id] ? ' checked' : '') + '>'
                : '';
            return '<tr>' +
                '<td>' + checkbox + '</td>' +
                '<td><strong>' + escapeHtml(candidate.raw_name) + '</strong><br><span class="muted">' + escapeHtml(candidate.raw_brand || 'Sin marca') + '</span><br><span class="muted">' + safeLink(candidate.raw_product_url, candidate.external_product_id || 'Ver origen') + '</span></td>' +
                '<td>' + escapeHtml(candidate.source ? candidate.source.name : candidate.source_id) + ' ' + sourceTypeChip(candidate.source) + '<br><span class="muted">Job #' + escapeHtml(candidate.scraping_job_id) + '</span></td>' +
                '<td>' + escapeHtml(money(candidate.raw_price)) + '<br><span class="muted">' + escapeHtml(candidate.raw_unit_price) + '</span></td>' +
                '<td>' + statusChip(candidate.review_status) + '</td>' +
                '<td>' + readinessBadgeSmall(candidate) + '</td>' +
                '<td><span class="muted">' + escapeHtml(product) + '</span><br><span class="muted">' + escapeHtml(ingredient) + '</span></td>' +
                '<td><button type="button" class="btn-ghost btn-sm" data-candidate-view="' + candidate.id + '">Detalle</button> ' +
                    (isFinal ? '' : '<button type="button" class="btn-main btn-sm" data-candidate-quick-approve="' + candidate.id + '">Aprobar</button>') +
                '</td>' +
            '</tr>';
        }).join('');

        var selectAll = qs('[data-candidates-select-all]', root);
        var pendingVisibleIds = rows.filter(function (c) { return c.review_status === 'pending'; }).map(function (c) { return c.id; });
        selectAll.checked = pendingVisibleIds.length > 0 && pendingVisibleIds.every(function (id) { return !!state.selectedIds[id]; });
        updateBulkButton(root);
    }

    function loadCandidate(root, id) {
        qs('[data-candidate-detail]', root).innerHTML = '<p class="muted">Cargando detalle...</p>';
        return window.CCApi.request(endpoint('/admin/scraping/product-candidates/' + encodeURIComponent(id)))
            .then(function (response) {
                state.selected = response.data;
                renderDetail(root, state.selected);
            }).catch(function (error) {
                state.selected = null;
                qs('[data-candidate-actions]', root).style.display = 'none';
                qs('[data-candidate-detail]', root).innerHTML = '<p class="muted">Error al cargar detalle.</p>';
                showMessage(root, 'danger', errorMessage(error));
            });
    }

    function tag(kind) {
        var labels = { auto: 'Detectado automaticamente', suggested: 'Sugerido', unresolved: 'Sin determinar' };
        var colors = {
            auto: 'background:#e3f6e5;color:#1e8e3e',
            suggested: 'background:#fff4e0;color:#b06a00',
            unresolved: 'background:#f5f5f5;color:#888',
        };
        return '<span class="chip" style="' + colors[kind] + ';font-size:11px;margin-left:6px">' + labels[kind] + '</span>';
    }

    function fieldLine(label, value, kind) {
        return '<div class="line"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + (kind ? tag(kind) : '') + '</strong></div>';
    }

    function setFormDefaults(root, candidate) {
        var enrichment = candidate.enrichment || { detected: {}, suggested: {} };
        var createForm = qs('[data-candidate-create-product-form]', root);
        createForm.elements.name.value = candidate.raw_name || '';
        qs('[data-candidate-product]', root).value = candidate.suggested_product_id || '';
        qs('[data-candidate-ingredient]', root).value = candidate.suggested_ingredient_id || '';
        qs('[data-candidate-create-ingredient]', root).value = candidate.suggested_ingredient_id || enrichment.suggested.ingredient_id || '';
        qs('[data-candidate-brand]', root).value = enrichment.suggested.brand_id || '';
        qs('[data-candidate-category]', root).value = enrichment.suggested.category_id || '';
    }

    function renderDetail(root, candidate) {
        var isFinal = candidate.review_status === 'approved' || candidate.review_status === 'rejected';
        var enrichment = candidate.enrichment || { detected: {}, suggested: {}, unresolved: [], ready_for_approval: false, review_reasons: [] };
        var detected = enrichment.detected || {};
        var suggested = enrichment.suggested || {};
        var unresolved = enrichment.unresolved || [];
        var image = detected.image_url && /^https?:\/\//i.test(detected.image_url)
            ? '<img src="' + escapeHtml(detected.image_url) + '" alt="" style="max-width:100%;border-radius:8px;border:1px solid var(--line);margin-bottom:10px">'
            : '';

        var presentacion = (detected.net_quantity && detected.package_unit_label)
            ? (detected.net_quantity + ' ' + detected.package_unit_label)
            : null;

        var readinessBadge = enrichment.ready_for_approval
            ? '<span class="chip" style="background:#e3f6e5;color:#1e8e3e">Listo para aprobar</span>'
            : '<span class="chip" style="background:#fff0f0;color:#c0392b">Requiere revision</span>';

        var reasons = (enrichment.review_reasons || []).map(function (reason) {
            return '<li>' + escapeHtml(reason) + '</li>';
        }).join('');
        var notes = (enrichment.review_notes || []).map(function (note) {
            return '<li>' + escapeHtml(note) + '</li>';
        }).join('');

        var existingProductWarning = suggested.existing_product_id && suggested.existing_product_id !== candidate.suggested_product_id
            ? '<p class="muted" style="color:#c0392b">El EAN ya pertenece al producto interno #' + escapeHtml(suggested.existing_product_id) + '. Usa "Asociar producto existente" en vez de crear uno nuevo.</p>'
            : '';

        var sourceCategory = detected.source_category || {};
        var sourceCategoryLine = sourceCategory.path
            ? fieldLine('Categoria origen Carrefour', sourceCategory.path, 'auto')
            : fieldLine('Categoria origen Carrefour', 'Sin dato de categoria en el scraping', 'unresolved');
        var systemCategoryLine = suggested.category_id
            ? fieldLine('Categoria del sistema', suggested.category_name, 'suggested')
            : fieldLine('Categoria del sistema', 'Sin determinar', 'unresolved');

        qs('[data-candidate-detail]', root).innerHTML =
            image +
            '<div style="margin-bottom:10px">' + readinessBadge + '</div>' +
            (reasons ? '<ul class="muted" style="margin:0 0 10px 18px;padding:0">' + reasons + '</ul>' : '') +
            (notes ? '<ul class="muted" style="margin:0 0 10px 18px;padding:0">' + notes + '</ul>' : '') +
            fieldLine('Nombre', detected.name, 'auto') +
            fieldLine('Marca', detected.brand, detected.brand ? 'auto' : null) +
            (suggested.brand_id ? fieldLine('Marca interna sugerida', suggested.brand_id, 'suggested') : '') +
            fieldLine('EAN', detected.ean || 'Sin EAN', detected.ean ? 'auto' : 'unresolved') +
            fieldLine('Cantidad', presentacion || 'Sin determinar', presentacion ? 'auto' : 'unresolved') +
            fieldLine('Precio', money(detected.price), detected.price !== null && detected.price !== undefined ? 'auto' : null) +
            fieldLine('SKU externo', detected.external_product_id, 'auto') +
            fieldLine('Fuente', candidate.source ? candidate.source.name : candidate.source_id, null) +
            fieldLine('Estado', candidate.review_status, null) +
            fieldLine('Producto interno', candidate.suggested_product ? productLabel(candidate.suggested_product) : 'Sin producto', null) +
            fieldLine(
                'Ingrediente',
                candidate.suggested_ingredient ? entityLabel(candidate.suggested_ingredient) : (suggested.ingredient_name || 'Sin ingrediente'),
                candidate.suggested_ingredient ? 'auto' : (suggested.ingredient_id ? 'suggested' : 'unresolved')
            ) +
            sourceCategoryLine +
            systemCategoryLine +
            '<div class="line"><span>Origen</span><span>' + safeLink(detected.source_url, 'Abrir URL') + '</span></div>' +
            existingProductWarning +
            (unresolved.length ? '<p class="muted" style="margin-top:6px">Campos sin senal automatica: ' + escapeHtml(unresolved.join(', ')) + '.</p>' : '') +
            (isFinal ? '<p class="muted" style="margin-top:12px">Este candidato ya fue procesado.</p>' : '');

        qs('[data-candidate-actions]', root).style.display = isFinal ? 'none' : 'block';
        if (!isFinal) {
            setFormDefaults(root, candidate);
        }
    }

    function selectedId(root) {
        if (!state.selected || !state.selected.id) {
            showMessage(root, 'danger', 'Selecciona un candidato primero.');
            return null;
        }
        return state.selected.id;
    }

    function refreshAfterAction(root, candidate) {
        state.selected = candidate;
        renderDetail(root, candidate);
        loadCandidates(root, state.page);
    }

    function submitJson(root, path, body, successMessage) {
        clearMessage(root);
        return window.CCApi.request(endpoint(path), { method: 'POST', body: body || {} })
            .then(function (response) {
                showMessage(root, 'success', successMessage);
                refreshAfterAction(root, response.data);
                return response;
            }).catch(function (error) {
                showMessage(root, 'danger', errorMessage(error));
                throw error;
            });
    }

    function matchProduct(root) {
        var id = selectedId(root);
        if (!id) {
            return;
        }
        var productId = qs('[data-candidate-product]', root).value;
        submitJson(root, '/admin/scraping/product-candidates/' + encodeURIComponent(id) + '/match-product', {
            product_id: productId ? parseInt(productId, 10) : null,
        }, 'Producto asociado.').catch(function () {
            return null;
        });
    }

    function assignIngredient(root) {
        var id = selectedId(root);
        if (!id) {
            return;
        }
        var ingredientId = qs('[data-candidate-ingredient]', root).value;
        submitJson(root, '/admin/scraping/product-candidates/' + encodeURIComponent(id) + '/assign-ingredient', {
            ingredient_id: ingredientId ? parseInt(ingredientId, 10) : null,
        }, 'Ingrediente asignado.').catch(function () {
            return null;
        });
    }

    function createProductBody(root) {
        var form = qs('[data-candidate-create-product-form]', root);
        var body = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = entry[1];
            if (value === '') {
                return;
            }
            if (key === 'brand_id' || key === 'category_id' || key === 'ingredient_id') {
                body[key] = parseInt(value, 10);
                return;
            }
            body[key] = value;
        });
        return body;
    }

    function createProduct(root) {
        var id = selectedId(root);
        if (!id) {
            return;
        }
        submitJson(root, '/admin/scraping/product-candidates/' + encodeURIComponent(id) + '/create-product', createProductBody(root), 'Producto creado y asociado.').catch(function () {
            return null;
        });
    }

    function approve(root, id) {
        var candidateId = id || selectedId(root);
        if (!candidateId) {
            return;
        }
        submitJson(root, '/admin/scraping/product-candidates/' + encodeURIComponent(candidateId) + '/approve', {}, 'Candidato aprobado.').catch(function () {
            return null;
        });
    }

    function createAndApprove(root) {
        var id = selectedId(root);
        if (!id) {
            return;
        }
        submitJson(root, '/admin/scraping/product-candidates/' + encodeURIComponent(id) + '/create-and-approve', createProductBody(root), 'Producto aprobado.').catch(function () {
            return null;
        });
    }

    function bulkResultMessage(result) {
        return 'Aprobados: ' + result.approved + '. Omitidos: ' + result.skipped + '. Fallidos: ' + result.failed + ' (de ' + result.requested + ' seleccionados).';
    }

    function bulkApprove(root) {
        var ids = Object.keys(state.selectedIds).map(function (id) { return parseInt(id, 10); });
        if (!ids.length) {
            return;
        }

        var readyCount = ids.filter(function (id) {
            var candidate = state.candidates.find(function (c) { return c.id === id; });
            return candidate && isReady(candidate);
        }).length;
        var reviewCount = ids.length - readyCount;

        var confirmMessage = 'Se aprobaran ' + readyCount + ' candidatos listos para catalogo.' +
            (reviewCount > 0 ? ' Los ' + reviewCount + ' candidatos que requieren revision seran omitidos.' : '');
        if (!window.confirm(confirmMessage)) {
            return;
        }

        clearMessage(root);
        var button = qs('[data-candidates-bulk-approve]', root);
        button.disabled = true;

        return window.CCApi.request(endpoint('/admin/scraping/product-candidates/bulk-create-and-approve'), {
            method: 'POST',
            body: { candidate_ids: ids },
        }).then(function (result) {
            showMessage(root, result.failed > 0 ? 'danger' : 'success', bulkResultMessage(result));
            state.selectedIds = {};
            loadCandidates(root, state.page);
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error));
            button.disabled = Object.keys(state.selectedIds).length === 0;
        });
    }

    function reject(root) {
        var id = selectedId(root);
        if (!id) {
            return;
        }
        var form = qs('[data-candidate-reject-form]', root);
        var reason = form.elements.reason.value.trim();
        submitJson(root, '/admin/scraping/product-candidates/' + encodeURIComponent(id) + '/reject', {
            reason: reason,
        }, 'Candidato rechazado.').then(function () {
            form.reset();
        }).catch(function () {
            return null;
        });
    }

    function bind(root) {
        qs('[data-candidates-refresh]', root).addEventListener('click', function () {
            loadCandidates(root, 1);
        });
        qs('[data-candidates-search]', root).addEventListener('input', function () {
            loadCandidates(root, 1);
        });
        qs('[data-candidates-status]', root).addEventListener('change', function () {
            loadCandidates(root, 1);
        });
        qs('[data-candidates-source]', root).addEventListener('change', function () {
            loadCandidates(root, 1);
        });
        qs('[data-candidates-job]', root).addEventListener('change', function () {
            loadCandidates(root, 1);
        });
        qs('[data-candidates-readiness]', root).addEventListener('change', function (event) {
            state.readinessFilter = event.target.value;
            renderCandidates(root, {});
        });
        qs('[data-candidates-select-all]', root).addEventListener('change', function (event) {
            var checked = event.target.checked;
            visibleCandidates().forEach(function (candidate) {
                if (candidate.review_status !== 'pending') {
                    return;
                }
                if (checked) {
                    state.selectedIds[candidate.id] = true;
                } else {
                    delete state.selectedIds[candidate.id];
                }
            });
            renderCandidates(root, {});
        });
        qs('[data-candidates-bulk-approve]', root).addEventListener('click', function () {
            bulkApprove(root);
        });
        qs('[data-candidates-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                loadCandidates(root, state.page - 1);
            }
        });
        qs('[data-candidates-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                loadCandidates(root, state.page + 1);
            }
        });
        qs('[data-candidates-body]', root).addEventListener('click', function (event) {
            var view = event.target.closest('[data-candidate-view]');
            var quickApprove = event.target.closest('[data-candidate-quick-approve]');
            var select = event.target.closest('[data-candidate-select]');
            if (view) {
                loadCandidate(root, view.getAttribute('data-candidate-view'));
            }
            if (quickApprove) {
                approve(root, quickApprove.getAttribute('data-candidate-quick-approve'));
            }
            if (select) {
                var id = select.getAttribute('data-candidate-select');
                if (select.checked) {
                    state.selectedIds[id] = true;
                } else {
                    delete state.selectedIds[id];
                }
                var selectAll = qs('[data-candidates-select-all]', root);
                var pendingVisibleIds = visibleCandidates().filter(function (c) { return c.review_status === 'pending'; }).map(function (c) { return c.id; });
                selectAll.checked = pendingVisibleIds.length > 0 && pendingVisibleIds.every(function (cid) { return !!state.selectedIds[cid]; });
                updateBulkButton(root);
            }
        });
        qs('[data-candidate-match-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            matchProduct(root);
        });
        qs('[data-candidate-ingredient-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            assignIngredient(root);
        });
        qs('[data-candidate-create-product-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            createProduct(root);
        });
        qs('[data-candidate-approve]', root).addEventListener('click', function () {
            approve(root);
        });
        qs('[data-candidate-create-and-approve]', root).addEventListener('click', function () {
            createAndApprove(root);
        });
        qs('[data-candidate-reject-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            reject(root);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-scraped-products]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
        loadLookups(root).then(function () {
            loadCandidates(root, 1);
        });
    });
})(window, document);
