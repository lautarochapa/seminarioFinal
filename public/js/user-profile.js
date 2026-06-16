(function (window, document) {
    'use strict';

    var state = {
        objectivesCatalog: [],
        selectedObjectiveIds: [],
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

    function showMessage(form, hook, type, message) {
        var alert = qs(hook, form);
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function clearFeedback(form, errorSelector, alertSelector) {
        qsa('.is-invalid', form).forEach(function (input) {
            input.classList.remove('is-invalid');
        });
        qsa(errorSelector, form).forEach(function (node) {
            node.textContent = '';
            node.style.display = 'none';
        });
        var alert = qs(alertSelector, form);
        if (alert) {
            alert.textContent = '';
            alert.className = 'alert';
            alert.style.display = 'none';
        }
    }

    function showErrors(form, error, errorPrefix, alertSelector) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        var fieldErrors = apiError.field_errors || {};
        var hasFieldErrors = false;

        Object.keys(fieldErrors).forEach(function (field) {
            hasFieldErrors = true;
            var input = form.querySelector('[name="' + field + '"]');
            var holder = form.querySelector(errorPrefix + '="' + field + '"]');

            if (input) {
                input.classList.add('is-invalid');
            }
            if (holder) {
                holder.textContent = fieldErrors[field].join(' ');
                holder.style.display = 'block';
            }
        });

        if (!hasFieldErrors || apiError.message) {
            showMessage(form, alertSelector, 'danger', apiError.message || error.message || 'No se pudo guardar.');
        }
    }

    function setLoading(form, loading) {
        var submit = qs('[type="submit"]', form);
        if (!submit) {
            return;
        }

        if (!submit.dataset.originalText) {
            submit.dataset.originalText = submit.textContent;
        }

        submit.disabled = loading;
        submit.textContent = loading ? 'Guardando...' : submit.dataset.originalText;
    }

    function parseNumber(value) {
        return value === '' ? null : Number(value);
    }

    function normalizeString(value) {
        return value === '' ? null : value;
    }

    function loadObjectivesCatalog() {
        return window.CCApi.request('/catalog/objectives')
            .then(function (response) {
                state.objectivesCatalog = response.data || [];
            });
    }

    function loadProfile(form) {
        return window.CCApi.request('/users/me/profile')
            .then(function (response) {
                fillProfile(form, response.data || {});
            });
    }

    function loadPrioritySettings(form) {
        return window.CCApi.request('/users/me/priority-settings')
            .then(function (response) {
                fillPrioritySettings(form, response.data || {});
            });
    }

    function fillProfile(form, profile) {
        state.selectedObjectiveIds = (profile.objectives || []).map(function (objective) {
            return Number(objective.id);
        });

        setValue(form, 'name', profile.name);
        setValue(form, 'lastname', profile.lastname);
        setValue(form, 'email', profile.email);
        setValue(form, 'phone', profile.phone);
        setValue(form, 'birth_date', profile.birth_date);
        setValue(form, 'gender', profile.gender);
        setValue(form, 'height_cm', profile.height_cm);
        setValue(form, 'current_weight_kg', profile.current_weight_kg);
        setValue(form, 'target_weight_kg', profile.target_weight_kg);
        setValue(form, 'activity_level', profile.activity_level);
        setValue(form, 'meals_per_day', profile.meals_per_day);
        setValue(form, 'notes', profile.notes);

        setCheckbox(form, 'preferences.uses_app_for_health', profile.preferences && profile.preferences.uses_app_for_health);
        setCheckbox(form, 'preferences.uses_app_for_budget', profile.preferences && profile.preferences.uses_app_for_budget);
        setCheckbox(form, 'preferences.uses_app_for_organization', profile.preferences && profile.preferences.uses_app_for_organization);

        renderObjectives(form);
        updateProfileSummary();
    }

    function fillPrioritySettings(form, settings) {
        setValue(form, 'health_weight', settings.health_weight);
        setValue(form, 'budget_weight', settings.budget_weight);
        setValue(form, 'time_weight', settings.time_weight);
        setValue(form, 'stock_usage_weight', settings.stock_usage_weight);
        setValue(form, 'preferred_mode', settings.preferred_mode);
        updatePrioritySummary(settings.preferred_mode);
    }

    function setValue(form, name, value) {
        var input = qs('[name="' + name + '"]', form);
        if (input) {
            input.value = value === null || value === undefined ? '' : value;
        }
    }

    function setCheckbox(form, name, checked) {
        var input = qs('[name="' + name + '"]', form);
        if (input) {
            input.checked = !!checked;
        }
    }

    function renderObjectives(form) {
        var container = qs('[data-objectives-list]', form);
        if (!container) {
            return;
        }

        if (!state.objectivesCatalog.length) {
            container.innerHTML = '<div class="muted">No hay objetivos disponibles.</div>';
            return;
        }

        container.innerHTML = state.objectivesCatalog.map(function (objective) {
            var checked = state.selectedObjectiveIds.indexOf(Number(objective.id)) !== -1 ? ' checked' : '';
            return '<label class="objective-item">' +
                '<input type="checkbox" value="' + objective.id + '" data-objective-checkbox' + checked + '>' +
                '<span><strong>' + escapeHtml(objective.name) + '</strong><br><span class="muted">' + escapeHtml(objective.code || '') + '</span></span>' +
                '</label>';
        }).join('');
    }

    function escapeHtml(value) {
        return text(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function profilePayload(form) {
        return {
            name: normalizeString(valueOf(form, 'name')),
            lastname: normalizeString(valueOf(form, 'lastname')),
            phone: normalizeString(valueOf(form, 'phone')),
            birth_date: normalizeString(valueOf(form, 'birth_date')),
            gender: normalizeString(valueOf(form, 'gender')),
            height_cm: parseNumber(valueOf(form, 'height_cm')),
            current_weight_kg: parseNumber(valueOf(form, 'current_weight_kg')),
            target_weight_kg: parseNumber(valueOf(form, 'target_weight_kg')),
            activity_level: normalizeString(valueOf(form, 'activity_level')),
            meals_per_day: parseNumber(valueOf(form, 'meals_per_day')),
            notes: normalizeString(valueOf(form, 'notes')),
            objective_ids: qsa('[data-objective-checkbox]:checked', form).map(function (input) {
                return Number(input.value);
            }),
            preferences: {
                uses_app_for_health: checked(form, 'preferences.uses_app_for_health'),
                uses_app_for_budget: checked(form, 'preferences.uses_app_for_budget'),
                uses_app_for_organization: checked(form, 'preferences.uses_app_for_organization'),
            },
        };
    }

    function priorityPayload(form) {
        return {
            health_weight: parseNumber(valueOf(form, 'health_weight')),
            budget_weight: parseNumber(valueOf(form, 'budget_weight')),
            time_weight: parseNumber(valueOf(form, 'time_weight')),
            stock_usage_weight: parseNumber(valueOf(form, 'stock_usage_weight')),
            preferred_mode: normalizeString(valueOf(form, 'preferred_mode')),
        };
    }

    function valueOf(form, name) {
        var input = qs('[name="' + name + '"]', form);
        return input ? input.value : '';
    }

    function checked(form, name) {
        var input = qs('[name="' + name + '"]', form);
        return !!(input && input.checked);
    }

    function updateProfileSummary() {
        var objectivesCount = state.selectedObjectiveIds.length;
        var preferencesCount = qsa('[data-user-profile-form] input[type="checkbox"]:checked').length;
        var objectivesNode = qs('[data-profile-objectives-count]');
        var preferencesNode = qs('[data-profile-preferences-count]');

        if (objectivesNode) {
            objectivesNode.textContent = String(objectivesCount);
        }
        if (preferencesNode) {
            preferencesNode.textContent = String(preferencesCount);
        }
    }

    function updatePrioritySummary(mode) {
        var node = qs('[data-priority-mode-summary]');
        if (node) {
            node.textContent = text(mode);
        }
    }

    function bindProfileForm(form) {
        form.addEventListener('change', function (event) {
            if (event.target.matches('[data-objective-checkbox]')) {
                state.selectedObjectiveIds = qsa('[data-objective-checkbox]:checked', form).map(function (input) {
                    return Number(input.value);
                });
                updateProfileSummary();
            }

            if (event.target.matches('input[type="checkbox"]')) {
                updateProfileSummary();
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFeedback(form, '[data-profile-error]', '[data-profile-message]');
            setLoading(form, true);

            window.CCApi.request('/users/me/profile', {
                method: 'PATCH',
                body: profilePayload(form),
            }).then(function (payload) {
                fillProfile(form, payload.data || {});
                showMessage(form, '[data-profile-message]', 'success', 'Perfil actualizado correctamente.');
            }).catch(function (error) {
                showErrors(form, error, '[data-profile-error', '[data-profile-message]');
            }).finally(function () {
                setLoading(form, false);
            });
        });
    }

    function bindPriorityForm(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFeedback(form, '[data-priority-error]', '[data-priority-message]');
            setLoading(form, true);

            window.CCApi.request('/users/me/priority-settings', {
                method: 'PATCH',
                body: priorityPayload(form),
            }).then(function (payload) {
                fillPrioritySettings(form, payload.data || {});
                showMessage(form, '[data-priority-message]', 'success', 'Prioridades actualizadas correctamente.');
            }).catch(function (error) {
                showErrors(form, error, '[data-priority-error', '[data-priority-message]');
            }).finally(function () {
                setLoading(form, false);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var profileForm = qs('[data-user-profile-form]');
        var priorityForm = qs('[data-priority-settings-form]');

        if (!profileForm || !priorityForm || !window.CCApi) {
            return;
        }

        bindProfileForm(profileForm);
        bindPriorityForm(priorityForm);

        Promise.all([
            loadObjectivesCatalog(),
            loadProfile(profileForm),
            loadPrioritySettings(priorityForm),
        ]).catch(function () {
            showMessage(profileForm, '[data-profile-message]', 'warning', 'No se pudo cargar toda la informacion del perfil desde la API.');
        });
    });
})(window, document);
