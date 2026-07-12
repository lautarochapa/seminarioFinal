(function (window, document) {
    'use strict';

    var state = {
        units: [],
        publicUnits: [],
        conversions: [],
        ingredients: [],
        unitsPage: 1,
        unitsLastPage: 1,
        conversionsPage: 1,
        conversionsLastPage: 1,
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function text(value) {
        return value === null || value === undefined || value === '' ? '-' : String(value);
    }

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpoint(path) {
        return '/api/v1' + path;
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-units-message]', root);
        if (!alert) {
            return;
        }
        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-units-message]', root);
        if (alert) {
            alert.textContent = '';
            alert.className = 'alert';
            alert.style.display = 'none';
        }
    }

    function handleError(root, error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        showMessage(root, 'danger', apiError.message || error.message || 'No se pudo completar la operacion.');
    }

    function unitLabel(unit) {
        if (!unit) {
            return '-';
        }
        return unit.name + (unit.symbol ? ' (' + unit.symbol + ')' : '');
    }

    function option(label, value) {
        return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
    }

    function numericOrNull(value) {
        return value === '' || value === null || value === undefined ? null : Number(value);
    }

    function setActiveTab(root, tab) {
        Array.from(root.querySelectorAll('[data-units-tab]')).forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-units-tab') === tab);
        });
        Array.from(root.querySelectorAll('[data-units-panel]')).forEach(function (panel) {
            panel.style.display = panel.getAttribute('data-units-panel') === tab ? '' : 'none';
        });
    }

    function fetchLookups(root) {
        return Promise.all([
            window.CCApi.request(endpoint('/admin/units?per_page=100&sort=name&order=asc')),
            window.CCApi.request(endpoint('/admin/ingredients?per_page=100&status=active&sort=name&order=asc')),
        ]).then(function (responses) {
            state.units = responses[0].data || [];
            state.ingredients = responses[1].data || [];
            renderLookups(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderLookups(root) {
        var unitSelects = [
            qs('[data-conversions-from]', root),
            qs('[data-conversions-to]', root),
            qs('[data-conversion-from-select]', root),
            qs('[data-conversion-to-select]', root),
        ];
        var ingredientSelects = [
            qs('[data-conversions-ingredient]', root),
            qs('[data-conversion-ingredient-select]', root),
        ];

        unitSelects.forEach(function (select, index) {
            if (!select) {
                return;
            }
            var first = index < 2
                ? (index === 0 ? 'Origen' : 'Destino')
                : (index === 2 ? 'Unidad origen' : 'Unidad destino');
            var current = select.value;
            select.innerHTML = '<option value="">' + first + '</option>' + state.units.map(function (unit) {
                return option(unitLabel(unit), unit.id);
            }).join('');
            select.value = current;
        });

        ingredientSelects.forEach(function (select, index) {
            if (!select) {
                return;
            }
            var current = select.value;
            var first = index === 0 ? 'General o ingrediente' : 'Conversion general';
            select.innerHTML = '<option value="">' + first + '</option>' + state.ingredients.map(function (ingredient) {
                return option(ingredient.name, ingredient.id);
            }).join('');
            select.value = current;
        });
    }

    function fetchUnits(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-units-search]', root);
        var type = qs('[data-units-type]', root);
        var status = qs('[data-units-status]', root);

        state.unitsPage = page || state.unitsPage || 1;
        params.set('page', String(state.unitsPage));
        params.set('per_page', '50');
        params.set('sort', 'code');
        params.set('order', 'asc');
        if (search && search.value) {
            params.set('search', search.value);
        }
        if (type && type.value) {
            params.set('type', type.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/units?' + params.toString()))
            .then(function (response) {
                state.units = response.data || [];
                state.unitsLastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderUnits(root, response.meta || {});
                renderLookups(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderUnits(root, meta) {
        var body = qs('[data-units-body]', root);
        var count = qs('[data-units-count]', root);
        var page = qs('[data-units-page]', root);

        if (count) {
            count.textContent = (meta.total || state.units.length) + ' unidades';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.unitsPage) + ' de ' + (meta.last_page || state.unitsLastPage);
        }
        if (!state.units.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay unidades cargadas.</td></tr>';
            return;
        }

        body.innerHTML = state.units.map(function (unit) {
            var action = unit.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-unit-delete="' + unit.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-unit-restore="' + unit.id + '">Restaurar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(unit.code) + '</strong></td>' +
                '<td>' + escapeHtml(unit.name) + '</td>' +
                '<td>' + escapeHtml(unit.type) + '</td>' +
                '<td>' + escapeHtml(unit.symbol) + '</td>' +
                '<td>' + escapeHtml(unit.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-unit-edit="' + unit.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function unitPayload(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = entry[1];
            if (key === 'id' || value === '') {
                return;
            }
            data[key] = value;
        });
        return data;
    }

    function resetUnitForm(root) {
        var form = qs('[data-unit-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-unit-form-title]', root).textContent = 'Nueva unidad';
    }

    function fillUnitForm(root, unit) {
        var form = qs('[data-unit-form]', root);
        form.elements.id.value = unit.id;
        form.elements.code.value = unit.code || '';
        form.elements.name.value = unit.name || '';
        form.elements.type.value = unit.type || '';
        form.elements.symbol.value = unit.symbol || '';
        form.elements.status.value = unit.status || 'active';
        qs('[data-unit-form-title]', root).textContent = 'Editar unidad #' + unit.id;
    }

    function saveUnit(root, form) {
        var id = form.elements.id.value;
        var path = id ? '/admin/units/' + id : '/admin/units';
        var method = id ? 'PATCH' : 'POST';

        clearMessage(root);
        window.CCApi.request(endpoint(path), {
            method: method,
            body: unitPayload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Unidad actualizada.' : 'Unidad creada.');
            resetUnitForm(root);
            fetchUnits(root);
            fetchPublicUnits(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteUnit(root, id) {
        if (!window.confirm('Eliminar esta unidad?')) {
            return;
        }
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/units/' + id), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Unidad eliminada.');
                fetchUnits(root);
                fetchPublicUnits(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function restoreUnit(root, id) {
        if (!id) {
            showMessage(root, 'warning', 'Ingresa un ID de unidad.');
            return;
        }
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/units/' + id + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Unidad restaurada.');
                fetchUnits(root);
                fetchPublicUnits(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function fetchConversions(root, page) {
        var params = new URLSearchParams();
        var search = qs('[data-conversions-search]', root);
        var from = qs('[data-conversions-from]', root);
        var to = qs('[data-conversions-to]', root);
        var ingredient = qs('[data-conversions-ingredient]', root);
        var status = qs('[data-conversions-status]', root);

        state.conversionsPage = page || state.conversionsPage || 1;
        params.set('page', String(state.conversionsPage));
        params.set('per_page', '50');
        params.set('sort', 'id');
        params.set('order', 'asc');
        if (search && search.value) {
            params.set('search', search.value);
        }
        if (from && from.value) {
            params.set('from_unit_id', from.value);
        }
        if (to && to.value) {
            params.set('to_unit_id', to.value);
        }
        if (ingredient && ingredient.value) {
            params.set('ingredient_id', ingredient.value);
        }
        if (status && status.value) {
            params.set('status', status.value);
        }

        return window.CCApi.request(endpoint('/admin/unit-conversions?' + params.toString()))
            .then(function (response) {
                state.conversions = response.data || [];
                state.conversionsLastPage = response.meta && response.meta.last_page ? response.meta.last_page : 1;
                renderConversions(root, response.meta || {});
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderConversions(root, meta) {
        var body = qs('[data-conversions-body]', root);
        var count = qs('[data-conversions-count]', root);
        var page = qs('[data-conversions-page]', root);

        if (count) {
            count.textContent = (meta.total || state.conversions.length) + ' conversiones';
        }
        if (page) {
            page.textContent = 'Pagina ' + (meta.current_page || state.conversionsPage) + ' de ' + (meta.last_page || state.conversionsLastPage);
        }
        if (!state.conversions.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">No hay conversiones cargadas.</td></tr>';
            return;
        }

        body.innerHTML = state.conversions.map(function (conversion) {
            var action = conversion.status === 'active'
                ? '<button type="button" class="btn-ghost btn-sm" data-conversion-delete="' + conversion.id + '">Eliminar</button>'
                : '<button type="button" class="btn-ghost btn-sm" data-conversion-restore="' + conversion.id + '">Restaurar</button>';

            return '<tr>' +
                '<td>' + escapeHtml(unitLabel(conversion.from_unit)) + '</td>' +
                '<td>' + escapeHtml(unitLabel(conversion.to_unit)) + '</td>' +
                '<td>' + escapeHtml(conversion.ingredient ? conversion.ingredient.name : 'General') + '</td>' +
                '<td><strong>' + escapeHtml(conversion.factor) + '</strong></td>' +
                '<td>' + escapeHtml(conversion.notes) + '</td>' +
                '<td>' + escapeHtml(conversion.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-conversion-edit="' + conversion.id + '">Editar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');
    }

    function conversionPayload(form) {
        var data = {
            from_unit_id: parseInt(form.elements.from_unit_id.value, 10),
            to_unit_id: parseInt(form.elements.to_unit_id.value, 10),
            factor: Number(form.elements.factor.value),
            status: form.elements.status.value || 'active',
        };
        var ingredientId = form.elements.ingredient_id.value;
        var notes = form.elements.notes.value;

        data.ingredient_id = ingredientId === '' ? null : parseInt(ingredientId, 10);
        if (notes !== '') {
            data.notes = notes;
        } else {
            data.notes = null;
        }

        Object.keys(data).forEach(function (key) {
            if (data[key] === null && key !== 'ingredient_id' && key !== 'notes') {
                delete data[key];
            }
        });

        return data;
    }

    function resetConversionForm(root) {
        var form = qs('[data-conversion-form]', root);
        form.reset();
        form.elements.id.value = '';
        form.elements.status.value = 'active';
        qs('[data-conversion-form-title]', root).textContent = 'Nueva conversion';
    }

    function fillConversionForm(root, conversion) {
        var form = qs('[data-conversion-form]', root);
        form.elements.id.value = conversion.id;
        form.elements.from_unit_id.value = conversion.from_unit_id || '';
        form.elements.to_unit_id.value = conversion.to_unit_id || '';
        form.elements.ingredient_id.value = conversion.ingredient_id || '';
        form.elements.factor.value = conversion.factor || '';
        form.elements.notes.value = conversion.notes || '';
        form.elements.status.value = conversion.status || 'active';
        qs('[data-conversion-form-title]', root).textContent = 'Editar conversion #' + conversion.id;
    }

    function saveConversion(root, form) {
        var id = form.elements.id.value;
        var path = id ? '/admin/unit-conversions/' + id : '/admin/unit-conversions';
        var method = id ? 'PATCH' : 'POST';

        clearMessage(root);
        window.CCApi.request(endpoint(path), {
            method: method,
            body: conversionPayload(form),
        }).then(function () {
            showMessage(root, 'success', id ? 'Conversion actualizada.' : 'Conversion creada.');
            resetConversionForm(root);
            fetchConversions(root);
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function deleteConversion(root, id) {
        if (!window.confirm('Eliminar esta conversion?')) {
            return;
        }
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/unit-conversions/' + id), { method: 'DELETE' })
            .then(function () {
                showMessage(root, 'success', 'Conversion eliminada.');
                fetchConversions(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function restoreConversion(root, id) {
        if (!id) {
            showMessage(root, 'warning', 'Ingresa un ID de conversion.');
            return;
        }
        clearMessage(root);
        window.CCApi.request(endpoint('/admin/unit-conversions/' + id + '/restore'), { method: 'PATCH' })
            .then(function () {
                showMessage(root, 'success', 'Conversion restaurada.');
                fetchConversions(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function fetchPublicUnits(root) {
        var params = new URLSearchParams();
        var search = qs('[data-units-public-search]', root);
        params.set('per_page', '100');
        params.set('sort', 'code');
        params.set('order', 'asc');
        if (search && search.value) {
            params.set('search', search.value);
        }

        return window.CCApi.request(endpoint('/units?' + params.toString()))
            .then(function (response) {
                state.publicUnits = response.data || [];
                renderPublicUnits(root);
            }).catch(function (error) {
                handleError(root, error);
            });
    }

    function renderPublicUnits(root) {
        var body = qs('[data-units-public-body]', root);
        var count = qs('[data-units-public-count]', root);
        if (count) {
            count.textContent = state.publicUnits.length + ' unidades activas';
        }
        if (!state.publicUnits.length) {
            body.innerHTML = '<tr><td colspan="4" class="muted">No hay unidades activas.</td></tr>';
            return;
        }

        body.innerHTML = state.publicUnits.map(function (unit) {
            return '<tr>' +
                '<td><strong>' + escapeHtml(unit.code) + '</strong></td>' +
                '<td>' + escapeHtml(unit.name) + '</td>' +
                '<td>' + escapeHtml(unit.type) + '</td>' +
                '<td>' + escapeHtml(unit.symbol) + '</td>' +
                '</tr>';
        }).join('');
    }

    function bind(root) {
        var unitForm = qs('[data-unit-form]', root);
        var conversionForm = qs('[data-conversion-form]', root);

        Array.from(root.querySelectorAll('[data-units-tab]')).forEach(function (button) {
            button.addEventListener('click', function () {
                setActiveTab(root, button.getAttribute('data-units-tab'));
            });
        });

        ['[data-units-search]', '[data-units-type]', '[data-units-status]'].forEach(function (selector) {
            var element = qs(selector, root);
            element.addEventListener(element.tagName === 'INPUT' ? 'input' : 'change', function () {
                fetchUnits(root, 1);
            });
        });
        qs('[data-units-refresh]', root).addEventListener('click', function () { fetchUnits(root, 1); });
        qs('[data-units-prev]', root).addEventListener('click', function () {
            if (state.unitsPage > 1) {
                fetchUnits(root, state.unitsPage - 1);
            }
        });
        qs('[data-units-next]', root).addEventListener('click', function () {
            if (state.unitsPage < state.unitsLastPage) {
                fetchUnits(root, state.unitsPage + 1);
            }
        });
        qs('[data-unit-reset]', root).addEventListener('click', function () { resetUnitForm(root); });
        qs('[data-unit-restore-submit]', root).addEventListener('click', function () {
            restoreUnit(root, qs('[data-unit-restore-id]', root).value);
        });

        ['[data-conversions-search]', '[data-conversions-from]', '[data-conversions-to]', '[data-conversions-ingredient]', '[data-conversions-status]'].forEach(function (selector) {
            var element = qs(selector, root);
            element.addEventListener(element.tagName === 'INPUT' ? 'input' : 'change', function () {
                fetchConversions(root, 1);
            });
        });
        qs('[data-conversions-refresh]', root).addEventListener('click', function () { fetchConversions(root, 1); });
        qs('[data-conversions-prev]', root).addEventListener('click', function () {
            if (state.conversionsPage > 1) {
                fetchConversions(root, state.conversionsPage - 1);
            }
        });
        qs('[data-conversions-next]', root).addEventListener('click', function () {
            if (state.conversionsPage < state.conversionsLastPage) {
                fetchConversions(root, state.conversionsPage + 1);
            }
        });
        qs('[data-conversion-reset]', root).addEventListener('click', function () { resetConversionForm(root); });
        qs('[data-conversion-restore-submit]', root).addEventListener('click', function () {
            restoreConversion(root, qs('[data-conversion-restore-id]', root).value);
        });
        qs('[data-units-public-refresh]', root).addEventListener('click', function () { fetchPublicUnits(root); });
        qs('[data-units-public-search]', root).addEventListener('input', function () { fetchPublicUnits(root); });

        unitForm.addEventListener('submit', function (event) {
            event.preventDefault();
            saveUnit(root, unitForm);
        });
        conversionForm.addEventListener('submit', function (event) {
            event.preventDefault();
            saveConversion(root, conversionForm);
        });

        root.addEventListener('click', function (event) {
            var target = event.target;
            var unitEdit = target.getAttribute('data-unit-edit');
            var unitDelete = target.getAttribute('data-unit-delete');
            var unitRestore = target.getAttribute('data-unit-restore');
            var conversionEdit = target.getAttribute('data-conversion-edit');
            var conversionDelete = target.getAttribute('data-conversion-delete');
            var conversionRestore = target.getAttribute('data-conversion-restore');

            if (unitEdit) {
                var unit = state.units.filter(function (item) { return String(item.id) === String(unitEdit); })[0];
                if (unit) {
                    fillUnitForm(root, unit);
                }
            }
            if (unitDelete) {
                deleteUnit(root, unitDelete);
            }
            if (unitRestore) {
                restoreUnit(root, unitRestore);
            }
            if (conversionEdit) {
                var conversion = state.conversions.filter(function (item) { return String(item.id) === String(conversionEdit); })[0];
                if (conversion) {
                    fillConversionForm(root, conversion);
                }
            }
            if (conversionDelete) {
                deleteConversion(root, conversionDelete);
            }
            if (conversionRestore) {
                restoreConversion(root, conversionRestore);
            }
        });

        fetchLookups(root).then(function () {
            fetchUnits(root, 1);
            fetchConversions(root, 1);
            fetchPublicUnits(root);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-admin-units]');
        if (!root || !window.CCApi) {
            return;
        }
        bind(root);
    });
})(window, document);
