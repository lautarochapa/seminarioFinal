(function (window, document) {
    'use strict';

    var state = {
        groups: [],
        lists: [],
        selectedGroupId: null,
        selectedListId: null,
        session: null,
        sessionItems: [],
        runningTotal: 0,
        scanLoading: false,
        finishLoading: false,
        cameraActive: false,
        cameraStream: null,
        barcodeDetector: null,
        cameraFrameId: null,
    };

    function qs(sel, root) { return (root || document).querySelector(sel); }

    function escapeHtml(v) {
        return (v === null || v === undefined ? '' : String(v))
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmt(val) {
        var n = parseFloat(val);
        return isNaN(n) ? '-' : '$' + n.toFixed(2);
    }

    function groupPath(path) {
        return '/api/v1/family-groups/' + encodeURIComponent(state.selectedGroupId) + path;
    }

    function sessionPath(path) {
        return '/api/v1/family-groups/' + encodeURIComponent(state.selectedGroupId) +
            '/shopping-sessions/' + encodeURIComponent(state.session.id) + (path || '');
    }

    function showMsg(root, type, text, attr) {
        var el = qs('[' + (attr || 'data-session-message') + ']', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = text;
        el.style.display = 'block';
    }

    function clearMsg(root, attr) {
        var el = qs('[' + (attr || 'data-session-message') + ']', root);
        if (!el) { return; }
        el.style.display = 'none';
        el.textContent = '';
    }

    function errMsg(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message)
            ? err.payload.error.message
            : (err && err.message) || 'Error inesperado.';
    }

    function setView(root, view) {
        ['[data-session-setup]', '[data-session-active]', '[data-session-done]'].forEach(function (sel) {
            var el = qs(sel, root);
            if (el) { el.style.display = 'none'; }
        });
        var target = qs('[data-session-' + view + ']', root);
        if (target) { target.style.display = ''; }
    }

    function recalcTotal() {
        var total = 0;
        state.sessionItems.forEach(function (item) {
            if (item.status === 'scanned' && item.actual_price) {
                total += parseFloat(item.actual_price) || 0;
            }
        });
        state.runningTotal = total;
    }

    function renderTotal(root) {
        var el = qs('[data-session-total]', root);
        if (el) { el.textContent = fmt(state.runningTotal); }
    }

    function renderItemsCount(root) {
        var el = qs('[data-session-items-count]', root);
        if (!el) { return; }
        var pending = state.sessionItems.filter(function (i) { return i.status === 'pending'; }).length;
        el.textContent = pending + ' pendiente' + (pending !== 1 ? 's' : '');
    }

    function renderSessionItems(root) {
        var container = qs('[data-session-items-list]', root);
        if (!container) { return; }

        var pending = state.sessionItems.filter(function (i) { return i.status === 'pending'; });
        var scanned = state.sessionItems.filter(function (i) { return i.status === 'scanned'; });
        var skipped = state.sessionItems.filter(function (i) { return i.status === 'skipped'; });

        function itemRow(item, extraStyle, actionHtml) {
            var qty = item.quantity ? (escapeHtml(item.quantity) + (item.unit_symbol ? ' ' + escapeHtml(item.unit_symbol) : '')) : '';
            return '<div style="' + extraStyle + 'padding:10px 0;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:8px">' +
                '<div style="flex:1;min-width:0">' +
                '<div style="font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtml(item.item_name || ('Item #' + item.id)) + '</div>' +
                (qty ? '<div style="font-size:11px;color:#66746b">' + qty + '</div>' : '') +
                '</div>' +
                (item.actual_price ? '<span style="font-size:13px;font-weight:700;color:#04ac85;white-space:nowrap">' + fmt(item.actual_price) + '</span>' : '') +
                actionHtml +
                '</div>';
        }

        var html = '';

        if (pending.length) {
            html += '<div style="font-size:11px;font-weight:700;color:#66746b;text-transform:uppercase;margin-bottom:4px">Pendientes (' + pending.length + ')</div>';
            html += pending.map(function (item) {
                return itemRow(item, '',
                    '<button type="button" style="background:#f0f0f0;border:0;border-radius:4px;padding:4px 8px;font-size:11px;cursor:pointer;white-space:nowrap" data-item-skip="' + escapeHtml(String(item.id)) + '">Omitir</button>' +
                    '<button type="button" style="background:#04ac85;color:#fff;border:0;border-radius:4px;padding:4px 8px;font-size:11px;cursor:pointer;white-space:nowrap" data-item-mark="' + escapeHtml(String(item.id)) + '">✓</button>'
                );
            }).join('');
        }

        if (scanned.length) {
            html += '<div style="font-size:11px;font-weight:700;color:#2a7a2a;text-transform:uppercase;margin:10px 0 4px">Comprados (' + scanned.length + ')</div>';
            html += scanned.map(function (item) {
                return itemRow(item, 'background:#f0fbf7;padding-left:8px;border-radius:4px;',
                    '<button type="button" style="background:#f0f0f0;border:0;border-radius:4px;padding:4px 8px;font-size:11px;cursor:pointer;white-space:nowrap" data-item-undo="' + escapeHtml(String(item.id)) + '">↩</button>'
                );
            }).join('');
        }

        if (skipped.length) {
            html += '<div style="font-size:11px;font-weight:700;color:#999;text-transform:uppercase;margin:10px 0 4px">Omitidos (' + skipped.length + ')</div>';
            html += skipped.map(function (item) {
                return itemRow(item, 'opacity:0.6;',
                    '<button type="button" style="background:#f0f0f0;border:0;border-radius:4px;padding:4px 8px;font-size:11px;cursor:pointer;white-space:nowrap" data-item-restore="' + escapeHtml(String(item.id)) + '">↩</button>'
                );
            }).join('');
        }

        if (!html) {
            html = '<p style="font-size:13px;color:#66746b;margin:0">No hay items en la sesión.</p>';
        }

        container.innerHTML = html;
        renderItemsCount(root);
    }

    function updateLocalItemStatus(itemId, status, actualPrice) {
        state.sessionItems = state.sessionItems.map(function (item) {
            if (String(item.id) === String(itemId)) {
                var updated = Object.assign({}, item, { status: status });
                if (actualPrice !== null && actualPrice !== undefined) {
                    updated.actual_price = actualPrice;
                }
                if (status !== 'scanned') {
                    updated.actual_price = null;
                }
                return updated;
            }
            return item;
        });
        recalcTotal();
    }

    function sendItemStatusPatch(itemId, status) {
        if (!state.session) { return; }
        window.CCApi.request(sessionPath(), {
            method: 'PATCH',
            body: { item_id: Number(itemId), item_status: status },
        }).catch(function () {});
    }

    function renderScanResult(root, data) {
        var el = qs('[data-session-scan-result]', root);
        if (!el) { return; }
        var status = data.status || '';
        if (status === 'matched') {
            var matched = data.matched_item || {};
            var itemName = matched.item_name || matched.name || 'Ítem de la lista';
            el.innerHTML = '<div style="background:#e7f7f2;border:1px solid #04ac85;border-radius:6px;padding:10px 12px;margin-top:8px">' +
                '<div style="font-size:12px;font-weight:700;color:#04ac85;margin-bottom:4px">✓ Encontrado en la lista</div>' +
                '<div style="font-size:14px;font-weight:700">' + escapeHtml(data.product ? (data.product.name || '') : '') + '</div>' +
                '<div style="font-size:12px;color:#66746b">Coincide con: ' + escapeHtml(itemName) + '</div>' +
                (data.actual_price !== null && data.actual_price !== undefined
                    ? '<div style="font-size:13px;font-weight:700;color:#04ac85;margin-top:4px">' + fmt(data.actual_price) + '</div>'
                    : '') +
                '</div>';
            // Update local item state
            if (matched.id) {
                updateLocalItemStatus(matched.id, 'scanned', data.actual_price);
                renderSessionItems(root);
                renderTotal(root);
            }
        } else if (status === 'not_in_list') {
            el.innerHTML = '<div style="background:#fff3e0;border:1px solid #b35c00;border-radius:6px;padding:10px 12px;margin-top:8px">' +
                '<div style="font-size:12px;font-weight:700;color:#b35c00;margin-bottom:4px">⚠ Producto no en la lista</div>' +
                '<div style="font-size:14px;font-weight:700">' + escapeHtml(data.product ? (data.product.name || '') : '') + '</div>' +
                '</div>';
        } else {
            el.innerHTML = '<div style="background:#f7e7e7;border:1px solid #b33a3a;border-radius:6px;padding:10px 12px;margin-top:8px">' +
                '<div style="font-size:12px;font-weight:700;color:#b33a3a">✗ Código no reconocido</div>' +
                '</div>';
        }
        el.style.display = '';
    }

    function doScan(root, barcode) {
        if (!barcode || !state.session || state.scanLoading) { return; }
        state.scanLoading = true;
        clearMsg(root, 'data-scan-message');
        var btn = qs('[data-session-scan-btn]', root);
        if (btn) { btn.disabled = true; }

        window.CCApi.request(sessionPath('/scan'), {
            method: 'POST',
            body: { barcode: String(barcode).trim() },
        }).then(function (response) {
            state.scanLoading = false;
            if (btn) { btn.disabled = false; }
            var barcodeInput = qs('[data-session-barcode]', root);
            if (barcodeInput) { barcodeInput.value = ''; }
            renderScanResult(root, response.data || {});
        }).catch(function (err) {
            state.scanLoading = false;
            if (btn) { btn.disabled = false; }
            showMsg(root, 'danger', errMsg(err), 'data-scan-message');
        });
    }

    function stopCamera() {
        if (state.cameraFrameId) {
            cancelAnimationFrame(state.cameraFrameId);
            state.cameraFrameId = null;
        }
        if (state.cameraStream) {
            state.cameraStream.getTracks().forEach(function (t) { t.stop(); });
            state.cameraStream = null;
        }
        state.cameraActive = false;
    }

    function startCamera(root) {
        var container = qs('[data-session-camera-container]', root);
        var video = qs('[data-session-video]', root);
        if (!container || !video) { return; }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showMsg(root, 'warning', 'La cámara no está disponible en este navegador.', 'data-scan-message');
            return;
        }
        container.style.display = '';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                state.cameraStream = stream;
                state.cameraActive = true;
                video.srcObject = stream;
                if (!window.BarcodeDetector) { return; }
                state.barcodeDetector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e'] });
                function detectFrame() {
                    if (!state.cameraActive || !state.barcodeDetector) { return; }
                    state.barcodeDetector.detect(video)
                        .then(function (barcodes) {
                            if (barcodes.length && !state.scanLoading) {
                                doScan(root, barcodes[0].rawValue);
                            }
                        })
                        .catch(function () {})
                        .finally(function () {
                            if (state.cameraActive) {
                                state.cameraFrameId = requestAnimationFrame(detectFrame);
                            }
                        });
                }
                state.cameraFrameId = requestAnimationFrame(detectFrame);
            })
            .catch(function () {
                container.style.display = 'none';
                showMsg(root, 'warning', 'No se pudo acceder a la cámara.', 'data-scan-message');
            });
    }

    function closeCamera(root) {
        stopCamera();
        var container = qs('[data-session-camera-container]', root);
        if (container) { container.style.display = 'none'; }
    }

    function loadGroups(root) {
        return window.CCApi.request('/api/v1/family-groups')
            .then(function (response) {
                state.groups = response.data || [];
                var select = qs('[data-session-group]', root);
                if (!select) { return; }
                select.innerHTML = '<option value="">Seleccioná un grupo</option>' +
                    state.groups.map(function (g) {
                        return '<option value="' + escapeHtml(g.id) + '">' + escapeHtml(g.name || ('Grupo #' + g.id)) + '</option>';
                    }).join('');
            })
            .catch(function () {
                showMsg(root, 'danger', 'No se pudieron cargar los grupos familiares.');
            });
    }

    function loadLists(root) {
        if (!state.selectedGroupId) {
            var listSelect = qs('[data-session-list]', root);
            if (listSelect) { listSelect.innerHTML = '<option value="">Primero seleccioná un grupo</option>'; }
            return Promise.resolve();
        }
        return window.CCApi.request('/api/v1/family-groups/' + encodeURIComponent(state.selectedGroupId) + '/shopping-lists?per_page=50&status=active')
            .then(function (response) {
                state.lists = response.data || [];
                var select = qs('[data-session-list]', root);
                if (!select) { return; }
                if (!state.lists.length) {
                    select.innerHTML = '<option value="">No hay listas activas</option>';
                    return;
                }
                select.innerHTML = '<option value="">Seleccioná una lista</option>' +
                    state.lists.map(function (l) {
                        return '<option value="' + escapeHtml(l.id) + '">' +
                            escapeHtml('#' + l.id + ' – ' + (l.source_type || '') + (l.estimated_total ? ' ($' + parseFloat(l.estimated_total).toFixed(2) + ')' : '')) +
                            '</option>';
                    }).join('');
            })
            .catch(function () {
                showMsg(root, 'danger', 'No se pudieron cargar las listas.');
            });
    }

    function startSession(root) {
        if (!state.selectedGroupId || !state.selectedListId) {
            showMsg(root, 'warning', 'Seleccioná un grupo y una lista.');
            return;
        }
        clearMsg(root);
        var btn = qs('[data-session-start]', root);
        if (btn) { btn.disabled = true; btn.textContent = 'Iniciando...'; }

        window.CCApi.request(
            '/api/v1/family-groups/' + encodeURIComponent(state.selectedGroupId) +
            '/shopping-lists/' + encodeURIComponent(state.selectedListId) + '/start-session',
            { method: 'POST', body: {} }
        ).then(function (response) {
            state.session = response.data || {};
            state.sessionItems = (state.session.items || []).map(function (i) {
                return Object.assign({}, i, { status: i.status || 'pending' });
            });
            recalcTotal();

            var titleEl = qs('[data-session-title]', root);
            if (titleEl) { titleEl.textContent = 'Lista #' + (state.session.shopping_list_id || state.selectedListId); }
            renderTotal(root);
            renderSessionItems(root);

            var scanResult = qs('[data-session-scan-result]', root);
            if (scanResult) { scanResult.style.display = 'none'; }

            setView(root, 'active');
        }).catch(function (err) {
            if (btn) { btn.disabled = false; btn.textContent = 'Iniciar compra'; }
            showMsg(root, 'danger', errMsg(err));
        });
    }

    function finishSession(root) {
        if (!state.session || state.finishLoading) { return; }
        if (!window.confirm('¿Confirmar la compra y cerrar la sesión?')) { return; }
        state.finishLoading = true;
        var btn = qs('[data-session-finish]', root);
        if (btn) { btn.disabled = true; btn.textContent = 'Confirmando...'; }

        window.CCApi.request(sessionPath('/finish'), { method: 'POST', body: {} })
            .then(function (response) {
                state.finishLoading = false;
                stopCamera();
                var data = response.data || {};
                var purchased = data.items_purchased !== undefined ? data.items_purchased : state.sessionItems.filter(function (i) { return i.status === 'scanned'; }).length;
                var skipped = data.items_skipped !== undefined ? data.items_skipped : state.sessionItems.filter(function (i) { return i.status === 'skipped'; }).length;
                var total = data.total !== undefined ? data.total : state.runningTotal;

                var summaryEl = qs('[data-session-summary]', root);
                if (summaryEl) {
                    summaryEl.innerHTML = '<div style="text-align:center;padding:20px 0">' +
                        '<div style="font-size:40px;margin-bottom:12px">🛍</div>' +
                        '<h2 style="margin:0 0 8px;font-size:22px">¡Compra confirmada!</h2>' +
                        '<div style="font-size:32px;font-weight:900;color:#04ac85;margin-bottom:16px">' + fmt(total) + '</div>' +
                        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:320px;margin:0 auto">' +
                        '<div style="border:1px solid #dde6df;border-radius:8px;padding:12px">' +
                        '<strong style="display:block;font-size:24px">' + escapeHtml(String(purchased)) + '</strong>' +
                        '<span style="font-size:12px;color:#66746b">Comprados</span>' +
                        '</div>' +
                        '<div style="border:1px solid #dde6df;border-radius:8px;padding:12px">' +
                        '<strong style="display:block;font-size:24px">' + escapeHtml(String(skipped)) + '</strong>' +
                        '<span style="font-size:12px;color:#66746b">Omitidos</span>' +
                        '</div>' +
                        '</div>' +
                        '</div>';
                }
                setView(root, 'done');
            })
            .catch(function (err) {
                state.finishLoading = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Confirmar compra'; }
                showMsg(root, 'danger', errMsg(err), 'data-scan-message');
            });
    }

    function cancelSession(root) {
        if (!window.confirm('¿Cancelar la sesión de compra? Se perderán los cambios.')) { return; }
        stopCamera();
        state.session = null;
        state.sessionItems = [];
        state.runningTotal = 0;
        var startBtn = qs('[data-session-start]', root);
        if (startBtn) { startBtn.disabled = false; startBtn.textContent = 'Iniciar compra'; }
        setView(root, 'setup');
    }

    function handleCameraSupport(root) {
        var cameraBtn = qs('[data-session-camera-btn]', root);
        if (!cameraBtn) { return; }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraBtn.style.display = 'none';
        }
    }

    function bind(root) {
        var groupSelect = qs('[data-session-group]', root);
        var listSelect = qs('[data-session-list]', root);
        var startBtn = qs('[data-session-start]', root);
        var barcodeInput = qs('[data-session-barcode]', root);

        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                state.selectedGroupId = groupSelect.value || null;
                state.selectedListId = null;
                loadLists(root);
            });
        }

        if (listSelect) {
            listSelect.addEventListener('change', function () {
                state.selectedListId = listSelect.value || null;
            });
        }

        if (startBtn) {
            startBtn.addEventListener('click', function () { startSession(root); });
        }

        if (barcodeInput) {
            barcodeInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    doScan(root, barcodeInput.value.trim());
                }
            });
        }

        root.addEventListener('click', function (event) {
            if (event.target.closest('[data-session-scan-btn]')) {
                var input = qs('[data-session-barcode]', root);
                if (input) { doScan(root, input.value.trim()); }
                return;
            }
            if (event.target.closest('[data-session-camera-btn]')) {
                if (state.cameraActive) { closeCamera(root); } else { startCamera(root); }
                return;
            }
            if (event.target.closest('[data-session-camera-close]')) {
                closeCamera(root);
                return;
            }
            if (event.target.closest('[data-session-finish]')) {
                finishSession(root);
                return;
            }
            if (event.target.closest('[data-session-cancel]')) {
                cancelSession(root);
                return;
            }
            if (event.target.closest('[data-session-new]')) {
                state.session = null;
                state.sessionItems = [];
                state.runningTotal = 0;
                setView(root, 'setup');
                return;
            }

            var markBtn = event.target.closest('[data-item-mark]');
            if (markBtn) {
                var itemId = markBtn.getAttribute('data-item-mark');
                updateLocalItemStatus(itemId, 'scanned', null);
                sendItemStatusPatch(itemId, 'scanned');
                renderSessionItems(root);
                renderTotal(root);
                return;
            }
            var skipBtn = event.target.closest('[data-item-skip]');
            if (skipBtn) {
                var skipId = skipBtn.getAttribute('data-item-skip');
                updateLocalItemStatus(skipId, 'skipped', null);
                sendItemStatusPatch(skipId, 'skipped');
                renderSessionItems(root);
                renderTotal(root);
                return;
            }
            var undoBtn = event.target.closest('[data-item-undo]');
            if (undoBtn) {
                var undoId = undoBtn.getAttribute('data-item-undo');
                updateLocalItemStatus(undoId, 'pending', null);
                sendItemStatusPatch(undoId, 'pending');
                renderSessionItems(root);
                renderTotal(root);
                return;
            }
            var restoreBtn = event.target.closest('[data-item-restore]');
            if (restoreBtn) {
                var restoreId = restoreBtn.getAttribute('data-item-restore');
                updateLocalItemStatus(restoreId, 'pending', null);
                sendItemStatusPatch(restoreId, 'pending');
                renderSessionItems(root);
                renderTotal(root);
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { stopCamera(); }
        });
        window.addEventListener('beforeunload', function () { stopCamera(); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-shopping-session]');
        if (!root) { return; }
        handleCameraSupport(root);
        bind(root);
        loadGroups(root);
    });
})(window, document);
