(function (window, document) {
    'use strict';

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

    function showMessage(root, type, message) {
        var alert = qs('[data-professional-panel-message]', root);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString('es-AR');
    }

    function renderUsers(root, links) {
        var body = qs('[data-professional-users-body]', root);
        if (!body) {
            return;
        }

        if (!links.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">Todavia no hay usuarios vinculados.</td></tr>';
            return;
        }

        body.innerHTML = links.map(function (link) {
            return '<tr>' +
                '<td><strong>' + escapeHtml((link.user && (link.user.name + ' ' + (link.user.lastname || ''))) || '-') + '</strong><br><span class="muted">' + escapeHtml(link.user ? link.user.email : '-') + '</span></td>' +
                '<td>' + escapeHtml(link.can_view_profile ? 'Si' : 'No') + '</td>' +
                '<td>' + escapeHtml(link.can_view_meal_plans ? 'Si' : 'No') + '</td>' +
                '<td>' + escapeHtml(link.can_edit_meal_plans ? 'Si' : 'No') + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-linked-user-select="' + link.user_id + '">Abrir</button></td>' +
            '</tr>';
        }).join('');
    }

    function renderProfile(root, profile) {
        var card = qs('[data-professional-profile]', root);
        if (!card) {
            return;
        }

        card.innerHTML = '' +
            '<div class="table-line"><span class="muted">Usuario</span><strong>' + escapeHtml((profile.name || '-') + ' ' + (profile.lastname || '')) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Email</span><strong>' + escapeHtml(profile.email) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Telefono</span><strong>' + escapeHtml(profile.phone) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Nacimiento</span><strong>' + escapeHtml(profile.birth_date) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Altura</span><strong>' + escapeHtml(profile.height_cm) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Peso actual</span><strong>' + escapeHtml(profile.current_weight_kg) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Peso objetivo</span><strong>' + escapeHtml(profile.target_weight_kg) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Actividad</span><strong>' + escapeHtml(profile.activity_level) + '</strong></div>' +
            '<div class="table-line"><span class="muted">Notas</span><strong>' + escapeHtml(profile.notes) + '</strong></div>';
    }

    function renderPlans(root, plans) {
        var body = qs('[data-professional-plans-body]', root);
        if (!body) {
            return;
        }

        if (!plans.length) {
            body.innerHTML = '<tr><td colspan="6" class="muted">No hay planes de comida para este usuario.</td></tr>';
            return;
        }

        body.innerHTML = plans.map(function (plan) {
            return '<tr>' +
                '<td>' + escapeHtml(plan.period_type) + '</td>' +
                '<td>' + escapeHtml(plan.start_date) + '</td>' +
                '<td>' + escapeHtml(plan.end_date) + '</td>' +
                '<td>' + escapeHtml(plan.mode) + '</td>' +
                '<td>' + escapeHtml(plan.status) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-plan-edit="' + plan.id + '">Editar</button></td>' +
            '</tr>';
        }).join('');
    }

    function fillPlanForm(root, plan) {
        var form = qs('[data-professional-plan-form]', root);
        if (!form) {
            return;
        }

        form.dataset.planId = plan ? plan.id : '';
        qs('[name="status"]', form).value = plan ? text(plan.status) : '';
        qs('[name="period_type"]', form).value = plan ? text(plan.period_type) : '';
        qs('[name="start_date"]', form).value = plan ? text(plan.start_date) : '';
        qs('[name="end_date"]', form).value = plan ? text(plan.end_date) : '';
        qs('[name="mode"]', form).value = plan ? text(plan.mode) : '';
        qs('[name="approved_at"]', form).value = plan && plan.approved_at ? plan.approved_at.slice(0, 16) : '';
        qs('[name="config_json"]', form).value = plan && plan.config_json ? JSON.stringify(plan.config_json, null, 2) : '';
    }

    function loadLinkedUsers(root) {
        return window.CCApi.request('/professional/linked-users').then(function (response) {
            var links = response.data || [];
            root.__linkedUsers = links;
            renderUsers(root, links);
        });
    }

    function loadProfile(root, userId) {
        return window.CCApi.request('/professional/users/' + userId + '/profile').then(function (response) {
            renderProfile(root, response.data || {});
        });
    }

    function loadPlans(root, userId) {
        return window.CCApi.request('/professional/users/' + userId + '/meal-plans').then(function (response) {
            var plans = response.data || [];
            root.__mealPlans = plans;
            renderPlans(root, plans);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-professional-panel]');
        if (!root || !window.CCApi) {
            return;
        }

        var form = qs('[data-professional-plan-form]', root);
        var reset = qs('[data-professional-plan-reset]', root);

        loadLinkedUsers(root).catch(function (error) {
            showMessage(root, 'warning', error.message || 'No se pudieron cargar los usuarios vinculados.');
        });

        root.addEventListener('click', function (event) {
            var userId = event.target.getAttribute('data-linked-user-select');
            var planId = event.target.getAttribute('data-plan-edit');

            if (userId) {
                root.dataset.currentUserId = userId;
                showMessage(root, 'info', 'Cargando perfil y planes del usuario seleccionado...');
                Promise.all([
                    loadProfile(root, userId),
                    loadPlans(root, userId)
                ]).then(function () {
                    fillPlanForm(root, null);
                    showMessage(root, 'success', 'Informacion del usuario cargada.');
                }).catch(function (error) {
                    showMessage(root, 'danger', error.message || 'No se pudo cargar el usuario vinculado.');
                });
            }

            if (planId) {
                var plan = (root.__mealPlans || []).find(function (item) {
                    return String(item.id) === String(planId);
                });

                if (!plan) {
                    return;
                }

                fillPlanForm(root, plan);
                showMessage(root, 'info', 'Editando plan de comida seleccionado.');
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var userId = root.dataset.currentUserId;
            var planId = form.dataset.planId;
            if (!userId || !planId) {
                showMessage(root, 'warning', 'Primero selecciona un usuario y un plan.');
                return;
            }

            var payload = {
                status: qs('[name="status"]', form).value || null,
                period_type: qs('[name="period_type"]', form).value || null,
                start_date: qs('[name="start_date"]', form).value || null,
                end_date: qs('[name="end_date"]', form).value || null,
                mode: qs('[name="mode"]', form).value || null,
                approved_at: qs('[name="approved_at"]', form).value || null,
                config_json: null
            };

            var configRaw = qs('[name="config_json"]', form).value.trim();
            if (configRaw) {
                try {
                    payload.config_json = JSON.parse(configRaw);
                } catch (error) {
                    showMessage(root, 'danger', 'El JSON de configuracion no es valido.');
                    return;
                }
            }

            window.CCApi.request('/professional/users/' + userId + '/meal-plans/' + planId, {
                method: 'PATCH',
                body: payload
            }).then(function () {
                showMessage(root, 'success', 'Plan de comida actualizado correctamente.');
                return loadPlans(root, userId);
            }).catch(function (error) {
                showMessage(root, 'danger', error.message || 'No se pudo actualizar el plan.');
            });
        });

        reset.addEventListener('click', function () {
            fillPlanForm(root, null);
        });
    });
})(window, document);
