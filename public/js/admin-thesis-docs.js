(function (window, document) {
    'use strict';

    var state = {
        documents: [],
        page: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        saving: false,
        selectedDocId: null,
        selectedDoc: null,
        typeFilter: '',
        search: '',
        sections: [],
        sectionsLoading: false,
        sectionSaving: false,
        selectedSectionId: null,
        versions: [],
        versionsLoading: false,
        versionSaving: false,
        showVersions: false,
    };

    var DOC_TYPES = [
        { value: 'functional', label: 'Funcional' },
        { value: 'technical',  label: 'Técnico' },
        { value: 'manual',     label: 'Manual' },
        { value: 'other',      label: 'Otro' },
    ];

    var DOC_CATEGORIES = [
        { value: 'scope',         label: 'Alcance' },
        { value: 'requirements',  label: 'Requerimientos' },
        { value: 'use_cases',     label: 'Casos de uso' },
        { value: 'architecture',  label: 'Arquitectura' },
        { value: 'data_model',    label: 'Modelo de datos' },
        { value: 'api_reference', label: 'Referencia API' },
        { value: 'user_manual',   label: 'Manual usuario' },
        { value: 'test_plan',     label: 'Plan de pruebas' },
        { value: 'general',       label: 'General' },
    ];

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v == null) { return ''; }
        return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function endpoint(path) { return path; }

    function errMsg(err) {
        if (err && err.message) { return err.message; }
        return 'Ocurrió un error inesperado.';
    }

    function docPath(extra) {
        return endpoint('/api/v1/admin/thesis-documents' + (extra || ''));
    }

    function showMsg(root, sel, type, msg) {
        var el = qs(sel, root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearMsg(root, sel) {
        var el = qs(sel, root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    // ── Document list ──────────────────────────────────────────────────────────

    function renderDocList(root) {
        var listEl  = qs('[data-tdoc-list]', root);
        var countEl = qs('[data-tdoc-count]', root);
        var prevBtn = qs('[data-tdoc-prev]', root);
        var nextBtn = qs('[data-tdoc-next]', root);
        var pageEl  = qs('[data-tdoc-page]', root);

        if (countEl) { countEl.textContent = state.total + ' doc' + (state.total !== 1 ? 's' : ''); }
        if (pageEl)  { pageEl.textContent = state.page + '/' + state.lastPage; }
        if (prevBtn) { prevBtn.disabled = state.loading || state.page <= 1; }
        if (nextBtn) { nextBtn.disabled = state.loading || state.page >= state.lastPage; }

        if (!listEl) { return; }

        if (state.loading) {
            listEl.innerHTML = '<p style="font-size:12px;color:#697681;margin:12px 0;text-align:center">Cargando...</p>';
            return;
        }
        if (!state.documents.length) {
            listEl.innerHTML = '<p style="font-size:12px;color:#697681;margin:12px 0;text-align:center">Sin documentos.</p>';
            return;
        }

        listEl.innerHTML = state.documents.map(function (d) {
            var isActive = String(d.id) === String(state.selectedDocId);
            var typeLabel = (DOC_TYPES.find(function (t) { return t.value === d.type; }) || {}).label || d.type || '';
            return '<div data-tdoc-card="' + escapeHtml(String(d.id)) + '" style="' +
                'padding:9px 10px;border-radius:6px;cursor:pointer;margin-bottom:4px;' +
                'background:' + (isActive ? '#e7f7f2' : '#f9f9f9') + ';' +
                'border:1px solid ' + (isActive ? 'rgba(4,172,133,.7)' : '#dde3e8') + '">' +
                '<div style="font-size:13px;font-weight:700;line-height:1.3;margin-bottom:2px">' + escapeHtml(d.title || '(sin título)') + '</div>' +
                '<div style="display:flex;gap:6px;flex-wrap:wrap">' +
                (typeLabel ? '<span style="font-size:10px;color:#697681;background:#eef1f3;border-radius:999px;padding:1px 6px">' + escapeHtml(typeLabel) + '</span>' : '') +
                (d.version ? '<span style="font-size:10px;color:#697681">v' + escapeHtml(String(d.version)) + '</span>' : '') +
                '</div>' +
                '</div>';
        }).join('');
    }

    function loadDocuments(root) {
        state.loading = true;
        clearMsg(root, '[data-tdoc-message]');
        renderDocList(root);

        var params = new URLSearchParams();
        params.set('page', state.page);
        params.set('per_page', 20);
        if (state.typeFilter) { params.set('type', state.typeFilter); }
        if (state.search)     { params.set('search', state.search); }

        window.CCApi.request(docPath('?' + params.toString()))
            .then(function (res) {
                state.documents = res.data || [];
                state.total     = (res.meta && res.meta.total) || state.documents.length;
                state.page      = (res.meta && res.meta.current_page) || state.page;
                state.lastPage  = (res.meta && res.meta.last_page) || 1;
                state.loading   = false;
                renderDocList(root);
            })
            .catch(function (err) {
                state.documents = []; state.loading = false;
                renderDocList(root);
                showMsg(root, '[data-tdoc-message]', 'danger', errMsg(err));
            });
    }

    // ── Document form ──────────────────────────────────────────────────────────

    function resetDocForm(root) {
        var form = qs('[data-tdoc-form]', root);
        if (form) { form.reset(); if (form.elements.id) { form.elements.id.value = ''; } }
        var titleEl = qs('[data-tdoc-form-title]', root);
        if (titleEl) { titleEl.textContent = 'Nuevo documento'; }
        clearMsg(root, '[data-tdoc-form-message]');
        state.selectedDocId = null;
        state.selectedDoc   = null;
        state.sections      = [];
        state.versions      = [];
        state.showVersions  = false;
        renderDocList(root);
        renderSections(root);
        renderVersions(root);
    }

    function fillDocForm(root, d) {
        state.selectedDocId = d.id;
        state.selectedDoc   = d;
        var form = qs('[data-tdoc-form]', root);
        if (!form) { return; }
        var f = form.elements;
        if (f.id)          { f.id.value = d.id; }
        if (f.title)       { f.title.value = d.title || ''; }
        if (f.type)        { f.type.value = d.type || ''; }
        if (f.category)    { f.category.value = d.category || ''; }
        if (f.description) { f.description.value = d.description || ''; }
        if (f.version)     { f.version.value = d.version || ''; }
        if (f.status)      { f.status.value = d.status || 'draft'; }
        var titleEl = qs('[data-tdoc-form-title]', root);
        if (titleEl) { titleEl.textContent = 'Editar: ' + (d.title || ''); }
        clearMsg(root, '[data-tdoc-form-message]');
        renderDocList(root);
        loadSections(root, d.id);
        loadVersions(root, d.id);
    }

    function saveDoc(root, form) {
        var f = form.elements;
        var title = f.title ? f.title.value.trim() : '';
        if (!title) { showMsg(root, '[data-tdoc-form-message]', 'warning', 'El título es obligatorio.'); return; }

        var payload = { title: title };
        if (f.type && f.type.value)            { payload.type = f.type.value; }
        if (f.category && f.category.value)    { payload.category = f.category.value; }
        if (f.description && f.description.value.trim()) { payload.description = f.description.value.trim(); }
        if (f.version && f.version.value.trim()) { payload.version = f.version.value.trim(); }
        if (f.status && f.status.value)        { payload.status = f.status.value; }

        var isEdit = !!(f.id && f.id.value);
        var url    = isEdit ? docPath('/' + encodeURIComponent(f.id.value)) : docPath();
        var method = isEdit ? 'PATCH' : 'POST';

        state.saving = true;
        var btn = qs('[data-tdoc-save]', root);
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
        clearMsg(root, '[data-tdoc-form-message]');

        window.CCApi.request(url, { method: method, body: payload })
            .then(function (res) {
                state.saving = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Guardar'; }
                var saved = res.data || res;
                if (!isEdit) {
                    fillDocForm(root, saved);
                } else {
                    state.selectedDoc = saved;
                }
                showMsg(root, '[data-tdoc-form-message]', 'success', 'Documento guardado.');
                state.page = 1;
                loadDocuments(root);
            })
            .catch(function (err) {
                state.saving = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Guardar'; }
                showMsg(root, '[data-tdoc-form-message]', 'danger', errMsg(err));
            });
    }

    function deleteDoc(root, id) {
        if (!window.confirm('¿Eliminar este documento y todas sus secciones?')) { return; }
        window.CCApi.request(docPath('/' + encodeURIComponent(id)), { method: 'DELETE' })
            .then(function () {
                if (String(state.selectedDocId) === String(id)) { resetDocForm(root); }
                state.page = 1;
                loadDocuments(root);
            })
            .catch(function (err) { showMsg(root, '[data-tdoc-message]', 'danger', errMsg(err)); });
    }

    // ── Sections ──────────────────────────────────────────────────────────────

    function renderSections(root) {
        var panel = qs('[data-tdoc-sections-panel]', root);
        if (!panel) { return; }
        if (!state.selectedDocId) { panel.style.display = 'none'; return; }
        panel.style.display = '';

        var listEl = qs('[data-section-list]', root);
        if (!listEl) { return; }

        if (state.sectionsLoading) {
            listEl.innerHTML = '<p style="font-size:12px;color:#697681;margin:8px 0">Cargando secciones...</p>';
            return;
        }
        if (!state.sections.length) {
            listEl.innerHTML = '<p style="font-size:12px;color:#697681;margin:8px 0">Sin secciones. Agregá una abajo.</p>';
            return;
        }

        listEl.innerHTML = state.sections.map(function (s) {
            var isActive = String(s.id) === String(state.selectedSectionId);
            var indent = (s.order_level || 0) * 16;
            return '<div style="display:flex;align-items:center;gap:6px;padding:7px 8px;border-radius:6px;margin-bottom:3px;' +
                'background:' + (isActive ? '#e7f7f2' : 'transparent') + ';border:1px solid ' + (isActive ? 'rgba(4,172,133,.6)' : 'transparent') + ';' +
                'padding-left:' + (8 + indent) + 'px">' +
                '<span style="flex:1;font-size:13px;font-weight:' + (isActive ? '700' : '400') + '">' + escapeHtml(s.title || '') + '</span>' +
                '<button type="button" class="btn-sm" style="font-size:11px;padding:2px 7px;background:#e7f7f2;color:#04ac85;border:1px solid rgba(4,172,133,.4);border-radius:4px" data-section-edit="' + escapeHtml(String(s.id)) + '">Editar</button>' +
                '<button type="button" class="btn-sm" style="font-size:11px;padding:2px 7px;background:#f7e7e7;color:#b33a3a;border:1px solid rgba(179,58,58,.3);border-radius:4px" data-section-delete="' + escapeHtml(String(s.id)) + '">✕</button>' +
                '</div>';
        }).join('');
    }

    function loadSections(root, docId) {
        state.sections        = [];
        state.sectionsLoading = true;
        state.selectedSectionId = null;
        renderSections(root);
        resetSectionForm(root);

        window.CCApi.request(endpoint('/api/v1/admin/thesis-documents/' + encodeURIComponent(docId) + '/sections'))
            .then(function (res) {
                state.sections        = res.data || res || [];
                state.sectionsLoading = false;
                renderSections(root);
            })
            .catch(function () { state.sectionsLoading = false; renderSections(root); });
    }

    function resetSectionForm(root) {
        var form = qs('[data-section-form]', root);
        if (form) { form.reset(); if (form.elements.id) { form.elements.id.value = ''; } }
        var titleEl = qs('[data-section-form-title]', root);
        if (titleEl) { titleEl.textContent = 'Nueva sección'; }
        var saveBtn = qs('[data-section-save]', root);
        if (saveBtn) { saveBtn.textContent = 'Agregar'; }
        state.selectedSectionId = null;
        clearMsg(root, '[data-section-message]');
    }

    function fillSectionForm(root, s) {
        state.selectedSectionId = s.id;
        var form = qs('[data-section-form]', root);
        if (!form) { return; }
        var f = form.elements;
        if (f.id)          { f.id.value = s.id; }
        if (f.title)       { f.title.value = s.title || ''; }
        if (f.content)     { f.content.value = s.content || s.body || ''; }
        if (f.order_level) { f.order_level.value = s.order_level != null ? s.order_level : 0; }
        if (f.order)       { f.order.value = s.order != null ? s.order : ''; }
        var titleEl = qs('[data-section-form-title]', root);
        if (titleEl) { titleEl.textContent = 'Editar sección'; }
        var saveBtn = qs('[data-section-save]', root);
        if (saveBtn) { saveBtn.textContent = 'Actualizar'; }
        clearMsg(root, '[data-section-message]');
        renderSections(root);
    }

    function saveSection(root, form) {
        if (!state.selectedDocId) { return; }
        var f = form.elements;
        var title = f.title ? f.title.value.trim() : '';
        if (!title) { showMsg(root, '[data-section-message]', 'warning', 'El título de sección es obligatorio.'); return; }

        var payload = { title: title };
        if (f.content && f.content.value.trim())   { payload.content = f.content.value.trim(); }
        if (f.order_level && f.order_level.value !== '') { payload.order_level = Number(f.order_level.value); }
        if (f.order && f.order.value !== '')       { payload.order = Number(f.order.value); }

        var isEdit = !!(f.id && f.id.value);
        var base   = '/api/v1/admin/thesis-documents/' + encodeURIComponent(state.selectedDocId) + '/sections';
        var url    = isEdit ? endpoint(base + '/' + encodeURIComponent(f.id.value)) : endpoint(base);
        var method = isEdit ? 'PATCH' : 'POST';

        state.sectionSaving = true;
        var btn = qs('[data-section-save]', root);
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
        clearMsg(root, '[data-section-message]');

        window.CCApi.request(url, { method: method, body: payload })
            .then(function () {
                state.sectionSaving = false;
                if (btn) { btn.disabled = false; }
                resetSectionForm(root);
                loadSections(root, state.selectedDocId);
            })
            .catch(function (err) {
                state.sectionSaving = false;
                if (btn) { btn.disabled = false; btn.textContent = isEdit ? 'Actualizar' : 'Agregar'; }
                showMsg(root, '[data-section-message]', 'danger', errMsg(err));
            });
    }

    function deleteSection(root, sectionId) {
        if (!state.selectedDocId) { return; }
        if (!window.confirm('¿Eliminar esta sección?')) { return; }
        window.CCApi.request(
            endpoint('/api/v1/admin/thesis-documents/' + encodeURIComponent(state.selectedDocId) + '/sections/' + encodeURIComponent(sectionId)),
            { method: 'DELETE' }
        )
            .then(function () {
                if (String(state.selectedSectionId) === String(sectionId)) { resetSectionForm(root); }
                loadSections(root, state.selectedDocId);
            })
            .catch(function (err) { showMsg(root, '[data-section-message]', 'danger', errMsg(err)); });
    }

    // ── Versions ──────────────────────────────────────────────────────────────

    function renderVersions(root) {
        var panel = qs('[data-tdoc-versions-panel]', root);
        if (!panel) { return; }
        if (!state.selectedDocId || !state.showVersions) { panel.style.display = 'none'; return; }
        panel.style.display = '';

        var listEl = qs('[data-version-list]', root);
        if (!listEl) { return; }

        if (state.versionsLoading) {
            listEl.innerHTML = '<p style="font-size:12px;color:#697681;margin:6px 0">Cargando versiones...</p>';
            return;
        }
        if (!state.versions.length) {
            listEl.innerHTML = '<p style="font-size:12px;color:#697681;margin:6px 0">Sin versiones guardadas.</p>';
            return;
        }

        listEl.innerHTML = state.versions.map(function (v) {
            var date = v.created_at ? v.created_at.substring(0, 16).replace('T', ' ') : '-';
            return '<div style="padding:8px 0;border-bottom:1px solid #edf0f3;display:flex;align-items:flex-start;justify-content:space-between;gap:8px">' +
                '<div>' +
                '<div style="font-size:13px;font-weight:700">v' + escapeHtml(String(v.version_number || v.version || '?')) + '</div>' +
                '<div style="font-size:11px;color:#697681">' + escapeHtml(date) + (v.created_by_name ? ' · ' + escapeHtml(v.created_by_name) : '') + '</div>' +
                (v.notes ? '<div style="font-size:12px;color:#24252a;margin-top:2px">' + escapeHtml(v.notes) + '</div>' : '') +
                '</div>' +
                '</div>';
        }).join('');
    }

    function loadVersions(root, docId) {
        state.versions        = [];
        state.versionsLoading = true;
        renderVersions(root);

        window.CCApi.request(endpoint('/api/v1/admin/thesis-documents/' + encodeURIComponent(docId) + '/versions'))
            .then(function (res) {
                state.versions        = res.data || res || [];
                state.versionsLoading = false;
                renderVersions(root);
            })
            .catch(function () { state.versionsLoading = false; renderVersions(root); });
    }

    function saveVersion(root, form) {
        if (!state.selectedDocId) { return; }
        var f       = form.elements;
        var payload = {};
        if (f.version_number && f.version_number.value.trim()) { payload.version_number = f.version_number.value.trim(); }
        if (f.notes && f.notes.value.trim())                   { payload.notes = f.notes.value.trim(); }

        state.versionSaving = true;
        var btn = qs('[data-version-save]', root);
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
        clearMsg(root, '[data-version-message]');

        window.CCApi.request(
            endpoint('/api/v1/admin/thesis-documents/' + encodeURIComponent(state.selectedDocId) + '/versions'),
            { method: 'POST', body: payload }
        )
            .then(function () {
                state.versionSaving = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Guardar versión'; }
                form.reset();
                showMsg(root, '[data-version-message]', 'success', 'Versión guardada.');
                loadVersions(root, state.selectedDocId);
            })
            .catch(function (err) {
                state.versionSaving = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Guardar versión'; }
                showMsg(root, '[data-version-message]', 'danger', errMsg(err));
            });
    }

    // ── Bind ──────────────────────────────────────────────────────────────────

    function bind(root) {
        var searchInput = qs('[data-tdoc-search]', root);
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                state.search = searchInput.value; state.page = 1; loadDocuments(root);
            });
        }

        var typeFilter = qs('[data-tdoc-type-filter]', root);
        if (typeFilter) {
            typeFilter.addEventListener('change', function () {
                state.typeFilter = typeFilter.value; state.page = 1; loadDocuments(root);
            });
        }

        var prevBtn = qs('[data-tdoc-prev]', root);
        if (prevBtn) { prevBtn.addEventListener('click', function () { if (state.page > 1) { state.page -= 1; loadDocuments(root); } }); }

        var nextBtn = qs('[data-tdoc-next]', root);
        if (nextBtn) { nextBtn.addEventListener('click', function () { if (state.page < state.lastPage) { state.page += 1; loadDocuments(root); } }); }

        var newBtn = qs('[data-tdoc-new]', root);
        if (newBtn) { newBtn.addEventListener('click', function () { resetDocForm(root); }); }

        var docForm = qs('[data-tdoc-form]', root);
        if (docForm) {
            docForm.addEventListener('submit', function (e) { e.preventDefault(); saveDoc(root, docForm); });
        }

        var sectionForm = qs('[data-section-form]', root);
        if (sectionForm) {
            sectionForm.addEventListener('submit', function (e) { e.preventDefault(); saveSection(root, sectionForm); });
        }

        var sectionResetBtn = qs('[data-section-reset]', root);
        if (sectionResetBtn) { sectionResetBtn.addEventListener('click', function () { resetSectionForm(root); }); }

        var versionsToggle = qs('[data-versions-toggle]', root);
        if (versionsToggle) {
            versionsToggle.addEventListener('click', function () {
                state.showVersions = !state.showVersions;
                versionsToggle.textContent = state.showVersions ? 'Ocultar versiones' : 'Ver versiones';
                renderVersions(root);
            });
        }

        var versionForm = qs('[data-version-form]', root);
        if (versionForm) {
            versionForm.addEventListener('submit', function (e) { e.preventDefault(); saveVersion(root, versionForm); });
        }

        root.addEventListener('click', function (event) {
            var card      = event.target.closest('[data-tdoc-card]');
            var delBtn    = event.target.closest('[data-tdoc-delete]');
            var secEdit   = event.target.closest('[data-section-edit]');
            var secDel    = event.target.closest('[data-section-delete]');

            if (card) {
                var id = card.getAttribute('data-tdoc-card');
                var doc = state.documents.find(function (d) { return String(d.id) === String(id); });
                if (doc) { fillDocForm(root, doc); }
                return;
            }
            if (delBtn) { deleteDoc(root, delBtn.getAttribute('data-tdoc-delete')); return; }
            if (secEdit) {
                var sid = secEdit.getAttribute('data-section-edit');
                var sec = state.sections.find(function (s) { return String(s.id) === String(sid); });
                if (sec) { fillSectionForm(root, sec); }
                return;
            }
            if (secDel) { deleteSection(root, secDel.getAttribute('data-section-delete')); }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-thesis-docs]');
        if (!root) { return; }
        bind(root);
        loadDocuments(root);
        renderSections(root);
        renderVersions(root);
    });
})(window, document);
