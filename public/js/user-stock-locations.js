(function (window, document) {
    'use strict';

    var API_BASE = '/api/v1';

    var state = {
        groups: [],
        currentGroupId: null,
        locations: [],
        page: 1,
        lastPage: 1,
        loading: false,
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

    function showMessage(root, type, message) {
        var alert = qs('[data-stock-locations-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-stock-locations-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = '';
        alert.className = 'alert';
        alert.style.display = 'none';
    }

    function apiErrorMessage(error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};

        if (error.status === 401) {
            return 'La sesion vencio. Inicia sesion nuevamente.';
        }

        if (error.status === 403) {
            return 'No tenes permiso para modificar ubicaciones de este grupo.';
        }

        if (error.status === 404) {
            return 'No se encontro el grupo o la ubicacion solicitada.';
        }

        if (error.status === 409) {
            return apiError.message || 'Ya existe una ubicacion activa con esos datos.';
        }

        if (error.status === 422) {
            return apiError.message || 'Revisa los datos ingresados.';
        }

        return apiError.message || error.message || 'No se pudo completar la operacion.';
    }

    function handleError(root, error) {
        showMessage(root, 'danger', apiErrorMessage(error));
    }

    function selectedStatus(root) {
        var select = qs('[data-stock-location-status]', root);
        return select ? select.value : '';
    }

    function endpoint(groupId, suffix) {
        return API_BASE + '/family-groups/' + encodeURIComponent(groupId) + '/stock-locations' + (suffix || '');
    }

    function renderGroups(root) {
        var select = qs('[data-stock-group-select]', root);
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

    function renderLoading(root) {
        var body = qs('[data-stock-locations-body]', root);
        if (body) {
            body.innerHTML = '<tr><td colspan="5" class="muted">Cargando ubicaciones...</td></tr>';
        }
    }

    function renderLocations(root) {
        var body = qs('[data-stock-locations-body]', root);
        var count = qs('[data-stock-locations-count]', root);
        var page = qs('[data-stock-locations-page]', root);
        var prev = qs('[data-stock-locations-prev]', root);
        var next = qs('[data-stock-locations-next]', root);

        if (count) {
            count.textContent = state.locations.length + (state.locations.length === 1 ? ' ubicacion' : ' ubicaciones');
        }

        if (page) {
            page.textContent = 'Pagina ' + state.page + ' de ' + state.lastPage;
        }

        if (prev) {
            prev.disabled = state.page <= 1 || state.loading;
        }

        if (next) {
            next.disabled = state.page >= state.lastPage || state.loading;
        }

        if (!body) {
            return;
        }

        if (!state.currentGroupId) {
            body.innerHTML = '<tr><td colspan="5" class="muted">Selecciona un grupo familiar.</td></tr>';
            return;
        }

        if (!state.locations.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">No hay ubicaciones para mostrar.</td></tr>';
            return;
        }

        body.innerHTML = state.locations.map(function (location) {
            return '<tr>' +
                '<td><strong>' + escapeHtml(location.name) + '</strong></td>' +
                '<td>' + escapeHtml(location.type) + '</td>' +
                '<td><span class="chip">' + escapeHtml(location.status) + '</span></td>' +
                '<td>' + escapeHtml(location.updated_at || location.created_at) + '</td>' +
                '<td>' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-location-edit="' + location.id + '">Editar</button> ' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-stock-location-delete="' + location.id + '">Eliminar</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function resetForm(root) {
        var form = qs('[data-stock-location-form]', root);
        var title = qs('[data-stock-location-form-title]', root);
        var cancel = qs('[data-stock-location-cancel]', root);

        if (form) {
            form.reset();
            form.elements.id.value = '';
            form.elements.status.value = 'active';
        }

        if (title) {
            title.textContent = 'Nueva ubicacion';
        }

        if (cancel) {
            cancel.style.display = 'none';
        }
    }

    function fillForm(root, location) {
        var form = qs('[data-stock-location-form]', root);
        var title = qs('[data-stock-location-form-title]', root);
        var cancel = qs('[data-stock-location-cancel]', root);

        if (!form || !location) {
            return;
        }

        form.elements.id.value = location.id;
        form.elements.name.value = location.name || '';
        form.elements.type.value = location.type || '';
        form.elements.status.value = location.status || 'active';

        if (title) {
            title.textContent = 'Editar ubicacion';
        }

        if (cancel) {
            cancel.style.display = 'inline-flex';
        }
    }

    function loadGroups(root) {
        clearMessage(root);

        return window.CCApi.request(API_BASE + '/family-groups')
            .then(function (response) {
                state.groups = response.data || [];
                state.currentGroupId = state.groups.length ? state.groups[0].id : null;
                renderGroups(root);

                if (!state.currentGroupId) {
                    renderLocations(root);
                    showMessage(root, 'warning', 'Necesitas un grupo familiar para administrar ubicaciones de stock.');
                    return null;
                }

                return loadLocations(root);
            })
            .catch(function (error) {
                state.groups = [];
                state.currentGroupId = null;
                renderGroups(root);
                renderLocations(root);
                handleError(root, error);
            });
    }

    function loadLocations(root) {
        if (!state.currentGroupId) {
            renderLocations(root);
            return Promise.resolve();
        }

        state.loading = true;
        renderLoading(root);
        clearMessage(root);

        var params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', 20);
        if (selectedStatus(root)) {
            params.set('status', selectedStatus(root));
        }

        return window.CCApi.request(endpoint(state.currentGroupId) + '?' + params.toString())
            .then(function (response) {
                state.locations = response.data || [];
                state.page = response.meta ? response.meta.current_page : 1;
                state.lastPage = response.meta ? response.meta.last_page : 1;
                state.loading = false;
                renderLocations(root);
            })
            .catch(function (error) {
                state.locations = [];
                state.loading = false;
                renderLocations(root);
                handleError(root, error);
            });
    }

    function saveLocation(root, event) {
        event.preventDefault();

        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Selecciona un grupo familiar.');
            return;
        }

        var form = event.currentTarget;
        var submit = qs('[data-stock-location-submit]', root);
        var id = form.elements.id.value;
        var body = {
            name: form.elements.name.value.trim(),
            status: form.elements.status.value,
        };

        if (form.elements.type.value.trim()) {
            body.type = form.elements.type.value.trim();
        } else {
            body.type = null;
        }

        if (submit) {
            submit.disabled = true;
        }

        clearMessage(root);

        return window.CCApi.request(endpoint(state.currentGroupId, id ? '/' + encodeURIComponent(id) : ''), {
            method: id ? 'PATCH' : 'POST',
            body: body,
        })
            .then(function () {
                resetForm(root);
                showMessage(root, 'success', id ? 'Ubicacion actualizada.' : 'Ubicacion creada.');
                return loadLocations(root);
            })
            .catch(function (error) {
                handleError(root, error);
            })
            .then(function () {
                if (submit) {
                    submit.disabled = false;
                }
            });
    }

    function deleteLocation(root, id) {
        if (!state.currentGroupId || !id) {
            return;
        }

        if (!window.confirm('Desactivar esta ubicacion?')) {
            return;
        }

        clearMessage(root);

        return window.CCApi.request(endpoint(state.currentGroupId, '/' + encodeURIComponent(id)), {
            method: 'DELETE',
        })
            .then(function () {
                showMessage(root, 'success', 'Ubicacion eliminada.');
                return loadLocations(root);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function bind(root) {
        var groupSelect = qs('[data-stock-group-select]', root);
        var statusSelect = qs('[data-stock-location-status]', root);
        var refresh = qs('[data-stock-locations-refresh]', root);
        var form = qs('[data-stock-location-form]', root);
        var cancel = qs('[data-stock-location-cancel]', root);
        var prev = qs('[data-stock-locations-prev]', root);
        var next = qs('[data-stock-locations-next]', root);

        if (groupSelect) {
            groupSelect.addEventListener('change', function () {
                state.currentGroupId = groupSelect.value || null;
                state.page = 1;
                resetForm(root);
                loadLocations(root);
            });
        }

        if (statusSelect) {
            statusSelect.addEventListener('change', function () {
                state.page = 1;
                loadLocations(root);
            });
        }

        if (refresh) {
            refresh.addEventListener('click', function () {
                loadLocations(root);
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                saveLocation(root, event);
            });
        }

        if (cancel) {
            cancel.addEventListener('click', function () {
                resetForm(root);
                clearMessage(root);
            });
        }

        if (prev) {
            prev.addEventListener('click', function () {
                if (state.page > 1) {
                    state.page -= 1;
                    loadLocations(root);
                }
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                if (state.page < state.lastPage) {
                    state.page += 1;
                    loadLocations(root);
                }
            });
        }

        root.addEventListener('click', function (event) {
            var edit = event.target.closest('[data-stock-location-edit]');
            var remove = event.target.closest('[data-stock-location-delete]');

            if (edit) {
                var editId = edit.getAttribute('data-stock-location-edit');
                var location = state.locations.find(function (item) {
                    return String(item.id) === String(editId);
                });
                fillForm(root, location);
            }

            if (remove) {
                deleteLocation(root, remove.getAttribute('data-stock-location-delete'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-stock-locations]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        loadGroups(root);
    });
})(window, document);
