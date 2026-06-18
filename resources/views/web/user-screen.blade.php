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

    @if($screenKey === 'profile-objectives')
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
