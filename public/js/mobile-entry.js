(function (window, document) {
    'use strict';
    var ua = window.navigator.userAgent || '';
    var android = /Android/i.test(ua);
    var ios = /iPhone|iPad|iPod/i.test(ua) || (/Macintosh/i.test(ua) && window.navigator.maxTouchPoints > 1);
    function intent(register) {
        var fallback = window.location.origin + '/abrir-app';
        return 'intent://' + (register ? 'register' : 'login') + '/#Intent;scheme=cccontrol;package=com.cccontrol.mobile;S.browser_fallback_url=' + encodeURIComponent(fallback) + ';end';
    }
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-open-app]').forEach(function (link) {
            if (android) link.href = intent(false);
        });
        document.querySelectorAll('a[data-mobile-entry]').forEach(function (link) {
            if (document.body.dataset.mobileInvitation === '1') return;
            if (!android && !ios) return;
            link.href = android ? intent(link.dataset.mobileEntry === 'register') : '/abrir-app';
            link.textContent = android ? (link.dataset.mobileEntry === 'register' ? 'Crear cuenta' : 'Abrir app') : 'Acceder';
        });
        document.querySelectorAll('[data-ios-entry]').forEach(function (node) { node.hidden = !ios; });
        document.querySelectorAll('[data-android-entry]').forEach(function (node) { node.hidden = ios; });
    });
})(window, document);
