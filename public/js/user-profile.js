(function (window, document) {
    'use strict';

    var state = {
        objectivesCatalog: [],
        userObjectives: [],
        consents: {},
        healthCatalogs: {
            'dietary-restrictions': [],
            'health-conditions': [],
            'allergies': [],
        },
        userHealthSelections: {
            'dietary-restrictions': [],
            'health-conditions': [],
            'allergies': [],
        },
        currentHealthType: 'dietary-restrictions',
        measurements: [],
        editingMeasurementId: null,
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

    function formatDateTime(value) {
        if (!value) {
            return '-';
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('es-AR');
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
                renderObjectiveCatalog();
            });
    }

    function loadUserObjectives(form) {
        return window.CCApi.request('/users/me/objectives')
            .then(function (response) {
                state.userObjectives = response.data || [];
                renderUserObjectives(form, state.userObjectives);
                updateObjectivesSummary();
            });
    }

    function loadHealthCatalog(type) {
        return window.CCApi.request('/api/v1/catalog/' + type)
            .then(function (response) {
                state.healthCatalogs[type] = response.data || [];
            });
    }

    function loadUserHealth(type, form) {
        return window.CCApi.request('/api/v1/users/me/' + type)
            .then(function (response) {
                state.userHealthSelections[type] = response.data || [];
                if (type === state.currentHealthType) {
                    renderUserHealth(form);
                }
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

    function loadMeasurements(form) {
        return window.CCApi.request('/users/me/body-measurements?per_page=20')
            .then(function (response) {
                state.measurements = response.data || [];
                renderMeasurements(form, state.measurements);
            });
    }

    function loadConsents(form) {
        return window.CCApi.request('/users/me/consents')
            .then(function (response) {
                var data = response.data || {};
                state.consents = data.consents || {};
                fillConsents(form, state.consents);
                renderConsents(state.consents);
            });
    }

    function fillProfile(form, profile) {
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

    function fillConsents(form, consents) {
        Object.keys(consentMeta()).forEach(function (key) {
            var consent = consents[key] || {};
            setCheckbox(form, key, consent.accepted);
        });
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

    function renderObjectiveCatalog() {
        var select = qs('[name="objective_id"]', document);
        if (!select) {
            return;
        }

        if (!state.objectivesCatalog.length) {
            select.innerHTML = '<option value="">No hay objetivos disponibles</option>';
            return;
        }

        var current = select.value;
        select.innerHTML = '<option value="">Seleccionar objetivo</option>' + state.objectivesCatalog.map(function (objective) {
            return '<option value="' + objective.id + '">' + escapeHtml(objective.name) + '</option>';
        }).join('');
        select.value = current;
    }

    function renderUserObjectives(form, items) {
        var body = qs('[data-user-objectives-body]');
        if (!body) {
            return;
        }

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="5" class="muted">Todavia no hay objetivos asignados.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            var target = item.target_value !== null
                ? text(item.target_value) + (item.target_unit ? ' ' + item.target_unit : '')
                : '-';
            return '<tr>' +
                '<td><strong>' + escapeHtml(item.objective ? item.objective.name : '-') + '</strong><br><span class="muted">' + escapeHtml(item.objective ? item.objective.code : '-') + '</span></td>' +
                '<td>' + escapeHtml(text(item.priority)) + '</td>' +
                '<td>' + escapeHtml(target) + '</td>' +
                '<td>' + escapeHtml(text(item.target_date)) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-user-objective-edit="' + item.id + '">Editar</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-user-objective-delete="' + item.id + '">Eliminar</button></td>' +
                '</tr>';
        }).join('');
    }

    function renderUserHealth(form) {
        var select = qs('[name="item_id"]', form);
        var body = qs('[data-user-health-body]');
        var severityWrap = qs('[data-user-health-severity-wrap]', form);
        var type = state.currentHealthType;
        var catalog = state.healthCatalogs[type] || [];
        var selected = state.userHealthSelections[type] || [];

        severityWrap.style.display = type === 'allergies' ? '' : 'none';

        if (!catalog.length) {
            select.innerHTML = '<option value="">No hay items disponibles</option>';
        } else {
            select.innerHTML = '<option value="">Seleccionar item</option>' + catalog.map(function (item) {
                return '<option value="' + item.id + '">' + escapeHtml(item.name) + '</option>';
            }).join('');
        }

        if (!selected.length) {
            body.innerHTML = '<tr><td colspan="3" class="muted">Todavia no hay selecciones cargadas.</td></tr>';
            return;
        }

        body.innerHTML = selected.map(function (row) {
            var detail = [];
            if (row.severity) {
                detail.push('Severidad: ' + row.severity);
            }
            if (row.notes) {
                detail.push(row.notes);
            }
            return '<tr>' +
                '<td><strong>' + escapeHtml(row.item ? row.item.name : '-') + '</strong><br><span class="muted">' + escapeHtml(row.item ? row.item.code : '-') + '</span></td>' +
                '<td>' + escapeHtml(detail.join(' · ') || '-') + '</td>' +
                '<td><button type="button" class="btn-secondary-web btn-sm" data-user-health-delete="' + row.id + '">Eliminar</button></td>' +
                '</tr>';
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
            preferences: {
                uses_app_for_health: checked(form, 'preferences.uses_app_for_health'),
                uses_app_for_budget: checked(form, 'preferences.uses_app_for_budget'),
                uses_app_for_organization: checked(form, 'preferences.uses_app_for_organization'),
            },
        };
    }

    function userObjectivePayload(form) {
        return {
            objective_id: parseNumber(valueOf(form, 'objective_id')),
            priority: parseNumber(valueOf(form, 'priority')),
            target_value: parseNumber(valueOf(form, 'target_value')),
            target_unit: normalizeString(valueOf(form, 'target_unit')),
            target_date: normalizeString(valueOf(form, 'target_date')),
            notes: normalizeString(valueOf(form, 'notes')),
        };
    }

    function userHealthPayload(form) {
        var payload = {
            notes: normalizeString(valueOf(form, 'notes')),
        };

        if (state.currentHealthType === 'dietary-restrictions') {
            payload.dietary_restriction_id = parseNumber(valueOf(form, 'item_id'));
        } else if (state.currentHealthType === 'health-conditions') {
            payload.health_condition_id = parseNumber(valueOf(form, 'item_id'));
        } else {
            payload.allergy_id = parseNumber(valueOf(form, 'item_id'));
            payload.severity = normalizeString(valueOf(form, 'severity'));
        }

        return payload;
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

    function measurementPayload(form) {
        return {
            measured_at: normalizeString(valueOf(form, 'measured_at')),
            weight_kg: parseNumber(valueOf(form, 'weight_kg')),
            waist_cm: parseNumber(valueOf(form, 'waist_cm')),
            blood_pressure_systolic: parseNumber(valueOf(form, 'blood_pressure_systolic')),
            blood_pressure_diastolic: parseNumber(valueOf(form, 'blood_pressure_diastolic')),
            glucose_level: parseNumber(valueOf(form, 'glucose_level')),
            notes: normalizeString(valueOf(form, 'notes')),
        };
    }

    function consentsPayload(form) {
        return {
            health_data_consent: checked(form, 'health_data_consent'),
            privacy_consent: checked(form, 'privacy_consent'),
            professional_access_consent: checked(form, 'professional_access_consent'),
            medical_disclaimer_accepted: checked(form, 'medical_disclaimer_accepted'),
            terms_accepted: checked(form, 'terms_accepted'),
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
        var preferencesCount = qsa('[data-user-profile-form] input[type="checkbox"]:checked').length;
        var preferencesNode = qs('[data-profile-preferences-count]');

        if (preferencesNode) {
            preferencesNode.textContent = String(preferencesCount);
        }
    }

    function updateObjectivesSummary() {
        var objectivesNode = qs('[data-profile-objectives-count]');
        if (objectivesNode) {
            objectivesNode.textContent = String(state.userObjectives.length);
        }
    }

    function updatePrioritySummary(mode) {
        var node = qs('[data-priority-mode-summary]');
        if (node) {
            node.textContent = text(mode);
        }
    }

    function renderMeasurements(form, items) {
        var body = qs('[data-measurements-body]');
        if (!body) {
            return;
        }

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="7" class="muted">Todavia no hay mediciones registradas.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (item) {
            var pressure = item.blood_pressure_systolic && item.blood_pressure_diastolic
                ? item.blood_pressure_systolic + '/' + item.blood_pressure_diastolic
                : '-';
            return '<tr>' +
                '<td>' + escapeHtml(item.measured_at) + '</td>' +
                '<td>' + escapeHtml(text(item.weight_kg)) + '</td>' +
                '<td>' + escapeHtml(text(item.waist_cm)) + '</td>' +
                '<td>' + escapeHtml(pressure) + '</td>' +
                '<td>' + escapeHtml(text(item.glucose_level)) + '</td>' +
                '<td>' + escapeHtml(text(item.notes)) + '</td>' +
                '<td><button type="button" class="btn-main btn-sm" data-measurement-edit="' + item.id + '">Editar</button> ' +
                '<button type="button" class="btn-secondary-web btn-sm" data-measurement-delete="' + item.id + '">Eliminar</button></td>' +
                '</tr>';
        }).join('');
    }

    function renderConsents(consents) {
        var body = qs('[data-consents-body]');
        var meta = consentMeta();
        if (!body) {
            return;
        }

        body.innerHTML = Object.keys(meta).map(function (key) {
            var consent = consents[key] || {};
            return '<tr>' +
                '<td><strong>' + escapeHtml(meta[key].title) + '</strong><br><span class="muted">' + escapeHtml(meta[key].description) + '</span></td>' +
                '<td>' + escapeHtml(consent.accepted ? 'Aceptado' : 'Pendiente o revocado') + '</td>' +
                '<td>' + escapeHtml(formatDateTime(consent.accepted_at)) + '</td>' +
                '<td>' + escapeHtml(formatDateTime(consent.revoked_at)) + '</td>' +
                '</tr>';
        }).join('');
    }

    function consentMeta() {
        return {
            health_data_consent: {
                title: 'Uso de datos de salud',
                description: 'Tratamiento de datos sensibles para perfil, mediciones y configuracion alimentaria.',
            },
            privacy_consent: {
                title: 'Politica de privacidad',
                description: 'Aceptacion de politicas de tratamiento y resguardo de informacion personal.',
            },
            professional_access_consent: {
                title: 'Acceso profesional',
                description: 'Permite compartir informacion con profesionales autorizados por el usuario.',
            },
            medical_disclaimer_accepted: {
                title: 'Aviso profesional',
                description: 'Confirma que la app no reemplaza seguimiento medico o nutricional.',
            },
            terms_accepted: {
                title: 'Terminos de uso',
                description: 'Aceptacion de las condiciones generales de uso de la plataforma.',
            },
        };
    }

    function fillMeasurementForm(form, item) {
        setValue(form, 'measured_at', item && item.measured_at);
        setValue(form, 'weight_kg', item && item.weight_kg);
        setValue(form, 'waist_cm', item && item.waist_cm);
        setValue(form, 'blood_pressure_systolic', item && item.blood_pressure_systolic);
        setValue(form, 'blood_pressure_diastolic', item && item.blood_pressure_diastolic);
        setValue(form, 'glucose_level', item && item.glucose_level);
        setValue(form, 'notes', item && item.notes);
    }

    function resetMeasurementForm(form) {
        form.reset();
        state.editingMeasurementId = null;
        var submit = qs('[type="submit"]', form);
        if (submit) {
            submit.textContent = submit.dataset.originalText || 'Guardar medicion';
        }
    }

    function bindProfileForm(form) {
        form.addEventListener('change', function (event) {
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

    function bindUserObjectives(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFeedback(form, '[data-user-objective-error]', '[data-user-objective-message]');
            setLoading(form, true);

            var assignmentId = valueOf(form, 'assignment_id');
            var endpoint = '/users/me/objectives' + (assignmentId ? '/' + assignmentId : '');
            var method = assignmentId ? 'PATCH' : 'POST';

            window.CCApi.request(endpoint, {
                method: method,
                body: userObjectivePayload(form),
            }).then(function () {
                showMessage(form, '[data-user-objective-message]', 'success', assignmentId ? 'Objetivo actualizado correctamente.' : 'Objetivo agregado correctamente.');
                resetUserObjectiveForm(form);
                return loadUserObjectives(form);
            }).catch(function (error) {
                showErrors(form, error, '[data-user-objective-error', '[data-user-objective-message]');
            }).finally(function () {
                setLoading(form, false);
            });
        });

        var reset = qs('[data-user-objective-reset]', form);
        if (reset) {
            reset.addEventListener('click', function () {
                resetUserObjectiveForm(form);
                clearFeedback(form, '[data-user-objective-error]', '[data-user-objective-message]');
            });
        }

        document.addEventListener('click', function (event) {
            var editId = event.target.getAttribute('data-user-objective-edit');
            var deleteId = event.target.getAttribute('data-user-objective-delete');

            if (editId) {
                var assignment = state.userObjectives.find(function (item) {
                    return String(item.id) === String(editId);
                });
                if (!assignment) {
                    return;
                }

                setValue(form, 'assignment_id', assignment.id);
                setValue(form, 'objective_id', assignment.objective ? assignment.objective.id : '');
                setValue(form, 'priority', assignment.priority);
                setValue(form, 'target_value', assignment.target_value);
                setValue(form, 'target_unit', assignment.target_unit);
                setValue(form, 'target_date', assignment.target_date);
                setValue(form, 'notes', assignment.notes);
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            if (deleteId) {
                window.CCApi.request('/users/me/objectives/' + deleteId, { method: 'DELETE' })
                    .then(function () {
                        showMessage(form, '[data-user-objective-message]', 'success', 'Objetivo eliminado correctamente.');
                        resetUserObjectiveForm(form);
                        return loadUserObjectives(form);
                    })
                    .catch(function (error) {
                        showErrors(form, error, '[data-user-objective-error', '[data-user-objective-message]');
                    });
            }
        });
    }

    function bindUserHealth(form) {
        qsa('[data-user-health-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                state.currentHealthType = button.getAttribute('data-user-health-tab');
                qsa('[data-user-health-tab]').forEach(function (tab) {
                    tab.classList.toggle('active', tab === button);
                });
                form.reset();
                renderUserHealth(form);
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFeedback(form, '[data-user-health-error]', '[data-user-health-message]');
            setLoading(form, true);

            window.CCApi.request('/api/v1/users/me/' + state.currentHealthType, {
                method: 'POST',
                body: userHealthPayload(form),
            }).then(function () {
                showMessage(form, '[data-user-health-message]', 'success', 'Seleccion guardada correctamente.');
                form.reset();
                return loadUserHealth(state.currentHealthType, form);
            }).catch(function (error) {
                showErrors(form, error, '[data-user-health-error', '[data-user-health-message]');
            }).finally(function () {
                setLoading(form, false);
            });
        });

        var reset = qs('[data-user-health-reset]', form);
        if (reset) {
            reset.addEventListener('click', function () {
                form.reset();
                clearFeedback(form, '[data-user-health-error]', '[data-user-health-message]');
                renderUserHealth(form);
            });
        }

        document.addEventListener('click', function (event) {
            var id = event.target.getAttribute('data-user-health-delete');
            if (!id) {
                return;
            }

            window.CCApi.request('/api/v1/users/me/' + state.currentHealthType + '/' + id, {
                method: 'DELETE',
            }).then(function () {
                showMessage(form, '[data-user-health-message]', 'success', 'Seleccion eliminada correctamente.');
                return loadUserHealth(state.currentHealthType, form);
            }).catch(function (error) {
                showErrors(form, error, '[data-user-health-error', '[data-user-health-message]');
            });
        });
    }

    function resetUserObjectiveForm(form) {
        form.reset();
        setValue(form, 'assignment_id', '');
        renderObjectiveCatalog();
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

    function bindMeasurementForm(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFeedback(form, '[data-measurement-error]', '[data-measurement-message]');
            setLoading(form, true);

            var method = state.editingMeasurementId ? 'PATCH' : 'POST';
            var endpoint = '/users/me/body-measurements' + (state.editingMeasurementId ? '/' + state.editingMeasurementId : '');

            window.CCApi.request(endpoint, {
                method: method,
                body: measurementPayload(form),
            }).then(function () {
                showMessage(form, '[data-measurement-message]', 'success', state.editingMeasurementId ? 'Medicion actualizada correctamente.' : 'Medicion registrada correctamente.');
                resetMeasurementForm(form);
                return loadMeasurements(form);
            }).catch(function (error) {
                showErrors(form, error, '[data-measurement-error', '[data-measurement-message]');
            }).finally(function () {
                setLoading(form, false);
            });
        });

        var resetButton = qs('[data-measurement-reset]', form);
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                clearFeedback(form, '[data-measurement-error]', '[data-measurement-message]');
                resetMeasurementForm(form);
            });
        }

        document.addEventListener('click', function (event) {
            var editId = event.target.getAttribute('data-measurement-edit');
            var deleteId = event.target.getAttribute('data-measurement-delete');

            if (editId) {
                var item = state.measurements.find(function (measurement) {
                    return String(measurement.id) === String(editId);
                });
                if (!item) {
                    return;
                }

                state.editingMeasurementId = item.id;
                fillMeasurementForm(form, item);
                clearFeedback(form, '[data-measurement-error]', '[data-measurement-message]');
                var submit = qs('[type="submit"]', form);
                if (submit) {
                    submit.textContent = 'Actualizar medicion';
                }
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            if (deleteId) {
                window.CCApi.request('/users/me/body-measurements/' + deleteId, {
                    method: 'DELETE',
                }).then(function () {
                    showMessage(form, '[data-measurement-message]', 'success', 'Medicion eliminada correctamente.');
                    if (state.editingMeasurementId && String(state.editingMeasurementId) === String(deleteId)) {
                        resetMeasurementForm(form);
                    }
                    return loadMeasurements(form);
                }).catch(function (error) {
                    showErrors(form, error, '[data-measurement-error', '[data-measurement-message]');
                });
            }
        });
    }

    function bindConsentsForm(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFeedback(form, '[data-consents-error]', '[data-consents-message]');
            setLoading(form, true);

            window.CCApi.request('/users/me/consents', {
                method: 'PATCH',
                body: consentsPayload(form),
            }).then(function (payload) {
                var data = payload.data || {};
                state.consents = data.consents || {};
                fillConsents(form, state.consents);
                renderConsents(state.consents);
                showMessage(form, '[data-consents-message]', 'success', 'Consentimientos actualizados correctamente.');
            }).catch(function (error) {
                showErrors(form, error, '[data-consents-error', '[data-consents-message]');
            }).finally(function () {
                setLoading(form, false);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var profileForm = qs('[data-user-profile-form]');
        var priorityForm = qs('[data-priority-settings-form]');
        var objectivesForm = qs('[data-user-objective-form]');
        var userHealthForm = qs('[data-user-health-form]');
        var measurementForm = qs('[data-body-measurement-form]');
        var consentsForm = qs('[data-consents-form]');

        if (!profileForm || !priorityForm || !objectivesForm || !userHealthForm || !measurementForm || !consentsForm || !window.CCApi) {
            return;
        }

        bindProfileForm(profileForm);
        bindPriorityForm(priorityForm);
        bindUserObjectives(objectivesForm);
        bindUserHealth(userHealthForm);
        bindMeasurementForm(measurementForm);
        bindConsentsForm(consentsForm);

        Promise.all([
            loadObjectivesCatalog(),
            loadHealthCatalog('dietary-restrictions'),
            loadHealthCatalog('health-conditions'),
            loadHealthCatalog('allergies'),
            loadProfile(profileForm),
            loadPrioritySettings(priorityForm),
            loadUserObjectives(objectivesForm),
            loadUserHealth('dietary-restrictions', userHealthForm),
            loadUserHealth('health-conditions', userHealthForm),
            loadUserHealth('allergies', userHealthForm),
            loadMeasurements(measurementForm),
            loadConsents(consentsForm),
        ]).then(function () {
            renderUserHealth(userHealthForm);
        }).catch(function () {
            showMessage(profileForm, '[data-profile-message]', 'warning', 'No se pudo cargar toda la informacion del perfil desde la API.');
        });
    });
})(window, document);
