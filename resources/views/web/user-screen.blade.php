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
            <div class="panel-grid">
                <article class="panel" style="grid-column: 1 / -1;">
                    <h2>Perfil basico</h2>
                    <form data-profile-api>
                        <div class="alert" data-api-message style="display:none"></div>

                        <div class="row">
                            <div class="col-md-6">
                                <label for="profile-name">Nombre</label>
                                <input id="profile-name" class="form-control" name="name" type="text" autocomplete="given-name">
                                <span class="invalid-feedback" data-field-error="name" role="alert"></span>
                            </div>
                            <div class="col-md-6">
                                <label for="profile-lastname">Apellido</label>
                                <input id="profile-lastname" class="form-control" name="lastname" type="text" autocomplete="family-name">
                                <span class="invalid-feedback" data-field-error="lastname" role="alert"></span>
                            </div>
                        </div>

                        <div class="row" style="margin-top:14px">
                            <div class="col-md-6">
                                <label for="profile-username">Usuario</label>
                                <input id="profile-username" class="form-control" name="username" type="text" autocomplete="username">
                                <span class="invalid-feedback" data-field-error="username" role="alert"></span>
                            </div>
                            <div class="col-md-6">
                                <label for="profile-email">Email</label>
                                <input id="profile-email" class="form-control" name="email" type="email" disabled>
                            </div>
                        </div>

                        <div class="row" style="margin-top:14px">
                            <div class="col-md-6">
                                <label for="profile-phone">Telefono</label>
                                <input id="profile-phone" class="form-control" name="phone" type="text" autocomplete="tel">
                                <span class="invalid-feedback" data-field-error="phone" role="alert"></span>
                            </div>
                            <div class="col-md-6">
                                <label for="profile-avatar">Avatar URL</label>
                                <input id="profile-avatar" class="form-control" name="avatar_url" type="url">
                                <span class="invalid-feedback" data-field-error="avatar_url" role="alert"></span>
                            </div>
                        </div>

                        <div style="margin-top:18px">
                            <button type="submit" class="btn-main">Guardar perfil</button>
                        </div>
                    </form>
                </article>
            </div>

            <aside class="aside-panel">
                <h2>Sesion API</h2>
                <div class="table-line"><span class="muted">Endpoint lectura</span><strong>GET /auth/me</strong></div>
                <div class="table-line"><span class="muted">Endpoint edicion</span><strong>PATCH /auth/me</strong></div>
                <p class="muted" style="margin-top:14px">Esta pantalla usa el token guardado al iniciar sesion para consultar y actualizar datos basicos.</p>
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
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                                <button type="submit" class="btn-main">Guardar grupo</button>
                                <button type="button" class="btn-secondary-web" data-family-delete>Desactivar grupo</button>
                            </div>
                        </form>

                        <div class="table-line"><span class="muted">Propietario</span><strong data-family-owner>-</strong></div>
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
