(function (window, document) {
    'use strict';

    var state = {
        supplements: [],
        page: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        saving: false,
        selectedId: null,
        typeFilter: '',
        search: '',
    };

    var SUPP_TYPES = [
        { value: 'protein',  label: 'Proteína' },
        { value: 'creatine', label: 'Creatina' },
        { value: 'vitamin',  label: 'Vitamina' },
        { value: 'mineral',  label: 'Mineral' },
        { value: 'omega',    label: 'Omega / Aceite' },
        { value: 'preworkout', label: 'Pre-entreno' },
        { value: 'other',    label: 'Otro' },
    ];

    var TYPE_COLORS = {
        protein:    { bg: '#e7f3ff', color: '#1a5fb4' },
        creatine:   { bg: '#f3e7ff', color: '#6a1fb4' },
        vitamin:    { bg: '#fff3e0', color: '#b35c00' },
        mineral:    { bg: '#e7f7f2', color: '#04ac85' },
        omega:      { bg: '#f0f7e7', color: '#4a7c10' },
        preworkout: { bg: '#f7e7e7', color: '#b33a3a' },
        other:      { bg: '#f0f0f0', color: '#555' },
    };

    var STATUS_COLORS = {
        active:   { bg: '#e7f7f2', color: '#04ac85', label: 'Activo' },
        inactive: { bg: '#f0f0f0', color: '#555',    label: 'Inactivo' },
    };

    function qs(sel, root) { return (root || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v == null) { return ''; }
        return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fmt(n) {
        return Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function endpoint(path) { return path; }

    function errMsg(err) {
        if (err && err.message) { return err.message; }
        return 'Ocurrió un error inesperado.';
    }

    function typeBadge(type) {
        var c = TYPE_COLORS[type] || TYPE_COLORS.other;
        var t = SUPP_TYPES.find(function (x) { return x.value === type; });
        return '<span style="background:' + c.bg + ';color:' + c.color + ';border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700">' + escapeHtml(t ? t.label : (type || '?')) + '</span>';
    }

    function statusBadge(status) {
        var c = STATUS_COLORS[status] || STATUS_COLORS.inactive;
        return '<span style="background:' + c.bg + ';color:' + c.color + ';border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700">' + escapeHtml(c.label) + '</span>';
    }

    function showMsg(root, type, msg) {
        var el = qs('[data-supp-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearMsg(root) {
        var el = qs('[data-supp-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function showFormMsg(root, type, msg) {
        var el = qs('[data-supp-form-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearFormMsg(root) {
        var el = qs('[data-supp-form-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function resetForm(root) {
        var form = qs('[data-supp-form]', root);
        if (form) { form.reset(); }
        var idInput = qs('[data-supp-form] input[name="id"]', root);
        if (idInput) { idInput.value = ''; }
        var title = qs('[data-supp-form-title]', root);
        if (title) { title.textContent = 'Nuevo suplemento'; }
        var saveBtn = qs('[data-supp-save]', root);
        if (saveBtn) { saveBtn.textContent = 'Guardar'; }
        state.selectedId = null;
        clearFormMsg(root);
    }

    function fillForm(root, s) {
        state.selectedId = s.id;
        var form = qs('[data-supp-form]', root);
        if (!form) { return; }
        var f = form.elements;
        if (f.id)             { f.id.value = s.id; }
        if (f.name)           { f.name.value = s.name || ''; }
        if (f.type)           { f.type.value = s.type || ''; }
        if (f.brand)          { f.brand.value = s.brand || ''; }
        if (f.dose)           { f.dose.value = s.dose || ''; }
        if (f.unit)           { f.unit.value = s.unit || ''; }
        if (f.frequency)      { f.frequency.value = s.frequency || ''; }
        if (f.price_per_unit) { f.price_per_unit.value = s.price_per_unit || ''; }
        if (f.stock_quantity) { f.stock_quantity.value = s.stock_quantity || ''; }
        if (f.status)         { f.status.value = s.status || 'active'; }
        if (f.notes)          { f.notes.value = s.notes || ''; }
        var title = qs('[data-supp-form-title]', root);
        if (title) { title.textContent = 'Editar suplemento'; }
        var saveBtn = qs('[data-supp-save]', root);
        if (saveBtn) { saveBtn.textContent = 'Actualizar'; }
        clearFormMsg(root);
        var aside = qs('.aside-panel', root);
        if (aside) { aside.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    function renderList(root) {
        var listEl = qs('[data-supp-list]', root);
        var countEl = qs('[data-supp-count]', root);
        var pageEl = qs('[data-supp-page]', root);
        var prevBtn = qs('[data-supp-prev]', root);
        var nextBtn = qs('[data-supp-next]', root);

        if (countEl) { countEl.textContent = state.total + ' suplemento' + (state.total !== 1 ? 's' : ''); }
        if (pageEl)  { pageEl.textContent = 'Pág. ' + state.page + ' / ' + state.lastPage; }
        if (prevBtn) { prevBtn.disabled = state.loading || state.page <= 1; }
        if (nextBtn) { nextBtn.disabled = state.loading || state.page >= state.lastPage; }

        if (!listEl) { return; }

        if (state.loading) {
            listEl.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#66746b;padding:18px">Cargando...</td></tr>';
            return;
        }
        if (!state.supplements.length) {
            listEl.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#66746b;padding:18px">No se encontraron suplementos.</td></tr>';
            return;
        }

        listEl.innerHTML = state.supplements.map(function (s) {
            var dose = s.dose != null ? (escapeHtml(String(s.dose)) + (s.unit ? ' ' + escapeHtml(s.unit) : '')) : '-';
            var price = s.price_per_unit != null ? ('$' + escapeHtml(fmt(s.price_per_unit))) : '-';
            var stock = s.stock_quantity != null ? escapeHtml(String(s.stock_quantity)) : '-';
            return '<tr>' +
                '<td><strong>' + escapeHtml(s.name || '') + '</strong>' +
                (s.brand ? '<br><span style="font-size:11px;color:#66746b">' + escapeHtml(s.brand) + '</span>' : '') +
                '</td>' +
                '<td>' + typeBadge(s.type) + '</td>' +
                '<td style="white-space:nowrap">' + escapeHtml(dose) + '</td>' +
                '<td style="white-space:nowrap">' + escapeHtml(s.frequency || '-') + '</td>' +
                '<td style="white-space:nowrap">' + escapeHtml(price) + '</td>' +
                '<td>' + statusBadge(s.status || 'active') + '</td>' +
                '<td style="white-space:nowrap">' +
                '<button type="button" class="btn-secondary-web btn-sm" data-supp-edit="' + escapeHtml(String(s.id)) + '" style="margin-right:4px">Editar</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-supp-delete="' + escapeHtml(String(s.id)) + '">Eliminar</button>' +
                '</td>' +
                '</tr>';
        }).join('');
    }

    function loadSupplements(root) {
        state.loading = true;
        clearMsg(root);
        renderList(root);

        var params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', 15);
        if (state.typeFilter) { params.set('type', state.typeFilter); }
        if (state.search)     { params.set('search', state.search); }

        window.CCApi.request(endpoint('/api/v1/users/me/supplements?' + params.toString()))
            .then(function (response) {
                state.supplements = response.data || [];
                state.total       = (response.meta && response.meta.total) || state.supplements.length;
                state.page        = (response.meta && response.meta.current_page) || state.page;
                state.lastPage    = (response.meta && response.meta.last_page) || 1;
                state.loading     = false;
                renderList(root);
            })
            .catch(function (err) {
                state.supplements = [];
                state.loading     = false;
                renderList(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    function saveSuplement(root, form) {
        var f = form.elements;
        var name = f.name ? f.name.value.trim() : '';
        var type = f.type ? f.type.value : '';
        if (!name) { showFormMsg(root, 'warning', 'El nombre es obligatorio.'); return; }
        if (!type) { showFormMsg(root, 'warning', 'El tipo es obligatorio.'); return; }

        var payload = { name: name, type: type };
        if (f.brand && f.brand.value.trim())         { payload.brand = f.brand.value.trim(); }
        if (f.dose && f.dose.value !== '')            { payload.dose = parseFloat(f.dose.value); }
        if (f.unit && f.unit.value.trim())            { payload.unit = f.unit.value.trim(); }
        if (f.frequency && f.frequency.value)        { payload.frequency = f.frequency.value; }
        if (f.price_per_unit && f.price_per_unit.value !== '') { payload.price_per_unit = parseFloat(f.price_per_unit.value); }
        if (f.stock_quantity && f.stock_quantity.value !== '') { payload.stock_quantity = parseFloat(f.stock_quantity.value); }
        if (f.status && f.status.value)              { payload.status = f.status.value; }
        if (f.notes && f.notes.value.trim())         { payload.notes = f.notes.value.trim(); }

        state.saving = true;
        var saveBtn = qs('[data-supp-save]', root);
        if (saveBtn) { saveBtn.disabled = true; }
        clearFormMsg(root);

        var isEdit = !!state.selectedId;
        var url = endpoint('/api/v1/users/me/supplements' + (isEdit ? '/' + encodeURIComponent(state.selectedId) : ''));
        var method = isEdit ? 'PATCH' : 'POST';

        window.CCApi.request(url, { method: method, body: payload })
            .then(function () {
                state.saving = false;
                if (saveBtn) { saveBtn.disabled = false; }
                resetForm(root);
                state.page = 1;
                loadSupplements(root);
            })
            .catch(function (err) {
                state.saving = false;
                if (saveBtn) { saveBtn.disabled = false; }
                showFormMsg(root, 'danger', errMsg(err));
            });
    }

    function deleteSuplement(root, id) {
        if (!window.confirm('¿Eliminar este suplemento?')) { return; }

        window.CCApi.request(endpoint('/api/v1/users/me/supplements/' + encodeURIComponent(id)), { method: 'DELETE' })
            .then(function () {
                if (state.selectedId && String(state.selectedId) === String(id)) { resetForm(root); }
                state.page = 1;
                loadSupplements(root);
            })
            .catch(function (err) { showMsg(root, 'danger', errMsg(err)); });
    }

    function bind(root) {
        var searchInput = qs('[data-supp-search]', root);
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                state.search = searchInput.value;
                state.page = 1;
                loadSupplements(root);
            });
        }

        var typeFilter = qs('[data-supp-type-filter]', root);
        if (typeFilter) {
            typeFilter.addEventListener('change', function () {
                state.typeFilter = typeFilter.value;
                state.page = 1;
                loadSupplements(root);
            });
        }

        var prevBtn = qs('[data-supp-prev]', root);
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (state.page > 1) { state.page -= 1; loadSupplements(root); }
            });
        }

        var nextBtn = qs('[data-supp-next]', root);
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (state.page < state.lastPage) { state.page += 1; loadSupplements(root); }
            });
        }

        var form = qs('[data-supp-form]', root);
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                saveSuplement(root, form);
            });
        }

        var resetBtn = qs('[data-supp-reset]', root);
        if (resetBtn) {
            resetBtn.addEventListener('click', function () { resetForm(root); });
        }

        root.addEventListener('click', function (event) {
            var editBtn = event.target.closest('[data-supp-edit]');
            var delBtn  = event.target.closest('[data-supp-delete]');
            if (editBtn) {
                var id = editBtn.getAttribute('data-supp-edit');
                var supp = state.supplements.find(function (s) { return String(s.id) === String(id); });
                if (supp) { fillForm(root, supp); }
                return;
            }
            if (delBtn) {
                deleteSuplement(root, delBtn.getAttribute('data-supp-delete'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-supplements]');
        if (!root) { return; }
        bind(root);
        loadSupplements(root);
    });
})(window, document);
