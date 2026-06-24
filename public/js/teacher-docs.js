(function (window, document) {
    'use strict';

    var state = {
        docType: 'functional',
        documents: [],
        loading: false,
        selectedDoc: null,
        sections: [],
        sectionsLoading: false,
        selectedSectionId: null,
        search: '',
        comments: [],
        commentsLoading: false,
    };

    var DOC_CATEGORY_LABELS = {
        scope:            { label: 'Alcance',              color: '#1a5fb4', bg: '#e7f3ff' },
        requirements:     { label: 'Requerimientos',       color: '#b35c00', bg: '#fff3e0' },
        use_cases:        { label: 'Casos de uso',         color: '#6a1fb4', bg: '#f3e7ff' },
        architecture:     { label: 'Arquitectura',         color: '#04ac85', bg: '#e7f7f2' },
        data_model:       { label: 'Modelo de datos',      color: '#1a5fb4', bg: '#e7f3ff' },
        api_reference:    { label: 'Referencia API',       color: '#b33a3a', bg: '#f7e7e7' },
        user_manual:      { label: 'Manual usuario',       color: '#4a7c10', bg: '#f0f7e7' },
        test_plan:        { label: 'Plan de pruebas',      color: '#555',    bg: '#f0f0f0' },
        general:          { label: 'General',              color: '#555',    bg: '#f0f0f0' },
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    function escapeHtml(v) {
        if (v == null) { return ''; }
        return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function endpoint(path) { return path; }

    function errMsg(err) {
        if (err && err.message) { return err.message; }
        return 'Ocurrió un error inesperado.';
    }

    function catBadge(category) {
        var c = DOC_CATEGORY_LABELS[category] || DOC_CATEGORY_LABELS.general;
        return '<span style="background:' + c.bg + ';color:' + c.color + ';border-radius:999px;padding:2px 9px;font-size:11px;font-weight:700">' + escapeHtml(c.label) + '</span>';
    }

    function showMsg(root, type, msg) {
        var el = qs('[data-docs-message]', root);
        if (!el) { return; }
        el.className = 'alert alert-' + type; el.textContent = msg; el.style.display = 'block';
    }

    function clearMsg(root) {
        var el = qs('[data-docs-message]', root);
        if (el) { el.style.display = 'none'; el.textContent = ''; }
    }

    // ── Document list ──────────────────────────────────────────────────────────

    function renderDocList(root) {
        var listEl  = qs('[data-docs-list]', root);
        var countEl = qs('[data-docs-count]', root);
        if (!listEl) { return; }

        var docs = state.documents.filter(function (d) {
            if (!state.search) { return true; }
            var q = state.search.toLowerCase();
            return (d.title || '').toLowerCase().indexOf(q) !== -1 ||
                   (d.description || '').toLowerCase().indexOf(q) !== -1;
        });

        if (countEl) { countEl.textContent = docs.length + ' documento' + (docs.length !== 1 ? 's' : ''); }

        if (state.loading) {
            listEl.innerHTML = '<p style="font-size:13px;color:#716d64;margin:16px 0;text-align:center">Cargando documentos...</p>';
            return;
        }
        if (!docs.length) {
            listEl.innerHTML = '<p style="font-size:13px;color:#716d64;margin:16px 0;text-align:center">No se encontraron documentos.</p>';
            return;
        }

        listEl.innerHTML = docs.map(function (d) {
            var isSelected = state.selectedDoc && String(state.selectedDoc.id) === String(d.id);
            return '<div data-doc-card="' + escapeHtml(String(d.id)) + '" style="' +
                'border:1px solid ' + (isSelected ? 'rgba(4,172,133,.8)' : '#e3ded2') + ';' +
                'background:' + (isSelected ? '#e7f7f2' : '#fff') + ';' +
                'border-radius:8px;padding:12px 14px;margin-bottom:8px;cursor:pointer;' +
                'transition:border-color .15s">' +
                '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:5px">' +
                '<span style="font-size:14px;font-weight:700;line-height:1.3">' + escapeHtml(d.title || '') + '</span>' +
                (d.version ? '<span style="font-size:10px;color:#716d64;background:#f0ede6;border-radius:999px;padding:2px 7px;white-space:nowrap;flex-shrink:0">v' + escapeHtml(String(d.version)) + '</span>' : '') +
                '</div>' +
                (d.category ? '<div style="margin-bottom:5px">' + catBadge(d.category) + '</div>' : '') +
                (d.description ? '<p style="font-size:12px;color:#716d64;margin:0;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">' + escapeHtml(d.description) + '</p>' : '') +
                (d.updated_at ? '<p style="font-size:11px;color:#b0a898;margin:6px 0 0">Actualizado: ' + escapeHtml(d.updated_at.substring(0, 10)) + '</p>' : '') +
                '</div>';
        }).join('');
    }

    function loadDocuments(root) {
        state.loading = true;
        clearMsg(root);
        renderDocList(root);

        var params = new URLSearchParams();
        params.set('type', state.docType);
        params.set('per_page', 50);

        window.CCApi.request(endpoint('/api/v1/thesis-documents?' + params.toString()))
            .then(function (response) {
                state.documents = response.data || [];
                state.loading   = false;
                renderDocList(root);
            })
            .catch(function (err) {
                state.documents = [];
                state.loading   = false;
                renderDocList(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    // ── Section nav + content ──────────────────────────────────────────────────

    function buildSectionTree(sections) {
        var map  = {};
        var roots = [];
        sections.forEach(function (s) { map[s.id] = s; s._children = []; });
        sections.forEach(function (s) {
            if (s.parent_section_id && map[s.parent_section_id]) {
                map[s.parent_section_id]._children.push(s);
            } else {
                roots.push(s);
            }
        });
        return roots;
    }

    function renderSectionNav(nodes, depth) {
        depth = depth || 0;
        return nodes.map(function (s) {
            var isSelected = String(s.id) === String(state.selectedSectionId);
            var hasChildren = s._children && s._children.length;
            return '<div>' +
                '<div data-section-nav="' + escapeHtml(String(s.id)) + '" style="' +
                'padding:' + (depth === 0 ? '8px 10px' : '6px 10px 6px ' + (14 + depth * 12) + 'px') + ';' +
                'border-radius:6px;cursor:pointer;font-size:' + (depth === 0 ? '13px' : '12px') + ';' +
                'font-weight:' + (depth === 0 ? '700' : '400') + ';' +
                'color:' + (isSelected ? '#04ac85' : '#24252a') + ';' +
                'background:' + (isSelected ? '#e7f7f2' : 'transparent') + ';' +
                'border-left:3px solid ' + (isSelected ? '#04ac85' : 'transparent') + ';' +
                'margin-bottom:2px;line-height:1.3">' +
                (hasChildren ? (isSelected ? '▾ ' : '▸ ') : '· ') +
                escapeHtml(s.title || '') +
                '</div>' +
                (hasChildren ? renderSectionNav(s._children, depth + 1) : '') +
                '</div>';
        }).join('');
    }

    function renderDocDetail(root) {
        var detailEl  = qs('[data-doc-detail]', root);
        var navEl     = qs('[data-section-nav-list]', root);
        var contentEl = qs('[data-section-content]', root);
        if (!detailEl) { return; }

        if (!state.selectedDoc) {
            detailEl.innerHTML = '<p style="font-size:13px;color:#716d64;text-align:center;padding:24px 0">Seleccioná un documento de la lista.</p>';
            if (navEl) { navEl.innerHTML = ''; }
            if (contentEl) { contentEl.innerHTML = ''; contentEl.style.display = 'none'; }
            return;
        }

        var d = state.selectedDoc;
        detailEl.innerHTML =
            '<div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #e3ded2">' +
            '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:6px">' +
            '<h2 style="font-size:17px;font-weight:900;margin:0">' + escapeHtml(d.title || '') + '</h2>' +
            (d.version ? '<span style="font-size:11px;color:#716d64;background:#f0ede6;border-radius:999px;padding:3px 9px;flex-shrink:0">v' + escapeHtml(String(d.version)) + '</span>' : '') +
            '</div>' +
            (d.category ? catBadge(d.category) + '&nbsp;' : '') +
            (d.description ? '<p style="font-size:13px;color:#716d64;margin:8px 0 0">' + escapeHtml(d.description) + '</p>' : '') +
            '</div>';

        if (state.sectionsLoading) {
            if (navEl) { navEl.innerHTML = '<p style="font-size:12px;color:#716d64;margin:8px 0">Cargando secciones...</p>'; }
            return;
        }

        if (!state.sections.length) {
            if (navEl) { navEl.innerHTML = '<p style="font-size:12px;color:#716d64;margin:8px 0">Sin secciones cargadas.</p>'; }
            return;
        }

        var tree = buildSectionTree(state.sections);
        if (navEl) { navEl.innerHTML = renderSectionNav(tree, 0); }

        renderSectionContent(root);
    }

    function renderSectionContent(root) {
        var contentEl = qs('[data-section-content]', root);
        if (!contentEl) { return; }

        var section = state.sections.find(function (s) { return String(s.id) === String(state.selectedSectionId); });

        if (!section) {
            contentEl.style.display = 'none';
            return;
        }

        contentEl.style.display = '';
        var body = section.content || section.body || '';
        var isHtml = body.indexOf('<') !== -1;

        contentEl.innerHTML =
            '<div style="border-top:2px solid #e3ded2;padding-top:14px;margin-top:10px">' +
            '<h3 style="font-size:15px;font-weight:900;margin:0 0 10px;color:#04ac85">' + escapeHtml(section.title || '') + '</h3>' +
            (section.order != null ? '<p style="font-size:11px;color:#b0a898;margin:0 0 10px">Sección ' + escapeHtml(String(section.order)) + '</p>' : '') +
            '<div style="font-size:14px;line-height:1.65;color:#24252a">' +
            (isHtml ? body : body.split('\n').map(function (line) {
                return line.trim() ? '<p style="margin:0 0 8px">' + escapeHtml(line) + '</p>' : '<br>';
            }).join('')) +
            '</div>' +
            '</div>';
    }

    function selectSection(root, sectionId) {
        state.selectedSectionId = sectionId;
        var navEl = qs('[data-section-nav-list]', root);
        if (navEl) {
            var tree = buildSectionTree(state.sections);
            navEl.innerHTML = renderSectionNav(tree, 0);
        }
        renderSectionContent(root);
        var contentEl = qs('[data-section-content]', root);
        if (contentEl) { contentEl.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    function loadDocument(root, docId) {
        state.selectedSectionId = null;
        state.sections          = [];
        state.sectionsLoading   = true;

        var docFromList = state.documents.find(function (d) { return String(d.id) === String(docId); });
        state.selectedDoc = docFromList || { id: docId };
        renderDocList(root);
        renderDocDetail(root);

        var docReq  = window.CCApi.request(endpoint('/api/v1/thesis-documents/' + encodeURIComponent(docId)));
        var secsReq = window.CCApi.request(endpoint('/api/v1/thesis-documents/' + encodeURIComponent(docId) + '/sections'));

        Promise.all([docReq, secsReq])
            .then(function (results) {
                var docRes  = results[0];
                var secsRes = results[1];
                state.selectedDoc     = docRes.data || docRes;
                state.sections        = secsRes.data || secsRes || [];
                state.sectionsLoading = false;
                if (state.sections.length) {
                    state.selectedSectionId = String(state.sections[0].id);
                }
                renderDocList(root);
                renderDocDetail(root);
                loadComments(root, docId);
            })
            .catch(function (err) {
                state.sectionsLoading = false;
                renderDocDetail(root);
                showMsg(root, 'danger', errMsg(err));
            });
    }

    // ── Comments ──────────────────────────────────────────────────────────────

    function renderComments(root) {
        var panel = qs('[data-doc-comments]', root);
        if (!panel) { return; }

        if (!state.selectedDoc) { panel.style.display = 'none'; return; }
        panel.style.display = '';

        var listEl = qs('[data-comments-list]', root);
        var msgEl  = qs('[data-comments-message]', root);
        if (msgEl) { msgEl.style.display = 'none'; }

        if (!listEl) { return; }

        if (state.commentsLoading) {
            listEl.innerHTML = '<p style="font-size:13px;color:#716d64;margin:0">Cargando comentarios...</p>';
            return;
        }

        if (!state.comments.length) {
            listEl.innerHTML = '<p style="font-size:13px;color:#716d64;margin:0">Sin comentarios todavía.</p>';
            return;
        }

        listEl.innerHTML = state.comments.map(function (c) {
            var date = c.created_at ? c.created_at.substring(0, 16).replace('T', ' ') : '';
            var author = c.user_name || c.author || (c.user && c.user.name) || 'Docente';
            return '<div style="padding:10px 0;border-bottom:1px solid #f0ede6">' +
                '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">' +
                '<span style="font-size:12px;font-weight:700">' + escapeHtml(author) + '</span>' +
                (date ? '<span style="font-size:11px;color:#b0a898">' + escapeHtml(date) + '</span>' : '') +
                (c.section_reference ? '<span style="font-size:10px;background:#f0ede6;color:#716d64;border-radius:999px;padding:1px 7px">§ ' + escapeHtml(c.section_reference) + '</span>' : '') +
                '</div>' +
                '<p style="font-size:13px;color:#24252a;margin:0;line-height:1.5">' + escapeHtml(c.body || c.content || c.text || '') + '</p>' +
                '</div>';
        }).join('');
    }

    function loadComments(root, docId) {
        state.comments         = [];
        state.commentsLoading  = true;
        renderComments(root);

        window.CCApi.request(endpoint('/api/v1/thesis-documents/' + encodeURIComponent(docId) + '/comments'))
            .then(function (response) {
                state.comments        = response.data || response || [];
                state.commentsLoading = false;
                renderComments(root);
            })
            .catch(function () {
                state.commentsLoading = false;
                renderComments(root);
            });
    }

    function postComment(root, form) {
        if (!state.selectedDoc) { return; }
        var bodyEl = form.elements.body;
        var body   = bodyEl ? bodyEl.value.trim() : '';
        if (!body) {
            var msgEl = qs('[data-comments-message]', root);
            if (msgEl) { msgEl.className = 'alert alert-warning'; msgEl.textContent = 'Escribí un comentario antes de enviar.'; msgEl.style.display = 'block'; }
            return;
        }

        var payload = { body: body };
        var sectionRef = form.elements.section_reference;
        if (sectionRef && sectionRef.value.trim()) { payload.section_reference = sectionRef.value.trim(); }

        var saveBtn = qs('[data-comment-save]', root);
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Enviando...'; }
        var msgEl = qs('[data-comments-message]', root);
        if (msgEl) { msgEl.style.display = 'none'; }

        window.CCApi.request(
            endpoint('/api/v1/thesis-documents/' + encodeURIComponent(state.selectedDoc.id) + '/comments'),
            { method: 'POST', body: payload }
        )
            .then(function (response) {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Comentar'; }
                form.reset();
                var newComment = response.data || response;
                state.comments.unshift(newComment);
                renderComments(root);
            })
            .catch(function (err) {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Comentar'; }
                if (msgEl) { msgEl.className = 'alert alert-danger'; msgEl.textContent = errMsg(err); msgEl.style.display = 'block'; }
            });
    }

    // ── Bind ──────────────────────────────────────────────────────────────────

    function bind(root) {
        var searchInput = qs('[data-docs-search]', root);
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                state.search = searchInput.value;
                renderDocList(root);
            });
        }

        var listEl = qs('[data-docs-list]', root);
        if (listEl) {
            listEl.addEventListener('click', function (event) {
                var card = event.target.closest('[data-doc-card]');
                if (card) { loadDocument(root, card.getAttribute('data-doc-card')); }
            });
        }

        var navEl = qs('[data-section-nav-list]', root);
        if (navEl) {
            navEl.addEventListener('click', function (event) {
                var item = event.target.closest('[data-section-nav]');
                if (item) { selectSection(root, item.getAttribute('data-section-nav')); }
            });
        }

        var commentForm = qs('[data-comment-form]', root);
        if (commentForm) {
            commentForm.addEventListener('submit', function (e) {
                e.preventDefault();
                postComment(root, commentForm);
            });
        }
    }

    function init(root, docType) {
        state.docType           = docType;
        state.documents         = [];
        state.selectedDoc       = null;
        state.sections          = [];
        state.selectedSectionId = null;
        state.comments          = [];
        state.commentsLoading   = false;
        bind(root);
        renderComments(root);
        loadDocuments(root);
        renderDocDetail(root);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var rootFn = qs('[data-teacher-functional-docs]');
        if (rootFn) { init(rootFn, 'functional'); }

        var rootTc = qs('[data-teacher-technical-docs]');
        if (rootTc) { init(rootTc, 'technical'); }
    });
})(window, document);
