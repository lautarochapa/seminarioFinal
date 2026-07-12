(function (window, document) {
    'use strict';

    var state = {
        groups: [],
        products: [],
        units: [],
        locations: [],
        currentGroupId: null,
        currentPurchaseId: null,
        purchaseStatus: null,
        items: [],
        selectedItem: null,
        loading: false,
        saving: false,
        confirming: false,
        addingToStock: false,
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

    function text(v) { return v === null || v === undefined || v === '' ? '-' : String(v); }

    function basePath() {
        return '/api/v1/family-groups/' + encodeURIComponent(state.currentGroupId) +
            '/purchases/' + encodeURIComponent(state.currentPurchaseId);
    }

    function showMsg(root, type, msg) {
        var el = qs('[data-purchases-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearMsg(root) {
        var el = qs('[data-purchases-message]', root);
        if (!el) { return; }
        el.style.display = 'none';
        el.textContent = '';
    }

    function showFormMsg(root, type, msg) {
        var el = qs('[data-purchases-form-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearFormMsg(root) {
        var el = qs('[data-purchases-form-message]', root);
        if (!el) { return; }
        el.style.display = 'none';
    }

    function showConfirmMsg(root, type, msg) {
        var el = qs('[data-purchases-confirm-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearConfirmMsg(root) {
        var el = qs('[data-purchases-confirm-message]', root);
        if (!el) { return; }
        el.style.display = 'none';
    }

    function errMsg(err) {
        return (err && err.payload && err.payload.error && err.payload.error.message)
            ? err.payload.error.message
            : (err && err.message) || 'Error inesperado.';
    }

    function productName(item) {
        if (item.product) { return item.product.name || ('#' + item.product.id); }
        if (item.product_id) { return '#' + item.product_id; }
        return '-';
    }

    function stockBadge(item) {
        if (item.stock_entry_id) {
            return '<span style="background:#e7f7f2;color:#04ac85;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700">En stock</span>';
        }
        return '<span style="background:#f0f0f0;color:#888;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700">Sin ingreso</span>';
    }

    function expiryBadge(dateStr) {
        if (!dateStr) { return '-'; }
        var d = new Date(dateStr);
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var diff = Math.floor((d - today) / 86400000);
        var color = diff < 0 ? '#b33a3a' : diff <= 7 ? '#b35c00' : '#2a7a2a';
        var label = diff < 0 ? 'Vencido' : diff === 0 ? 'Hoy' : dateStr;
        return '<span style="color:' + color + ';font-size:12px;font-weight:700">' + escapeHtml(label) + '</span>';
    }

    function renderItems(root) {
        var tbody = qs('[data-purchases-body]', root);
        var count = qs('[data-purchases-count]', root);
        if (count) { count.textContent = state.items.length + ' ítems'; }
        if (!tbody) { return; }
        if (!state.currentPurchaseId) {
            tbody.innerHTML = '<tr><td colspan="7" class="muted">Ingresá el ID de una compra para ver sus ítems.</td></tr>';
            return;
        }
        if (!state.items.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="muted">Esta compra no tiene ítems cargados.</td></tr>';
            return;
        }
        tbody.innerHTML = state.items.map(function (item) {
            var qty = text(item.quantity);
            var unit = item.unit ? (item.unit.symbol || item.unit.code || '') : '';
            var unitPrice = fmt(item.unit_price);
            var total = (item.quantity && item.unit_price)
                ? fmt(parseFloat(item.quantity) * parseFloat(item.unit_price))
                : (item.total !== undefined ? fmt(item.total) : '-');
            return '<tr>' +
                '<td>' + escapeHtml(productName(item)) + '</td>' +
                '<td>' + escapeHtml(qty) + (unit ? ' <span class="muted">' + escapeHtml(unit) + '</span>' : '') + '</td>' +
                '<td>' + escapeHtml(unitPrice) + '</td>' +
                '<td><strong>' + escapeHtml(total) + '</strong></td>' +
                '<td>' + expiryBadge(item.expiry_date) + '</td>' +
                '<td>' + stockBadge(item) + '</td>' +
                '<td>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-item-edit="' + escapeHtml(String(item.id)) + '">Editar</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-item-delete="' + escapeHtml(String(item.id)) + '">Eliminar</button>' +
                '</td>' +
                '</tr>';
        }).join('');
    }

    function renderGroups(root) {
        var select = qs('[data-purchases-group]', root);
        if (!select) { return; }
        select.innerHTML = '<option value="">Seleccioná un grupo</option>' +
            state.groups.map(function (g) {
                return '<option value="' + escapeHtml(g.id) + '"' + (String(g.id) === String(state.currentGroupId) ? ' selected' : '') + '>' +
                    escapeHtml(g.name || ('Grupo #' + g.id)) + '</option>';
            }).join('');
    }

    function renderProductOptions(root) {
        var sel = qs('[data-item-product]', root);
        if (!sel) { return; }
        var current = sel.value;
        sel.innerHTML = '<option value="">Seleccioná un producto</option>' +
            state.products.map(function (p) {
                return '<option value="' + escapeHtml(p.id) + '"' + (String(p.id) === String(current) ? ' selected' : '') + '>' +
                    escapeHtml(p.name || p.normalized_name || ('#' + p.id)) + '</option>';
            }).join('');
    }

    function renderUnitOptions(root) {
        var sel = qs('[data-item-unit]', root);
        if (!sel) { return; }
        var current = sel.value;
        sel.innerHTML = '<option value="">Unidad (opcional)</option>' +
            state.units.map(function (u) {
                var label = [u.symbol || u.code || '', u.name || ''].filter(Boolean).join(' – ');
                return '<option value="' + escapeHtml(u.id) + '"' + (String(u.id) === String(current) ? ' selected' : '') + '>' +
                    escapeHtml(label || ('#' + u.id)) + '</option>';
            }).join('');
    }

    function resetForm(root) {
        var form = qs('[data-purchases-item-form]', root);
        var title = qs('[data-purchases-form-title]', root);
        if (form) { form.reset(); if (form.elements.id) { form.elements.id.value = ''; } }
        if (title) { title.textContent = 'Agregar ítem'; }
        state.selectedItem = null;
        clearFormMsg(root);
        renderProductOptions(root);
        renderUnitOptions(root);
    }

    function fillForm(root, item) {
        var form = qs('[data-purchases-item-form]', root);
        var title = qs('[data-purchases-form-title]', root);
        if (!form || !item) { return; }
        if (form.elements.id) { form.elements.id.value = item.id; }
        if (form.elements.product_id) { form.elements.product_id.value = item.product ? item.product.id : (item.product_id || ''); }
        if (form.elements.quantity) { form.elements.quantity.value = item.quantity || ''; }
        if (form.elements.unit_id) { form.elements.unit_id.value = item.unit ? item.unit.id : (item.unit_id || ''); }
        if (form.elements.unit_price) { form.elements.unit_price.value = item.unit_price || ''; }
        if (form.elements.expiry_date) { form.elements.expiry_date.value = item.expiry_date || ''; }
        if (form.elements.add_to_stock) { form.elements.add_to_stock.checked = !!item.stock_entry_id; }
        if (title) { title.textContent = 'Editar ítem #' + item.id; }
        clearFormMsg(root);
        renderProductOptions(root);
        renderUnitOptions(root);
    }

    function buildPayload(form) {
        var data = {};
        if (form.elements.product_id && form.elements.product_id.value) {
            data.product_id = Number(form.elements.product_id.value);
        }
        if (form.elements.quantity && form.elements.quantity.value !== '') {
            data.quantity = parseFloat(form.elements.quantity.value);
        }
        if (form.elements.unit_id && form.elements.unit_id.value) {
            data.unit_id = Number(form.elements.unit_id.value);
        }
        if (form.elements.unit_price && form.elements.unit_price.value !== '') {
            data.unit_price = parseFloat(form.elements.unit_price.value);
        }
        if (form.elements.expiry_date && form.elements.expiry_date.value) {
            data.expiry_date = form.elements.expiry_date.value;
        }
        if (form.elements.add_to_stock) {
            data.add_to_stock = form.elements.add_to_stock.checked;
        }
        return data;
    }

    function loadGroups(root) {
        return window.CCApi.request('/api/v1/family-groups')
            .then(function (response) {
                state.groups = response.data || [];
                if (!state.currentGroupId && state.groups.length) {
                    state.currentGroupId = state.groups[0].id;
                }
                renderGroups(root);
            })
            .catch(function (err) {
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function loadCatalogs(root) {
        return Promise.all([
            window.CCApi.request('/api/v1/products?per_page=100').catch(function () { return { data: [] }; }),
            window.CCApi.request('/api/v1/units?per_page=100').catch(function () { return { data: [] }; }),
        ]).then(function (results) {
            state.products = (results[0].data || []);
            state.units = (results[1].data || []);
            renderProductOptions(root);
            renderUnitOptions(root);
        });
    }

    function loadItems(root) {
        if (!state.currentGroupId || !state.currentPurchaseId) {
            renderItems(root);
            renderConfirmPanel(root);
            return Promise.resolve();
        }
        state.loading = true;
        clearMsg(root);
        return window.CCApi.request(basePath() + '/items')
            .then(function (response) {
                state.items = response.data || [];
                state.purchaseStatus = (response.meta && response.meta.purchase_status) ||
                    (response.data && response.data.purchase_status) || null;
                state.loading = false;
                renderItems(root);
                renderConfirmPanel(root);
            })
            .catch(function (err) {
                state.loading = false;
                state.items = [];
                renderItems(root);
                renderConfirmPanel(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function saveItem(root, form) {
        if (!state.currentGroupId || !state.currentPurchaseId) {
            showFormMsg(root, 'warning', 'Cargá una compra antes de agregar ítems.');
            return;
        }
        var id = form.elements.id ? form.elements.id.value : '';
        var payload = buildPayload(form);
        if (!payload.product_id) {
            showFormMsg(root, 'warning', 'Seleccioná un producto.');
            return;
        }
        state.saving = true;
        var btn = qs('[data-purchases-save]', root);
        if (btn) { btn.disabled = true; }

        window.CCApi.request(basePath() + '/items' + (id ? '/' + encodeURIComponent(id) : ''), {
            method: id ? 'PATCH' : 'POST',
            body: payload,
        }).then(function () {
            state.saving = false;
            if (btn) { btn.disabled = false; }
            showMsg(root, 'success', id ? 'Ítem actualizado.' : 'Ítem agregado.');
            resetForm(root);
            return loadItems(root);
        }).catch(function (err) {
            state.saving = false;
            if (btn) { btn.disabled = false; }
            showFormMsg(root, 'danger', errMsg(err));
        });
    }

    function deleteItem(root, itemId) {
        if (!state.currentGroupId || !state.currentPurchaseId || !itemId) { return; }
        if (!window.confirm('¿Eliminar este ítem de la compra?')) { return; }
        window.CCApi.request(basePath() + '/items/' + encodeURIComponent(itemId), { method: 'DELETE' })
            .then(function () {
                showMsg(root, 'success', 'Ítem eliminado.');
                return loadItems(root);
            })
            .catch(function (err) {
                showMsg(root, 'danger', errMsg(err));
            });
    }

    var STATUS_LABELS = {
        pending: 'Pendiente',
        confirmed: 'Confirmada',
        cancelled: 'Cancelada',
    };
    var STATUS_COLORS = {
        pending: 'background:#f0f0f0;color:#555',
        confirmed: 'background:#e7f7f2;color:#04ac85',
        cancelled: 'background:#f7e7e7;color:#b33a3a',
    };

    function calcItemsTotal() {
        var total = 0;
        state.items.forEach(function (item) {
            var qty = parseFloat(item.quantity) || 0;
            var price = parseFloat(item.unit_price) || 0;
            total += qty * price;
        });
        return total;
    }

    function renderConfirmPanel(root) {
        var panel = qs('[data-purchases-confirm-panel]', root);
        if (!panel) { return; }
        if (!state.currentPurchaseId) {
            panel.style.display = 'none';
            return;
        }
        panel.style.display = '';

        var badge = qs('[data-purchases-status-badge]', root);
        if (badge) {
            var status = state.purchaseStatus || 'pending';
            var label = STATUS_LABELS[status] || status;
            var color = STATUS_COLORS[status] || 'background:#f0f0f0;color:#555';
            badge.innerHTML = '<span style="' + color + ';border-radius:999px;padding:2px 10px;font-size:12px;font-weight:700">' + escapeHtml(label) + '</span>';
        }

        var totalEl = qs('[data-purchases-total]', root);
        if (totalEl) { totalEl.textContent = fmt(calcItemsTotal()); }

        var confirmBtn = qs('[data-purchases-confirm]', root);
        if (confirmBtn) {
            var isConfirmed = state.purchaseStatus === 'confirmed';
            confirmBtn.disabled = isConfirmed || state.confirming;
            confirmBtn.textContent = state.confirming ? 'Confirmando...' : (isConfirmed ? '✓ Compra confirmada' : '✓ Confirmar compra');
        }

        var stockBtn = qs('[data-purchases-add-to-stock]', root);
        if (stockBtn) {
            stockBtn.disabled = state.addingToStock;
            stockBtn.textContent = state.addingToStock ? 'Ingresando...' : '↑ Ingresar al stock';
        }
    }

    function renderLocationOptions(root) {
        var sel = qs('[data-purchases-stock-location]', root);
        if (!sel) { return; }
        sel.innerHTML = '<option value="">Sin ubicación específica</option>' +
            state.locations.map(function (loc) {
                return '<option value="' + escapeHtml(loc.id) + '">' +
                    escapeHtml(loc.name || loc.description || ('#' + loc.id)) + '</option>';
            }).join('');
    }

    function loadLocations(root) {
        if (!state.currentGroupId) { return Promise.resolve(); }
        return window.CCApi.request('/api/v1/family-groups/' + encodeURIComponent(state.currentGroupId) + '/stock-locations?per_page=100')
            .then(function (response) {
                state.locations = response.data || [];
                renderLocationOptions(root);
            })
            .catch(function () {
                state.locations = [];
            });
    }

    function confirmPurchase(root) {
        if (!state.currentGroupId || !state.currentPurchaseId || state.confirming) { return; }
        if (state.purchaseStatus === 'confirmed') { return; }
        state.confirming = true;
        clearConfirmMsg(root);
        renderConfirmPanel(root);

        window.CCApi.request(basePath() + '/confirm', { method: 'POST', body: {} })
            .then(function (response) {
                state.confirming = false;
                var data = response.data || {};
                state.purchaseStatus = data.status || 'confirmed';
                renderConfirmPanel(root);
                var msgParts = ['Compra confirmada.'];
                if (data.budget_entry) { msgParts.push('Presupuesto actualizado.'); }
                showConfirmMsg(root, 'success', msgParts.join(' '));
            })
            .catch(function (err) {
                state.confirming = false;
                renderConfirmPanel(root);
                showConfirmMsg(root, 'danger', errMsg(err));
            });
    }

    function addToStock(root) {
        if (!state.currentGroupId || !state.currentPurchaseId || state.addingToStock) { return; }
        state.addingToStock = true;
        clearConfirmMsg(root);
        renderConfirmPanel(root);

        var locationSel = qs('[data-purchases-stock-location]', root);
        var overwriteChk = qs('[data-purchases-overwrite]', root);
        var body = {};
        if (locationSel && locationSel.value) { body.location_id = Number(locationSel.value); }
        if (overwriteChk) { body.overwrite = overwriteChk.checked; }

        window.CCApi.request(basePath() + '/add-to-stock', { method: 'POST', body: body })
            .then(function (response) {
                state.addingToStock = false;
                renderConfirmPanel(root);
                var data = response.data || {};
                var added = data.items_added !== undefined ? data.items_added : '?';
                showConfirmMsg(root, 'success', added + ' ítem(s) ingresados al stock.');
                return loadItems(root);
            })
            .catch(function (err) {
                state.addingToStock = false;
                renderConfirmPanel(root);
                showConfirmMsg(root, 'danger', errMsg(err));
            });
    }

    function updateTotal(root) {
        var qty = parseFloat((qs('[data-item-quantity]', root) || {}).value || '0');
        var price = parseFloat((qs('[data-item-unit-price]', root) || {}).value || '0');
        var totalEl = qs('[data-item-total-preview]', root);
        if (totalEl) {
            totalEl.textContent = (!isNaN(qty) && !isNaN(price) && qty > 0 && price > 0)
                ? 'Total: ' + fmt(qty * price)
                : '';
        }
    }

    function bind(root) {
        var groupSelect = qs('[data-purchases-group]', root);
        var purchaseInput = qs('[data-purchases-id]', root);
        var loadBtn = qs('[data-purchases-load]', root);
        var form = qs('[data-purchases-item-form]', root);
        var resetBtn = qs('[data-purchases-reset]', root);

        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                state.currentGroupId = groupSelect.value || null;
                state.currentPurchaseId = null;
                state.purchaseStatus = null;
                state.locations = [];
                if (purchaseInput) { purchaseInput.value = ''; }
                state.items = [];
                renderItems(root);
                renderConfirmPanel(root);
                resetForm(root);
                if (state.currentGroupId) { loadLocations(root); }
            });
        }

        if (loadBtn && purchaseInput) {
            var doLoad = function () {
                var val = purchaseInput.value.trim();
                if (!val) { showMsg(root, 'warning', 'Ingresá el ID de la compra.'); return; }
                if (!state.currentGroupId) { showMsg(root, 'warning', 'Seleccioná un grupo familiar.'); return; }
                state.currentPurchaseId = val;
                state.purchaseStatus = null;
                state.items = [];
                clearConfirmMsg(root);
                resetForm(root);
                loadItems(root);
            };
            loadBtn.addEventListener('click', doLoad);
            purchaseInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { doLoad(); }
            });
        }

        var confirmBtn = qs('[data-purchases-confirm]', root);
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () { confirmPurchase(root); });
        }

        var addToStockBtn = qs('[data-purchases-add-to-stock]', root);
        if (addToStockBtn) {
            addToStockBtn.addEventListener('click', function () { addToStock(root); });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                saveItem(root, form);
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () { resetForm(root); });
        }

        // Live total preview
        root.addEventListener('input', function (event) {
            if (event.target.matches('[data-item-quantity]') || event.target.matches('[data-item-unit-price]')) {
                updateTotal(root);
            }
        });

        // Table row actions (delegated)
        var tbody = qs('[data-purchases-body]', root);
        if (tbody) {
            tbody.addEventListener('click', function (event) {
                var editBtn = event.target.closest('[data-item-edit]');
                var delBtn = event.target.closest('[data-item-delete]');
                if (editBtn) {
                    var id = editBtn.getAttribute('data-item-edit');
                    var item = state.items.find(function (i) { return String(i.id) === String(id); });
                    if (item) {
                        state.selectedItem = item;
                        fillForm(root, item);
                    }
                }
                if (delBtn) {
                    deleteItem(root, delBtn.getAttribute('data-item-delete'));
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-purchases]');
        if (!root) { return; }
        bind(root);
        renderConfirmPanel(root);
        loadGroups(root).then(function () {
            if (state.currentGroupId) { loadLocations(root); }
        });
        loadCatalogs(root);
    });
})(window, document);
