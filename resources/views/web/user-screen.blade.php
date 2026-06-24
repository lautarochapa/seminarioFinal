@extends('layouts.web-user')

@section('title', $screen['title'].' - CC Control Web')

@section('content')
    <section class="hero">
        <div>
            <div class="module">{{ $screen['module'] }}</div>
            <h1>{{ $screen['title'] }}</h1>
            <p class="lead">{{ $screen['description'] }}</p>
        </div>
        <div class="actions">
            <a href="#" class="btn-main">{{ $screen['primary'] }}</a>
            <a href="#" class="btn-secondary-web">{{ $screen['secondary'] }}</a>
        </div>
    </section>

    <section class="metric-row">
        @foreach($screen['metrics'] as $metric)
            <article class="metric">
                <strong>{{ $stats[$metric] ?? 0 }}</strong>
                <span>{{ str_replace('_', ' ', $metric) }}</span>
            </article>
        @endforeach
    </section>

    @if($screenKey === 'stock')
        <section data-user-stock-locations>
            <div class="alert" data-stock-locations-message style="display:none"></div>
            <div class="family-layout">
                <div class="family-stack">
                    <div class="metric-row" style="margin-bottom:14px">
                        <article class="metric">
                            <strong data-stock-summary-total>0</strong>
                            <span>items activos</span>
                        </article>
                        <article class="metric">
                            <strong data-stock-summary-products>0</strong>
                            <span>productos distintos</span>
                        </article>
                        <article class="metric">
                            <strong data-stock-summary-expiring>0</strong>
                            <span>proximos a vencer</span>
                        </article>
                        <article class="metric">
                            <strong data-stock-value-total>ARS 0</strong>
                            <span data-stock-value-count>0 valorizados</span>
                        </article>
                    </div>

                    <article class="panel" style="margin-bottom:14px">
                        <h2>Stock del hogar</h2>
                        <div class="web-tools">
                            <select class="form-control" data-stock-filter-location>
                                <option value="">Todas las ubicaciones</option>
                            </select>
                            <input class="form-control" data-stock-filter-expiry type="date" aria-label="Vence antes de">
                            <button type="button" class="btn-secondary-web" data-stock-refresh>Actualizar stock</button>
                            <span class="chip" data-stock-count>0 items</span>
                        </div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Ubicacion</th>
                                        <th>Cantidad</th>
                                        <th>Vencimiento</th>
                                        <th>Precio compra</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-stock-body>
                                    <tr><td colspan="6" class="muted">Selecciona un grupo familiar.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="catalog-pagination">
                            <button type="button" class="btn-secondary-web btn-sm" data-stock-prev>Anterior</button>
                            <span class="muted" data-stock-page>Pagina 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-stock-next>Siguiente</button>
                        </div>
                    </article>

                    <article class="panel" style="margin-bottom:14px">
                        <h2>Movimientos de stock</h2>
                        <div class="web-tools">
                            <select class="form-control" data-stock-movement-filter-type>
                                <option value="">Todos los tipos</option>
                                <option value="adjustment">Ajustes</option>
                                <option value="consumption">Consumos</option>
                                <option value="discard">Descartes</option>
                                <option value="entry">Entradas</option>
                                <option value="expiration">Vencimientos</option>
                            </select>
                            <input class="form-control" data-stock-movement-date-from type="date" aria-label="Desde">
                            <input class="form-control" data-stock-movement-date-to type="date" aria-label="Hasta">
                            <button type="button" class="btn-secondary-web" data-stock-movements-refresh>Actualizar historial</button>
                            <span class="chip" data-stock-movements-count>0 movimientos</span>
                        </div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Producto</th>
                                        <th>Ubicacion</th>
                                        <th>Cantidad</th>
                                        <th>Motivo</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody data-stock-movements-body>
                                    <tr><td colspan="7" class="muted">Selecciona un grupo familiar.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="catalog-pagination">
                            <button type="button" class="btn-secondary-web btn-sm" data-stock-movements-prev>Anterior</button>
                            <span class="muted" data-stock-movements-page>Pagina 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-stock-movements-next>Siguiente</button>
                        </div>
                    </article>

                    <article class="panel" style="margin-bottom:14px">
                        <h2>Alertas y stock minimo</h2>
                        <div class="web-tools">
                            <select class="form-control" data-stock-alert-status>
                                <option value="">Todas las alertas</option>
                                <option value="open">Abiertas</option>
                                <option value="read">Leidas</option>
                            </select>
                            <select class="form-control" data-stock-alert-severity>
                                <option value="">Todas las severidades</option>
                                <option value="low">Baja</option>
                                <option value="medium">Media</option>
                                <option value="high">Alta</option>
                            </select>
                            <button type="button" class="btn-secondary-web" data-stock-alerts-refresh>Actualizar alertas</button>
                            <span class="chip" data-stock-alerts-count>0 alertas</span>
                        </div>
                        <div data-stock-alerts-list style="display:grid;gap:8px;margin-top:12px">
                            <p class="muted">Selecciona un grupo familiar.</p>
                        </div>

                        <div class="web-tools" style="margin-top:16px">
                            <input class="form-control" data-stock-expiring-days type="number" min="1" max="365" value="7" aria-label="Dias a vencer">
                            <button type="button" class="btn-secondary-web" data-stock-expiring-refresh>Ver por vencer</button>
                            <button type="button" class="btn-secondary-web" data-stock-low-refresh>Ver bajo stock</button>
                        </div>
                        <div class="profile-grid" style="margin-top:12px">
                            <div>
                                <h3>Productos por vencer</h3>
                                <div data-stock-expiring-list class="muted">Sin datos cargados.</div>
                            </div>
                            <div>
                                <h3>Bajo stock</h3>
                                <div data-stock-low-list class="muted">Sin datos cargados.</div>
                            </div>
                        </div>

                        <hr>

                        <h3>Reglas de stock minimo</h3>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Minimo</th>
                                        <th>Unidad</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-stock-rules-body>
                                    <tr><td colspan="5" class="muted">Selecciona un grupo familiar.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="panel" style="margin-bottom:14px">
                        <h2>Reporte de desperdicio</h2>
                        <div class="metric-row" style="margin-bottom:12px">
                            <article class="metric">
                                <strong data-waste-total-quantity>0</strong>
                                <span>cantidad descartada</span>
                            </article>
                            <article class="metric">
                                <strong data-waste-total-loss>ARS 0</strong>
                                <span>perdida estimada</span>
                            </article>
                            <article class="metric">
                                <strong data-waste-with-price>0</strong>
                                <span>items valorizados</span>
                            </article>
                            <article class="metric">
                                <strong data-waste-without-price>0</strong>
                                <span>sin precio</span>
                            </article>
                        </div>
                        <div class="web-tools">
                            <select class="form-control" data-waste-product-filter>
                                <option value="">Todos los productos</option>
                            </select>
                            <select class="form-control" data-waste-location-filter>
                                <option value="">Todas las ubicaciones</option>
                            </select>
                            <input class="form-control" data-waste-reason-filter type="search" placeholder="Motivo">
                            <input class="form-control" data-waste-date-from type="date" aria-label="Desde">
                            <input class="form-control" data-waste-date-to type="date" aria-label="Hasta">
                            <button type="button" class="btn-secondary-web" data-waste-refresh>Actualizar reporte</button>
                        </div>
                        <div style="overflow:auto;margin-top:12px">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Producto</th>
                                        <th>Ubicacion</th>
                                        <th>Cantidad</th>
                                        <th>Motivo</th>
                                        <th>Perdida estimada</th>
                                    </tr>
                                </thead>
                                <tbody data-waste-body>
                                    <tr><td colspan="7" class="muted">Selecciona un grupo familiar.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="catalog-pagination">
                            <button type="button" class="btn-secondary-web btn-sm" data-waste-prev>Anterior</button>
                            <span class="muted" data-waste-page>Pagina 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-waste-next>Siguiente</button>
                        </div>
                    </article>

                    <article class="panel">
                        <h2>Ubicaciones del hogar</h2>
                        <div class="web-tools">
                            <select class="form-control" data-stock-group-select>
                                <option value="">Cargando grupo familiar...</option>
                            </select>
                            <select class="form-control" data-stock-location-status>
                                <option value="">Todas</option>
                                <option value="active">Activas</option>
                                <option value="inactive">Inactivas</option>
                            </select>
                            <button type="button" class="btn-secondary-web" data-stock-locations-refresh>Actualizar</button>
                            <span class="chip" data-stock-locations-count>0 ubicaciones</span>
                        </div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Actualizacion</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-stock-locations-body>
                                    <tr><td colspan="5" class="muted">Selecciona un grupo familiar.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="catalog-pagination">
                            <button type="button" class="btn-secondary-web btn-sm" data-stock-locations-prev>Anterior</button>
                            <span class="muted" data-stock-locations-page>Pagina 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-stock-locations-next>Siguiente</button>
                        </div>
                    </article>
                </div>

                <aside class="aside-panel">
                    <h2 data-stock-item-form-title>Cargar stock</h2>
                    <form class="family-form" data-stock-item-form>
                        <input type="hidden" name="id">
                        <label>Producto</label>
                        <input class="form-control" data-stock-product-search type="search" placeholder="Buscar producto">
                        <select class="form-control" name="product_id" required data-stock-product-select>
                            <option value="">Cargando productos...</option>
                        </select>
                        <label>Ubicacion</label>
                        <select class="form-control" name="stock_location_id" data-stock-item-location>
                            <option value="">Sin ubicacion</option>
                        </select>
                        <label>Cantidad</label>
                        <input class="form-control" name="quantity" type="number" step="0.01" min="0" required>
                        <label>Unidad</label>
                        <select class="form-control" name="unit_id" required data-stock-unit-select>
                            <option value="">Cargando unidades...</option>
                        </select>
                        <label>Vencimiento</label>
                        <input class="form-control" name="expiration_date" type="date">
                        <label>Precio de compra</label>
                        <input class="form-control" name="purchase_price" type="number" step="0.01" min="0">
                        <label>Estado</label>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                            <button type="submit" class="btn-main" data-stock-item-submit>Guardar stock</button>
                            <button type="button" class="btn-secondary-web" data-stock-item-cancel style="display:none">Cancelar</button>
                        </div>
                    </form>

                    <hr>

                    <h2 data-stock-movement-form-title>Registrar movimiento</h2>
                    <form class="family-form" data-stock-movement-form>
                        <input type="hidden" name="stock_item_id">
                        <label>Item de stock</label>
                        <select class="form-control" name="stock_item_select" data-stock-movement-item-select>
                            <option value="">Selecciona item</option>
                        </select>
                        <label>Operacion</label>
                        <select class="form-control" name="operation" data-stock-movement-operation>
                            <option value="adjust">Ajustar</option>
                            <option value="consume">Consumir</option>
                            <option value="discard">Descartar</option>
                        </select>
                        <label>Modo de ajuste</label>
                        <select class="form-control" name="mode" data-stock-movement-mode>
                            <option value="set">Fijar cantidad final</option>
                            <option value="add">Sumar cantidad</option>
                            <option value="subtract">Restar cantidad</option>
                        </select>
                        <label>Cantidad</label>
                        <input class="form-control" name="quantity" type="number" step="0.01" min="0" required>
                        <label>Motivo</label>
                        <textarea class="form-control" name="reason" rows="3" maxlength="1000" placeholder="Conteo manual, consumo, vencido..."></textarea>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                            <button type="submit" class="btn-main" data-stock-movement-submit>Registrar</button>
                            <button type="button" class="btn-secondary-web" data-stock-movement-cancel>Limpiar</button>
                        </div>
                    </form>

                    <hr>

                    <h2 data-stock-rule-form-title>Regla de minimo</h2>
                    <form class="family-form" data-stock-rule-form>
                        <input type="hidden" name="id">
                        <label>Producto</label>
                        <select class="form-control" name="product_id" required data-stock-rule-product-select>
                            <option value="">Selecciona producto</option>
                        </select>
                        <label>Cantidad minima</label>
                        <input class="form-control" name="minimum_quantity" type="number" min="0" step="0.01" required>
                        <label>Unidad</label>
                        <select class="form-control" name="unit_id" required data-stock-rule-unit-select>
                            <option value="">Selecciona unidad</option>
                        </select>
                        <label>Estado</label>
                        <select class="form-control" name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                            <button type="submit" class="btn-main" data-stock-rule-submit>Guardar regla</button>
                            <button type="button" class="btn-secondary-web" data-stock-rule-cancel>Limpiar</button>
                        </div>
                    </form>

                    <hr>

                    <h2 data-stock-location-form-title>Nueva ubicacion</h2>
                    <form class="family-form" data-stock-location-form>
                        <input type="hidden" name="id">
                        <label>Nombre</label>
                        <input class="form-control" name="name" type="text" placeholder="Alacena" required maxlength="120">
                        <label>Tipo</label>
                        <input class="form-control" name="type" type="text" placeholder="pantry, fridge, freezer" maxlength="60">
                        <label>Estado</label>
                        <select class="form-control" name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                            <button type="submit" class="btn-main" data-stock-location-submit>Guardar</button>
                            <button type="button" class="btn-secondary-web" data-stock-location-cancel style="display:none">Cancelar</button>
                        </div>
                    </form>
                    <p class="muted" style="margin-top:14px">Las ubicaciones permiten organizar stock por alacena, heladera, freezer u otros espacios. Al eliminar una ubicacion no se borran productos ni historial.</p>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'profile-objectives')
        <section class="workspace">
            <div style="display:grid;gap:14px">
            <div class="profile-grid">
                <article class="panel">
                    <h2>Mi perfil</h2>
                    <form class="profile-form" data-user-profile-form>
                        <div class="alert" data-profile-message style="display:none"></div>

                        <div class="row">
                            <div class="col-md-6">
                                <label for="profile-name">Nombre</label>
                                <input id="profile-name" class="form-control" name="name" type="text" autocomplete="given-name">
                                <span class="invalid-feedback" data-profile-error="name" role="alert"></span>
                            </div>
                            <div class="col-md-6">
                                <label for="profile-lastname">Apellido</label>
                                <input id="profile-lastname" class="form-control" name="lastname" type="text" autocomplete="family-name">
                                <span class="invalid-feedback" data-profile-error="lastname" role="alert"></span>
                            </div>
                        </div>

                        <div class="row" style="margin-top:10px">
                            <div class="col-md-6">
                                <label for="profile-email">Email</label>
                                <input id="profile-email" class="form-control" name="email" type="email" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="profile-phone">Telefono</label>
                                <input id="profile-phone" class="form-control" name="phone" type="text" autocomplete="tel">
                                <span class="invalid-feedback" data-profile-error="phone" role="alert"></span>
                            </div>
                        </div>

                        <div class="row" style="margin-top:10px">
                            <div class="col-md-6">
                                <label for="profile-birth-date">Fecha de nacimiento</label>
                                <input id="profile-birth-date" class="form-control" name="birth_date" type="date">
                                <span class="invalid-feedback" data-profile-error="birth_date" role="alert"></span>
                            </div>
                            <div class="col-md-6">
                                <label for="profile-gender">Genero</label>
                                <select id="profile-gender" class="form-control" name="gender">
                                    <option value="">Sin especificar</option>
                                    <option value="male">Masculino</option>
                                    <option value="female">Femenino</option>
                                    <option value="other">Otro</option>
                                    <option value="prefer_not_to_say">Prefiero no decirlo</option>
                                </select>
                                <span class="invalid-feedback" data-profile-error="gender" role="alert"></span>
                            </div>
                        </div>

                        <div class="row" style="margin-top:10px">
                            <div class="col-md-4">
                                <label for="profile-height">Altura (cm)</label>
                                <input id="profile-height" class="form-control" name="height_cm" type="number" min="0" step="0.1">
                                <span class="invalid-feedback" data-profile-error="height_cm" role="alert"></span>
                            </div>
                            <div class="col-md-4">
                                <label for="profile-current-weight">Peso actual (kg)</label>
                                <input id="profile-current-weight" class="form-control" name="current_weight_kg" type="number" min="0" step="0.1">
                                <span class="invalid-feedback" data-profile-error="current_weight_kg" role="alert"></span>
                            </div>
                            <div class="col-md-4">
                                <label for="profile-target-weight">Peso objetivo (kg)</label>
                                <input id="profile-target-weight" class="form-control" name="target_weight_kg" type="number" min="0" step="0.1">
                                <span class="invalid-feedback" data-profile-error="target_weight_kg" role="alert"></span>
                            </div>
                        </div>

                        <div class="row" style="margin-top:10px">
                            <div class="col-md-6">
                                <label for="profile-activity-level">Nivel de actividad</label>
                                <select id="profile-activity-level" class="form-control" name="activity_level">
                                    <option value="">Sin especificar</option>
                                    <option value="sedentary">Sedentario</option>
                                    <option value="light">Ligero</option>
                                    <option value="moderate">Moderado</option>
                                    <option value="active">Activo</option>
                                    <option value="very_active">Muy activo</option>
                                </select>
                                <span class="invalid-feedback" data-profile-error="activity_level" role="alert"></span>
                            </div>
                            <div class="col-md-6">
                                <label for="profile-meals-per-day">Comidas por dia</label>
                                <input id="profile-meals-per-day" class="form-control" name="meals_per_day" type="number" min="1" step="1">
                                <span class="invalid-feedback" data-profile-error="meals_per_day" role="alert"></span>
                            </div>
                        </div>

                        <div style="margin-top:10px">
                            <label>Para que usas la app</label>
                            <div class="checkbox-grid">
                                <label class="checkbox-card">
                                    <input type="checkbox" name="preferences.uses_app_for_health" value="1">
                                    <span>Salud y habitos</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="preferences.uses_app_for_budget" value="1">
                                    <span>Presupuesto</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="preferences.uses_app_for_organization" value="1">
                                    <span>Organizacion del hogar</span>
                                </label>
                            </div>
                        </div>

                        <div style="margin-top:12px">
                            <label for="profile-notes">Notas</label>
                            <textarea id="profile-notes" class="form-control" name="notes" rows="4" placeholder="Observaciones, contexto o notas personales"></textarea>
                            <span class="invalid-feedback" data-profile-error="notes" role="alert"></span>
                        </div>

                        <div style="margin-top:18px">
                            <button type="submit" class="btn-main">Guardar perfil</button>
                        </div>
                    </form>
                </article>

                <article class="panel">
                    <h2>Prioridades</h2>
                    <form class="profile-form" data-priority-settings-form>
                        <div class="alert" data-priority-message style="display:none"></div>

                        <label for="priority-health">Peso salud</label>
                        <input id="priority-health" class="form-control" name="health_weight" type="number" min="0" step="0.1">
                        <span class="invalid-feedback" data-priority-error="health_weight" role="alert"></span>

                        <label for="priority-budget">Peso economia</label>
                        <input id="priority-budget" class="form-control" name="budget_weight" type="number" min="0" step="0.1">
                        <span class="invalid-feedback" data-priority-error="budget_weight" role="alert"></span>

                        <label for="priority-time">Peso tiempo</label>
                        <input id="priority-time" class="form-control" name="time_weight" type="number" min="0" step="0.1">
                        <span class="invalid-feedback" data-priority-error="time_weight" role="alert"></span>

                        <label for="priority-stock">Peso uso de stock</label>
                        <input id="priority-stock" class="form-control" name="stock_usage_weight" type="number" min="0" step="0.1">
                        <span class="invalid-feedback" data-priority-error="stock_usage_weight" role="alert"></span>

                        <label for="priority-mode">Modo preferido</label>
                        <input id="priority-mode" class="form-control" name="preferred_mode" type="text" placeholder="balanceado, ahorro, rapido...">
                        <span class="invalid-feedback" data-priority-error="preferred_mode" role="alert"></span>

                        <div style="margin-top:18px">
                            <button type="submit" class="btn-main">Guardar prioridades</button>
                        </div>
                    </form>

                    <div style="margin-top:18px">
                        <h2 style="margin-bottom:10px">Resumen actual</h2>
                        <div class="table-line"><span class="muted">Objetivos elegidos</span><strong data-profile-objectives-count>0</strong></div>
                        <div class="table-line"><span class="muted">Preferencias activas</span><strong data-profile-preferences-count>0</strong></div>
                        <div class="table-line"><span class="muted">Modo preferido</span><strong data-priority-mode-summary>-</strong></div>
                    </div>
                </article>
            </div>

            <article class="panel">
                <h2>Objetivos personales</h2>
                <div class="profile-grid">
                    <div>
                        <form class="profile-form" data-user-objective-form>
                            <div class="alert" data-user-objective-message style="display:none"></div>
                            <input type="hidden" name="assignment_id">

                            <label for="objective-select">Objetivo</label>
                            <select id="objective-select" class="form-control" name="objective_id" required>
                                <option value="">Cargando objetivos...</option>
                            </select>
                            <span class="invalid-feedback" data-user-objective-error="objective_id" role="alert"></span>

                            <div class="row" style="margin-top:10px">
                                <div class="col-md-4">
                                    <label for="objective-priority">Prioridad</label>
                                    <input id="objective-priority" class="form-control" name="priority" type="number" min="1" step="1">
                                    <span class="invalid-feedback" data-user-objective-error="priority" role="alert"></span>
                                </div>
                                <div class="col-md-4">
                                    <label for="objective-target-value">Valor objetivo</label>
                                    <input id="objective-target-value" class="form-control" name="target_value" type="number" step="0.1">
                                    <span class="invalid-feedback" data-user-objective-error="target_value" role="alert"></span>
                                </div>
                                <div class="col-md-4">
                                    <label for="objective-target-unit">Unidad</label>
                                    <input id="objective-target-unit" class="form-control" name="target_unit" type="text" placeholder="kg, %, mg, $">
                                    <span class="invalid-feedback" data-user-objective-error="target_unit" role="alert"></span>
                                </div>
                            </div>

                            <div class="row" style="margin-top:10px">
                                <div class="col-md-6">
                                    <label for="objective-target-date">Fecha objetivo</label>
                                    <input id="objective-target-date" class="form-control" name="target_date" type="date">
                                    <span class="invalid-feedback" data-user-objective-error="target_date" role="alert"></span>
                                </div>
                                <div class="col-md-6">
                                    <label for="objective-notes">Notas</label>
                                    <input id="objective-notes" class="form-control" name="notes" type="text" placeholder="Contexto del objetivo">
                                    <span class="invalid-feedback" data-user-objective-error="notes" role="alert"></span>
                                </div>
                            </div>

                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                                <button type="submit" class="btn-main">Guardar objetivo</button>
                                <button type="button" class="btn-secondary-web" data-user-objective-reset>Limpiar</button>
                            </div>
                        </form>
                    </div>

                    <div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Objetivo</th>
                                        <th>Prioridad</th>
                                        <th>Meta</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-user-objectives-body>
                                    <tr><td colspan="5" class="muted">Cargando objetivos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </article>

            <article class="panel">
                <h2>Restricciones, alergias y condiciones</h2>
                <div class="profile-grid">
                    <div>
                        <div class="audit-tabs" role="tablist" aria-label="Preferencias de salud">
                            <button type="button" class="audit-tab active" data-user-health-tab="dietary-restrictions">Restricciones</button>
                            <button type="button" class="audit-tab" data-user-health-tab="health-conditions">Condiciones</button>
                            <button type="button" class="audit-tab" data-user-health-tab="allergies">Alergias</button>
                        </div>

                        <form class="profile-form" data-user-health-form>
                            <div class="alert" data-user-health-message style="display:none"></div>
                            <label for="user-health-item">Item del catalogo</label>
                            <select id="user-health-item" class="form-control" name="item_id" required>
                                <option value="">Cargando catalogo...</option>
                            </select>
                            <span class="invalid-feedback" data-user-health-error="item_id" role="alert"></span>

                            <div data-user-health-severity-wrap style="display:none">
                                <label for="user-health-severity">Severidad</label>
                                <input id="user-health-severity" class="form-control" name="severity" type="text" placeholder="leve, moderada, alta">
                                <span class="invalid-feedback" data-user-health-error="severity" role="alert"></span>
                            </div>

                            <label for="user-health-notes">Notas</label>
                            <input id="user-health-notes" class="form-control" name="notes" type="text" placeholder="Observaciones relevantes">
                            <span class="invalid-feedback" data-user-health-error="notes" role="alert"></span>

                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                                <button type="submit" class="btn-main">Agregar seleccion</button>
                                <button type="button" class="btn-secondary-web" data-user-health-reset>Limpiar</button>
                            </div>
                        </form>
                    </div>

                    <div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Detalle</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-user-health-body>
                                    <tr><td colspan="3" class="muted">Cargando seleccion actual...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </article>

            <article class="panel">
                <h2>Mediciones personales</h2>
                <form class="profile-form" data-body-measurement-form>
                    <div class="alert" data-measurement-message style="display:none"></div>

                    <div class="row">
                        <div class="col-md-3">
                            <label for="measurement-date">Fecha</label>
                            <input id="measurement-date" class="form-control" name="measured_at" type="date">
                            <span class="invalid-feedback" data-measurement-error="measured_at" role="alert"></span>
                        </div>
                        <div class="col-md-3">
                            <label for="measurement-weight">Peso (kg)</label>
                            <input id="measurement-weight" class="form-control" name="weight_kg" type="number" min="0" step="0.1">
                            <span class="invalid-feedback" data-measurement-error="weight_kg" role="alert"></span>
                        </div>
                        <div class="col-md-3">
                            <label for="measurement-waist">Cintura (cm)</label>
                            <input id="measurement-waist" class="form-control" name="waist_cm" type="number" min="0" step="0.1">
                            <span class="invalid-feedback" data-measurement-error="waist_cm" role="alert"></span>
                        </div>
                        <div class="col-md-3">
                            <label for="measurement-glucose">Glucosa</label>
                            <input id="measurement-glucose" class="form-control" name="glucose_level" type="number" min="0" step="0.1">
                            <span class="invalid-feedback" data-measurement-error="glucose_level" role="alert"></span>
                        </div>
                    </div>

                    <div class="row" style="margin-top:10px">
                        <div class="col-md-3">
                            <label for="measurement-systolic">Presion sistolica</label>
                            <input id="measurement-systolic" class="form-control" name="blood_pressure_systolic" type="number" min="1" step="1">
                            <span class="invalid-feedback" data-measurement-error="blood_pressure_systolic" role="alert"></span>
                        </div>
                        <div class="col-md-3">
                            <label for="measurement-diastolic">Presion diastolica</label>
                            <input id="measurement-diastolic" class="form-control" name="blood_pressure_diastolic" type="number" min="1" step="1">
                            <span class="invalid-feedback" data-measurement-error="blood_pressure_diastolic" role="alert"></span>
                        </div>
                        <div class="col-md-6">
                            <label for="measurement-notes">Notas</label>
                            <input id="measurement-notes" class="form-control" name="notes" type="text" placeholder="Ej: despues de entrenar, en ayunas, control medico...">
                            <span class="invalid-feedback" data-measurement-error="notes" role="alert"></span>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                        <button type="submit" class="btn-main">Guardar medicion</button>
                        <button type="button" class="btn-secondary-web" data-measurement-reset>Limpiar formulario</button>
                    </div>
                </form>

                <div style="overflow:auto;margin-top:18px">
                    <table class="web-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Peso</th>
                                <th>Cintura</th>
                                <th>Presion</th>
                                <th>Glucosa</th>
                                <th>Notas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody data-measurements-body>
                            <tr><td colspan="7" class="muted">Cargando mediciones...</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="panel">
                <h2>Consentimientos</h2>
                <p class="muted" style="margin-bottom:16px">Desde aca podes aceptar o revocar permisos de privacidad, uso de datos sensibles y acceso profesional. Cada cambio guarda su fecha de aceptacion o revocacion.</p>

                <form class="profile-form" data-consents-form>
                    <div class="alert" data-consents-message style="display:none"></div>

                    <div class="checkbox-grid">
                        <label class="checkbox-card">
                            <input type="checkbox" name="health_data_consent" value="1">
                            <span>Uso de datos de salud</span>
                        </label>
                        <label class="checkbox-card">
                            <input type="checkbox" name="privacy_consent" value="1">
                            <span>Politica de privacidad</span>
                        </label>
                        <label class="checkbox-card">
                            <input type="checkbox" name="professional_access_consent" value="1">
                            <span>Acceso profesional</span>
                        </label>
                        <label class="checkbox-card">
                            <input type="checkbox" name="medical_disclaimer_accepted" value="1">
                            <span>Aviso profesional</span>
                        </label>
                        <label class="checkbox-card">
                            <input type="checkbox" name="terms_accepted" value="1">
                            <span>Terminos de uso</span>
                        </label>
                    </div>

                    <div style="margin-top:18px">
                        <button type="submit" class="btn-main">Guardar consentimientos</button>
                    </div>
                </form>

                <div style="overflow:auto;margin-top:18px">
                    <table class="web-table">
                        <thead>
                            <tr>
                                <th>Consentimiento</th>
                                <th>Estado</th>
                                <th>Aceptado</th>
                                <th>Revocado</th>
                            </tr>
                        </thead>
                        <tbody data-consents-body>
                            <tr><td colspan="4" class="muted">Cargando consentimientos...</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>
            </div>

            <aside class="aside-panel">
                <h2>Sesion API</h2>
                <div class="table-line"><span class="muted">Perfil</span><strong>GET/PATCH /users/me/profile</strong></div>
                <div class="table-line"><span class="muted">Prioridades</span><strong>GET/PATCH /users/me/priority-settings</strong></div>
                <div class="table-line"><span class="muted">Mediciones</span><strong>CRUD /users/me/body-measurements</strong></div>
                <div class="table-line"><span class="muted">Objetivos</span><strong>CRUD /users/me/objectives</strong></div>
                <div class="table-line"><span class="muted">Restricciones</span><strong>Catalogos + POST/DELETE /users/me/*</strong></div>
                <div class="table-line"><span class="muted">Consentimientos</span><strong>GET/PATCH /users/me/consents</strong></div>
                <div class="table-line"><span class="muted">Catalogo</span><strong>GET /catalog/objectives</strong></div>
                <p class="muted" style="margin-top:14px">Esta pantalla usa el token activo para traer el perfil personal, administrar objetivos, restricciones, mediciones y consentimientos, y guardar la configuracion de prioridades.</p>
            </aside>
        </section>
    @elseif($screenKey === 'family-group')
        <section data-family-groups>
            <div class="alert" data-family-message style="display:none"></div>

            <div class="family-layout">
                <div class="family-stack">
                    <article class="panel">
                        <div class="web-tools">
                            <select class="form-control" data-family-select>
                                <option value="">Cargando grupos...</option>
                            </select>
                            <button type="button" class="btn-secondary-web" data-family-refresh>Actualizar</button>
                            <span class="chip" data-family-count>0 grupos</span>
                        </div>

                        <form class="family-form" data-family-edit-form>
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Nombre del grupo</label>
                                    <input class="form-control" name="name" type="text" placeholder="Mi hogar">
                                </div>
                                <div class="col-md-6">
                                    <label>Estado</label>
                                    <select class="form-control" name="status">
                                        <option value="active">Activo</option>
                                        <option value="inactive">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row" style="margin-top:10px">
                                <div class="col-md-12">
                                    <label>Ciudad</label>
                                    <select class="form-control" name="city_id" data-family-city-select>
                                        <option value="">Sin ciudad asignada</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                                <button type="submit" class="btn-main">Guardar grupo</button>
                                <button type="button" class="btn-secondary-web" data-family-delete>Desactivar grupo</button>
                            </div>
                        </form>

                        <div class="table-line"><span class="muted">Propietario</span><strong data-family-owner>-</strong></div>
                        <div class="table-line"><span class="muted">Ciudad</span><strong data-family-city-name>-</strong></div>
                        <div class="table-line"><span class="muted">Direccion por defecto</span><strong data-family-address>-</strong></div>
                    </article>

                    <article class="panel">
                        <h2>Miembros</h2>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-members-body>
                                    <tr><td colspan="4" class="muted">Seleccioná un grupo para ver miembros.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="panel">
                        <h2>Preferencias del grupo</h2>
                        <form class="family-form" data-preferences-form>
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Presupuesto</label>
                                    <input class="form-control" name="default_budget_mode" type="text" placeholder="mensual">
                                </div>
                                <div class="col-md-6">
                                    <label>Compras</label>
                                    <input class="form-control" name="default_shopping_mode" type="text" placeholder="economico">
                                </div>
                            </div>
                            <div class="row" style="margin-top:10px">
                                <div class="col-md-6">
                                    <label>Prioridad recetas</label>
                                    <input class="form-control" name="default_recipe_priority_mode" type="text" placeholder="balanceado">
                                </div>
                                <div class="col-md-6">
                                    <label style="display:flex;gap:8px;align-items:center;margin-top:32px">
                                        <input name="allow_auto_stock_discount" type="checkbox" value="1">
                                        Descontar stock automaticamente
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn-main" style="margin-top:12px">Guardar preferencias</button>
                        </form>
                    </article>
                </div>

                <aside class="aside-panel">
                    <h2>Nuevo grupo</h2>
                    <form class="family-form" data-family-create-form>
                        <input class="form-control" name="name" type="text" placeholder="Nombre del grupo" required>
                        <button type="submit" class="btn-main">Crear grupo</button>
                    </form>

                    <h2 style="margin-top:20px">Agregar miembro</h2>
                    <form class="family-form" data-member-create-form>
                        <input class="form-control" name="user_id" type="number" min="1" placeholder="ID de usuario" required>
                        <select class="form-control" name="role" required>
                            <option value="member">Miembro</option>
                            <option value="admin">Administrador</option>
                        </select>
                        <button type="submit" class="btn-main">Agregar miembro</button>
                    </form>

                    <h2 style="margin-top:20px">Invitar por email</h2>
                    <form class="family-form" data-invitation-form>
                        <input class="form-control" name="email" type="email" placeholder="email@ejemplo.com" required>
                        <select class="form-control" name="role" required>
                            <option value="member">Miembro</option>
                            <option value="admin">Administrador</option>
                        </select>
                        <button type="submit" class="btn-main">Enviar invitacion</button>
                    </form>

                    <h2 style="margin-top:20px">Aceptar invitacion</h2>
                    <form class="family-form" data-invitation-accept-form>
                        <input class="form-control" name="invitation_id" type="number" min="1" placeholder="ID de invitacion" required>
                        <button type="submit" class="btn-secondary-web">Aceptar invitacion</button>
                    </form>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'payment-methods')
        <section class="workspace" data-user-payment-methods>
            <div style="display:grid;gap:14px">
                <article class="panel">
                    <h2>Mis metodos de pago</h2>
                    <div class="alert" data-user-payment-message style="display:none"></div>
                    <div style="overflow:auto">
                        <table class="web-table">
                            <thead>
                                <tr>
                                    <th>Metodo</th>
                                    <th>Alias</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-user-payment-body>
                                <tr><td colspan="4" class="muted">Cargando metodos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel">
                    <h2>Agregar metodo</h2>
                    <form class="profile-form" data-user-payment-form>
                        <select class="form-control" name="payment_method_id" required data-user-payment-select>
                            <option value="">Cargando catalogo...</option>
                        </select>
                        <input class="form-control" name="alias" type="text" maxlength="120" placeholder="Alias opcional, ej. Visa personal">
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main" data-user-payment-submit>Agregar metodo</button>
                            <button type="button" class="btn-secondary-web" data-user-payment-refresh>Actualizar</button>
                        </div>
                    </form>
                </article>

                <article class="panel">
                    <h2>Catalogo disponible</h2>
                    <div class="web-tools">
                        <select class="form-control" data-user-payment-type>
                            <option value="">Todos los tipos</option>
                            <option value="credit_card">Tarjeta credito</option>
                            <option value="debit_card">Tarjeta debito</option>
                            <option value="bank_account">Cuenta bancaria</option>
                            <option value="digital_wallet">Billetera digital</option>
                            <option value="cash">Efectivo</option>
                            <option value="other">Otro</option>
                        </select>
                        <button type="button" class="btn-secondary-web" data-user-payment-catalog-refresh>Filtrar</button>
                    </div>
                    <div data-user-payment-catalog class="muted">Cargando catalogo...</div>
                </article>
            </div>

            <aside class="aside-panel">
                <h2>Seguridad</h2>
                <div class="table-line"><span class="muted">Datos guardados</span><strong>Metodo y alias</strong></div>
                <div class="table-line"><span class="muted">No se guarda</span><strong>Numero, CVV ni token</strong></div>
                <div class="table-line"><span class="muted">Promociones</span><strong>Compatibilidad por tipo/emisor</strong></div>
                <p class="muted" style="margin-top:14px;font-size:13px">Las promociones generales siguen visibles aunque no tengas metodos asociados.</p>
            </aside>
        </section>
    @elseif($screenKey === 'professional-permissions')
        <section class="workspace" data-professional-links>
            <div style="display:grid;gap:14px">
                <article class="panel">
                    <h2>Vincular profesional</h2>
                    <div class="alert" data-professional-links-message style="display:none"></div>

                    <form class="profile-form" data-professional-links-form>
                        <label for="professional-user-id">ID del profesional</label>
                        <input id="professional-user-id" class="form-control" name="professional_user_id" type="number" min="1" placeholder="Ej: 2" required>

                        <label>Permisos otorgados</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-card"><input type="checkbox" name="can_view_profile" value="1"><span>Ver perfil</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="can_view_stock" value="1"><span>Ver stock</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="can_view_meal_plans" value="1"><span>Ver planificacion</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="can_edit_meal_plans" value="1"><span>Editar planificacion</span></label>
                            <label class="checkbox-card"><input type="checkbox" name="can_view_reports" value="1"><span>Ver reportes</span></label>
                        </div>

                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
                            <button type="submit" class="btn-main">Guardar acceso</button>
                            <button type="button" class="btn-secondary-web" data-professional-links-reset>Limpiar</button>
                        </div>
                    </form>
                </article>

                <article class="panel">
                    <h2>Profesionales autorizados</h2>
                    <div class="table-line"><span class="muted">Vinculos activos</span><strong data-professional-links-count>0</strong></div>
                    <div style="overflow:auto;margin-top:12px">
                        <table class="web-table">
                            <thead>
                                <tr>
                                    <th>Profesional</th>
                                    <th>Permisos</th>
                                    <th>Estado</th>
                                    <th>Otorgado</th>
                                    <th>Revocado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-professional-links-body>
                                <tr><td colspan="6" class="muted">Cargando profesionales vinculados...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>

            <aside class="aside-panel">
                <h2>Sesion API</h2>
                <div class="table-line"><span class="muted">Vinculos</span><strong>GET /professional-links</strong></div>
                <div class="table-line"><span class="muted">Alta</span><strong>POST /professional-links</strong></div>
                <div class="table-line"><span class="muted">Cambios</span><strong>PATCH /professional-links/{id}</strong></div>
                <div class="table-line"><span class="muted">Revocacion</span><strong>DELETE /professional-links/{id}</strong></div>
                <p class="muted" style="margin-top:14px">Desde esta pantalla el usuario otorga acceso profesional a un dietologo, define que puede ver o editar y puede revocar el permiso cuando quiera.</p>
            </aside>
        </section>
    @elseif($screenKey === 'catalog')
        <section class="workspace" data-user-catalog>
            <div>
                <div class="audit-tabs" role="tablist" aria-label="Catálogo">
                    <button type="button" class="audit-tab active" data-catalog-tab="products">Productos</button>
                    <button type="button" class="audit-tab" data-catalog-tab="ingredients">Ingredientes</button>
                </div>

                {{-- Panel Productos --}}
                <div data-catalog-panel="products">
                    <article class="panel">
                        <div class="web-tools">
                            <input class="form-control" type="search" data-catalog-products-search placeholder="Buscar productos...">
                            <select class="form-control" data-catalog-products-category>
                                <option value="">Todas las categorías</option>
                            </select>
                            <select class="form-control" data-catalog-products-brand>
                                <option value="">Todas las marcas</option>
                            </select>
                            <button type="button" class="btn-secondary-web" data-catalog-products-refresh>Buscar</button>
                            <span class="chip" data-catalog-products-count>0 productos</span>
                        </div>
                        <div class="alert" data-catalog-products-message style="display:none"></div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Marca</th>
                                        <th>Categoría</th>
                                        <th>Código</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-catalog-products-body>
                                    <tr><td colspan="5" class="muted">Cargando productos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="catalog-pagination">
                            <button type="button" class="btn-secondary-web btn-sm" data-catalog-products-prev>Anterior</button>
                            <span class="muted" data-catalog-products-page>Página 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-catalog-products-next>Siguiente</button>
                        </div>
                    </article>
                </div>

                {{-- Panel Ingredientes --}}
                <div data-catalog-panel="ingredients" style="display:none">
                    <article class="panel">
                        <div class="web-tools">
                            <input class="form-control" type="search" data-catalog-ingredients-search placeholder="Buscar ingredientes...">
                            <select class="form-control" data-catalog-ingredients-category>
                                <option value="">Todas las categorías</option>
                            </select>
                            <button type="button" class="btn-secondary-web" data-catalog-ingredients-refresh>Buscar</button>
                            <span class="chip" data-catalog-ingredients-count>0 ingredientes</span>
                        </div>
                        <div class="alert" data-catalog-ingredients-message style="display:none"></div>
                        <div data-catalog-tags-filter style="margin-bottom:12px;display:none">
                            <span class="muted" style="font-size:13px;margin-right:6px">Filtrar por tag:</span>
                            <span data-catalog-tags-chips></span>
                        </div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Ingrediente</th>
                                        <th>Categoría</th>
                                        <th>Tags</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-catalog-ingredients-body>
                                    <tr><td colspan="4" class="muted">Cargando ingredientes...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="catalog-pagination">
                            <button type="button" class="btn-secondary-web btn-sm" data-catalog-ingredients-prev>Anterior</button>
                            <span class="muted" data-catalog-ingredients-page>Página 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-catalog-ingredients-next>Siguiente</button>
                        </div>
                    </article>
                </div>
            </div>

            <aside class="aside-panel" data-catalog-detail>
                <p class="muted">Seleccioná un item para ver el detalle.</p>
            </aside>
        </section>

    @elseif($screenKey === 'barcode-scanner')
        <section class="workspace" data-user-barcode>
            <div>
                <article class="panel">
                    <h2>Buscar por código de barras</h2>
                    <div class="alert" data-barcode-message style="display:none"></div>
                    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                        <div style="flex:1;min-width:200px">
                            <label for="barcode-code-input">Código de barras</label>
                            <input id="barcode-code-input"
                                   class="form-control"
                                   type="text"
                                   inputmode="numeric"
                                   data-barcode-code-input
                                   placeholder="Ej: 7790580002312"
                                   autocomplete="off"
                                   style="font-size:20px;letter-spacing:2px;margin-top:4px">
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;padding-bottom:1px">
                            <button type="button" class="btn-main" data-barcode-search-submit>Buscar</button>
                            <button type="button" class="btn-secondary-web" data-barcode-camera-toggle>Usar cámara</button>
                        </div>
                    </div>

                    <div data-barcode-camera-container style="display:none;margin-top:16px">
                        <video data-barcode-video
                               autoplay muted playsinline
                               style="width:100%;max-width:420px;border-radius:8px;border:1px solid var(--line);display:block"></video>
                        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                            <button type="button" class="btn-secondary-web btn-sm" data-barcode-camera-stop>Cerrar cámara</button>
                        </div>
                        <p class="muted" style="font-size:12px;margin-top:6px">El stream de cámara se libera al cerrar o salir de la pantalla.</p>
                    </div>
                </article>

                <article class="panel" style="margin-top:12px">
                    <h2>Resultado</h2>
                    <div data-barcode-result>
                        <p class="muted">Ingresá un código para ver el producto.</p>
                    </div>
                </article>

                <article class="panel" style="margin-top:12px">
                    <h2>Agregar al stock</h2>
                    <div class="web-tools">
                        <select class="form-control" data-barcode-group-select>
                            <option value="">Cargando grupo familiar...</option>
                        </select>
                        <select class="form-control" data-barcode-location-select>
                            <option value="">Selecciona ubicacion</option>
                        </select>
                        <input class="form-control" data-barcode-quantity type="number" min="0.0001" step="0.01" value="1" aria-label="Cantidad">
                        <button type="button" class="btn-main" data-barcode-stock-submit>Agregar stock</button>
                    </div>
                    <div data-barcode-stock-result style="margin-top:12px">
                        <p class="muted">Busca o escanea un producto, elegi ubicacion y confirma la carga.</p>
                    </div>
                </article>
            </div>

            <aside class="aside-panel">
                <h2>Cómo usar</h2>
                <div class="table-line"><span class="muted">Manual</span><strong>Escribí el código numérico</strong></div>
                <div class="table-line"><span class="muted">Cámara</span><strong>Apuntá al código de barras</strong></div>
                <div class="table-line"><span class="muted">Resultado</span><strong>Producto, marca y categoría</strong></div>
                <p class="muted" style="margin-top:14px;font-size:13px">Si el código no está registrado, el sistema lo indicará claramente.</p>
                <p class="muted" style="margin-top:8px;font-size:12px">La cámara no guarda imágenes. El stream se libera automáticamente al salir de la pantalla.</p>
                <div style="margin-top:16px">
                    <a href="/web/catalog" class="btn-secondary-web btn-sm">Ir al catálogo</a>
                </div>
            </aside>
        </section>

    @elseif($screenKey === 'branches')
        <section class="workspace" data-user-branches>
            <div style="display:grid;gap:14px">
                <article class="panel">
                    <div class="web-tools" style="flex-wrap:wrap;gap:6px">
                        <select class="form-control" data-branches-filter-city style="min-width:140px">
                            <option value="">Todas las ciudades</option>
                        </select>
                        <select class="form-control" data-branches-filter-chain style="min-width:150px">
                            <option value="">Todas las cadenas</option>
                        </select>
                        <button type="button" class="btn-secondary-web" data-branches-load>Ver sucursales</button>
                        <span class="chip" data-branches-count>0 sucursales</span>
                    </div>
                    <div style="margin-top:10px;background:var(--green-soft);border-radius:8px;padding:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <button type="button" class="btn-secondary-web btn-sm" data-branches-nearby-btn>📍 Usar mi ubicación</button>
                        <input class="form-control" type="number" data-branches-radius value="5" min="0.1" max="500" step="0.5" style="width:80px">
                        <span class="muted" style="font-size:13px">km de radio</span>
                        <span data-branches-geo-status class="muted" style="font-size:12px"></span>
                    </div>
                    <div class="alert" data-branches-message style="display:none;margin-top:10px"></div>
                    <div data-branches-list style="display:grid;gap:8px;margin-top:12px">
                        <p class="muted">Seleccioná una ciudad o usá tu ubicación para ver sucursales.</p>
                    </div>
                </article>
            </div>

            <aside class="aside-panel" style="display:flex;flex-direction:column;gap:12px;min-height:300px">
                <div data-branches-map style="height:220px;border-radius:8px;background:#e8efeb;display:flex;align-items:center;justify-content:center;overflow:hidden">
                    <span class="muted" style="font-size:13px">El mapa aparecerá aquí</span>
                </div>
                <div data-branches-detail>
                    <p class="muted" style="font-size:13px">Seleccioná una sucursal para ver el detalle.</p>
                </div>
            </aside>
        </section>

    @elseif($screenKey === 'recipes')
        <section class="workspace" data-user-recipes data-user-id="{{ auth()->id() }}">
            <div style="display:flex;flex-direction:column;gap:14px">
                <article class="panel">
                    <div class="web-tools">
                        <input class="form-control" type="search" data-recipes-search placeholder="Buscar receta...">
                        <select class="form-control" style="max-width:160px" data-recipes-source-type>
                            <option value="">Todas las fuentes</option>
                            <option value="official">Oficiales</option>
                            <option value="user">De usuario</option>
                            <option value="shared">Compartidas</option>
                            <option value="external">Externas</option>
                        </select>
                        <select class="form-control" style="max-width:180px" data-recipes-category>
                            <option value="">Todas las categorías</option>
                        </select>
                        <button type="button" class="btn-main btn-sm" data-recipes-new>+ Nueva receta</button>
                        <span class="chip" data-recipes-count>0 recetas</span>
                    </div>
                    <div class="alert" data-recipes-message style="display:none"></div>
                    <div data-recipes-list style="display:grid;gap:10px;margin-top:10px">
                        <p class="muted">Cargando recetas...</p>
                    </div>
                    <div class="catalog-pagination" style="margin-top:12px">
                        <button type="button" class="btn-secondary-web btn-sm" data-recipes-prev>Anterior</button>
                        <span class="muted" data-recipes-page>Pág 1</span>
                        <button type="button" class="btn-secondary-web btn-sm" data-recipes-next>Siguiente</button>
                    </div>
                </article>
            </div>

            <aside class="aside-panel" style="display:flex;flex-direction:column;gap:0">
                {{-- Detail view --}}
                <div data-recipes-detail>
                    <p class="muted">Seleccioná una receta para ver el detalle.</p>
                </div>

                {{-- Create / Edit form --}}
                <div data-recipes-form-panel style="display:none">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                        <h2 style="margin:0" data-recipes-form-title>Nueva receta</h2>
                        <button type="button" class="btn-secondary-web btn-sm" data-recipes-form-cancel>Cancelar</button>
                    </div>
                    <form data-recipes-form>
                        <input type="hidden" name="id">
                        <div style="margin-bottom:9px">
                            <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">Nombre *</label>
                            <input class="form-control" name="name" type="text" placeholder="Nombre de la receta" required>
                        </div>
                        <div style="margin-bottom:9px">
                            <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">Descripción</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Descripción breve..."></textarea>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-bottom:9px">
                            <div>
                                <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">Porciones</label>
                                <input class="form-control" name="servings" type="number" min="1" max="9999" placeholder="4">
                            </div>
                            <div>
                                <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">Dificultad</label>
                                <select class="form-control" name="difficulty">
                                    <option value="">Sin especificar</option>
                                    <option value="fácil">Fácil</option>
                                    <option value="media">Media</option>
                                    <option value="difícil">Difícil</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-bottom:9px">
                            <div>
                                <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">Prep. (min)</label>
                                <input class="form-control" name="prep_time_minutes" type="number" min="0" max="9999" placeholder="15">
                            </div>
                            <div>
                                <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">Cocción (min)</label>
                                <input class="form-control" name="cook_time_minutes" type="number" min="0" max="9999" placeholder="30">
                            </div>
                        </div>
                        <div style="margin-bottom:9px">
                            <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">Categoría</label>
                            <select class="form-control" name="category_id" data-recipes-form-category>
                                <option value="">Sin categoría</option>
                            </select>
                        </div>
                        <div style="margin-bottom:14px" data-recipes-form-status-row style="display:none">
                            <label style="font-size:13px;color:var(--muted);display:block;margin-bottom:4px">Estado</label>
                            <select class="form-control" name="status">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                            </select>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar</button>
                            <button type="button" class="btn-secondary-web" data-recipes-form-cancel-2>Cancelar</button>
                        </div>
                    </form>
                </div>
            </aside>
        </section>

    @elseif($screenKey === 'supplements')
        <section class="workspace" data-user-supplements>
            <div>
                {{-- Herramientas --}}
                <div class="web-tools">
                    <input class="form-control" type="search" data-supp-search placeholder="Buscar por nombre o marca...">
                    <select class="form-control" data-supp-type-filter style="max-width:160px">
                        <option value="">Todos los tipos</option>
                        <option value="protein">Proteína</option>
                        <option value="creatine">Creatina</option>
                        <option value="vitamin">Vitamina</option>
                        <option value="mineral">Mineral</option>
                        <option value="omega">Omega / Aceite</option>
                        <option value="preworkout">Pre-entreno</option>
                        <option value="other">Otro</option>
                    </select>
                </div>

                {{-- Tabla --}}
                <article class="panel" style="padding:16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:8px">
                        <h2 style="margin:0;font-size:16px">Mis suplementos</h2>
                        <span style="font-size:12px;color:#66746b" data-supp-count>0 suplementos</span>
                    </div>
                    <div class="alert" data-supp-message style="display:none"></div>
                    <div style="overflow-x:auto">
                        <table class="web-table">
                            <thead>
                                <tr>
                                    <th>Nombre / Marca</th>
                                    <th>Tipo</th>
                                    <th>Dosis</th>
                                    <th>Frecuencia</th>
                                    <th>Precio unit.</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-supp-list>
                                <tr><td colspan="7" style="text-align:center;color:#66746b;padding:18px">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;margin-top:10px">
                        <button type="button" class="btn-secondary-web btn-sm" data-supp-prev disabled>‹</button>
                        <span style="font-size:12px;color:#66746b" data-supp-page>Pág. 1 / 1</span>
                        <button type="button" class="btn-secondary-web btn-sm" data-supp-next disabled>›</button>
                    </div>
                </article>

                {{-- Panel horarios --}}
                <div data-supp-schedule-panel style="display:none;margin-top:12px">
                    <article class="panel" style="padding:16px">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:8px">
                            <h2 data-sched-title style="margin:0;font-size:16px">Horarios</h2>
                            <div style="display:flex;gap:6px">
                                <button type="button" class="btn-secondary-web btn-sm" data-sched-refresh>Actualizar</button>
                                <button type="button" class="btn-secondary-web btn-sm" data-sched-close>✕ Cerrar</button>
                            </div>
                        </div>
                        <div class="alert" data-sched-message style="display:none"></div>

                        {{-- Lista de horarios --}}
                        <div data-sched-list style="margin-bottom:14px"></div>

                        {{-- Formulario horario --}}
                        <div style="border-top:1px solid #dde6df;padding-top:14px">
                            <h3 data-sched-form-title style="font-size:13px;font-weight:900;margin:0 0 8px">Nuevo horario</h3>
                            <div class="alert" data-sched-form-message style="display:none"></div>
                            <form data-sched-form>
                                <input type="hidden" name="id">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:9px">
                                    <div>
                                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Hora *</label>
                                        <input class="form-control" type="time" name="time">
                                    </div>
                                    <div>
                                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Días *</label>
                                        <select class="form-control" name="day_type">
                                            <option value="every_day">Todos los días</option>
                                            <option value="weekdays">Días de semana</option>
                                            <option value="weekends">Fines de semana</option>
                                            <option value="monday">Lunes</option>
                                            <option value="tuesday">Martes</option>
                                            <option value="wednesday">Miércoles</option>
                                            <option value="thursday">Jueves</option>
                                            <option value="friday">Viernes</option>
                                            <option value="saturday">Sábado</option>
                                            <option value="sunday">Domingo</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-bottom:9px">
                                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Dosis (sobreescribe la base)</label>
                                    <input class="form-control" type="number" name="dose_override" min="0" step="0.01" placeholder="Dejar vacío para usar la dosis base">
                                </div>
                                <div style="margin-bottom:9px">
                                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Estado</label>
                                    <select class="form-control" name="active">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div style="margin-bottom:12px">
                                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Notas</label>
                                    <input class="form-control" type="text" name="notes" placeholder="Ej: Con desayuno">
                                </div>
                                <div style="display:flex;gap:6px">
                                    <button type="submit" class="btn-main btn-sm" data-sched-save>Agregar</button>
                                    <button type="button" class="btn-secondary-web btn-sm" data-sched-reset>Cancelar</button>
                                </div>
                            </form>
                        </div>

                        {{-- Registro de consumo --}}
                        <div style="border-top:1px solid #dde6df;padding-top:14px;margin-top:14px">
                            <h3 style="font-size:13px;font-weight:900;margin:0 0 8px">Registrar consumo</h3>
                            <div class="alert" data-log-message style="display:none"></div>
                            <form data-log-form>
                                <div style="margin-bottom:9px">
                                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Fecha y hora</label>
                                    <input class="form-control" type="datetime-local" name="taken_at">
                                </div>
                                <div style="margin-bottom:9px">
                                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Dosis tomada</label>
                                    <input class="form-control" type="number" name="dose_actual" min="0" step="0.01" placeholder="Dejar vacío para usar la dosis base">
                                </div>
                                <div style="margin-bottom:12px">
                                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Notas</label>
                                    <input class="form-control" type="text" name="log_notes" placeholder="Ej: Tomé con leche">
                                </div>
                                <button type="submit" class="btn-main btn-sm" data-log-save>Registrar consumo</button>
                            </form>
                        </div>
                    </article>
                </div>
            </div>

            {{-- Formulario lateral --}}
            <aside class="aside-panel">
                <h2 data-supp-form-title style="font-size:15px;margin-bottom:10px">Nuevo suplemento</h2>
                <div class="alert" data-supp-form-message style="display:none"></div>
                <form data-supp-form>
                    <input type="hidden" name="id">
                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Nombre *</label>
                        <input class="form-control" type="text" name="name" placeholder="Ej: Whey Protein Gold Standard">
                    </div>
                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Tipo *</label>
                        <select class="form-control" name="type">
                            <option value="">Seleccionar...</option>
                            <option value="protein">Proteína</option>
                            <option value="creatine">Creatina</option>
                            <option value="vitamin">Vitamina</option>
                            <option value="mineral">Mineral</option>
                            <option value="omega">Omega / Aceite</option>
                            <option value="preworkout">Pre-entreno</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Marca</label>
                        <input class="form-control" type="text" name="brand" placeholder="Ej: Optimum Nutrition">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:9px">
                        <div>
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Dosis</label>
                            <input class="form-control" type="number" name="dose" min="0" step="0.01" placeholder="30">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Unidad</label>
                            <input class="form-control" type="text" name="unit" placeholder="g, mg, ml...">
                        </div>
                    </div>
                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Frecuencia</label>
                        <select class="form-control" name="frequency">
                            <option value="">Sin especificar</option>
                            <option value="daily">Diaria</option>
                            <option value="twice_daily">2 veces por día</option>
                            <option value="weekly">Semanal</option>
                            <option value="post_workout">Post-entrenamiento</option>
                            <option value="pre_workout">Pre-entrenamiento</option>
                            <option value="with_meals">Con comidas</option>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:9px">
                        <div>
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Precio unit. ($)</label>
                            <input class="form-control" type="number" name="price_per_unit" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Stock actual</label>
                            <input class="form-control" type="number" name="stock_quantity" min="0" step="0.01" placeholder="0">
                        </div>
                    </div>
                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Estado</label>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                    <div style="margin-bottom:14px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Notas</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Observaciones..." style="resize:vertical"></textarea>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn-main" data-supp-save>Guardar</button>
                        <button type="button" class="btn-secondary-web" data-supp-reset>Cancelar</button>
                    </div>
                </form>
            </aside>
        </section>

    @elseif($screenKey === 'budget')
        <section class="workspace" data-user-budget>
            <div>
                <article class="panel">
                    <h2>Presupuestos mensuales</h2>
                    <div class="alert" data-budget-message style="display:none"></div>
                    <div class="web-tools" style="flex-wrap:wrap;gap:8px">
                        <select class="form-control" data-budget-group style="max-width:220px">
                            <option value="">Cargando grupos...</option>
                        </select>
                        <button type="button" class="btn-secondary-web" data-budget-refresh>Actualizar</button>
                        <span class="chip" data-budget-count>0 presupuestos</span>
                    </div>
                    <div style="overflow:auto;margin-top:10px">
                        <table class="web-table">
                            <thead>
                                <tr>
                                    <th>Período</th>
                                    <th>Monto</th>
                                    <th>Gastado</th>
                                    <th>Disponible</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-budget-body>
                                <tr><td colspan="5" class="muted">Seleccioná un grupo familiar.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;margin-top:10px">
                        <button type="button" class="btn-secondary-web btn-sm" data-budget-prev disabled>‹ Anterior</button>
                        <span data-budget-page style="font-size:13px;color:#66746b">Pág. 1 / 1</span>
                        <button type="button" class="btn-secondary-web btn-sm" data-budget-next disabled>Siguiente ›</button>
                    </div>
                </article>

                {{-- Panel resumen / proyección (se muestra al hacer click en "Resumen") --}}
                <div data-budget-summary-panel style="display:none;margin-top:12px">
                    <article class="panel">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
                            <h2 data-budget-summary-title style="margin:0;font-size:16px">Resumen</h2>
                            <button type="button" class="btn-secondary-web btn-sm" data-budget-summary-close>✕ Cerrar</button>
                        </div>
                        <div class="alert" data-budget-summary-message style="display:none"></div>

                        {{-- Métricas principales --}}
                        <div data-budget-summary-metrics style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:14px"></div>

                        {{-- Barras de progreso --}}
                        <div data-budget-summary-bars style="margin-bottom:14px"></div>

                        {{-- Desglose por categoría --}}
                        <div data-budget-summary-categories></div>

                        {{-- Proyección --}}
                        <div data-budget-projection style="margin-top:14px;padding-top:14px;border-top:1px solid #dde6df">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                                <h3 style="margin:0;font-size:14px;font-weight:900">Proyección</h3>
                                <button type="button" class="btn-secondary-web btn-sm" data-budget-reload-projection>Recalcular</button>
                            </div>
                            <div data-budget-projection-content>
                                <p class="muted" style="font-size:13px;margin:0">Cargando proyección...</p>
                            </div>
                        </div>
                    </article>
                </div>

                {{-- Panel categorías (se muestra al hacer click en "Categorías") --}}
                <div data-budget-categories-panel style="display:none;margin-top:12px">
                    <article class="panel">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                            <h2 data-budget-cats-title style="margin:0;font-size:16px">Categorías</h2>
                            <button type="button" class="btn-secondary-web btn-sm" data-budget-cats-close>✕ Cerrar</button>
                        </div>
                        <div data-budget-cats-allocation style="font-size:12px;color:#66746b;margin-bottom:12px"></div>
                        <div class="alert" data-budget-cats-message style="display:none"></div>
                        <div data-budget-cats-list style="margin-bottom:16px"></div>

                        {{-- Formulario inline agregar / editar categoría --}}
                        <div style="border-top:1px solid #dde6df;padding-top:14px">
                            <h3 data-budget-cat-form-title style="font-size:13px;font-weight:900;margin:0 0 10px">Agregar categoría</h3>
                            <div class="alert" data-budget-cat-form-message style="display:none"></div>
                            <form data-budget-cat-form>
                                <input type="hidden" name="id">
                                <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:8px;align-items:end">
                                    <div>
                                        <label style="font-size:11px;font-weight:700;display:block;margin-bottom:3px">Nombre *</label>
                                        <input class="form-control" type="text" name="name" placeholder="Ej: Carnes, Lácteos..." maxlength="80">
                                    </div>
                                    <div>
                                        <label style="font-size:11px;font-weight:700;display:block;margin-bottom:3px">Color</label>
                                        <input type="color" name="color" value="#04ac85" style="height:38px;padding:2px;border:1px solid #dde6df;border-radius:4px;cursor:pointer">
                                    </div>
                                </div>
                                <div style="margin-bottom:10px">
                                    <label style="font-size:11px;font-weight:700;display:block;margin-bottom:3px">Monto asignado *</label>
                                    <input class="form-control" type="number" name="allocated_amount" min="0" step="0.01" placeholder="0.00">
                                </div>
                                <div style="display:flex;gap:8px">
                                    <button type="submit" class="btn-main btn-sm" data-budget-cat-save>Guardar</button>
                                    <button type="button" class="btn-secondary-web btn-sm" data-budget-cat-reset>Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </article>
                </div>

                {{-- Panel movimientos --}}
                <div data-budget-movements-panel style="display:none;margin-top:12px">
                    <article class="panel">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                            <h2 data-budget-movs-title style="margin:0;font-size:16px">Movimientos</h2>
                            <button type="button" class="btn-secondary-web btn-sm" data-budget-movs-close>✕ Cerrar</button>
                        </div>
                        <div class="alert" data-budget-movs-message style="display:none"></div>

                        {{-- Filtros --}}
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;align-items:center">
                            <select class="form-control" data-movs-type-filter style="max-width:160px;font-size:13px">
                                <option value="">Todos los tipos</option>
                                <option value="purchase">Compra real</option>
                                <option value="planned">Planificada</option>
                                <option value="reserve">Reserva</option>
                                <option value="release">Liberación</option>
                                <option value="adjustment">Ajuste</option>
                            </select>
                            <button type="button" class="btn-secondary-web btn-sm" data-movs-refresh>Actualizar</button>
                            <span class="chip" data-movs-count style="font-size:11px">0 movimientos</span>
                        </div>

                        {{-- Lista --}}
                        <div data-budget-movs-list style="margin-bottom:10px"></div>

                        {{-- Paginación --}}
                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
                            <button type="button" class="btn-secondary-web btn-sm" data-movs-prev disabled>‹</button>
                            <span data-movs-page style="font-size:12px;color:#66746b">Pág. 1 / 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-movs-next disabled>›</button>
                        </div>

                        {{-- Ajuste manual --}}
                        <div style="border-top:1px solid #dde6df;padding-top:14px">
                            <h3 style="font-size:13px;font-weight:900;margin:0 0 10px">Registrar ajuste</h3>
                            <div class="alert" data-movs-adj-message style="display:none"></div>
                            <form data-budget-adj-form>
                                <div style="margin-bottom:8px">
                                    <label style="font-size:11px;font-weight:700;display:block;margin-bottom:3px">Tipo *</label>
                                    <select class="form-control" name="type" style="font-size:13px">
                                        <option value="adjustment">Ajuste manual</option>
                                        <option value="reserve">Reserva</option>
                                        <option value="release">Liberación</option>
                                    </select>
                                </div>
                                <div style="margin-bottom:8px">
                                    <label style="font-size:11px;font-weight:700;display:block;margin-bottom:3px">
                                        Monto * <span style="font-weight:400;color:#66746b">(negativo = gasto, positivo = crédito)</span>
                                    </label>
                                    <input class="form-control" type="number" name="amount" step="0.01" placeholder="Ej: -500 o 200">
                                </div>
                                <div style="margin-bottom:8px">
                                    <label style="font-size:11px;font-weight:700;display:block;margin-bottom:3px">Descripción *</label>
                                    <input class="form-control" type="text" name="description" maxlength="200" placeholder="Motivo del ajuste...">
                                </div>
                                <div style="margin-bottom:12px">
                                    <label style="font-size:11px;font-weight:700;display:block;margin-bottom:3px">Categoría</label>
                                    <select class="form-control" name="category_id" data-movs-adj-category style="font-size:13px">
                                        <option value="">Sin categoría</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-main btn-sm" data-movs-adj-save>Registrar ajuste</button>
                            </form>
                        </div>
                    </article>
                </div>

                {{-- Panel alertas --}}
                <div data-budget-alerts-panel style="display:none;margin-top:12px">
                    <article class="panel" style="padding:16px">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:8px">
                            <h2 data-budget-alerts-title style="margin:0;font-size:16px">Alertas</h2>
                            <div style="display:flex;gap:6px">
                                <button type="button" class="btn-secondary-web btn-sm" data-alerts-refresh>Actualizar</button>
                                <button type="button" class="btn-secondary-web btn-sm" data-budget-alerts-close>✕ Cerrar</button>
                            </div>
                        </div>
                        <div class="alert" data-budget-alerts-message style="display:none"></div>
                        <div data-budget-alerts-list></div>
                    </article>
                </div>
            </div>

            <aside class="aside-panel">
                {{-- Presupuesto actual --}}
                <div style="margin-bottom:16px">
                    <h2 style="font-size:15px;margin-bottom:10px">Mes actual</h2>
                    <div data-budget-current>
                        <p class="muted" style="font-size:13px;margin:0">Seleccioná un grupo para ver el presupuesto actual.</p>
                    </div>
                </div>

                {{-- Formulario crear / editar --}}
                <div style="border-top:1px solid #dde6df;padding-top:16px">
                    <h2 data-budget-form-title style="font-size:15px;margin-bottom:10px">Nuevo presupuesto</h2>
                    <div class="alert" data-budget-form-message style="display:none"></div>
                    <form data-budget-form>
                        <input type="hidden" name="id">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:9px">
                            <div>
                                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Mes *</label>
                                <select class="form-control" name="month">
                                    <option value="1">Enero</option>
                                    <option value="2">Febrero</option>
                                    <option value="3">Marzo</option>
                                    <option value="4">Abril</option>
                                    <option value="5">Mayo</option>
                                    <option value="6">Junio</option>
                                    <option value="7">Julio</option>
                                    <option value="8">Agosto</option>
                                    <option value="9">Septiembre</option>
                                    <option value="10">Octubre</option>
                                    <option value="11">Noviembre</option>
                                    <option value="12">Diciembre</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Año *</label>
                                <input class="form-control" type="number" name="year" min="2020" max="2099" step="1" placeholder="{{ date('Y') }}">
                            </div>
                        </div>
                        <div style="margin-bottom:9px">
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Monto *</label>
                            <input class="form-control" type="number" name="amount" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div style="margin-bottom:9px">
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Moneda</label>
                            <input class="form-control" type="text" name="currency" maxlength="10" placeholder="ARS">
                        </div>
                        <div style="margin-bottom:14px">
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Notas</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Observaciones opcionales..." style="resize:vertical"></textarea>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="submit" class="btn-main" data-budget-save>Guardar</button>
                            <button type="button" class="btn-secondary-web" data-budget-reset>Cancelar</button>
                        </div>
                    </form>
                </div>
            </aside>
        </section>

    @elseif($screenKey === 'shopping-list')
        <section data-user-shopping-lists>
            <div class="alert" data-shopping-lists-message style="display:none"></div>
            <div class="family-layout">
                <div class="family-stack">
                    <article class="panel">
                        <h2>Listas de compras</h2>
                        <div class="web-tools">
                            <select class="form-control" data-shopping-list-group>
                                <option value="">Grupo familiar</option>
                            </select>
                            <select class="form-control" data-shopping-list-status>
                                <option value="">Todos los estados</option>
                                <option value="draft">Borrador</option>
                                <option value="active">Activa</option>
                                <option value="completed">Completada</option>
                                <option value="cancelled">Cancelada</option>
                            </select>
                            <select class="form-control" data-shopping-list-source>
                                <option value="">Todos los origenes</option>
                                <option value="manual">Manual</option>
                                <option value="meal_plan">Planificacion</option>
                                <option value="history">Historico</option>
                            </select>
                            <button type="button" class="btn-secondary-web" data-shopping-list-refresh>Actualizar</button>
                            <span class="chip" data-shopping-list-count>0 listas</span>
                        </div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Origen</th>
                                        <th>Estado</th>
                                        <th>Plan</th>
                                        <th>Estimado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-shopping-list-body>
                                    <tr><td colspan="6" class="muted">Selecciona un grupo familiar.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="catalog-pagination">
                            <button type="button" class="btn-secondary-web btn-sm" data-shopping-list-prev>Anterior</button>
                            <span class="muted" data-shopping-list-page>Pagina 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-shopping-list-next>Siguiente</button>
                        </div>
                    </article>

                    <article class="panel">
                        <h2>Detalle</h2>
                        <div data-shopping-list-detail class="muted">Selecciona una lista para ver sus items.</div>
                    </article>
                </div>

                <aside class="panel">
                    <h2>Generar lista</h2>
                    <form class="family-form" data-shopping-list-generate-plan-form>
                        <select class="form-control" name="meal_plan_id" data-shopping-list-generate-plan required>
                            <option value="">Plan para generar lista</option>
                        </select>
                        <button type="submit" class="btn-main">Generar desde menu</button>
                    </form>
                    <form class="family-form" data-shopping-list-generate-history-form style="margin-top:14px">
                        <input class="form-control" name="date_from" type="date" aria-label="Desde">
                        <input class="form-control" name="date_to" type="date" aria-label="Hasta">
                        <button type="submit" class="btn-secondary-web">Generar desde historico</button>
                    </form>

                    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">

                    <h2 data-shopping-list-form-title>Crear lista</h2>
                    <form class="family-form" data-shopping-list-form>
                        <input type="hidden" name="id">
                        <select class="form-control" name="source_type">
                            <option value="manual">Manual</option>
                            <option value="meal_plan">Desde plan</option>
                            <option value="history">Desde historico</option>
                        </select>
                        <select class="form-control" name="meal_plan_id" data-shopping-list-plan>
                            <option value="">Plan asociado opcional</option>
                        </select>
                        <select class="form-control" name="status">
                            <option value="draft">Borrador</option>
                            <option value="active">Activa</option>
                            <option value="completed">Completada</option>
                            <option value="cancelled">Cancelada</option>
                        </select>
                        <input class="form-control" name="optimization_mode" type="text" maxlength="60" placeholder="Modo de optimizacion opcional">
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar lista</button>
                            <button type="button" class="btn-secondary-web" data-shopping-list-reset>Limpiar</button>
                        </div>
                    </form>

                    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">

                    <h2 data-shopping-list-item-form-title>Agregar item</h2>
                    <form class="family-form" data-shopping-list-item-form>
                        <input type="hidden" name="id">
                        <select class="form-control" name="ingredient_id" data-shopping-list-item-ingredient>
                            <option value="">Ingrediente opcional</option>
                        </select>
                        <select class="form-control" name="product_id" data-shopping-list-item-product>
                            <option value="">Producto opcional</option>
                        </select>
                        <input class="form-control" name="quantity" type="number" min="0.0001" step="0.0001" placeholder="Cantidad" required>
                        <select class="form-control" name="unit_id" data-shopping-list-item-unit required>
                            <option value="">Unidad</option>
                        </select>
                        <input class="form-control" name="estimated_price" type="number" min="0" step="0.01" placeholder="Precio estimado">
                        <input class="form-control" name="actual_price" type="number" min="0" step="0.01" placeholder="Precio real">
                        <select class="form-control" name="status">
                            <option value="pending">Pendiente</option>
                            <option value="purchased">Comprado</option>
                            <option value="skipped">Omitido</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                        <textarea class="form-control" name="notes" rows="3" maxlength="1000" placeholder="Notas"></textarea>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar item</button>
                            <button type="button" class="btn-secondary-web" data-shopping-list-item-reset>Limpiar</button>
                        </div>
                    </form>
                </aside>
            </div>
        </section>

    @elseif($screenKey === 'planning')
        <section data-user-meal-plans>
            <div class="alert" data-meal-plans-message style="display:none"></div>
            <div class="family-layout">
                <div class="family-stack">
                    <article class="panel">
                        <h2>Calendario de comidas</h2>
                        <div class="web-tools">
                            <select class="form-control" data-meal-plan-group>
                                <option value="">Grupo familiar</option>
                            </select>
                            <select class="form-control" data-meal-plan-period>
                                <option value="">Todos los periodos</option>
                                <option value="daily">Diario</option>
                                <option value="weekly">Semanal</option>
                                <option value="monthly">Mensual</option>
                            </select>
                            <select class="form-control" data-meal-plan-status>
                                <option value="">Todos los estados</option>
                                <option value="draft">Borrador</option>
                                <option value="approved">Aprobado</option>
                                <option value="active">Activo</option>
                                <option value="archived">Archivado</option>
                            </select>
                            <input class="form-control" type="date" data-meal-plan-from aria-label="Desde">
                            <input class="form-control" type="date" data-meal-plan-to aria-label="Hasta">
                            <button type="button" class="btn-secondary-web" data-meal-plan-refresh>Actualizar</button>
                            <span class="chip" data-meal-plan-count>0 planes</span>
                        </div>
                        <div style="overflow:auto">
                            <table class="web-table">
                                <thead>
                                    <tr>
                                        <th>Periodo</th>
                                        <th>Fechas</th>
                                        <th>Estado</th>
                                        <th>Items</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-meal-plan-body>
                                    <tr><td colspan="5" class="muted">Selecciona un grupo familiar.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="catalog-pagination">
                            <button type="button" class="btn-secondary-web btn-sm" data-meal-plan-prev>Anterior</button>
                            <span class="muted" data-meal-plan-page>Pagina 1</span>
                            <button type="button" class="btn-secondary-web btn-sm" data-meal-plan-next>Siguiente</button>
                        </div>
                    </article>

                    <article class="panel">
                        <h2>Detalle del plan</h2>
                        <div data-meal-plan-detail class="muted">Selecciona un plan para ver sus comidas.</div>
                    </article>

                    <article class="panel">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                            <h2 style="margin:0">Alertas alimentarias</h2>
                            <button type="button" class="btn-secondary-web btn-sm" data-meal-plan-check-incompatibilities>Revisar incompatibilidades</button>
                        </div>
                        <div data-meal-plan-incompatibilities class="muted" style="margin-top:12px">Selecciona un plan para ver sus alertas.</div>
                    </article>

                    <article class="panel">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                            <h2 style="margin:0">Vista previa de compra</h2>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <button type="button" class="btn-secondary-web btn-sm" data-meal-plan-shopping-preview>Calcular faltantes</button>
                                <button type="button" class="btn-main btn-sm" data-meal-plan-shopping-generate>Generar lista</button>
                            </div>
                        </div>
                        <div data-meal-plan-shopping-preview-panel class="muted" style="margin-top:12px">Selecciona un plan para calcular faltantes.</div>
                    </article>

                    <article class="panel">
                        <h2 data-meal-plan-portions-title>Porciones por persona</h2>
                        <div data-meal-plan-portions-panel class="muted">Selecciona una comida para gestionar porciones.</div>
                    </article>
                </div>

                <aside class="panel">
                    <h2>Generacion automatica</h2>
                    <form class="family-form" data-meal-plan-generate-form>
                        <select class="form-control" name="period_type" required>
                            <option value="weekly">Semanal</option>
                            <option value="daily">Diario</option>
                            <option value="monthly">Mensual</option>
                        </select>
                        <input class="form-control" name="start_date" type="date" required>
                        <input class="form-control" name="end_date" type="date" required>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
                            <button type="submit" class="btn-main" data-meal-plan-generate-submit>Generar menu</button>
                            <button type="button" class="btn-secondary-web" data-meal-plan-approve>Aprobar seleccionado</button>
                            <button type="button" class="btn-secondary-web" data-meal-plan-regenerate>Regenerar seleccionado</button>
                        </div>
                    </form>

                    <h2 data-meal-plan-form-title>Crear plan</h2>
                    <form class="family-form" data-meal-plan-form>
                        <input type="hidden" name="id">
                        <select class="form-control" name="period_type" required>
                            <option value="weekly">Semanal</option>
                            <option value="daily">Diario</option>
                            <option value="monthly">Mensual</option>
                        </select>
                        <input class="form-control" name="start_date" type="date" required>
                        <input class="form-control" name="end_date" type="date" required>
                        <input class="form-control" name="mode" type="text" maxlength="50" placeholder="Modo opcional">

                        <h2 style="margin-top:16px">Item rapido</h2>
                        <input class="form-control" name="item_date" type="date">
                        <select class="form-control" name="meal_type_id" data-meal-plan-meal-type>
                            <option value="">Tipo de comida</option>
                        </select>
                        <select class="form-control" name="recipe_id" data-meal-plan-recipe>
                            <option value="">Receta opcional</option>
                        </select>
                        <input class="form-control" name="free_meal_description" type="text" maxlength="500" placeholder="Comida libre">
                        <input class="form-control" name="servings_total" type="number" min="0" step="0.1" placeholder="Porciones">
                        <textarea class="form-control" name="notes" rows="2" maxlength="1000" placeholder="Notas"></textarea>
                        <label class="checkbox-card" style="margin-bottom:9px">
                            <input type="checkbox" name="is_eating_out" value="1">
                            <span>Comer afuera</span>
                        </label>

                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar plan</button>
                            <button type="button" class="btn-secondary-web" data-meal-plan-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px" data-meal-plan-item-form-title>Agregar comida al calendario</h2>
                    <form class="family-form" data-meal-plan-item-form>
                        <input type="hidden" name="item_id">
                        <input class="form-control" name="date" type="date" required>
                        <select class="form-control" name="meal_type_id" data-meal-plan-item-meal-type required>
                            <option value="">Tipo de comida</option>
                        </select>
                        <select class="form-control" name="recipe_id" data-meal-plan-item-recipe>
                            <option value="">Receta opcional</option>
                        </select>
                        <input class="form-control" name="free_meal_description" type="text" maxlength="500" placeholder="Comida libre o descripcion">
                        <input class="form-control" name="servings_total" type="number" min="0" step="0.1" placeholder="Porciones">
                        <textarea class="form-control" name="notes" rows="2" maxlength="1000" placeholder="Notas"></textarea>
                        <label class="checkbox-card" style="margin-bottom:9px">
                            <input type="checkbox" name="is_eating_out" value="1">
                            <span>Comer afuera</span>
                        </label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar comida</button>
                            <button type="button" class="btn-secondary-web" data-meal-plan-item-reset>Limpiar comida</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px" data-meal-plan-portion-form-title>Asignar porcion</h2>
                    <form class="family-form" data-meal-plan-portion-form>
                        <input type="hidden" name="portion_id">
                        <select class="form-control" name="user_id" data-meal-plan-portion-user required>
                            <option value="">Miembro del grupo</option>
                        </select>
                        <input class="form-control" name="portion_factor" type="number" min="0.01" step="0.01" placeholder="Factor de porcion">
                        <input class="form-control" name="servings" type="number" min="0" step="0.1" placeholder="Porciones">
                        <textarea class="form-control" name="notes" rows="2" maxlength="1000" placeholder="Notas"></textarea>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar porcion</button>
                            <button type="button" class="btn-secondary-web" data-meal-plan-portion-reset>Limpiar porcion</button>
                        </div>
                    </form>
                </aside>
            </div>
        </section>

    @elseif($screenKey === 'recipe-search')
        <section class="workspace" data-recipe-search>
            {{-- Main: results --}}
            <div style="display:flex;flex-direction:column;gap:14px">
                <article class="panel">
                    <h2>Buscar recetas</h2>
                    <div class="web-tools" style="margin-bottom:10px">
                        <input class="form-control" type="search" data-rs-search placeholder="Nombre de la receta..." style="flex:1;min-width:180px">
                        <button type="button" class="btn-main btn-sm" data-rs-btn>Buscar</button>
                        <button type="button" class="btn-secondary-web btn-sm" data-rs-clear>Limpiar</button>
                        <span class="chip" data-rs-count style="display:none">0 resultados</span>
                    </div>
                    <div data-rs-message style="display:none;font-size:13px;padding:7px 10px;border-radius:4px;margin-bottom:10px"></div>
                    <div data-rs-list style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:4px">
                        <p class="muted" style="font-size:13px">Usá los filtros para buscar recetas.</p>
                    </div>
                    <div class="catalog-pagination" style="margin-top:12px" data-rs-pagination>
                        <button type="button" class="btn-secondary-web btn-sm" data-rs-prev disabled>Anterior</button>
                        <span class="muted" data-rs-page>Pág 1</span>
                        <button type="button" class="btn-secondary-web btn-sm" data-rs-next disabled>Siguiente</button>
                    </div>
                </article>
            </div>

            {{-- Aside: filters + detail --}}
            <aside style="display:flex;flex-direction:column;gap:14px">
                <div class="aside-panel">
                    <h2>Filtros</h2>

                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:3px">Categoría</label>
                        <select class="form-control" data-rs-category>
                            <option value="">Todas las categorías</option>
                        </select>
                    </div>

                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:3px">Dificultad</label>
                        <select class="form-control" data-rs-difficulty>
                            <option value="">Cualquier dificultad</option>
                            <option value="easy">Fácil</option>
                            <option value="medium">Media</option>
                            <option value="hard">Difícil</option>
                        </select>
                    </div>

                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:3px">Tiempo total máx. (min)</label>
                        <input class="form-control" type="number" min="1" data-rs-max-time placeholder="ej. 30">
                    </div>

                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:3px">Fuente</label>
                        <select class="form-control" data-rs-source>
                            <option value="">Todas</option>
                            <option value="official">Oficiales</option>
                            <option value="user">De usuario</option>
                        </select>
                    </div>

                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:3px">Incluir ingrediente</label>
                        <div style="position:relative">
                            <input class="form-control" type="text" data-rs-inc-input placeholder="Buscar ingrediente..." autocomplete="off" style="width:100%">
                            <div data-rs-inc-results style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #dde3e8;border-radius:4px;z-index:20;max-height:160px;overflow-y:auto"></div>
                        </div>
                        <div data-rs-inc-chips style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px"></div>
                    </div>

                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:3px">Excluir ingrediente</label>
                        <div style="position:relative">
                            <input class="form-control" type="text" data-rs-exc-input placeholder="Buscar ingrediente..." autocomplete="off" style="width:100%">
                            <div data-rs-exc-results style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #dde3e8;border-radius:4px;z-index:20;max-height:160px;overflow-y:auto"></div>
                        </div>
                        <div data-rs-exc-chips style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px"></div>
                    </div>

                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:3px">Tags</label>
                        <div data-rs-tag-chips style="display:flex;flex-wrap:wrap;gap:5px;max-height:120px;overflow-y:auto"></div>
                    </div>

                    <div style="display:flex;gap:8px;margin-top:4px">
                        <button type="button" class="btn-main btn-sm" style="flex:1" data-rs-apply>Aplicar</button>
                        <button type="button" class="btn-secondary-web btn-sm" data-rs-reset>Reset</button>
                    </div>
                </div>

                <div class="aside-panel" data-rs-detail style="display:none">
                    <p class="muted" style="font-size:13px">Seleccioná una receta para ver el detalle.</p>
                </div>
            </aside>
        </section>

    @elseif($screenKey === 'recipe-favorites')
        <section class="workspace" data-user-fav>
            <div style="display:flex;flex-direction:column;gap:14px">
                <article class="panel" style="min-height:360px">
                    <h2>Mis recetas</h2>
                    <div data-fav-tabs class="audit-tabs"></div>
                    <div data-fav-message style="display:none;font-size:13px;padding:7px 10px;border-radius:4px;margin-bottom:10px"></div>
                    <div data-fav-list style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:4px">
                        <p class="muted" style="font-size:13px">Cargando...</p>
                    </div>
                    <div class="catalog-pagination" style="margin-top:12px;display:none" data-fav-pagination>
                        <button type="button" class="btn-secondary-web btn-sm" data-fav-prev disabled>Anterior</button>
                        <span class="muted" data-fav-page>Pág 1</span>
                        <button type="button" class="btn-secondary-web btn-sm" data-fav-next disabled>Siguiente</button>
                    </div>
                </article>
            </div>
            <aside class="aside-panel" data-fav-detail>
                <p class="muted" style="font-size:13px">Seleccioná una receta para ver el detalle.</p>
            </aside>
        </section>

    @elseif($screenKey === 'recipe-suggestions')
        <section class="workspace" data-recipe-sugg>
            <div style="display:flex;flex-direction:column;gap:14px">
                <article class="panel" style="min-height:400px">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">
                        <h2 style="margin:0">Recomendaciones</h2>
                        <select class="form-control" data-sugg-group style="max-width:200px;display:none;font-size:13px"></select>
                    </div>
                    <div data-sugg-tabs class="audit-tabs"></div>
                    <div data-sugg-extra style="margin-bottom:8px"></div>
                    <div data-sugg-message style="display:none;font-size:13px;padding:7px 10px;border-radius:4px;margin-bottom:10px"></div>
                    <div data-sugg-list style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:4px">
                        <p class="muted" style="font-size:13px">Cargando recomendaciones...</p>
                    </div>
                    <div class="catalog-pagination" style="margin-top:12px;display:none" data-sugg-pagination>
                        <button type="button" class="btn-secondary-web btn-sm" data-sugg-prev disabled>Anterior</button>
                        <span class="muted" data-sugg-page>Pág 1</span>
                        <button type="button" class="btn-secondary-web btn-sm" data-sugg-next disabled>Siguiente</button>
                    </div>
                </article>
            </div>
            <aside class="aside-panel" data-sugg-detail>
                <p class="muted" style="font-size:13px">Seleccioná una receta para ver el detalle.</p>
            </aside>
        </section>

    @elseif($screenKey === 'shopping-session')
        <section data-user-shopping-session>
            {{-- Setup --}}
            <div data-session-setup>
                <article class="panel" style="max-width:520px">
                    <h2>Sesión de compra</h2>
                    <div class="alert" data-session-message style="display:none"></div>
                    <div style="display:grid;gap:10px">
                        <label style="font-size:13px;font-weight:700;margin-bottom:2px">Grupo familiar</label>
                        <select class="form-control" data-session-group>
                            <option value="">Cargando grupos...</option>
                        </select>
                        <label style="font-size:13px;font-weight:700;margin-bottom:2px">Lista de compras activa</label>
                        <select class="form-control" data-session-list>
                            <option value="">Primero seleccioná un grupo</option>
                        </select>
                        <button type="button" class="btn-main" data-session-start style="margin-top:6px">Iniciar compra</button>
                    </div>
                </article>
            </div>

            {{-- Sesión activa --}}
            <div data-session-active style="display:none">
                <div style="background:#fff;border:1px solid #dde6df;border-radius:8px;padding:14px 16px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <div>
                        <div class="muted" style="font-size:11px;text-transform:uppercase;font-weight:700">Sesión activa</div>
                        <strong data-session-title style="font-size:15px">Lista</strong>
                    </div>
                    <div style="text-align:right">
                        <div class="muted" style="font-size:11px;text-transform:uppercase;font-weight:700">Total acumulado</div>
                        <strong data-session-total style="font-size:22px;color:#04ac85">$0.00</strong>
                    </div>
                </div>

                <div style="display:grid;gap:12px">
                    {{-- Escanear --}}
                    <article class="panel">
                        <h2 style="font-size:15px;margin-bottom:10px">Escanear producto</h2>
                        <div class="alert" data-scan-message style="display:none"></div>
                        <div style="display:flex;gap:8px;margin-bottom:10px">
                            <input type="text" class="form-control" data-session-barcode
                                placeholder="Código de barras..."
                                inputmode="numeric"
                                autocomplete="off"
                                style="font-size:18px;font-weight:700;letter-spacing:2px;flex:1">
                            <button type="button" class="btn-main" data-session-scan-btn>Buscar</button>
                            <button type="button" class="btn-secondary-web" data-session-camera-btn title="Usar cámara" style="padding:10px 12px">📷</button>
                        </div>
                        <div data-session-camera-container style="display:none;border-radius:8px;overflow:hidden;background:#000;position:relative;max-width:100%;aspect-ratio:4/3">
                            <video data-session-video autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;display:block"></video>
                            <button type="button" data-session-camera-close
                                style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.65);color:#fff;border:0;border-radius:50%;width:32px;height:32px;font-size:16px;cursor:pointer;line-height:1">✕</button>
                            <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.4);color:#fff;font-size:12px;text-align:center;padding:6px">
                                Apuntá la cámara al código de barras
                            </div>
                        </div>
                        <div data-session-scan-result style="display:none"></div>
                    </article>

                    {{-- Items --}}
                    <article class="panel">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                            <h2 style="margin:0;font-size:15px">Items de la lista</h2>
                            <span data-session-items-count class="chip">0 pendientes</span>
                        </div>
                        <div data-session-items-list></div>
                        <div style="margin-top:14px;padding-top:14px;border-top:1px solid #dde6df;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
                            <button type="button" class="btn-secondary-web" data-session-cancel>Cancelar sesión</button>
                            <button type="button" class="btn-main" data-session-finish>Confirmar compra</button>
                        </div>
                    </article>
                </div>
            </div>

            {{-- Finalizado --}}
            <div data-session-done style="display:none">
                <article class="panel" style="max-width:480px">
                    <div data-session-summary></div>
                    <div style="margin-top:16px">
                        <button type="button" class="btn-main" data-session-new>Nueva sesión</button>
                    </div>
                </article>
            </div>
        </section>

    @elseif($screenKey === 'purchases')
        <section class="workspace" data-user-purchases>
            <div>
                <article class="panel">
                    <h2>Ítems de compra</h2>
                    <div class="alert" data-purchases-message style="display:none"></div>
                    <div class="web-tools" style="flex-wrap:wrap;gap:8px">
                        <select class="form-control" data-purchases-group style="max-width:200px">
                            <option value="">Cargando grupos...</option>
                        </select>
                        <input class="form-control" type="number" data-purchases-id
                            placeholder="ID de la compra..." min="1" step="1" style="max-width:180px">
                        <button type="button" class="btn-main" data-purchases-load>Cargar ítems</button>
                        <span class="chip" data-purchases-count>0 ítems</span>
                    </div>
                    <div style="overflow:auto;margin-top:10px">
                        <table class="web-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio unit.</th>
                                    <th>Total</th>
                                    <th>Vencimiento</th>
                                    <th>Stock</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-purchases-body>
                                <tr><td colspan="7" class="muted">Ingresá el ID de una compra para ver sus ítems.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>

            <aside class="aside-panel">
                <h2 data-purchases-form-title>Agregar ítem</h2>
                <div class="alert" data-purchases-form-message style="display:none"></div>
                <form data-purchases-item-form>
                    <input type="hidden" name="id">
                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Producto *</label>
                        <select class="form-control" name="product_id" data-item-product>
                            <option value="">Seleccioná un producto</option>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:9px">
                        <div>
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Cantidad</label>
                            <input class="form-control" type="number" name="quantity" data-item-quantity min="0" step="0.001" placeholder="0">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Unidad</label>
                            <select class="form-control" name="unit_id" data-item-unit>
                                <option value="">Sin unidad</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Precio unitario</label>
                        <input class="form-control" type="number" name="unit_price" data-item-unit-price min="0" step="0.01" placeholder="0.00">
                        <div data-item-total-preview style="font-size:12px;color:#04ac85;font-weight:700;margin-top:4px"></div>
                    </div>
                    <div style="margin-bottom:9px">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Fecha de vencimiento</label>
                        <input class="form-control" type="date" name="expiry_date">
                    </div>
                    <div style="margin-bottom:14px;display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="add_to_stock" id="purchases-add-to-stock" value="1">
                        <label for="purchases-add-to-stock" style="font-size:13px;cursor:pointer;margin:0">
                            Ingresar al stock del hogar
                        </label>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn-main" data-purchases-save>Guardar</button>
                        <button type="button" class="btn-secondary-web" data-purchases-reset>Cancelar</button>
                    </div>
                </form>

                {{-- Panel: confirmar compra y stock --}}
                <div data-purchases-confirm-panel style="display:none;margin-top:20px;padding-top:16px;border-top:1px solid #dde6df">
                    <h2 style="font-size:15px;margin-bottom:4px">Finalizar compra</h2>

                    <div style="margin-bottom:12px">
                        <span style="font-size:12px;color:#66746b">Estado:</span>
                        <span data-purchases-status-badge style="margin-left:6px;font-size:12px;font-weight:700"></span>
                    </div>

                    <div class="alert" data-purchases-confirm-message style="display:none"></div>

                    <div style="margin-bottom:10px">
                        <div style="font-size:12px;font-weight:700;margin-bottom:4px">Total de la compra</div>
                        <div data-purchases-total style="font-size:22px;font-weight:900;color:#04ac85">$0.00</div>
                    </div>

                    <div style="display:grid;gap:8px">
                        <button type="button" class="btn-main" data-purchases-confirm style="width:100%">
                            ✓ Confirmar compra
                        </button>
                        <div style="border-top:1px solid #dde6df;padding-top:8px">
                            <div style="font-size:12px;color:#66746b;margin-bottom:6px">
                                Ingresa los productos comprados al stock del hogar en una sola acción.
                            </div>
                            <div style="margin-bottom:6px">
                                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:3px">Ubicación de stock</label>
                                <select class="form-control" data-purchases-stock-location style="font-size:13px">
                                    <option value="">Sin ubicación específica</option>
                                </select>
                            </div>
                            <div style="margin-bottom:8px;display:flex;align-items:center;gap:6px">
                                <input type="checkbox" id="purchases-overwrite-stock" data-purchases-overwrite value="1">
                                <label for="purchases-overwrite-stock" style="font-size:12px;cursor:pointer;margin:0">
                                    Sobreescribir entradas existentes
                                </label>
                            </div>
                            <button type="button" class="btn-secondary-web" data-purchases-add-to-stock style="width:100%">
                                ↑ Ingresar al stock
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

    @elseif($screenKey === 'supermarkets')
        <section class="workspace" data-user-supermarkets>
            <div>
                <article class="panel">
                    <div class="web-tools">
                        <input class="form-control" type="search" data-supermarkets-search placeholder="Buscar supermercado...">
                        <button type="button" class="btn-secondary-web" data-supermarkets-refresh>Buscar</button>
                        <span class="chip" data-supermarkets-count>0 cadenas</span>
                    </div>
                    <div class="alert" data-supermarkets-message style="display:none"></div>
                    <div data-supermarkets-list style="display:grid;gap:10px;margin-top:10px">
                        <p class="muted">Cargando supermercados...</p>
                    </div>
                </article>
            </div>

            <aside class="aside-panel" data-supermarkets-detail>
                <p class="muted">Seleccioná un supermercado para ver el detalle.</p>
            </aside>
        </section>

    @else
    <section class="workspace">
        <div class="panel-grid">
            @foreach($screen['panels'] as $panel)
                <article class="panel">
                    <h2>{{ $panel }}</h2>
                    <div class="table-line"><span class="muted">Estado</span><strong>Preparado</strong></div>
                    <div class="table-line"><span class="muted">Datos conectados</span><strong>Base local</strong></div>
                    <div class="table-line"><span class="muted">Siguiente paso</span><strong>CRUD</strong></div>
                    <div style="margin-top:12px">
                        <span class="chip">{{ $screen['module'] }}</span>
                    </div>
                </article>
            @endforeach
        </div>

        <aside class="aside-panel">
            <h2>Panel de trabajo</h2>
            <div class="table-line"><span class="muted">Pantalla</span><strong>{{ $screen['title'] }}</strong></div>
            <div class="table-line"><span class="muted">Modulo</span><strong>{{ $screen['module'] }}</strong></div>
            <div class="table-line"><span class="muted">Ruta</span><strong>/web/{{ $screenKey }}</strong></div>
            <p class="muted" style="margin-top:14px">Esta vista queda lista como pantalla web de usuario para conectar formularios, tablas, filtros, graficos y acciones reales.</p>
        </aside>
    </section>
    @endif
@endsection
