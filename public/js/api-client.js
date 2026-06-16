(function (window) {
    'use strict';

    var TOKEN_KEY = 'cccontrol.auth.token';
    var USER_KEY = 'cccontrol.auth.user';

    function getToken() {
        return window.localStorage.getItem(TOKEN_KEY);
    }

    function setSession(payload) {
        if (payload && payload.token && payload.token.access_token) {
            window.localStorage.setItem(TOKEN_KEY, payload.token.access_token);
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

    function request(path, options) {
        var config = options || {};
        var headers = config.headers || {};
        var token = getToken();

        headers.Accept = 'application/json';

        if (!(config.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        return window.fetch(path, {
            method: config.method || 'GET',
            headers: headers,
            body: config.body instanceof FormData ? config.body : (config.body ? JSON.stringify(config.body) : undefined),
            credentials: 'same-origin',
        }).then(function (response) {
            if (response.status === 204) {
                return { ok: response.ok, status: response.status, data: null };
            }

            return response.json().then(function (data) {
                if (!response.ok) {
                    var error = new Error((data.error && data.error.message) || 'No se pudo completar la operacion.');
                    error.status = response.status;
                    error.payload = data;
                    throw error;
                }

                return data;
            });
        });
    }

    window.CCApi = {
        request: request,
        getToken: getToken,
        setSession: setSession,
        clearSession: clearSession,
        getUser: getUser,
    };
})(window);
