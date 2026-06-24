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
        schedSuppId: null,
        schedSupp: null,
        schedules: [],
        schedLoading: false,
        schedSaving: false,
        selectedSchedId: null,
        logSaving: false,
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
                '<button type="button" class="btn-secondary-web btn-sm" data-supp-sched="' + escapeHtml(String(s.id)) + '" style="margin-right:4px">Horario</button>' +
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

    var DAY_LABELS = {
        every_day: 'Todos los días',
        weekdays:  'Días de semana',
        weekends:  'Fines de semana',
        monday:    'Lunes',
        tuesday:   'Martes',
        wednesday: 'Miércoles',
        thursday:  'Jueves',
        friday:    'Viernes',
        saturday:  'Sábado',
        sunday:    'Domingo',
    };

    function schedPath(suppId, extra) {
        return endpoint('/api/v1/users/me/supplements/' + encodeURIComponent(suppId) + (extra || ''));
    }

    function showSchedMsg(root, type, msg) {
        var el = qs('[data-sched-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearSchedMsg(root) {
        var el = qs('[data-sched-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function showSchedFormMsg(root, type, msg) {
        var el = qs('[data-sched-form-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearSchedFormMsg(root) {
        var el = qs('[data-sched-form-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function showLogMsg(root, type, msg) {
        var el = qs('[data-log-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearLogMsg(root) {
        var el = qs('[data-log-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    function resetSchedForm(root) {
        var form = qs('[data-sched-form]', root);
        if (form) { form.reset(); }
        var idEl = qs('[data-sched-form] input[name="id"]', root);
        if (idEl) { idEl.value = ''; }
        var title = qs('[data-sched-form-title]', root);
        if (title) { title.textContent = 'Nuevo horario'; }
        var saveBtn = qs('[data-sched-save]', root);
        if (saveBtn) { saveBtn.textContent = 'Agregar'; }
        state.selectedSchedId = null;
        clearSchedFormMsg(root);
    }

    function fillSchedForm(root, s) {
        state.selectedSchedId = s.id;
        var form = qs('[data-sched-form]', root);
        if (!form) { return; }
        var f = form.elements;
        if (f.id)            { f.id.value = s.id; }
        if (f.time)          { f.time.value = s.time || ''; }
        if (f.day_type)      { f.day_type.value = s.day_type || 'every_day'; }
        if (f.dose_override) { f.dose_override.value = s.dose_override != null ? s.dose_override : ''; }
        if (f.notes)         { f.notes.value = s.notes || ''; }
        if (f.active)        { f.active.value = s.active === false ? '0' : '1'; }
        var title = qs('[data-sched-form-title]', root);
        if (title) { title.textContent = 'Editar horario'; }
        var saveBtn = qs('[data-sched-save]', root);
        if (saveBtn) { saveBtn.textContent = 'Actualizar'; }
        clearSchedFormMsg(root);
    }

    function renderSchedulePanel(root) {
        var panel = qs('[data-supp-schedule-panel]', root);
        if (!panel) { return; }
        if (!state.schedSuppId) { panel.style.display = 'none'; return; }
        panel.style.display = '';

        var titleEl = qs('[data-sched-title]', root);
        if (titleEl) {
            titleEl.textContent = 'Horarios — ' + (state.schedSupp ? escapeHtml(state.schedSupp.name) : '#' + state.schedSuppId);
        }

        var listEl = qs('[data-sched-list]', root);
        if (!listEl) { return; }

        if (state.schedLoading) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">Cargando horarios...</p>';
            return;
        }
        if (!state.schedules.length) {
            listEl.innerHTML = '<p style="font-size:13px;color:#66746b;margin:0">Sin horarios configurados. Agregá uno abajo.</p>';
            return;
        }

        listEl.innerHTML = state.schedules.map(function (s) {
            var isActive = s.active !== false;
            var dayLabel = DAY_LABELS[s.day_type] || (s.day_type || '-');
            var time = s.time ? s.time.substring(0, 5) : '-';
            var supp = state.schedSupp;
            var doseUnit = supp ? (supp.unit || '') : '';
            var doseBase = supp ? (supp.dose != null ? supp.dose : null) : null;
            var doseDisplay = s.dose_override != null
                ? escapeHtml(String(s.dose_override)) + (doseUnit ? ' ' + escapeHtml(doseUnit) : '')
                : (doseBase != null ? escapeHtml(String(doseBase)) + (doseUnit ? ' ' + escapeHtml(doseUnit) : '') + ' (base)' : '-');
            return '<div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #f0f0f0">' +
                '<div style="flex:1;min-width:0">' +
                '<div style="font-size:14px;font-weight:700">' + escapeHtml(time) +
                '<span style="font-size:12px;font-weight:400;color:#66746b;margin-left:8px">' + escapeHtml(dayLabel) + '</span>' +
                '</div>' +
                '<div style="font-size:12px;color:#66746b;margin-top:2px">Dosis: ' + doseDisplay +
                (s.notes ? ' · ' + escapeHtml(s.notes) : '') + '</div>' +
                '</div>' +
                '<span style="font-size:11px;border-radius:999px;padding:2px 7px;background:' + (isActive ? '#e7f7f2' : '#f0f0f0') + ';color:' + (isActive ? '#04ac85' : '#888') + '">' + (isActive ? 'Activo' : 'Inactivo') + '</span>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-sched-edit="' + escapeHtml(String(s.id)) + '" style="font-size:11px">Editar</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-sched-delete="' + escapeHtml(String(s.id)) + '" style="font-size:11px">✕</button>' +
                '</div>';
        }).join('');
    }

    function loadSchedules(root, suppId) {
        var supp = state.supplements.find(function (s) { return String(s.id) === String(suppId); });
        state.schedSuppId   = suppId;
        state.schedSupp     = supp || null;
        state.schedules     = [];
        state.schedLoading  = true;
        state.selectedSchedId = null;
        renderSchedulePanel(root);
        resetSchedForm(root);

        var panel = qs('[data-supp-schedule-panel]', root);
        if (panel) { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }

        window.CCApi.request(schedPath(suppId, '/schedule'))
            .then(function (response) {
                state.schedules    = response.data || [];
                state.schedLoading = false;
                renderSchedulePanel(root);
            })
            .catch(function (err) {
                state.schedules    = [];
                state.schedLoading = false;
                renderSchedulePanel(root);
                showSchedMsg(root, 'danger', errMsg(err));
            });
    }

    function saveSchedule(root, form) {
        if (!state.schedSuppId) { return; }
        var f = form.elements;
        var time = f.time ? f.time.value : '';
        var dayType = f.day_type ? f.day_type.value : '';
        if (!time) { showSchedFormMsg(root, 'warning', 'El horario es obligatorio.'); return; }
        if (!dayType) { showSchedFormMsg(root, 'warning', 'El día es obligatorio.'); return; }

        var payload = { time: time, day_type: dayType };
        if (f.dose_override && f.dose_override.value !== '') { payload.dose_override = parseFloat(f.dose_override.value); }
        if (f.notes && f.notes.value.trim()) { payload.notes = f.notes.value.trim(); }
        payload.active = !(f.active && f.active.value === '0');

        state.schedSaving = true;
        var saveBtn = qs('[data-sched-save]', root);
        if (saveBtn) { saveBtn.disabled = true; }
        clearSchedFormMsg(root);

        var isEdit = !!state.selectedSchedId;
        var url = schedPath(state.schedSuppId, '/schedule' + (isEdit ? '/' + encodeURIComponent(state.selectedSchedId) : ''));
        var method = isEdit ? 'PATCH' : 'POST';

        window.CCApi.request(url, { method: method, body: payload })
            .then(function () {
                state.schedSaving = false;
                if (saveBtn) { saveBtn.disabled = false; }
                resetSchedForm(root);
                loadSchedules(root, state.schedSuppId);
            })
            .catch(function (err) {
                state.schedSaving = false;
                if (saveBtn) { saveBtn.disabled = false; }
                showSchedFormMsg(root, 'danger', errMsg(err));
            });
    }

    function deleteSchedule(root, schedId) {
        if (!state.schedSuppId) { return; }
        if (!window.confirm('¿Eliminar este horario?')) { return; }

        window.CCApi.request(schedPath(state.schedSuppId, '/schedule/' + encodeURIComponent(schedId)), { method: 'DELETE' })
            .then(function () {
                if (state.selectedSchedId && String(state.selectedSchedId) === String(schedId)) { resetSchedForm(root); }
                loadSchedules(root, state.schedSuppId);
            })
            .catch(function (err) { showSchedMsg(root, 'danger', errMsg(err)); });
    }

    function logConsumption(root, form) {
        if (!state.schedSuppId) { return; }
        var f = form.elements;
        var payload = {};
        if (f.taken_at && f.taken_at.value) { payload.taken_at = f.taken_at.value; }
        if (f.dose_actual && f.dose_actual.value !== '') { payload.dose_actual = parseFloat(f.dose_actual.value); }
        if (f.log_notes && f.log_notes.value.trim()) { payload.notes = f.log_notes.value.trim(); }

        state.logSaving = true;
        var logBtn = qs('[data-log-save]', root);
        if (logBtn) { logBtn.disabled = true; logBtn.textContent = 'Registrando...'; }
        clearLogMsg(root);

        window.CCApi.request(schedPath(state.schedSuppId, '/log'), { method: 'POST', body: payload })
            .then(function () {
                state.logSaving = false;
                if (logBtn) { logBtn.disabled = false; logBtn.textContent = 'Registrar consumo'; }
                form.reset();
                showLogMsg(root, 'success', 'Consumo registrado correctamente.');
            })
            .catch(function (err) {
                state.logSaving = false;
                if (logBtn) { logBtn.disabled = false; logBtn.textContent = 'Registrar consumo'; }
                showLogMsg(root, 'danger', errMsg(err));
            });
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

        // Schedule panel close
        var schedCloseBtn = qs('[data-sched-close]', root);
        if (schedCloseBtn) {
            schedCloseBtn.addEventListener('click', function () {
                state.schedSuppId = null;
                renderSchedulePanel(root);
            });
        }

        // Schedule refresh
        var schedRefreshBtn = qs('[data-sched-refresh]', root);
        if (schedRefreshBtn) {
            schedRefreshBtn.addEventListener('click', function () {
                if (state.schedSuppId) { loadSchedules(root, state.schedSuppId); }
            });
        }

        // Schedule form
        var schedForm = qs('[data-sched-form]', root);
        if (schedForm) {
            schedForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveSchedule(root, schedForm);
            });
        }

        var schedResetBtn = qs('[data-sched-reset]', root);
        if (schedResetBtn) {
            schedResetBtn.addEventListener('click', function () { resetSchedForm(root); });
        }

        // Log form
        var logForm = qs('[data-log-form]', root);
        if (logForm) {
            logForm.addEventListener('submit', function (e) {
                e.preventDefault();
                logConsumption(root, logForm);
            });
        }

        root.addEventListener('click', function (event) {
            var schedBtn   = event.target.closest('[data-supp-sched]');
            var editBtn    = event.target.closest('[data-supp-edit]');
            var delBtn     = event.target.closest('[data-supp-delete]');
            var schedEdit  = event.target.closest('[data-sched-edit]');
            var schedDel   = event.target.closest('[data-sched-delete]');

            if (schedBtn) {
                loadSchedules(root, schedBtn.getAttribute('data-supp-sched'));
                return;
            }
            if (editBtn) {
                var id = editBtn.getAttribute('data-supp-edit');
                var supp = state.supplements.find(function (s) { return String(s.id) === String(id); });
                if (supp) { fillForm(root, supp); }
                return;
            }
            if (delBtn) {
                deleteSuplement(root, delBtn.getAttribute('data-supp-delete'));
                return;
            }
            if (schedEdit) {
                var sid = schedEdit.getAttribute('data-sched-edit');
                var sched = state.schedules.find(function (s) { return String(s.id) === String(sid); });
                if (sched) { fillSchedForm(root, sched); }
                return;
            }
            if (schedDel) {
                deleteSchedule(root, schedDel.getAttribute('data-sched-delete'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-supplements]');
        if (!root) { return; }
        bind(root);
        renderSchedulePanel(root);
        loadSupplements(root);
    });
})(window, document);
