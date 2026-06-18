(function (window, document) {
    'use strict';

    var cameraStream   = null;
    var barcodeDetector = null;
    var detecting      = false;

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

    function showMessage(root, type, msg) {
        var el = qs('[data-barcode-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-barcode-message]', root);
        if (!el) { return; }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    // ── BÚSQUEDA ─────────────────────────────────────────────────────────────

    function searchBarcode(root) {
        clearMessage(root);
        var input = qs('[data-barcode-code-input]', root);
        var code  = input ? input.value.trim() : '';

        if (!code) {
            showMessage(root, 'danger', 'Ingresá un código de barras para buscar.');
            return;
        }

        var resultEl = qs('[data-barcode-result]', root);
        resultEl.innerHTML = '<p class="muted">Buscando...</p>';

        var submitBtn = qs('[data-barcode-search-submit]', root);
        if (submitBtn) { submitBtn.disabled = true; }

        window.CCApi.request(endpoint('/products/barcode/' + encodeURIComponent(code)))
            .then(function (r) {
                renderResult(resultEl, r.data);
            })
            .catch(function (err) {
                var status = err.status || 0;
                if (status === 404) {
                    resultEl.innerHTML = '<p class="muted">No se encontró ningún producto con el código <strong>' +
                        escapeHtml(code) + '</strong>.</p>';
                } else {
                    var msg = (err.payload && err.payload.error && err.payload.error.message)
                        || 'Error al buscar el código. Intentá de nuevo.';
                    resultEl.innerHTML = '<p class="muted">' + escapeHtml(msg) + '</p>';
                    showMessage(root, 'danger', msg);
                }
            })
            .then(function () {
                if (submitBtn) { submitBtn.disabled = false; }
            });
    }

    function renderResult(el, product) {
        if (!product) {
            el.innerHTML = '<p class="muted">Producto no disponible.</p>';
            return;
        }
        el.innerHTML =
            '<div class="table-line"><span class="muted">Producto</span><strong>'   + escapeHtml(product.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Marca</span><strong>'      + escapeHtml(product.brand    && product.brand.name)    + '</strong></div>' +
            '<div class="table-line"><span class="muted">Categoría</span><strong>'  + escapeHtml(product.category && product.category.name) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Código</span><strong style="font-family:monospace">' + escapeHtml(product.barcode) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Cantidad neta</span><strong>' +
                escapeHtml(product.net_quantity) + ' ' + escapeHtml(product.unit && product.unit.symbol) +
            '</strong></div>' +
            '<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">' +
            '<a href="/web/catalog" class="btn-secondary-web btn-sm">Ver catálogo</a>' +
            '</div>';
    }

    // ── CÁMARA ───────────────────────────────────────────────────────────────

    function isCameraSupported() {
        return !!(window.BarcodeDetector &&
            navigator.mediaDevices &&
            navigator.mediaDevices.getUserMedia);
    }

    function startCamera(root) {
        if (!isCameraSupported()) {
            showMessage(root, 'danger',
                'Tu navegador no soporta lectura de códigos desde la cámara. Usá el ingreso manual.');
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                cameraStream    = stream;
                barcodeDetector = new window.BarcodeDetector({
                    formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code'],
                });

                var video = qs('[data-barcode-video]', root);
                if (video) { video.srcObject = stream; }

                var container = qs('[data-barcode-camera-container]', root);
                if (container) { container.style.display = 'block'; }

                detecting = true;
                requestAnimationFrame(function () { detectLoop(root); });
            })
            .catch(function () {
                showMessage(root, 'danger',
                    'No se pudo acceder a la cámara. Verificá los permisos del navegador.');
            });
    }

    function detectLoop(root) {
        if (!detecting || !cameraStream || !barcodeDetector) { return; }
        var video = qs('[data-barcode-video]', root);
        if (!video || video.readyState < 2) {
            if (detecting) { requestAnimationFrame(function () { detectLoop(root); }); }
            return;
        }

        barcodeDetector.detect(video)
            .then(function (barcodes) {
                if (barcodes.length > 0) {
                    var code  = barcodes[0].rawValue;
                    var input = qs('[data-barcode-code-input]', root);
                    if (input) { input.value = code; }
                    stopCamera(root);
                    searchBarcode(root);
                    return;
                }
                if (detecting) { requestAnimationFrame(function () { detectLoop(root); }); }
            })
            .catch(function () {
                if (detecting) { requestAnimationFrame(function () { detectLoop(root); }); }
            });
    }

    function stopCamera(root) {
        detecting = false;
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (t) { t.stop(); });
            cameraStream = null;
        }
        barcodeDetector = null;
        var video = qs('[data-barcode-video]', root);
        if (video) { video.srcObject = null; }
        var container = qs('[data-barcode-camera-container]', root);
        if (container) { container.style.display = 'none'; }
    }

    // ── BIND ─────────────────────────────────────────────────────────────────

    function bind(root) {
        var submitBtn = qs('[data-barcode-search-submit]', root);
        if (submitBtn) {
            submitBtn.addEventListener('click', function () { searchBarcode(root); });
        }

        var input = qs('[data-barcode-code-input]', root);
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); searchBarcode(root); }
            });
        }

        var cameraToggle = qs('[data-barcode-camera-toggle]', root);
        if (cameraToggle) {
            if (!isCameraSupported()) {
                cameraToggle.style.display = 'none';
            } else {
                cameraToggle.addEventListener('click', function () { startCamera(root); });
            }
        }

        var cameraStop = qs('[data-barcode-camera-stop]', root);
        if (cameraStop) {
            cameraStop.addEventListener('click', function () { stopCamera(root); });
        }
    }

    // ── LIMPIEZA ─────────────────────────────────────────────────────────────

    window.addEventListener('beforeunload', function () {
        detecting = false;
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (t) { t.stop(); });
            cameraStream = null;
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            var root = qs('[data-user-barcode]');
            if (root) { stopCamera(root); }
        }
    });

    // ── INIT ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-barcode]');
        if (!root || !window.CCApi) { return; }
        bind(root);
    });
})(window, document);
