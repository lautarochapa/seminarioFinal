(function (window) {
    'use strict';

    var TOKEN_KEY = 'cccontrol.auth.token';
    var USER_KEY = 'cccontrol.auth.user';
    var page = 0, reads = new Set(), writes = new Set(), requests = new Set();
    var sessionClosing = false, sessionClosePromise = null;

    function cancelPageReads() {
        page += 1;
        if (sessionClosing) return; // Keep draining HTTP responses until logout owns the last cookie.
        reads.forEach(function (read) {
            // Discard the old render through its generation, but keep cookie-session
            // responses tracked: the server may still write the session after navigation.
            if (!read.webSession) {
                read.controller.abort();
                reads.delete(read);
            }
        });
    }

    function afterWrites() {
        return Promise.all(Array.from(writes).map(function (pending) { return pending.catch(function () {}); })).then(function () {
            return new Promise(function (resolve) { window.setTimeout(resolve, 0); });
        }).then(function () {
            return writes.size ? afterWrites() : undefined;
        });
    }

    function usesWebSession() {
        return !!window.document.querySelector('meta[name="ccc-auth"][content="session"]');
    }

    function getToken() {
        return usesWebSession() ? null : window.localStorage.getItem(TOKEN_KEY);
    }

    function setSession(payload) {
        if (usesWebSession()) {
            window.localStorage.removeItem(TOKEN_KEY);
        } else if (payload && payload.token && payload.token.access_token) {
            window.localStorage.setItem(TOKEN_KEY, payload.token.access_token);
        }

        if (payload && payload.csrf_token) {
            var csrf = window.document.querySelector('meta[name="csrf-token"]');
            if (csrf) csrf.content = payload.csrf_token;
        }

        if (payload && payload.data) {
            window.localStorage.setItem(USER_KEY, JSON.stringify(payload.data));
        }
    }

    function clearSession() {
        window.localStorage.removeItem(TOKEN_KEY);
        window.localStorage.removeItem(USER_KEY);
    }

    function getUser() {
        try {
            return JSON.parse(window.localStorage.getItem(USER_KEY) || 'null');
        } catch (error) {
            return null;
        }
    }

    function afterRequests() {
        return Promise.all(Array.from(requests).map(function (pending) {
            return pending.catch(function () {});
        })).then(function () {
            return requests.size ? afterRequests() : undefined;
        });
    }

    function closeWebSession() {
        if (sessionClosePromise) return sessionClosePromise;
        sessionClosing = true;
        // A cancelled fetch can still finish on the server and restore its old session.
        // Let every response finish before invalidating the cookie with the logout POST.
        sessionClosePromise = afterRequests().then(function () {
            return sendRequest('/api/v1/auth/logout', { method: 'POST' });
        }).then(function (data) {
            page += 1;
            return data;
        }, function (error) {
            sessionClosing = false;
            sessionClosePromise = null;
            throw error;
        });
        return sessionClosePromise;
    }

    function request(path, options) {
        if (sessionClosing) {
            var error = new Error('Estamos cerrando la sesión. Esperá un momento.');
            error.code = 'SESSION_CLOSING';
            return Promise.reject(error);
        }
        return sendRequest(path, options);
    }

    function sendRequest(path, options) {
        var config = options || {};
        var headers = config.headers || {};
        var token = getToken();
        var method = (config.method || 'GET').toUpperCase();
        var reading = method === 'GET' || method === 'HEAD';
        var generation = page;
        var controller = new AbortController();
        var read = { controller: controller, webSession: usesWebSession() };
        if (reading) reads.add(read);
        if (read.webSession) {
            path = path.replace(/^\/api\/v1\/auth\/(login|register|logout)$/, '/web-session/$1');
            var csrf = window.document.querySelector('meta[name="csrf-token"]');
            if (csrf) headers['X-CSRF-TOKEN'] = csrf.content;
        }

        headers.Accept = 'application/json';

        if (!(config.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        var pending = window.fetch(path, {
            method: method,
            headers: headers,
            body: config.body instanceof FormData ? config.body : (config.body ? JSON.stringify(config.body) : undefined),
            credentials: 'same-origin',
            signal: controller.signal,
        }).then(function (response) {
            if (response.status === 204) {
                return { ok: response.ok, status: response.status, data: null };
            }

            return response.text().then(function (body) {
                var data;
                try {
                    data = JSON.parse(body);
                } catch (parseError) {
                    var loginRedirect = response.redirected && /\/login(?:[?#]|$)/.test(response.url || '');
                    var invalid = new Error(loginRedirect
                        ? 'Tu sesión venció. Volvé a ingresar para continuar.'
                        : 'No pudimos cargar la información. Intentá nuevamente en unos instantes.');
                    invalid.status = loginRedirect ? 401 : response.status;
                    invalid.code = 'API_INVALID_RESPONSE';
                    invalid.traceId = response.headers.get('X-Trace-Id');
                    if (loginRedirect) clearSession();
                    throw invalid;
                }
                if (!response.ok) {
                    var error = new Error((data.error && data.error.message) || 'No se pudo completar la operacion.');
                    error.status = response.status;
                    if (response.status === 401) clearSession();
                    error.payload = data;
                    throw error;
                }

                return data;
            });
        });
        requests.add(pending);
        if (!reading) writes.add(pending);
        return pending.finally(function () {
            reads.delete(read); writes.delete(pending); requests.delete(pending);
        }).then(function (data) {
            // A replaced screen must not run its old render chain against the new DOM.
            return reading && generation !== page ? new Promise(function () {}) : data;
        }, function (error) {
            if (reading && generation !== page) return new Promise(function () {});
            throw error;
        });
    }

    window.CCApi = {
        request: request,
        getToken: getToken,
        setSession: setSession,
        clearSession: clearSession,
        getUser: getUser,
        usesWebSession: usesWebSession,
        cancelPageReads: cancelPageReads,
        closeWebSession: closeWebSession,
        hasPendingWrites: function () { return writes.size > 0; },
        afterWrites: afterWrites,
    };
    if (usesWebSession()) window.localStorage.removeItem(TOKEN_KEY);
    window.addEventListener('pagehide', function () {
        if (usesWebSession() && window.document.querySelector('.site-navbar-authenticated')) {
            window.document.documentElement.style.visibility = 'hidden';
        }
    });
    window.addEventListener('pageshow', function (event) {
        // Browser back/forward cache is independent from Turbo's snapshot cache.
        if (event.persisted && usesWebSession()) {
            window.document.documentElement.style.visibility = 'hidden';
            window.location.reload();
        }
    });
})(window);
