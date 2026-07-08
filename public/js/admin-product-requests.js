(function (window, document) {
    'use strict';

    var state = {
        requests: [],
        brands: [],
        categories: [],
        ingredients: [],
        units: [],
        page: 1,
        lastPage: 1,
        selected: null,
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function text(value) {
        return value === null || value === undefined || value === '' ? '-' : String(value);
    }

    function escapeHtml(value) {
        return text(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function endpoint(path) {
        return '/api/v1' + path;
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-product-requests-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function handleError(root, error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        showMessage(root, 'danger', apiError.message || error.message || 'No se pudo completar la operacion.');
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
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

    function unitLabel(unit) {
        return unit.name + (unit.symbol ? ' (' + unit.symbol + ')' : '');
    }

    function renderSelect(select, items, first, labeler) {
        if (!select) {
            return;
        }
        var current = select.value;
        select.innerHTML = '<option value="">' + escapeHtml(first) + '</option>' + items.map(function (item) {
            var label = labeler ? labeler(item) : ((item.level ? Array(item.level + 1).join('- ') : '') + item.name);
            return option(label, item.id);
        }).join('');
        select.value = current;
    }

    function loadLookups(root) {
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

    function renderLookups(root) {
        renderSelect(qs('[data-product-request-brand]', root), state.brands, 'Marca');
        renderSelect(qs('[data-product-request-category]', root), state.categories, 'Categoria');
        renderSelect(qs('[data-product-request-ingredient]', root), state.ingredients, 'Ingrediente principal');
        renderSelect(qs('[data-product-request-unit]', root), state.units, 'Unidad base *', unitLabel);
        renderSelect(qs('[data-product-request-package-unit]', root), state.units, 'Unidad de paquete', unitLabel);
    }

    function loadRequests(root, page) {
        var params = new URLSearchParams();
        var status = qs('[data-product-requests-status]', root);
        var search = qs('[data-product-requests-search]', root);
        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'created_at');
        params.set('order', 'desc');
        if (status && status.value) {
            params.set('status', status.value);
        }
        if (search && search.value) {
            params.set('search', search.value);
        }

        return window.CCApi.request(endpoint('/product-requests?' + params.toString()))
            .then(function (response) {
                state.requests = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderRequests(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderRequests(root, meta) {
        var body = qs('[data-product-requests-body]', root);
        var count = qs('[data-product-requests-count]', root);
        var page = qs('[data-product-requests-page]', root);
        if (count) {
            count.textContent = (meta.total || state.requests.length) + ' solicitudes';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }
        if (!state.requests.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay solicitudes para mostrar.</td></tr>';
            return;
        }

        body.innerHTML = state.requests.map(function (item) {
            var requester = item.requester ? (item.requester.name || item.requester.email) : '-';
            var actions = item.status === 'pending'
                ? '<button type="button" class="btn-main btn-sm" data-product-request-review="' + item.id + '">Aprobar</button> <button type="button" class="btn-ghost btn-sm" data-product-request-table-reject="' + item.id + '">Rechazar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-product-request-review="' + item.id + '">Ver</button>';
            return '<tr>' +
                '<td><strong>' + escapeHtml(item.name) + '</strong><br><span class="muted">' + escapeHtml(item.brand) + ' ' + escapeHtml(item.presentation) + '</span></td>' +
                '<td>' + escapeHtml(requester) + '</td>' +
                '<td>' + escapeHtml(item.barcode) + '</td>' +
                '<td>' + escapeHtml(item.status) + '</td>' +
                '<td>' + escapeHtml(item.created_at) + '</td>' +
                '<td>' + actions + '</td>' +
                '</tr>';
        }).join('');
    }

    function fillForm(root, item) {
        state.selected = item;
        var form = qs('[data-product-request-approve-form]', root);
        form.reset();
        form.elements.id.value = item.id;
        form.elements.name.value = item.name || '';
        form.elements.barcode.value = item.barcode || '';
        form.elements.description.value = item.comment || '';
        form.elements.default_unit_id.value = item.unit_id || '';
        form.elements.status.value = 'active';
        qs('[data-product-request-form-title]', root).textContent = 'Solicitud #' + item.id;
        qs('[data-product-request-detail]', root).innerHTML =
            '<div class="line"><span>Marca solicitada</span><strong>' + escapeHtml(item.brand) + '</strong></div>' +
            '<div class="line"><span>Presentacion</span><strong>' + escapeHtml(item.presentation) + '</strong></div>' +
            '<div class="line"><span>Comentario</span><strong>' + escapeHtml(item.comment) + '</strong></div>' +
            '<div class="line"><span>Origen</span><strong>' + escapeHtml(item.source) + '</strong></div>';
    }

    function payload(form) {
        var numeric = ['brand_id', 'category_id', 'ingredient_id', 'default_unit_id', 'package_unit_id'];
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

    function approve(root, form) {
        var id = form.elements.id.value;
        if (!id) {
            showMessage(root, 'danger', 'Selecciona una solicitud.');
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/product-requests/' + encodeURIComponent(id) + '/approve'), {
            method: 'POST',
            body: payload(form),
        }).then(function () {
            showMessage(root, 'success', 'Solicitud aprobada y producto creado.');
            form.reset();
            return loadRequests(root, state.page);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function reject(root, id, notes) {
        if (!id) {
            showMessage(root, 'danger', 'Selecciona una solicitud.');
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/product-requests/' + encodeURIComponent(id) + '/reject'), {
            method: 'POST',
            body: { review_notes: notes || null },
        }).then(function () {
            showMessage(root, 'success', 'Solicitud rechazada.');
            return loadRequests(root, state.page);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        qs('[data-product-requests-refresh]', root).addEventListener('click', function () { loadRequests(root, 1); });
        qs('[data-product-requests-prev]', root).addEventListener('click', function () { loadRequests(root, Math.max(1, state.page - 1)); });
        qs('[data-product-requests-next]', root).addEventListener('click', function () { loadRequests(root, Math.min(state.lastPage, state.page + 1)); });
        qs('[data-product-requests-status]', root).addEventListener('change', function () { loadRequests(root, 1); });
        qs('[data-product-requests-search]', root).addEventListener('change', function () { loadRequests(root, 1); });
        qs('[data-product-request-approve-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            approve(root, event.currentTarget);
        });
        qs('[data-product-request-reject]', root).addEventListener('click', function () {
            var form = qs('[data-product-request-approve-form]', root);
            reject(root, form.elements.id.value, form.elements.review_notes.value);
        });
        qs('[data-product-requests-body]', root).addEventListener('click', function (event) {
            var review = event.target.closest('[data-product-request-review]');
            var rejectBtn = event.target.closest('[data-product-request-table-reject]');
            if (review) {
                var id = parseInt(review.getAttribute('data-product-request-review'), 10);
                var item = state.requests.find(function (row) { return row.id === id; });
                if (item) {
                    fillForm(root, item);
                }
            }
            if (rejectBtn) {
                reject(root, rejectBtn.getAttribute('data-product-request-table-reject'), null);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-product-requests]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        loadLookups(root).then(function () {
            return loadRequests(root, 1);
        });
    });
})(window, document);
