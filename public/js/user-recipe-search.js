(function (window, document) {
    'use strict';

    var root = null;

    var state = {
        page: 1,
        perPage: 12,
        search: '',
        categoryId: '',
        difficulty: '',
        maxTotalTime: '',
        sourceType: '',
        includeIngredients: [],  // [{id, name}]
        excludeIngredients: [],  // [{id, name}]
        activeTagIds: [],        // [id, ...]
        loading: false,
        results: [],
        total: 0,
        lastPage: 1,
    };

    var tagsCache = [];       // flat list of tags loaded once
    var incDebounceTimer = null;
    var excDebounceTimer = null;

    // ─── Helpers ────────────────────────────────────────────────────────────────

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }

    function escapeHtml(v) {
        if (v === null || v === undefined) { return ''; }
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function endpoint(path) { return '/api/v1' + path; }

    function buildQs(params) {
        var parts = [];
        Object.keys(params).forEach(function (k) {
            var v = params[k];
            if (v === '' || v === null || v === undefined) { return; }
            parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
        });
        return parts.join('&');
    }

    function difficultyLabel(d) {
        if (d === 'easy')   { return 'Fácil'; }
        if (d === 'medium') { return 'Media'; }
        if (d === 'hard')   { return 'Difícil'; }
        return d || '';
    }

    function fmtMinutes(m) {
        var n = parseInt(m, 10);
        if (!n) { return '—'; }
        if (n < 60) { return n + ' min'; }
        var h = Math.floor(n / 60);
        var rm = n % 60;
        return rm ? h + 'h ' + rm + 'min' : h + 'h';
    }

    function sourceLabel(s) {
        if (s === 'official') { return 'Oficial'; }
        if (s === 'user')     { return 'Usuario'; }
        if (s === 'imported') { return 'Importada'; }
        if (s === 'scraped')  { return 'Scraping'; }
        return s || '';
    }

    // ─── Render helpers ─────────────────────────────────────────────────────────

    function showMsg(type, text) {
        var el = qs('[data-rs-message]', root);
        if (!el) { return; }
        el.textContent = text;
        el.style.display = 'block';
        el.style.background = type === 'ok' ? '#e7f7f2' : '#f7e7e7';
        el.style.color      = type === 'ok' ? '#04ac85' : '#b33a3a';
        if (type === 'ok') { setTimeout(function () { el.style.display = 'none'; }, 3500); }
    }

    function hideMsg() {
        var el = qs('[data-rs-message]', root);
        if (el) { el.style.display = 'none'; }
    }

    function renderRecipeCard(r) {
        var totalTime = (parseInt(r.prep_time_minutes, 10) || 0) + (parseInt(r.cook_time_minutes, 10) || 0);
        var timeStr = totalTime ? fmtMinutes(totalTime) : '—';
        var catName = r.category ? escapeHtml(r.category.name) : '';
        var diffStr = r.difficulty ? difficultyLabel(r.difficulty) : '';
        var srcStr  = r.source_type ? sourceLabel(r.source_type) : '';

        var badges = '';
        if (diffStr) {
            badges += '<span style="display:inline-block;background:#f0f4f8;color:#697681;border-radius:4px;padding:2px 7px;font-size:11px;margin-right:4px">' + escapeHtml(diffStr) + '</span>';
        }
        if (r.is_official) {
            badges += '<span style="display:inline-block;background:#e7f7f2;color:#04ac85;border-radius:4px;padding:2px 7px;font-size:11px">Oficial</span>';
        }

        return '<div data-rs-card data-id="' + r.id + '" style="border:1px solid #dde3e8;border-radius:8px;padding:13px;cursor:pointer;background:#fff;transition:box-shadow 0.15s" ' +
            'onmouseover="this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.08)\'" onmouseout="this.style.boxShadow=\'\'">' +
            '<div style="font-size:15px;font-weight:900;margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(r.name) + '</div>' +
            (catName ? '<div style="font-size:12px;color:#697681;margin-bottom:5px">' + catName + '</div>' : '') +
            '<div style="display:flex;gap:10px;font-size:12px;color:#697681;margin-bottom:7px">' +
            '<span>⏱ ' + timeStr + '</span>' +
            (r.servings ? '<span>🍽 ' + escapeHtml(r.servings) + ' porciones</span>' : '') +
            '</div>' +
            (badges ? '<div style="margin-top:4px">' + badges + '</div>' : '') +
            '</div>';
    }

    function renderResults() {
        var listEl  = qs('[data-rs-list]', root);
        var countEl = qs('[data-rs-count]', root);
        var pageEl  = qs('[data-rs-page]', root);
        var prevBtn = qs('[data-rs-prev]', root);
        var nextBtn = qs('[data-rs-next]', root);
        var paginEl = qs('[data-rs-pagination]', root);

        if (!listEl) { return; }

        if (state.loading) {
            listEl.innerHTML = '<p class="muted" style="font-size:13px">Buscando...</p>';
            if (countEl) { countEl.style.display = 'none'; }
            if (paginEl) { paginEl.style.display = 'none'; }
            return;
        }

        if (!state.results.length) {
            listEl.innerHTML = '<p class="muted" style="font-size:13px">No se encontraron recetas con esos filtros.</p>';
            if (countEl) { countEl.style.display = 'none'; }
            if (paginEl) { paginEl.style.display = 'none'; }
            return;
        }

        listEl.innerHTML = state.results.map(renderRecipeCard).join('');
        if (countEl) {
            countEl.textContent = state.total + (state.total === 1 ? ' resultado' : ' resultados');
            countEl.style.display = '';
        }
        if (paginEl) { paginEl.style.display = ''; }
        if (pageEl) { pageEl.textContent = 'Pág ' + state.page + ' / ' + state.lastPage; }
        if (prevBtn) { prevBtn.disabled = state.page <= 1; }
        if (nextBtn) { nextBtn.disabled = state.page >= state.lastPage; }
    }

    function renderDetail(r) {
        var detailEl = qs('[data-rs-detail]', root);
        if (!detailEl) { return; }
        detailEl.style.display = '';

        var totalTime = (parseInt(r.prep_time_minutes, 10) || 0) + (parseInt(r.cook_time_minutes, 10) || 0);

        detailEl.innerHTML =
            '<h2 style="font-size:16px;font-weight:900;margin:0 0 10px">' + escapeHtml(r.name) + '</h2>' +
            (r.category ? '<div style="font-size:12px;color:#697681;margin-bottom:8px">Categoría: ' + escapeHtml(r.category.name) + '</div>' : '') +
            '<table style="width:100%;border-collapse:collapse;font-size:13px">' +
            (r.difficulty ? '<tr><td style="padding:5px 0;color:#697681">Dificultad</td><td style="padding:5px 0;font-weight:700">' + escapeHtml(difficultyLabel(r.difficulty)) + '</td></tr>' : '') +
            (r.prep_time_minutes ? '<tr><td style="padding:5px 0;color:#697681">Preparación</td><td style="padding:5px 0">' + fmtMinutes(r.prep_time_minutes) + '</td></tr>' : '') +
            (r.cook_time_minutes ? '<tr><td style="padding:5px 0;color:#697681">Cocción</td><td style="padding:5px 0">' + fmtMinutes(r.cook_time_minutes) + '</td></tr>' : '') +
            (totalTime ? '<tr><td style="padding:5px 0;color:#697681">Tiempo total</td><td style="padding:5px 0">' + fmtMinutes(totalTime) + '</td></tr>' : '') +
            (r.servings ? '<tr><td style="padding:5px 0;color:#697681">Porciones</td><td style="padding:5px 0">' + escapeHtml(r.servings) + '</td></tr>' : '') +
            (r.source_type ? '<tr><td style="padding:5px 0;color:#697681">Fuente</td><td style="padding:5px 0">' + escapeHtml(sourceLabel(r.source_type)) + '</td></tr>' : '') +
            (r.owner ? '<tr><td style="padding:5px 0;color:#697681">Autor</td><td style="padding:5px 0">' + escapeHtml(r.owner.name) + '</td></tr>' : '') +
            (r.ingredients_count !== undefined && r.ingredients_count !== null ? '<tr><td style="padding:5px 0;color:#697681">Ingredientes</td><td style="padding:5px 0">' + r.ingredients_count + '</td></tr>' : '') +
            '</table>' +
            (r.is_official ? '<div style="margin-top:8px"><span style="background:#e7f7f2;color:#04ac85;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:700">Receta oficial</span></div>' : '') +
            '<div style="margin-top:14px">' +
            '<a href="/web/recipes" style="font-size:13px;color:#04ac85;text-decoration:underline">Ver en Mis recetas</a>' +
            '</div>';
    }

    // ─── Tag chips ───────────────────────────────────────────────────────────────

    function renderTagChips() {
        var container = qs('[data-rs-tag-chips]', root);
        if (!container) { return; }
        if (!tagsCache.length) {
            container.innerHTML = '<span style="font-size:12px;color:#697681">Cargando...</span>';
            return;
        }
        container.innerHTML = tagsCache.map(function (t) {
            var active = state.activeTagIds.indexOf(t.id) !== -1;
            return '<button type="button" data-rs-tag="' + t.id + '" ' +
                'style="border:1px solid ' + (active ? '#04ac85' : '#dde3e8') + ';' +
                'background:' + (active ? '#e7f7f2' : '#fff') + ';' +
                'color:' + (active ? '#04ac85' : '#697681') + ';' +
                'border-radius:999px;padding:4px 10px;font-size:11px;font-weight:' + (active ? '900' : '500') + ';cursor:pointer">' +
                escapeHtml(t.name) + '</button>';
        }).join('');
    }

    // ─── Ingredient typeahead ────────────────────────────────────────────────────

    function renderIngChips(type) {
        var chipsSel = type === 'inc' ? '[data-rs-inc-chips]' : '[data-rs-exc-chips]';
        var container = qs(chipsSel, root);
        if (!container) { return; }
        var list = type === 'inc' ? state.includeIngredients : state.excludeIngredients;
        container.innerHTML = list.map(function (ing) {
            return '<span style="display:inline-flex;align-items:center;background:' +
                (type === 'inc' ? '#e7f7f2;color:#04ac85' : '#f7e7e7;color:#b33a3a') +
                ';border-radius:999px;padding:3px 8px;font-size:11px;font-weight:700;gap:5px">' +
                escapeHtml(ing.name) +
                '<button type="button" data-rs-remove-ing="' + type + '" data-ing-id="' + ing.id + '" ' +
                'style="border:0;background:transparent;color:inherit;font-weight:900;cursor:pointer;padding:0;line-height:1">✕</button>' +
                '</span>';
        }).join('');
    }

    function searchIngredients(term, resultsEl, type) {
        if (!term || term.length < 2) {
            resultsEl.style.display = 'none';
            return;
        }
        window.CCApi.request(endpoint('/ingredients?search=' + encodeURIComponent(term) + '&per_page=8&status=active'))
            .then(function (res) {
                var items = (res.data || []);
                if (!items.length) {
                    resultsEl.innerHTML = '<div style="padding:8px;font-size:12px;color:#697681">Sin resultados</div>';
                    resultsEl.style.display = '';
                    return;
                }
                var existingIds = (type === 'inc' ? state.includeIngredients : state.excludeIngredients).map(function (i) { return i.id; });
                resultsEl.innerHTML = items.map(function (ing) {
                    var already = existingIds.indexOf(ing.id) !== -1;
                    return '<div data-rs-ing-pick="' + type + '" data-ing-id="' + ing.id + '" data-ing-name="' + escapeHtml(ing.name) + '" ' +
                        'style="padding:7px 10px;font-size:13px;cursor:' + (already ? 'default' : 'pointer') + ';color:' + (already ? '#04ac85' : 'inherit') + ';border-bottom:1px solid #f0f0f0">' +
                        escapeHtml(ing.name) + (already ? ' ✓' : '') +
                        '</div>';
                }).join('');
                resultsEl.style.display = '';
            })
            .catch(function () {
                resultsEl.style.display = 'none';
            });
    }

    // ─── API ─────────────────────────────────────────────────────────────────────

    function fetchResults() {
        hideMsg();
        state.loading = true;
        renderResults();

        var params = {
            page:     state.page,
            per_page: state.perPage,
        };
        if (state.search)      { params.search        = state.search; }
        if (state.categoryId)  { params.category_id   = state.categoryId; }
        if (state.difficulty)  { params.difficulty     = state.difficulty; }
        if (state.maxTotalTime){ params.max_total_time = state.maxTotalTime; }
        if (state.sourceType)  { params.source_type    = state.sourceType; }

        state.includeIngredients.forEach(function (ing) {
            params.ingredient_id = ing.id;
        });
        state.excludeIngredients.forEach(function (ing) {
            params.exclude_ingredient_id = ing.id;
        });
        if (state.activeTagIds.length) {
            params.tag_id = state.activeTagIds[0];
        }

        var url = endpoint('/recipes/search') + '?' + buildQs(params);

        window.CCApi.request(url)
            .then(function (res) {
                state.results  = res.data || [];
                var meta = res.meta || {};
                state.total    = meta.total || state.results.length;
                state.lastPage = meta.last_page || 1;
                state.loading  = false;
                renderResults();
            })
            .catch(function (err) {
                state.results  = [];
                state.total    = 0;
                state.lastPage = 1;
                state.loading  = false;
                renderResults();
                var status = err && err.status;
                if (status !== 401 && status !== 403) {
                    showMsg('err', (err && err.payload && err.payload.error && err.payload.error.message) || 'Error al buscar recetas.');
                }
            });
    }

    function loadCategories() {
        var sel = qs('[data-rs-category]', root);
        if (!sel) { return; }
        window.CCApi.request(endpoint('/recipe-categories?per_page=100'))
            .then(function (res) {
                var items = res.data || [];
                var opts = '<option value="">Todas las categorías</option>';
                items.forEach(function (c) {
                    opts += '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
                    if (c.children && c.children.length) {
                        c.children.forEach(function (ch) {
                            opts += '<option value="' + ch.id + '">  ' + escapeHtml(ch.name) + '</option>';
                        });
                    }
                });
                sel.innerHTML = opts;
            })
            .catch(function () {});
    }

    function loadTags() {
        var container = qs('[data-rs-tag-chips]', root);
        if (!container) { return; }
        window.CCApi.request(endpoint('/recipe-tags?per_page=100&status=active'))
            .then(function (res) {
                tagsCache = res.data || [];
                renderTagChips();
            })
            .catch(function () {
                tagsCache = [];
                renderTagChips();
            });
    }

    // ─── Read filters from DOM ────────────────────────────────────────────────────

    function readFilters() {
        var catSel   = qs('[data-rs-category]', root);
        var diffSel  = qs('[data-rs-difficulty]', root);
        var maxTime  = qs('[data-rs-max-time]', root);
        var srcSel   = qs('[data-rs-source]', root);
        var searchEl = qs('[data-rs-search]', root);

        if (catSel)   { state.categoryId   = catSel.value; }
        if (diffSel)  { state.difficulty    = diffSel.value; }
        if (maxTime)  { state.maxTotalTime  = maxTime.value; }
        if (srcSel)   { state.sourceType    = srcSel.value; }
        if (searchEl) { state.search        = searchEl.value.trim(); }
    }

    function resetFilters() {
        state.search           = '';
        state.categoryId       = '';
        state.difficulty       = '';
        state.maxTotalTime     = '';
        state.sourceType       = '';
        state.includeIngredients = [];
        state.excludeIngredients = [];
        state.activeTagIds       = [];
        state.page = 1;

        var catSel   = qs('[data-rs-category]', root);
        var diffSel  = qs('[data-rs-difficulty]', root);
        var maxTime  = qs('[data-rs-max-time]', root);
        var srcSel   = qs('[data-rs-source]', root);
        var searchEl = qs('[data-rs-search]', root);
        var incInput = qs('[data-rs-inc-input]', root);
        var excInput = qs('[data-rs-exc-input]', root);

        if (catSel)   { catSel.value   = ''; }
        if (diffSel)  { diffSel.value  = ''; }
        if (maxTime)  { maxTime.value  = ''; }
        if (srcSel)   { srcSel.value   = ''; }
        if (searchEl) { searchEl.value = ''; }
        if (incInput) { incInput.value = ''; }
        if (excInput) { excInput.value = ''; }

        renderIngChips('inc');
        renderIngChips('exc');
        renderTagChips();

        var detailEl = qs('[data-rs-detail]', root);
        if (detailEl) { detailEl.style.display = 'none'; }
    }

    // ─── Events ──────────────────────────────────────────────────────────────────

    function bindEvents() {
        if (!root) { return; }

        var incInput    = qs('[data-rs-inc-input]', root);
        var incResults  = qs('[data-rs-inc-results]', root);
        var excInput    = qs('[data-rs-exc-input]', root);
        var excResults  = qs('[data-rs-exc-results]', root);

        // Search button
        root.addEventListener('click', function (e) {
            var t = e.target;

            if (t.closest('[data-rs-btn]')) {
                state.page = 1;
                state.search = (qs('[data-rs-search]', root) || {}).value || '';
                state.search = state.search.trim();
                fetchResults();
                return;
            }

            if (t.closest('[data-rs-apply]')) {
                readFilters();
                state.page = 1;
                fetchResults();
                return;
            }

            if (t.closest('[data-rs-clear]') || t.closest('[data-rs-reset]')) {
                resetFilters();
                state.results  = [];
                state.total    = 0;
                state.lastPage = 1;
                renderResults();
                hideMsg();
                return;
            }

            if (t.closest('[data-rs-prev]') && state.page > 1) {
                state.page--;
                fetchResults();
                return;
            }

            if (t.closest('[data-rs-next]') && state.page < state.lastPage) {
                state.page++;
                fetchResults();
                return;
            }

            // Card click
            var card = t.closest('[data-rs-card]');
            if (card) {
                var rid = card.getAttribute('data-id');
                var recipe = state.results.filter(function (r) { return String(r.id) === String(rid); })[0];
                if (recipe) { renderDetail(recipe); }
                return;
            }

            // Tag toggle
            var tagBtn = t.closest('[data-rs-tag]');
            if (tagBtn) {
                var tid = parseInt(tagBtn.getAttribute('data-rs-tag'), 10);
                var idx = state.activeTagIds.indexOf(tid);
                if (idx === -1) {
                    state.activeTagIds.push(tid);
                } else {
                    state.activeTagIds.splice(idx, 1);
                }
                renderTagChips();
                return;
            }

            // Remove ingredient chip
            var removeBtn = t.closest('[data-rs-remove-ing]');
            if (removeBtn) {
                var removeType = removeBtn.getAttribute('data-rs-remove-ing');
                var removeId   = parseInt(removeBtn.getAttribute('data-ing-id'), 10);
                if (removeType === 'inc') {
                    state.includeIngredients = state.includeIngredients.filter(function (i) { return i.id !== removeId; });
                    renderIngChips('inc');
                } else {
                    state.excludeIngredients = state.excludeIngredients.filter(function (i) { return i.id !== removeId; });
                    renderIngChips('exc');
                }
                return;
            }

            // Pick ingredient from typeahead
            var pickBtn = t.closest('[data-rs-ing-pick]');
            if (pickBtn) {
                var pickType = pickBtn.getAttribute('data-rs-ing-pick');
                var pickId   = parseInt(pickBtn.getAttribute('data-ing-id'), 10);
                var pickName = pickBtn.getAttribute('data-ing-name') || '';
                var list     = pickType === 'inc' ? state.includeIngredients : state.excludeIngredients;
                var already  = list.filter(function (i) { return i.id === pickId; }).length;
                if (!already) {
                    list.push({ id: pickId, name: pickName });
                }
                if (pickType === 'inc') {
                    if (incInput) { incInput.value = ''; }
                    if (incResults) { incResults.style.display = 'none'; }
                    renderIngChips('inc');
                } else {
                    if (excInput) { excInput.value = ''; }
                    if (excResults) { excResults.style.display = 'none'; }
                    renderIngChips('exc');
                }
                return;
            }
        });

        // Typeahead inputs
        if (incInput && incResults) {
            incInput.addEventListener('input', function () {
                clearTimeout(incDebounceTimer);
                var term = incInput.value.trim();
                incDebounceTimer = setTimeout(function () { searchIngredients(term, incResults, 'inc'); }, 280);
            });
            incInput.addEventListener('blur', function () {
                setTimeout(function () { if (incResults) { incResults.style.display = 'none'; } }, 200);
            });
        }

        if (excInput && excResults) {
            excInput.addEventListener('input', function () {
                clearTimeout(excDebounceTimer);
                var term = excInput.value.trim();
                excDebounceTimer = setTimeout(function () { searchIngredients(term, excResults, 'exc'); }, 280);
            });
            excInput.addEventListener('blur', function () {
                setTimeout(function () { if (excResults) { excResults.style.display = 'none'; } }, 200);
            });
        }

        // Search on Enter in main search box
        var searchEl = qs('[data-rs-search]', root);
        if (searchEl) {
            searchEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    state.page   = 1;
                    state.search = searchEl.value.trim();
                    fetchResults();
                }
            });
        }
    }

    // ─── Boot ────────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        root = qs('[data-recipe-search]');
        if (!root) { return; }

        loadCategories();
        loadTags();
        bindEvents();

        // Initial empty state already shown via Blade HTML
    });

})(window, document);
