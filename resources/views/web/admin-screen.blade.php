@extends('layouts.admin-web')

@section('title', $screen['title'].' - Admin CC Control')

@section('content')
    <section class="hero">
        <div>
            <div class="module">{{ $screen['module'] }}</div>
            <h1>{{ $screen['title'] }}</h1>
            <p class="lead">{{ $screen['description'] }}</p>
        </div>
        <div class="actions">
            <a href="#" class="btn-main">{{ $screen['primary'] }}</a>
            <a href="#" class="btn-ghost">{{ $screen['secondary'] }}</a>
        </div>
    </section>

    <section class="metrics">
        @foreach($screen['metrics'] as $metric)
            <article class="metric">
                <strong>{{ $stats[$metric] ?? 0 }}</strong>
                <span>{{ str_replace('_', ' ', $metric) }}</span>
            </article>
        @endforeach
    </section>

    @if($screenKey === 'users')
        <section data-rbac-users>
            <div class="alert" data-rbac-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-users-search placeholder="Buscar por nombre, email o usuario">
                        <label class="muted" style="display:flex;gap:6px;align-items:center;margin:0">
                            <input type="checkbox" data-users-deleted> incluir eliminados
                        </label>
                        <button type="button" class="btn-ghost" data-users-refresh>Actualizar</button>
                        <span class="chip" data-users-count>0 usuarios</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Username</th>
                                    <th>Estado</th>
                                    <th>Roles</th>
                                    <th>Asignar rol</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-users-body>
                                <tr><td colspan="6" class="muted">Cargando usuarios...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <aside class="panel">
                    <h2>Nuevo usuario</h2>
                    <form class="rbac-form" data-user-create-form>
                        <input class="form-control" name="name" type="text" placeholder="Nombre" required>
                        <input class="form-control" name="lastname" type="text" placeholder="Apellido">
                        <input class="form-control" name="email" type="email" placeholder="Email" required>
                        <input class="form-control" name="password" type="password" placeholder="Password" required>
                        <input class="form-control" name="password_confirmation" type="password" placeholder="Confirmar password" required>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <button type="submit" class="btn-main">Crear usuario</button>
                    </form>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'roles-permissions')
        <section data-rbac-roles>
            <div class="alert" data-rbac-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-roles-search placeholder="Buscar rol">
                        <button type="button" class="btn-ghost" data-roles-refresh>Actualizar</button>
                        <span class="chip" data-roles-count>0 roles</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Permisos</th>
                                    <th>Asignar permiso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-roles-body>
                                <tr><td colspan="5" class="muted">Cargando roles...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <aside class="panel">
                    <h2>Nuevo rol</h2>
                    <form class="rbac-form" data-role-create-form>
                        <input class="form-control" name="code" type="text" placeholder="codigo_ejemplo" required>
                        <input class="form-control" name="name" type="text" placeholder="Nombre" required>
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
                        <button type="submit" class="btn-main">Crear rol</button>
                    </form>

                    <h2 style="margin-top:18px">Permisos disponibles</h2>
                    <select class="form-control" data-permission-select></select>
                    <p class="muted" style="margin-top:10px">Los permisos se asignan desde cada fila de rol para mantener el contexto visible.</p>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'objectives')
        <section data-admin-objectives>
            <div class="alert" data-objectives-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-objectives-search placeholder="Buscar por codigo o nombre">
                        <label class="muted" style="display:flex;gap:6px;align-items:center;margin:0">
                            <input type="checkbox" data-objectives-deleted> incluir eliminados
                        </label>
                        <button type="button" class="btn-ghost" data-objectives-refresh>Actualizar</button>
                        <span class="chip" data-objectives-count>0 objetivos</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Nombre</th>
                                    <th>Descripcion</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-objectives-body>
                                <tr><td colspan="5" class="muted">Cargando objetivos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-objective-form-title>Nuevo objetivo</h2>
                    <form class="rbac-form" data-objective-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="code" type="text" placeholder="bajar_peso" required>
                        <input class="form-control" name="name" type="text" placeholder="Nombre" required>
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar objetivo</button>
                            <button type="button" class="btn-ghost" data-objective-reset>Limpiar</button>
                        </div>
                    </form>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'health-preferences')
        <section data-admin-health-preferences>
            <div class="alert" data-health-preferences-message style="display:none"></div>
            <div class="audit-tabs" role="tablist" aria-label="Catalogos de salud">
                <button type="button" class="audit-tab active" data-health-tab="dietary-restrictions">Restricciones alimentarias</button>
                <button type="button" class="audit-tab" data-health-tab="health-conditions">Condiciones de salud</button>
                <button type="button" class="audit-tab" data-health-tab="allergies">Alergias</button>
            </div>

            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-health-search placeholder="Buscar por codigo o nombre">
                        <label class="muted" style="display:flex;gap:6px;align-items:center;margin:0">
                            <input type="checkbox" data-health-deleted> incluir eliminados
                        </label>
                        <button type="button" class="btn-ghost" data-health-refresh>Actualizar</button>
                        <span class="chip" data-health-count>0 items</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Nombre</th>
                                    <th>Descripcion</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-health-body>
                                <tr><td colspan="5" class="muted">Cargando catalogo...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-health-form-title>Nuevo item</h2>
                    <form class="rbac-form" data-health-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="code" type="text" placeholder="sin_gluten" required>
                        <input class="form-control" name="name" type="text" placeholder="Nombre" required>
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar item</button>
                            <button type="button" class="btn-ghost" data-health-reset>Limpiar</button>
                        </div>
                    </form>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'audit')
        <section data-admin-audit>
            <div class="alert" data-audit-message style="display:none"></div>

            <div class="audit-tabs" role="tablist" aria-label="Auditoria">
                <button type="button" class="audit-tab active" data-audit-tab="audit">Cambios</button>
                <button type="button" class="audit-tab" data-audit-tab="login">Accesos</button>
                <button type="button" class="audit-tab" data-audit-tab="resource">Por recurso</button>
            </div>

            <article class="panel" data-audit-panel="audit">
                <div class="admin-tools">
                    <input class="form-control" type="search" data-audit-search placeholder="Buscar accion, entidad o usuario">
                    <input class="form-control" type="text" data-audit-resource placeholder="Recurso. Ej: users">
                    <input class="form-control" type="number" min="1" data-audit-user placeholder="ID usuario">
                    <input class="form-control" type="date" data-audit-from>
                    <input class="form-control" type="date" data-audit-to>
                    <button type="button" class="btn-ghost" data-audit-refresh>Actualizar</button>
                    <span class="chip" data-audit-count>0 eventos</span>
                </div>
                <div style="overflow:auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Accion</th>
                                <th>Recurso</th>
                                <th>Antes</th>
                                <th>Despues</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody data-audit-body>
                            <tr><td colspan="7" class="muted">Cargando auditoria...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="audit-pagination">
                    <button type="button" class="btn-ghost btn-sm" data-audit-prev>Anterior</button>
                    <span class="muted" data-audit-page>Pagina 1</span>
                    <button type="button" class="btn-ghost btn-sm" data-audit-next>Siguiente</button>
                </div>
            </article>

            <article class="panel" data-audit-panel="login" style="display:none">
                <div class="admin-tools">
                    <input class="form-control" type="search" data-login-search placeholder="Buscar email o IP">
                    <select class="form-control" data-login-success>
                        <option value="">Todos</option>
                        <option value="1">Exitosos</option>
                        <option value="0">Fallidos</option>
                    </select>
                    <input class="form-control" type="date" data-login-from>
                    <input class="form-control" type="date" data-login-to>
                    <button type="button" class="btn-ghost" data-login-refresh>Actualizar</button>
                    <span class="chip" data-login-count>0 accesos</span>
                </div>
                <div style="overflow:auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Email</th>
                                <th>Usuario</th>
                                <th>Resultado</th>
                                <th>Motivo</th>
                                <th>IP</th>
                                <th>Dispositivo</th>
                            </tr>
                        </thead>
                        <tbody data-login-body>
                            <tr><td colspan="7" class="muted">Cargando accesos...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="audit-pagination">
                    <button type="button" class="btn-ghost btn-sm" data-login-prev>Anterior</button>
                    <span class="muted" data-login-page>Pagina 1</span>
                    <button type="button" class="btn-ghost btn-sm" data-login-next>Siguiente</button>
                </div>
            </article>

            <article class="panel" data-audit-panel="resource" style="display:none">
                <div class="admin-tools">
                    <input class="form-control" type="text" data-resource-name placeholder="Recurso. Ej: users">
                    <input class="form-control" type="number" min="1" data-resource-id placeholder="ID recurso">
                    <button type="button" class="btn-main" data-resource-refresh>Consultar historial</button>
                    <span class="chip" data-resource-count>0 eventos</span>
                </div>
                <div style="overflow:auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Accion</th>
                                <th>Antes</th>
                                <th>Despues</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody data-resource-body>
                            <tr><td colspan="6" class="muted">Ingresá recurso e ID para consultar.</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    @else
    <section class="grid">
        @foreach($screen['panels'] as $panel)
            <article class="panel">
                <h2>{{ $panel }}</h2>
                <div class="line"><span class="muted">Estado</span><strong>Listo</strong></div>
                <div class="line"><span class="muted">Origen</span><strong>PostgreSQL</strong></div>
                <div class="line"><span class="muted">Accion</span><strong>ABM</strong></div>
                <span class="chip">{{ $screen['module'] }}</span>
            </article>
        @endforeach
    </section>
    @endif
@endsection
