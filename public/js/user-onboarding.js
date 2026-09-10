(function (window, document) {
    'use strict';

    var STEP_META = {
        basic_profile: {
            title: '1. Datos basicos',
            hint: 'Altura, peso y peso objetivo (opcional).',
            link: '/web/profile-objectives',
            cta: 'Completar datos',
        },
        objective: {
            title: '2. Objetivo',
            hint: 'Elegi al menos un objetivo nutricional.',
            link: '/web/profile-objectives',
            cta: 'Elegir objetivo',
        },
        meals_per_day: {
            title: '3. Comidas por dia',
            hint: 'Cuantas comidas haces por dia.',
            link: '/web/profile-objectives',
            cta: 'Definir comidas',
        },
        food_preferences: {
            title: 'Preferencias alimentarias (opcional)',
            hint: 'Restricciones, alergias y condiciones de salud.',
            link: '/web/profile-objectives',
            cta: 'Revisar preferencias',
        },
        family_group: {
            title: '4. Grupo familiar',
            hint: 'Crea un grupo o unite a uno existente.',
            link: '/web/family-group',
            cta: 'Ir a grupo familiar',
        },
    };

    var STEP_ORDER = ['basic_profile', 'objective', 'meals_per_day', 'food_preferences', 'family_group'];

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function showMessage(root, type, message) {
        var el = qs('[data-onboarding-message]', root);
        if (!el) {
            return;
        }
        el.textContent = message;
        el.className = 'alert alert-' + type;
        el.style.display = 'block';
    }

    function stepDetail(key, step) {
        if (key === 'basic_profile' && step.missing && step.missing.length) {
            return 'Falta: ' + step.missing.join(', ');
        }
        if (key === 'objective') {
            return step.objectives_count + ' objetivo(s) elegido(s)';
        }
        if (key === 'meals_per_day') {
            return step.value ? (step.value + ' comidas por dia') : 'Sin definir';
        }
        if (key === 'food_preferences') {
            return step.restrictions_count + ' restricciones, ' + step.allergies_count + ' alergias, ' + step.health_conditions_count + ' condiciones';
        }
        if (key === 'family_group') {
            return step.groups_count + ' grupo(s) activo(s)';
        }
        return '';
    }

    function render(root, data) {
        var list = qs('[data-onboarding-steps]', root);
        var progress = qs('[data-onboarding-progress]', root);
        var done = qs('[data-onboarding-done]', root);
        if (!list) {
            return;
        }

        var total = (data.required_steps || []).length;
        if (progress) {
            progress.textContent = data.complete
                ? 'Onboarding completo. Ya podes usar stock y recetas.'
                : ('Paso ' + (data.completed_count + 1) + ' de ' + total + '. Falta: ' + (STEP_META[data.next_step] ? STEP_META[data.next_step].title : data.next_step));
        }

        list.innerHTML = STEP_ORDER.map(function (key) {
            var step = data.steps[key];
            if (!step) {
                return '';
            }
            var meta = STEP_META[key];
            var badge = step.complete
                ? '<span style="color:#04ac85;font-weight:700">Listo</span>'
                : (step.optional ? '<span class="muted">Opcional</span>' : '<span style="color:#b33a3a;font-weight:700">Pendiente</span>');
            return '<li class="panel" style="padding:12px">' +
                '<div style="display:flex;justify-content:space-between;gap:10px;align-items:center">' +
                '<strong>' + escapeHtml(meta.title) + '</strong>' + badge + '</div>' +
                '<p class="muted" style="margin:4px 0">' + escapeHtml(meta.hint) + '</p>' +
                '<p class="muted" style="margin:4px 0;font-size:12px">' + escapeHtml(stepDetail(key, step)) + '</p>' +
                '<a class="btn-secondary-web" href="' + escapeHtml(meta.link) + '">' + escapeHtml(meta.cta) + '</a>' +
                '</li>';
        }).join('');

        if (done) {
            done.style.display = data.complete ? 'flex' : 'none';
        }
    }

    function load(root) {
        var list = qs('[data-onboarding-steps]', root);
        if (list) {
            list.innerHTML = '<li class="muted">Cargando pasos...</li>';
        }
        window.CCApi.request('/api/v1/users/me/onboarding')
            .then(function (response) {
                render(root, (response && response.data) ? response.data : response);
            })
            .catch(function (error) {
                var message = 'No se pudo cargar tu progreso de onboarding.';
                if (error && error.status === 401) {
                    message = 'Sesion vencida. Inicia sesion nuevamente.';
                }
                showMessage(root, 'danger', message);
                if (list) {
                    list.innerHTML = '<li class="muted">' + escapeHtml(message) + '</li>';
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = qs('[data-user-onboarding]');
        if (!root || !window.CCApi) {
            return;
        }
        load(root);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                load(root);
            }
        });
    });
})(window, document);
