(function (window, document) {
    'use strict';
    var counter = 0;
    function all(selector, root) { return Array.from((root || document).querySelectorAll(selector)); }
    function button(text, cls) {
        var el = document.createElement('button');
        el.type = 'button'; el.className = cls || 'btn-secondary-web'; el.textContent = text;
        return el;
    }
    function reveal(el) {
        if (!el) { return; }
        var panel = el.closest('[role="tabpanel"]');
        if (panel && panel.ccSelect) { panel.ccSelect(false); }
        var dialog = el.closest('dialog');
        if (dialog && !dialog.open) {
            dialog.ccReturnFocus = document.activeElement;
            dialog.showModal();
        }
    }
    function close(el) {
        var dialog = el && el.closest('dialog');
        if (dialog && dialog.open) { dialog.close(); }
    }
    function saved(form, text) {
        if (!form) { return; }
        var dialog = form.closest('dialog');
        if (!dialog) { return; }
        var root = dialog.parentElement;
        close(form);
        var notice = root.querySelector(':scope > [data-ui-notice]');
        if (!notice) {
            notice = document.createElement('div');
            notice.setAttribute('data-ui-notice', '');
            notice.setAttribute('role', 'status');
            notice.className = 'alert alert-success';
            root.prepend(notice);
        }
        notice.textContent = text || 'Cambios guardados correctamente.';
        notice.hidden = false;
    }
    function tabs(container, labels, ids) {
        if (!container) { return; }
        var panels = Array.from(container.children).filter(function (el) { return el.matches('article.panel'); });
        if (panels.length !== labels.length) { return; }
        var bar = document.createElement('div');
        bar.className = 'panel-tabs'; bar.setAttribute('role', 'tablist');
        bar.setAttribute('aria-label', 'Secciones');
        container.insertBefore(bar, panels[0]);
        var buttons = [];
        panels.forEach(function (panel, index) {
            var id = (ids && ids[index]) || 'seccion-' + index;
            panel.id = id; panel.setAttribute('role', 'tabpanel');
            var tab = button(labels[index], 'panel-tab');
            tab.id = 'tab-' + id; tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', id); panel.setAttribute('aria-labelledby', tab.id);
            buttons.push(tab); bar.appendChild(tab);
            panel.ccSelect = function (focus) {
                panels.forEach(function (item, i) {
                    item.hidden = i !== index;
                    buttons[i].setAttribute('aria-selected', String(i === index));
                    buttons[i].tabIndex = i === index ? 0 : -1;
                });
                if (focus) {
                    tab.focus();
                    window.history.replaceState(null, '', '#' + id);
                }
            };
            tab.addEventListener('click', function () { panel.ccSelect(true); });
            tab.addEventListener('keydown', function (event) {
                var next;
                if (event.key === 'ArrowRight') { next = (index + 1) % panels.length; }
                if (event.key === 'ArrowLeft') { next = (index + panels.length - 1) % panels.length; }
                if (event.key === 'Home') { next = 0; }
                if (event.key === 'End') { next = panels.length - 1; }
                if (next !== undefined) { event.preventDefault(); panels[next].ccSelect(true); }
            });
        });
        var selected = panels.find(function (panel) { return '#' + panel.id === window.location.hash; });
        (selected || panels[0]).ccSelect(false);
        window.addEventListener('hashchange', function () {
            var target = panels.find(function (panel) { return '#' + panel.id === window.location.hash; });
            if (target) { target.ccSelect(false); }
        });
    }
    function toolbar(root) {
        var bar = root.querySelector(':scope > .panel-actions');
        if (!bar) {
            bar = document.createElement('div'); bar.className = 'panel-actions';
            root.prepend(bar);
        }
        return bar;
    }
    function labelFields(container) {
        var names = {
            period_type: 'Período', start_date: 'Fecha de inicio', end_date: 'Fecha de fin',
            mode: 'Modo', item_date: 'Fecha de la comida', meal_type_id: 'Tipo de comida',
            recipe_id: 'Receta', free_meal_description: 'Comida libre', servings_total: 'Porciones',
            notes: 'Notas', date: 'Fecha', portion_factor: 'Porción', stock_location_id: 'Ubicación',
            quantity: 'Cantidad', unit_id: 'Unidad', expiration_date: 'Vencimiento',
            purchase_price: 'Precio de compra', status: 'Estado', name: 'Nombre', brand: 'Marca',
            presentation: 'Presentación', barcode: 'Código de barras', stock_item_select: 'Producto del hogar',
            operation: 'Operación', reason: 'Motivo', product_id: 'Producto', minimum_quantity: 'Cantidad mínima',
            type: 'Tipo', city_id: 'Ciudad', user_id: 'Usuario', role: 'Rol', invitation_id: 'Invitación',
            description: 'Descripción', servings: 'Porciones', difficulty: 'Dificultad',
            prep_time_minutes: 'Preparación (minutos)', cook_time_minutes: 'Cocción (minutos)',
            category_id: 'Categoría', color: 'Color', allocated_amount: 'Monto asignado', amount: 'Monto',
            month: 'Mes', year: 'Año', currency: 'Moneda', meal_plan_id: 'Plan de comidas',
            date_from: 'Desde', date_to: 'Hasta', source_type: 'Origen', optimization_mode: 'Optimización',
            free_text_name: 'Artículo', ingredient_id: 'Ingrediente', estimated_price: 'Precio estimado',
            actual_price: 'Precio real', unit_price: 'Precio unitario', expiry_date: 'Vencimiento'
        };
        all('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea', container).forEach(function (field) {
            if (field.labels && field.labels.length) { return; }
            var label = field.previousElementSibling;
            var existing = label && label.matches('label');
            if (!existing && !names[field.name]) { return; }
            if (!field.id) { field.id = 'dialog-field-' + (++counter); }
            if (!existing) {
                label = document.createElement('label'); label.textContent = names[field.name];
                field.before(label);
            }
            label.htmlFor = field.id;
        });
    }
    function modal(root, selector, label, options) {
        var form = root.querySelector(selector);
        if (!form) { return null; }
        options = options || {};
        var originalParent = form.parentElement;
        var dialog = document.createElement('dialog');
        dialog.className = 'panel-dialog'; dialog.id = 'dialog-' + (++counter);
        var header = document.createElement('div'); header.className = 'dialog-header';
        var title = document.createElement('h2'); title.id = dialog.id + '-title'; title.textContent = label;
        var closeButton = button('×', 'dialog-close');
        closeButton.title = 'Cerrar'; closeButton.setAttribute('aria-label', 'Cerrar');
        header.append(title, closeButton); dialog.appendChild(header);
        dialog.setAttribute('aria-labelledby', title.id);
        var message = document.createElement('div');
        message.className = 'alert'; message.hidden = true; message.setAttribute('role', 'status');
        dialog.appendChild(message);
        var body = document.createElement('div'); body.className = 'dialog-body'; dialog.appendChild(body);
        // Keep the original form nodes and data attributes: existing CRUD handlers stay attached.
        if (options.wrapper) {
            var wrapper = form.closest(options.wrapper);
            body.appendChild(wrapper);
            var wrapperTitle = wrapper.querySelector('h2');
            if (wrapperTitle) {
                wrapperTitle.parentElement.hidden = true;
                title.replaceWith(wrapperTitle); wrapperTitle.id = title.id;
            }
        } else {
            var preceding = [], sibling = form.previousElementSibling;
            while (sibling && !sibling.matches('form, hr, .web-tools, table, .panel-actions')) {
                preceding.unshift(sibling);
                if (sibling.matches('h2,h3')) { break; }
                sibling = sibling.previousElementSibling;
            }
            if (preceding.length && preceding[0].matches('h2,h3')) {
                var heading = preceding.shift();
                title.replaceWith(heading); heading.id = title.id; heading.style.margin = '0';
                preceding.forEach(function (node) { body.appendChild(node); });
            }
            body.appendChild(form);
        }
        labelFields(body);
        root.appendChild(dialog);
        var trigger = button('+ ' + label);
        trigger.setAttribute('aria-haspopup', 'dialog'); trigger.setAttribute('aria-controls', dialog.id);
        if (!options.noTrigger) { (options.local ? originalParent : toolbar(root)).appendChild(trigger); }
        trigger.addEventListener('click', function () {
            if (options.open) { options.open(); }
            else {
                var reset = all('button[type="button"]', form).find(function (item) {
                    return Array.from(item.attributes).some(function (attr) { return /^data-.*-reset$/.test(attr.name); });
                });
                if (reset) { reset.click(); }
            }
            message.hidden = true;
            reveal(form);
        });
        closeButton.addEventListener('click', function () { dialog.close(); });
        dialog.addEventListener('close', function () {
            if (options.closed) { options.closed(); }
            if (dialog.ccReturnFocus && dialog.ccReturnFocus.isConnected) { dialog.ccReturnFocus.focus(); }
        });
        dialog.addEventListener('click', function (event) {
            var target = event.target.closest('button');
            if (target && target.type !== 'submit' && /^(Cancelar|Cerrar)$/.test(target.textContent.trim())) {
                dialog.close();
            }
        });
        // Screen-level validation must remain visible while a modal is on top.
        var alerts = all('.alert', root).filter(function (el) { return !el.closest('dialog'); });
        alerts.forEach(function (alert) {
            new MutationObserver(function () {
                if (!dialog.open || alert.style.display === 'none') { return; }
                message.textContent = alert.textContent; message.className = alert.className;
                message.hidden = !alert.textContent.trim();
            }).observe(alert, { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
        });
        dialog.addEventListener('invalid', function () { reveal(form); }, true);
        return dialog;
    }
    function extractForms(root, definitions) {
        if (!root) { return; }
        definitions.forEach(function (definition) { modal(root, definition[0], definition[1], definition[2]); });
        root.classList.add('panel-wide');
        var layout = root.querySelector('.family-layout');
        if (layout) { layout.classList.add('panel-wide'); }
        all(':scope > aside, :scope > .family-layout > aside', root).forEach(function (aside) {
            if (!aside.querySelector('form, [data-recipes-detail], [data-purchase-detail]')) { aside.hidden = true; }
        });
    }
    function groupSelector(root, selector) {
        var select = root && root.querySelector(selector);
        if (!select) { return; }
        var line = document.createElement('label'); line.className = 'panel-group';
        var text = document.createElement('span'); text.textContent = 'Hogar';
        line.append(text, select); root.prepend(line);
    }
    function sectionLinks() {
        var path = window.location.pathname;
        var groups = [
            [['Mi cocina', '/web/stock'], ['Catálogo', '/web/catalog'], ['Escáner', '/web/barcode-scanner'], ['Reportes', '/web/reports']],
            [['Mi perfil', '/web/profile-objectives'], ['Notificaciones', '/web/notifications'], ['Suplementos', '/web/supplements'], ['Puesta en marcha', '/web/onboarding']],
            [['Recetas', '/web/recipes'], ['Buscar', '/web/recipe-search'], ['Sugerencias', '/web/recipe-suggestions'], ['Favoritas', '/web/recipe-favorites']],
            [['Listas', '/web/shopping-list'], ['Compra en curso', '/web/shopping-session'], ['Historial', '/web/purchases'], ['Supermercados', '/web/supermarkets'], ['Sucursales', '/web/branches'], ['Medios de pago', '/web/payment-methods']]
        ];
        groups.forEach(function (links) {
            if (!links.some(function (link) { return link[1] === path; })) { return; }
            var nav = document.createElement('nav'); nav.className = 'module-links'; nav.setAttribute('aria-label', 'Vistas');
            links.forEach(function (link) {
                var a = document.createElement('a'); a.textContent = link[0]; a.href = link[1];
                a.setAttribute('data-page-navigation', '');
                if (link[1] === path) { a.setAttribute('aria-current', 'page'); }
                nav.appendChild(a);
            });
            var hero = document.querySelector('.hero'); if (hero) { hero.after(nav); }
        });
    }
    function setup() {
        sectionLinks();
        var profile = document.querySelector('.workspace-profile');
        if (profile) {
            profile.classList.add('panel-wide');
            var profileGrid = profile.querySelector(':scope > div > .profile-grid');
            if (profileGrid) {
                Array.from(profileGrid.children).forEach(function (panel) { profileGrid.before(panel); });
                profileGrid.remove();
            }
            tabs(profile.querySelector(':scope > div'), ['Datos personales', 'Prioridades', 'Objetivos', 'Restricciones', 'Mediciones', 'Consentimientos'],
                ['datos', 'prioridades', 'objetivos', 'restricciones', 'mediciones', 'consentimientos']);
        }
        var stock = document.querySelector('[data-user-stock-locations]');
        if (stock) {
            groupSelector(stock, '[data-stock-group-select]');
            tabs(stock.querySelector('.family-stack'), ['Stock del hogar', 'Movimientos', 'Alertas', 'Reportes', 'Ubicaciones'],
                ['stock', 'movimientos', 'alertas', 'reportes', 'ubicaciones']);
            extractForms(stock, [
                ['[data-stock-item-form]', 'Agregar producto'],
                ['[data-stock-movement-form]', 'Registrar movimiento'],
                ['[data-stock-rule-form]', 'Definir alerta'],
                ['[data-stock-location-form]', 'Crear ubicación']
            ]);
            var request = stock.querySelector('[data-product-request-modal]');
            var stockDialog = stock.querySelector('[data-stock-item-form]').closest('dialog');
            if (request && stockDialog) {
                stockDialog.appendChild(request);
                stock.querySelector('.family-layout > aside').hidden = true;
            }
        }
        var plan = document.querySelector('[data-user-meal-plans]');
        if (plan) {
            groupSelector(plan, '[data-meal-plan-group]');
            tabs(plan.querySelector('.family-stack'), ['Calendario', 'Detalle', 'Compatibilidad', 'Compras', 'Porciones'],
                ['calendario', 'detalle', 'compatibilidad', 'compras', 'porciones']);
            extractForms(plan, [
                ['[data-meal-plan-generate-form]', 'Generar menú'],
                ['[data-meal-plan-form]', 'Crear plan'],
                ['[data-meal-plan-item-form]', 'Agregar comida'],
                ['[data-meal-plan-portion-form]', 'Asignar porción']
            ]);
        }
        var shopping = document.querySelector('[data-user-shopping-lists]');
        if (shopping) {
            groupSelector(shopping, '[data-shopping-list-group]');
            tabs(shopping.querySelector('.family-stack'), ['Mis listas', 'Detalle de la lista'], ['listas', 'detalle']);
            extractForms(shopping, [
                ['[data-shopping-list-generate-plan-form]', 'Generar desde menú'],
                ['[data-shopping-list-generate-history-form]', 'Generar desde historial'],
                ['[data-shopping-list-form]', 'Crear lista'],
                ['[data-shopping-list-item-form]', 'Agregar artículo']
            ]);
        }
        var family = document.querySelector('[data-family-groups]');
        if (family) {
            groupSelector(family, '[data-family-select]');
            tabs(family.querySelector('.family-stack'), ['Mi hogar', 'Miembros', 'Preferencias'], ['hogar', 'miembros', 'preferencias']);
            extractForms(family, [
                ['[data-family-create-form]', 'Crear grupo'],
                ['[data-family-edit-form]', 'Editar grupo', { local: true }],
                ['[data-member-create-form]', 'Agregar miembro'],
                ['[data-invitation-form]', 'Invitar por email'],
                ['[data-invitation-accept-form]', 'Aceptar invitación']
            ]);
            var resend = family.querySelector('[data-invitation-resend]');
            if (resend) { family.querySelector('[data-invitation-form]').closest('dialog').appendChild(resend); }
            if (new URLSearchParams(window.location.search).has('invitation')) { reveal(family.querySelector('[data-invitation-accept-form]')); }
        }
        var recipes = document.querySelector('[data-user-recipes]');
        if (recipes) {
            modal(recipes, '[data-recipes-form]', 'Nueva receta', {
                wrapper: '[data-recipes-form-panel]',
                noTrigger: true,
                open: function () { recipes.querySelector('[data-recipes-new]').click(); },
                closed: function () {
                    recipes.querySelector('[data-recipes-detail]').style.display = 'block';
                    recipes.querySelector('[data-recipes-form-panel]').style.display = 'none';
                }
            });
        }
        var budget = document.querySelector('[data-user-budget]');
        if (budget) {
            var current = budget.querySelector('[data-budget-current]');
            if (current) { current.parentElement.hidden = true; }
            extractForms(budget, [
                ['[data-budget-form]', 'Nuevo presupuesto'],
                ['[data-budget-cat-form]', 'Agregar categoría', { local: true }],
                ['[data-budget-adj-form]', 'Registrar ajuste', { local: true }]
            ]);
        }
        var purchases = document.querySelector('[data-user-purchases]');
        if (purchases) { modal(purchases, '[data-purchases-item-form]', 'Agregar artículo'); }
    }
    window.CCUI = { reveal: reveal, close: close, saved: saved, tabs: tabs, modal: modal };
    document.addEventListener('DOMContentLoaded', setup);
})(window, document);
