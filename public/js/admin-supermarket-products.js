(function (window, document) {
    'use strict';

    var state = {
        mappings: [],
        products: [],
        chains: [],
        branches: [],
        page: 1,
        lastPage: 1,
        selectedMappingId: null,
        pricePage: 1,
        priceLastPage: 1,
        priceRequestSeq: 0,
        savingPrice: false,
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
        var alert = qs('[data-supermarket-products-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-supermarket-products-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = '';
        alert.className = 'alert';
        alert.style.display = 'none';
    }

    function handleError(root, error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        showMessage(root, 'danger', apiError.message || error.message || 'No se pudo completar la operacion.');
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function productName(product) {
        return product && product.name ? product.name : 'Producto #' + text(product && product.id);
    }

    function branchName(branch) {
        if (!branch) {
            return '-';
        }
        var chain = branch.chain && branch.chain.name ? branch.chain.name + ' - ' : '';
        return chain + branch.name;
    }

    function currentPrice(row) {
        if (!row || !row.current_price) {
            return '<span class="muted">Sin precio</span>';
        }
        return '<strong>' + escapeHtml(row.current_price.currency || 'ARS') + ' ' + escapeHtml(row.current_price.price) + '</strong>';
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

    function linkHtml(url) {
        if (!url) {
            return '';
        }
        return '<br><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" style="font-size:12px">Abrir fuente</a>';
    }

    function filteredBranches(chainId) {
        return state.branches.filter(function (branch) {
            return !chainId || String(branch.supermarket_chain_id || (branch.chain && branch.chain.id)) === String(chainId);
        });
    }

    function renderBranchSelect(select, chainId, firstLabel) {
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '<option value="">' + firstLabel + '</option>' + filteredBranches(chainId).map(function (branch) {
            return option(branchName(branch), branch.id);
        }).join('');
        select.value = current;
    }

    function renderLookups(root) {
        var productOptions = state.products.map(function (product) {
            return option(productName(product), product.id);
        }).join('');
        [
            '[data-sp-filter-product]',
            '[data-sp-product-select]',
            '[data-sp-price-product]',
            '[data-sp-best-product]',
        ].forEach(function (selector) {
            var select = qs(selector, root);
            if (select) {
                var current = select.value;
                var label = selector === '[data-sp-filter-product]' ? 'Producto' : 'Producto interno';
                select.innerHTML = '<option value="">' + label + '</option>' + productOptions;
                select.value = current;
            }
        });

        var chainOptions = state.chains.map(function (chain) {
            return option(chain.name, chain.id);
        }).join('');
        [
            '[data-sp-filter-chain]',
            '[data-sp-chain-select]',
            '[data-sp-branch-products-chain]',
        ].forEach(function (selector) {
            var select = qs(selector, root);
            if (select) {
                var current = select.value;
                select.innerHTML = '<option value="">Cadena</option>' + chainOptions;
                select.value = current;
            }
        });

        renderBranchSelect(qs('[data-sp-filter-branch]', root), qs('[data-sp-filter-chain]', root).value, 'Sucursal');
        renderBranchSelect(qs('[data-sp-branch-select]', root), qs('[data-sp-chain-select]', root).value, 'Sucursal');
        renderBranchSelect(qs('[data-sp-branch-products-branch]', root), qs('[data-sp-branch-products-chain]', root).value, 'Sucursal');
    }

    function loadLookups(root) {
        return Promise.all([
            window.CCApi.request(endpoint('/admin/products?per_page=100&status=active&sort=name&order=asc')),
            window.CCApi.request(endpoint('/supermarkets?per_page=100&sort=name&order=asc')),
            window.CCApi.request(endpoint('/admin/supermarket-branches?per_page=100&status=active')),
        ]).then(function (responses) {
            state.products = responses[0].data || [];
            state.chains = responses[1].data || [];
            state.branches = responses[2].data || [];
            renderLookups(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function fetchMappings(root, page) {
        clearMessage(root);
        var params = new URLSearchParams();
        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');

        var search = qs('[data-sp-search]', root);
        var product = qs('[data-sp-filter-product]', root);
        var chain = qs('[data-sp-filter-chain]', root);
        var branch = qs('[data-sp-filter-branch]', root);
        var status = qs('[data-sp-filter-status]', root);

        if (search && search.value.trim()) {
            params.set('search', search.value.trim());
        }
        if (product && product.value) {
            params.set('product_id', product.value);
        }
        if (chain && chain.value) {
            params.set('chain_id', chain.value);
        }
        if (branch && branch.value) {
            params.set('branch_id', branch.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        qs('[data-sp-body]', root).innerHTML = '<tr><td colspan="6" class="muted">Cargando...</td></tr>';

        return window.CCApi.request(endpoint('/admin/supermarket-products?' + params.toString()))
            .then(function (response) {
                state.mappings = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderMappings(root, response.meta || {});
            }).catch(function (error) {
                qs('[data-sp-body]', root).innerHTML = '<tr><td colspan="6" class="muted">Error al cargar mapeos.</td></tr>';
                handleError(root, error);
            });
    }

    function renderMappings(root, meta) {
        var body = qs('[data-sp-body]', root);
        qs('[data-sp-count]', root).textContent = (meta.total || state.mappings.length) + ' mapeos';
        qs('[data-sp-page]', root).textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);

        if (!state.mappings.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay mapeos para los filtros aplicados.</td></tr>';
            return;
        }

        body.innerHTML = state.mappings.map(function (row) {
            var action = row.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-sp-delete="' + row.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-sp-restore-row="' + row.id + '">Restaurar</button>';
            return '<tr>' +
                '<td><strong>' + escapeHtml(productName(row.product)) + '</strong><br><span class="muted">#' + escapeHtml(row.id) + '</span></td>' +
                '<td>' + escapeHtml(branchName(row.branch)) + '</td>' +
                '<td><strong>' + escapeHtml(row.external_sku) + '</strong><br><span class="muted">' + escapeHtml(row.source_name) + '</span>' + linkHtml(row.source_url) + '</td>' +
                '<td>' + currentPrice(row) + '</td>' +
                '<td>' + escapeHtml(row.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-sp-edit="' + row.id + '">Editar</button> <button type="button" class="btn-ghost btn-sm" data-sp-prices-row="' + row.id + '">Precios</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function setPriceFormEnabled(root, enabled) {
        var form = qs('[data-sp-price-form]', root);
        if (!form) {
            return;
        }
        Array.from(form.elements).forEach(function (element) {
            element.disabled = !enabled || state.savingPrice;
        });
    }

    function resetPricePanel(root) {
        state.selectedMappingId = null;
        state.pricePage = 1;
        state.priceLastPage = 1;
        state.priceRequestSeq += 1;
        qs('[data-sp-current-price]', root).innerHTML = '<span class="muted">Selecciona un mapeo para cargar precios.</span>';
        qs('[data-sp-price-history]', root).innerHTML = '<span class="muted">Selecciona un mapeo.</span>';
        qs('[data-sp-price-page]', root).textContent = 'Pagina 1';
        qs('[data-sp-price-form]', root).reset();
        setPriceFormEnabled(root, false);
    }

    function resetForm(root) {
        var form = qs('[data-sp-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.product_id.disabled = false;
        qs('[data-sp-form-title]', root).textContent = 'Nuevo mapeo';
        qs('[data-sp-submit]', root).textContent = 'Guardar mapeo';
        renderBranchSelect(qs('[data-sp-branch-select]', root), '', 'Sucursal');
        resetPricePanel(root);
    }

    function fillForm(root, row) {
        var form = qs('[data-sp-form]', root);
        form.elements.id.value = row.id;
        form.elements.product_id.value = row.product && row.product.id ? row.product.id : '';
        form.elements.product_id.disabled = true;
        form.elements.supermarket_chain_id.value = row.branch && row.branch.chain ? row.branch.chain.id : '';
        renderBranchSelect(qs('[data-sp-branch-select]', root), form.elements.supermarket_chain_id.value, 'Sucursal');
        form.elements.supermarket_branch_id.value = row.branch ? row.branch.id : '';
        form.elements.external_sku.value = row.external_sku || '';
        form.elements.source_url.value = row.source_url || '';
        form.elements.source_name.value = row.source_name || '';
        form.elements.last_scraped_at.value = row.last_scraped_at ? String(row.last_scraped_at).slice(0, 16) : '';
        qs('[data-sp-form-title]', root).textContent = 'Editar mapeo #' + row.id;
        qs('[data-sp-submit]', root).textContent = 'Guardar cambios';
        selectPriceMapping(root, row);
    }

    function payload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id' || key === 'supermarket_chain_id' || value === '') {
                return;
            }
            if (key === 'product_id' || key === 'supermarket_branch_id') {
                data[key] = parseInt(value, 10);
            } else {
                data[key] = value;
            }
        });
        return data;
    }

    function saveMapping(root, form) {
        clearMessage(root);
        var id = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path = id ? '/admin/supermarket-products/' + encodeURIComponent(id) : '/admin/supermarket-products';

        return window.CCApi.request(endpoint(path), {
            method: method,
            body: payload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Mapeo actualizado.' : 'Mapeo creado.');
            resetForm(root);
            return fetchMappings(root, state.page);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteMapping(root, id) {
        if (!window.confirm('Eliminar este mapeo?')) {
            return Promise.resolve();
        }
        return window.CCApi.request(endpoint('/admin/supermarket-products/' + encodeURIComponent(id)), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Mapeo eliminado.');
                return fetchMappings(root, state.page);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function restoreMapping(root, id) {
        if (!id) {
            showMessage(root, 'danger', 'No se encontro el mapeo.');
            return Promise.resolve();
        }
        return window.CCApi.request(endpoint('/admin/supermarket-products/' + encodeURIComponent(id) + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Mapeo restaurado.');
                return fetchMappings(root, state.page);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderCurrentPrice(root, row, prices) {
        var target = qs('[data-sp-current-price]', root);
        var current = prices.find(function (price) { return price.is_current; });
        if (!current && row && row.current_price) {
            target.innerHTML = '<div class="line"><span>Precio actual</span>' + currentPrice(row) + '</div>';
            return;
        }
        if (!current) {
            target.innerHTML = '<span class="muted">Sin precio actual.</span>';
            return;
        }
        target.innerHTML =
            '<div class="line"><span>Precio actual</span><strong>' + escapeHtml(current.currency || 'ARS') + ' ' + escapeHtml(current.price) + '</strong></div>' +
            '<div class="line"><span>Fecha</span><span>' + escapeHtml(dateLabel(current.captured_at || current.valid_from || current.created_at)) + '</span></div>' +
            '<div class="line"><span>Origen</span><span>' + escapeHtml(current.source) + '</span></div>';
    }

    function renderPriceHistory(root, rows, meta) {
        var target = qs('[data-sp-price-history]', root);
        state.priceLastPage = meta && meta.last_page ? meta.last_page : 1;
        qs('[data-sp-price-page]', root).textContent = 'Pagina ' + (meta && meta.current_page ? meta.current_page : state.pricePage) + ' de ' + state.priceLastPage;
        if (!rows.length) {
            target.innerHTML = '<span class="muted">Sin precios historicos.</span>';
            return;
        }
        target.innerHTML = '<div style="overflow:auto"><table class="admin-table"><thead><tr>' +
            '<th>Precio</th><th>Fecha</th><th>Origen</th><th>Vigencia</th><th>Estado</th>' +
            '</tr></thead><tbody>' + rows.map(function (price) {
                var validity = price.valid_to ? 'Hasta ' + dateLabel(price.valid_to) : 'Actual';
                return '<tr>' +
                    '<td><strong>' + escapeHtml(price.currency || 'ARS') + ' ' + escapeHtml(price.price) + '</strong></td>' +
                    '<td>' + escapeHtml(dateLabel(price.captured_at || price.created_at)) + '</td>' +
                    '<td>' + escapeHtml(price.source) + '</td>' +
                    '<td>' + escapeHtml(validity) + '</td>' +
                    '<td>' + escapeHtml(price.status) + '</td>' +
                    '</tr>';
            }).join('') + '</tbody></table></div>';
    }

    function loadPriceHistory(root, page) {
        if (!state.selectedMappingId) {
            resetPricePanel(root);
            return Promise.resolve();
        }
        var requestSeq = ++state.priceRequestSeq;
        state.pricePage = page || state.pricePage || 1;
        qs('[data-sp-price-history]', root).innerHTML = '<span class="muted">Cargando historial...</span>';

        return window.CCApi.request(endpoint('/admin/supermarket-products/' + encodeURIComponent(state.selectedMappingId) + '/prices?page=' + state.pricePage + '&per_page=10'))
            .then(function (response) {
                if (requestSeq !== state.priceRequestSeq) {
                    return;
                }
                var rows = response.data || [];
                var row = state.mappings.find(function (item) { return String(item.id) === String(state.selectedMappingId); });
                renderCurrentPrice(root, row, rows);
                renderPriceHistory(root, rows, response.meta || {});
                setPriceFormEnabled(root, true);
            }).catch(function (error) {
                if (requestSeq !== state.priceRequestSeq) {
                    return;
                }
                qs('[data-sp-price-history]', root).innerHTML = '<span class="muted">No se pudo cargar el historial.</span>';
                setPriceFormEnabled(root, false);
                handleError(root, error);
            });
    }

    function selectPriceMapping(root, row) {
        state.selectedMappingId = row.id;
        state.pricePage = 1;
        qs('[data-sp-current-price]', root).innerHTML = '<span class="muted">Cargando precio actual...</span>';
        qs('[data-sp-price-form]', root).reset();
        setPriceFormEnabled(root, true);
        return loadPriceHistory(root, 1);
    }

    function savePrice(root, form) {
        if (!state.selectedMappingId || state.savingPrice) {
            return Promise.resolve();
        }
        clearMessage(root);
        var price = form.elements.price.value;
        if (parseFloat(price) <= 0) {
            showMessage(root, 'danger', 'El precio debe ser mayor que cero.');
            return Promise.resolve();
        }
        state.savingPrice = true;
        setPriceFormEnabled(root, true);

        var data = {
            price: price,
            currency: form.elements.currency.value || 'ARS',
        };
        if (form.elements.captured_at.value) {
            data.captured_at = form.elements.captured_at.value;
        }

        return window.CCApi.request(endpoint('/admin/supermarket-products/' + encodeURIComponent(state.selectedMappingId) + '/prices'), {
            method: 'POST',
            body: data,
        }).then(function () {
            showMessage(root, 'success', 'Precio agregado.');
            form.reset();
            return loadPriceHistory(root, 1).then(function () {
                return fetchMappings(root, state.page);
            });
        }).catch(function (error) {
            handleError(root, error);
        }).finally(function () {
            state.savingPrice = false;
            setPriceFormEnabled(root, true);
        });
    }

    function renderPriceList(target, rows) {
        if (!rows.length) {
            target.innerHTML = '<span class="muted">Sin precios disponibles.</span>';
            return;
        }
        target.innerHTML = rows.map(function (row) {
            return '<div class="line"><span>' + escapeHtml(branchName(row.branch)) + '</span><strong>' +
                (row.current_price ? escapeHtml(row.current_price.currency || 'ARS') + ' ' + escapeHtml(row.current_price.price) : 'Sin precio') +
                '</strong></div>';
        }).join('');
    }

    function loadBranchProducts(root) {
        var branchId = qs('[data-sp-branch-products-branch]', root).value;
        var target = qs('[data-sp-branch-products]', root);
        if (!branchId) {
            target.innerHTML = '<span class="muted">Selecciona una sucursal.</span>';
            return Promise.resolve();
        }
        target.innerHTML = '<span class="muted">Cargando productos...</span>';
        return window.CCApi.request(endpoint('/supermarket-branches/' + encodeURIComponent(branchId) + '/products?per_page=20'))
            .then(function (response) {
                var rows = response.data || [];
                if (!rows.length) {
                    target.innerHTML = '<span class="muted">La sucursal no tiene productos mapeados.</span>';
                    return;
                }
                target.innerHTML = rows.map(function (row) {
                    return '<div class="line"><span>' + escapeHtml(productName(row.product)) + '</span>' + currentPrice(row) + '</div>';
                }).join('');
            }).catch(function (error) {
                target.innerHTML = '<span class="muted">No se pudieron cargar productos.</span>';
                handleError(root, error);
            });
    }

    function loadPrices(root) {
        var productId = qs('[data-sp-price-product]', root).value;
        var target = qs('[data-sp-prices]', root);
        if (!productId) {
            target.innerHTML = '<span class="muted">Selecciona un producto.</span>';
            return Promise.resolve();
        }
        target.innerHTML = '<span class="muted">Cargando precios...</span>';
        return window.CCApi.request(endpoint('/products/' + encodeURIComponent(productId) + '/supermarket-prices'))
            .then(function (response) {
                renderPriceList(target, response.data || []);
            }).catch(function (error) {
                target.innerHTML = '<span class="muted">No se pudieron cargar precios.</span>';
                handleError(root, error);
            });
    }

    function loadBestPrice(root) {
        var productId = qs('[data-sp-best-product]', root).value;
        var target = qs('[data-sp-best-price]', root);
        if (!productId) {
            target.innerHTML = '<span class="muted">Selecciona un producto.</span>';
            return Promise.resolve();
        }
        target.innerHTML = '<span class="muted">Buscando mejor precio...</span>';
        return window.CCApi.request(endpoint('/products/' + encodeURIComponent(productId) + '/best-price'))
            .then(function (response) {
                var row = response.data;
                target.innerHTML = '<div class="line"><span>' + escapeHtml(productName(row.product)) + '</span>' + currentPrice(row) + '</div>' +
                    '<div class="line"><span>Sucursal</span><strong>' + escapeHtml(branchName(row.branch)) + '</strong></div>' +
                    (row.source_url ? '<a href="' + escapeHtml(row.source_url) + '" target="_blank" rel="noopener noreferrer">Abrir fuente</a>' : '');
            }).catch(function (error) {
                target.innerHTML = '<span class="muted">Sin mejor precio disponible.</span>';
                if (error.status !== 404) {
                    handleError(root, error);
                }
            });
    }

    function bind(root) {
        qs('[data-sp-refresh]', root).addEventListener('click', function () {
            fetchMappings(root, 1);
        });
        qs('[data-sp-prev]', root).addEventListener('click', function () {
            fetchMappings(root, Math.max(1, state.page - 1));
        });
        qs('[data-sp-next]', root).addEventListener('click', function () {
            fetchMappings(root, Math.min(state.lastPage, state.page + 1));
        });
        qs('[data-sp-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            saveMapping(root, event.currentTarget);
        });
        qs('[data-sp-price-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            savePrice(root, event.currentTarget);
        });
        qs('[data-sp-price-prev]', root).addEventListener('click', function () {
            loadPriceHistory(root, Math.max(1, state.pricePage - 1));
        });
        qs('[data-sp-price-next]', root).addEventListener('click', function () {
            loadPriceHistory(root, Math.min(state.priceLastPage, state.pricePage + 1));
        });
        qs('[data-sp-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-sp-chain-select]', root).addEventListener('change', function () {
            renderBranchSelect(qs('[data-sp-branch-select]', root), this.value, 'Sucursal');
        });
        qs('[data-sp-filter-chain]', root).addEventListener('change', function () {
            renderBranchSelect(qs('[data-sp-filter-branch]', root), this.value, 'Sucursal');
            fetchMappings(root, 1);
        });
        qs('[data-sp-branch-products-chain]', root).addEventListener('change', function () {
            renderBranchSelect(qs('[data-sp-branch-products-branch]', root), this.value, 'Sucursal');
        });
        [
            '[data-sp-filter-product]',
            '[data-sp-filter-branch]',
            '[data-sp-filter-status]',
        ].forEach(function (selector) {
            qs(selector, root).addEventListener('change', function () {
                fetchMappings(root, 1);
            });
        });
        qs('[data-sp-search]', root).addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                fetchMappings(root, 1);
            }
        });
        qs('[data-sp-load-branch-products]', root).addEventListener('click', function () {
            loadBranchProducts(root);
        });
        qs('[data-sp-load-prices]', root).addEventListener('click', function () {
            loadPrices(root);
        });
        qs('[data-sp-load-best]', root).addEventListener('click', function () {
            loadBestPrice(root);
        });
        qs('[data-sp-body]', root).addEventListener('click', function (event) {
            var edit = event.target.closest('[data-sp-edit]');
            var prices = event.target.closest('[data-sp-prices-row]');
            var remove = event.target.closest('[data-sp-delete]');
            var restore = event.target.closest('[data-sp-restore-row]');

            if (edit) {
                var id = parseInt(edit.getAttribute('data-sp-edit'), 10);
                var row = state.mappings.find(function (item) { return item.id === id; });
                if (row) {
                    fillForm(root, row);
                }
            }
            if (prices) {
                var priceId = parseInt(prices.getAttribute('data-sp-prices-row'), 10);
                var priceRow = state.mappings.find(function (item) { return item.id === priceId; });
                if (priceRow) {
                    selectPriceMapping(root, priceRow);
                }
            }
            if (remove) {
                deleteMapping(root, remove.getAttribute('data-sp-delete'));
            }
            if (restore) {
                restoreMapping(root, restore.getAttribute('data-sp-restore-row'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-supermarket-products]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
        loadLookups(root).then(function () {
            resetForm(root);
            return fetchMappings(root, 1);
        });
    });
})(window, document);
