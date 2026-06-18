(function (window, document) {
    'use strict';

    var state = {
        products: [],
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
        var alert = qs('[data-barcodes-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-barcodes-message]', root);
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

    function productLabel(product) {
        var parts = [product.name];
        if (product.brand && product.brand.name) {
            parts.push(product.brand.name);
        }
        if (product.barcode) {
            parts.push(product.barcode);
        }
        return parts.filter(Boolean).join(' · ');
    }

    function fetchProducts(root) {
        var params = new URLSearchParams();
        var search = qs('[data-barcode-product-search]', root);

        params.set('per_page', '100');
        params.set('sort', 'name');
        params.set('order', 'asc');
        params.set('status', 'active');
        if (search && search.value) {
            params.set('search', search.value);
        }

        return window.CCApi.request(endpoint('/admin/products?' + params.toString()))
            .then(function (response) {
                state.products = response.data || [];
                renderProducts(root);
                renderProductSelects(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderProductSelects(root) {
        var selects = [
            qs('[data-barcode-product-select]', root),
            qs('[data-barcode-delete-product-select]', root),
        ];

        selects.forEach(function (select) {
            if (!select) {
                return;
            }
            var current = select.value;
            select.innerHTML = '<option value="">Producto</option>' + state.products.map(function (product) {
                return option(productLabel(product), product.id);
            }).join('');
            select.value = current;
        });
    }

    function renderProducts(root) {
        var target = qs('[data-barcode-products-list]', root);

        if (!state.products.length) {
            target.innerHTML = '<span class="muted">No hay productos para mostrar.</span>';
            return;
        }

        target.innerHTML = state.products.slice(0, 12).map(function (product) {
            return '<div class="line"><span>' + escapeHtml(product.name) + '</span><strong>#' + escapeHtml(product.id) + '</strong></div>';
        }).join('');
    }

    function searchBarcode(root) {
        clearMessage(root);
        var input = qs('[data-barcode-search-code]', root);
        var barcode = input.value.trim();
        var target = qs('[data-barcode-search-result]', root);

        if (!barcode) {
            showMessage(root, 'danger', 'Ingresa un codigo para buscar.');
            return Promise.resolve();
        }

        target.innerHTML = '<span class="muted">Buscando...</span>';

        return window.CCApi.request(endpoint('/products/barcode/' + encodeURIComponent(barcode)))
            .then(function (response) {
                renderProductResult(target, response.data);
            }).catch(function (error) {
                target.innerHTML = '<span class="muted">No se encontro producto para ese codigo.</span>';
                handleError(root, error);
            });
    }

    function renderProductResult(target, product) {
        if (!product) {
            target.innerHTML = '<span class="muted">Producto no disponible.</span>';
            return;
        }

        target.innerHTML =
            '<div class="line"><span>ID</span><strong>' + escapeHtml(product.id) + '</strong></div>' +
            '<div class="line"><span>Producto</span><strong>' + escapeHtml(product.name) + '</strong></div>' +
            '<div class="line"><span>Marca</span><strong>' + escapeHtml(product.brand && product.brand.name) + '</strong></div>' +
            '<div class="line"><span>Categoria</span><strong>' + escapeHtml(product.category && product.category.name) + '</strong></div>' +
            '<div class="line"><span>Ingrediente</span><strong>' + escapeHtml(product.ingredient && product.ingredient.name) + '</strong></div>' +
            '<div class="line"><span>Barcode</span><strong>' + escapeHtml(product.barcode) + '</strong></div>';
    }

    function createBarcode(root, form) {
        clearMessage(root);
        var productId = form.elements.product_id.value;
        var barcode = form.elements.barcode.value.trim();
        var target = qs('[data-barcode-created-result]', root);

        return window.CCApi.request(endpoint('/admin/products/' + encodeURIComponent(productId) + '/barcodes'), {
            method: 'POST',
            body: { barcode: barcode },
        }).then(function (response) {
            form.reset();
            showMessage(root, 'success', 'Codigo agregado.');
            target.innerHTML =
                '<div class="line"><span>ID barcode</span><strong>' + escapeHtml(response.data.id) + '</strong></div>' +
                '<div class="line"><span>Producto</span><strong>#' + escapeHtml(response.data.product_id) + '</strong></div>' +
                '<div class="line"><span>Codigo</span><strong>' + escapeHtml(response.data.barcode) + '</strong></div>';
            return fetchProducts(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteBarcode(root, form) {
        clearMessage(root);
        var productId = form.elements.product_id.value;
        var barcodeId = form.elements.barcode_id.value;

        if (!window.confirm('Desactivar este codigo de barras?')) {
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/products/' + encodeURIComponent(productId) + '/barcodes/' + encodeURIComponent(barcodeId)), {
            method: 'DELETE',
        }).then(function () {
            form.reset();
            showMessage(root, 'success', 'Codigo desactivado.');
            return fetchProducts(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        qs('[data-barcode-search-submit]', root).addEventListener('click', function () {
            searchBarcode(root);
        });
        qs('[data-barcode-search-code]', root).addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchBarcode(root);
            }
        });
        qs('[data-barcode-products-refresh]', root).addEventListener('click', function () {
            fetchProducts(root);
        });
        qs('[data-barcode-create-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            createBarcode(root, event.currentTarget);
        });
        qs('[data-barcode-delete-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            deleteBarcode(root, event.currentTarget);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-barcodes]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        fetchProducts(root);
    });
})(window, document);
