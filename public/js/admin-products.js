(function (window, document) {
    'use strict';

    var state = {
        products: [],
        brands: [],
        categories: [],
        ingredients: [],
        units: [],
        page: 1,
        lastPage: 1,
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
        var alert = qs('[data-products-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-products-message]', root);
        if (alert) {
            alert.textContent = '';
            alert.className = 'alert';
            alert.style.display = 'none';
        }
    }

    function handleError(root, error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        showMessage(root, 'danger', apiError.message || error.message || 'No se pudo completar la operacion.');
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function entityName(entity) {
        return entity ? entity.name : '-';
    }

    function unitLabel(unit) {
        if (!unit) {
            return '-';
        }
        return unit.name + (unit.symbol ? ' (' + unit.symbol + ')' : '');
    }

    function fetchLookups(root) {
        return Promise.all([
            window.CCApi.request(endpoint('/brands?per_page=100&sort=name&order=asc')),
            window.CCApi.request(endpoint('/product-categories')),
            window.CCApi.request(endpoint('/admin/ingredients?per_page=100&status=active&sort=name&order=asc')),
            window.CCApi.request(endpoint('/units?per_page=100&sort=name&order=asc')),
        ]).then(function (responses) {
            state.brands = responses[0].data || [];
            state.categories = flattenTree(responses[1].data || []);
            state.ingredients = responses[2].data || [];
            state.units = responses[3].data || [];
            renderLookups(root);
        }).catch(function (error) {
            handleError(root, error);
        });
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

    function renderLookups(root) {
        renderSelects(root, ['[data-products-brand]', '[data-product-brand-select]'], state.brands, ['Marca', 'Marca']);
        renderSelects(root, ['[data-products-category]', '[data-product-category-select]'], state.categories, ['Categoria', 'Categoria']);
        renderSelects(root, ['[data-products-ingredient]', '[data-product-ingredient-select]'], state.ingredients, ['Ingrediente', 'Ingrediente principal']);
        renderSelects(root, ['[data-product-unit-select]'], state.units, ['Unidad'], unitLabel);
    }

    function renderSelects(root, selectors, items, labels, labeler) {
        selectors.forEach(function (selector, index) {
            var select = qs(selector, root);
            if (!select) {
                return;
            }
            var current = select.value;
            var first = labels[index] || labels[0] || 'Seleccionar';
            select.innerHTML = '<option value="">' + first + '</option>' + items.map(function (item) {
                var label = labeler ? labeler(item) : ((item.level ? Array(item.level + 1).join('- ') : '') + item.name);
                return option(label, item.id);
            }).join('');
            select.value = current;
        });
    }

    function fetchProducts(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-products-search]', root);
        var brand = qs('[data-products-brand]', root);
        var category = qs('[data-products-category]', root);
        var ingredient = qs('[data-products-ingredient]', root);
        var status = qs('[data-products-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'name');
        params.set('order', 'asc');

        if (search && search.value) {
            params.set('search', search.value);
        }
        if (brand && brand.value) {
            params.set('brand_id', brand.value);
        }
        if (category && category.value) {
            params.set('category_id', category.value);
        }
        if (ingredient && ingredient.value) {
            params.set('ingredient_id', ingredient.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/products?' + params.toString()))
            .then(function (response) {
                state.products = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderProducts(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderProducts(root, meta) {
        var body = qs('[data-products-body]', root);
        var count = qs('[data-products-count]', root);
        var page = qs('[data-products-page]', root);

        if (count) {
            count.textContent = (meta.total || state.products.length) + ' productos';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }
        if (!state.products.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay productos cargados.</td></tr>';
            return;
        }

        body.innerHTML = state.products.map(function (product) {
            var action = product.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-product-delete="' + product.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-product-restore="' + product.id + '">Restaurar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(product.name) + '</strong><br><span class="muted">' + escapeHtml(product.normalized_name) + '</span></td>' +
                '<td>' + escapeHtml(entityName(product.brand)) + '</td>' +
                '<td>' + escapeHtml(entityName(product.category)) + '</td>' +
                '<td>' + escapeHtml(entityName(product.ingredient)) + '</td>' +
                '<td>' + escapeHtml(product.barcode) + '</td>' +
                '<td>' + escapeHtml(product.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-product-edit="' + product.id + '">Editar</button> ' +
                '<button type="button" class="btn-ghost btn-sm" data-product-view="' + product.id + '">Detalle</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function payload(form) {
        var numeric = ['brand_id', 'category_id', 'ingredient_id', 'default_unit_id'];
        var decimal = ['net_quantity'];
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id' || value === '') {
                return;
            }
            if (numeric.indexOf(key) !== -1) {
                data[key] = parseInt(value, 10);
            } else if (decimal.indexOf(key) !== -1) {
                data[key] = Number(value);
            } else {
                data[key] = value;
            }
        });
        return data;
    }

    function resetForm(root) {
        var form = qs('[data-product-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-product-form-title]', root).textContent = 'Nuevo producto';
    }

    function fillForm(root, product) {
        var form = qs('[data-product-form]', root);
        form.elements.id.value = product.id;
        form.elements.name.value = product.name || '';
        form.elements.brand_id.value = product.brand_id || '';
        form.elements.category_id.value = product.category_id || '';
        form.elements.ingredient_id.value = product.ingredient_id || '';
        form.elements.barcode.value = product.barcode || '';
        form.elements.net_quantity.value = product.net_quantity || '';
        form.elements.default_unit_id.value = product.default_unit_id || '';
        form.elements.description.value = product.description || '';
        form.elements.status.value = product.status || 'active';
        qs('[data-product-form-title]', root).textContent = 'Editar producto #' + product.id;
    }

    function saveProduct(root, form) {
        clearMessage(root);
        var id = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path = id ? '/admin/products/' + encodeURIComponent(id) : '/admin/products';

        return window.CCApi.request(endpoint(path), {
            method: method,
            body: payload(form),
        }).then(function (response) {
            showMessage(root, 'success', id ? 'Producto actualizado.' : 'Producto creado.');
            resetForm(root);
            return fetchProducts(root, state.page).then(function () {
                if (response.data && response.data.id) {
                    showProductDetails(root, response.data.id);
                }
            });
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteProduct(root, id) {
        if (!window.confirm('Eliminar este producto?')) {
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/products/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Producto eliminado.');
            return fetchProducts(root, state.page);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function restoreProduct(root, id) {
        if (!id) {
            showMessage(root, 'danger', 'Indica el ID del producto a restaurar.');
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/products/' + encodeURIComponent(id) + '/restore'), {
            method: 'PATCH',
        }).then(function () {
            showMessage(root, 'success', 'Producto restaurado.');
            var input = qs('[data-product-restore-id]', root);
            if (input) {
                input.value = '';
            }
            return fetchProducts(root, state.page);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function showProductDetails(root, id) {
        qs('[data-product-detail-id]', root).value = id;
        var imageProductInput = qs('[data-product-image-product-id]', root);
        if (imageProductInput) {
            imageProductInput.value = id;
        }
        return Promise.all([
            window.CCApi.request(endpoint('/products/' + encodeURIComponent(id))),
            window.CCApi.request(endpoint('/products/' + encodeURIComponent(id) + '/nutrition')),
            window.CCApi.request(endpoint('/products/' + encodeURIComponent(id) + '/prices')),
            window.CCApi.request(endpoint('/products/' + encodeURIComponent(id) + '/alternatives')),
        ]).then(function (responses) {
            renderDetail(root, responses[0].data);
            renderNutrition(root, responses[1].data || []);
            renderPrices(root, responses[2].data || []);
            renderAlternatives(root, responses[3].data || []);
            renderImages(root, responses[0].data && responses[0].data.images ? responses[0].data.images : []);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderDetail(root, product) {
        var target = qs('[data-product-detail]', root);
        if (!product) {
            target.innerHTML = '<span class="muted">Producto no disponible.</span>';
            return;
        }
        target.innerHTML =
            '<div class="line"><span>ID</span><strong>' + escapeHtml(product.id) + '</strong></div>' +
            '<div class="line"><span>Nombre</span><strong>' + escapeHtml(product.name) + '</strong></div>' +
            '<div class="line"><span>Marca</span><strong>' + escapeHtml(entityName(product.brand)) + '</strong></div>' +
            '<div class="line"><span>Categoria</span><strong>' + escapeHtml(entityName(product.category)) + '</strong></div>' +
            '<div class="line"><span>Ingrediente</span><strong>' + escapeHtml(entityName(product.ingredient)) + '</strong></div>' +
            '<div class="line"><span>Cantidad</span><strong>' + escapeHtml(product.net_quantity) + ' ' + escapeHtml(product.unit && product.unit.symbol) + '</strong></div>' +
            '<div class="line"><span>Barcode</span><strong>' + escapeHtml(product.barcode) + '</strong></div>' +
            '<p class="muted">' + escapeHtml(product.description) + '</p>';
    }

    function renderNutrition(root, rows) {
        var target = qs('[data-product-nutrition]', root);
        if (!rows.length) {
            target.innerHTML = '<span class="muted">Sin datos nutricionales.</span>';
            return;
        }
        target.innerHTML = rows.map(function (row) {
            var nutrient = row.nutrient || {};
            var unit = nutrient.unit || {};
            return '<div class="line"><span>' + escapeHtml(nutrient.name || nutrient.code) + '</span><strong>' +
                escapeHtml(row.amount_per_100g) + ' ' + escapeHtml(unit.symbol || unit.code) + '</strong></div>';
        }).join('');
    }

    function renderPrices(root, rows) {
        var target = qs('[data-product-prices]', root);
        if (!rows.length) {
            target.innerHTML = '<span class="muted">Sin precios cargados.</span>';
            return;
        }
        target.innerHTML = rows.map(function (row) {
            var source = row.supermarket_product || {};
            var chain = source.chain || {};
            return '<div class="line"><span>' + escapeHtml(chain.name || source.source_name || row.source) + '</span><strong>' +
                escapeHtml(row.currency) + ' ' + escapeHtml(row.price) + '</strong></div>';
        }).join('');
    }

    function renderAlternatives(root, rows) {
        var target = qs('[data-product-alternatives]', root);
        if (!rows.length) {
            target.innerHTML = '<span class="muted">Sin alternativas activas.</span>';
            return;
        }
        target.innerHTML = rows.map(function (product) {
            return '<div class="line"><span>' + escapeHtml(product.name) + '</span><strong>' + escapeHtml(entityName(product.brand)) + '</strong></div>';
        }).join('');
    }

    function renderImages(root, rows) {
        var target = qs('[data-product-images]', root);
        if (!target) {
            return;
        }
        if (!rows.length) {
            target.innerHTML = '<span class="muted">Sin imagenes cargadas.</span>';
            return;
        }

        target.innerHTML = rows.map(function (image) {
            return '<div class="line" style="align-items:center">' +
                '<span style="display:flex;gap:10px;align-items:center;min-width:0">' +
                '<img src="' + escapeHtml(image.image_url) + '" alt="Imagen producto" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #edf1f4">' +
                '<span><strong>' + escapeHtml(image.source || 'manual') + '</strong><br><span class="muted">' + (image.is_primary ? 'Principal' : 'Secundaria') + '</span></span>' +
                '</span>' +
                '<button type="button" class="btn-ghost btn-sm" data-product-image-delete="' + escapeHtml(image.product_id) + ':' + escapeHtml(image.id) + '">Eliminar</button>' +
                '</div>';
        }).join('');
    }

    function uploadImage(root, form) {
        clearMessage(root);
        var productId = form.elements.product_id.value;
        var file = form.elements.image.files[0];
        var url = form.elements.url.value.trim();

        if (!productId) {
            showMessage(root, 'danger', 'Selecciona un producto.');
            return Promise.resolve();
        }

        if (!file && !url) {
            showMessage(root, 'danger', 'Carga un archivo o indica una URL.');
            return Promise.resolve();
        }

        var data = new FormData();
        if (file) {
            data.append('image', file);
        }
        if (url) {
            data.append('url', url);
        }
        if (form.elements.source.value.trim()) {
            data.append('source', form.elements.source.value.trim());
        }
        if (form.elements.is_primary.checked) {
            data.append('is_primary', '1');
        }

        return window.CCApi.request(endpoint('/admin/products/' + encodeURIComponent(productId) + '/images'), {
            method: 'POST',
            body: data,
        }).then(function () {
            showMessage(root, 'success', 'Imagen cargada.');
            form.reset();
            form.elements.product_id.value = productId;
            return showProductDetails(root, productId);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteImage(root, productId, imageId) {
        if (!window.confirm('Eliminar esta imagen?')) {
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/products/' + encodeURIComponent(productId) + '/images/' + encodeURIComponent(imageId)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Imagen eliminada.');
            return showProductDetails(root, productId);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        qs('[data-products-refresh]', root).addEventListener('click', function () {
            fetchProducts(root, 1);
        });
        qs('[data-products-prev]', root).addEventListener('click', function () {
            fetchProducts(root, Math.max(1, state.page - 1));
        });
        qs('[data-products-next]', root).addEventListener('click', function () {
            fetchProducts(root, Math.min(state.lastPage, state.page + 1));
        });
        qs('[data-product-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-product-restore-submit]', root).addEventListener('click', function () {
            restoreProduct(root, qs('[data-product-restore-id]', root).value);
        });
        qs('[data-product-detail-refresh]', root).addEventListener('click', function () {
            showProductDetails(root, qs('[data-product-detail-id]', root).value);
        });
        qs('[data-product-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            saveProduct(root, event.currentTarget);
        });
        qs('[data-product-image-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            uploadImage(root, event.currentTarget);
        });
        qs('[data-product-images]', root).addEventListener('click', function (event) {
            var remove = event.target.closest('[data-product-image-delete]');
            if (!remove) {
                return;
            }

            var parts = remove.getAttribute('data-product-image-delete').split(':');
            deleteImage(root, parts[0], parts[1]);
        });
        qs('[data-products-body]', root).addEventListener('click', function (event) {
            var edit = event.target.closest('[data-product-edit]');
            var view = event.target.closest('[data-product-view]');
            var remove = event.target.closest('[data-product-delete]');
            var restore = event.target.closest('[data-product-restore]');

            if (edit) {
                var id = parseInt(edit.getAttribute('data-product-edit'), 10);
                var product = state.products.find(function (item) {
                    return item.id === id;
                });
                if (product) {
                    fillForm(root, product);
                }
            }
            if (view) {
                showProductDetails(root, view.getAttribute('data-product-view'));
            }
            if (remove) {
                deleteProduct(root, remove.getAttribute('data-product-delete'));
            }
            if (restore) {
                restoreProduct(root, restore.getAttribute('data-product-restore'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-products]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        resetForm(root);
        fetchLookups(root).then(function () {
            return fetchProducts(root, 1);
        });

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                resetForm(root);
                var form = qs('[data-product-form]', root);
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    var first = form.querySelector('input:not([type=hidden]),select,textarea');
                    if (first) { first.focus(); }
                }
            });
        }

        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showMessage(root, 'info', 'La importacion masiva de productos no esta disponible en esta version. Usa "Nuevo producto" para agregar productos uno a uno.');
            });
        }
    });
})(window, document);
