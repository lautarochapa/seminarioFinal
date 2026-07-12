(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        total: 0,
        branches: [],
        editId: null,
        previewMap: null,
        previewMarker: null,
    };

    var CHAINS = [];
    var CITIES  = [];

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
        var el = qs('[data-branches-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-branches-message]', root);
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

    // ── Selectors ──────────────────────────────────────────────────────────────

    function loadSelectors(root) {
        Promise.all([
            window.CCApi.request(endpoint('/supermarkets')),
            window.CCApi.request(endpoint('/cities')),
        ]).then(function (results) {
            CHAINS = results[0].data || [];
            CITIES = results[1].data || [];
            populateChainSelects(root);
            populateCitySelects(root);
        }).catch(function () {});
    }

    function populateChainSelects(root) {
        var opts = CHAINS.map(function (c) {
            return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
        }).join('');

        qs('[data-branches-filter-chain]', root).innerHTML =
            '<option value="">Todas las cadenas</option>' + opts;

        qs('[data-branches-form-chain]', root).innerHTML =
            '<option value="">Seleccioná una cadena</option>' + opts;
    }

    function populateCitySelects(root) {
        var opts = CITIES.map(function (c) {
            return '<option value="' + c.id + '">' + escapeHtml(c.name) +
                (c.province ? ' (' + escapeHtml(c.province) + ')' : '') + '</option>';
        }).join('');

        qs('[data-branches-filter-city]', root).innerHTML =
            '<option value="">Todas las ciudades</option>' + opts;

        qs('[data-branches-form-city]', root).innerHTML =
            '<option value="">Seleccioná una ciudad</option>' + opts;
    }

    // ── Fetch & Render ─────────────────────────────────────────────────────────

    function fetchBranches(root, page) {
        state.page   = page || 1;
        var search   = (qs('[data-branches-search]', root).value || '').trim();
        var chainId  = qs('[data-branches-filter-chain]', root).value;
        var cityId   = qs('[data-branches-filter-city]', root).value;
        var status   = qs('[data-branches-filter-status]', root).value;

        var params = '?page=' + state.page +
            (search  ? '&search='   + encodeURIComponent(search)  : '') +
            (chainId ? '&chain_id=' + encodeURIComponent(chainId) : '') +
            (cityId  ? '&city_id='  + encodeURIComponent(cityId)  : '') +
            (status  ? '&status='   + encodeURIComponent(status)  : '');

        qs('[data-branches-body]', root).innerHTML =
            '<tr><td colspan="5" class="muted">Cargando...</td></tr>';

        window.CCApi.request(endpoint('/admin/supermarket-branches') + params)
            .then(function (r) {
                state.branches = r.data || [];
                state.total    = r.meta ? r.meta.total : 0;
                state.lastPage = r.meta ? r.meta.last_page : 1;
                state.page     = r.meta ? r.meta.current_page : 1;
                renderTable(root, state.branches);
                qs('[data-branches-count]', root).textContent = state.total + ' sucursales';
                qs('[data-branches-page]', root).textContent  =
                    'Pagina ' + state.page + ' de ' + state.lastPage;
            })
            .catch(function (err) {
                var msg = (err.payload && err.payload.error && err.payload.error.message) ||
                    'Error al cargar sucursales.';
                showMessage(root, 'danger', msg);
                qs('[data-branches-body]', root).innerHTML =
                    '<tr><td colspan="5" class="muted">Error al cargar.</td></tr>';
            });
    }

    function renderTable(root, branches) {
        var tbody = qs('[data-branches-body]', root);
        if (!branches.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="muted">No se encontraron sucursales.</td></tr>';
            return;
        }
        tbody.innerHTML = branches.map(function (b) {
            var actions = b.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-branches-edit="' + b.id + '">Editar</button> ' +
                  '<button type="button" class="btn-ghost btn-sm" style="color:var(--danger)" data-branches-deactivate="' + b.id + '">Desactivar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-branches-restore="' + b.id + '">Restaurar</button>';
            return '<tr>' +
                '<td><strong>' + escapeHtml(b.name) + '</strong><br>' +
                '<span class="muted" style="font-size:12px">' + escapeHtml(b.address) + '</span></td>' +
                '<td>' + escapeHtml(b.chain && b.chain.name) + '</td>' +
                '<td>' + escapeHtml(b.city  && b.city.name)  + '</td>' +
                '<td>' + statusChip(b.status) + '</td>' +
                '<td style="white-space:nowrap">' + actions + '</td>' +
                '</tr>';
        }).join('');
    }

    // ── Form ───────────────────────────────────────────────────────────────────

    function setEditMode(root, branch) {
        state.editId = branch.id;
        qs('[data-branches-form-title]', root).textContent = 'Editar sucursal';
        qs('[data-branches-submit]', root).textContent     = 'Guardar cambios';
        qs('[data-branches-edit-id]', root).value          = branch.id;

        var form = qs('[data-branches-form]', root);
        if (branch.chain) {
            form.elements.supermarket_chain_id.value = branch.chain.id || '';
        }
        if (branch.city) {
            form.elements.city_id.value = branch.city.id || '';
        }
        form.elements.name.value          = branch.name          || '';
        form.elements.address.value       = branch.address       || '';
        form.elements.latitude.value      = branch.latitude  !== null && branch.latitude  !== undefined ? branch.latitude  : '';
        form.elements.longitude.value     = branch.longitude !== null && branch.longitude !== undefined ? branch.longitude : '';
        form.elements.opening_hours.value = branch.opening_hours || '';
        form.elements.delivery_available.checked = !!branch.delivery_available;
        form.elements.pickup_available.checked   = !!branch.pickup_available;

        if (branch.latitude && branch.longitude) {
            updatePreviewMap(root, parseFloat(branch.latitude), parseFloat(branch.longitude));
        }
    }

    function resetForm(root) {
        state.editId = null;
        qs('[data-branches-form-title]', root).textContent = 'Nueva sucursal';
        qs('[data-branches-submit]', root).textContent     = 'Crear sucursal';
        qs('[data-branches-edit-id]', root).value          = '';
        qs('[data-branches-form]', root).reset();
        hidePreviewMap(root);
    }

    function formPayload(form) {
        var data = {};
        var chainId = form.elements.supermarket_chain_id.value;
        var cityId  = form.elements.city_id.value;
        if (chainId) { data.supermarket_chain_id = parseInt(chainId, 10); }
        if (cityId)  { data.city_id  = parseInt(cityId, 10); }

        ['name', 'address', 'opening_hours'].forEach(function (k) {
            var v = (form.elements[k].value || '').trim();
            if (v !== '') { data[k] = v; }
        });

        var lat = (form.elements.latitude.value  || '').trim();
        var lng = (form.elements.longitude.value || '').trim();
        if (lat !== '') { data.latitude  = parseFloat(lat); }
        if (lng !== '') { data.longitude = parseFloat(lng); }

        data.delivery_available = form.elements.delivery_available.checked ? 1 : 0;
        data.pickup_available   = form.elements.pickup_available.checked   ? 1 : 0;

        return data;
    }

    function submitForm(root) {
        clearMessage(root);
        var form    = qs('[data-branches-form]', root);
        var btn     = qs('[data-branches-submit]', root);
        var payload = formPayload(form);

        if (!payload.supermarket_chain_id) {
            showMessage(root, 'danger', 'Seleccioná una cadena de supermercado.');
            return;
        }
        if (!payload.city_id) {
            showMessage(root, 'danger', 'Seleccioná una ciudad.');
            return;
        }
        if (!payload.name) {
            showMessage(root, 'danger', 'El nombre de la sucursal es obligatorio.');
            return;
        }
        if (!payload.address) {
            showMessage(root, 'danger', 'La dirección es obligatoria.');
            return;
        }

        if (payload.latitude !== undefined && (payload.latitude < -90 || payload.latitude > 90)) {
            showMessage(root, 'danger', 'La latitud debe estar entre -90 y 90.');
            return;
        }
        if (payload.longitude !== undefined && (payload.longitude < -180 || payload.longitude > 180)) {
            showMessage(root, 'danger', 'La longitud debe estar entre -180 y 180.');
            return;
        }

        btn.disabled = true;
        var promise = state.editId
            ? window.CCApi.request(endpoint('/admin/supermarket-branches/' + state.editId), { method: 'PATCH', body: payload })
            : window.CCApi.request(endpoint('/admin/supermarket-branches'), { method: 'POST', body: payload });

        promise
            .then(function () {
                showMessage(root, 'success', state.editId ? 'Sucursal actualizada correctamente.' : 'Sucursal creada correctamente.');
                resetForm(root);
                fetchBranches(root, 1);
            })
            .catch(function (err) {
                var status = err.status || 0;
                var pl     = err.payload || {};
                var apiErr = pl.error || {};
                if (status === 409) {
                    showMessage(root, 'danger', apiErr.message || 'Conflicto: ya existe una sucursal con esos datos.');
                } else if (status === 422) {
                    var fields = apiErr.field_errors || {};
                    var msgs   = Object.keys(fields).map(function (k) { return fields[k][0]; });
                    showMessage(root, 'danger', msgs.length ? msgs.join(' ') : 'Datos inválidos.');
                } else {
                    showMessage(root, 'danger', apiErr.message || 'Error al guardar la sucursal.');
                }
            })
            .then(function () { btn.disabled = false; });
    }

    function deactivate(root, branchId) {
        if (!window.confirm('¿Desactivar esta sucursal?')) { return; }
        window.CCApi.request(endpoint('/admin/supermarket-branches/' + branchId), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Sucursal desactivada.');
                if (state.editId === branchId) { resetForm(root); }
                fetchBranches(root, state.page);
            })
            .catch(function (err) {
                var apiErr = ((err.payload || {}).error) || {};
                if (err.status === 409) {
                    showMessage(root, 'danger', apiErr.message || 'La sucursal tiene relaciones activas y no puede desactivarse.');
                } else {
                    showMessage(root, 'danger', apiErr.message || 'Error al desactivar la sucursal.');
                }
            });
    }

    function restore(root, branchId) {
        window.CCApi.request(endpoint('/admin/supermarket-branches/' + branchId + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Sucursal restaurada.');
                fetchBranches(root, state.page);
            })
            .catch(function (err) {
                var apiErr = ((err.payload || {}).error) || {};
                showMessage(root, 'danger', apiErr.message || 'Error al restaurar la sucursal.');
            });
    }

    // ── Mapa preview ───────────────────────────────────────────────────────────

    function updatePreviewMap(root, lat, lng) {
        if (!window.L) { return; }
        var el = qs('[data-branches-map-preview]', root);
        if (!el) { return; }

        el.style.display = 'block';

        if (!state.previewMap) {
            state.previewMap = window.L.map(el).setView([lat, lng], 15);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(state.previewMap);
            state.previewMarker = window.L.marker([lat, lng]).addTo(state.previewMap);
        } else {
            state.previewMap.invalidateSize();
            state.previewMap.setView([lat, lng], 15);
            if (state.previewMarker) {
                state.previewMarker.setLatLng([lat, lng]);
            } else {
                state.previewMarker = window.L.marker([lat, lng]).addTo(state.previewMap);
            }
        }
    }

    function hidePreviewMap(root) {
        var el = qs('[data-branches-map-preview]', root);
        if (el) { el.style.display = 'none'; }
    }

    function onCoordInput(root) {
        var lat = parseFloat((qs('[data-branches-lat]', root).value || '').trim());
        var lng = parseFloat((qs('[data-branches-lng]', root).value || '').trim());
        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
            updatePreviewMap(root, lat, lng);
        }
    }

    // ── Bind ───────────────────────────────────────────────────────────────────

    function bind(root) {
        qs('[data-branches-refresh]', root).addEventListener('click', function () {
            clearMessage(root);
            fetchBranches(root, 1);
        });

        qs('[data-branches-search]', root).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { fetchBranches(root, 1); }
        });

        ['[data-branches-filter-chain]', '[data-branches-filter-city]', '[data-branches-filter-status]']
            .forEach(function (sel) {
                qs(sel, root).addEventListener('change', function () { fetchBranches(root, 1); });
            });

        qs('[data-branches-prev]', root).addEventListener('click', function () {
            if (state.page > 1) { fetchBranches(root, state.page - 1); }
        });

        qs('[data-branches-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) { fetchBranches(root, state.page + 1); }
        });

        qs('[data-branches-form]', root).addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(root);
        });

        qs('[data-branches-reset]', root).addEventListener('click', function () {
            clearMessage(root);
            resetForm(root);
        });

        qs('[data-branches-lat]', root).addEventListener('input', function () { onCoordInput(root); });
        qs('[data-branches-lng]', root).addEventListener('input', function () { onCoordInput(root); });

        root.addEventListener('click', function (e) {
            var target = e.target;

            if (target.matches('[data-branches-edit]')) {
                var id     = parseInt(target.getAttribute('data-branches-edit'), 10);
                var branch = state.branches.find(function (b) { return b.id === id; });
                if (branch) { clearMessage(root); setEditMode(root, branch); }
            }

            if (target.matches('[data-branches-deactivate]')) {
                deactivate(root, parseInt(target.getAttribute('data-branches-deactivate'), 10));
            }

            if (target.matches('[data-branches-restore]')) {
                restore(root, parseInt(target.getAttribute('data-branches-restore'), 10));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-branches]');
        if (!root || !window.CCApi) { return; }
        bind(root);
        loadSelectors(root);
        fetchBranches(root, 1);
    });
})(window, document);
