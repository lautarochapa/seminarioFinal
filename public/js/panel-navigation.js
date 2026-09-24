(function (window, document) {
    'use strict';
    var modules = new Map(), cleanups = [], mounted = null;
    var queuedVisit = null, waitingForWrite = false;
    var routes = ['/web', '/web/stock', '/web/recipes', '/web/planning', '/web/shopping-list', '/web/budget', '/web/family-group', '/web/profile-objectives'];

    function mount() {
        var root = document.querySelector('[data-panel-page]');
        if (!root || root === mounted) return;
        mounted = root;
        modules.forEach(function (initialize) { initialize(); });
        document.querySelectorAll('a[href]').forEach(function (link) {
            var url = new URL(link.href, window.location.href);
            if (url.origin === window.location.origin && routes.indexOf(url.pathname) !== -1 && !link.hasAttribute('download')) {
                link.dataset.turbo = 'true';
            }
        });
    }

    function dispose() {
        cleanups.splice(0).forEach(function (cleanup) { cleanup(); });
        if (window.CCApi) window.CCApi.cancelPageReads();
        mounted = null;
    }

    function reconcileHeader(body) {
        var current = document.getElementById('panel-header'), next = body.querySelector('#panel-header');
        if (!current || !next) return;
        var fingerprint = function (node) {
            return node.dataset.user + ':' + Array.from(node.querySelectorAll('a')).map(function (link) {
                return link.getAttribute('href') + ':' + link.textContent.trim();
            }).join('|');
        };
        // Keep the node only when identity and server-authorized menu entries still agree.
        if (fingerprint(current) !== fingerprint(next)) {
            current.removeAttribute('data-turbo-permanent');
            return;
        }
        var links = next.querySelectorAll('a');
        current.querySelectorAll('a').forEach(function (link, index) {
            link.classList.toggle('active', links[index].classList.contains('active'));
            if (links[index].hasAttribute('aria-current')) link.setAttribute('aria-current', links[index].getAttribute('aria-current'));
            else link.removeAttribute('aria-current');
        });
    }

    window.CCPage = {
        register: function (name, initialize) { modules.set(name, initialize); },
        onDispose: function (cleanup) { cleanups.push(cleanup); },
        listen: function (target, type, handler) {
            target.addEventListener(type, handler);
            cleanups.push(function () { target.removeEventListener(type, handler); });
        }
    };
    window.Turbo.session.drive = false;
    window.Turbo.config.drive.progressBarDelay = 150;
    document.addEventListener('turbo:before-prefetch', function (event) { event.preventDefault(); });
    document.addEventListener('turbo:before-visit', function (event) {
        if (!window.CCApi || !window.CCApi.hasPendingWrites()) return;
        event.preventDefault();
        queuedVisit = event.detail.url;
        if (waitingForWrite) return;
        waitingForWrite = true;
        window.CCApi.afterWrites().then(function () {
            waitingForWrite = false;
            var url = queuedVisit; queuedVisit = null;
            window.Turbo.visit(url);
        });
    });
    document.addEventListener('turbo:before-render', function (event) {
        var finish = function () { reconcileHeader(event.detail.newBody); dispose(); };
        if (window.CCApi && window.CCApi.hasPendingWrites()) {
            event.preventDefault();
            window.CCApi.afterWrites().then(function () { finish(); event.detail.resume(); });
        } else finish();
    });
    document.addEventListener('turbo:load', mount);
    document.addEventListener('DOMContentLoaded', mount);
})(window, document);
