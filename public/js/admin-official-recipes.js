(function (window, document) {
    'use strict';

    var state = {
        recipes: [],
        categories: [],
        page: 1,
        lastPage: 1,
        view: 'empty', // 'empty' | 'detail' | 'form'
    };

    var SOURCE_LABELS = {
        official: 'Oficial',
        user: 'De usuario',
        shared: 'Compartida',
        external: 'Externa',
    };

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined || v === '') { return ''; }
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function text(v) { return (v === null || v === undefined || v === '') ? '-' : String(v); }

    function endpoint(path) { return '/api/v1' + path; }

    function showMessage(root, type, msg) {
        var el = qs('[data-recipes-adm-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-recipes-adm-message]', root);
        if (!el) { return; }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function handleError(root, err) {
        var payload = err.payload || {};
        var apiError = payload.error || {};
        var status = err.status || 0;
        var msg = status === 403 ? 'No tenés permiso para realizar esta acción.' :
                  status === 404 ? 'La receta no fue encontrada.' :
                  (apiError.message || err.message || 'Ocurrió un error inesperado.');
        showMessage(root, 'danger', msg);
    }

    function formatMinutes(mins) {
        if (!mins && mins !== 0) { return null; }
        if (mins < 60) { return mins + ' min'; }
        var h = Math.floor(mins / 60);
        var m = mins % 60;
        return h + 'h' + (m ? ' ' + m + 'min' : '');
    }

    // ─── Categories ──────────────────────────────────────────────────────────

    function loadCategories(root) {
        return window.CCApi.request(endpoint('/recipe-categories'))
            .then(function (r) {
                state.categories = flattenTree(r.data || []);
                populateCategorySelect(root);
            })
            .catch(function () {});
    }

    function flattenTree(nodes, prefix) {
        var result = [];
        (nodes || []).forEach(function (node) {
            var label = (prefix ? prefix + ' › ' : '') + node.name;
            result.push({ id: node.id, name: label });
            if (node.children && node.children.length) {
                flattenTree(node.children, label).forEach(function (c) { result.push(c); });
            }
        });
        return result;
    }

    function populateCategorySelect(root) {
        var sel = qs('[data-recipes-adm-form-category]', root);
        if (!sel) { return; }
        sel.innerHTML = '<option value="">Sin categoria</option>' +
            state.categories.map(function (c) {
                return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
            }).join('');
    }

    // ─── List ─────────────────────────────────────────────────────────────────

    function loadRecipes(root, page) {
        clearMessage(root);
        state.page = page || 1;
        var params = new URLSearchParams();
        var search     = qs('[data-recipes-adm-search]', root);
        var sourceType = qs('[data-recipes-adm-source-type]', root);
        var isOfficial = qs('[data-recipes-adm-is-official]', root);
        var status     = qs('[data-recipes-adm-status]', root);

        params.set('page', String(state.page));
        params.set('per_page', '20');
        params.set('sort', 'created_at');
        params.set('order', 'desc');

        if (search && search.value.trim())     { params.set('search', search.value.trim()); }
        if (sourceType && sourceType.value)    { params.set('source_type', sourceType.value); }
        if (status && status.value)            { params.set('status', status.value); }
        if (isOfficial && isOfficial.value !== '') {
            params.set('is_official', isOfficial.value);
        }

        var body = qs('[data-recipes-adm-body]', root);
        body.innerHTML = '<tr><td colspan="6" class="muted">Cargando recetas...</td></tr>';

        return window.CCApi.request(endpoint('/admin/recipes?' + params.toString()))
            .then(function (r) {
                state.recipes  = r.data || [];
                state.lastPage = (r.meta && r.meta.last_page) ? r.meta.last_page : 1;
                renderTable(root, r.meta || {});
            })
            .catch(function (err) {
                body.innerHTML = '<tr><td colspan="6" class="muted">Error al cargar recetas.</td></tr>';
                handleError(root, err);
            });
    }

    function renderTable(root, meta) {
        var body    = qs('[data-recipes-adm-body]', root);
        var counter = qs('[data-recipes-adm-count]', root);
        var pageEl  = qs('[data-recipes-adm-page]', root);

        if (counter) { counter.textContent = (meta.total || state.recipes.length) + ' recetas'; }
        if (pageEl)  { pageEl.textContent = 'Pagina ' + (meta.current_page || state.page) + ' de ' + (meta.last_page || state.lastPage); }

        if (!state.recipes.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay recetas.</td></tr>';
            return;
        }

        body.innerHTML = state.recipes.map(function (r) {
            var deletedBadge = r.deleted_at ? '<span class="chip danger" style="font-size:11px">eliminada</span>' : '';
            var officialBadge = r.is_official ? '<span class="chip" style="font-size:11px">✓ Oficial</span>' : '';
            var verifiedBadge = r.is_verified ? '<span style="background:#e8f0fe;color:#2f80ed;border-radius:999px;padding:2px 7px;font-size:11px;font-weight:900">✓ Verif.</span>' : '';
            var categoryName = (r.category && r.category.name) ? r.category.name : '-';
            var content = (r.ingredients_count || 0) + ' ing. / ' + (r.tags_count || 0) + ' tags';
            var sourceLabel = SOURCE_LABELS[r.source_type] || text(r.source_type);

            var actions = r.deleted_at
                ? '<span class="muted" style="font-size:12px">Eliminada</span>'
                : '<button type="button" class="btn-main btn-sm" data-recipe-adm-edit="' + r.id + '">Editar</button>' +
                  ' <button type="button" class="btn-ghost btn-sm" data-recipe-adm-delete="' + r.id + '">Eliminar</button>';

            return '<tr>' +
                '<td>' +
                '<strong>' + escapeHtml(r.name) + '</strong>' +
                '<div style="margin-top:3px">' + officialBadge + verifiedBadge + deletedBadge + '</div>' +
                '</td>' +
                '<td>' + escapeHtml(sourceLabel) + '</td>' +
                '<td>' + escapeHtml(categoryName) + '</td>' +
                '<td>' + escapeHtml(r.status) + '</td>' +
                '<td>' + escapeHtml(content) + '</td>' +
                '<td>' +
                '<button type="button" class="btn-ghost btn-sm" data-recipe-adm-show="' + r.id + '" style="margin-right:4px">Ver</button>' +
                actions +
                '</td>' +
                '</tr>';
        }).join('');
    }

    // ─── Detail ───────────────────────────────────────────────────────────────

    function showDetail(root, recipeId) {
        switchView(root, 'detail');
        var detailEl = qs('[data-recipes-adm-detail]', root);
        detailEl.innerHTML = '<p class="muted">Cargando...</p>';

        window.CCApi.request(endpoint('/admin/recipes/' + recipeId))
            .then(function (r) { renderDetail(root, r.data); })
            .catch(function (err) {
                var msg = (err.payload && err.payload.error && err.payload.error.message) || 'Error al cargar el detalle.';
                detailEl.innerHTML = '<p class="muted">' + escapeHtml(msg) + '</p>';
            });
    }

    function renderDetail(root, r) {
        var detailEl = qs('[data-recipes-adm-detail]', root);

        var imageHtml = '';
        var primaryImage = (r.images || []).find(function (i) { return i.is_primary; }) || (r.images && r.images[0]);
        if (primaryImage) {
            imageHtml = '<img src="' + escapeHtml(primaryImage.image_url) + '" alt="" ' +
                'style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;margin-bottom:10px" ' +
                'onerror="this.style.display=\'none\'">';
        }

        var badgesHtml = '';
        if (r.is_official) { badgesHtml += '<span class="chip">✓ Oficial</span>'; }
        if (r.is_verified) { badgesHtml += '<span style="background:#e8f0fe;color:#2f80ed;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:900;margin-right:6px">✓ Verificada</span>'; }
        if (r.deleted_at)  { badgesHtml += '<span class="chip danger">Eliminada</span>'; }

        var ownerName = r.owner ? ((r.owner.name || '') + ' ' + (r.owner.lastname || '')).trim() : '-';
        var sourceLabel = SOURCE_LABELS[r.source_type] || text(r.source_type);

        var tagsHtml = (r.tags || []).map(function (t) {
            return '<span class="chip" style="margin-bottom:4px">' + escapeHtml(t.name) + '</span>';
        }).join('');

        var ingredientsHtml = '<div data-ingr-panel></div>';

        var stepsHtml = '';
        if (r.steps && r.steps.length) {
            stepsHtml = '<div style="margin-top:12px"><h3 style="font-size:13px;font-weight:900;margin:0 0 6px">Pasos (' + r.steps.length + ')</h3>' +
                '<ol style="margin:0;padding-left:18px;font-size:13px">' +
                r.steps.map(function (s) {
                    var t = s.estimated_minutes ? ' <span class="muted">(' + formatMinutes(s.estimated_minutes) + ')</span>' : '';
                    return '<li style="margin-bottom:6px">' + escapeHtml(s.description) + t + '</li>';
                }).join('') + '</ol></div>';
        }

        var sourceHtml = '';
        if (r.sources && r.sources.length) {
            var src = r.sources[0];
            sourceHtml =
                (src.source_url ? '<div class="line"><span class="muted">URL fuente</span><a href="' + escapeHtml(src.source_url) + '" target="_blank" rel="noopener noreferrer" style="color:#04ac85;font-size:13px;word-break:break-all">' + escapeHtml(src.source_site || src.source_url) + '</a></div>' : '') +
                (src.source_author ? '<div class="line"><span class="muted">Autor</span><strong>' + escapeHtml(src.source_author) + '</strong></div>' : '');
        }

        var editBtn = !r.deleted_at
            ? '<button type="button" class="btn-main btn-sm" data-recipe-adm-edit="' + r.id + '" style="margin-top:14px">Editar esta receta</button>'
            : '';

        detailEl.innerHTML =
            imageHtml +
            '<h2 style="font-size:16px;margin:0 0 8px">' + escapeHtml(r.name) + '</h2>' +
            (badgesHtml ? '<div style="margin-bottom:10px">' + badgesHtml + '</div>' : '') +
            '<div class="line"><span class="muted">Fuente</span><strong>' + escapeHtml(sourceLabel) + '</strong></div>' +
            (r.category ? '<div class="line"><span class="muted">Categoria</span><strong>' + escapeHtml(r.category.name) + '</strong></div>' : '') +
            '<div class="line"><span class="muted">Estado</span><strong>' + escapeHtml(r.status) + '</strong></div>' +
            (ownerName !== '-' ? '<div class="line"><span class="muted">Autor</span><strong>' + escapeHtml(ownerName) + '</strong></div>' : '') +
            (r.difficulty ? '<div class="line"><span class="muted">Dificultad</span><strong>' + escapeHtml(r.difficulty) + '</strong></div>' : '') +
            (r.servings ? '<div class="line"><span class="muted">Porciones</span><strong>' + escapeHtml(r.servings) + '</strong></div>' : '') +
            (r.prep_time_minutes ? '<div class="line"><span class="muted">Preparacion</span><strong>' + escapeHtml(formatMinutes(r.prep_time_minutes)) + '</strong></div>' : '') +
            (r.cook_time_minutes ? '<div class="line"><span class="muted">Coccion</span><strong>' + escapeHtml(formatMinutes(r.cook_time_minutes)) + '</strong></div>' : '') +
            sourceHtml +
            (r.description ? '<p style="font-size:13px;margin:10px 0;color:#24252a">' + escapeHtml(r.description) + '</p>' : '') +
            (tagsHtml ? '<div style="margin-top:8px">' + tagsHtml + '</div>' : '') +
            ingredientsHtml +
            stepsHtml +
            editBtn;

        if (window.RecipeIngredients) {
            var ingrPanel = detailEl.querySelector('[data-ingr-panel]');
            if (ingrPanel) { window.RecipeIngredients.mount(ingrPanel, r.id, !r.deleted_at, r.ingredients || []); }
        }
    }

    // ─── Form ─────────────────────────────────────────────────────────────────

    function switchView(root, view) {
        state.view = view;
        var detailEl  = qs('[data-recipes-adm-detail]', root);
        var formPanel = qs('[data-recipes-adm-form-panel]', root);
        var newBtnWrap = qs('[data-recipes-adm-new-btn-wrap]', root);

        if (view === 'form') {
            detailEl.style.display  = 'none';
            formPanel.style.display = '';
            newBtnWrap.style.display = 'none';
        } else {
            detailEl.style.display  = '';
            formPanel.style.display = 'none';
            newBtnWrap.style.display = '';
        }
    }

    function openForm(root, recipe) {
        switchView(root, 'form');
        var form  = qs('[data-recipes-adm-form]', root);
        var title = qs('[data-recipes-adm-form-title]', root);

        form.reset();
        form.elements.id.value = recipe ? recipe.id : '';

        if (recipe) {
            title.textContent = 'Editar receta';
            form.elements.name.value        = recipe.name || '';
            form.elements.description.value = recipe.description || '';
            form.elements.servings.value    = recipe.servings || '';
            form.elements.difficulty.value  = recipe.difficulty || '';
            form.elements.prep_time_minutes.value = recipe.prep_time_minutes || '';
            form.elements.cook_time_minutes.value = recipe.cook_time_minutes || '';
            form.elements.status.value      = recipe.status || 'active';
            form.elements.source_type.value = recipe.source_type || 'official';
            form.elements.source_url.value  = '';
            form.elements.source_site.value = '';
            form.elements.source_author.value = '';
            if (recipe.sources && recipe.sources.length) {
                form.elements.source_url.value    = recipe.sources[0].source_url || '';
                form.elements.source_site.value   = recipe.sources[0].source_site || '';
                form.elements.source_author.value = recipe.sources[0].source_author || '';
            }
            form.elements.is_official.checked = !!recipe.is_official;
            form.elements.is_public.checked   = false; // API no devuelve is_public
            form.elements.category_id.value   = recipe.category_id || '';
        } else {
            title.textContent = 'Nueva receta oficial';
            form.elements.source_type.value = 'official';
            form.elements.is_official.checked = true;
            form.elements.status.value = 'active';
        }

        populateCategorySelect(root);
        if (recipe && recipe.category_id) {
            form.elements.category_id.value = recipe.category_id;
        }
    }

    function closeForm(root) {
        switchView(root, state.view === 'form' ? 'empty' : state.view);
        switchView(root, 'empty');
        qs('[data-recipes-adm-detail]', root).innerHTML =
            '<p class="muted">Seleccioná una receta para ver el detalle o usá el formulario para crear una nueva.</p>';
    }

    function buildPayload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key   = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id') { return; }
            // checkboxes: present = 1, absent = 0
            if (key === 'is_official' || key === 'is_public') {
                data[key] = true;
                return;
            }
            if (value === '') {
                var nullables = ['description', 'category_id', 'difficulty', 'servings',
                                 'prep_time_minutes', 'cook_time_minutes',
                                 'source_url', 'source_site', 'source_author'];
                if (nullables.indexOf(key) !== -1) { data[key] = null; }
                return;
            }
            if (['servings', 'prep_time_minutes', 'cook_time_minutes', 'category_id'].indexOf(key) !== -1) {
                data[key] = parseInt(value, 10);
                return;
            }
            data[key] = value;
        });
        // Unchecked checkboxes are absent from FormData; explicitly send false
        if (!Object.prototype.hasOwnProperty.call(data, 'is_official')) { data.is_official = false; }
        if (!Object.prototype.hasOwnProperty.call(data, 'is_public'))   { data.is_public   = false; }
        return data;
    }

    function submitForm(root) {
        clearMessage(root);
        var form      = qs('[data-recipes-adm-form]', root);
        var submitBtn = form.querySelector('[type=submit]');
        if (submitBtn) { submitBtn.disabled = true; }

        var id     = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path   = '/admin/recipes' + (id ? '/' + id : '');

        window.CCApi.request(endpoint(path), { method: method, body: buildPayload(form) })
            .then(function (r) {
                showMessage(root, 'success', id ? 'Receta actualizada.' : 'Receta creada correctamente.');
                closeForm(root);
                return loadRecipes(root, state.page).then(function () {
                    if (r.data && r.data.id) { showDetail(root, r.data.id); }
                });
            })
            .catch(function (err) { handleError(root, err); })
            .then(function () { if (submitBtn) { submitBtn.disabled = false; } });
    }

    function deleteRecipe(root, id) {
        if (!window.confirm('¿Eliminar esta receta? Quedará marcada como inactiva.')) { return; }
        clearMessage(root);

        window.CCApi.request(endpoint('/admin/recipes/' + id), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Receta eliminada.');
                closeForm(root);
                return loadRecipes(root, state.page);
            })
            .catch(function (err) { handleError(root, err); });
    }

    // ─── Bind ─────────────────────────────────────────────────────────────────

    function debounce(fn, wait) {
        var t;
        return function () { clearTimeout(t); t = setTimeout(fn, wait); };
    }

    function bind(root) {
        qs('[data-recipes-adm-refresh]', root).addEventListener('click', function () {
            loadRecipes(root, 1);
        });
        qs('[data-recipes-adm-search]', root).addEventListener('input', debounce(function () {
            loadRecipes(root, 1);
        }, 350));
        qs('[data-recipes-adm-source-type]', root).addEventListener('change', function () { loadRecipes(root, 1); });
        qs('[data-recipes-adm-is-official]', root).addEventListener('change', function () { loadRecipes(root, 1); });
        qs('[data-recipes-adm-status]', root).addEventListener('change', function () { loadRecipes(root, 1); });
        qs('[data-recipes-adm-prev]', root).addEventListener('click', function () {
            if (state.page > 1) { loadRecipes(root, state.page - 1); }
        });
        qs('[data-recipes-adm-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) { loadRecipes(root, state.page + 1); }
        });
        qs('[data-recipes-adm-new]', root).addEventListener('click', function () {
            openForm(root, null);
        });
        function cancelFn() { closeForm(root); }
        qs('[data-recipes-adm-cancel]', root).addEventListener('click', cancelFn);
        qs('[data-recipes-adm-cancel-2]', root).addEventListener('click', cancelFn);

        qs('[data-recipes-adm-form]', root).addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(root);
        });

        root.addEventListener('click', function (e) {
            var showId   = e.target.getAttribute('data-recipe-adm-show');
            var editId   = e.target.getAttribute('data-recipe-adm-edit');
            var deleteId = e.target.getAttribute('data-recipe-adm-delete');

            if (showId)   { showDetail(root, showId); }
            if (deleteId) { deleteRecipe(root, deleteId); }
            if (editId) {
                window.CCApi.request(endpoint('/admin/recipes/' + editId))
                    .then(function (r) { openForm(root, r.data); })
                    .catch(function (err) { handleError(root, err); });
            }
        });
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-official-recipes]');
        if (!root || !window.CCApi) { return; }

        loadCategories(root);
        bind(root);
        loadRecipes(root, 1);
    });
})(window, document);
