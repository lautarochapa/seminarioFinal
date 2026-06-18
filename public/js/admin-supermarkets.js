(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        total: 0,
        chains: [],
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
        var el = qs('[data-supermarkets-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-supermarkets-message]', root);
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

    function fetchChains(root, page) {
        state.page  = page || 1;
        var search  = (qs('[data-supermarkets-search]', root).value || '').trim();
        var status  = qs('[data-supermarkets-filter-status]', root).value;
        var params  = '?page=' + state.page +
            (search ? '&search=' + encodeURIComponent(search) : '') +
            (status ? '&status=' + encodeURIComponent(status) : '');

        qs('[data-supermarkets-body]', root).innerHTML = '<tr><td colspan="4" class="muted">Cargando...</td></tr>';

        window.CCApi.request(endpoint('/admin/supermarket-chains') + params)
            .then(function (r) {
                state.chains   = r.data || [];
                state.total    = r.meta ? r.meta.total : 0;
                state.lastPage = r.meta ? r.meta.last_page : 1;
                state.page     = r.meta ? r.meta.current_page : 1;
                renderTable(root, state.chains);
                qs('[data-supermarkets-count]', root).textContent = state.total + ' cadenas';
                qs('[data-supermarkets-page]', root).textContent  = 'Pagina ' + state.page + ' de ' + state.lastPage;
            })
            .catch(function (err) {
                var msg = (err.payload && err.payload.error && err.payload.error.message) || 'Error al cargar cadenas.';
                showMessage(root, 'danger', msg);
                qs('[data-supermarkets-body]', root).innerHTML = '<tr><td colspan="4" class="muted">Error al cargar.</td></tr>';
            });
    }

    function renderTable(root, chains) {
        var tbody = qs('[data-supermarkets-body]', root);
        if (!chains.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="muted">No se encontraron cadenas.</td></tr>';
            return;
        }
        tbody.innerHTML = chains.map(function (c) {
            var website = c.website_url
                ? '<a href="' + escapeHtml(c.website_url) + '" target="_blank" rel="noopener noreferrer" style="font-size:12px">' + escapeHtml(c.website_url) + '</a>'
                : '<span class="muted">-</span>';
            var actions = c.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-supermarkets-edit="' + c.id + '">Editar</button> ' +
                  '<button type="button" class="btn-ghost btn-sm" style="color:var(--danger)" data-supermarkets-deactivate="' + c.id + '">Desactivar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-supermarkets-restore="' + c.id + '">Restaurar</button>';
            return '<tr>' +
                '<td><strong>' + escapeHtml(c.name) + '</strong></td>' +
                '<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + website + '</td>' +
                '<td>' + statusChip(c.status) + '</td>' +
                '<td style="white-space:nowrap">' + actions + '</td>' +
                '</tr>';
        }).join('');
    }

    function setEditMode(root, chain) {
        state.editId = chain.id;
        qs('[data-supermarkets-form-title]', root).textContent = 'Editar cadena';
        qs('[data-supermarkets-submit]', root).textContent     = 'Guardar cambios';
        qs('[data-supermarkets-edit-id]', root).value          = chain.id;
        var form = qs('[data-supermarkets-form]', root);
        form.elements.name.value        = chain.name        || '';
        form.elements.website_url.value = chain.website_url || '';
    }

    function resetForm(root) {
        state.editId = null;
        qs('[data-supermarkets-form-title]', root).textContent = 'Nueva cadena';
        qs('[data-supermarkets-submit]', root).textContent     = 'Crear cadena';
        qs('[data-supermarkets-edit-id]', root).value          = '';
        qs('[data-supermarkets-form]', root).reset();
    }

    function formPayload(form) {
        var data = {};
        var name = (form.elements.name.value || '').trim();
        if (name) { data.name = name; }
        var url = (form.elements.website_url.value || '').trim();
        if (url) { data.website_url = url; } else { data.website_url = null; }
        return data;
    }

    function submitForm(root) {
        clearMessage(root);
        var form    = qs('[data-supermarkets-form]', root);
        var btn     = qs('[data-supermarkets-submit]', root);
        var payload = formPayload(form);

        if (!payload.name) {
            showMessage(root, 'danger', 'El nombre de la cadena es obligatorio.');
            return;
        }

        btn.disabled = true;
        var promise = state.editId
            ? window.CCApi.request(endpoint('/admin/supermarket-chains/' + state.editId), { method: 'PATCH', body: payload })
            : window.CCApi.request(endpoint('/admin/supermarket-chains'), { method: 'POST', body: payload });

        promise
            .then(function () {
                showMessage(root, 'success', state.editId ? 'Cadena actualizada correctamente.' : 'Cadena creada correctamente.');
                resetForm(root);
                fetchChains(root, 1);
            })
            .catch(function (err) {
                var status  = err.status || 0;
                var pl      = err.payload || {};
                var apiErr  = pl.error || {};
                if (status === 409) {
                    showMessage(root, 'danger', apiErr.message || 'Ya existe una cadena con ese nombre.');
                } else if (status === 422) {
                    var fields = apiErr.field_errors || {};
                    var msgs   = Object.keys(fields).map(function (k) { return fields[k][0]; });
                    showMessage(root, 'danger', msgs.length ? msgs.join(' ') : 'Datos inválidos.');
                } else {
                    showMessage(root, 'danger', apiErr.message || 'Error al guardar la cadena.');
                }
            })
            .then(function () { btn.disabled = false; });
    }

    function deactivate(root, chainId) {
        if (!window.confirm('¿Desactivar esta cadena de supermercados?')) { return; }
        window.CCApi.request(endpoint('/admin/supermarket-chains/' + chainId), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Cadena desactivada.');
                if (state.editId === chainId) { resetForm(root); }
                fetchChains(root, state.page);
            })
            .catch(function (err) {
                var apiErr = ((err.payload || {}).error) || {};
                if (err.status === 409) {
                    showMessage(root, 'danger', apiErr.message || 'La cadena tiene sucursales, precios o promociones activas y no puede desactivarse.');
                } else {
                    showMessage(root, 'danger', apiErr.message || 'Error al desactivar la cadena.');
                }
            });
    }

    function restore(root, chainId) {
        window.CCApi.request(endpoint('/admin/supermarket-chains/' + chainId + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Cadena restaurada.');
                fetchChains(root, state.page);
            })
            .catch(function (err) {
                var apiErr = ((err.payload || {}).error) || {};
                showMessage(root, 'danger', apiErr.message || 'Error al restaurar la cadena.');
            });
    }

    function bind(root) {
        qs('[data-supermarkets-refresh]', root).addEventListener('click', function () {
            clearMessage(root);
            fetchChains(root, 1);
        });

        qs('[data-supermarkets-search]', root).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { fetchChains(root, 1); }
        });

        qs('[data-supermarkets-filter-status]', root).addEventListener('change', function () {
            fetchChains(root, 1);
        });

        qs('[data-supermarkets-prev]', root).addEventListener('click', function () {
            if (state.page > 1) { fetchChains(root, state.page - 1); }
        });

        qs('[data-supermarkets-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) { fetchChains(root, state.page + 1); }
        });

        qs('[data-supermarkets-form]', root).addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(root);
        });

        qs('[data-supermarkets-reset]', root).addEventListener('click', function () {
            clearMessage(root);
            resetForm(root);
        });

        root.addEventListener('click', function (e) {
            var target = e.target;

            if (target.matches('[data-supermarkets-edit]')) {
                var chainId = parseInt(target.getAttribute('data-supermarkets-edit'), 10);
                var chain   = state.chains.find(function (c) { return c.id === chainId; });
                if (chain) { clearMessage(root); setEditMode(root, chain); }
            }

            if (target.matches('[data-supermarkets-deactivate]')) {
                deactivate(root, parseInt(target.getAttribute('data-supermarkets-deactivate'), 10));
            }

            if (target.matches('[data-supermarkets-restore]')) {
                restore(root, parseInt(target.getAttribute('data-supermarkets-restore'), 10));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-supermarkets]');
        if (!root || !window.CCApi) { return; }
        bind(root);
        fetchChains(root, 1);
    });
})(window, document);
