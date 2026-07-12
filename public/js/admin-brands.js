(function (window, document) {
    'use strict';

    var state = {
        brands: [],
        publicBrands: [],
        page: 1,
        lastPage: 1,
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
        var alert = qs('[data-brands-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-brands-message]', root);
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

    function fetchBrands(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-brands-search]', root);
        var status = qs('[data-brands-status]', root);

        state.page = page || state.page || 1;
        params.set('page', String(state.page));
        params.set('per_page', '50');
        params.set('sort', 'name');
        params.set('order', 'asc');

        if (search && search.value) {
            params.set('search', search.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/brands?' + params.toString()))
            .then(function (response) {
                state.brands = response.data || [];
                state.lastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderBrands(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderBrands(root, meta) {
        var body = qs('[data-brands-body]', root);
        var count = qs('[data-brands-count]', root);
        var page = qs('[data-brands-page]', root);

        if (count) {
            count.textContent = (meta.total || state.brands.length) + ' marcas';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage);
        }
        if (!state.brands.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay marcas cargadas.</td></tr>';
            return;
        }

        body.innerHTML = state.brands.map(function (brand) {
            var action = brand.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-brand-delete="' + brand.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-brand-restore="' + brand.id + '">Restaurar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(brand.name) + '</strong></td>' +
                '<td>' + escapeHtml(brand.normalized_name) + '</td>' +
                '<td>' + escapeHtml(brand.status) + '</td>' +
                '<td>' + escapeHtml(brand.updated_at) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-brand-edit="' + brand.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function fetchPublicBrands(root) {
        var params = new URLSearchParams();
        var search = qs('[data-brands-public-search]', root);

        params.set('per_page', '100');
        params.set('sort', 'name');
        params.set('order', 'asc');
        if (search && search.value) {
            params.set('search', search.value);
        }

        return window.CCApi.request(endpoint('/brands?' + params.toString()))
            .then(function (response) {
                state.publicBrands = response.data || [];
                renderPublicBrands(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderPublicBrands(root, meta) {
        var target = qs('[data-brands-public-results]', root);
        var count = qs('[data-brands-public-count]', root);

        if (count) {
            count.textContent = (meta.total || state.publicBrands.length) + ' marcas activas';
        }
        if (!state.publicBrands.length) {
            target.innerHTML = '<span class="muted">No hay marcas activas para mostrar.</span>';
            return;
        }

        target.innerHTML = state.publicBrands.map(function (brand) {
            return '<span class="chip">' + escapeHtml(brand.name) + '</span>';
        }).join('');
    }

    function payload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id' || value === '') {
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function resetForm(root) {
        var form = qs('[data-brand-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-brand-form-title]', root).textContent = 'Nueva marca';
    }

    function fillForm(root, brand) {
        var form = qs('[data-brand-form]', root);
        form.elements.id.value = brand.id;
        form.elements.name.value = brand.name || '';
        form.elements.status.value = brand.status || 'active';
        qs('[data-brand-form-title]', root).textContent = 'Editar marca #' + brand.id;
    }

    function saveBrand(root, form) {
        clearMessage(root);
        var id = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path = id ? '/admin/brands/' + encodeURIComponent(id) : '/admin/brands';

        return window.CCApi.request(endpoint(path), {
            method: method,
            body: payload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Marca actualizada.' : 'Marca creada.');
            resetForm(root);
            return Promise.all([fetchBrands(root, state.page), fetchPublicBrands(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteBrand(root, id) {
        if (!window.confirm('Eliminar esta marca?')) {
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/brands/' + encodeURIComponent(id)), {
            method: 'DELETE',
        }).then(function () {
            showMessage(root, 'success', 'Marca eliminada.');
            return Promise.all([fetchBrands(root, state.page), fetchPublicBrands(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function restoreBrand(root, id) {
        if (!id) {
            showMessage(root, 'danger', 'Indica el ID de la marca a restaurar.');
            return Promise.resolve();
        }

        return window.CCApi.request(endpoint('/admin/brands/' + encodeURIComponent(id) + '/restore'), {
            method: 'PATCH',
        }).then(function () {
            showMessage(root, 'success', 'Marca restaurada.');
            var input = qs('[data-brand-restore-id]', root);
            if (input) {
                input.value = '';
            }
            return Promise.all([fetchBrands(root, state.page), fetchPublicBrands(root)]);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function bind(root) {
        qs('[data-brands-refresh]', root).addEventListener('click', function () {
            fetchBrands(root, 1);
        });
        qs('[data-brands-public-refresh]', root).addEventListener('click', function () {
            fetchPublicBrands(root);
        });
        qs('[data-brands-prev]', root).addEventListener('click', function () {
            fetchBrands(root, Math.max(1, state.page - 1));
        });
        qs('[data-brands-next]', root).addEventListener('click', function () {
            fetchBrands(root, Math.min(state.lastPage, state.page + 1));
        });
        qs('[data-brand-reset]', root).addEventListener('click', function () {
            resetForm(root);
        });
        qs('[data-brand-restore-submit]', root).addEventListener('click', function () {
            restoreBrand(root, qs('[data-brand-restore-id]', root).value);
        });
        qs('[data-brand-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            saveBrand(root, event.currentTarget);
        });
        qs('[data-brands-body]', root).addEventListener('click', function (event) {
            var edit = event.target.closest('[data-brand-edit]');
            var remove = event.target.closest('[data-brand-delete]');
            var restore = event.target.closest('[data-brand-restore]');

            if (edit) {
                var id = parseInt(edit.getAttribute('data-brand-edit'), 10);
                var brand = state.brands.find(function (item) {
                    return item.id === id;
                });
                if (brand) {
                    fillForm(root, brand);
                }
            }
            if (remove) {
                deleteBrand(root, remove.getAttribute('data-brand-delete'));
            }
            if (restore) {
                restoreBrand(root, restore.getAttribute('data-brand-restore'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-brands]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        resetForm(root);
        fetchBrands(root, 1);
        fetchPublicBrands(root);

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                resetForm(root);
                var form = qs('[data-brand-form]', root);
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    var first = form.querySelector('input:not([type=hidden]),select,textarea');
                    if (first) { first.focus(); }
                }
            });
        }
        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showMessage(root, 'info', 'Accion no disponible en esta version.');
            });
        }
    });
})(window, document);
