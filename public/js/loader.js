(function (window, document) {
    'use strict';
    function show() {
        var loader = document.querySelector('[data-navigation-loader]');
        if (!loader) return;
        loader.hidden = false;
        var video = loader.querySelector('video');
        if (video && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            if (!video.src) video.src = '/videos/loading/' + (Math.random() < .5 ? 'cocina' : 'alimentos') + '.mp4';
            var playback = video.play();
            if (playback) playback.catch(function () {});
        }
        window.setTimeout(hide, 12000);
    }
    function hide() {
        var loader = document.querySelector('[data-navigation-loader]');
        if (!loader) return;
        loader.hidden = true;
        var video = loader.querySelector('video');
        if (video) video.pause();
    }
    document.addEventListener('cc:navigating', show);
    document.addEventListener('turbo:load', hide);
    window.addEventListener('pageshow', hide);
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-page-navigation]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target) return;
        if (link.dataset.turbo === 'true') return;
        var url = new URL(link.href, window.location.href);
        if (url.origin === window.location.origin && url.pathname !== window.location.pathname) show();
    });
})(window, document);
