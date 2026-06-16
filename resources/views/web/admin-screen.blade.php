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
