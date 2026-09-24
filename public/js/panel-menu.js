(function (document) {
    'use strict';
    function closeAll() {
        document.querySelectorAll('.site-navbar .dropdown.show').forEach(function (group) {
            group.classList.remove('show');
            group.querySelector('.dropdown-menu').classList.remove('show');
            group.querySelector('[data-toggle="dropdown"]').setAttribute('aria-expanded', 'false');
        });
    }
    function open(toggle) {
        closeAll();
        var group = toggle.closest('.dropdown');
        group.classList.add('show');
        group.querySelector('.dropdown-menu').classList.add('show');
        toggle.setAttribute('aria-expanded', 'true');
    }
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.site-navbar [data-toggle="dropdown"]');
        if (!toggle) { closeAll(); return; }
        event.preventDefault();
        if (toggle.getAttribute('aria-expanded') === 'true') closeAll();
        else open(toggle);
    });
    document.addEventListener('keydown', function (event) {
        var group = event.target.closest('.site-navbar .dropdown');
        if (!group) return;
        var toggle = group.querySelector('[data-toggle="dropdown"]');
        if (event.key === 'Escape') {
            event.preventDefault(); closeAll(); toggle.focus(); return;
        }
        if (event.key === ' ' && event.target === toggle) { event.preventDefault(); toggle.click(); return; }
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        event.preventDefault(); open(toggle);
        var links = Array.from(group.querySelectorAll('.dropdown-menu a[href]'));
        var index = links.indexOf(event.target);
        var next = index < 0 ? (event.key === 'ArrowDown' ? 0 : links.length - 1)
            : (index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
        if (links[next]) links[next].focus();
    });
    document.addEventListener('focusin', function (event) {
        if (!event.target.closest('.site-navbar .dropdown')) closeAll();
    });
    document.addEventListener('turbo:before-render', closeAll);
})(document);
