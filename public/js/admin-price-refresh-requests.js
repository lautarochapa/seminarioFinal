(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        rows: [],
        selected: null,
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
        var alert = qs('[data-price-refresh-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-price-refresh-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = '';
        alert.className = 'alert';
        alert.style.display = 'none';
    }

    function apiMessage(error) {
        var payload = error && error.payload ? error.payload : {};
        var apiError = payload.error || {};
        if (apiError.message) {
            return apiError.message;
        }
        if (error && error.status === 401) {
            return 'Sesion vencida. Inicia sesion nuevamente.';
        }
        if (error && error.status === 403) {
            return 'No tenes permiso para procesar solicitudes.';
        }
        if (error && error.status === 404) {
            return 'La solicitud no existe.';
        }
        if (error && error.status === 409) {
            return 'La solicitud ya fue procesada.';
        }
        if (error && error.status === 422) {
            return 'Revisa los filtros enviados.';
        }
        return (error && error.message) || 'No se pudo completar la operacion.';
    }

    function dateLabel(value) {
        if (!value) {
            return '-';
        }
        return String(value).replace('T', ' ').replace('.000000Z', '').replace('Z', '');
    }

    function statusChip(status) {
        var danger = status === 'failed' ? ' danger' : '';
        return '<span class="chip' + danger + '">' + escapeHtml(status) + '</span>';
    }

    function productName(row) {
        return row.product ? row.product.name : ('Producto #' + row.product_id);
    }

    function userLabel(row) {
        if (!row.user) {
            return 'Usuario #' + row.user_id;
        }
        return row.user.name + ' / ' + row.user.email;
    }

    function buildParams(root) {
        var params = new URLSearchParams();
        var pairs = [
            ['status', qs('[data-price-refresh-status]', root).value],
            ['product_id', qs('[data-price-refresh-product]', root).value],
            ['user_id', qs('[data-price-refresh-user]', root).value],
            ['date_from', qs('[data-price-refresh-from]', root).value],
            ['date_to', qs('[data-price-refresh-to]', root).value],
        ];
        pairs.forEach(function (pair) {
            if (pair[1]) {
                params.set(pair[0], pair[1]);
            }
        });
        return params;
    }

    function loadRows(root, page) {
        var params = buildParams(root);
        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '20');

        qs('[data-price-refresh-body]', root).innerHTML = '<tr><td colspan="7" class="muted">Cargando solicitudes...</td></tr>';
        return window.CCApi.request(endpoint('/admin/price-refresh-requests?' + params.toString()))
            .then(function (response) {
                state.rows = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderRows(root, response.meta || {});
            }).catch(function (error) {
                qs('[data-price-refresh-body]', root).innerHTML = '<tr><td colspan="7" class="muted">Error al cargar.</td></tr>';
                showMessage(root, 'danger', apiMessage(error));
            });
    }

    function renderRows(root, meta) {
        var body = qs('[data-price-refresh-body]', root);
        qs('[data-price-refresh-count]', root).textContent = (meta.total || state.rows.length) + ' solicitudes';
        qs('[data-price-refresh-page]', root).textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);

        if (!state.rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay solicitudes para los filtros seleccionados.</td></tr>';
            return;
        }

        body.innerHTML = state.rows.map(function (row) {
            var pending = row.status === 'pending';
            return '<tr>' +
                '<td><strong>' + escapeHtml(productName(row)) + '</strong><br><span class="muted">#' + escapeHtml(row.product_id) + '</span></td>' +
                '<td>' + escapeHtml(userLabel(row)) + '<br><span class="muted">#' + escapeHtml(row.user_id) + '</span></td>' +
                '<td><span class="muted">Cadena: ' + escapeHtml(row.supermarket_chain_id) + '</span><br><span class="muted">Sucursal: ' + escapeHtml(row.supermarket_branch_id) + '</span></td>' +
                '<td>' + escapeHtml(row.reason) + '</td>' +
                '<td>' + statusChip(row.status) + '</td>' +
                '<td><span class="muted">Pedido: ' + escapeHtml(dateLabel(row.requested_at)) + '</span><br><span class="muted">Proceso: ' + escapeHtml(dateLabel(row.processed_at)) + '</span></td>' +
                '<td><button type="button" class="btn-ghost btn-sm" data-price-refresh-view="' + row.id + '">Detalle</button> ' +
                    (pending ? '<button type="button" class="btn-main btn-sm" data-price-refresh-process="' + row.id + '">Procesar</button>' : '') +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function renderLine(label, value) {
        return '<div class="line"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
    }

    function renderDetail(root, row) {
        qs('[data-price-refresh-detail]', root).innerHTML =
            renderLine('ID', '#' + row.id) +
            renderLine('Producto', productName(row)) +
            renderLine('Usuario', userLabel(row)) +
            renderLine('Estado', row.status) +
            renderLine('Motivo', row.reason) +
            renderLine('Cadena', row.supermarket_chain_id || '-') +
            renderLine('Sucursal', row.supermarket_branch_id || '-') +
            renderLine('Pedido', dateLabel(row.requested_at)) +
            renderLine('Procesado', dateLabel(row.processed_at));
    }

    function selectRow(root, id) {
        var row = state.rows.filter(function (item) {
            return String(item.id) === String(id);
        })[0] || null;
        state.selected = row;
        if (!row) {
            qs('[data-price-refresh-detail]', root).innerHTML = '<p class="muted">Solicitud no encontrada en la pagina actual.</p>';
            return;
        }
        renderDetail(root, row);
    }

    function processRow(root, id) {
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/price-refresh-requests/' + encodeURIComponent(id) + '/process'), {
            method: 'POST',
            body: {},
        }).then(function (response) {
            showMessage(root, 'success', 'Solicitud procesada.');
            state.selected = response.data;
            renderDetail(root, response.data);
            loadRows(root, state.page);
        }).catch(function (error) {
            showMessage(root, 'danger', apiMessage(error));
        });
    }

    function reload(root, page) {
        clearMessage(root);
        loadRows(root, page || 1);
    }

    function bind(root) {
        ['[data-price-refresh-status]', '[data-price-refresh-from]', '[data-price-refresh-to]'].forEach(function (selector) {
            qs(selector, root).addEventListener('change', function () {
                reload(root, 1);
            });
        });
        ['[data-price-refresh-product]', '[data-price-refresh-user]'].forEach(function (selector) {
            qs(selector, root).addEventListener('input', function () {
                reload(root, 1);
            });
        });
        qs('[data-price-refresh-refresh]', root).addEventListener('click', function () {
            reload(root, 1);
        });
        qs('[data-price-refresh-prev]', root).addEventListener('click', function () {
            if (state.page > 1) {
                reload(root, state.page - 1);
            }
        });
        qs('[data-price-refresh-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) {
                reload(root, state.page + 1);
            }
        });
        qs('[data-price-refresh-body]', root).addEventListener('click', function (event) {
            var view = event.target.closest('[data-price-refresh-view]');
            var process = event.target.closest('[data-price-refresh-process]');
            if (view) {
                selectRow(root, view.getAttribute('data-price-refresh-view'));
            }
            if (process) {
                processRow(root, process.getAttribute('data-price-refresh-process'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-price-refresh]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
        reload(root, 1);
    });
})(window, document);
