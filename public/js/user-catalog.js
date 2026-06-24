(function (window, document) {
    'use strict';

    var state = {
        activeTab: 'products',
        products: { items: [], page: 1, lastPage: 1, total: 0 },
        ingredients: { items: [], page: 1, lastPage: 1, total: 0 },
        selectedTagIds: [],
        reportProductId: null,
    };

    var REPORT_TYPES = {
        incorrect_price:        'Precio incorrecto',
        incorrect_product_data: 'Datos del producto incorrectos',
        incorrect_nutrition:    'Información nutricional incorrecta',
        duplicate_product:      'Producto duplicado',
        other:                  'Otro problema',
    };

    function primaryImage(images) {
        var imgs = (images || []).filter(function (i) { return i.image_url; });
        return imgs.filter(function (i) { return i.is_primary; })[0] || imgs[0] || null;
    }

    function thumbnailHtml(images) {
        var img = primaryImage(images);
        if (!img) { return ''; }
        return '<img class="product-thumb-img" src="' + escapeHtml(img.image_url) + '" loading="lazy" alt="">';
    }

    function buildImageGalleryHtml(images) {
        var imgs = (images || []).filter(function (i) { return i.image_url; });
        if (!imgs.length) {
            return '<div class="product-image-placeholder">Sin imagen disponible</div>';
        }
        var main = imgs.filter(function (i) { return i.is_primary; })[0] || imgs[0];
        var mainHtml = '<img class="product-image-main" data-catalog-img-main src="' +
            escapeHtml(main.image_url) + '" loading="lazy" alt="">';
        var thumbsHtml = '';
        if (imgs.length > 1) {
            thumbsHtml = '<div class="product-image-gallery">' +
                imgs.map(function (img) {
                    var active = img.image_url === main.image_url ? ' active' : '';
                    return '<img class="product-image-thumb' + active + '" loading="lazy" alt=""' +
                        ' data-catalog-img-switch="' + escapeHtml(img.image_url) + '"' +
                        ' src="' + escapeHtml(img.image_url) + '">';
                }).join('') +
            '</div>';
        }
        return mainHtml + thumbsHtml;
    }

    function buildReportFormHtml(productId) {
        var options = Object.keys(REPORT_TYPES).map(function (k) {
            return '<option value="' + k + '">' + escapeHtml(REPORT_TYPES[k]) + '</option>';
        }).join('');
        return '<div class="report-form-toggle">' +
            '<button type="button" class="btn-secondary-web btn-sm" data-catalog-report-toggle>' +
                'Reportar problema' +
            '</button>' +
        '</div>' +
        '<div class="report-form-section" data-catalog-report-form style="display:none" data-catalog-report-product="' + productId + '">' +
            '<select class="form-control" data-catalog-report-type style="margin-bottom:8px">' +
                '<option value="">Seleccioná el tipo de problema...</option>' +
                options +
            '</select>' +
            '<textarea class="form-control" data-catalog-report-desc rows="3"' +
                ' placeholder="Descripción (opcional, máx. 2000 caracteres)"' +
                ' maxlength="2000" style="width:100%;box-sizing:border-box;margin-bottom:8px"></textarea>' +
            '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
                '<button type="button" class="btn-main btn-sm" data-catalog-report-submit>Enviar reporte</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-catalog-report-cancel>Cancelar</button>' +
            '</div>' +
            '<div class="alert" data-catalog-report-message style="display:none;margin-top:8px"></div>' +
        '</div>';
    }

    function buildPriceRefreshHtml(productId) {
        return '<div class="report-form-toggle" style="margin-top:10px">' +
            '<button type="button" class="btn-secondary-web btn-sm" data-price-refresh-toggle>' +
                'Precio incorrecto' +
            '</button>' +
        '</div>' +
        '<div class="report-form-section" data-price-refresh-form style="display:none" data-price-refresh-product="' + productId + '">' +
            '<textarea class="form-control" data-price-refresh-reason rows="3"' +
                ' placeholder="Contanos que precio viste o donde esta desactualizado (opcional)"' +
                ' maxlength="1000" style="width:100%;box-sizing:border-box;margin-bottom:8px"></textarea>' +
            '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
                '<button type="button" class="btn-main btn-sm" data-price-refresh-submit>Solicitar actualizacion</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-price-refresh-cancel>Cancelar</button>' +
            '</div>' +
            '<div class="alert" data-price-refresh-message style="display:none;margin-top:8px"></div>' +
        '</div>' +
        '<div data-price-refresh-mine style="margin-top:12px"></div>';
    }

    function priceLabel(row) {
        if (!row || !row.current_price) {
            return 'Sin precio';
        }
        return escapeHtml(row.current_price.currency || 'ARS') + ' ' + escapeHtml(row.current_price.price);
    }

    function dateLabel(value) {
        if (!value) {
            return '-';
        }
        var date = new Date(value);
        if (isNaN(date.getTime())) {
            return text(value);
        }
        return date.toLocaleString('es-AR');
    }

    function supermarketLabel(row) {
        var branch = row && row.branch ? row.branch : null;
        if (!branch) {
            return '-';
        }
        var chain = branch.chain && branch.chain.name ? branch.chain.name + ' - ' : '';
        return chain + branch.name;
    }

    function loadProductPrices(root, productId) {
        var pricesTarget = qs('[data-catalog-product-prices]', root);
        var bestTarget = qs('[data-catalog-product-best-price]', root);
        if (pricesTarget) {
            pricesTarget.innerHTML = '<p class="muted" style="font-size:13px">Cargando precios...</p>';
        }
        if (bestTarget) {
            bestTarget.innerHTML = '<p class="muted" style="font-size:13px">Buscando mejor precio...</p>';
        }

        window.CCApi.request(endpoint('/products/' + encodeURIComponent(productId) + '/supermarket-prices'))
            .then(function (response) {
                var rows = response.data || [];
                if (!pricesTarget) {
                    return;
                }
                if (!rows.length) {
                    pricesTarget.innerHTML = '<p class="muted" style="font-size:13px">Sin precios disponibles.</p>';
                    return;
                }
                pricesTarget.innerHTML = rows.map(function (row) {
                    var updatedAt = row.current_price
                        ? (row.current_price.scraped_at || row.current_price.captured_at || row.updated_at)
                        : row.updated_at;
                    return '<div class="table-line">' +
                        '<span><strong>' + escapeHtml(supermarketLabel(row)) + '</strong><br><span class="muted" style="font-size:12px">Actualizado: ' + escapeHtml(dateLabel(updatedAt)) + '</span></span>' +
                        '<span style="text-align:right"><strong>' + priceLabel(row) + '</strong><br><a href="/web/branches" class="muted" style="font-size:12px">Ver sucursal</a></span>' +
                        '</div>';
                }).join('');
            })
            .catch(function () {
                if (pricesTarget) {
                    pricesTarget.innerHTML = '<p class="muted" style="font-size:13px">No se pudo cargar la comparación.</p>';
                }
            });

        window.CCApi.request(endpoint('/products/' + encodeURIComponent(productId) + '/best-price'))
            .then(function (response) {
                var row = response.data;
                if (!bestTarget) {
                    return;
                }
                bestTarget.innerHTML =
                    '<div class="table-line"><span class="muted">Precio</span><strong>' + priceLabel(row) + '</strong></div>' +
                    '<div class="table-line"><span class="muted">Sucursal</span><strong>' + escapeHtml(supermarketLabel(row)) + '</strong></div>' +
                    '<div class="table-line"><span class="muted">Actualizado</span><span>' + escapeHtml(dateLabel(row.current_price && (row.current_price.scraped_at || row.current_price.captured_at))) + '</span></div>' +
                    (row.source_url ? '<a href="' + escapeHtml(row.source_url) + '" target="_blank" rel="noopener noreferrer" class="btn-secondary-web btn-sm">Abrir publicación</a>' : '');
            })
            .catch(function () {
                if (bestTarget) {
                    bestTarget.innerHTML = '<p class="muted" style="font-size:13px">Sin mejor precio disponible.</p>';
                }
            });
    }

    function loadMyPriceRefreshRequests(root) {
        var target = qs('[data-price-refresh-mine]', root);
        if (!target) { return; }
        target.innerHTML = '<p class="muted" style="font-size:13px">Cargando solicitudes recientes...</p>';
        window.CCApi.request(endpoint('/users/me/price-refresh-requests?per_page=5'))
            .then(function (response) {
                var rows = response.data || [];
                if (!rows.length) {
                    target.innerHTML = '<p class="muted" style="font-size:13px">Todavia no enviaste solicitudes de actualizacion.</p>';
                    return;
                }
                target.innerHTML = '<h3 style="font-size:13px;font-weight:900;margin:16px 0 6px">Mis solicitudes recientes</h3>' +
                    rows.map(function (row) {
                        var name = row.product ? row.product.name : ('Producto #' + row.product_id);
                        return '<div class="table-line">' +
                            '<span><strong>' + escapeHtml(name) + '</strong><br><span class="muted" style="font-size:12px">' + escapeHtml(dateLabel(row.requested_at || row.created_at)) + '</span></span>' +
                            '<span>' + escapeHtml(row.status) + '</span>' +
                        '</div>';
                    }).join('');
            })
            .catch(function () {
                target.innerHTML = '<p class="muted" style="font-size:13px">No se pudieron cargar tus solicitudes.</p>';
            });
    }

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

    function showMessage(el, type, msg) {
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function hideMessage(el) {
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

    // ── FILTROS ──────────────────────────────────────────────────────────────

    function loadFilters(root) {
        window.CCApi.request(endpoint('/product-categories?' + buildParams({ per_page: 200, sort: 'name', order: 'asc' })))
            .then(function (r) {
                var sel = qs('[data-catalog-products-category]', root);
                if (!sel) { return; }
                sel.innerHTML = '<option value="">Todas las categorías</option>' +
                    (r.data || []).map(function (c) {
                        return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
                    }).join('');
            }).catch(function () {});

        window.CCApi.request(endpoint('/brands?' + buildParams({ per_page: 200, sort: 'name', order: 'asc' })))
            .then(function (r) {
                var sel = qs('[data-catalog-products-brand]', root);
                if (!sel) { return; }
                sel.innerHTML = '<option value="">Todas las marcas</option>' +
                    (r.data || []).map(function (b) {
                        return '<option value="' + b.id + '">' + escapeHtml(b.name) + '</option>';
                    }).join('');
            }).catch(function () {});

        window.CCApi.request(endpoint('/ingredient-categories?' + buildParams({ per_page: 200, sort: 'name', order: 'asc' })))
            .then(function (r) {
                var sel = qs('[data-catalog-ingredients-category]', root);
                if (!sel) { return; }
                sel.innerHTML = '<option value="">Todas las categorías</option>' +
                    (r.data || []).map(function (c) {
                        return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
                    }).join('');
            }).catch(function () {});

        window.CCApi.request(endpoint('/food-tags?' + buildParams({ per_page: 200 })))
            .then(function (r) {
                var tags = r.data || [];
                if (!tags.length) { return; }
                var wrapper = qs('[data-catalog-tags-filter]', root);
                if (wrapper) { wrapper.style.display = 'block'; }
                renderTagChips(root, tags);
            }).catch(function () {});
    }

    function renderTagChips(root, tags) {
        var container = qs('[data-catalog-tags-chips]', root);
        if (!container) { return; }
        container.innerHTML = (tags || []).map(function (tag) {
            var active = state.selectedTagIds.indexOf(tag.id) >= 0;
            return '<button type="button"' +
                ' class="chip' + (active ? '" style="background:var(--green);color:#fff;cursor:pointer;margin:2px"' : '" style="cursor:pointer;margin:2px"') +
                ' data-catalog-tag-toggle="' + tag.id + '">' + escapeHtml(tag.name) + '</button>';
        }).join('');
    }

    // ── PRODUCTOS ────────────────────────────────────────────────────────────

    function fetchProducts(root, page) {
        var msgEl = qs('[data-catalog-products-message]', root);
        hideMessage(msgEl);
        state.products.page = page || 1;

        var params = { per_page: 20, page: state.products.page, sort: 'name', order: 'asc' };
        var search = qs('[data-catalog-products-search]', root);
        var cat    = qs('[data-catalog-products-category]', root);
        var brand  = qs('[data-catalog-products-brand]', root);
        if (search && search.value.trim()) { params.search = search.value.trim(); }
        if (cat && cat.value)              { params.category_id = cat.value; }
        if (brand && brand.value)          { params.brand_id = brand.value; }

        var tbody = qs('[data-catalog-products-body]', root);
        tbody.innerHTML = '<tr><td colspan="5" class="muted">Buscando...</td></tr>';

        return window.CCApi.request(endpoint('/products?' + buildParams(params)))
            .then(function (r) {
                state.products.items    = r.data || [];
                state.products.total    = (r.meta && r.meta.total)     || 0;
                state.products.lastPage = (r.meta && r.meta.last_page) || 1;
                renderProductsTable(root);
                updatePagination(root,
                    '[data-catalog-products-prev]',
                    '[data-catalog-products-next]',
                    '[data-catalog-products-page]',
                    state.products.page,
                    state.products.lastPage);
                var countEl = qs('[data-catalog-products-count]', root);
                if (countEl) { countEl.textContent = state.products.total + ' productos'; }
            }).catch(function (err) {
                showMessage(msgEl, 'danger', apiError(err));
                tbody.innerHTML = '<tr><td colspan="5" class="muted">Error al cargar el catálogo.</td></tr>';
            });
    }

    function renderProductsTable(root) {
        var tbody = qs('[data-catalog-products-body]', root);
        if (!state.products.items.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="muted">Sin resultados para los filtros aplicados.</td></tr>';
            return;
        }
        tbody.innerHTML = state.products.items.map(function (p) {
            return '<tr>' +
                '<td style="display:flex;align-items:center;gap:6px;min-width:0">' +
                    thumbnailHtml(p.images) +
                    '<span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(p.name) + '</span>' +
                '</td>' +
                '<td>' + escapeHtml(p.brand && p.brand.name) + '</td>' +
                '<td>' + escapeHtml(p.category && p.category.name) + '</td>' +
                '<td style="font-family:monospace;font-size:12px">' + escapeHtml(p.barcode) + '</td>' +
                '<td><button type="button" class="btn-secondary-web btn-sm" data-catalog-product-view="' + p.id + '">Ver</button></td>' +
            '</tr>';
        }).join('');
    }

    function showProductDetail(root, productId) {
        var detail = qs('[data-catalog-detail]', root);
        if (!detail) { return; }
        detail.innerHTML = '<p class="muted">Cargando...</p>';
        state.reportProductId = null;

        window.CCApi.request(endpoint('/products/' + encodeURIComponent(productId)))
            .then(function (r) {
                var p = r.data;
                if (!p) { detail.innerHTML = '<p class="muted">Producto no disponible.</p>'; return; }
                state.reportProductId = p.id;
                detail.innerHTML =
                    buildImageGalleryHtml(p.images) +
                    '<h2 style="font-size:16px;margin:0 0 10px">' + escapeHtml(p.name) + '</h2>' +
                    '<div class="table-line"><span class="muted">Marca</span><strong>'     + escapeHtml(p.brand    && p.brand.name)    + '</strong></div>' +
                    '<div class="table-line"><span class="muted">Categoría</span><strong>' + escapeHtml(p.category && p.category.name) + '</strong></div>' +
                    '<div class="table-line"><span class="muted">Código</span><strong style="font-family:monospace;font-size:12px">' + escapeHtml(p.barcode) + '</strong></div>' +
                    '<div class="table-line"><span class="muted">Cantidad</span><strong>'  + escapeHtml(p.net_quantity) + ' ' + escapeHtml(p.unit && p.unit.symbol) + '</strong></div>' +
                    '<div class="table-line"><span class="muted">Descripción</span><span style="font-size:13px">' + escapeHtml(p.description) + '</span></div>' +
                    '<h3 style="font-size:13px;font-weight:900;margin:16px 0 6px">Mejor precio</h3>' +
                    '<div data-catalog-product-best-price><p class="muted" style="font-size:13px">Buscando mejor precio...</p></div>' +
                    '<h3 style="font-size:13px;font-weight:900;margin:16px 0 6px">Comparación por supermercado</h3>' +
                    '<div data-catalog-product-prices><p class="muted" style="font-size:13px">Cargando precios...</p></div>' +
                    '<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">' +
                    '<a href="/web/barcode-scanner" class="btn-secondary-web btn-sm">Buscar por código</a>' +
                    '<a href="/web/branches" class="btn-secondary-web btn-sm">Ver sucursales</a>' +
                    '</div>' +
                    buildPriceRefreshHtml(p.id) +
                    buildReportFormHtml(p.id);
                loadProductPrices(root, p.id);
                loadMyPriceRefreshRequests(root);
            }).catch(function (err) {
                var code = err.status || 0;
                if (code === 404) { detail.innerHTML = '<p class="muted">Producto no encontrado.</p>'; }
                else { detail.innerHTML = '<p class="muted">No se pudo cargar el detalle del producto.</p>'; }
            });
    }

    function submitReport(root) {
        var form = qs('[data-catalog-report-form]', root);
        if (!form) { return; }

        var productId = form.dataset.catalogReportProduct;
        var typeEl    = qs('[data-catalog-report-type]', root);
        var descEl    = qs('[data-catalog-report-desc]', root);
        var submitBtn = qs('[data-catalog-report-submit]', root);
        var msgEl     = qs('[data-catalog-report-message]', root);

        if (!productId) { return; }

        var type = typeEl ? typeEl.value : '';
        if (!type) {
            if (msgEl) { msgEl.textContent = 'Seleccioná el tipo de problema.'; msgEl.className = 'alert alert-danger'; msgEl.style.display = 'block'; }
            return;
        }

        var body = { type: type };
        var desc = descEl ? descEl.value.trim() : '';
        if (desc) { body.description = desc; }

        if (submitBtn) { submitBtn.disabled = true; }
        if (msgEl) { msgEl.style.display = 'none'; }

        window.CCApi.request(endpoint('/products/' + encodeURIComponent(productId) + '/reports'), {
            method: 'POST',
            body: body,
        })
        .then(function () {
            if (typeEl) { typeEl.value = ''; }
            if (descEl) { descEl.value = ''; }
            if (form)   { form.style.display = 'none'; }
            if (msgEl) {
                msgEl.textContent = 'Reporte enviado. ¡Gracias por tu colaboración!';
                msgEl.className = 'alert alert-success';
                msgEl.style.display = 'block';
            }
        })
        .catch(function (err) {
            var code = err.status || 0;
            var msg  = 'Error al enviar el reporte.';
            if (code === 404) { msg = 'Producto no encontrado.'; }
            else if (code === 422) {
                var errors = err.payload && err.payload.error && err.payload.error.errors;
                if (errors && errors.type)        { msg = errors.type[0]; }
                else if (errors && errors.description) { msg = errors.description[0]; }
                else { msg = apiError(err); }
            } else { msg = apiError(err); }
            if (msgEl) { msgEl.textContent = msg; msgEl.className = 'alert alert-danger'; msgEl.style.display = 'block'; }
        })
        .then(function () {
            if (submitBtn) { submitBtn.disabled = false; }
        });
    }

    function submitPriceRefresh(root) {
        var form = qs('[data-price-refresh-form]', root);
        if (!form) { return; }

        var productId = form.dataset.priceRefreshProduct;
        var reasonEl = qs('[data-price-refresh-reason]', root);
        var submitBtn = qs('[data-price-refresh-submit]', root);
        var msgEl = qs('[data-price-refresh-message]', root);
        if (!productId) { return; }

        var body = {};
        var reason = reasonEl ? reasonEl.value.trim() : '';
        if (reason) { body.reason = reason; }

        if (submitBtn) { submitBtn.disabled = true; }
        if (msgEl) { msgEl.style.display = 'none'; }

        window.CCApi.request(endpoint('/products/' + encodeURIComponent(productId) + '/request-price-refresh'), {
            method: 'POST',
            body: body,
        })
        .then(function () {
            if (reasonEl) { reasonEl.value = ''; }
            form.style.display = 'none';
            if (msgEl) {
                msgEl.textContent = 'Solicitud enviada. Vamos a revisar este precio.';
                msgEl.className = 'alert alert-success';
                msgEl.style.display = 'block';
            }
            loadMyPriceRefreshRequests(root);
        })
        .catch(function (err) {
            var msg = apiError(err);
            if (err && err.status === 409) {
                msg = 'Ya tenes una solicitud pendiente para este producto.';
            }
            if (err && err.status === 404) {
                msg = 'Producto no encontrado.';
            }
            if (msgEl) {
                msgEl.textContent = msg;
                msgEl.className = 'alert alert-danger';
                msgEl.style.display = 'block';
            }
        })
        .then(function () {
            if (submitBtn) { submitBtn.disabled = false; }
        });
    }

    // ── INGREDIENTES ─────────────────────────────────────────────────────────

    function fetchIngredients(root, page) {
        var msgEl = qs('[data-catalog-ingredients-message]', root);
        hideMessage(msgEl);
        state.ingredients.page = page || 1;

        var params = { per_page: 20, page: state.ingredients.page, sort: 'name', order: 'asc' };
        var search = qs('[data-catalog-ingredients-search]', root);
        var cat    = qs('[data-catalog-ingredients-category]', root);
        if (search && search.value.trim())   { params.search = search.value.trim(); }
        if (cat && cat.value)                { params.category_id = cat.value; }
        if (state.selectedTagIds.length)     { params.food_tag_ids = state.selectedTagIds.join(','); }

        var tbody = qs('[data-catalog-ingredients-body]', root);
        tbody.innerHTML = '<tr><td colspan="4" class="muted">Buscando...</td></tr>';

        return window.CCApi.request(endpoint('/ingredients?' + buildParams(params)))
            .then(function (r) {
                state.ingredients.items    = r.data || [];
                state.ingredients.total    = (r.meta && r.meta.total)     || 0;
                state.ingredients.lastPage = (r.meta && r.meta.last_page) || 1;
                renderIngredientsTable(root);
                updatePagination(root,
                    '[data-catalog-ingredients-prev]',
                    '[data-catalog-ingredients-next]',
                    '[data-catalog-ingredients-page]',
                    state.ingredients.page,
                    state.ingredients.lastPage);
                var countEl = qs('[data-catalog-ingredients-count]', root);
                if (countEl) { countEl.textContent = state.ingredients.total + ' ingredientes'; }
            }).catch(function (err) {
                showMessage(msgEl, 'danger', apiError(err));
                tbody.innerHTML = '<tr><td colspan="4" class="muted">Error al cargar el catálogo.</td></tr>';
            });
    }

    function renderIngredientsTable(root) {
        var tbody = qs('[data-catalog-ingredients-body]', root);
        if (!state.ingredients.items.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="muted">Sin resultados para los filtros aplicados.</td></tr>';
            return;
        }
        tbody.innerHTML = state.ingredients.items.map(function (ing) {
            var tags = (ing.food_tags || []).map(function (t) {
                return '<span class="chip" style="font-size:10px;padding:2px 6px;margin:1px">' + escapeHtml(t.name) + '</span>';
            }).join('');
            return '<tr>' +
                '<td>' + escapeHtml(ing.name) + '</td>' +
                '<td>' + escapeHtml(ing.category && ing.category.name) + '</td>' +
                '<td>' + (tags || '<span class="muted">-</span>') + '</td>' +
                '<td><button type="button" class="btn-secondary-web btn-sm" data-catalog-ingredient-view="' + ing.id + '">Ver</button></td>' +
            '</tr>';
        }).join('');
    }

    function showIngredientDetail(root, ingredientId) {
        var detail = qs('[data-catalog-detail]', root);
        if (!detail) { return; }
        detail.innerHTML = '<p class="muted">Cargando...</p>';

        var requests = [
            window.CCApi.request(endpoint('/ingredients/' + encodeURIComponent(ingredientId))),
            window.CCApi.request(endpoint('/ingredients/' + encodeURIComponent(ingredientId) + '/nutrition'))
                .catch(function () { return { data: [] }; }),
            window.CCApi.request(endpoint('/ingredients/' + encodeURIComponent(ingredientId) + '/equivalences'))
                .catch(function () { return { data: [] }; }),
        ];

        Promise.all(requests).then(function (results) {
            var ing         = results[0].data;
            var nutrition   = results[1].data || [];
            var equivalences = results[2].data || [];

            if (!ing) { detail.innerHTML = '<p class="muted">Ingrediente no disponible.</p>'; return; }

            var tags = (ing.food_tags || []).map(function (t) {
                return '<span class="chip" style="font-size:10px;padding:2px 6px">' + escapeHtml(t.name) + '</span>';
            }).join(' ');

            var nutritionHtml = nutrition.length
                ? nutrition.map(function (n) {
                    return '<div class="table-line">' +
                        '<span class="muted">' + escapeHtml(n.nutrient && n.nutrient.name) + '</span>' +
                        '<strong>' + escapeHtml(n.amount_per_100g) + ' ' +
                            escapeHtml(n.nutrient && n.nutrient.unit && n.nutrient.unit.symbol) + '</strong></div>';
                }).join('')
                : '<p class="muted" style="font-size:13px">Sin datos nutricionales registrados.</p>';

            var equivHtml = equivalences.length
                ? '<ul style="padding-left:16px;font-size:13px;margin:0">' +
                    equivalences.map(function (e) {
                        var name = (e.target_ingredient && e.target_ingredient.name) || '#' + e.target_ingredient_id;
                        return '<li>' + escapeHtml(name) + ' — factor: ' + escapeHtml(e.conversion_factor) + '</li>';
                    }).join('') + '</ul>'
                : '<p class="muted" style="font-size:13px">Sin equivalencias registradas.</p>';

            detail.innerHTML =
                '<h2 style="font-size:16px;margin:0 0 10px">' + escapeHtml(ing.name) + '</h2>' +
                (tags ? '<div style="margin-bottom:12px">' + tags + '</div>' : '') +
                '<div class="table-line"><span class="muted">Categoría</span><strong>'   + escapeHtml(ing.category  && ing.category.name)    + '</strong></div>' +
                '<div class="table-line"><span class="muted">Unidad base</span><strong>' + escapeHtml(ing.base_unit && ing.base_unit.symbol)  + '</strong></div>' +
                '<div class="table-line"><span class="muted">Descripción</span><strong>' + escapeHtml(ing.description) + '</strong></div>' +
                '<h3 style="font-size:13px;font-weight:900;margin:16px 0 6px">Nutrientes por 100g</h3>' + nutritionHtml +
                '<h3 style="font-size:13px;font-weight:900;margin:16px 0 6px">Equivalencias</h3>' + equivHtml;
        }).catch(function () {
            detail.innerHTML = '<p class="muted">No se pudo cargar el detalle del ingrediente.</p>';
        });
    }

    // ── PAGINACIÓN ───────────────────────────────────────────────────────────

    function updatePagination(root, prevSel, nextSel, pageSel, page, lastPage) {
        var prev = qs(prevSel, root);
        var next = qs(nextSel, root);
        var label = qs(pageSel, root);
        if (prev)  { prev.disabled  = page <= 1; }
        if (next)  { next.disabled  = page >= lastPage; }
        if (label) { label.textContent = 'Página ' + page + ' / ' + lastPage; }
    }

    // ── TABS ─────────────────────────────────────────────────────────────────

    function switchTab(root, tab) {
        state.activeTab = tab;
        root.querySelectorAll('[data-catalog-tab]').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.catalogTab === tab);
        });
        root.querySelectorAll('[data-catalog-panel]').forEach(function (panel) {
            panel.style.display = (panel.dataset.catalogPanel === tab) ? '' : 'none';
        });
        var detail = qs('[data-catalog-detail]', root);
        if (detail) { detail.innerHTML = '<p class="muted">Seleccioná un item para ver el detalle.</p>'; }

        if (tab === 'products'    && !state.products.items.length)    { fetchProducts(root, 1); }
        if (tab === 'ingredients' && !state.ingredients.items.length) { fetchIngredients(root, 1); }
    }

    // ── BIND ─────────────────────────────────────────────────────────────────

    function bind(root) {
        root.addEventListener('click', function (e) {
            var t = e.target;

            if (t.dataset.catalogTab)            { switchTab(root, t.dataset.catalogTab); return; }
            if (t.dataset.catalogProductView)    { showProductDetail(root, t.dataset.catalogProductView); return; }
            if (t.dataset.catalogIngredientView) { showIngredientDetail(root, t.dataset.catalogIngredientView); return; }

            if (t.dataset.catalogImgSwitch) {
                var mainImg = root.querySelector('[data-catalog-img-main]');
                if (mainImg) { mainImg.src = t.dataset.catalogImgSwitch; }
                root.querySelectorAll('.product-image-thumb').forEach(function (thumb) {
                    thumb.classList.toggle('active', thumb.dataset.catalogImgSwitch === t.dataset.catalogImgSwitch);
                });
                return;
            }

            if (t.dataset.hasOwnProperty('catalogReportToggle')) {
                var form = root.querySelector('[data-catalog-report-form]');
                if (form) { form.style.display = form.style.display === 'none' ? '' : 'none'; }
                return;
            }

            if (t.dataset.hasOwnProperty('catalogReportCancel')) {
                var cancelForm = root.querySelector('[data-catalog-report-form]');
                if (cancelForm) { cancelForm.style.display = 'none'; }
                var cancelMsg = root.querySelector('[data-catalog-report-message]');
                if (cancelMsg) { cancelMsg.style.display = 'none'; }
                return;
            }

            if (t.dataset.hasOwnProperty('catalogReportSubmit')) {
                submitReport(root);
                return;
            }

            if (t.dataset.hasOwnProperty('priceRefreshToggle')) {
                var priceForm = root.querySelector('[data-price-refresh-form]');
                if (priceForm) { priceForm.style.display = priceForm.style.display === 'none' ? '' : 'none'; }
                return;
            }

            if (t.dataset.hasOwnProperty('priceRefreshCancel')) {
                var cancelPriceForm = root.querySelector('[data-price-refresh-form]');
                if (cancelPriceForm) { cancelPriceForm.style.display = 'none'; }
                var priceMsg = root.querySelector('[data-price-refresh-message]');
                if (priceMsg) { priceMsg.style.display = 'none'; }
                return;
            }

            if (t.dataset.hasOwnProperty('priceRefreshSubmit')) {
                submitPriceRefresh(root);
                return;
            }

            if (t.dataset.catalogTagToggle !== undefined) {
                var tagId = parseInt(t.dataset.catalogTagToggle, 10);
                var idx = state.selectedTagIds.indexOf(tagId);
                if (idx >= 0) { state.selectedTagIds.splice(idx, 1); } else { state.selectedTagIds.push(tagId); }
                t.style.background = (state.selectedTagIds.indexOf(tagId) >= 0) ? 'var(--green)' : '';
                t.style.color      = (state.selectedTagIds.indexOf(tagId) >= 0) ? '#fff' : '';
                fetchIngredients(root, 1);
                return;
            }

            if (t.dataset.hasOwnProperty('catalogProductsPrev')         && !t.disabled) { fetchProducts(root, state.products.page - 1); return; }
            if (t.dataset.hasOwnProperty('catalogProductsNext')         && !t.disabled) { fetchProducts(root, state.products.page + 1); return; }
            if (t.dataset.hasOwnProperty('catalogProductsRefresh'))                     { fetchProducts(root, 1); return; }
            if (t.dataset.hasOwnProperty('catalogIngredientsPrev')      && !t.disabled) { fetchIngredients(root, state.ingredients.page - 1); return; }
            if (t.dataset.hasOwnProperty('catalogIngredientsNext')      && !t.disabled) { fetchIngredients(root, state.ingredients.page + 1); return; }
            if (t.dataset.hasOwnProperty('catalogIngredientsRefresh'))                  { fetchIngredients(root, 1); return; }
        });

        [
            { sel: '[data-catalog-products-search]',    tab: 'products' },
            { sel: '[data-catalog-ingredients-search]', tab: 'ingredients' },
        ].forEach(function (item) {
            var el = qs(item.sel, root);
            if (!el) { return; }
            el.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') { return; }
                e.preventDefault();
                if (item.tab === 'products')    { fetchProducts(root, 1); }
                if (item.tab === 'ingredients') { fetchIngredients(root, 1); }
            });
        });

        var productCat   = qs('[data-catalog-products-category]', root);
        var productBrand = qs('[data-catalog-products-brand]', root);
        var ingCat       = qs('[data-catalog-ingredients-category]', root);
        if (productCat)   { productCat.addEventListener('change',   function () { fetchProducts(root, 1); }); }
        if (productBrand) { productBrand.addEventListener('change', function () { fetchProducts(root, 1); }); }
        if (ingCat)       { ingCat.addEventListener('change',       function () { fetchIngredients(root, 1); }); }
    }

    // ── INIT ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-catalog]');
        if (!root || !window.CCApi) { return; }

        loadFilters(root);
        fetchProducts(root, 1);
        bind(root);
    });
})(window, document);
