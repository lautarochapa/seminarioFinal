(function (window, document) {
    'use strict';

    function formToObject(form) {
        var data = {};
        Array.from(new FormData(form).entries()).forEach(function (entry) {
            var key = entry[0];
            var value = entry[1];
            if (key === '_token') {
                return;
            }
            if (key === 'remember') {
                data[key] = true;
                return;
            }
            data[key] = value;
        });

        if (form.querySelector('[name="remember"]') && !data.remember) {
            data.remember = false;
        }

        return data;
    }

    function clearFeedback(form) {
        Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (input) {
            input.classList.remove('is-invalid');
        });
        Array.prototype.forEach.call(form.querySelectorAll('[data-field-error]'), function (node) {
            node.textContent = '';
            node.style.display = 'none';
        });

        var alert = form.querySelector('[data-api-message]');
        if (alert) {
            alert.textContent = '';
            alert.className = 'alert';
            alert.style.display = 'none';
        }
    }

    function showMessage(form, type, message) {
        var alert = form.querySelector('[data-api-message]');
        if (!alert) {
            return;
        }

        alert.textContent = message;
        alert.className = 'alert alert-' + type;
        alert.style.display = 'block';
    }

    function showErrors(form, error) {
        var payload = error.payload || {};
        var apiError = payload.error || {};
        var fieldErrors = apiError.field_errors || {};
        var hasFieldError = false;

        Object.keys(fieldErrors).forEach(function (field) {
            hasFieldError = true;
            var input = form.querySelector('[name="' + field + '"]');
            var holder = form.querySelector('[data-field-error="' + field + '"]');

            if (input) {
                input.classList.add('is-invalid');
            }
            if (holder) {
                holder.textContent = fieldErrors[field].join(' ');
                holder.style.display = 'block';
            }
        });

        if (!hasFieldError || apiError.message) {
            showMessage(form, 'danger', apiError.message || error.message);
        }
    }

    function setLoading(form, loading) {
        form.setAttribute('aria-busy', loading ? 'true' : 'false');
        Array.prototype.forEach.call(form.querySelectorAll('[type="submit"]'), function (submit) {
            if (!submit.dataset.originalText) {
                submit.dataset.originalText = submit.textContent;
            }
            submit.disabled = loading;
            submit.textContent = loading ? 'Procesando...' : submit.dataset.originalText;
        });
    }

    function establishWebSession() {
        return window.CCApi.request('/api/v1/auth/me').then(function (payload) {
            window.CCApi.setSession(payload);
            return payload;
        });
    }

    function payloadPermissions(payload) {
        var token = payload && payload.token_payload ? payload.token_payload : {};
        var data = payload && payload.data ? payload.data : {};

        if (Array.isArray(token.permissions)) {
            return token.permissions;
        }

        if (Array.isArray(data.permissions)) {
            return data.permissions;
        }

        return [];
    }

    function dashboardRedirect(fallback, payload) {
        var permissions = payloadPermissions(payload);

        try {
            var destination = new URL(fallback, window.location.origin);
            if (destination.origin === window.location.origin && destination.pathname === '/web/family-group'
                && /^[1-9][0-9]*$/.test(destination.searchParams.get('invitation') || '')
                && permissions.indexOf('web.user.family-group') !== -1) {
                return destination.pathname + destination.search;
            }
        } catch (error) { /* Continue with the role's default dashboard. */ }

        if (permissions.indexOf('web.admin.dashboard') !== -1) {
            return '/admin-web';
        }

        if (permissions.indexOf('web.user.dashboard') !== -1) {
            return '/web';
        }

        return '/web';
    }

    function redirectAfterAuth(fallback, confirmedPayload) {
        return (confirmedPayload ? Promise.resolve(confirmedPayload) : establishWebSession()).then(function (payload) {
            document.dispatchEvent(new Event('cc:navigating'));
            window.location.href = dashboardRedirect(fallback, payload);
        });
    }

    function bindApiForm(form) {
        if (form.dataset.apiBound === 'true') {
            return;
        }
        form.dataset.apiBound = 'true';
        var pending = false;
        var authenticated = false;
        var navigating = false;
        var confirmedPayload = null;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (pending || navigating) {
                return;
            }
            pending = true;
            clearFeedback(form);
            setLoading(form, true);

            Promise.resolve().then(function () {
                // A session lookup failure must not submit a successful registration again.
                if (authenticated) {
                    return;
                }
                return window.CCApi.request(form.dataset.apiEndpoint, {
                    method: form.dataset.apiMethod || 'POST',
                    body: formToObject(form),
                }).then(function (payload) {
                    if (form.dataset.authSession === 'true') {
                        window.CCApi.setSession(payload);
                        authenticated = true;
                        confirmedPayload = payload;
                    }
                });
            }).then(function () {
                if (form.dataset.successMessage) {
                    showMessage(form, 'success', form.dataset.successMessage);
                }

                if (form.dataset.redirect && form.dataset.authSession === 'true') {
                    return redirectAfterAuth(form.dataset.redirect, confirmedPayload).then(function () {
                        navigating = true;
                    });
                }

                if (form.dataset.redirect) {
                    window.location.href = form.dataset.redirect;
                    navigating = true;
                }
            }).catch(function (error) {
                if (authenticated) {
                    showMessage(form, 'warning', 'Tu acceso ya fue confirmado. Pulsa Continuar para volver a intentar abrir tu cuenta.');
                    Array.prototype.forEach.call(form.querySelectorAll('[type="submit"]'), function (submit) {
                        submit.dataset.originalText = 'Continuar';
                    });
                } else {
                    showErrors(form, error);
                }
            }).finally(function () {
                if (!navigating) {
                    pending = false;
                    setLoading(form, false);
                }
            });
        });
    }

    function bindLogout(link) {
        if (link.dataset.apiBound === 'true') return;
        link.dataset.apiBound = 'true';
        var pending = false;
        link.addEventListener('click', function (event) {
            event.preventDefault();
            if (pending) return;
            pending = true;
            link.setAttribute('aria-busy', 'true');
            var logout = window.CCApi.usesWebSession()
                ? window.CCApi.closeWebSession()
                : window.CCApi.request('/api/v1/auth/logout', { method: 'POST' });
            logout.then(function () {
                window.CCApi.clearSession();
                window.location.href = '/login';
            }).catch(function () {
                pending = false;
                link.removeAttribute('aria-busy');
                window.alert('No pudimos cerrar la sesión. Volvé a intentarlo.');
            });
        });
    }

    function bindProfileForm(form) {
        if (form.dataset.apiBound === 'true') return;
        form.dataset.apiBound = 'true';
        var message = form.querySelector('[data-api-message]');

        window.CCApi.request('/api/v1/auth/me').then(function (payload) {
            var user = payload.data || {};
            window.CCApi.setSession({ data: user });
            ['name', 'lastname', 'username', 'email', 'phone', 'avatar_url'].forEach(function (field) {
                var input = form.querySelector('[name="' + field + '"]');
                if (input) {
                    input.value = user[field] || '';
                }
            });
        }).catch(function () {
            if (message) {
                showMessage(form, 'warning', 'Inicia sesion para cargar tu perfil desde la API.');
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearFeedback(form);
            setLoading(form, true);

            var body = formToObject(form);
            delete body.email;

            window.CCApi.request('/api/v1/auth/me', {
                method: 'PATCH',
                body: body,
            }).then(function (payload) {
                window.CCApi.setSession(payload);
                showMessage(form, 'success', 'Perfil actualizado correctamente.');
            }).catch(function (error) {
                showErrors(form, error);
            }).finally(function () {
                setLoading(form, false);
            });
        });
    }

    function bindDemoLogin(button) {
        button.addEventListener('click', function () {
            var email = document.querySelector('input[name="email"]');
            var password = document.querySelector('input[name="password"]');

            if (email) {
                email.value = button.dataset.demoEmail || '';
                email.dispatchEvent(new Event('input', { bubbles: true }));
            }

            if (password) {
                password.value = button.dataset.demoPassword || '';
                password.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    function filterPermissionElements() {
        if (window.CCApi.usesWebSession && window.CCApi.usesWebSession()) return;
        var user = window.CCApi.getUser();
        var permissions = user && Array.isArray(user.permissions) ? user.permissions : null;

        if (!permissions) {
            return;
        }

        Array.from(document.querySelectorAll('[data-permission]')).forEach(function (element) {
            if (permissions.indexOf(element.dataset.permission) === -1) {
                element.parentNode.removeChild(element);
            }
        });
    }

    function setup() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-api-endpoint]'), bindApiForm);
        Array.prototype.forEach.call(document.querySelectorAll('[data-api-logout]'), bindLogout);
        Array.prototype.forEach.call(document.querySelectorAll('form[data-profile-api]'), bindProfileForm);
        Array.prototype.forEach.call(document.querySelectorAll('[data-demo-login]'), bindDemoLogin);
        filterPermissionElements();
    }
    if (window.CCPage) window.CCPage.register('auth', setup);
    else document.addEventListener('DOMContentLoaded', setup);
})(window, document);
