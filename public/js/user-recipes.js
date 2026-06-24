(function (window, document) {
    'use strict';

    var state = {
        recipes: [],
        categories: [],
        page: 1,
        lastPage: 1,
        currentUserId: null,
        view: 'empty', // 'empty' | 'detail' | 'form'
        editId: null,
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

    function text(v) {
        return (v === null || v === undefined || v === '') ? '-' : String(v);
    }

    function endpoint(path) { return '/api/v1' + path; }

    function showMessage(root, type, msg) {
        var el = qs('[data-recipes-message]', root);
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-recipes-message]', root);
        if (!el) { return; }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function handleError(root, err) {
        var payload = err.payload || {};
        var apiError = payload.error || {};
        var status = err.status || 0;
        var msg;
        if (status === 401) {
            msg = 'Tu sesión venció. Por favor iniciá sesión nuevamente.';
        } else if (status === 403) {
            msg = 'No tenés permiso para realizar esta acción.';
        } else if (status === 404) {
            msg = 'La receta no fue encontrada.';
        } else {
            msg = apiError.message || err.message || 'Ocurrió un error inesperado.';
        }
        showMessage(root, 'danger', msg);
    }

    function formatMinutes(mins) {
        if (!mins && mins !== 0) { return null; }
        if (mins < 60) { return mins + ' min'; }
        var h = Math.floor(mins / 60);
        var m = mins % 60;
        return h + 'h' + (m ? ' ' + m + 'min' : '');
    }

    function formatDate(str) {
        if (!str) { return '-'; }
        try { return new Date(str).toLocaleDateString('es-AR', { year: 'numeric', month: 'long', day: 'numeric' }); }
        catch (e) { return str; }
    }

    // ─── Categories ──────────────────────────────────────────────────────────

    function loadCategories(root) {
        return window.CCApi.request(endpoint('/recipe-categories'))
            .then(function (r) {
                state.categories = flattenTree(r.data || []);
                populateCategorySelects(root);
            })
            .catch(function () { /* categories are optional — ignore errors */ });
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

    function populateCategorySelects(root) {
        var opts = '<option value="">Todas las categorías</option>' +
            state.categories.map(function (c) {
                return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
            }).join('');
        var filterSel = qs('[data-recipes-category]', root);
        if (filterSel) { filterSel.innerHTML = opts; }

        var formSel = qs('[data-recipes-form-category]', root);
        if (formSel) {
            formSel.innerHTML = '<option value="">Sin categoría</option>' +
                state.categories.map(function (c) {
                    return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
                }).join('');
        }
    }

    // ─── List ─────────────────────────────────────────────────────────────────

    function loadRecipes(root, page) {
        clearMessage(root);
        state.page = page || 1;
        var params = new URLSearchParams();
        var search = qs('[data-recipes-search]', root);
        var sourceType = qs('[data-recipes-source-type]', root);
        var category = qs('[data-recipes-category]', root);

        params.set('page', String(state.page));
        params.set('per_page', '20');
        params.set('sort', 'created_at');
        params.set('order', 'desc');

        if (search && search.value.trim()) { params.set('search', search.value.trim()); }
        if (sourceType && sourceType.value) { params.set('source_type', sourceType.value); }
        if (category && category.value) { params.set('category_id', category.value); }

        var listEl = qs('[data-recipes-list]', root);
        listEl.innerHTML = '<p class="muted">Cargando recetas...</p>';

        return window.CCApi.request(endpoint('/recipes?' + params.toString()))
            .then(function (r) {
                state.recipes  = r.data || [];
                state.lastPage = (r.meta && r.meta.last_page) ? r.meta.last_page : 1;
                renderList(root, r.meta || {});
            })
            .catch(function (err) {
                listEl.innerHTML = '';
                handleError(root, err);
            });
    }

    function renderList(root, meta) {
        var listEl  = qs('[data-recipes-list]', root);
        var countEl = qs('[data-recipes-count]', root);
        var pageEl  = qs('[data-recipes-page]', root);

        if (countEl) { countEl.textContent = (meta.total || state.recipes.length) + ' recetas'; }
        if (pageEl)  { pageEl.textContent = 'Pág ' + (meta.current_page || state.page) + ' / ' + (meta.last_page || state.lastPage); }

        if (!state.recipes.length) {
            listEl.innerHTML = '<p class="muted">No se encontraron recetas.</p>';
            return;
        }

        listEl.innerHTML = state.recipes.map(function (r) {
            var sourceLabel = SOURCE_LABELS[r.source_type] || r.source_type || '-';
            var sourceBg = r.source_type === 'official' ? 'var(--green-soft)' : '#f0f4f8';
            var sourceColor = r.source_type === 'official' ? 'var(--green)' : '#697681';
            var badgeHtml = '';
            if (r.is_official) {
                badgeHtml += '<span style="background:var(--green-soft);color:var(--green);border-radius:50px;padding:2px 7px;font-size:11px;font-weight:900;margin-right:4px">✓ Oficial</span>';
            }
            if (r.is_verified) {
                badgeHtml += '<span style="background:#e8f0fe;color:#2f80ed;border-radius:50px;padding:2px 7px;font-size:11px;font-weight:900">✓ Verificada</span>';
            }
            var categoryName = (r.category && r.category.name) ? r.category.name : null;
            var times = [];
            if (r.prep_time_minutes) { times.push('Prep: ' + formatMinutes(r.prep_time_minutes)); }
            if (r.cook_time_minutes) { times.push('Cocción: ' + formatMinutes(r.cook_time_minutes)); }
            var metaHtml = [
                r.difficulty ? '<span class="muted" style="font-size:12px">🍳 ' + escapeHtml(r.difficulty) + '</span>' : null,
                r.servings   ? '<span class="muted" style="font-size:12px">🍽 ' + escapeHtml(r.servings) + ' porciones</span>' : null,
                times.length  ? '<span class="muted" style="font-size:12px">⏱ ' + escapeHtml(times.join(' · ')) + '</span>' : null,
            ].filter(Boolean).join('&nbsp;&nbsp;');

            var tagsHtml = (r.tags_count && r.tags_count > 0)
                ? '<span style="font-size:12px;color:var(--muted)">' + r.tags_count + ' tags</span>'
                : '';

            return '<div style="border:1px solid var(--line);border-radius:8px;padding:14px;cursor:pointer;background:#fff" data-recipe-card="' + r.id + '">' +
                '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px">' +
                '<strong style="font-size:15px">' + escapeHtml(r.name) + '</strong>' +
                '<span style="background:' + sourceBg + ';color:' + sourceColor + ';border-radius:50px;padding:2px 8px;font-size:11px;font-weight:900;white-space:nowrap;flex-shrink:0">' + escapeHtml(sourceLabel) + '</span>' +
                '</div>' +
                (badgeHtml ? '<div style="margin-bottom:6px">' + badgeHtml + '</div>' : '') +
                (categoryName ? '<div style="margin-bottom:4px"><span style="font-size:12px;color:var(--muted)">📂 ' + escapeHtml(categoryName) + '</span></div>' : '') +
                (metaHtml ? '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:4px">' + metaHtml + '</div>' : '') +
                (tagsHtml ? '<div>' + tagsHtml + '</div>' : '') +
                '</div>';
        }).join('');
    }

    // ─── Detail ───────────────────────────────────────────────────────────────

    function showDetail(root, recipeId) {
        var detailEl = qs('[data-recipes-detail]', root);
        var formPanel = qs('[data-recipes-form-panel]', root);
        detailEl.style.display = '';
        formPanel.style.display = 'none';
        state.view = 'detail';
        detailEl.innerHTML = '<p class="muted">Cargando detalle...</p>';

        window.CCApi.request(endpoint('/recipes/' + recipeId))
            .then(function (r) {
                renderDetail(root, r.data);
            })
            .catch(function (err) {
                var status = err.status || 0;
                var msg = status === 404 ? 'Esta receta ya no está disponible.' :
                    ((err.payload && err.payload.error && err.payload.error.message) || 'Error al cargar el detalle.');
                detailEl.innerHTML = '<p class="muted">' + escapeHtml(msg) + '</p>';
            });
    }

    function renderDetail(root, r) {
        var detailEl = qs('[data-recipes-detail]', root);
        var isOwner = state.currentUserId && String(r.owner && r.owner.id) === String(state.currentUserId);

        var imageHtml = '';
        var primaryImage = (r.images || []).find(function (i) { return i.is_primary; }) || (r.images && r.images[0]);
        if (primaryImage) {
            imageHtml = '<img src="' + escapeHtml(primaryImage.image_url) + '" alt="' + escapeHtml(r.name) + '" ' +
                'style="width:100%;max-height:200px;object-fit:cover;border-radius:8px;margin-bottom:12px" ' +
                'onerror="this.style.display=\'none\'">';
        }

        var badgesHtml = '';
        if (r.is_official) { badgesHtml += '<span class="chip">✓ Oficial</span>'; }
        if (r.is_verified) { badgesHtml += '<span style="background:#e8f0fe;color:#2f80ed;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:900;margin-right:6px">✓ Verificada</span>'; }

        var sourceLabel = SOURCE_LABELS[r.source_type] || r.source_type;

        var tagsHtml = (r.tags || []).map(function (t) {
            return '<span class="chip" style="margin-bottom:4px">' + escapeHtml(t.name) + '</span>';
        }).join('');

        var ingredientsHtml = '<div data-ingr-panel></div>';

        var stepsHtml = '<div data-steps-panel></div>';

        var nutritionHtml = '<div data-nutrition-panel></div>';

        var sourceHtml = '';
        if (r.source_type === 'external' && r.sources && r.sources.length) {
            var src = r.sources[0];
            sourceHtml = '<div class="table-line"><span class="muted">Fuente</span>' +
                '<a href="' + escapeHtml(src.source_url) + '" target="_blank" rel="noopener noreferrer" style="color:var(--green);font-size:13px;word-break:break-all">' +
                escapeHtml(src.source_site || src.source_url) + '</a></div>';
        }

        var ownerName = r.owner ? escapeHtml((r.owner.name || '') + ' ' + (r.owner.lastname || '')).trim() : '-';

        var actionsHtml = '';
        if (isOwner) {
            actionsHtml = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid var(--line)">' +
                '<button type="button" class="btn-main btn-sm" data-recipe-edit="' + r.id + '">Editar receta</button>' +
                '<button type="button" class="btn-secondary-web btn-sm" data-recipe-delete="' + r.id + '">Eliminar</button>' +
                '</div>';
        }

        detailEl.innerHTML =
            imageHtml +
            '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px">' +
            '<h2 style="margin:0;font-size:18px">' + escapeHtml(r.name) + '</h2>' +
            '</div>' +
            (badgesHtml ? '<div style="margin-bottom:10px">' + badgesHtml + '</div>' : '') +
            '<div class="table-line"><span class="muted">Fuente</span><strong>' + escapeHtml(sourceLabel) + '</strong></div>' +
            (r.category ? '<div class="table-line"><span class="muted">Categoría</span><strong>' + escapeHtml(r.category.name) + '</strong></div>' : '') +
            (ownerName !== '-' ? '<div class="table-line"><span class="muted">Autor</span><strong>' + ownerName + '</strong></div>' : '') +
            (r.difficulty ? '<div class="table-line"><span class="muted">Dificultad</span><strong>' + escapeHtml(r.difficulty) + '</strong></div>' : '') +
            (r.servings   ? '<div class="table-line"><span class="muted">Porciones</span><strong>' + escapeHtml(r.servings) + '</strong></div>' : '') +
            (r.prep_time_minutes ? '<div class="table-line"><span class="muted">Preparación</span><strong>' + escapeHtml(formatMinutes(r.prep_time_minutes)) + '</strong></div>' : '') +
            (r.cook_time_minutes ? '<div class="table-line"><span class="muted">Cocción</span><strong>' + escapeHtml(formatMinutes(r.cook_time_minutes)) + '</strong></div>' : '') +
            sourceHtml +
            (r.description ? '<p style="font-size:14px;margin:12px 0 0;color:var(--ink)">' + escapeHtml(r.description) + '</p>' : '') +
            (tagsHtml ? '<div style="margin-top:10px">' + tagsHtml + '</div>' : '') +
            ingredientsHtml +
            stepsHtml +
            nutritionHtml +
            actionsHtml;

        if (window.RecipeIngredients) {
            var ingrPanel = qs('[data-ingr-panel]', detailEl);
            if (ingrPanel) { window.RecipeIngredients.mount(ingrPanel, r.id, isOwner, r.ingredients || []); }
        }
        if (window.RecipeSteps) {
            var stepsPanel = qs('[data-steps-panel]', detailEl);
            if (stepsPanel) { window.RecipeSteps.mount(stepsPanel, r.id, isOwner, r.steps || []); }
        }
        if (window.RecipeNutrition) {
            var nutrPanel = qs('[data-nutrition-panel]', detailEl);
            if (nutrPanel) { window.RecipeNutrition.mount(nutrPanel, r.id, false); }
        }
    }

    // ─── Form ─────────────────────────────────────────────────────────────────

    function showForm(root, recipe) {
        var detailEl  = qs('[data-recipes-detail]', root);
        var formPanel = qs('[data-recipes-form-panel]', root);
        var form      = qs('[data-recipes-form]', root);
        var title     = qs('[data-recipes-form-title]', root);
        var statusRow = qs('[data-recipes-form-status-row]', root);

        detailEl.style.display  = 'none';
        formPanel.style.display = '';
        state.view   = 'form';
        state.editId = recipe ? recipe.id : null;

        form.reset();
        form.elements.id.value = recipe ? recipe.id : '';

        if (recipe) {
            title.textContent             = 'Editar receta';
            form.elements.name.value      = recipe.name || '';
            form.elements.description.value = recipe.description || '';
            form.elements.servings.value  = recipe.servings || '';
            form.elements.difficulty.value = recipe.difficulty || '';
            form.elements.prep_time_minutes.value = recipe.prep_time_minutes || '';
            form.elements.cook_time_minutes.value = recipe.cook_time_minutes || '';
            form.elements.category_id.value = recipe.category_id || '';
            form.elements.status.value    = recipe.status || 'active';
            if (statusRow) { statusRow.style.display = ''; }
        } else {
            title.textContent = 'Nueva receta';
            form.elements.status.value = 'active';
            if (statusRow) { statusRow.style.display = 'none'; }
        }

        populateCategorySelects(root);
        if (recipe && recipe.category_id) {
            form.elements.category_id.value = recipe.category_id;
        }
    }

    function hideForm(root) {
        var detailEl  = qs('[data-recipes-detail]', root);
        var formPanel = qs('[data-recipes-form-panel]', root);
        detailEl.style.display  = '';
        formPanel.style.display = 'none';
        state.view   = 'empty';
        state.editId = null;
        qs('[data-recipes-detail]', root).innerHTML = '<p class="muted">Seleccioná una receta para ver el detalle.</p>';
    }

    function buildPayload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key   = entry[0];
            var value = typeof entry[1] === 'string' ? entry[1].trim() : entry[1];
            if (key === 'id') { return; }
            if (value === '') {
                if (['description', 'category_id', 'difficulty', 'servings', 'prep_time_minutes', 'cook_time_minutes'].indexOf(key) !== -1) {
                    data[key] = null;
                }
                return;
            }
            if (['servings', 'prep_time_minutes', 'cook_time_minutes', 'category_id'].indexOf(key) !== -1) {
                data[key] = parseInt(value, 10);
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function submitForm(root) {
        clearMessage(root);
        var form      = qs('[data-recipes-form]', root);
        var submitBtn = form.querySelector('[type=submit]');
        if (submitBtn) { submitBtn.disabled = true; }

        var id     = form.elements.id.value;
        var method = id ? 'PATCH' : 'POST';
        var path   = '/recipes' + (id ? '/' + id : '');

        window.CCApi.request(endpoint(path), { method: method, body: buildPayload(form) })
            .then(function (r) {
                showMessage(root, 'success', id ? 'Receta actualizada correctamente.' : 'Receta creada correctamente.');
                hideForm(root);
                return loadRecipes(root, state.page).then(function () {
                    if (r.data && r.data.id) { showDetail(root, r.data.id); }
                });
            })
            .catch(function (err) { handleError(root, err); })
            .then(function () { if (submitBtn) { submitBtn.disabled = false; } });
    }

    function deleteRecipe(root, id) {
        if (!window.confirm('¿Eliminar esta receta? Esta acción no se puede deshacer.')) { return; }
        clearMessage(root);

        window.CCApi.request(endpoint('/recipes/' + id), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Receta eliminada correctamente.');
                hideForm(root);
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
        qs('[data-recipes-search]', root).addEventListener('input', debounce(function () {
            loadRecipes(root, 1);
        }, 350));

        qs('[data-recipes-source-type]', root).addEventListener('change', function () {
            loadRecipes(root, 1);
        });

        qs('[data-recipes-category]', root).addEventListener('change', function () {
            loadRecipes(root, 1);
        });

        qs('[data-recipes-prev]', root).addEventListener('click', function () {
            if (state.page > 1) { loadRecipes(root, state.page - 1); }
        });

        qs('[data-recipes-next]', root).addEventListener('click', function () {
            if (state.page < state.lastPage) { loadRecipes(root, state.page + 1); }
        });

        qs('[data-recipes-new]', root).addEventListener('click', function () {
            showForm(root, null);
        });

        function cancelForm() { hideForm(root); }
        qs('[data-recipes-form-cancel]', root).addEventListener('click', cancelForm);
        qs('[data-recipes-form-cancel-2]', root).addEventListener('click', cancelForm);

        qs('[data-recipes-form]', root).addEventListener('submit', function (e) {
            e.preventDefault();
            submitForm(root);
        });

        // Delegated: card click, edit, delete
        root.addEventListener('click', function (e) {
            var card   = e.target.closest('[data-recipe-card]');
            var editEl = e.target.closest('[data-recipe-edit]');
            var delEl  = e.target.closest('[data-recipe-delete]');

            if (card && !editEl && !delEl) {
                showDetail(root, card.getAttribute('data-recipe-card'));
            }

            if (editEl) {
                var editId = editEl.getAttribute('data-recipe-edit');
                window.CCApi.request(endpoint('/recipes/' + editId))
                    .then(function (r) { showForm(root, r.data); })
                    .catch(function (err) { handleError(root, err); });
            }

            if (delEl) {
                deleteRecipe(root, delEl.getAttribute('data-recipe-delete'));
            }
        });
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-recipes]');
        if (!root || !window.CCApi) { return; }

        state.currentUserId = root.getAttribute('data-user-id');

        loadCategories(root);
        bind(root);
        loadRecipes(root, 1);
    });
})(window, document);
