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
        units: [],
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
            '<div class="table-line"><span class="muted">Stock</span><strong>' + escapeHtml(stockStatus(product)) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Marca</span><strong>' + escapeHtml(product.brand && product.brand.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Categoria</span><strong>' + escapeHtml(product.category && product.category.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Ingrediente</span><strong>' + escapeHtml(product.ingredient && product.ingredient.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Codigo</span><strong style="font-family:monospace">' + escapeHtml(product.barcode) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Cantidad neta</span><strong>' +
                escapeHtml(product.net_quantity) + ' ' + escapeHtml(product.unit && product.unit.symbol) +
            '</strong></div>';
    }

    function stockStatus(product) {
        var summary = product.stock_summary || null;
        if (!summary || !summary.in_stock) {
            return 'No esta en tu stock';
        }
        return summary.items_count === 1 ? 'Ya esta en tu stock (1 item)' : 'Ya esta en tu stock (' + summary.items_count + ' items)';
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

    function renderUnits(root) {
        var select = qs('[data-barcode-unit-select]', root);
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '<option value="">Selecciona unidad</option>' + state.units.map(function (unit) {
            return '<option value="' + unit.id + '">' + escapeHtml(unit.name) +
                (unit.symbol ? ' (' + escapeHtml(unit.symbol) + ')' : '') + '</option>';
        }).join('');
        select.value = current;
    }

    function existingUnits(product) {
        return product && product.stock_entry_suggestion && product.stock_entry_suggestion.existing_units
            ? product.stock_entry_suggestion.existing_units
            : [];
    }

    function unitNames(units) {
        return units.map(function (unit) {
            return unit.name || unit.symbol || unit.code;
        }).join(', ');
    }

    function updateUnitWarning(root) {
        var select = qs('[data-barcode-unit-select]', root);
        var warning = qs('[data-barcode-unit-warning]', root);
        var units = existingUnits(state.lastProduct);
        var differs = select && select.value && units.length && !units.some(function (unit) {
            return String(unit.id) === String(select.value);
        });
        if (warning) {
            warning.textContent = differs
                ? 'Ya tenés este producto cargado en ' + unitNames(units) + '. Si elegís otra unidad se creará un lote separado.'
                : '';
            warning.style.display = differs ? 'block' : 'none';
        }
        return !!differs;
    }

    function applyStockSuggestion(root, product) {
        var suggestion = product && product.stock_entry_suggestion ? product.stock_entry_suggestion : {};
        var quantity = qs('[data-barcode-quantity]', root);
        var unit = qs('[data-barcode-unit-select]', root);
        var note = qs('[data-barcode-unit-note]', root);

        if (quantity) {
            quantity.value = suggestion.quantity !== null && suggestion.quantity !== undefined ? suggestion.quantity : '';
        }
        if (unit) {
            unit.value = suggestion.unit_id || '';
        }
        if (note) {
            if (suggestion.source === 'existing_stock') {
                note.textContent = 'Unidad usada actualmente para este producto: ' + unitNames(existingUnits(product)) + '.';
            } else if (suggestion.source === 'package') {
                note.textContent = 'Cantidad y unidad tomadas de la presentacion del producto.';
            } else if (suggestion.source === 'default_unit') {
                note.textContent = 'Unidad predeterminada del producto.';
            } else {
                note.textContent = product ? 'Este producto no tiene una unidad conocida. Selecciona una antes de guardar.' : '';
            }
        }
        updateUnitWarning(root);
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

    function loadUnits(root) {
        return window.CCApi.request(endpoint('/units?per_page=100'))
            .then(function (response) {
                state.units = response.data || [];
                renderUnits(root);
            })
            .catch(function (error) {
                state.units = [];
                renderUnits(root);
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
        applyStockSuggestion(root, null);
        renderStockResult(root, null);

        if (resultEl) {
            resultEl.innerHTML = '<p class="muted">Buscando...</p>';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        var lookupUrl = endpoint('/products/barcode/' + encodeURIComponent(code));
        if (state.currentGroupId) {
            lookupUrl += '?family_group_id=' + encodeURIComponent(state.currentGroupId);
        }

        window.CCApi.request(lookupUrl)
            .then(function (response) {
                state.lastProduct = response.data || null;
                renderResult(root, state.lastProduct);
                applyStockSuggestion(root, state.lastProduct);
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
        var unit = qs('[data-barcode-unit-select]', root);
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
        if (!unit || !unit.value) {
            showMessage(root, 'danger', 'Selecciona una unidad para cargar el producto.');
            return;
        }
        if (updateUnitWarning(root) && !window.confirm('Ya tenés este producto cargado en otra unidad. Si continuás se creará un lote separado. ¿Querés continuar?')) {
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
                unit_id: Number(unit.value),
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
        var unitSelect = qs('[data-barcode-unit-select]', root);

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
                state.lastProduct = null;
                applyStockSuggestion(root, null);
                loadLocations(root);
            });
        }
        if (unitSelect) {
            unitSelect.addEventListener('change', function () {
                updateUnitWarning(root);
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
        loadUnits(root);
        loadGroups(root);

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var inp = qs('[data-barcode-code-input]', root);
                if (inp) { inp.focus(); inp.select(); }
            });
        }

        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = '/web/catalog';
            });
        }
    });
})(window, document);
