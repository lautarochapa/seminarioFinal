(function (window, document) {
    'use strict';

    var API_BASE = '/api/v1';

    var cameraStream = null;
    var barcodeDetector = null;
    var detecting = false;

    var state = {
        groups: [],
        currentGroupId: null,
        locations: [],
        lastBarcode: '',
        lastProduct: null,
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
        return API_BASE + path;
    }

    function showMessage(root, type, message) {
        var el = qs('[data-barcode-message]', root);
        if (!el) {
            return;
        }
        el.textContent = message;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-barcode-message]', root);
        if (!el) {
            return;
        }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function apiErrorMessage(error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        if (error.status === 401) {
            return 'La sesion vencio. Inicia sesion nuevamente.';
        }
        if (error.status === 403) {
            return 'No tenes permiso para operar sobre ese grupo.';
        }
        if (error.status === 404) {
            return apiError.message || 'No se encontro el producto o la ubicacion.';
        }
        if (error.status === 422) {
            return apiError.message || 'Revisa el codigo, ubicacion y cantidad.';
        }
        return apiError.message || error.message || 'No se pudo completar la operacion.';
    }

    function primaryImageUrl(images) {
        var imgs = (images || []).filter(function (image) {
            return image.image_url;
        });
        var primary = imgs.filter(function (image) {
            return image.is_primary;
        })[0] || imgs[0];
        return primary ? primary.image_url : null;
    }

    function renderResult(root, product) {
        var el = qs('[data-barcode-result]', root);
        if (!el) {
            return;
        }
        if (!product) {
            el.innerHTML = '<p class="muted">Producto no disponible.</p>';
            return;
        }
        var imgUrl = primaryImageUrl(product.images);
        var imgHtml = imgUrl
            ? '<img src="' + escapeHtml(imgUrl) + '" loading="lazy" alt="' + escapeHtml(product.name) + '"' +
                ' style="width:100%;max-height:180px;object-fit:contain;background:#f0f0f0;border-radius:8px;display:block;margin-bottom:12px">'
            : '';
        el.innerHTML =
            imgHtml +
            '<div class="table-line"><span class="muted">Producto</span><strong>' + escapeHtml(product.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Marca</span><strong>' + escapeHtml(product.brand && product.brand.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Categoria</span><strong>' + escapeHtml(product.category && product.category.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Ingrediente</span><strong>' + escapeHtml(product.ingredient && product.ingredient.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Codigo</span><strong style="font-family:monospace">' + escapeHtml(product.barcode) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Cantidad neta</span><strong>' +
                escapeHtml(product.net_quantity) + ' ' + escapeHtml(product.unit && product.unit.symbol) +
            '</strong></div>';
    }

    function renderStockResult(root, item) {
        var target = qs('[data-barcode-stock-result]', root);
        if (!target) {
            return;
        }
        if (!item) {
            target.innerHTML = '<p class="muted">Busca o escanea un producto, elegi ubicacion y confirma la carga.</p>';
            return;
        }
        var product = item.product || {};
        var ingredient = product.ingredient || {};
        var location = item.location || {};
        var unit = item.unit || {};
        target.innerHTML =
            '<div class="table-line"><span class="muted">Producto</span><strong>' + escapeHtml(product.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Ingrediente</span><strong>' + escapeHtml(ingredient.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Ubicacion</span><strong>' + escapeHtml(location.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Cantidad actual</span><strong>' +
                escapeHtml(item.quantity) + ' ' + escapeHtml(unit.symbol || unit.code || unit.name) +
            '</strong></div>' +
            '<div style="margin-top:12px"><a href="/web/stock" class="btn-secondary-web btn-sm">Ver stock</a></div>';
    }

    function renderGroups(root) {
        var select = qs('[data-barcode-group-select]', root);
        if (!select) {
            return;
        }
        if (!state.groups.length) {
            select.innerHTML = '<option value="">Sin grupo familiar activo</option>';
            return;
        }
        select.innerHTML = state.groups.map(function (group) {
            return '<option value="' + group.id + '"' + (String(group.id) === String(state.currentGroupId) ? ' selected' : '') + '>' +
                escapeHtml(group.name) +
            '</option>';
        }).join('');
    }

    function renderLocations(root) {
        var select = qs('[data-barcode-location-select]', root);
        if (!select) {
            return;
        }
        if (!state.locations.length) {
            select.innerHTML = '<option value="">Sin ubicaciones activas</option>';
            return;
        }
        select.innerHTML = '<option value="">Selecciona ubicacion</option>' + state.locations.map(function (location) {
            return '<option value="' + location.id + '">' + escapeHtml(location.name) +
                (location.type ? ' (' + escapeHtml(location.type) + ')' : '') +
            '</option>';
        }).join('');
    }

    function loadGroups(root) {
        return window.CCApi.request(endpoint('/family-groups'))
            .then(function (response) {
                state.groups = response.data || [];
                state.currentGroupId = state.groups.length ? state.groups[0].id : null;
                renderGroups(root);
                return loadLocations(root);
            })
            .catch(function (error) {
                state.groups = [];
                state.currentGroupId = null;
                renderGroups(root);
                renderLocations(root);
                showMessage(root, 'danger', apiErrorMessage(error));
            });
    }

    function loadLocations(root) {
        if (!state.currentGroupId) {
            state.locations = [];
            renderLocations(root);
            return Promise.resolve();
        }
        return window.CCApi.request(endpoint('/family-groups/' + encodeURIComponent(state.currentGroupId) + '/stock-locations?status=active&per_page=100'))
            .then(function (response) {
                state.locations = response.data || [];
                renderLocations(root);
            })
            .catch(function (error) {
                state.locations = [];
                renderLocations(root);
                showMessage(root, 'danger', apiErrorMessage(error));
            });
    }

    function searchBarcode(root) {
        clearMessage(root);
        var input = qs('[data-barcode-code-input]', root);
        var code = input ? input.value.trim() : '';
        var resultEl = qs('[data-barcode-result]', root);
        var submitBtn = qs('[data-barcode-search-submit]', root);

        if (!code) {
            showMessage(root, 'danger', 'Ingresa un codigo de barras para buscar.');
            return;
        }

        state.lastBarcode = code;
        state.lastProduct = null;
        renderStockResult(root, null);

        if (resultEl) {
            resultEl.innerHTML = '<p class="muted">Buscando...</p>';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        window.CCApi.request(endpoint('/products/barcode/' + encodeURIComponent(code)))
            .then(function (response) {
                state.lastProduct = response.data || null;
                renderResult(root, state.lastProduct);
            })
            .catch(function (error) {
                if (resultEl && error.status === 404) {
                    resultEl.innerHTML = '<p class="muted">No se encontro ningun producto con el codigo <strong>' +
                        escapeHtml(code) + '</strong>.</p>';
                } else if (resultEl) {
                    resultEl.innerHTML = '<p class="muted">' + escapeHtml(apiErrorMessage(error)) + '</p>';
                }
                showMessage(root, error.status === 404 ? 'warning' : 'danger', apiErrorMessage(error));
            })
            .then(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
    }

    function addToStock(root) {
        clearMessage(root);
        var location = qs('[data-barcode-location-select]', root);
        var quantity = qs('[data-barcode-quantity]', root);
        var button = qs('[data-barcode-stock-submit]', root);
        var code = state.lastBarcode || (qs('[data-barcode-code-input]', root) ? qs('[data-barcode-code-input]', root).value.trim() : '');

        if (!state.currentGroupId) {
            showMessage(root, 'danger', 'Selecciona un grupo familiar.');
            return;
        }
        if (!code) {
            showMessage(root, 'danger', 'Primero ingresa o escanea un codigo.');
            return;
        }
        if (!location || !location.value) {
            showMessage(root, 'danger', 'Selecciona una ubicacion de stock.');
            return;
        }
        if (!quantity || Number(quantity.value) <= 0) {
            showMessage(root, 'danger', 'La cantidad debe ser mayor que cero.');
            return;
        }

        if (button) {
            button.disabled = true;
        }

        window.CCApi.request(endpoint('/family-groups/' + encodeURIComponent(state.currentGroupId) + '/stock/scan'), {
            method: 'POST',
            body: {
                barcode: code,
                stock_location_id: Number(location.value),
                quantity: Number(quantity.value),
            },
        }).then(function (response) {
            renderStockResult(root, response.data || null);
            showMessage(root, 'success', 'Stock actualizado.');
        }).catch(function (error) {
            renderStockResult(root, null);
            showMessage(root, 'danger', apiErrorMessage(error));
        }).then(function () {
            if (button) {
                button.disabled = false;
            }
        });
    }

    function isCameraSupported() {
        return !!(window.BarcodeDetector && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    function startCamera(root) {
        if (!isCameraSupported()) {
            showMessage(root, 'danger', 'Tu navegador no soporta lectura de codigos desde la camara. Usa el ingreso manual.');
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                cameraStream = stream;
                barcodeDetector = new window.BarcodeDetector({
                    formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code'],
                });
                var video = qs('[data-barcode-video]', root);
                if (video) {
                    video.srcObject = stream;
                }
                var container = qs('[data-barcode-camera-container]', root);
                if (container) {
                    container.style.display = 'block';
                }
                detecting = true;
                window.requestAnimationFrame(function () {
                    detectLoop(root);
                });
            })
            .catch(function () {
                showMessage(root, 'danger', 'No se pudo acceder a la camara. Verifica los permisos del navegador.');
            });
    }

    function detectLoop(root) {
        if (!detecting || !cameraStream || !barcodeDetector) {
            return;
        }
        var video = qs('[data-barcode-video]', root);
        if (!video || video.readyState < 2) {
            if (detecting) {
                window.requestAnimationFrame(function () {
                    detectLoop(root);
                });
            }
            return;
        }
        barcodeDetector.detect(video)
            .then(function (barcodes) {
                if (barcodes.length > 0) {
                    var code = barcodes[0].rawValue;
                    var input = qs('[data-barcode-code-input]', root);
                    if (input) {
                        input.value = code;
                    }
                    stopCamera(root);
                    searchBarcode(root);
                    return;
                }
                if (detecting) {
                    window.requestAnimationFrame(function () {
                        detectLoop(root);
                    });
                }
            })
            .catch(function () {
                if (detecting) {
                    window.requestAnimationFrame(function () {
                        detectLoop(root);
                    });
                }
            });
    }

    function stopCamera(root) {
        detecting = false;
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) {
                track.stop();
            });
            cameraStream = null;
        }
        barcodeDetector = null;
        var video = qs('[data-barcode-video]', root);
        if (video) {
            video.srcObject = null;
        }
        var container = qs('[data-barcode-camera-container]', root);
        if (container) {
            container.style.display = 'none';
        }
    }

    function bind(root) {
        var submitBtn = qs('[data-barcode-search-submit]', root);
        var input = qs('[data-barcode-code-input]', root);
        var cameraToggle = qs('[data-barcode-camera-toggle]', root);
        var cameraStop = qs('[data-barcode-camera-stop]', root);
        var groupSelect = qs('[data-barcode-group-select]', root);
        var stockSubmit = qs('[data-barcode-stock-submit]', root);

        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                searchBarcode(root);
            });
        }
        if (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchBarcode(root);
                }
            });
        }
        if (cameraToggle) {
            if (!isCameraSupported()) {
                cameraToggle.style.display = 'none';
            } else {
                cameraToggle.addEventListener('click', function () {
                    startCamera(root);
                });
            }
        }
        if (cameraStop) {
            cameraStop.addEventListener('click', function () {
                stopCamera(root);
            });
        }
        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                state.currentGroupId = groupSelect.value || null;
                loadLocations(root);
            });
        }
        if (stockSubmit) {
            stockSubmit.addEventListener('click', function () {
                addToStock(root);
            });
        }
    }

    window.addEventListener('beforeunload', function () {
        detecting = false;
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) {
                track.stop();
            });
            cameraStream = null;
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            var root = qs('[data-user-barcode]');
            if (root) {
                stopCamera(root);
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-barcode]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
        loadGroups(root);
    });
})(window, document);
