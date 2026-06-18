(function (window, document) {
    'use strict';

    var state = {
        products: [],
        stream: null,
        detector: null,
        scanning: false,
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

    function setCameraStatus(root, message) {
        var target = qs('[data-barcode-camera-status]', root);
        if (target) {
            target.textContent = message;
        }
    }

    function setCameraButtons(root, active) {
        var start = qs('[data-barcode-camera-start]', root);
        var stop = qs('[data-barcode-camera-stop]', root);
        if (start) {
            start.style.display = active ? 'none' : '';
        }
        if (stop) {
            stop.style.display = active ? '' : 'none';
        }
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
        var actions = qs('[data-barcode-next-actions]', root);

        if (!barcode) {
            showMessage(root, 'danger', 'Ingresa un codigo para buscar.');
            return Promise.resolve();
        }

        if (actions) {
            actions.style.display = 'none';
        }
        target.innerHTML = '<span class="muted">Buscando...</span>';

        return window.CCApi.request(endpoint('/products/barcode/' + encodeURIComponent(barcode)))
            .then(function (response) {
                renderProductResult(target, response.data);
                if (actions) {
                    actions.style.display = response.data ? 'flex' : 'none';
                }
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

    function stopCamera(root) {
        var video = qs('[data-barcode-video]', root);

        state.scanning = false;
        if (state.stream) {
            state.stream.getTracks().forEach(function (track) {
                track.stop();
            });
            state.stream = null;
        }
        if (video) {
            video.pause();
            video.srcObject = null;
            video.style.display = 'none';
        }
        setCameraButtons(root, false);
        setCameraStatus(root, 'Camara detenida.');
    }

    function scanFrame(root) {
        var video = qs('[data-barcode-video]', root);

        if (!state.scanning || !state.detector || !video) {
            return;
        }

        state.detector.detect(video).then(function (codes) {
            if (!state.scanning) {
                return;
            }

            if (codes && codes.length && codes[0].rawValue) {
                var input = qs('[data-barcode-search-code]', root);
                input.value = codes[0].rawValue;
                stopCamera(root);
                setCameraStatus(root, 'Codigo detectado. Buscando producto...');
                searchBarcode(root);
                return;
            }

            window.requestAnimationFrame(function () {
                scanFrame(root);
            });
        }).catch(function () {
            stopCamera(root);
            showMessage(root, 'danger', 'No se pudo leer el codigo con la camara.');
        });
    }

    function startCamera(root) {
        clearMessage(root);

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setCameraStatus(root, 'Este navegador no permite acceder a la camara. Usa la entrada manual.');
            return;
        }

        if (!window.BarcodeDetector) {
            setCameraStatus(root, 'Este navegador no soporta deteccion de codigos. Usa la entrada manual.');
            return;
        }

        var video = qs('[data-barcode-video]', root);
        state.detector = new window.BarcodeDetector({
            formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39'],
        });

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                state.stream = stream;
                state.scanning = true;
                video.srcObject = stream;
                video.style.display = 'block';
                setCameraButtons(root, true);
                setCameraStatus(root, 'Apunta la camara al codigo de barras.');
                return video.play();
            })
            .then(function () {
                scanFrame(root);
            })
            .catch(function () {
                stopCamera(root);
                setCameraStatus(root, 'No se pudo iniciar la camara. Usa la entrada manual.');
            });
    }

    function bind(root) {
        qs('[data-barcode-search-submit]', root).addEventListener('click', function () {
            searchBarcode(root);
        });
        qs('[data-barcode-camera-start]', root).addEventListener('click', function () {
            startCamera(root);
        });
        qs('[data-barcode-camera-stop]', root).addEventListener('click', function () {
            stopCamera(root);
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
        window.addEventListener('pagehide', function () {
            stopCamera(root);
        });
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopCamera(root);
            }
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
