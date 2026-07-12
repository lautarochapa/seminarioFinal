(function (window, document) {
    'use strict';

    var API = '/api/v1';

    var state = {
        groups: [],
        currentGroupId: null,
        cities: [],
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

    function formData(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            data[entry[0]] = entry[1];
        });
        return data;
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-family-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-family-message]', root);
        if (alert) {
            alert.textContent = '';
            alert.className = 'alert';
            alert.style.display = 'none';
        }
    }

    function handleError(root, error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        showMessage(root, 'danger', apiError.message || error.message || 'No se pudo completar la accion.');
    }

    function requireGroup(root) {
        if (!state.currentGroupId) {
            showMessage(root, 'warning', 'Primero selecciona un grupo familiar.');
            return false;
        }

        return true;
    }

    function loadCities(root) {
        return window.CCApi.request(API + '/cities')
            .then(function (response) {
                state.cities = response.data || [];
                var select = qs('[data-family-city-select]', root);
                if (!select) { return; }
                var current = select.value;
                select.innerHTML = '<option value="">Sin ciudad asignada</option>' +
                    state.cities.map(function (c) {
                        return '<option value="' + c.id + '">' + escapeHtml(c.name) +
                            (c.province ? ' (' + escapeHtml(c.province) + ')' : '') + '</option>';
                    }).join('');
                if (current) { select.value = current; }
            })
            .catch(function () {});
    }

    function loadGroups(root) {
        clearMessage(root);

        return window.CCApi.request(API + '/family-groups')
            .then(function (response) {
                state.groups = response.data || [];
                if (!state.currentGroupId && state.groups.length) {
                    state.currentGroupId = state.groups[0].id;
                }
                if (state.currentGroupId && !state.groups.some(function (group) { return String(group.id) === String(state.currentGroupId); })) {
                    state.currentGroupId = state.groups.length ? state.groups[0].id : null;
                }

                renderGroupSelect(root);
                renderGroupDetails(root);

                if (state.currentGroupId) {
                    return Promise.all([loadMembers(root), loadPreferences(root)]);
                }

                renderMembers(root, []);
                clearPreferences(root);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function loadMembers(root) {
        if (!state.currentGroupId) {
            return Promise.resolve();
        }

        return window.CCApi.request(API + '/family-groups/' + state.currentGroupId + '/members')
            .then(function (response) {
                renderMembers(root, response.data || []);
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function loadPreferences(root) {
        if (!state.currentGroupId) {
            return Promise.resolve();
        }

        return window.CCApi.request(API + '/family-groups/' + state.currentGroupId + '/preferences')
            .then(function (response) {
                fillPreferences(root, response.data || {});
            })
            .catch(function (error) {
                handleError(root, error);
            });
    }

    function renderGroupSelect(root) {
        var select = qs('[data-family-select]', root);
        var counter = qs('[data-family-count]', root);

        counter.textContent = state.groups.length + ' grupos';

        if (!state.groups.length) {
            select.innerHTML = '<option value="">Sin grupos familiares</option>';
            return;
        }

        select.innerHTML = state.groups.map(function (group) {
            return '<option value="' + group.id + '">' + escapeHtml(group.name) + '</option>';
        }).join('');
        select.value = state.currentGroupId || '';
    }

    function currentGroup() {
        return state.groups.find(function (group) {
            return String(group.id) === String(state.currentGroupId);
        }) || null;
    }

    function renderGroupDetails(root) {
        var group = currentGroup();
        var form = qs('[data-family-edit-form]', root);

        form.elements.name.value = group ? group.name || '' : '';
        form.elements.status.value = group ? group.status || 'active' : 'active';

        var citySelect = qs('[data-family-city-select]', root);
        if (citySelect) { citySelect.value = group && group.city_id ? String(group.city_id) : ''; }

        var cityNameEl = qs('[data-family-city-name]', root);
        if (cityNameEl) {
            var city = group && group.city_id
                ? state.cities.find(function (c) { return c.id === group.city_id; })
                : null;
            cityNameEl.textContent = city ? city.name : '-';
        }

        qs('[data-family-owner]', root).textContent = group ? text(group.owner_user_id) : '-';
        qs('[data-family-address]', root).textContent = group ? text(group.default_address) : '-';
    }

    function renderMembers(root, members) {
        var body = qs('[data-members-body]', root);

        if (!state.currentGroupId) {
            body.innerHTML = '<tr><td colspan="4" class="muted">Creá o seleccioná un grupo para ver miembros.</td></tr>';
            return;
        }

        if (!members.length) {
            body.innerHTML = '<tr><td colspan="4" class="muted">Todavia no hay miembros cargados.</td></tr>';
            return;
        }

        body.innerHTML = members.map(function (member) {
            var status = member.status === 'active'
                ? '<span class="chip">Activo</span>'
                : '<span class="chip danger">' + escapeHtml(member.status) + '</span>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(member.name || ('Usuario #' + member.user_id)) + '</strong><br><span class="muted">' + escapeHtml(member.email) + '</span></td>' +
                '<td><select class="form-control" data-member-role="' + member.id + '">' +
                    '<option value="owner"' + selected(member.role, 'owner') + '>Propietario</option>' +
                    '<option value="admin"' + selected(member.role, 'admin') + '>Administrador</option>' +
                    '<option value="member"' + selected(member.role, 'member') + '>Miembro</option>' +
                '</select></td>' +
                '<td>' + status + '</td>' +
                '<td>' +
                    '<button type="button" class="btn-main btn-sm" data-member-save="' + member.id + '">Guardar</button> ' +
                    '<button type="button" class="btn-secondary-web btn-sm" data-member-remove="' + member.id + '">Quitar</button>' +
                '</td>' +
                '</tr>';
        }).join('');
    }

    function selected(current, value) {
        return String(current) === value ? ' selected' : '';
    }

    function fillPreferences(root, preferences) {
        var form = qs('[data-preferences-form]', root);
        form.elements.default_budget_mode.value = preferences.default_budget_mode || '';
        form.elements.default_shopping_mode.value = preferences.default_shopping_mode || '';
        form.elements.default_recipe_priority_mode.value = preferences.default_recipe_priority_mode || '';
        form.elements.allow_auto_stock_discount.checked = !!preferences.allow_auto_stock_discount;
    }

    function clearPreferences(root) {
        qs('[data-preferences-form]', root).reset();
    }

    function bind(root) {
        qs('[data-family-refresh]', root).addEventListener('click', function () {
            loadGroups(root);
        });

        qs('[data-family-select]', root).addEventListener('change', function (event) {
            state.currentGroupId = event.target.value || null;
            renderGroupDetails(root);
            loadMembers(root);
            loadPreferences(root);
        });

        var citySelect = qs('[data-family-city-select]', root);
        if (citySelect) {
            citySelect.addEventListener('change', function () {
                var cityNameEl = qs('[data-family-city-name]', root);
                if (!cityNameEl) { return; }
                var cityId = parseInt(citySelect.value, 10);
                var city = cityId ? state.cities.find(function (c) { return c.id === cityId; }) : null;
                cityNameEl.textContent = city ? city.name : '-';
            });
        }

        qs('[data-family-create-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);
            var form = event.currentTarget;
            window.CCApi.request(API + '/family-groups', { method: 'POST', body: formData(form) })
                .then(function (response) {
                    form.reset();
                    state.currentGroupId = response.data.id;
                    showMessage(root, 'success', 'Grupo familiar creado correctamente.');
                    return loadGroups(root);
                })
                .catch(function (error) { handleError(root, error); });
        });

        qs('[data-family-edit-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            if (!requireGroup(root)) {
                return;
            }
            window.CCApi.request(API + '/family-groups/' + state.currentGroupId, { method: 'PATCH', body: formData(event.currentTarget) })
                .then(function () {
                    showMessage(root, 'success', 'Grupo actualizado correctamente.');
                    return loadGroups(root);
                })
                .catch(function (error) { handleError(root, error); });
        });

        qs('[data-family-delete]', root).addEventListener('click', function () {
            if (!requireGroup(root)) {
                return;
            }
            window.CCApi.request(API + '/family-groups/' + state.currentGroupId, { method: 'DELETE' })
                .then(function () {
                    state.currentGroupId = null;
                    showMessage(root, 'success', 'Grupo desactivado correctamente.');
                    return loadGroups(root);
                })
                .catch(function (error) { handleError(root, error); });
        });

        qs('[data-member-create-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            if (!requireGroup(root)) {
                return;
            }
            var form = event.currentTarget;
            window.CCApi.request(API + '/family-groups/' + state.currentGroupId + '/members', { method: 'POST', body: formData(form) })
                .then(function () {
                    form.reset();
                    showMessage(root, 'success', 'Miembro agregado correctamente.');
                    return loadMembers(root);
                })
                .catch(function (error) { handleError(root, error); });
        });

        qs('[data-invitation-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            if (!requireGroup(root)) {
                return;
            }
            var form = event.currentTarget;
            window.CCApi.request(API + '/family-groups/' + state.currentGroupId + '/invitations', { method: 'POST', body: formData(form) })
                .then(function (response) {
                    form.reset();
                    showMessage(root, 'success', 'Invitacion creada. ID: ' + response.data.id);
                })
                .catch(function (error) { handleError(root, error); });
        });

        qs('[data-invitation-accept-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            var form = event.currentTarget;
            var data = formData(form);
            window.CCApi.request(API + '/family-groups/invitations/' + data.invitation_id + '/accept', { method: 'POST' })
                .then(function () {
                    form.reset();
                    showMessage(root, 'success', 'Invitacion aceptada correctamente.');
                    return loadGroups(root);
                })
                .catch(function (error) { handleError(root, error); });
        });

        qs('[data-preferences-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            if (!requireGroup(root)) {
                return;
            }
            var data = formData(event.currentTarget);
            data.allow_auto_stock_discount = event.currentTarget.elements.allow_auto_stock_discount.checked;
            window.CCApi.request(API + '/family-groups/' + state.currentGroupId + '/preferences', { method: 'PATCH', body: data })
                .then(function () {
                    showMessage(root, 'success', 'Preferencias actualizadas correctamente.');
                    return loadPreferences(root);
                })
                .catch(function (error) { handleError(root, error); });
        });

        root.addEventListener('click', function (event) {
            var target = event.target;
            if (target.matches('[data-member-save]')) {
                if (!requireGroup(root)) {
                    return;
                }
                var memberId = target.getAttribute('data-member-save');
                var role = qs('[data-member-role="' + memberId + '"]', root).value;
                window.CCApi.request(API + '/family-groups/' + state.currentGroupId + '/members/' + memberId, {
                    method: 'PATCH',
                    body: { role: role },
                })
                    .then(function () {
                        showMessage(root, 'success', 'Miembro actualizado correctamente.');
                        return loadMembers(root);
                    })
                    .catch(function (error) { handleError(root, error); });
            }

            if (target.matches('[data-member-remove]')) {
                if (!requireGroup(root)) {
                    return;
                }
                window.CCApi.request(API + '/family-groups/' + state.currentGroupId + '/members/' + target.getAttribute('data-member-remove'), { method: 'DELETE' })
                    .then(function () {
                        showMessage(root, 'success', 'Miembro quitado correctamente.');
                        return loadMembers(root);
                    })
                    .catch(function (error) { handleError(root, error); });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-family-groups]');
        if (!root || !window.CCApi) {
            return;
        }

        bind(root);
        loadCities(root).then(function () { loadGroups(root); });

        var primaryBtn = document.querySelector('[data-screen-primary-action]');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var createForm = qs('[data-family-create-form]', root);
                if (createForm) {
                    createForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    var first = createForm.querySelector('input:not([type=hidden]),select');
                    if (first) { first.focus(); }
                }
            });
        }

        var secondaryBtn = document.querySelector('[data-screen-secondary-action]');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var editForm = qs('[data-family-edit-form]', root);
                if (editForm) {
                    editForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }
    });
})(window, document);
