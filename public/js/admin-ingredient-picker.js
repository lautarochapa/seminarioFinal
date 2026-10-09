(function (window, document) {
    'use strict';

    var nextId = 0;

    function attach(select) {
        if (select.ccIngredientPicker) { return select.ccIngredientPicker; }

        var first = Array.from(select.options).find(function (option) { return option.value === ''; });
        var placeholder = first ? first.textContent : 'Ingrediente';
        var selectedOption = select.selectedOptions[0];
        var selected = select.value ? { id: select.value, name: selectedOption.textContent } : null;
        var results = [];
        var timer = null;
        var revision = 0;
        var disposed = false;
        var id = 'cc-ingredient-search-' + (++nextId);
        var wrapper = document.createElement('div');
        var label = document.createElement('label');
        var input = document.createElement('input');
        var status = document.createElement('div');
        var previous = select.previousElementSibling;
        var originalLabel = previous && previous.tagName === 'LABEL' && select.id && previous.htmlFor === select.id ? previous : null;

        wrapper.style.marginBottom = '6px';
        wrapper.style.minWidth = '0';
        wrapper.style.maxWidth = '100%';
        label.htmlFor = id;
        label.textContent = 'Buscar ingrediente';
        label.style.display = 'block';
        label.style.fontSize = '13px';
        input.id = id;
        input.type = 'search';
        input.className = 'form-control';
        input.placeholder = 'Ej.: fideo, puré de tomate';
        input.autocomplete = 'off';
        input.setAttribute('data-ingredient-search', '');
        input.setAttribute('aria-describedby', id + '-status');
        input.setAttribute('aria-busy', 'false');
        status.id = id + '-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.style.fontSize = '12px';
        wrapper.appendChild(label);
        wrapper.appendChild(input);
        wrapper.appendChild(status);
        select.parentNode.insertBefore(wrapper, select);
        if (originalLabel) { wrapper.insertBefore(originalLabel, label); }
        wrapper.appendChild(select);

        function invalidate() {
            revision++;
            window.clearTimeout(timer);
            timer = null;
            input.setAttribute('aria-busy', 'false');
        }

        function dispose() {
            if (disposed) { return; }
            disposed = true;
            invalidate();
            input.removeEventListener('input', onInput);
            input.removeEventListener('keydown', onKeydown);
            select.removeEventListener('change', onChange);
            if (wrapper.parentNode) {
                if (originalLabel && originalLabel.parentNode === wrapper) { wrapper.before(originalLabel); }
                if (select.parentNode === wrapper) { wrapper.before(select); }
            }
            wrapper.remove();
            delete select.ccIngredientPicker;
        }

        function alive() {
            if (disposed) { return false; }
            if (!select.isConnected || !wrapper.isConnected) { dispose(); return false; }
            return true;
        }

        function render() {
            var wanted = selected ? String(selected.id) : '';
            var items = selected ? [selected].concat(results) : results;
            var seen = {};
            select.replaceChildren(new window.Option(placeholder, '', true, !wanted));
            items.forEach(function (item) {
                var value = String(item.id);
                if (!value || seen[value]) { return; }
                seen[value] = true;
                select.appendChild(new window.Option(item.name, value, false, value === wanted));
            });
            select.value = wanted;
        }

        function search(query, requestRevision) {
            if (!alive() || requestRevision !== revision) { return; }
            input.setAttribute('aria-busy', 'true');
            status.textContent = 'Buscando ingredientes…';
            var path = '/api/v1/admin/ingredients?status=active&search=' + encodeURIComponent(query) + '&per_page=20&sort=name&order=asc';
            Promise.resolve().then(function () {
                if (!alive() || requestRevision !== revision) { return null; }
                return window.CCApi.request(path);
            }).then(function (response) {
                if (!alive() || requestRevision !== revision || !response) { return; }
                results = response.data || [];
                render();
                input.setAttribute('aria-busy', 'false');
                var total = response.meta && Number(response.meta.total);
                status.textContent = results.length === 0 ? 'Sin resultados. Probá otro nombre.' :
                    (total > results.length ? 'Se muestran ' + results.length + ' resultados. Escribí un nombre más específico.' : results.length + ' ingredientes encontrados.');
            }).catch(function () {
                if (!alive() || requestRevision !== revision) { return; }
                input.setAttribute('aria-busy', 'false');
                status.textContent = 'No se pudieron buscar los ingredientes. Volvé a escribir para reintentar.';
            });
        }

        function onInput() {
            invalidate();
            if (!alive()) { return; }
            var requestRevision = revision;
            status.textContent = 'Buscando ingredientes…';
            timer = window.setTimeout(function () { search(input.value.trim(), requestRevision); }, 300);
        }

        function onChange() {
            var option = select.selectedOptions[0];
            selected = select.value ? { id: select.value, name: option.textContent } : null;
        }

        function onKeydown(event) {
            if (event.key !== 'Enter') { return; }
            event.preventDefault();
            invalidate();
            search(input.value.trim(), revision);
        }

        var instance = {
            input: input,
            setSelected: function (entity) {
                if (!alive()) { return; }
                invalidate();
                input.value = '';
                status.textContent = '';
                selected = entity && entity.id !== null && entity.id !== undefined ? { id: entity.id, name: entity.name || 'Ingrediente #' + entity.id } : null;
                render();
            },
            reset: function () {
                if (!alive()) { return; }
                invalidate();
                selected = null;
                results = [];
                input.value = '';
                render();
                search('', revision);
            },
        };

        select.ccIngredientPicker = instance;
        input.addEventListener('input', onInput);
        input.addEventListener('keydown', onKeydown);
        select.addEventListener('change', onChange);
        if (window.CCPage && typeof window.CCPage.onDispose === 'function') { window.CCPage.onDispose(dispose); }
        render();
        search('', revision);
        return instance;
    }

    window.CCIngredientPicker = { attach: attach };
})(window, document);
