(function (document) {
    'use strict';
    if (document.ccPanelMenuBound) return;
    document.ccPanelMenuBound = true;

    function close(group) {
        group.classList.remove('show');
        var menu = group.querySelector('.dropdown-menu');
        var toggle = group.querySelector('[data-toggle="dropdown"]');
        if (menu) menu.classList.remove('show');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }
    function closeAll() {
        document.querySelectorAll('.site-navbar .dropdown.show').forEach(function (group) {
            close(group);
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
        // Own only navbar toggles, including layouts that still load Bootstrap.
        event.stopPropagation();
        if (toggle.getAttribute('aria-expanded') === 'true') closeAll();
        else open(toggle);
    }, true);
    document.addEventListener('keydown', function (event) {
        var group = event.target.closest('.site-navbar .dropdown');
        if (!group) return;
        var toggle = group.querySelector('[data-toggle="dropdown"]');
        if (!toggle) return;
        if (event.key === 'Escape') {
            if (toggle.getAttribute('aria-expanded') !== 'true') return;
            event.stopPropagation();
            event.preventDefault(); closeAll(); toggle.focus(); return;
        }
        if ((event.key === ' ' || event.key === 'Enter') && event.target === toggle) {
            event.preventDefault(); event.stopPropagation(); toggle.click(); return;
        }
        if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(event.key) === -1) return;
        event.stopPropagation();
        event.preventDefault(); open(toggle);
        var links = Array.from(group.querySelectorAll('.dropdown-menu a[href]')).filter(function (link) {
            return !link.closest('[hidden], [aria-hidden="true"]') && link.getAttribute('aria-disabled') !== 'true' && !link.classList.contains('disabled');
        });
        var index = links.indexOf(event.target);
        var next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1
            : index < 0 ? (event.key === 'ArrowDown' ? 0 : links.length - 1)
            : (index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
        if (links[next]) links[next].focus();
    }, true);
    document.addEventListener('focusin', function (event) {
        var current = event.target.closest('.site-navbar .dropdown');
        document.querySelectorAll('.site-navbar .dropdown.show').forEach(function (group) {
            if (group !== current) close(group);
        });
    });
    document.addEventListener('turbo:before-render', closeAll);
    document.addEventListener('turbo:before-cache', closeAll);
})(document);
