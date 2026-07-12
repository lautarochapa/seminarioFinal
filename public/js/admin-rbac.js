(function (window, document) {
    'use strict';

    var state = {
        users: [],
        roles: [],
        permissions: [],
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function text(value) {
        return value === null || value === undefined || value === '' ? '-' : String(value);
    }

    function showMessage(root, type, message) {
        var alert = qs('[data-rbac-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearMessage(root) {
        var alert = qs('[data-rbac-message]', root);
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

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function optionList(items, labelFn) {
        return items.map(function (item) {
            return '<option value="' + item.id + '">' + escapeHtml(labelFn(item)) + '</option>';
        }).join('');
    }

    function fetchAll(path, params) {
        var clean = {};
        Object.keys(params || {}).forEach(function (key) {
            var value = params[key];
            if (value !== null && value !== undefined && value !== '') {
                clean[key] = value;
            }
        });
        var query = new URLSearchParams(clean);
        if (!query.has('per_page')) {
            query.set('per_page', '100');
        }
        return window.CCApi.request(path + '?' + query.toString());
    }

    function loadCatalogs() {
        return Promise.all([
            fetchAll('/admin/roles', { order: 'asc' }),
            fetchAll('/admin/permissions', { order: 'asc' }),
        ]).then(function (responses) {
            state.roles = responses[0].data || [];
            state.permissions = responses[1].data || [];
            renderRoleOptions();
            renderPermissionOptions();
        });
    }

    function loadUsers() {
        var root = qs('[data-rbac-users]');
        if (!root) {
            return Promise.resolve();
        }

        var search = qs('[data-users-search]', root);
        var includeDeleted = qs('[data-users-deleted]', root);
        var params = {
            search: search ? search.value : '',
            include_deleted: includeDeleted && includeDeleted.checked ? '1' : '',
            per_page: '50',
        };

        return fetchAll('/admin/users', params).then(function (response) {
            state.users = response.data || [];
            renderUsers(root, response.meta || {});
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function loadRoles() {
        var root = qs('[data-rbac-roles]');
        if (!root) {
            return Promise.resolve();
        }

        var search = qs('[data-roles-search]', root);
        return fetchAll('/admin/roles', { search: search ? search.value : '', per_page: '50' }).then(function (response) {
            state.roles = response.data || [];
            renderRoles(root, response.meta || {});
            renderRoleOptions();
        }).catch(function (error) {
            handleError(root, error);
        });
    }

    function renderRoleOptions() {
        qsa('[data-role-select]').forEach(function (select) {
            var current = select.value;
            select.innerHTML = '<option value="">Seleccionar rol</option>' + optionList(state.roles, function (role) {
                return role.name + ' (' + role.code + ')';
            });
            select.value = current;
        });
    }

    function renderPermissionOptions() {
        qsa('[data-permission-select]').forEach(function (select) {
            var current = select.value;
            select.innerHTML = '<option value="">Seleccionar permiso</option>' + optionList(state.permissions, function (permission) {
                return permission.code;
            });
            select.value = current;
        });
    }

    function renderUsers(root, meta) {
        var body = qs('[data-users-body]', root);
        var counter = qs('[data-users-count]', root);
        if (counter) {
            counter.textContent = (meta.total || state.users.length) + ' usuarios';
        }

        body.innerHTML = state.users.map(function (user) {
            var roles = (user.roles || []).map(function (role) {
                return '<span class="chip">' + escapeHtml(role.code) + '<button type="button" data-remove-user-role data-user-id="' + user.id + '" data-role-id="' + role.id + '">x</button></span>';
            }).join('') || '<span class="muted">Sin roles</span>';
            var deleted = user.deleted_at ? '<span class="chip danger">eliminado</span>' : '';
            var action = user.deleted_at
                ? '<button type="button" class="btn-ghost" data-restore-user="' + user.id + '">Restaurar</button>'
                : '<button type="button" class="btn-ghost" data-delete-user="' + user.id + '">Eliminar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(user.name) + '</strong><br><span class="muted">' + escapeHtml(user.email) + '</span></td>' +
                '<td>' + escapeHtml(user.username || '-') + '</td>' +
                '<td>' + escapeHtml(user.status) + ' ' + deleted + '</td>' +
                '<td class="chips">' + roles + '</td>' +
                '<td><select class="form-control form-control-sm" data-role-select data-user-role-select="' + user.id + '"></select></td>' +
                '<td><button type="button" class="btn-main btn-sm" data-assign-user-role="' + user.id + '">Asignar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');

        renderRoleOptions();
    }

    function renderRoles(root, meta) {
        var body = qs('[data-roles-body]', root);
        var counter = qs('[data-roles-count]', root);
        if (counter) {
            counter.textContent = (meta.total || state.roles.length) + ' roles';
        }

        body.innerHTML = state.roles.map(function (role) {
            var permissions = (role.permissions || []).map(function (permission) {
                return '<span class="chip">' + escapeHtml(permission.code) + '<button type="button" data-remove-role-permission data-role-id="' + role.id + '" data-permission-id="' + permission.id + '">x</button></span>';
            }).join('') || '<span class="muted">Sin permisos</span>';
            var action = role.status === 'inactive'
                ? '<button type="button" class="btn-ghost" data-restore-role="' + role.id + '">Activar</button>'
                : '<button type="button" class="btn-ghost" data-delete-role="' + role.id + '">Desactivar</button>';

            return '<tr>' +
                '<td><strong>' + escapeHtml(role.name) + '</strong><br><span class="muted">' + escapeHtml(role.code) + '</span></td>' +
                '<td>' + escapeHtml(role.status) + '</td>' +
                '<td class="chips">' + permissions + '</td>' +
                '<td><select class="form-control form-control-sm" data-permission-select data-role-permission-select="' + role.id + '"></select></td>' +
                '<td><button type="button" class="btn-main btn-sm" data-assign-role-permission="' + role.id + '">Asignar</button> ' + action + '</td>' +
                '</tr>';
        }).join('');

        renderPermissionOptions();
    }

    function formData(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            if (entry[0] !== '_token') {
                data[entry[0]] = entry[1];
            }
        });
        return data;
    }

    function bindUserPanel(root) {
        qs('[data-users-refresh]', root).addEventListener('click', loadUsers);
        var search = qs('[data-users-search]', root);
        if (search) {
            search.addEventListener('input', debounce(loadUsers, 300));
        }
        var includeDeleted = qs('[data-users-deleted]', root);
        if (includeDeleted) {
            includeDeleted.addEventListener('change', loadUsers);
        }

        qs('[data-user-create-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);
            var form = event.currentTarget;
            window.CCApi.request('/admin/users', { method: 'POST', body: formData(form) })
                .then(function () {
                    form.reset();
                    showMessage(root, 'success', 'Usuario creado correctamente.');
                    return loadUsers();
                })
                .catch(function (error) { handleError(root, error); });
        });

        root.addEventListener('click', function (event) {
            var target = event.target;
            var userId;
            if (target.matches('[data-assign-user-role]')) {
                userId = target.getAttribute('data-assign-user-role');
                var select = qs('[data-user-role-select="' + userId + '"]', root);
                if (!select || !select.value) {
                    showMessage(root, 'warning', 'Selecciona un rol para asignar.');
                    return;
                }
                window.CCApi.request('/admin/users/' + userId + '/roles', { method: 'POST', body: { role_id: select.value } })
                    .then(loadUsers)
                    .catch(function (error) { handleError(root, error); });
            }
            if (target.matches('[data-remove-user-role]')) {
                window.CCApi.request('/admin/users/' + target.dataset.userId + '/roles/' + target.dataset.roleId, { method: 'DELETE' })
                    .then(loadUsers)
                    .catch(function (error) { handleError(root, error); });
            }
            if (target.matches('[data-delete-user]')) {
                window.CCApi.request('/admin/users/' + target.getAttribute('data-delete-user'), { method: 'DELETE' })
                    .then(loadUsers)
                    .catch(function (error) { handleError(root, error); });
            }
            if (target.matches('[data-restore-user]')) {
                window.CCApi.request('/admin/users/' + target.getAttribute('data-restore-user') + '/restore', { method: 'PATCH' })
                    .then(loadUsers)
                    .catch(function (error) { handleError(root, error); });
            }
        });
    }

    function bindRolePanel(root) {
        qs('[data-roles-refresh]', root).addEventListener('click', function () {
            loadCatalogs().then(loadRoles);
        });
        var search = qs('[data-roles-search]', root);
        if (search) {
            search.addEventListener('input', debounce(loadRoles, 300));
        }

        qs('[data-role-create-form]', root).addEventListener('submit', function (event) {
            event.preventDefault();
            clearMessage(root);
            var form = event.currentTarget;
            window.CCApi.request('/admin/roles', { method: 'POST', body: formData(form) })
                .then(function () {
                    form.reset();
                    showMessage(root, 'success', 'Rol creado correctamente.');
                    return loadCatalogs().then(loadRoles);
                })
                .catch(function (error) { handleError(root, error); });
        });

        root.addEventListener('click', function (event) {
            var target = event.target;
            var roleId;
            if (target.matches('[data-assign-role-permission]')) {
                roleId = target.getAttribute('data-assign-role-permission');
                var select = qs('[data-role-permission-select="' + roleId + '"]', root);
                if (!select || !select.value) {
                    showMessage(root, 'warning', 'Selecciona un permiso para asignar.');
                    return;
                }
                window.CCApi.request('/admin/roles/' + roleId + '/permissions', { method: 'POST', body: { permission_id: select.value } })
                    .then(function () { return loadCatalogs().then(loadRoles); })
                    .catch(function (error) { handleError(root, error); });
            }
            if (target.matches('[data-remove-role-permission]')) {
                window.CCApi.request('/admin/roles/' + target.dataset.roleId + '/permissions/' + target.dataset.permissionId, { method: 'DELETE' })
                    .then(function () { return loadCatalogs().then(loadRoles); })
                    .catch(function (error) { handleError(root, error); });
            }
            if (target.matches('[data-delete-role]')) {
                window.CCApi.request('/admin/roles/' + target.getAttribute('data-delete-role'), { method: 'DELETE' })
                    .then(function () { return loadCatalogs().then(loadRoles); })
                    .catch(function (error) { handleError(root, error); });
            }
            if (target.matches('[data-restore-role]')) {
                window.CCApi.request('/admin/roles/' + target.getAttribute('data-restore-role') + '/restore', { method: 'PATCH' })
                    .then(function () { return loadCatalogs().then(loadRoles); })
                    .catch(function (error) { handleError(root, error); });
            }
        });
    }

    function debounce(fn, wait) {
        var timeout;
        return function () {
            clearTimeout(timeout);
            timeout = setTimeout(fn, wait);
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        var usersRoot = qs('[data-rbac-users]');
        var rolesRoot = qs('[data-rbac-roles]');

        if (!usersRoot && !rolesRoot) {
            return;
        }

        loadCatalogs().then(function () {
            if (usersRoot) {
                bindUserPanel(usersRoot);
                loadUsers();
            }
            if (rolesRoot) {
                bindRolePanel(rolesRoot);
                loadRoles();
            }
        }).catch(function (error) {
            if (usersRoot) {
                handleError(usersRoot, error);
            }
            if (rolesRoot) {
                handleError(rolesRoot, error);
            }
        });
    });
})(window, document);
