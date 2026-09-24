(function (window, document) {
    'use strict';

    var STEP_META = {
        basic_profile: {
            title: 'Datos básicos',
            hint: 'Altura. El peso actual y el peso objetivo son opcionales.',
            link: '/web/profile-objectives#datos',
            cta: 'Completar datos',
        },
        objective: {
            title: 'Objetivo',
            hint: 'Elegi al menos un objetivo personal.',
            link: '/web/profile-objectives#objetivos',
            cta: 'Elegir objetivo',
        },
        meals_per_day: {
            title: 'Comidas por día',
            hint: 'Cuantas comidas haces por dia.',
            link: '/web/profile-objectives#datos',
            cta: 'Definir comidas',
        },
        food_preferences: {
            title: 'Preferencias alimentarias (opcional)',
            hint: 'Restricciones, alergias y condiciones de salud.',
            link: '/web/profile-objectives#restricciones',
            cta: 'Revisar preferencias',
        },
        family_group: {
            title: 'Grupo familiar',
            hint: 'Crea un grupo o unite a uno existente.',
            link: '/web/family-group',
            cta: 'Ir a grupo familiar',
        },
    };

    var STEP_ORDER = ['basic_profile', 'objective', 'meals_per_day', 'family_group', 'food_preferences'];

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
            var labels = { height_cm: 'altura', current_weight_kg: 'peso actual', target_weight_kg: 'peso objetivo' };
            return 'Falta: ' + step.missing.map(function (field) { return labels[field] || 'dato del perfil'; }).join(', ');
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
                ? 'Configuracion inicial completa. Ya podes usar stock y recetas.'
                : ('Paso ' + (data.completed_count + 1) + ' de ' + total + '. Falta: ' + (STEP_META[data.next_step] ? STEP_META[data.next_step].title : data.next_step));
        }

        var continueLink = qs('[data-screen-primary-action]');
        if (continueLink) {
            continueLink.href = data.complete ? '/web' : (STEP_META[data.next_step] || STEP_META.basic_profile).link;
            continueLink.textContent = data.complete ? 'Ir al inicio' : 'Continuar';
        }

        list.innerHTML = STEP_ORDER.map(function (key, index) {
            var step = data.steps[key];
            if (!step) {
                return '';
            }
            var meta = STEP_META[key];
            var badge = step.complete ? 'Completo' : (step.optional ? 'Opcional' : 'Pendiente');
            return '<li class="onboarding-step' + (step.complete ? ' is-complete' : '') + '">' +
                '<span class="step-marker" aria-hidden="true">' + (step.complete ? '&#10003;' : (index + 1)) + '</span>' +
                '<div class="step-copy"><h3>' + escapeHtml(meta.title) + '</h3>' +
                '<span class="step-status">' + badge + '</span>' +
                '<p>' + escapeHtml(meta.hint) + '</p>' +
                '<p>' + escapeHtml(stepDetail(key, step)) + '</p></div>' +
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
            list.innerHTML = '<li class="cc-loading" role="status"><span class="cc-spinner" aria-hidden="true"></span>Cargando tu progreso...</li>';
        }
        window.CCApi.request('/api/v1/users/me/onboarding')
            .then(function (response) {
                render(root, (response && response.data) ? response.data : response);
            })
            .catch(function (error) {
                var message = 'No se pudo cargar tu progreso de configuracion inicial.';
                if (error && error.status === 401) {
                    message = 'Sesion vencida. Inicia sesion nuevamente.';
                }
                showMessage(root, 'danger', message);
                if (list) {
                    list.innerHTML = '<li class="onboarding-error">' + escapeHtml(message) + '</li>';
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
