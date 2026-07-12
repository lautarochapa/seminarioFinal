(function (window, document) {
    'use strict';

    var state = {
        chains: [],
        branches: [],
        page: 1,
        lastPage: 1,
        total: 0,
        editId: null,
    };

    var typeLabels = {
        percentage: 'Porcentaje',
        fixed_amount: 'Monto fijo',
        buy_x_pay_y: '2x1 / Buy X Pay Y',
        payment_method: 'Metodo de pago',
        day_discount: 'Descuento por dia',
    };

    var dayLabels = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function escapeHtml(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }

        return String(value)
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
        var el = qs('[data-promotions-message]', root);
        if (!el) {
            return;
        }

        el.textContent = message;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-promotions-message]', root);
        if (!el) {
            return;
        }

        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function errorMessage(error, fallback) {
        if (error && error.payload && error.payload.error && error.payload.error.message) {
            return error.payload.error.message;
        }

        if (error && error.status === 401) {
            return 'Sesion vencida. Inicia sesion nuevamente.';
        }

        if (error && error.status === 403) {
            return 'No tenes permiso para gestionar promociones.';
        }

        if (error && error.status === 404) {
            return 'La promocion o sucursal no existe.';
        }

        if (error && error.status === 409) {
            return 'Existe un conflicto con la promocion.';
        }

        if (error && error.status === 422) {
            return 'Revisa los datos de la promocion.';
        }

        return fallback || 'No se pudo completar la operacion.';
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function chainName(chain) {
        return chain && chain.name ? chain.name : '-';
    }

    function branchName(branch) {
        if (!branch) {
            return 'Promocion de cadena';
        }

        var chain = branch.chain && branch.chain.name ? branch.chain.name + ' - ' : '';
        return chain + branch.name;
    }

    function filteredBranches(chainId) {
        return state.branches.filter(function (branch) {
            var branchChainId = branch.supermarket_chain_id || (branch.chain && branch.chain.id);
            return !chainId || String(branchChainId) === String(chainId);
        });
    }

    function renderBranchSelect(select, chainId, label) {
        if (!select) {
            return;
        }

        var current = select.value;
        select.innerHTML = '<option value="">' + label + '</option>' + filteredBranches(chainId).map(function (branch) {
            return option(branchName(branch), branch.id);
        }).join('');
        select.value = current;
    }

    function renderLookupSelects(root) {
        [
            '[data-promotions-filter-chain]',
            '[data-promotion-chain-select]',
        ].forEach(function (selector) {
            var select = qs(selector, root);
            if (!select) {
                return;
            }

            var first = select.getAttribute('data-promotion-chain-select') !== null ? 'Cadena' : 'Cadena';
            var current = select.value;
            select.innerHTML = '<option value="">' + first + '</option>' + state.chains.map(function (chain) {
                return option(chain.name, chain.id);
            }).join('');
            select.value = current;
        });

        renderBranchSelect(qs('[data-promotions-filter-branch]', root), qs('[data-promotions-filter-chain]', root).value, 'Sucursal');
        renderBranchSelect(qs('[data-promotion-branch-select]', root), qs('[data-promotion-chain-select]', root).value, 'Promocion de cadena');
    }

    function loadLookups(root) {
        return Promise.all([
            window.CCApi.request(endpoint('/supermarkets?per_page=100&sort=name&order=asc')),
            window.CCApi.request(endpoint('/admin/supermarket-branches?per_page=100&status=active')),
        ]).then(function (responses) {
            state.chains = responses[0].data || [];
            state.branches = responses[1].data || [];
            renderLookupSelects(root);
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error, 'No se pudieron cargar cadenas y sucursales.'));
        });
    }

    function statusChip(status) {
        return status === 'active'
            ? '<span style="background:#e7f7f2;color:#04ac85;padding:2px 8px;border-radius:50px;font-size:12px">Activa</span>'
            : '<span style="background:#fdecea;color:#b33a3a;padding:2px 8px;border-radius:50px;font-size:12px">Inactiva</span>';
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        var date = new Date(value);
        if (isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString('es-AR', { year: 'numeric', month: '2-digit', day: '2-digit' });
    }

    function toDatetimeLocal(value) {
        if (!value) {
            return '';
        }

        var date = new Date(value);
        if (isNaN(date.getTime())) {
            return '';
        }

        var pad = function (number) {
            return number < 10 ? '0' + number : String(number);
        };

        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) +
            'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function benefit(row) {
        var label = typeLabels[row.discount_type] || 'Promocion';
        var value = row.discount_value !== null && row.discount_value !== undefined ? row.discount_value : null;

        if (row.discount_type === 'percentage' && value !== null) {
            return label + ' ' + value + '%';
        }

        if (row.discount_type === 'fixed_amount' && value !== null) {
            return label + ' ARS ' + value;
        }

        if (row.discount_type === 'day_discount') {
            return label + (row.day_of_week !== null && row.day_of_week !== undefined ? ' - ' + dayLabels[row.day_of_week] : '');
        }

        if (row.discount_type === 'payment_method') {
            return label + (row.requires_payment_method ? ' requerido' : '');
        }

        if (row.discount_type === 'buy_x_pay_y') {
            return label + (value !== null ? ' - valor ' + value : '');
        }

        return label + (value !== null ? ' ' + value : '');
    }

    function validity(row) {
        var from = formatDate(row.valid_from);
        var to = formatDate(row.valid_to);
        if (from === '-' && to === '-') {
            return 'Sin vigencia definida';
        }

        return from + ' a ' + to;
    }

    function fetchPromotions(root, page) {
        clearMessage(root);
        state.page = page || 1;

        var params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', 20);

        var search = (qs('[data-promotions-search]', root).value || '').trim();
        var chain = qs('[data-promotions-filter-chain]', root).value;
        var branch = qs('[data-promotions-filter-branch]', root).value;
        var status = qs('[data-promotions-filter-status]', root).value;

        if (search) {
            params.set('search', search);
        }
        if (chain) {
            params.set('chain_id', chain);
        }
        if (branch) {
            params.set('branch_id', branch);
        }
        if (status) {
            params.set('status', status);
        }

        qs('[data-promotions-body]', root).innerHTML = '<tr><td colspan="6" class="muted">Cargando promociones...</td></tr>';

        return window.CCApi.request(endpoint('/admin/promotions?' + params.toString()))
            .then(function (response) {
                var rows = response.data || [];
                state.total = response.meta ? response.meta.total : rows.length;
                state.lastPage = response.meta ? response.meta.last_page : 1;
                state.page = response.meta ? response.meta.current_page : state.page;
                renderTable(root, rows);
                qs('[data-promotions-count]', root).textContent = state.total + ' promociones';
                qs('[data-promotions-page]', root).textContent = 'Pagina ' + state.page + ' de ' + state.lastPage;
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudieron cargar las promociones.'));
                qs('[data-promotions-body]', root).innerHTML = '<tr><td colspan="6" class="muted">Error al cargar.</td></tr>';
            });
    }

    function renderTable(root, rows) {
        var tbody = qs('[data-promotions-body]', root);
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="muted">No se encontraron promociones.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (row) {
            var actions = '<button type="button" class="btn-ghost btn-sm" data-promotion-edit="' + row.id + '">Editar</button> ';
            if (row.deleted_at || row.status !== 'active') {
                actions += '<button type="button" class="btn-ghost btn-sm" data-promotion-restore="' + row.id + '">Restaurar</button>';
            } else {
                actions += '<button type="button" class="btn-ghost btn-sm" style="color:var(--danger)" data-promotion-delete="' + row.id + '">Desactivar</button>';
            }

            return '<tr>' +
                '<td><strong>' + escapeHtml(row.name) + '</strong><br><span class="muted">' + escapeHtml(row.description) + '</span></td>' +
                '<td>' + escapeHtml(chainName(row.chain)) + '<br><span class="muted">' + escapeHtml(branchName(row.branch)) + '</span></td>' +
                '<td>' + escapeHtml(benefit(row)) + (row.requires_payment_method ? '<br><span class="muted">Requiere metodo de pago</span>' : '') + '</td>' +
                '<td>' + escapeHtml(validity(row)) + '</td>' +
                '<td>' + statusChip(row.status) + '</td>' +
                '<td style="white-space:nowrap">' + actions + '</td>' +
                '</tr>';
        }).join('');
    }

    function updateDynamicFields(root) {
        var form = qs('[data-promotion-form]', root);
        var type = form.elements.discount_type.value;
        var valueInput = form.elements.discount_value;
        var daySelect = form.elements.day_of_week;
        var paymentRequired = form.elements.requires_payment_method;

        valueInput.closest('[data-promotion-value-row]').style.display = (type === 'payment_method') ? 'none' : '';
        daySelect.style.display = (type === 'day_discount') ? '' : 'none';

        valueInput.min = '0';
        valueInput.max = '';
        valueInput.placeholder = 'Valor del descuento';

        if (type === 'percentage' || type === 'day_discount') {
            valueInput.max = '100';
            valueInput.placeholder = 'Porcentaje entre 0 y 100';
        } else if (type === 'fixed_amount') {
            valueInput.min = '0.01';
            valueInput.placeholder = 'Monto fijo mayor que cero';
        } else if (type === 'buy_x_pay_y') {
            valueInput.min = '0.01';
            valueInput.placeholder = 'Valor numerico segun contrato';
        }

        if (type === 'payment_method') {
            paymentRequired.checked = true;
        }
    }

    function resetForm(root) {
        state.editId = null;
        var form = qs('[data-promotion-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.supermarket_chain_id.disabled = false;
        form.elements.supermarket_branch_id.disabled = false;
        qs('[data-promotion-form-title]', root).textContent = 'Nueva promocion';
        qs('[data-promotion-submit]', root).textContent = 'Guardar promocion';
        renderBranchSelect(qs('[data-promotion-branch-select]', root), '', 'Promocion de cadena');
        updateDynamicFields(root);
    }

    function setEditMode(root, row) {
        state.editId = row.id;
        var form = qs('[data-promotion-form]', root);
        form.elements.id.value = row.id;
        form.elements.name.value = row.name || '';
        form.elements.description.value = row.description || '';
        form.elements.supermarket_chain_id.value = row.supermarket_chain_id || '';
        renderBranchSelect(qs('[data-promotion-branch-select]', root), form.elements.supermarket_chain_id.value, 'Promocion de cadena');
        form.elements.supermarket_branch_id.value = row.supermarket_branch_id || '';
        form.elements.discount_type.value = row.discount_type || '';
        form.elements.discount_value.value = row.discount_value !== null && row.discount_value !== undefined ? row.discount_value : '';
        form.elements.day_of_week.value = row.day_of_week !== null && row.day_of_week !== undefined ? row.day_of_week : '';
        form.elements.requires_payment_method.checked = !!row.requires_payment_method;
        form.elements.valid_from.value = toDatetimeLocal(row.valid_from);
        form.elements.valid_to.value = toDatetimeLocal(row.valid_to);
        form.elements.status.value = row.status || 'active';
        form.elements.supermarket_chain_id.disabled = true;
        form.elements.supermarket_branch_id.disabled = true;
        qs('[data-promotion-form-title]', root).textContent = 'Editar promocion';
        qs('[data-promotion-submit]', root).textContent = 'Guardar cambios';
        updateDynamicFields(root);
    }

    function payload(root) {
        var form = qs('[data-promotion-form]', root);
        var data = {};
        var id = form.elements.id.value;

        ['name', 'description', 'discount_type', 'discount_value', 'valid_from', 'valid_to', 'day_of_week', 'status'].forEach(function (key) {
            var value = form.elements[key].value;
            if (value !== '') {
                data[key] = value;
            }
        });

        if (!id) {
            data.supermarket_chain_id = parseInt(form.elements.supermarket_chain_id.value, 10);
            if (form.elements.supermarket_branch_id.value) {
                data.supermarket_branch_id = parseInt(form.elements.supermarket_branch_id.value, 10);
            }
        }

        if (data.discount_value !== undefined) {
            data.discount_value = parseFloat(data.discount_value);
        }

        if (data.day_of_week !== undefined) {
            data.day_of_week = parseInt(data.day_of_week, 10);
        }

        data.requires_payment_method = form.elements.requires_payment_method.checked;

        if (data.discount_type !== 'day_discount') {
            delete data.day_of_week;
        }

        if (data.discount_type === 'payment_method') {
            delete data.discount_value;
        }

        return data;
    }

    function savePromotion(root) {
        clearMessage(root);
        var form = qs('[data-promotion-form]', root);
        var button = qs('[data-promotion-submit]', root);
        var id = form.elements.id.value;
        var body = payload(root);
        var request = id
            ? window.CCApi.request(endpoint('/admin/promotions/' + encodeURIComponent(id)), { method: 'PATCH', body: body })
            : window.CCApi.request(endpoint('/admin/promotions'), { method: 'POST', body: body });

        button.disabled = true;
        button.textContent = 'Guardando...';

        request.then(function () {
            showMessage(root, 'success', id ? 'Promocion actualizada.' : 'Promocion creada.');
            resetForm(root);
            return fetchPromotions(root, state.page);
        }).catch(function (error) {
            showMessage(root, 'danger', errorMessage(error, 'No se pudo guardar la promocion.'));
        }).then(function () {
            button.disabled = false;
            button.textContent = state.editId ? 'Guardar cambios' : 'Guardar promocion';
        });
    }

    function loadPromotion(root, id) {
        window.CCApi.request(endpoint('/admin/promotions/' + encodeURIComponent(id)))
            .then(function (response) {
                setEditMode(root, response.data || response);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo cargar la promocion.'));
            });
    }

    function deletePromotion(root, id) {
        if (!window.confirm('Desactivar esta promocion?')) {
            return;
        }

        window.CCApi.request(endpoint('/admin/promotions/' + encodeURIComponent(id)), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Promocion desactivada.');
                return fetchPromotions(root, state.page);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo desactivar la promocion.'));
            });
    }

    function restorePromotion(root, id) {
        window.CCApi.request(endpoint('/admin/promotions/' + encodeURIComponent(id) + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Promocion restaurada.');
                return fetchPromotions(root, state.page);
            })
            .catch(function (error) {
                showMessage(root, 'danger', errorMessage(error, 'No se pudo restaurar la promocion.'));
            });
    }

    function bind(root) {
        qs('[data-promotion-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            savePromotion(root);
        });

        qs('[data-promotion-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });

        qs('[data-promotions-refresh]', root).addEventListener('click', function () {
            fetchPromotions(root, 1);
        });

        qs('[data-promotions-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                fetchPromotions(root, state.page - 1);
            }
        });

        qs('[data-promotions-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                fetchPromotions(root, state.page + 1);
            }
        });

        qs('[data-promotion-chain-select]', root).addEventListener('change', function () {
            renderBranchSelect(qs('[data-promotion-branch-select]', root), this.value, 'Promocion de cadena');
        });

        qs('[data-promotions-filter-chain]', root).addEventListener('change', function () {
            renderBranchSelect(qs('[data-promotions-filter-branch]', root), this.value, 'Sucursal');
            fetchPromotions(root, 1);
        });

        qs('[data-promotion-type]', root).addEventListener('change', function () {
            updateDynamicFields(root);
        });

        ['[data-promotions-filter-branch]', '[data-promotions-filter-status]'].forEach(function (selector) {
            qs(selector, root).addEventListener('change', function () {
                fetchPromotions(root, 1);
            });
        });

        qs('[data-promotions-search]', root).addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                fetchPromotions(root, 1);
            }
        });

        root.addEventListener('click', function (event) {
            var edit = event.target.closest('[data-promotion-edit]');
            var remove = event.target.closest('[data-promotion-delete]');
            var restore = event.target.closest('[data-promotion-restore]');

            if (edit) {
                loadPromotion(root, edit.getAttribute('data-promotion-edit'));
            } else if (remove) {
                deletePromotion(root, remove.getAttribute('data-promotion-delete'));
            } else if (restore) {
                restorePromotion(root, restore.getAttribute('data-promotion-restore'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-promotions]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        updateDynamicFields(root);
        loadLookups(root).then(function () {
            fetchPromotions(root, 1);
        });
    });
})(window, document);
