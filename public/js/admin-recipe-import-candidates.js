(function (window, document) {
    'use strict';

    var state = {
        page: 1,
        lastPage: 1,
        selected: null,
        ingredients: [],
        units: [],
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function escapeHtml(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpoint(path) {
        return '/api/v1' + path;
    }

    function request(path, options) {
        if (!window.CCApi || typeof window.CCApi.request !== 'function') {
            return Promise.reject({ status: 500, payload: { error: { message: 'Cliente API no disponible.' } } });
        }
        return window.CCApi.request(endpoint(path), options || {});
    }

    function messageFrom(error, fallback) {
        if (error && error.payload && error.payload.error && error.payload.error.message) {
            return error.payload.error.message;
        }
        if (error && error.status === 401) {
            return 'Sesion vencida. Inicia sesion nuevamente.';
        }
        if (error && error.status === 403) {
            return 'No tenes permiso para validar recetas.';
        }
        if (error && error.status === 404) {
            return 'Candidata inexistente.';
        }
        if (error && error.status === 409) {
            return 'La candidata ya fue finalizada o hay un conflicto.';
        }
        if (error && error.status === 422) {
            return 'Datos invalidos para la revision.';
        }
        return fallback || 'No se pudo completar la operacion.';
    }

    function showMessage(root, type, text) {
        var el = qs('[data-import-candidates-message]', root);
        if (!el) {
            return;
        }
        el.textContent = text;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function clearMessage(root) {
        var el = qs('[data-import-candidates-message]', root);
        if (!el) {
            return;
        }
        el.textContent = '';
        el.className = 'alert';
        el.style.display = 'none';
    }

    function dateLabel(value) {
        if (!value) {
            return '-';
        }
        var date = new Date(value);
        if (isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString('es-AR');
    }

    function collection(payload) {
        return payload && Array.isArray(payload.data) ? payload.data : [];
    }

    function statusChip(status) {
        var color = '#697681';
        var bg = '#eef2f5';
        if (status === 'parsed') {
            color = '#04ac85';
            bg = '#e7f7f2';
        } else if (status === 'approved' || status === 'recipe_created') {
            color = '#2f5fc4';
            bg = '#eef2ff';
        } else if (status === 'rejected') {
            color = '#b33a3a';
            bg = '#fdecea';
        }
        return '<span style="background:' + bg + ';color:' + color + ';padding:2px 8px;border-radius:50px;font-size:12px">' + escapeHtml(status) + '</span>';
    }

    function parsed(candidate) {
        return candidate && candidate.parsed_recipe_json && typeof candidate.parsed_recipe_json === 'object'
            ? candidate.parsed_recipe_json
            : {};
    }

    function rawIngredients(candidate) {
        var value = candidate && candidate.raw_ingredients_json;
        return Array.isArray(value) ? value : [];
    }

    function rawSteps(candidate) {
        var value = candidate && candidate.raw_steps_json;
        return Array.isArray(value) ? value : [];
    }

    function ingredientText(item) {
        if (typeof item === 'string') {
            return item;
        }
        if (item && typeof item === 'object') {
            return [item.quantity, item.unit, item.name || item.text || item.description].filter(Boolean).join(' ');
        }
        return String(item || '');
    }

    function stepText(item) {
        if (typeof item === 'string') {
            return item;
        }
        if (item && typeof item === 'object') {
            return item.description || item.text || '';
        }
        return String(item || '');
    }

    function mappingFor(candidate, index) {
        var mappings = parsed(candidate).ingredient_mappings || [];
        for (var i = 0; i < mappings.length; i += 1) {
            if (Number(mappings[i].ingredient_index) === Number(index)) {
                return mappings[i];
            }
        }
        return null;
    }

    function suggestionFor(candidate, index) {
        var list = candidate && Array.isArray(candidate.ingredient_suggestions) ? candidate.ingredient_suggestions : [];
        for (var i = 0; i < list.length; i += 1) {
            if (Number(list[i].index) === Number(index)) {
                return list[i];
            }
        }
        return null;
    }

    function suggestionLabel(suggestion) {
        if (!suggestion || !suggestion.suggested_ingredient_id) {
            return 'sin sugerencia';
        }
        return 'sugerido: ' + suggestion.suggested_ingredient_name + ' (#' + suggestion.suggested_ingredient_id + ', ' + suggestion.confidence + ')';
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function renderCatalogs(root) {
        var ingredientSelect = qs('[data-import-candidates-ingredient]', root);
        var unitSelect = qs('[data-import-candidates-unit]', root);
        if (ingredientSelect) {
            ingredientSelect.innerHTML = '<option value="">Ingrediente del catalogo</option>' + state.ingredients.map(function (ingredient) {
                return option(ingredient.name, ingredient.id);
            }).join('');
        }
        if (unitSelect) {
            unitSelect.innerHTML = '<option value="">Unidad</option>' + state.units.map(function (unit) {
                var label = unit.symbol ? unit.name + ' (' + unit.symbol + ')' : unit.name;
                return option(label, unit.id);
            }).join('');
        }
    }

    function buildQuery(root) {
        var params = new URLSearchParams();
        var status = qs('[data-import-candidates-status]', root);
        var source = qs('[data-import-candidates-source]', root);
        params.set('page', state.page);
        params.set('per_page', 20);
        if (status && status.value) {
            params.set('status', status.value);
        }
        if (source && source.value.trim()) {
            params.set('source_site', source.value.trim());
        }
        return params.toString();
    }

    function renderRows(root, candidates) {
        var body = qs('[data-import-candidates-body]', root);
        if (!body) {
            return;
        }
        if (!candidates.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay candidatas para los filtros seleccionados.</td></tr>';
            return;
        }
        body.innerHTML = candidates.map(function (candidate) {
            var ingredients = rawIngredients(candidate).length;
            var steps = rawSteps(candidate).length;
            return '<tr>' +
                '<td><strong>' + escapeHtml(candidate.raw_title || 'Sin titulo') + '</strong><br><span class="muted">#' + escapeHtml(candidate.id) + '</span></td>' +
                '<td>' + escapeHtml(candidate.source_site) + '<br><span class="muted">' + escapeHtml(candidate.source_url) + '</span></td>' +
                '<td>' + statusChip(candidate.status) + '</td>' +
                '<td>' + ingredients + ' ingredientes<br>' + steps + ' pasos</td>' +
                '<td>' + escapeHtml(dateLabel(candidate.created_at)) + '</td>' +
                '<td><button type="button" class="btn-ghost btn-sm" data-import-candidates-show="' + escapeHtml(candidate.id) + '">Revisar</button></td>' +
                '</tr>';
        }).join('');
    }

    function renderMeta(root, payload) {
        var meta = payload && payload.meta ? payload.meta : {};
        state.page = Number(meta.current_page || state.page || 1);
        state.lastPage = Number(meta.last_page || 1);
        var count = qs('[data-import-candidates-count]', root);
        var page = qs('[data-import-candidates-page]', root);
        var prev = qs('[data-import-candidates-prev]', root);
        var next = qs('[data-import-candidates-next]', root);
        if (count) {
            count.textContent = (meta.total || 0) + ' candidatas';
        }
        if (page) {
            page.textContent = 'Pagina ' + state.page + ' de ' + state.lastPage;
        }
        if (prev) {
            prev.disabled = state.page <= 1;
        }
        if (next) {
            next.disabled = state.page >= state.lastPage;
        }
    }

    function renderDetail(root, candidate) {
        var detail = qs('[data-import-candidates-detail]', root);
        if (!detail) {
            return;
        }
        if (!candidate) {
            detail.className = 'muted';
            detail.textContent = 'Selecciona una candidata.';
            return;
        }
        var ingredients = rawIngredients(candidate);
        var steps = rawSteps(candidate);
        var ingredientList = ingredients.length ? ingredients.map(function (item, index) {
            var mapping = mappingFor(candidate, index);
            var mapped = mapping ? ' -> ingrediente #' + mapping.ingredient_id + ', unidad #' + mapping.unit_id : ' -> sin mapear';
            var suggestion = suggestionFor(candidate, index);
            var hint = suggestion ? '<br><span class="muted" style="font-size:12px">' + escapeHtml(suggestionLabel(suggestion)) + '</span>' : '';
            return '<li>' + escapeHtml(ingredientText(item)) + '<span class="muted">' + escapeHtml(mapped) + '</span>' + hint + '</li>';
        }).join('') : '<li class="muted">Sin ingredientes parseados.</li>';
        var stepList = steps.length ? steps.map(function (item) {
            return '<li>' + escapeHtml(stepText(item)) + '</li>';
        }).join('') : '<li class="muted">Sin pasos parseados.</li>';
        var link = candidate.source_url
            ? '<a href="' + escapeHtml(candidate.source_url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(candidate.source_url) + '</a>'
            : '-';

        detail.className = '';
        detail.innerHTML = '<div class="line"><span>ID</span><strong>#' + escapeHtml(candidate.id) + '</strong></div>' +
            '<div class="line"><span>Estado</span><strong>' + statusChip(candidate.status) + '</strong></div>' +
            '<div class="line"><span>Fuente</span><strong>' + escapeHtml(candidate.source_site) + '</strong></div>' +
            '<div class="line"><span>URL</span><span style="word-break:break-all">' + link + '</span></div>' +
            '<h3 style="font-size:15px;margin:12px 0 6px">' + escapeHtml(candidate.raw_title || 'Sin titulo') + '</h3>' +
            '<p class="muted">' + escapeHtml(candidate.raw_description) + '</p>' +
            '<h3 style="font-size:15px;margin:12px 0 6px">Ingredientes</h3><ul style="padding-left:18px">' + ingredientList + '</ul>' +
            '<h3 style="font-size:15px;margin:12px 0 6px">Pasos</h3><ol style="padding-left:18px">' + stepList + '</ol>';
    }

    function fillForms(root, candidate) {
        var edit = qs('[data-import-candidates-edit-form]', root);
        var map = qs('[data-import-candidates-map-form]', root);
        var reject = qs('[data-import-candidates-reject-form]', root);
        var indexSelect = qs('[data-import-candidates-ingredient-index]', root);
        if (edit) {
            edit.id.value = candidate ? candidate.id : '';
            edit.raw_title.value = candidate ? (candidate.raw_title || '') : '';
            edit.raw_description.value = candidate ? (candidate.raw_description || '') : '';
            edit.raw_image_url.value = candidate ? (candidate.raw_image_url || '') : '';
            edit.raw_ingredients_json.value = candidate ? rawIngredients(candidate).map(ingredientText).join('\n') : '';
            edit.raw_steps_json.value = candidate ? rawSteps(candidate).map(stepText).join('\n') : '';
        }
        if (map) {
            map.id.value = candidate ? candidate.id : '';
            map.quantity.value = '';
            map.notes.value = '';
            map.is_optional.checked = false;
            var hint = qs('[data-import-candidates-suggestion]', root);
            if (hint) { hint.textContent = ''; }
        }
        if (reject) {
            reject.id.value = candidate ? candidate.id : '';
            reject.reason.value = '';
        }
        if (indexSelect) {
            var options = candidate ? rawIngredients(candidate).map(function (item, index) {
                return option((index + 1) + '. ' + ingredientText(item), index);
            }).join('') : '';
            indexSelect.innerHTML = '<option value="">Ingrediente parseado</option>' + options;
        }
    }

    function applySuggestionToMapForm(root) {
        var form = qs('[data-import-candidates-map-form]', root);
        var hint = qs('[data-import-candidates-suggestion]', root);
        if (!form || !state.selected) {
            return;
        }
        var index = form.ingredient_index.value;
        if (index === '') {
            if (hint) { hint.textContent = ''; }
            return;
        }
        var suggestion = suggestionFor(state.selected, Number(index));
        if (hint) {
            hint.textContent = suggestion
                ? suggestionLabel(suggestion) + (suggestion.parsed_quantity != null ? ' - cantidad detectada: ' + suggestion.parsed_quantity : '')
                : '';
        }
        if (suggestion && suggestion.suggested_ingredient_id && !form.ingredient_id.value) {
            form.ingredient_id.value = String(suggestion.suggested_ingredient_id);
        }
        if (suggestion && suggestion.parsed_quantity != null && form.quantity.value === '') {
            form.quantity.value = String(suggestion.parsed_quantity);
        }
    }

    function loadCatalogs(root) {
        return Promise.all([
            request('/ingredients?per_page=100&status=active&sort=name&order=asc').catch(function () { return { data: [] }; }),
            request('/units?per_page=100&sort=name&order=asc').catch(function () { return { data: [] }; }),
        ]).then(function (responses) {
            state.ingredients = collection(responses[0]);
            state.units = collection(responses[1]);
            renderCatalogs(root);
        });
    }

    function loadCandidates(root) {
        var body = qs('[data-import-candidates-body]', root);
        if (body) {
            body.innerHTML = '<tr><td colspan="6" class="muted">Cargando candidatas...</td></tr>';
        }
        return request('/admin/recipes/import-candidates?' + buildQuery(root))
            .then(function (payload) {
                renderRows(root, collection(payload));
                renderMeta(root, payload);
            })
            .catch(function (error) {
                if (body) {
                    body.innerHTML = '<tr><td colspan="6" class="muted">' + escapeHtml(messageFrom(error)) + '</td></tr>';
                }
                showMessage(root, 'danger', messageFrom(error));
            });
    }

    function loadCandidate(root, id) {
        clearMessage(root);
        return request('/admin/recipes/import-candidates/' + encodeURIComponent(id))
            .then(function (payload) {
                state.selected = payload.data || null;
                renderDetail(root, state.selected);
                fillForms(root, state.selected);
            })
            .catch(function (error) {
                showMessage(root, 'danger', messageFrom(error));
            });
    }

    function linesToArray(value, steps) {
        return String(value || '').split(/\r?\n/).map(function (line, index) {
            var text = line.trim();
            if (!text) {
                return null;
            }
            return steps ? { step_number: index + 1, description: text } : text;
        }).filter(Boolean);
    }

    function saveEdit(root, form) {
        if (!form.id.value) {
            showMessage(root, 'danger', 'Selecciona una candidata.');
            return;
        }
        clearMessage(root);
        request('/admin/recipes/import-candidates/' + encodeURIComponent(form.id.value), {
            method: 'PATCH',
            body: {
                raw_title: form.raw_title.value.trim(),
                raw_description: form.raw_description.value.trim() || null,
                raw_image_url: form.raw_image_url.value.trim() || null,
                raw_ingredients_json: linesToArray(form.raw_ingredients_json.value, false),
                raw_steps_json: linesToArray(form.raw_steps_json.value, true),
            },
        }).then(function (payload) {
            showMessage(root, 'success', 'Parseo actualizado.');
            state.selected = payload.data;
            renderDetail(root, state.selected);
            fillForms(root, state.selected);
            loadCandidates(root);
        }).catch(function (error) {
            showMessage(root, 'danger', messageFrom(error));
        });
    }

    function saveMapping(root, form) {
        if (!form.id.value) {
            showMessage(root, 'danger', 'Selecciona una candidata.');
            return;
        }
        clearMessage(root);
        request('/admin/recipes/import-candidates/' + encodeURIComponent(form.id.value) + '/map-ingredient', {
            method: 'POST',
            body: {
                ingredient_index: Number(form.ingredient_index.value),
                ingredient_id: Number(form.ingredient_id.value),
                unit_id: Number(form.unit_id.value),
                quantity: form.quantity.value === '' ? null : Number(form.quantity.value),
                notes: form.notes.value.trim() || null,
                is_optional: form.is_optional.checked,
            },
        }).then(function (payload) {
            showMessage(root, 'success', 'Ingrediente mapeado.');
            state.selected = payload.data;
            renderDetail(root, state.selected);
            fillForms(root, state.selected);
            loadCandidates(root);
        }).catch(function (error) {
            showMessage(root, 'danger', messageFrom(error));
        });
    }

    function postAction(root, action, body) {
        if (!state.selected || !state.selected.id) {
            showMessage(root, 'danger', 'Selecciona una candidata.');
            return;
        }
        clearMessage(root);
        request('/admin/recipes/import-candidates/' + encodeURIComponent(state.selected.id) + '/' + action, {
            method: 'POST',
            body: body || {},
        }).then(function (payload) {
            showMessage(root, 'success', action === 'create-recipe' ? 'Receta creada.' : 'Candidata actualizada.');
            if (payload.data && payload.data.status) {
                state.selected = payload.data;
                renderDetail(root, state.selected);
                fillForms(root, state.selected);
            }
            loadCandidates(root);
        }).catch(function (error) {
            showMessage(root, 'danger', messageFrom(error));
        });
    }

    function bind(root) {
        var status = qs('[data-import-candidates-status]', root);
        var source = qs('[data-import-candidates-source]', root);
        var refresh = qs('[data-import-candidates-refresh]', root);
        var prev = qs('[data-import-candidates-prev]', root);
        var next = qs('[data-import-candidates-next]', root);
        var editForm = qs('[data-import-candidates-edit-form]', root);
        var mapForm = qs('[data-import-candidates-map-form]', root);
        var rejectForm = qs('[data-import-candidates-reject-form]', root);
        var approve = qs('[data-import-candidates-approve]', root);
        var create = qs('[data-import-candidates-create]', root);

        if (status) {
            status.addEventListener('change', function () {
                state.page = 1;
                loadCandidates(root);
            });
        }
        if (source) {
            source.addEventListener('change', function () {
                state.page = 1;
                loadCandidates(root);
            });
        }
        if (refresh) {
            refresh.addEventListener('click', function () {
                loadCandidates(root);
            });
        }
        if (prev) {
            prev.addEventListener('click', function () {
                if (state.page > 1) {
                    state.page -= 1;
                    loadCandidates(root);
                }
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                if (state.page < state.lastPage) {
                    state.page += 1;
                    loadCandidates(root);
                }
            });
        }
        if (editForm) {
            editForm.addEventListener('submit', function (event) {
                event.preventDefault();
                saveEdit(root, editForm);
            });
        }
        if (mapForm) {
            mapForm.addEventListener('submit', function (event) {
                event.preventDefault();
                saveMapping(root, mapForm);
            });
            var indexSelect = qs('[data-import-candidates-ingredient-index]', root);
            if (indexSelect) {
                indexSelect.addEventListener('change', function () {
                    applySuggestionToMapForm(root);
                });
            }
        }
        if (rejectForm) {
            rejectForm.addEventListener('submit', function (event) {
                event.preventDefault();
                postAction(root, 'reject', { reason: rejectForm.reason.value.trim() || null });
            });
        }
        if (approve) {
            approve.addEventListener('click', function () {
                postAction(root, 'approve');
            });
        }
        if (create) {
            create.addEventListener('click', function () {
                postAction(root, 'create-recipe', { is_public: false });
            });
        }
        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-import-candidates-show]');
            if (button) {
                loadCandidate(root, button.getAttribute('data-import-candidates-show'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-recipe-import-candidates]');
        if (!root) {
            return;
        }
        bind(root);
        loadCatalogs(root);
        loadCandidates(root);
    });
})(window, document);
