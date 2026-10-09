(function (window, document) {
    'use strict';

    function mountPage() {
        var workspace = document.querySelector('[data-admin-ui]');
        if (!workspace || workspace.ccAdminMounted || !window.CCUI) { return; }
        workspace.ccAdminMounted = true;
        var root = workspace.firstElementChild;
        if (!root) { return; }
        var screen = workspace.getAttribute('data-admin-screen') || 'admin';
        var counter = 0;
        root.classList.add('admin-workspace');

        function all(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
        function uid(kind) { return 'admin-' + screen + '-' + kind + '-' + (++counter); }
        function dispose(callback) { if (window.CCPage) { window.CCPage.onDispose(callback); } }
        function heading(element, fallback) {
            var title = element.querySelector('h2,h3,[data-section-form-title]');
            return title ? title.textContent.trim() : fallback;
        }
        function labelFields(element) {
            var names = { id: 'Identificador', name: 'Nombre', lastname: 'Apellido', email: 'Correo electrónico', password: 'Contraseña', password_confirmation: 'Confirmar contraseña',
                code: 'Código', status: 'Estado', type: 'Tipo', symbol: 'Símbolo', description: 'Descripción', notes: 'Notas', barcode: 'Código de barras',
                brand_id: 'Marca', category_id: 'Categoría', ingredient_id: 'Ingrediente', product_id: 'Producto', unit_id: 'Unidad', default_unit_id: 'Unidad base',
                net_quantity: 'Contenido neto', package_unit_id: 'Unidad del contenido', amount: 'Cantidad', quantity: 'Cantidad', price: 'Precio', currency: 'Moneda',
                from_unit_id: 'Unidad de origen', to_unit_id: 'Unidad de destino', factor: 'Factor de conversión', parent_id: 'Categoría superior', image_url: 'URL de imagen',
                image: 'Archivo de imagen', reason: 'Motivo', title: 'Título', content: 'Contenido', source_url: 'URL de origen', external_product_id: 'Producto externo',
                external_sku: 'SKU externo', supermarket_chain_id: 'Supermercado', supermarket_branch_id: 'Sucursal', source_id: 'Fuente', city_id: 'Ciudad',
                address: 'Dirección', amount_per_100g: 'Cantidad por cada 100 g', amount_per_serving: 'Cantidad por porción', barcode_id: 'Código de barras',
                base_unit_id: 'Unidad base', base_url: 'URL base', captured_at: 'Fecha del precio', category: 'Categoría', conversion_factor: 'Factor de conversión',
                cook_time_minutes: 'Tiempo de cocción (minutos)', country: 'País', delay_ms: 'Pausa entre solicitudes (milisegundos)',
                delivery_available: 'Envío disponible', demo_user_id: 'Usuario de demostración', difficulty: 'Dificultad', dry_run: 'Simulación sin guardar',
                equivalence_type: 'Tipo de equivalencia', ingredient_index: 'Posición del ingrediente', is_active: 'Activo', is_generic: 'Genérico',
                is_official: 'Oficial', is_optional: 'Opcional', is_preparation: 'Preparación', is_primary: 'Principal', is_public: 'Público', is_supplement: 'Suplemento',
                last_scraped_at: 'Última importación', latitude: 'Latitud', longitude: 'Longitud', max_pages: 'Máximo de páginas', max_products: 'Máximo de productos',
                nutrient_id: 'Nutriente', opening_hours: 'Horarios de atención', order_level: 'Nivel de orden', order: 'Orden', pickup_available: 'Retiro disponible',
                prep_time_minutes: 'Tiempo de preparación (minutos)', province: 'Provincia', raw_description: 'Descripción original', raw_image_url: 'URL de la imagen original',
                raw_ingredients_json: 'Ingredientes originales (JSON)', raw_steps_json: 'Pasos originales (JSON)', raw_title: 'Título original',
                resolution_notes: 'Notas de resolución', review_notes: 'Notas de revisión', route: 'Ruta', search_term: 'Término de búsqueda',
                serving_size: 'Tamaño de la porción', servings: 'Porciones', sort_order: 'Orden', source_author: 'Autor original', source_ingredient_id: 'Ingrediente de origen',
                source_name: 'Nombre de la fuente', source_site: 'Sitio de origen', source_type: 'Tipo de fuente', source: 'Origen', target_ingredient_id: 'Ingrediente de destino',
                url: 'URL', version_number: 'Número de versión', version: 'Versión', website_url: 'Sitio web' };
            all('input:not([type="hidden"]),select,textarea', element).forEach(function (field) {
                if ((field.labels && field.labels.length) || field.hasAttribute('aria-label') || field.hasAttribute('aria-labelledby')) { return; }
                var previous = field.previousElementSibling;
                var label = previous && previous.matches('label') ? previous : null;
                if (!field.id) { field.id = uid('field'); }
                if (!label) {
                    label = document.createElement('label');
                    label.textContent = names[field.name] || field.getAttribute('placeholder') || field.getAttribute('title') || (field.name || 'Valor').replace(/_/g, ' ');
                    field.before(label);
                }
                label.htmlFor = field.id;
            });
        }

        // A thin adapter around the shared helper. The original nodes stay under
        // their feature root, so both bound and delegated CRUD listeners survive.
        function modal(feature, target, label, options) {
            var element = typeof target === 'string' ? feature.querySelector(target) : target;
            if (!element) { return null; }
            if (element.closest('dialog')) { return element.closest('dialog'); }
            if (!window.HTMLDialogElement || !window.HTMLDialogElement.prototype.showModal) { return null; }
            options = options || {};
            var marker = uid('modal');
            element.setAttribute('data-admin-modal', marker);
            var dialog = window.CCUI.modal(feature, '[data-admin-modal="' + marker + '"]', label, options);
            if (!dialog) { return null; }
            dialog.classList.add('admin-dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('data-admin-dialog', '');
            labelFields(dialog);
            // Most existing forms only had Limpiar. Cancelar dismisses the dialog
            // without synthesizing a reset, submit, API call or successful save.
            if (!all('button', dialog).some(function (button) { return button.textContent.trim() === 'Cancelar'; })) {
                var footer = document.createElement('div'); footer.className = 'admin-form-actions';
                var cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn-ghost'; cancel.textContent = 'Cancelar';
                footer.appendChild(cancel); dialog.appendChild(footer);
            }
            dialog.addEventListener('cancel', function (event) {
                if (event.defaultPrevented) { return; }
                event.preventDefault(); dialog.close();
            });
            return dialog;
        }

        function tabs(container, items, options) {
            options = options || {};
            if (!items.length || container.ccAdminTabs) { return; }
            container.ccAdminTabs = true;
            var bar = options.bar || document.createElement('div');
            bar.classList.add('panel-tabs', 'admin-tabs'); bar.setAttribute('role', 'tablist');
            bar.setAttribute('aria-label', options.label || 'Secciones de administración');
            if (!options.bar) { container.before(bar); }
            var current = 0;
            var changingFromReveal = false;
            items.forEach(function (item, index) {
                var panel = item.panel;
                if (!panel.id) { panel.id = uid('panel'); }
                panel.classList.add('admin-panel'); panel.setAttribute('role', 'tabpanel'); panel.tabIndex = 0;
                var button = item.button || document.createElement('button'); item.button = button;
                if (!button.id) { button.id = uid('tab'); }
                button.type = 'button'; button.classList.add('panel-tab'); button.setAttribute('role', 'tab');
                button.setAttribute('aria-controls', panel.id);
                if (!options.shared) { panel.setAttribute('aria-labelledby', button.id); }
                if (!options.bar) { button.textContent = item.label; bar.appendChild(button); }
                if (button.classList.contains('active')) { current = index; }
                button.addEventListener('click', function () { select(index, !changingFromReveal); });
                button.addEventListener('keydown', function (event) {
                    var next;
                    if (event.key === 'ArrowRight') { next = (index + 1) % items.length; }
                    if (event.key === 'ArrowLeft') { next = (index + items.length - 1) % items.length; }
                    if (event.key === 'Home') { next = 0; }
                    if (event.key === 'End') { next = items.length - 1; }
                    if (next !== undefined) { event.preventDefault(); items[next].button.click(); items[next].button.focus(); }
                });
                panel.ccSelect = function (focus) {
                    if (options.native && current !== index) {
                        changingFromReveal = !focus; button.click(); changingFromReveal = false;
                    } else { select(index, !!focus); }
                };
            });
            function select(index, focus) {
                current = index;
                items.forEach(function (item, i) {
                    var selected = index === i;
                    item.button.setAttribute('aria-selected', String(selected)); item.button.tabIndex = selected ? 0 : -1;
                    item.button.classList.toggle('active', selected);
                    if (!options.shared) {
                        item.panel.hidden = !selected;
                        if (options.native) { item.panel.style.display = selected ? '' : 'none'; }
                    }
                });
                if (options.shared) { items[index].panel.setAttribute('aria-labelledby', items[index].button.id); }
                if (focus) {
                    items[index].button.focus();
                    if (!options.shared) { window.history.replaceState(window.history.state, '', '#' + items[index].panel.id); }
                }
            }
            var initial = items.findIndex(function (item) { return '#' + item.panel.id === window.location.hash; });
            select(initial >= 0 ? initial : current, false);
            if (options.native && initial >= 0) {
                // Native handlers bind later in DOMContentLoaded and may load
                // their data only on click (for example login audit entries).
                var initialLoad = window.setTimeout(function () {
                    if (!container.isConnected) { return; }
                    changingFromReveal = true; items[initial].button.click(); changingFromReveal = false;
                }, 0);
                dispose(function () { window.clearTimeout(initialLoad); });
            }
            if (options.shared) { items[0].panel.ccSelect = function (focus) { select(current, !!focus); }; }
            var onHash = function () {
                var index = items.findIndex(function (item) { return '#' + item.panel.id === window.location.hash; });
                if (index >= 0) { items[index].panel.ccSelect(false); }
            };
            window.addEventListener('hashchange', onHash);
            dispose(function () { window.removeEventListener('hashchange', onHash); });
        }

        function nativeTabs(tabAttribute, panelAttribute, sharedSelector) {
            var buttons = all('[' + tabAttribute + ']');
            if (!buttons.length) { return false; }
            var shared = sharedSelector ? root.querySelector(sharedSelector) : null;
            var items = buttons.map(function (button) {
                return { button: button, panel: shared || root.querySelector('[' + panelAttribute + '="' + button.getAttribute(tabAttribute) + '"]') };
            });
            if (items.some(function (item) { return !item.panel; })) { return false; }
            tabs(buttons[0].parentElement, items, { bar: buttons[0].parentElement, native: true, shared: !!shared });
            return true;
        }

        var hasNativeTabs = nativeTabs('data-units-tab', 'data-units-panel') || nativeTabs('data-audit-tab', 'data-audit-panel') || nativeTabs('data-health-tab', '', '.rbac-layout');

        // A panel can keep its result area after its form and heading move into
        // a dialog. Remember the section name without leaving an empty tab.
        all('.panel').forEach(function (panel) { panel.ccAdminLabel = heading(panel, ''); });

        function contextModal(selector, label) {
            var element = root.querySelector(selector);
            if (element) { modal(root, element.closest('.panel'), label, { noTrigger: true }); }
        }
        contextModal('[data-candidate-detail]', 'Revisar producto importado');
        contextModal('[data-product-request-approve-form]', 'Revisar solicitud de producto');
        contextModal('[data-alert-resolve-form]', 'Resolver alerta');

        var recipeEditor = root.querySelector('[data-recipes-adm-form-panel]');
        if (recipeEditor) {
            modal(root, recipeEditor, 'Receta oficial', { noTrigger: true, closed: function () {
                // Use the real cancel handler to restore its detail/form state.
                var cancel = root.querySelector('[data-recipes-adm-cancel]');
                if (cancel && recipeEditor.style.display !== 'none') { cancel.click(); }
            } });
        }

        var imported = root.querySelector('[data-import-candidates-edit-form]');
        if (imported) {
            var review = document.createElement('div'); review.setAttribute('data-admin-recipe-review', '');
            var blocks = ['[data-import-candidate-detail]', '[data-import-candidates-detail]', '[data-import-candidates-edit-form]', '[data-import-candidates-map-form]', '[data-import-candidates-reject-form]'];
            var panels = [];
            blocks.forEach(function (selector) {
                var element = root.querySelector(selector);
                var panel = element && element.closest('.panel');
                if (panel && panels.indexOf(panel) === -1) { panels.push(panel); }
            });
            root.appendChild(review);
            panels.forEach(function (panel) { review.appendChild(panel); });
            var reviewDialog = modal(root, review, 'Revisar receta importada', { noTrigger: true });
            if (reviewDialog) { tabs(review, panels.map(function (panel) { return { panel: panel, label: heading(panel, 'Detalle') }; })); }
        }

        // Import actions are UI forms implemented without a <form> element.
        ['[data-import-url]', '[data-importtxt-body]', '[data-ai-test-panel]'].forEach(function (selector) {
            var element = root.querySelector(selector);
            if (element) { var panel = element.closest('.panel'); modal(root, panel, heading(panel, 'Importar')); }
        });

        all('form').forEach(function (form) {
            if (form.closest('dialog')) { return; }
            var title = form.previousElementSibling;
            var label = title && title.matches('h2,h3,[data-section-form-title]') ? title.textContent.trim() : heading(form.closest('.panel') || form.parentElement, 'Formulario');
            var conditional = false;
            for (var owner = form.parentElement; owner && owner !== root; owner = owner.parentElement) {
                if (owner.hidden || owner.style.display === 'none') { conditional = true; }
            }
            modal(root, form, label, { local: conditional });
        });
        var branchForm = root.querySelector('[data-branches-form]');
        var branchMap = root.querySelector('[data-branches-map-preview]');
        if (branchForm && branchMap && branchForm.closest('dialog')) { branchForm.after(branchMap); }
        all('button').filter(function (button) {
            return !button.closest('dialog') && Array.from(button.attributes).some(function (attribute) { return /^data-.*-restore-submit$/.test(attribute.name); });
        }).forEach(function (button) {
            var block = button.closest('.admin-tools');
            if (block) { modal(root, block, block.previousElementSibling ? block.previousElementSibling.textContent.trim() : 'Restaurar'); }
        });

        // Keep filter controls and a dynamically rendered list in the same panel.
        ['[data-settings-container]', '[data-ff-container]'].forEach(function (selector) {
            var list = root.querySelector(selector);
            if (!list) { return; }
            var filter = root.querySelector(':scope > .panel');
            if (filter) { filter.appendChild(list); }
        });

        all('.panel').filter(function (panel) { return !panel.closest('dialog'); }).forEach(function (panel) {
            var content = panel.cloneNode(true);
            all('h2,h3,hr', content).forEach(function (element) { element.remove(); });
            var hasDynamicTarget = all('*', content).some(function (element) {
                return Array.from(element.attributes).some(function (attribute) { return attribute.name.indexOf('data-') === 0; });
            });
            if (!content.textContent.trim() && !content.querySelector('input,select,textarea,button,table') && !hasDynamicTarget) {
                panel.hidden = true; panel.ccAdminEmpty = true;
            }
        });
        if (!hasNativeTabs) {
            var panels = all('.panel').filter(function (panel) {
                return !panel.closest('dialog') && !panel.parentElement.closest('.panel') && !panel.ccAdminEmpty;
            });
            var seen = [];
            var items = [];
            panels.forEach(function (panel) {
                // Preserve wrappers whose visibility is controlled by a feature
                // handler (for example document sections/versions).
                var block = panel;
                for (var parent = panel.parentElement; parent && parent !== root; parent = parent.parentElement) {
                    if (Array.from(parent.attributes).some(function (attribute) { return attribute.name.indexOf('data-') === 0; })) { block = parent; }
                }
                if (seen.indexOf(block) !== -1) { return; }
                seen.push(block);
                var fallback = panel.tagName === 'ASIDE' || all('[data-recipes-adm-detail],[data-import-detail],[data-importtxt-detail]', panel).length ? 'Detalle' : (items.length ? 'Consulta' : 'Listado');
                items.push({ panel: block, label: panel.ccAdminLabel || heading(panel, fallback) });
            });
            if (!items.length) {
                var fallbackPanel = document.createElement('article'); fallbackPanel.className = 'panel';
                Array.from(root.children).filter(function (element) { return !element.matches('dialog,.alert,.panel-actions'); }).forEach(function (element) { fallbackPanel.appendChild(element); });
                root.appendChild(fallbackPanel); items.push({ panel: fallbackPanel, label: 'Resumen' });
            }
            var stack = document.createElement('div'); stack.className = 'admin-panel-stack'; root.appendChild(stack);
            items.forEach(function (item) { stack.appendChild(item.panel); });
            tabs(stack, items);
        }
        all('.rbac-layout,.grid').forEach(function (layout) { if (!layout.children.length) { layout.hidden = true; } });

        // Existing feature handlers own these hero links. Other screens forward
        // to an existing create trigger; no mutation is inferred from link text.
        var ownedHero = ['barcodes', 'brands', 'ingredient-categories', 'food-tags', 'nutrients', 'ingredients', 'product-categories', 'products', 'recipe-scraping'];
        var primary = document.querySelector('[data-screen-primary-action]');
        var secondary = document.querySelector('[data-screen-secondary-action]');
        var createTrigger = root.querySelector(':scope > .panel-actions > [aria-haspopup="dialog"]');
        if (screen === 'supermarket-scraping') {
            var jobForm = root.querySelector('[data-scraping-job-form]');
            var jobDialog = jobForm && jobForm.closest('dialog');
            createTrigger = jobDialog && root.querySelector('[aria-controls="' + jobDialog.id + '"]');
        }
        var createButton = screen === 'official-recipes' ? root.querySelector('[data-recipes-adm-new]') : (screen === 'thesis-docs' ? root.querySelector('[data-tdoc-new]') : null);
        if (primary && primary.getAttribute('href') === '#') {
            var target = createButton || createTrigger;
            if (ownedHero.indexOf(screen) >= 0) {
                // Keep the real reset/create handler and remove its duplicate.
                if (createTrigger) { createTrigger.hidden = true; }
            } else if (target) {
                primary.setAttribute('aria-haspopup', 'dialog');
                if (target.getAttribute('aria-controls')) { primary.setAttribute('aria-controls', target.getAttribute('aria-controls')); }
                primary.addEventListener('click', function (event) { event.preventDefault(); target.click(); });
                target.hidden = true;
            } else { primary.hidden = true; }
        }
        if (secondary && secondary.getAttribute('href') === '#' && screen !== 'recipe-scraping') { secondary.hidden = true; }

        // Explicit hooks for handlers that generate their own editor (settings,
        // for example). Do not infer mutation success from a global alert.
        window.CCAdminUI = { modal: modal, tabs: tabs, labelFields: labelFields, reveal: window.CCUI.reveal, close: window.CCUI.close, saved: window.CCUI.saved };
    }
    if (window.CCPage) { window.CCPage.register('admin-panel-ui', mountPage); }
    else { document.addEventListener('DOMContentLoaded', mountPage); }
})(window, document);
