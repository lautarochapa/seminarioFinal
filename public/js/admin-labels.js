(function (window) {
    'use strict';

    // Presentation only: API values, form values and stored records keep their codes.
    var groups = {
        status: {
            active: 'Activo', inactive: 'Inactivo', pending: 'Pendiente', pending_review: 'Pendiente de revisión',
            approved: 'Aprobado', rejected: 'Rechazado', matched: 'Vinculado', created: 'Creado',
            draft: 'Borrador', published: 'Publicado', archived: 'Archivado', open: 'Abierto', resolved: 'Resuelto',
            queued: 'En cola', running: 'En ejecución', completed: 'Completado', failed: 'Fallido',
            cancelled: 'Cancelado', cancel_requested: 'Cancelación solicitada', parsed: 'Analizado',
            converted: 'Convertido', recipe_created: 'Receta creada', error: 'Error', success: 'Correcto',
            unknown: 'Desconocido', available: 'Disponible', unavailable: 'No disponible', outdated: 'Desactualizado',
            ok: 'Correcto', skipped: 'Omitido', confirmed: 'Confirmado', enabled: 'Activado', disabled: 'Desactivado',
            missing: 'Faltante', unmatched: 'Sin vincular', retrying: 'Reintentando', expired: 'Vencido',
            not_started: 'Sin iniciar', processing: 'En proceso', stopped: 'Detenido', paused: 'En pausa'
        },
        role: {
            user: 'Usuario', catalog_admin: 'Administrador de catálogo', recipe_admin: 'Administrador de recetas',
            super_admin: 'Superadministrador', admin: 'Administrador', professional: 'Profesional',
            teacher: 'Docente', student: 'Estudiante', nutritionist: 'Nutricionista', member: 'Integrante', owner: 'Responsable'
        },
        severity: { low: 'Baja', medium: 'Media', high: 'Alta', critical: 'Crítica', warning: 'Advertencia', info: 'Información', debug: 'Depuración', error: 'Error' },
        source: {
            official: 'Oficial', user: 'Usuario', imported: 'Importado', scraped: 'Importación web', manual: 'Manual',
            web_scraper: 'Importación web', scraping: 'Importación web', api: 'API', product_prices: 'Precios de productos',
            recipe_scraping: 'Importación de recetas', admin: 'Administración', system: 'Sistema', barcode: 'Código de barras'
        },
        unit_type: { mass: 'Masa', weight: 'Peso', volume: 'Volumen', count: 'Cantidad', household: 'Medida casera', package: 'Envase', energy: 'Energía' },
        product_request_source: { user_request: 'Solicitud del usuario', barcode: 'Código de barras', stock: 'Inventario', shopping_list: 'Lista de compras' },
        price_source: { manual: 'Manual', scraper: 'Importación web', demo: 'Demostración', global_catalog: 'Catálogo global' },
        food_tag_type: {
            nutrition: 'Nutrición', restriction: 'Restricción', diet: 'Dieta',
            INGREDIENT_FORM: 'Forma del ingrediente', DIETARY_NUTRITIONAL: 'Alimentación y nutrición'
        },
        recipe_tag_type: { meal_type: 'Tipo de comida', diet: 'Dieta', cuisine: 'Cocina', season: 'Temporada', other: 'Otro' },
        difficulty: { easy: 'Fácil', medium: 'Media', hard: 'Difícil' },
        settingGroup: { auth: 'Autenticación', app: 'Aplicación', ai: 'Inteligencia artificial', mail: 'Correo', scraping: 'Importación web', module: 'Módulos', general: 'General' },
        provider: { fake: 'Simulado', FakeAiSuggestionProvider: 'Proveedor simulado' },
        permission: {
            'security.users.read': 'Ver usuarios', 'security.users.write': 'Administrar usuarios', 'security.roles.read': 'Ver roles', 'security.roles.write': 'Administrar roles',
            'security.permissions.read': 'Ver permisos', 'audit.read': 'Ver auditoría', 'profile.manage': 'Administrar perfil', 'family.manage': 'Administrar grupo familiar',
            'stock.manage': 'Administrar inventario', 'recipes.use': 'Usar recetas', 'meal_plans.manage': 'Administrar planes de comidas', 'shopping.manage': 'Administrar compras',
            'budget.manage': 'Administrar presupuesto', 'reports.read': 'Ver reportes', 'notifications.manage': 'Administrar notificaciones', 'supplements.manage': 'Administrar suplementos',
            'professional.users.read': 'Ver usuarios vinculados', 'professional.plans.write': 'Administrar planes profesionales', 'catalog.manage': 'Administrar catálogo',
            'scraped_products.review': 'Revisar productos importados', 'supermarkets.manage': 'Administrar supermercados y precios', 'scraping.manage': 'Administrar importación web',
            'recipes.manage': 'Administrar recetas oficiales', 'thesis_documents.read': 'Ver documentos de tesis', 'thesis_documents.write': 'Administrar documentos de tesis',
            'demo_scenarios.read': 'Ver demostraciones', 'settings.manage': 'Administrar configuración', 'feature_flags.manage': 'Administrar funciones'
        },
        reportMetric: {
            total_logins: 'Accesos registrados', unique_users: 'Usuarios distintos', total_pending: 'Pendientes', total: 'Total', limit: 'Límite',
            total_cook_events: 'Preparaciones registradas', total_servings: 'Porciones totales', total_chains: 'Cadenas de supermercados', total_alerts: 'Alertas totales', failed_jobs: 'Ejecuciones fallidas',
            user_id: 'Usuario', name: 'Nombre', email: 'Correo electrónico', role: 'Rol', last_login_at: 'Último acceso', actions_count: 'Acciones', status: 'Estado',
            product_id: 'Producto', brand: 'Marca', category: 'Categoría', source: 'Origen', created_at: 'Creado', submitted_by: 'Enviado por', recipe_id: 'Receta', title: 'Título',
            supermarket: 'Supermercado', error_type: 'Tipo de error', message: 'Mensaje', url: 'URL', occurred_at: 'Fecha', retries: 'Reintentos', product_name: 'Producto',
            price_before: 'Precio anterior', price_after: 'Precio actual', variation_pct: 'Variación porcentual', detected_at: 'Detectado', rank: 'Posición', recipe_name: 'Receta',
            times_cooked: 'Veces preparada', avg_rating: 'Valoración promedio', branch: 'Sucursal', products_total: 'Productos', prices_ok: 'Precios vigentes', prices_outdated: 'Precios desactualizados',
            last_scraped_at: 'Última importación web', freshness_pct: 'Actualización porcentual', source_name: 'Fuente', source_code: 'Código de fuente', source_site: 'Sitio de origen',
            raw_name: 'Nombre original', raw_brand: 'Marca original', raw_price: 'Precio original', raw_title: 'Título original', supermarket_product_id: 'Publicación', chain_name: 'Cadena',
            current_price: 'Precio actual', previous_price: 'Precio anterior', variation: 'Variación', variation_percent: 'Variación porcentual', currency: 'Moneda', chain_id: 'Cadena',
            total_products: 'Productos totales', with_valid_price: 'Con precio vigente', with_expired_price: 'Con precio vencido', without_price: 'Sin precio', logins: 'Accesos', day: 'Día', count: 'Cantidad'
        },
        action: {
            action: 'Acción', update: 'Actualizar', rejected: 'Rechazar',
            'ai.test_suggestion': 'Probar sugerencia de IA', 'budget_movement.adjustment': 'Registrar ajuste de presupuesto',
            'family_group.invitation.accept': 'Aceptar invitación al grupo familiar', 'family_group.invitation.create': 'Crear invitación al grupo familiar',
            'family_group.member.add': 'Agregar integrante al grupo familiar', 'family_group.member.remove': 'Quitar integrante del grupo familiar',
            'family_group.member.update': 'Actualizar integrante del grupo familiar', 'family_group.preferences.update': 'Actualizar preferencias del grupo familiar',
            'meal_plan.preferences.update': 'Actualizar preferencias del plan', 'notification.read_all': 'Marcar todas las notificaciones como leídas',
            'purchase.stock_added': 'Agregar compra al inventario', 'role.permission.assign': 'Asignar permiso al rol', 'role.permission.remove': 'Quitar permiso del rol',
            'user.role.assign': 'Asignar rol al usuario', 'user.role.remove': 'Quitar rol del usuario',
            'shopping_list.generated_from_recipe': 'Generar lista de compras desde receta', 'stock-item.expired-processed': 'Procesar vencimientos del inventario',
            report_export_requested: 'Solicitar exportación de reporte', import_candidate_apply_suggestions_bulk: 'Aplicar sugerencias de importación en lote',
            import_candidate_approve_bulk: 'Aprobar importaciones en lote', import_candidate_ingredient_mapped: 'Vincular ingrediente de importación',
            import_candidate_recipe_created: 'Crear receta desde importación', import_candidate_suggestions_applied: 'Aplicar sugerencias de importación',
            import_candidate_suggestions_recalculated: 'Recalcular sugerencias de importación', import_candidate_suggestions_recalculated_bulk: 'Recalcular sugerencias de importación en lote',
            meal_plan_incompatibilities_checked: 'Revisar incompatibilidades del plan'
        },
        resource: {
            ai_foundation: 'Base de IA', body_measurements: 'Mediciones corporales', brands: 'Marcas', budget_alerts: 'Alertas de presupuesto', budget_categories: 'Categorías de presupuesto',
            budget_movements: 'Movimientos de presupuesto', budgets: 'Presupuestos', cities: 'Ciudades', demo_scenarios: 'Escenarios de demostración',
            family_group_invitations: 'Invitaciones al grupo familiar', family_group_members: 'Integrantes del grupo familiar', family_group_preferences: 'Preferencias del grupo familiar', family_groups: 'Grupos familiares',
            feature_flags: 'Funciones', food_tags: 'Etiquetas alimentarias', imported_recipe_candidates: 'Importaciones de recetas', ingredient_categories: 'Categorías de ingredientes',
            ingredient_equivalences: 'Equivalencias de ingredientes', ingredient_nutrients: 'Nutrientes de ingredientes', ingredients: 'Ingredientes', meal_plan_item_portions: 'Porciones del plan',
            meal_plan_items: 'Comidas del plan', meal_plan_preferences: 'Preferencias del plan', meal_plans: 'Planes de comidas', meal_types: 'Tipos de comida', notification_preferences: 'Preferencias de notificaciones',
            notifications: 'Notificaciones', nutrients: 'Nutrientes', objectives: 'Objetivos', payment_methods: 'Métodos de pago', price_refresh_requests: 'Solicitudes de actualización de precios',
            product_barcodes: 'Códigos de barras', product_categories: 'Categorías de productos', product_images: 'Imágenes de productos', product_nutrients: 'Nutrientes de productos',
            product_reports: 'Reportes de productos', product_requests: 'Solicitudes de productos', products: 'Productos', professional_user_links: 'Vínculos profesionales', promotions: 'Promociones',
            purchase_items: 'Artículos de compra', purchases: 'Compras', recipe_categories: 'Categorías de recetas', recipe_cook_logs: 'Preparaciones de recetas', recipe_cost_snapshots: 'Costos de recetas',
            recipe_favorites: 'Recetas favoritas', recipe_ingredients: 'Ingredientes de recetas', recipe_nutrition: 'Nutrición de recetas', recipe_steps: 'Pasos de recetas', recipe_tags: 'Etiquetas de recetas', recipes: 'Recetas',
            report_exports: 'Exportaciones de reportes', roles: 'Roles', scraped_product_candidates: 'Productos importados por revisar', scraping_alerts: 'Alertas de importación web', scraping_jobs: 'Ejecuciones de importación web',
            shopping_list_items: 'Artículos de listas de compras', shopping_lists: 'Listas de compras', shopping_sessions: 'Sesiones de compra', stock_items: 'Artículos del inventario', stock_locations: 'Ubicaciones del inventario',
            supermarket_branches: 'Sucursales', supermarket_chains: 'Cadenas de supermercados', supermarket_product_prices: 'Precios de supermercados', supermarket_products: 'Publicaciones en supermercados',
            supplement_logs: 'Consumos de suplementos', supplement_schedules: 'Programación de suplementos', system_settings: 'Configuración del sistema', thesis_comments: 'Comentarios de tesis',
            thesis_document_sections: 'Secciones de documentos de tesis', thesis_document_versions: 'Versiones de documentos de tesis', thesis_documents: 'Documentos de tesis', unit_conversions: 'Conversiones de unidades',
            unit_measures: 'Unidades de medida', user_consents: 'Consentimientos de usuarios', user_objectives: 'Objetivos de usuarios', user_payment_methods: 'Métodos de pago de usuarios',
            user_priority_settings: 'Prioridades de usuarios', user_profile: 'Perfil de usuario', user_supplements: 'Suplementos de usuarios', users: 'Usuarios'
        },
        failureReason: { AUTH_INVALID_CREDENTIALS: 'Credenciales incorrectas', AUTH_USER_INACTIVE: 'Usuario inactivo' },
        alertType: {
            source_unavailable: 'Fuente no disponible', scraping_failed: 'Importación fallida', recipe_scraping_failed: 'Importación de recetas fallida',
            source_inactive: 'Fuente inactiva', offset_not_advancing: 'La paginación no avanza', repeated_empty_pages: 'Páginas vacías repetidas',
            rate_limited: 'Límite de solicitudes alcanzado', http_error: 'Error HTTP', unexpected_error: 'Error inesperado',
            time_budget_exceeded: 'Tiempo máximo alcanzado', blocked: 'Acceso bloqueado', listing_fetch_failed: 'No se pudo obtener el listado',
            max_items_reached: 'Máximo de elementos alcanzado', max_pages_reached: 'Máximo de páginas alcanzado'
        }
    };

    // Only known event prefixes and verbs are presented as labels. Unknown audit
    // identifiers remain intact, as do the original JSON details of every event.
    var actionSubjects = {
        'body-measurement': 'medición corporal', branch: 'sucursal', budget: 'presupuesto', budget_alert: 'alerta de presupuesto', budget_category: 'categoría de presupuesto',
        chain: 'cadena de supermercados', city: 'ciudad', demo_scenario: 'escenario de demostración', family_group: 'grupo familiar', 'feature-flag': 'función',
        import_candidate: 'importación de receta', meal_plan: 'plan de comidas', meal_plan_item: 'comida del plan', meal_plan_portion: 'porción del plan', meal_type: 'tipo de comida',
        notification: 'notificación', notification_preference: 'preferencias de notificaciones', payment_method: 'método de pago', price: 'precio',
        product_barcode: 'código de barras', product_image: 'imagen de producto', product_report: 'reporte de producto', 'professional-link': 'vínculo profesional',
        'professional-meal-plan': 'plan profesional', promotion: 'promoción', purchase: 'compra', purchase_item: 'artículo de compra', 'recipe-cost': 'costo de receta',
        'recipe-nutrition': 'nutrición de receta', recipe: 'receta', recipe_favorite: 'receta favorita', recipe_import: 'importación de receta', recipe_import_text: 'importación de receta por texto',
        recipe_scraping_job: 'ejecución de importación de recetas', 'role.admin': 'rol', shopping_list: 'lista de compras', supermarket_product: 'publicación de supermercado',
        supplement_log: 'consumo de suplemento', supplement_schedule: 'programación de suplemento', 'system-setting': 'configuración', thesis_comment: 'comentario de tesis',
        thesis_document: 'documento de tesis', thesis_section: 'sección de tesis', thesis_version: 'versión de tesis', 'user-consents': 'consentimientos',
        'user.admin': 'usuario', user_payment_method: 'método de pago de usuario', user_supplement: 'suplemento de usuario'
    };
    var actionVerbs = {
        create: 'Crear', created: 'Crear', update: 'Actualizar', updated: 'Actualizar', delete: 'Eliminar', deleted: 'Eliminar',
        restore: 'Restaurar', restored: 'Restaurar', deactivated: 'Desactivar', added: 'Agregar', removed: 'Quitar', read: 'Registrar lectura de',
        approved: 'Aprobar', rejected: 'Rechazar', resolved: 'Resolver', generated: 'Generar', regenerated: 'Regenerar', revoked: 'Revocar', cancel: 'Cancelar', confirm: 'Confirmar',
        recalculated: 'Recalcular', branched: 'Crear variante de', cooked: 'Preparar', failed: 'Registrar fallo de', retried: 'Reintentar', shared: 'Compartir', unshared: 'Dejar de compartir'
    };
    var screens = {
        dashboard: 'Resumen', users: 'Usuarios', 'roles-permissions': 'Roles y permisos', ingredients: 'Ingredientes', 'ingredient-categories': 'Categorías de ingredientes',
        nutrients: 'Nutrientes', 'units-conversions': 'Unidades y conversiones', equivalences: 'Equivalencias', products: 'Productos', brands: 'Marcas', barcodes: 'Códigos de barras',
        supermarkets: 'Supermercados', branches: 'Sucursales', prices: 'Precios', 'supermarket-products': 'Publicaciones de supermercados',
        'supermarket-scraping': 'Importación de productos', 'scraped-products': 'Revisión de productos importados', 'official-recipes': 'Recetas oficiales',
        'imported-recipes': 'Recetas importadas', 'recipe-scraping': 'Importación de recetas', 'recipe-tags': 'Etiquetas de recetas', 'scraping-alerts': 'Alertas de importación web',
        'admin-reports': 'Reportes administrativos', audit: 'Auditoría', settings: 'Configuración', 'feature-flags': 'Funciones', 'ai-foundation': 'Base de IA',
        'product-requests': 'Solicitudes de productos', 'product-reports': 'Reportes de productos', cities: 'Ciudades', 'recipe-categories': 'Categorías de recetas',
        'recipe-import': 'Importación de receta por URL', 'recipe-import-text': 'Importación de receta por texto', 'food-tags': 'Etiquetas alimentarias',
        'product-categories': 'Categorías de productos', 'price-refresh-requests': 'Actualización de precios', 'meal-types': 'Tipos de comida', objectives: 'Objetivos',
        'health-preferences': 'Restricciones, alergias y condiciones', stock: 'Inventario', recipes: 'Recetas', planning: 'Planificación', 'shopping-list': 'Lista de compras',
        budget: 'Presupuesto', reports: 'Reportes', 'family-group': 'Grupo familiar', 'profile-objectives': 'Perfil y objetivos', 'professional-permissions': 'Permisos profesionales',
        catalog: 'Catálogo', 'barcode-scanner': 'Lector de códigos de barras', 'recipe-search': 'Buscador de recetas', 'recipe-suggestions': 'Sugerencias de recetas',
        'recipe-favorites': 'Recetas favoritas', 'shopping-session': 'Sesión de compra', purchases: 'Compras', supplements: 'Suplementos', notifications: 'Notificaciones', onboarding: 'Configuración inicial'
    };

    function permissionLabel(key) {
        var match = key.match(/^web\.(admin|user)\.([a-z-]+)$/);
        if (!match || !Object.prototype.hasOwnProperty.call(screens, match[2])) return key;
        return 'Acceder a ' + screens[match[2]] + (match[1] === 'admin' ? ' (administración)' : ' (usuario)');
    }

    function actionLabel(key) {
        var match = key.match(/^(.+)[._]([a-z]+)$/);
        if (!match) return key;
        var subject = Object.prototype.hasOwnProperty.call(actionSubjects, match[1]) && actionSubjects[match[1]];
        var verb = Object.prototype.hasOwnProperty.call(actionVerbs, match[2]) && actionVerbs[match[2]];
        return subject && verb ? verb + ' ' + subject : key;
    }

    function get(value, group) {
        if (value === null || value === undefined || value === '') return '-';
        var key = String(value);
        var scope = group || 'status';
        var labels = Object.prototype.hasOwnProperty.call(groups, scope) ? groups[scope] : null;
        if (labels && Object.prototype.hasOwnProperty.call(labels, key)) return labels[key];
        if (group === 'permission') return permissionLabel(key);
        return group === 'action' ? actionLabel(key) : key;
    }

    window.CCAdminLabels = { get: get };
})(window);
