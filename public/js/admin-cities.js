(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        total: 0,
        cities: [],
        editId: null,
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined || v === '') { return '-'; }
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function showMessage(root, type, msg) {
        var el = qs('[data-cities-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-cities-message]', root);
        if (!el) { return; }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function statusChip(status) {
        return status === 'active'
            ? '<span style="background:#e7f7f2;color:#04ac85;padding:2px 8px;border-radius:50px;font-size:12px">Activa</span>'
            : '<span style="background:#fdecea;color:#b33a3a;padding:2px 8px;border-radius:50px;font-size:12px">Inactiva</span>';
    }

    function fetchCities(root, page) {
        state.page  = page || 1;
        var search  = (qs('[data-cities-search]', root).value || '').trim();
        var status  = qs('[data-cities-filter-status]', root).value;
        var params  = '?page=' + state.page +
            (search ? '&search=' + encodeURIComponent(search) : '') +
            (status ? '&status=' + encodeURIComponent(status) : '');

        qs('[data-cities-body]', root).innerHTML = '<tr><td colspan="5" class="muted">Cargando...</td></tr>';

        window.CCApi.request(endpoint('/admin/cities') + params)
            .then(function (r) {
                state.cities   = r.data || [];
                state.total    = r.meta ? r.meta.total : 0;
                state.lastPage = r.meta ? r.meta.last_page : 1;
                state.page     = r.meta ? r.meta.current_page : 1;
                renderTable(root, state.cities);
                qs('[data-cities-count]', root).textContent = state.total + ' ciudades';
                qs('[data-cities-page]', root).textContent  = 'Pagina ' + state.page + ' de ' + state.lastPage;
            })
            .catch(function (err) {
                var msg = (err.payload && err.payload.error && err.payload.error.message) || 'Error al cargar ciudades.';
                showMessage(root, 'danger', msg);
                qs('[data-cities-body]', root).innerHTML = '<tr><td colspan="5" class="muted">Error al cargar.</td></tr>';
            });
    }

    function renderTable(root, cities) {
        var tbody = qs('[data-cities-body]', root);
        if (!cities.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="muted">No se encontraron ciudades.</td></tr>';
            return;
        }
        tbody.innerHTML = cities.map(function (c) {
            var actions = c.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-cities-edit="' + c.id + '">Editar</button> ' +
                  '<button type="button" class="btn-ghost btn-sm" style="color:var(--danger)" data-cities-deactivate="' + c.id + '">Desactivar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-cities-restore="' + c.id + '">Restaurar</button>';
            return '<tr>' +
                '<td><strong>' + escapeHtml(c.name) + '</strong></td>' +
                '<td>' + escapeHtml(c.province) + '</td>' +
                '<td>' + escapeHtml(c.country) + '</td>' +
                '<td>' + statusChip(c.status) + '</td>' +
                '<td style="white-space:nowrap">' + actions + '</td>' +
                '</tr>';
        }).join('');
    }

    function setEditMode(root, city) {
        state.editId = city.id;
        qs('[data-cities-form-title]', root).textContent    = 'Editar ciudad';
        qs('[data-cities-submit]', root).textContent        = 'Guardar cambios';
        qs('[data-cities-edit-id]', root).value             = city.id;
        var form = qs('[data-cities-form]', root);
        form.elements.name.value      = city.name      || '';
        form.elements.province.value  = city.province  || '';
        form.elements.country.value   = city.country   || '';
        form.elements.latitude.value  = city.latitude  !== null && city.latitude  !== undefined ? city.latitude  : '';
        form.elements.longitude.value = city.longitude !== null && city.longitude !== undefined ? city.longitude : '';
    }

    function resetForm(root) {
        state.editId = null;
        qs('[data-cities-form-title]', root).textContent = 'Nueva ciudad';
        qs('[data-cities-submit]', root).textContent     = 'Crear ciudad';
        qs('[data-cities-edit-id]', root).value          = '';
        qs('[data-cities-form]', root).reset();
    }

    function formPayload(form) {
        var data = {};
        ['name', 'province', 'country'].forEach(function (k) {
            var v = (form.elements[k].value || '').trim();
            if (v !== '') { data[k] = v; }
        });
        ['latitude', 'longitude'].forEach(function (k) {
            var v = (form.elements[k].value || '').trim();
            if (v !== '') { data[k] = v; }
        });
        return data;
    }

    function submitForm(root) {
        clearMessage(root);
        var form    = qs('[data-cities-form]', root);
        var btn     = qs('[data-cities-submit]', root);
        var payload = formPayload(form);

        if (!payload.name) {
            showMessage(root, 'danger', 'El nombre de la ciudad es obligatorio.');
            return;
        }

        btn.disabled = true;
        var promise = state.editId
            ? window.CCApi.request(endpoint('/admin/cities/' + state.editId), { method: 'PATCH', body: payload })
            : window.CCApi.request(endpoint('/admin/cities'), { method: 'POST', body: payload });

        promise
            .then(function () {
                showMessage(root, 'success', state.editId ? 'Ciudad actualizada correctamente.' : 'Ciudad creada correctamente.');
                resetForm(root);
                fetchCities(root, 1);
            })
            .catch(function (err) {
                var status  = err.status || 0;
                var pl      = err.payload || {};
                var apiErr  = pl.error || {};
                if (status === 409) {
                    showMessage(root, 'danger', apiErr.message || 'Ya existe una ciudad con ese nombre.');
                } else if (status === 422) {
                    var fields = apiErr.field_errors || {};
                    var msgs   = Object.keys(fields).map(function (k) { return fields[k][0]; });
                    showMessage(root, 'danger', msgs.length ? msgs.join(' ') : 'Datos inválidos.');
                } else {
                    showMessage(root, 'danger', apiErr.message || 'Error al guardar la ciudad.');
                }
            })
            .then(function () { btn.disabled = false; });
    }

    function deactivate(root, cityId) {
        if (!window.confirm('¿Desactivar esta ciudad?')) { return; }
        window.CCApi.request(endpoint('/admin/cities/' + cityId), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Ciudad desactivada.');
                if (state.editId === cityId) { resetForm(root); }
                fetchCities(root, state.page);
            })
            .catch(function (err) {
                var apiErr = ((err.payload || {}).error) || {};
                if (err.status === 409) {
                    showMessage(root, 'danger', apiErr.message || 'La ciudad tiene sucursales o fuentes de scraping asociadas y no puede desactivarse.');
                } else {
                    showMessage(root, 'danger', apiErr.message || 'Error al desactivar la ciudad.');
                }
            });
    }

    function restore(root, cityId) {
        window.CCApi.request(endpoint('/admin/cities/' + cityId + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Ciudad restaurada.');
                fetchCities(root, state.page);
            })
            .catch(function (err) {
                var apiErr = ((err.payload || {}).error) || {};
                showMessage(root, 'danger', apiErr.message || 'Error al restaurar la ciudad.');
            });
    }

    function bind(root) {
        qs('[data-cities-refresh]', root).addEventListener('click', function () {
            clearMessage(root);
            fetchCities(root, 1);
        });

        qs('[data-cities-search]', root).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { fetchCities(root, 1); }
        });

        qs('[data-cities-filter-status]', root).addEventListener('change', function () {
            fetchCities(root, 1);
        });

        qs('[data-cities-prev]', root).addEventListener('click', function () {
            if (state.page > 1) { fetchCities(root, state.page - 1); }
        });

        qs('[data-cities-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) { fetchCities(root, state.page + 1); }
        });

        qs('[data-cities-form]', root).addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(root);
        });

        qs('[data-cities-reset]', root).addEventListener('click', function () {
            clearMessage(root);
            resetForm(root);
        });

        root.addEventListener('click', function (e) {
            var target = e.target;

            if (target.matches('[data-cities-edit]')) {
                var cityId = parseInt(target.getAttribute('data-cities-edit'), 10);
                var city   = state.cities.find(function (c) { return c.id === cityId; });
                if (city) { clearMessage(root); setEditMode(root, city); }
            }

            if (target.matches('[data-cities-deactivate]')) {
                deactivate(root, parseInt(target.getAttribute('data-cities-deactivate'), 10));
            }

            if (target.matches('[data-cities-restore]')) {
                restore(root, parseInt(target.getAttribute('data-cities-restore'), 10));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-cities]');
        if (!root || !window.CCApi) { return; }
        bind(root);
        fetchCities(root, 1);
    });
})(window, document);
