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
    @elseif($screenKey === 'ingredients')
        <section data-admin-ingredients>
            <div class="alert" data-ingredients-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-ingredients-search placeholder="Buscar arroz, leche, tomate...">
                        <select class="form-control" data-ingredients-category>
                            <option value="">Todas las categorias</option>
                        </select>
                        <select class="form-control" data-ingredients-status>
                            <option value="">Todos</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                        </select>
                        <select class="form-control" data-ingredients-kind>
                            <option value="">Todos los tipos</option>
                            <option value="is_generic">Genericos</option>
                            <option value="is_preparation">Preparaciones</option>
                            <option value="is_supplement">Suplementos</option>
                        </select>
                        <button type="button" class="btn-ghost" data-ingredients-refresh>Actualizar</button>
                        <span class="chip" data-ingredients-count>0 ingredientes</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Ingrediente</th>
                                    <th>Categoria</th>
                                    <th>Unidad base</th>
                                    <th>Flags</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-ingredients-body>
                                <tr><td colspan="6" class="muted">Cargando ingredientes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-ingredients-prev>Anterior</button>
                        <span class="muted" data-ingredients-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-ingredients-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-ingredient-form-title>Nuevo ingrediente</h2>
                    <form class="rbac-form" data-ingredient-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="name" type="text" placeholder="Nombre" required>
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
                        <select class="form-control" name="category_id" data-ingredient-category-select>
                            <option value="">Sin categoria</option>
                        </select>
                        <select class="form-control" name="base_unit_id" data-ingredient-unit-select>
                            <option value="">Sin unidad base</option>
                        </select>
                        <label class="muted" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_generic"> Generico</label>
                        <label class="muted" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_preparation"> Preparacion</label>
                        <label class="muted" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_supplement"> Suplemento</label>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar ingrediente</button>
                            <button type="button" class="btn-ghost" data-ingredient-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar ingrediente</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-ingredient-restore-id placeholder="ID eliminado">
                        <button type="button" class="btn-ghost" data-ingredient-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <section class="grid" style="margin-top:14px">
                <article class="panel">
                    <div class="admin-tools">
                        <h2 style="margin:0">Buscador publico</h2>
                        <input class="form-control" type="search" data-ingredient-public-search placeholder="Buscar catalogo activo">
                        <button type="button" class="btn-ghost" data-ingredient-public-refresh>Buscar</button>
                    </div>
                    <div data-ingredient-public-results class="muted">Buscá ingredientes activos para ver detalle, nutricion y equivalencias.</div>
                </article>

                <article class="panel">
                    <h2>Detalle nutricional</h2>
                    <div data-ingredient-detail class="muted">Seleccioná un ingrediente.</div>
                    <div style="overflow:auto;margin-top:10px">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nutriente</th>
                                    <th>Cantidad / 100g</th>
                                    <th>Fuente</th>
                                </tr>
                            </thead>
                            <tbody data-ingredient-nutrition>
                                <tr><td colspan="3" class="muted">Sin ingrediente seleccionado.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel">
                    <h2>Equivalencias</h2>
                    <div data-ingredient-equivalences class="muted">Seleccioná un ingrediente.</div>
                </article>
            </section>
        </section>
    @elseif($screenKey === 'ingredient-categories')
        <section data-admin-ingredient-categories>
            <div class="alert" data-ingredient-categories-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-ingredient-categories-search placeholder="Buscar por codigo, nombre o descripcion">
                        <select class="form-control" data-ingredient-categories-status>
                            <option value="">Todos los estados</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-ingredient-categories-refresh>Actualizar</button>
                        <span class="chip" data-ingredient-categories-count>0 categorias</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Padre</th>
                                    <th>Orden</th>
                                    <th>Estado</th>
                                    <th>Uso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-ingredient-categories-body>
                                <tr><td colspan="6" class="muted">Cargando categorias...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-ingredient-categories-prev>Anterior</button>
                        <span class="muted" data-ingredient-categories-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-ingredient-categories-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-ingredient-category-form-title>Nueva categoria</h2>
                    <form class="rbac-form" data-ingredient-category-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="code" type="text" placeholder="verduras">
                        <input class="form-control" name="name" type="text" placeholder="Nombre" required>
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
                        <select class="form-control" name="parent_id" data-ingredient-category-parent>
                            <option value="">Sin categoria padre</option>
                        </select>
                        <input class="form-control" name="sort_order" type="number" min="0" step="1" placeholder="Orden">
                        <select class="form-control" name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar categoria</button>
                            <button type="button" class="btn-ghost" data-ingredient-category-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar categoria</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-ingredient-category-restore-id placeholder="ID eliminado">
                        <button type="button" class="btn-ghost" data-ingredient-category-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <article class="panel" style="margin-top:14px">
                <div class="admin-tools">
                    <h2 style="margin:0">Arbol de categorias activo</h2>
                    <button type="button" class="btn-ghost" data-ingredient-categories-tree-refresh>Actualizar arbol</button>
                </div>
                <div data-ingredient-categories-tree class="muted">Cargando arbol...</div>
            </article>
        </section>
    @elseif($screenKey === 'nutrients')
        <section data-admin-nutrients>
            <div class="alert" data-nutrients-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-nutrients-search placeholder="Buscar calorias, proteinas, sodio...">
                        <select class="form-control" data-nutrients-unit>
                            <option value="">Todas las unidades</option>
                        </select>
                        <select class="form-control" data-nutrients-status>
                            <option value="">Todos</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                        </select>
                        <button type="button" class="btn-ghost" data-nutrients-refresh>Actualizar</button>
                        <span class="chip" data-nutrients-count>0 nutrientes</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Nombre</th>
                                    <th>Unidad</th>
                                    <th>Descripcion</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-nutrients-body>
                                <tr><td colspan="6" class="muted">Cargando nutrientes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-nutrients-prev>Anterior</button>
                        <span class="muted" data-nutrients-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-nutrients-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-nutrient-form-title>Nuevo nutriente</h2>
                    <form class="rbac-form" data-nutrient-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="code" type="text" placeholder="proteinas" required>
                        <input class="form-control" name="name" type="text" placeholder="Nombre" required>
                        <select class="form-control" name="unit_id" data-nutrient-unit-select required>
                            <option value="">Unidad</option>
                        </select>
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar nutriente</button>
                            <button type="button" class="btn-ghost" data-nutrient-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar nutriente</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-nutrient-restore-id placeholder="ID inactivo">
                        <button type="button" class="btn-ghost" data-nutrient-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <section class="grid" style="margin-top:14px">
                <article class="panel">
                    <h2>Carga nutricional de ingrediente</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-nutrient-ingredient-select>
                            <option value="">Seleccionar ingrediente</option>
                        </select>
                        <button type="button" class="btn-ghost" data-ingredient-nutrients-refresh>Ver valores</button>
                    </div>
                    <form class="rbac-form" data-ingredient-nutrient-form>
                        <select class="form-control" name="nutrient_id" data-ingredient-nutrient-select required>
                            <option value="">Nutriente</option>
                        </select>
                        <input class="form-control" name="amount_per_100g" type="number" min="0" step="0.0001" placeholder="Cantidad cada 100g" required>
                        <input class="form-control" name="source" type="text" placeholder="Fuente">
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <button type="submit" class="btn-main">Guardar valor</button>
                    </form>
                    <div style="overflow:auto;margin-top:10px">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nutriente</th>
                                    <th>Cantidad / 100g</th>
                                    <th>Fuente</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-ingredient-nutrients-body>
                                <tr><td colspan="4" class="muted">Selecciona un ingrediente.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel">
                    <h2>Carga nutricional de producto</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-nutrient-product-select>
                            <option value="">Seleccionar producto</option>
                        </select>
                        <button type="button" class="btn-ghost" data-product-nutrients-refresh>Ver valores</button>
                    </div>
                    <form class="rbac-form" data-product-nutrient-form>
                        <select class="form-control" name="nutrient_id" data-product-nutrient-select required>
                            <option value="">Nutriente</option>
                        </select>
                        <input class="form-control" name="amount_per_100g" type="number" min="0" step="0.0001" placeholder="Cantidad cada 100g">
                        <input class="form-control" name="amount_per_serving" type="number" min="0" step="0.0001" placeholder="Cantidad por porcion">
                        <input class="form-control" name="serving_size" type="number" min="0" step="0.0001" placeholder="Tamano porcion">
                        <input class="form-control" name="source" type="text" placeholder="Fuente">
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <button type="submit" class="btn-main">Guardar valor</button>
                    </form>
                    <div style="overflow:auto;margin-top:10px">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nutriente</th>
                                    <th>/100g</th>
                                    <th>Porcion</th>
                                    <th>Fuente</th>
                                </tr>
                            </thead>
                            <tbody data-product-nutrients-body>
                                <tr><td colspan="4" class="muted">Selecciona un producto.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </section>
    @elseif($screenKey === 'food-tags')
        <section data-admin-food-tags>
            <div class="alert" data-food-tags-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-food-tags-search placeholder="Buscar bajo sodio, sin gluten...">
                        <input class="form-control" type="text" data-food-tags-type placeholder="Tipo. Ej: dieta">
                        <select class="form-control" data-food-tags-status>
                            <option value="">Todos</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                        </select>
                        <button type="button" class="btn-ghost" data-food-tags-refresh>Actualizar</button>
                        <span class="chip" data-food-tags-count>0 tags</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Descripcion</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-food-tags-body>
                                <tr><td colspan="6" class="muted">Cargando tags...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-food-tags-prev>Anterior</button>
                        <span class="muted" data-food-tags-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-food-tags-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-food-tag-form-title>Nuevo tag alimentario</h2>
                    <form class="rbac-form" data-food-tag-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="code" type="text" placeholder="low_sodium" required>
                        <input class="form-control" name="name" type="text" placeholder="Bajo sodio" required>
                        <input class="form-control" name="type" type="text" placeholder="dieta, salud, advertencia">
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion funcional"></textarea>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar tag</button>
                            <button type="button" class="btn-ghost" data-food-tag-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar tag</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-food-tag-restore-id placeholder="ID inactivo">
                        <button type="button" class="btn-ghost" data-food-tag-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <article class="panel" style="margin-top:14px">
                <div class="admin-tools">
                    <h2 style="margin:0">Catalogo publico</h2>
                    <input class="form-control" type="search" data-food-tags-public-search placeholder="Buscar tags activos">
                    <button type="button" class="btn-ghost" data-food-tags-public-refresh>Consultar</button>
                    <span class="chip" data-food-tags-public-count>0 tags activos</span>
                </div>
                <div data-food-tags-public-results class="chips"></div>
            </article>
        </section>
    @elseif($screenKey === 'brands')
        <section data-admin-brands>
            <div class="alert" data-brands-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-brands-search placeholder="Buscar Gallo, Pureza...">
                        <select class="form-control" data-brands-status>
                            <option value="">Todas</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-brands-refresh>Actualizar</button>
                        <span class="chip" data-brands-count>0 marcas</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Normalizado</th>
                                    <th>Estado</th>
                                    <th>Actualizada</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-brands-body>
                                <tr><td colspan="5" class="muted">Cargando marcas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-brands-prev>Anterior</button>
                        <span class="muted" data-brands-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-brands-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-brand-form-title>Nueva marca</h2>
                    <form class="rbac-form" data-brand-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="name" type="text" placeholder="La Serenisima" required>
                        <select class="form-control" name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar marca</button>
                            <button type="button" class="btn-ghost" data-brand-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar marca</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-brand-restore-id placeholder="ID inactiva">
                        <button type="button" class="btn-ghost" data-brand-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <article class="panel" style="margin-top:14px">
                <div class="admin-tools">
                    <h2 style="margin:0">Catalogo publico</h2>
                    <input class="form-control" type="search" data-brands-public-search placeholder="Buscar marcas activas">
                    <button type="button" class="btn-ghost" data-brands-public-refresh>Consultar</button>
                    <span class="chip" data-brands-public-count>0 marcas activas</span>
                </div>
                <div data-brands-public-results class="chips"></div>
            </article>
        </section>
    @elseif($screenKey === 'product-categories')
        <section data-admin-product-categories>
            <div class="alert" data-product-categories-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-product-categories-search placeholder="Buscar almacen, lacteos...">
                        <select class="form-control" data-product-categories-status>
                            <option value="">Todas</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-product-categories-refresh>Actualizar</button>
                        <span class="chip" data-product-categories-count>0 categorias</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Padre</th>
                                    <th>Descripcion</th>
                                    <th>Hijos</th>
                                    <th>Productos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-product-categories-body>
                                <tr><td colspan="7" class="muted">Cargando categorias...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-product-categories-prev>Anterior</button>
                        <span class="muted" data-product-categories-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-product-categories-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-product-category-form-title>Nueva categoria</h2>
                    <form class="rbac-form" data-product-category-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="name" type="text" placeholder="Almacen" required>
                        <select class="form-control" name="parent_id" data-product-category-parent>
                            <option value="">Categoria raiz</option>
                        </select>
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
                        <select class="form-control" name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar categoria</button>
                            <button type="button" class="btn-ghost" data-product-category-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar categoria</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-product-category-restore-id placeholder="ID eliminada">
                        <button type="button" class="btn-ghost" data-product-category-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <article class="panel" style="margin-top:14px">
                <div class="admin-tools">
                    <h2 style="margin:0">Catalogo activo</h2>
                    <button type="button" class="btn-ghost" data-product-categories-tree-refresh>Actualizar arbol</button>
                </div>
                <div data-product-categories-tree class="muted">Cargando arbol de categorias...</div>
            </article>
        </section>
    @elseif($screenKey === 'products')
        <section data-admin-products>
            <div class="alert" data-products-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-products-search placeholder="Buscar arroz, barcode o marca">
                        <select class="form-control" data-products-brand>
                            <option value="">Marca</option>
                        </select>
                        <select class="form-control" data-products-category>
                            <option value="">Categoria</option>
                        </select>
                        <select class="form-control" data-products-ingredient>
                            <option value="">Ingrediente</option>
                        </select>
                        <select class="form-control" data-products-status>
                            <option value="">Todos</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                        </select>
                        <button type="button" class="btn-ghost" data-products-refresh>Actualizar</button>
                        <span class="chip" data-products-count>0 productos</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Marca</th>
                                    <th>Categoria</th>
                                    <th>Ingrediente</th>
                                    <th>Barcode</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-products-body>
                                <tr><td colspan="7" class="muted">Cargando productos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-products-prev>Anterior</button>
                        <span class="muted" data-products-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-products-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-product-form-title>Nuevo producto</h2>
                    <form class="rbac-form" data-product-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="name" type="text" placeholder="Arroz Gallo Oro 1 kg" required>
                        <select class="form-control" name="brand_id" data-product-brand-select>
                            <option value="">Marca</option>
                        </select>
                        <select class="form-control" name="category_id" data-product-category-select>
                            <option value="">Categoria</option>
                        </select>
                        <select class="form-control" name="ingredient_id" data-product-ingredient-select>
                            <option value="">Ingrediente principal</option>
                        </select>
                        <input class="form-control" name="barcode" type="text" placeholder="Codigo de barras">
                        <input class="form-control" name="net_quantity" type="number" min="0" step="0.0001" placeholder="Cantidad neta">
                        <select class="form-control" name="default_unit_id" data-product-unit-select>
                            <option value="">Unidad</option>
                        </select>
                        <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar producto</button>
                            <button type="button" class="btn-ghost" data-product-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar producto</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-product-restore-id placeholder="ID eliminado">
                        <button type="button" class="btn-ghost" data-product-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <section class="grid" style="margin-top:14px">
                <article class="panel">
                    <h2>Detalle publico</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-product-detail-id placeholder="ID producto">
                        <button type="button" class="btn-ghost" data-product-detail-refresh>Ver detalle</button>
                    </div>
                    <div data-product-detail class="muted">Selecciona un producto.</div>
                </article>
                <article class="panel">
                    <h2>Nutricion</h2>
                    <div data-product-nutrition class="muted">Sin producto seleccionado.</div>
                </article>
                <article class="panel">
                    <h2>Precios</h2>
                    <div data-product-prices class="muted">Sin producto seleccionado.</div>
                </article>
                <article class="panel">
                    <h2>Alternativas</h2>
                    <div data-product-alternatives class="muted">Sin producto seleccionado.</div>
                </article>
                <article class="panel">
                    <h2>Imagenes</h2>
                    <form class="rbac-form" data-product-image-form enctype="multipart/form-data">
                        <input class="form-control" type="number" min="1" name="product_id" data-product-image-product-id placeholder="ID producto" required>
                        <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                        <input class="form-control" type="url" name="url" placeholder="URL de imagen scraping/manual">
                        <input class="form-control" type="text" name="source" placeholder="Origen. Ej: manual, scraping">
                        <label class="muted" style="display:flex;gap:8px;align-items:center;margin-bottom:9px">
                            <input type="checkbox" name="is_primary" value="1"> Principal
                        </label>
                        <button type="submit" class="btn-main">Cargar imagen</button>
                    </form>
                    <div data-product-images class="muted" style="margin-top:10px">Selecciona un producto.</div>
                </article>
            </section>
        </section>
    @elseif($screenKey === 'barcodes')
        <section data-admin-barcodes>
            <div class="alert" data-barcodes-message style="display:none"></div>
            <div class="grid">
                <article class="panel">
                    <h2>Busqueda por codigo</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="text" inputmode="numeric" data-barcode-search-code placeholder="7791234567890">
                        <button type="button" class="btn-main" data-barcode-search-submit>Buscar</button>
                        <button type="button" class="btn-ghost" data-barcode-camera-start>Usar camara</button>
                        <button type="button" class="btn-ghost" data-barcode-camera-stop style="display:none">Detener</button>
                    </div>
                    <video data-barcode-video playsinline muted style="display:none;width:100%;max-height:260px;background:#111;border-radius:8px;margin-bottom:10px"></video>
                    <div data-barcode-camera-status class="muted" style="margin-bottom:10px">La camara se activa solo al presionar usar camara.</div>
                    <div data-barcode-search-result class="muted">Escanea o ingresa un codigo para buscar el producto asociado.</div>
                    <div class="admin-tools" data-barcode-next-actions style="display:none;margin-top:10px">
                        <a class="btn-ghost" href="{{ url('/web/stock') }}">Continuar a stock</a>
                        <a class="btn-ghost" href="{{ url('/web/shopping-list') }}">Continuar a compras</a>
                    </div>
                </article>

                <article class="panel">
                    <h2>Alta de barcode</h2>
                    <form class="rbac-form" data-barcode-create-form>
                        <select class="form-control" name="product_id" data-barcode-product-select required>
                            <option value="">Producto</option>
                        </select>
                        <input class="form-control" name="barcode" type="text" inputmode="numeric" placeholder="Codigo de barras" required>
                        <button type="submit" class="btn-main">Agregar codigo</button>
                    </form>
                    <div data-barcode-created-result class="muted" style="margin-top:10px">El ID generado se muestra aca para poder desactivar el codigo si hace falta.</div>
                </article>

                <article class="panel">
                    <h2>Baja de barcode</h2>
                    <form class="rbac-form" data-barcode-delete-form>
                        <select class="form-control" name="product_id" data-barcode-delete-product-select required>
                            <option value="">Producto</option>
                        </select>
                        <input class="form-control" name="barcode_id" type="number" min="1" placeholder="ID barcode" required>
                        <button type="submit" class="btn-ghost">Desactivar codigo</button>
                    </form>
                </article>

                <article class="panel">
                    <h2>Productos recientes</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-barcode-product-search placeholder="Buscar producto">
                        <button type="button" class="btn-ghost" data-barcode-products-refresh>Actualizar</button>
                    </div>
                    <div data-barcode-products-list class="muted">Cargando productos...</div>
                </article>
            </div>
        </section>
    @elseif($screenKey === 'equivalences')
        <section data-admin-ingredient-equivalences>
            <div class="alert" data-equivalences-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-equivalences-search placeholder="Buscar motivo o tipo">
                        <select class="form-control" data-equivalences-source>
                            <option value="">Ingrediente origen</option>
                        </select>
                        <select class="form-control" data-equivalences-target>
                            <option value="">Ingrediente destino</option>
                        </select>
                        <input class="form-control" type="text" data-equivalences-type placeholder="Tipo. Ej: replacement">
                        <select class="form-control" data-equivalences-status>
                            <option value="">Todos</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-equivalences-refresh>Actualizar</button>
                        <span class="chip" data-equivalences-count>0 equivalencias</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Tipo</th>
                                    <th>Factor</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-equivalences-body>
                                <tr><td colspan="7" class="muted">Cargando equivalencias...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-equivalences-prev>Anterior</button>
                        <span class="muted" data-equivalences-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-equivalences-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-equivalence-form-title>Nueva equivalencia</h2>
                    <form class="rbac-form" data-equivalence-form>
                        <input type="hidden" name="id">
                        <select class="form-control" name="source_ingredient_id" data-equivalence-source-select required>
                            <option value="">Ingrediente origen</option>
                        </select>
                        <select class="form-control" name="target_ingredient_id" data-equivalence-target-select required>
                            <option value="">Ingrediente destino</option>
                        </select>
                        <input class="form-control" name="equivalence_type" type="text" placeholder="replacement">
                        <input class="form-control" name="conversion_factor" type="number" min="0.00000001" step="0.0001" placeholder="Factor" required>
                        <textarea class="form-control" name="reason" rows="4" placeholder="Motivo o criterio"></textarea>
                        <select class="form-control" name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar equivalencia</button>
                            <button type="button" class="btn-ghost" data-equivalence-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar equivalencia</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-equivalence-restore-id placeholder="ID inactiva">
                        <button type="button" class="btn-ghost" data-equivalence-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <article class="panel" style="margin-top:14px">
                <div class="admin-tools">
                    <h2 style="margin:0">Sustituciones posibles</h2>
                    <select class="form-control" data-equivalence-public-ingredient>
                        <option value="">Seleccionar ingrediente</option>
                    </select>
                    <button type="button" class="btn-ghost" data-equivalence-public-refresh>Consultar</button>
                    <span class="chip" data-equivalence-public-count>0 opciones</span>
                </div>
                <div data-equivalence-public-results class="muted">Selecciona un ingrediente para ver reemplazos activos.</div>
            </article>
        </section>
    @elseif($screenKey === 'units-conversions')
        <section data-admin-units>
            <div class="alert" data-units-message style="display:none"></div>
            <div class="audit-tabs" role="tablist" aria-label="Unidades y conversiones">
                <button type="button" class="audit-tab active" data-units-tab="units">Unidades</button>
                <button type="button" class="audit-tab" data-units-tab="conversions">Conversiones</button>
                <button type="button" class="audit-tab" data-units-tab="catalog">Catalogo publico</button>
            </div>

            <section data-units-panel="units">
                <div class="rbac-layout">
                    <article class="panel">
                        <div class="admin-tools">
                            <input class="form-control" type="search" data-units-search placeholder="Buscar g, kg, ml, taza...">
                            <select class="form-control" data-units-type>
                                <option value="">Todos los tipos</option>
                                <option value="mass">Masa</option>
                                <option value="volume">Volumen</option>
                                <option value="count">Conteo</option>
                                <option value="household">Domestica</option>
                                <option value="package">Paquete</option>
                            </select>
                            <select class="form-control" data-units-status>
                                <option value="">Todos</option>
                                <option value="active">Activas</option>
                                <option value="inactive">Inactivas</option>
                            </select>
                            <button type="button" class="btn-ghost" data-units-refresh>Actualizar</button>
                            <span class="chip" data-units-count>0 unidades</span>
                        </div>
                        <div style="overflow:auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Simbolo</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-units-body>
                                    <tr><td colspan="6" class="muted">Cargando unidades...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="audit-pagination">
                            <button type="button" class="btn-ghost btn-sm" data-units-prev>Anterior</button>
                            <span class="muted" data-units-page>Pagina 1</span>
                            <button type="button" class="btn-ghost btn-sm" data-units-next>Siguiente</button>
                        </div>
                    </article>

                    <aside class="panel">
                        <h2 data-unit-form-title>Nueva unidad</h2>
                        <form class="rbac-form" data-unit-form>
                            <input type="hidden" name="id">
                            <input class="form-control" name="code" type="text" placeholder="kg" required>
                            <input class="form-control" name="name" type="text" placeholder="Kilogramo" required>
                            <select class="form-control" name="type" required>
                                <option value="">Tipo</option>
                                <option value="mass">Masa</option>
                                <option value="volume">Volumen</option>
                                <option value="count">Conteo</option>
                                <option value="household">Domestica</option>
                                <option value="package">Paquete</option>
                            </select>
                            <input class="form-control" name="symbol" type="text" placeholder="kg">
                            <select class="form-control" name="status">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                            </select>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <button type="submit" class="btn-main">Guardar unidad</button>
                                <button type="button" class="btn-ghost" data-unit-reset>Limpiar</button>
                            </div>
                        </form>

                        <h2 style="margin-top:18px">Restaurar unidad</h2>
                        <div class="admin-tools">
                            <input class="form-control" type="number" min="1" data-unit-restore-id placeholder="ID inactivo">
                            <button type="button" class="btn-ghost" data-unit-restore-submit>Restaurar</button>
                        </div>
                    </aside>
                </div>
            </section>

            <section data-units-panel="conversions" style="display:none">
                <div class="rbac-layout">
                    <article class="panel">
                        <div class="admin-tools">
                            <input class="form-control" type="search" data-conversions-search placeholder="Buscar en notas">
                            <select class="form-control" data-conversions-from>
                                <option value="">Origen</option>
                            </select>
                            <select class="form-control" data-conversions-to>
                                <option value="">Destino</option>
                            </select>
                            <select class="form-control" data-conversions-ingredient>
                                <option value="">General o ingrediente</option>
                            </select>
                            <select class="form-control" data-conversions-status>
                                <option value="">Todos</option>
                                <option value="active">Activas</option>
                                <option value="inactive">Inactivas</option>
                            </select>
                            <button type="button" class="btn-ghost" data-conversions-refresh>Actualizar</button>
                            <span class="chip" data-conversions-count>0 conversiones</span>
                        </div>
                        <div style="overflow:auto">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Desde</th>
                                        <th>Hacia</th>
                                        <th>Ingrediente</th>
                                        <th>Factor</th>
                                        <th>Notas</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody data-conversions-body>
                                    <tr><td colspan="7" class="muted">Cargando conversiones...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="audit-pagination">
                            <button type="button" class="btn-ghost btn-sm" data-conversions-prev>Anterior</button>
                            <span class="muted" data-conversions-page>Pagina 1</span>
                            <button type="button" class="btn-ghost btn-sm" data-conversions-next>Siguiente</button>
                        </div>
                    </article>

                    <aside class="panel">
                        <h2 data-conversion-form-title>Nueva conversion</h2>
                        <form class="rbac-form" data-conversion-form>
                            <input type="hidden" name="id">
                            <select class="form-control" name="from_unit_id" data-conversion-from-select required>
                                <option value="">Unidad origen</option>
                            </select>
                            <select class="form-control" name="to_unit_id" data-conversion-to-select required>
                                <option value="">Unidad destino</option>
                            </select>
                            <select class="form-control" name="ingredient_id" data-conversion-ingredient-select>
                                <option value="">Conversion general</option>
                            </select>
                            <input class="form-control" name="factor" type="number" min="0.00000001" step="0.00000001" placeholder="Factor" required>
                            <textarea class="form-control" name="notes" rows="4" placeholder="Notas"></textarea>
                            <select class="form-control" name="status">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                            </select>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <button type="submit" class="btn-main">Guardar conversion</button>
                                <button type="button" class="btn-ghost" data-conversion-reset>Limpiar</button>
                            </div>
                        </form>

                        <h2 style="margin-top:18px">Restaurar conversion</h2>
                        <div class="admin-tools">
                            <input class="form-control" type="number" min="1" data-conversion-restore-id placeholder="ID inactiva">
                            <button type="button" class="btn-ghost" data-conversion-restore-submit>Restaurar</button>
                        </div>
                    </aside>
                </div>
            </section>

            <section data-units-panel="catalog" style="display:none">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-units-public-search placeholder="Buscar unidades activas">
                        <button type="button" class="btn-ghost" data-units-public-refresh>Actualizar catalogo</button>
                        <span class="chip" data-units-public-count>0 unidades activas</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Simbolo</th>
                                </tr>
                            </thead>
                            <tbody data-units-public-body>
                                <tr><td colspan="4" class="muted">Cargando catalogo publico...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
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
    @elseif($screenKey === 'branches')
        <section data-admin-branches>
            <div class="alert" data-branches-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <div class="admin-tools">
                        <input class="form-control" type="text" data-branches-search placeholder="Buscar sucursal o dirección...">
                        <select class="form-control" data-branches-filter-chain>
                            <option value="">Todas las cadenas</option>
                        </select>
                        <select class="form-control" data-branches-filter-city>
                            <option value="">Todas las ciudades</option>
                        </select>
                        <select class="form-control" data-branches-filter-status>
                            <option value="">Todos los estados</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-branches-refresh>Actualizar</button>
                        <span class="chip" data-branches-count>0 sucursales</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Cadena</th>
                                    <th>Ciudad</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-branches-body>
                                <tr><td colspan="5" class="muted">Cargando sucursales...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-branches-prev>Anterior</button>
                        <span class="muted" data-branches-page>Pagina 1 de 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-branches-next>Siguiente</button>
                    </div>
                </article>

                <article class="panel" style="min-width:0">
                    <h2 data-branches-form-title>Nueva sucursal</h2>
                    <form data-branches-form style="max-height:62vh;overflow-y:auto;padding-right:2px">
                        <input type="hidden" data-branches-edit-id>
                        <div style="margin-bottom:8px">
                            <label>Cadena <span style="color:var(--danger)">*</span></label>
                            <select class="form-control" name="supermarket_chain_id" data-branches-form-chain>
                                <option value="">Cargando cadenas...</option>
                            </select>
                        </div>
                        <div style="margin-bottom:8px">
                            <label>Ciudad <span style="color:var(--danger)">*</span></label>
                            <select class="form-control" name="city_id" data-branches-form-city>
                                <option value="">Cargando ciudades...</option>
                            </select>
                        </div>
                        <div style="margin-bottom:8px">
                            <label>Nombre <span style="color:var(--danger)">*</span></label>
                            <input class="form-control" name="name" type="text" placeholder="Ej: Sucursal Centro">
                        </div>
                        <div style="margin-bottom:8px">
                            <label>Dirección <span style="color:var(--danger)">*</span></label>
                            <input class="form-control" name="address" type="text" placeholder="Ej: Av. Bartolomé Mitre 180">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                            <div>
                                <label>Latitud</label>
                                <input class="form-control" name="latitude" type="number" step="any" min="-90" max="90" placeholder="-41.1334" data-branches-lat>
                            </div>
                            <div>
                                <label>Longitud</label>
                                <input class="form-control" name="longitude" type="number" step="any" min="-180" max="180" placeholder="-71.3103" data-branches-lng>
                            </div>
                        </div>
                        <div style="margin-bottom:8px">
                            <label>Horarios</label>
                            <textarea class="form-control" name="opening_hours" rows="2" placeholder="Ej: Lun-Vie 8:00-21:00, Sáb 9:00-20:00"></textarea>
                        </div>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
                            <label style="display:flex;gap:6px;align-items:center">
                                <input type="checkbox" name="delivery_available" value="1"> Delivery disponible
                            </label>
                            <label style="display:flex;gap:6px;align-items:center">
                                <input type="checkbox" name="pickup_available" value="1"> Pickup disponible
                            </label>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main" data-branches-submit>Crear sucursal</button>
                            <button type="button" class="btn-ghost" data-branches-reset>Limpiar</button>
                        </div>
                    </form>
                    <div data-branches-map-preview style="height:200px;border-radius:8px;margin-top:12px;display:none;border:1px solid var(--line)"></div>
                </article>
            </div>
        </section>
    @elseif($screenKey === 'supermarkets')
        <section data-admin-supermarkets>
            <div class="alert" data-supermarkets-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <div class="admin-tools">
                        <input class="form-control" type="text" data-supermarkets-search placeholder="Buscar cadena...">
                        <select class="form-control" data-supermarkets-filter-status>
                            <option value="">Todos los estados</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-supermarkets-refresh>Actualizar</button>
                        <span class="chip" data-supermarkets-count>0 cadenas</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Sitio web</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-supermarkets-body>
                                <tr><td colspan="4" class="muted">Cargando cadenas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-supermarkets-prev>Anterior</button>
                        <span class="muted" data-supermarkets-page>Pagina 1 de 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-supermarkets-next>Siguiente</button>
                    </div>
                </article>

                <article class="panel" style="min-width:0">
                    <h2 data-supermarkets-form-title>Nueva cadena</h2>
                    <form data-supermarkets-form>
                        <input type="hidden" data-supermarkets-edit-id>
                        <div style="margin-bottom:8px">
                            <label>Nombre <span style="color:var(--danger)">*</span></label>
                            <input class="form-control" name="name" type="text" placeholder="Carrefour">
                        </div>
                        <div style="margin-bottom:8px">
                            <label>Sitio web</label>
                            <input class="form-control" name="website_url" type="url" placeholder="https://www.carrefour.com.ar">
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                            <button type="submit" class="btn-main" data-supermarkets-submit>Crear cadena</button>
                            <button type="button" class="btn-ghost" data-supermarkets-reset>Limpiar</button>
                        </div>
                    </form>
                </article>
            </div>
        </section>
    @elseif($screenKey === 'supermarket-products')
        <section data-admin-supermarket-products>
            <div class="alert" data-supermarket-products-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-sp-search placeholder="Buscar SKU o referencia">
                        <select class="form-control" data-sp-filter-product>
                            <option value="">Producto</option>
                        </select>
                        <select class="form-control" data-sp-filter-chain>
                            <option value="">Cadena</option>
                        </select>
                        <select class="form-control" data-sp-filter-branch>
                            <option value="">Sucursal</option>
                        </select>
                        <select class="form-control" data-sp-filter-status>
                            <option value="">Todos</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                        </select>
                        <button type="button" class="btn-ghost" data-sp-refresh>Actualizar</button>
                        <span class="chip" data-sp-count>0 mapeos</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Supermercado</th>
                                    <th>SKU / fuente</th>
                                    <th>Precio actual</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-sp-body>
                                <tr><td colspan="6" class="muted">Cargando mapeos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-sp-prev>Anterior</button>
                        <span class="muted" data-sp-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-sp-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-sp-form-title>Nuevo mapeo</h2>
                    <form class="rbac-form" data-sp-form>
                        <input type="hidden" name="id">
                        <select class="form-control" name="product_id" data-sp-product-select required>
                            <option value="">Producto interno</option>
                        </select>
                        <select class="form-control" name="supermarket_chain_id" data-sp-chain-select required>
                            <option value="">Cadena</option>
                        </select>
                        <select class="form-control" name="supermarket_branch_id" data-sp-branch-select required>
                            <option value="">Sucursal</option>
                        </select>
                        <input class="form-control" name="external_sku" type="text" placeholder="SKU externo">
                        <input class="form-control" name="source_url" type="url" placeholder="URL externa">
                        <input class="form-control" name="source_name" type="text" placeholder="Referencia scrapeada">
                        <input class="form-control" name="last_scraped_at" type="datetime-local">
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main" data-sp-submit>Guardar mapeo</button>
                            <button type="button" class="btn-ghost" data-sp-reset>Limpiar</button>
                        </div>
                    </form>
                    <div style="border-top:1px solid #e2e8f0;margin-top:18px;padding-top:16px">
                        <h2 style="font-size:1rem;margin-bottom:.75rem">Carga manual de precio</h2>
                        <div data-sp-current-price class="muted" style="margin-bottom:10px">Selecciona un mapeo para cargar precios.</div>
                        <form class="rbac-form" data-sp-price-form>
                            <input class="form-control" name="price" type="number" min="0.01" step="0.01" placeholder="Precio" required disabled>
                            <select class="form-control" name="currency" disabled>
                                <option value="ARS">ARS</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                            <input class="form-control" name="captured_at" type="datetime-local" disabled>
                            <button type="submit" class="btn-main" data-sp-price-submit disabled>Agregar precio</button>
                        </form>
                    </div>
                </aside>
            </div>

            <section class="grid" style="margin-top:14px">
                <article class="panel">
                    <h2>Historial de precios</h2>
                    <div data-sp-price-history class="muted">Selecciona un mapeo.</div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-sp-price-prev>Anterior</button>
                        <span class="muted" data-sp-price-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-sp-price-next>Siguiente</button>
                    </div>
                </article>
                <article class="panel">
                    <h2>Productos por sucursal</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-sp-branch-products-chain>
                            <option value="">Cadena</option>
                        </select>
                        <select class="form-control" data-sp-branch-products-branch>
                            <option value="">Sucursal</option>
                        </select>
                        <button type="button" class="btn-ghost" data-sp-load-branch-products>Ver productos</button>
                    </div>
                    <div data-sp-branch-products class="muted">Selecciona una sucursal.</div>
                </article>
                <article class="panel">
                    <h2>Comparacion de precios</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-sp-price-product>
                            <option value="">Producto</option>
                        </select>
                        <button type="button" class="btn-ghost" data-sp-load-prices>Comparar</button>
                    </div>
                    <div data-sp-prices class="muted">Selecciona un producto.</div>
                </article>
                <article class="panel">
                    <h2>Mejor precio</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-sp-best-product>
                            <option value="">Producto</option>
                        </select>
                        <button type="button" class="btn-ghost" data-sp-load-best>Buscar mejor precio</button>
                    </div>
                    <div data-sp-best-price class="muted">Selecciona un producto.</div>
                </article>
            </section>
        </section>
    @elseif($screenKey === 'promotions')
        <section data-admin-promotions>
            <div class="alert" data-promotions-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-promotions-search placeholder="Buscar promocion">
                        <select class="form-control" data-promotions-filter-chain>
                            <option value="">Cadena</option>
                        </select>
                        <select class="form-control" data-promotions-filter-branch>
                            <option value="">Sucursal</option>
                        </select>
                        <select class="form-control" data-promotions-filter-status>
                            <option value="">Todos</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-promotions-refresh>Actualizar</button>
                        <span class="chip" data-promotions-count>0 promociones</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Promocion</th>
                                    <th>Supermercado</th>
                                    <th>Beneficio</th>
                                    <th>Vigencia</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-promotions-body>
                                <tr><td colspan="6" class="muted">Cargando promociones...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-promotions-prev>Anterior</button>
                        <span class="muted" data-promotions-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-promotions-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-promotion-form-title>Nueva promocion</h2>
                    <form class="rbac-form" data-promotion-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="name" type="text" placeholder="Nombre" required>
                        <textarea class="form-control" name="description" rows="3" placeholder="Descripcion"></textarea>
                        <select class="form-control" name="supermarket_chain_id" data-promotion-chain-select required>
                            <option value="">Cadena</option>
                        </select>
                        <select class="form-control" name="supermarket_branch_id" data-promotion-branch-select>
                            <option value="">Promocion de cadena</option>
                        </select>
                        <select class="form-control" name="discount_type" data-promotion-type>
                            <option value="">Tipo de promocion</option>
                            <option value="percentage">Porcentaje</option>
                            <option value="fixed_amount">Monto fijo</option>
                            <option value="buy_x_pay_y">2x1 / Buy X Pay Y</option>
                            <option value="payment_method">Metodo de pago</option>
                            <option value="day_discount">Descuento por dia</option>
                        </select>
                        <div data-promotion-value-row>
                            <input class="form-control" name="discount_value" type="number" min="0" step="0.01" placeholder="Valor del descuento">
                        </div>
                        <select class="form-control" name="day_of_week" data-promotion-day-row>
                            <option value="">Dia de la semana</option>
                            <option value="0">Domingo</option>
                            <option value="1">Lunes</option>
                            <option value="2">Martes</option>
                            <option value="3">Miercoles</option>
                            <option value="4">Jueves</option>
                            <option value="5">Viernes</option>
                            <option value="6">Sabado</option>
                        </select>
                        <label class="muted" style="display:flex;gap:8px;align-items:center">
                            <input type="checkbox" name="requires_payment_method" value="1" data-promotion-payment-required>
                            Requiere metodo de pago
                        </label>
                        <input class="form-control" name="valid_from" type="datetime-local" placeholder="Desde">
                        <input class="form-control" name="valid_to" type="datetime-local" placeholder="Hasta">
                        <select class="form-control" name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main" data-promotion-submit>Guardar promocion</button>
                            <button type="button" class="btn-ghost" data-promotion-reset>Limpiar</button>
                        </div>
                    </form>
                    <p class="muted" style="margin-top:10px;font-size:13px">El contrato actual permite asociar la promocion a una cadena y opcionalmente a una sucursal. Producto, banco y billetera no se envian porque no forman parte de esta API.</p>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'payment-methods')
        <section data-admin-payment-methods>
            <div class="alert" data-payment-methods-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-payment-methods-search placeholder="Buscar metodo o emisor">
                        <select class="form-control" data-payment-methods-filter-type>
                            <option value="">Todos los tipos</option>
                            <option value="credit_card">Tarjeta credito</option>
                            <option value="debit_card">Tarjeta debito</option>
                            <option value="bank_account">Cuenta bancaria</option>
                            <option value="digital_wallet">Billetera digital</option>
                            <option value="cash">Efectivo</option>
                            <option value="other">Otro</option>
                        </select>
                        <select class="form-control" data-payment-methods-filter-status>
                            <option value="">Todos</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                        </select>
                        <button type="button" class="btn-ghost" data-payment-methods-refresh>Actualizar</button>
                        <span class="chip" data-payment-methods-count>0 metodos</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Metodo</th>
                                    <th>Tipo</th>
                                    <th>Emisor</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-payment-methods-body>
                                <tr><td colspan="5" class="muted">Cargando metodos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-payment-methods-prev>Anterior</button>
                        <span class="muted" data-payment-methods-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-payment-methods-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-payment-method-form-title>Nuevo metodo</h2>
                    <form class="rbac-form" data-payment-method-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="name" type="text" placeholder="Visa Credito" required>
                        <select class="form-control" name="type" required>
                            <option value="">Tipo</option>
                            <option value="credit_card">Tarjeta credito</option>
                            <option value="debit_card">Tarjeta debito</option>
                            <option value="bank_account">Cuenta bancaria</option>
                            <option value="digital_wallet">Billetera digital</option>
                            <option value="cash">Efectivo</option>
                            <option value="other">Otro</option>
                        </select>
                        <input class="form-control" name="issuer" type="text" placeholder="Banco o billetera">
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main" data-payment-method-submit>Guardar metodo</button>
                            <button type="button" class="btn-ghost" data-payment-method-reset>Limpiar</button>
                        </div>
                    </form>
                    <p class="muted" style="margin-top:10px;font-size:13px">No se guardan numeros de tarjeta, CVV, tokens ni credenciales. El contrato solo admite nombre, tipo y emisor.</p>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'scraped-products')
        <section data-admin-scraped-products>
            <div class="alert" data-scraped-products-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <h2>Candidatos scrapeados</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-candidates-search placeholder="Buscar nombre, SKU o URL">
                        <select class="form-control" data-candidates-status>
                            <option value="">Todos los estados</option>
                            <option value="pending">Pendientes</option>
                            <option value="matched">Mapeados</option>
                            <option value="created">Producto creado</option>
                            <option value="approved">Aprobados</option>
                            <option value="rejected">Rechazados</option>
                        </select>
                        <select class="form-control" data-candidates-source>
                            <option value="">Todas las fuentes</option>
                        </select>
                        <button type="button" class="btn-ghost" data-candidates-refresh>Actualizar</button>
                        <span class="chip" data-candidates-count>0 candidatos</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Producto scrapeado</th>
                                    <th>Fuente</th>
                                    <th>Precio</th>
                                    <th>Estado</th>
                                    <th>Match</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-candidates-body>
                                <tr><td colspan="6" class="muted">Cargando candidatos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-candidates-prev>Anterior</button>
                        <span class="muted" data-candidates-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-candidates-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2>Detalle y revision</h2>
                    <div data-candidate-detail class="muted">Selecciona un candidato.</div>

                    <div data-candidate-actions style="display:none">
                        <h2 style="margin-top:18px">Mapear producto existente</h2>
                        <form class="rbac-form" data-candidate-match-form>
                            <select class="form-control" name="product_id" data-candidate-product required>
                                <option value="">Producto</option>
                            </select>
                            <button type="submit" class="btn-main">Asociar producto</button>
                        </form>

                        <h2 style="margin-top:18px">Asignar ingrediente</h2>
                        <form class="rbac-form" data-candidate-ingredient-form>
                            <select class="form-control" name="ingredient_id" data-candidate-ingredient required>
                                <option value="">Ingrediente</option>
                            </select>
                            <button type="submit" class="btn-main">Asignar ingrediente</button>
                        </form>

                        <h2 style="margin-top:18px">Crear producto nuevo</h2>
                        <form class="rbac-form" data-candidate-create-product-form>
                            <input class="form-control" name="name" type="text" placeholder="Nombre del producto">
                            <select class="form-control" name="brand_id" data-candidate-brand>
                                <option value="">Marca opcional</option>
                            </select>
                            <select class="form-control" name="category_id" data-candidate-category>
                                <option value="">Categoria opcional</option>
                            </select>
                            <select class="form-control" name="ingredient_id" data-candidate-create-ingredient>
                                <option value="">Ingrediente opcional</option>
                            </select>
                            <button type="submit" class="btn-main">Crear y asociar</button>
                        </form>

                        <div class="admin-tools" style="margin-top:18px">
                            <button type="button" class="btn-main" data-candidate-approve>Aprobar</button>
                        </div>

                        <h2 style="margin-top:18px">Rechazar</h2>
                        <form class="rbac-form" data-candidate-reject-form>
                            <textarea class="form-control" name="reason" rows="3" maxlength="500" placeholder="Motivo del rechazo" required></textarea>
                            <button type="submit" class="btn-ghost" style="color:var(--danger)">Rechazar candidato</button>
                        </form>
                    </div>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'recipe-scraping')
        <section data-admin-recipe-scraping>
            <div class="alert" data-recipe-scraping-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <h2>Jobs Cookpad</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-recipe-scraping-status>
                            <option value="">Todos los estados</option>
                            <option value="pending">Pendientes</option>
                            <option value="running">En ejecucion</option>
                            <option value="completed">Completados</option>
                            <option value="failed">Fallidos</option>
                            <option value="cancelled">Cancelados</option>
                        </select>
                        <button type="button" class="btn-ghost" data-recipe-scraping-refresh>Actualizar</button>
                        <span class="chip" data-recipe-scraping-count>0 jobs</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>Fuente</th>
                                    <th>Estado</th>
                                    <th>Parametros</th>
                                    <th>Resumen</th>
                                    <th>Fechas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-recipe-scraping-jobs-body>
                                <tr><td colspan="7" class="muted">Cargando jobs...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-recipe-scraping-prev>Anterior</button>
                        <span class="muted" data-recipe-scraping-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-recipe-scraping-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2>Ejecutar Cookpad</h2>
                    <form class="rbac-form" data-recipe-scraping-form>
                        <label class="muted" for="recipe-scraping-max-pages">Paginas maximas</label>
                        <input id="recipe-scraping-max-pages" class="form-control" name="max_pages" type="number" min="1" max="50" value="1" required>
                        <button type="submit" class="btn-main" data-recipe-scraping-submit>Ejecutar Cookpad</button>
                    </form>
                    <div class="line"><span>Fuente</span><strong>Cookpad Argentina</strong></div>
                    <div class="line"><span>Estado inicial</span><strong>pending</strong></div>
                    <p class="muted" style="margin-top:12px">El backend crea o reutiliza la fuente Cookpad y encola el job. Los resultados se revisan en las pantallas de importacion y recetas existentes.</p>
                </aside>
            </div>

            <section class="grid" style="margin-top:14px">
                <article class="panel" style="grid-column:1 / -1">
                    <h2>Detalle del job</h2>
                    <div data-recipe-scraping-detail class="muted">Selecciona un job.</div>
                </article>
            </section>
        </section>
    @elseif($screenKey === 'supermarket-scraping')
        <section data-admin-supermarket-scraping>
            <div class="alert" data-scraping-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <h2>Fuentes</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-scraping-source-search placeholder="Buscar fuente">
                        <select class="form-control" data-scraping-source-status>
                            <option value="">Todos</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-scraping-source-refresh>Actualizar</button>
                        <span class="chip" data-scraping-source-count>0 fuentes</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Fuente</th>
                                    <th>Tipo</th>
                                    <th>URL</th>
                                    <th>Activa</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-scraping-sources-body>
                                <tr><td colspan="5" class="muted">Cargando fuentes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <aside class="panel">
                    <h2>Nueva fuente</h2>
                    <form class="rbac-form" data-scraping-source-form>
                        <input class="form-control" name="code" type="text" placeholder="carrefour_bariloche" required>
                        <input class="form-control" name="name" type="text" placeholder="Carrefour Bariloche" required>
                        <select class="form-control" name="type">
                            <option value="web_scraper">Web scraper</option>
                            <option value="api">API</option>
                            <option value="feed">Feed</option>
                        </select>
                        <input class="form-control" name="base_url" type="url" placeholder="https://..." required>
                        <select class="form-control" name="city_id" data-scraping-source-city>
                            <option value="">Ciudad opcional</option>
                        </select>
                        <label class="muted" style="display:flex;gap:8px;align-items:center">
                            <input type="checkbox" name="is_active" value="1" checked>
                            Activa
                        </label>
                        <button type="submit" class="btn-main" data-scraping-source-submit>Crear fuente</button>
                    </form>
                </aside>
            </div>

            <section class="grid" style="margin-top:14px">
                <article class="panel">
                    <h2>Ejecutar scraping</h2>
                    <form class="rbac-form" data-scraping-job-form>
                        <select class="form-control" name="source_id" data-scraping-job-source required>
                            <option value="">Fuente</option>
                        </select>
                        <select class="form-control" name="supermarket_chain_id" data-scraping-job-chain>
                            <option value="">Cadena opcional</option>
                        </select>
                        <select class="form-control" name="supermarket_branch_id" data-scraping-job-branch>
                            <option value="">Sucursal opcional</option>
                        </select>
                        <input class="form-control" name="max_pages" type="number" min="1" max="50" placeholder="Max paginas">
                        <button type="submit" class="btn-main" data-scraping-job-submit>Ejecutar</button>
                    </form>
                </article>

                <article class="panel" style="grid-column:1 / -1">
                    <h2>Jobs</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-scraping-job-filter-source>
                            <option value="">Todas las fuentes</option>
                        </select>
                        <select class="form-control" data-scraping-job-filter-status>
                            <option value="">Todos los estados</option>
                            <option value="pending">Pendiente</option>
                            <option value="running">En ejecucion</option>
                            <option value="cancel_requested">Cancel solicitado</option>
                            <option value="cancelled">Cancelado</option>
                            <option value="completed">Completado</option>
                            <option value="failed">Fallido</option>
                        </select>
                        <button type="button" class="btn-ghost" data-scraping-job-refresh>Actualizar</button>
                        <span class="chip" data-scraping-job-count>0 jobs</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>Fuente</th>
                                    <th>Estado</th>
                                    <th>Resumen</th>
                                    <th>Fechas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-scraping-jobs-body>
                                <tr><td colspan="6" class="muted">Cargando jobs...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-scraping-jobs-prev>Anterior</button>
                        <span class="muted" data-scraping-jobs-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-scraping-jobs-next>Siguiente</button>
                    </div>
                </article>
            </section>

            <section class="grid" style="margin-top:14px">
                <article class="panel" style="grid-column:1 / -1">
                    <h2>Detalle del job</h2>
                    <div data-scraping-job-detail class="muted">Selecciona un job.</div>
                </article>
                <article class="panel" style="grid-column:1 / -1">
                    <div class="admin-tools">
                        <h2 style="margin:0">Logs</h2>
                        <select class="form-control" data-scraping-log-level>
                            <option value="">Todos los niveles</option>
                            <option value="debug">Debug</option>
                            <option value="info">Info</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                        </select>
                        <button type="button" class="btn-ghost" data-scraping-logs-refresh>Actualizar logs</button>
                    </div>
                    <div data-scraping-job-logs class="muted">Selecciona un job para ver logs.</div>
                </article>
            </section>
        </section>
    @elseif($screenKey === 'price-refresh-requests')
        <section data-admin-price-refresh>
            <div class="alert" data-price-refresh-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <h2>Solicitudes de actualizacion de precio</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-price-refresh-status>
                            <option value="">Todos los estados</option>
                            <option value="pending">Pendientes</option>
                            <option value="queued">Encoladas</option>
                            <option value="failed">Fallidas</option>
                            <option value="processed">Procesadas</option>
                        </select>
                        <input class="form-control" type="number" min="1" data-price-refresh-product placeholder="Producto ID">
                        <input class="form-control" type="number" min="1" data-price-refresh-user placeholder="Usuario ID">
                        <input class="form-control" type="date" data-price-refresh-from>
                        <input class="form-control" type="date" data-price-refresh-to>
                        <button type="button" class="btn-ghost" data-price-refresh-refresh>Actualizar</button>
                        <span class="chip" data-price-refresh-count>0 solicitudes</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Usuario</th>
                                    <th>Contexto</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                    <th>Fechas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-price-refresh-body>
                                <tr><td colspan="7" class="muted">Cargando solicitudes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-price-refresh-prev>Anterior</button>
                        <span class="muted" data-price-refresh-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-price-refresh-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2>Detalle</h2>
                    <div data-price-refresh-detail class="muted">Selecciona una solicitud.</div>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'scraping-alerts')
        <section data-admin-scraping-alerts>
            <div class="alert" data-scraping-alerts-message style="display:none"></div>

            <section class="grid" style="margin-bottom:14px">
                <article class="panel">
                    <h2>Resumen</h2>
                    <div data-scraping-alerts-summary class="muted">Cargando reporte...</div>
                </article>
                <article class="panel">
                    <h2>Por severidad</h2>
                    <div data-scraping-alerts-severity class="muted">Cargando...</div>
                </article>
                <article class="panel">
                    <h2>Por fuente</h2>
                    <div data-scraping-alerts-source-report class="muted">Cargando...</div>
                </article>
                <article class="panel">
                    <h2>Evolucion</h2>
                    <div data-scraping-alerts-evolution class="muted">Cargando...</div>
                </article>
            </section>

            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <h2>Alertas de scraping</h2>
                    <div class="admin-tools">
                        <select class="form-control" data-alerts-status>
                            <option value="">Todos los estados</option>
                            <option value="open">Abiertas</option>
                            <option value="resolved">Resueltas</option>
                        </select>
                        <select class="form-control" data-alerts-severity>
                            <option value="">Todas las severidades</option>
                            <option value="low">Baja</option>
                            <option value="medium">Media</option>
                            <option value="high">Alta</option>
                            <option value="critical">Critica</option>
                        </select>
                        <select class="form-control" data-alerts-type>
                            <option value="">Todos los tipos</option>
                            <option value="parser_error">Parser error</option>
                            <option value="network_error">Network error</option>
                            <option value="product_not_found">Producto no encontrado</option>
                            <option value="price_error">Error de precio</option>
                        </select>
                        <select class="form-control" data-alerts-source>
                            <option value="">Todas las fuentes</option>
                        </select>
                        <input class="form-control" type="number" min="1" data-alerts-job placeholder="Job ID">
                        <input class="form-control" type="date" data-alerts-from>
                        <input class="form-control" type="date" data-alerts-to>
                        <button type="button" class="btn-ghost" data-alerts-refresh>Actualizar</button>
                        <span class="chip" data-alerts-count>0 alertas</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Alerta</th>
                                    <th>Fuente</th>
                                    <th>Job</th>
                                    <th>Severidad</th>
                                    <th>Estado</th>
                                    <th>Resolucion</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-alerts-body>
                                <tr><td colspan="7" class="muted">Cargando alertas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-alerts-prev>Anterior</button>
                        <span class="muted" data-alerts-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-alerts-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2>Resolver alerta</h2>
                    <div data-alert-detail class="muted">Selecciona una alerta abierta.</div>
                    <form class="rbac-form" data-alert-resolve-form style="display:none;margin-top:14px">
                        <textarea class="form-control" name="resolution_notes" rows="4" maxlength="1000" placeholder="Notas opcionales de resolucion"></textarea>
                        <button type="submit" class="btn-main">Marcar como resuelta</button>
                    </form>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'cities')
        <section data-admin-cities>
            <div class="alert" data-cities-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <div class="admin-tools">
                        <input class="form-control" type="text" data-cities-search placeholder="Buscar ciudad...">
                        <select class="form-control" data-cities-filter-status>
                            <option value="">Todos los estados</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-cities-refresh>Actualizar</button>
                        <span class="chip" data-cities-count>0 ciudades</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Provincia</th>
                                    <th>País</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-cities-body>
                                <tr><td colspan="5" class="muted">Cargando ciudades...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-cities-prev>Anterior</button>
                        <span class="muted" data-cities-page>Pagina 1 de 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-cities-next>Siguiente</button>
                    </div>
                </article>

                <article class="panel" style="min-width:0">
                    <h2 data-cities-form-title>Nueva ciudad</h2>
                    <form data-cities-form>
                        <input type="hidden" data-cities-edit-id>
                        <div style="margin-bottom:8px">
                            <label>Nombre <span style="color:var(--danger)">*</span></label>
                            <input class="form-control" name="name" type="text" placeholder="Bariloche">
                        </div>
                        <div style="margin-bottom:8px">
                            <label>Provincia</label>
                            <input class="form-control" name="province" type="text" placeholder="Río Negro">
                        </div>
                        <div style="margin-bottom:8px">
                            <label>País</label>
                            <input class="form-control" name="country" type="text" placeholder="Argentina">
                        </div>
                        <div style="margin-bottom:8px">
                            <label>Latitud</label>
                            <input class="form-control" name="latitude" type="number" step="any" placeholder="-41.1335">
                        </div>
                        <div style="margin-bottom:8px">
                            <label>Longitud</label>
                            <input class="form-control" name="longitude" type="number" step="any" placeholder="-71.3103">
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                            <button type="submit" class="btn-main" data-cities-submit>Crear ciudad</button>
                            <button type="button" class="btn-ghost" data-cities-reset>Limpiar</button>
                        </div>
                    </form>
                </article>
            </div>
        </section>
    @elseif($screenKey === 'product-reports')
        <section data-admin-product-reports>
            <div class="alert" data-reports-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel" style="min-width:0">
                    <div class="admin-tools">
                        <select class="form-control" data-reports-filter-status>
                            <option value="">Todos los estados</option>
                            <option value="open">Pendientes</option>
                            <option value="resolved">Resueltos</option>
                            <option value="rejected">Rechazados</option>
                        </select>
                        <select class="form-control" data-reports-filter-type>
                            <option value="">Todos los tipos</option>
                            <option value="incorrect_price">Precio incorrecto</option>
                            <option value="incorrect_product_data">Datos incorrectos</option>
                            <option value="incorrect_nutrition">Nutrición incorrecta</option>
                            <option value="duplicate_product">Producto duplicado</option>
                            <option value="other">Otro</option>
                        </select>
                        <input class="form-control" type="date" data-reports-filter-from placeholder="Desde">
                        <input class="form-control" type="date" data-reports-filter-to placeholder="Hasta">
                        <button type="button" class="btn-ghost" data-reports-refresh>Actualizar</button>
                        <span class="chip" data-reports-count>0 reportes</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Usuario</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-reports-body>
                                <tr><td colspan="6" class="muted">Cargando reportes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-reports-prev>Anterior</button>
                        <span class="muted" data-reports-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-reports-next>Siguiente</button>
                    </div>
                </article>

                <article class="panel" style="min-width:0">
                    <h2>Detalle del reporte</h2>
                    <div data-reports-detail class="muted">Seleccioná un reporte para ver el detalle y resolverlo.</div>
                </article>
            </div>
        </section>
    @elseif($screenKey === 'recipe-tags')
        <section data-admin-recipe-tags>
            <div class="alert" data-recipe-tags-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-recipe-tags-search placeholder="Buscar por codigo, nombre o descripcion">
                        <select class="form-control" data-recipe-tags-type>
                            <option value="">Todos los tipos</option>
                            <option value="diet">Dieta</option>
                            <option value="health">Salud</option>
                            <option value="time">Tiempo</option>
                            <option value="cost">Costo</option>
                            <option value="general">General</option>
                        </select>
                        <select class="form-control" data-recipe-tags-status>
                            <option value="">Todos los estados</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                        </select>
                        <button type="button" class="btn-ghost" data-recipe-tags-refresh>Actualizar</button>
                        <span class="chip" data-recipe-tags-count>0 tags</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Descripcion</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-recipe-tags-body>
                                <tr><td colspan="6" class="muted">Cargando tags...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-recipe-tags-prev>Anterior</button>
                        <span class="muted" data-recipe-tags-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-recipe-tags-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-recipe-tag-form-title>Nuevo tag</h2>
                    <form class="rbac-form" data-recipe-tag-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="code" type="text" placeholder="ej: vegana, baja_sodio, alta_proteina">
                        <input class="form-control" name="name" type="text" placeholder="Nombre (ej: Vegana, Baja en sodio)" required>
                        <textarea class="form-control" name="description" rows="3" placeholder="Descripcion opcional"></textarea>
                        <select class="form-control" name="type">
                            <option value="">Sin tipo</option>
                            <option value="diet">Dieta</option>
                            <option value="health">Salud</option>
                            <option value="time">Tiempo</option>
                            <option value="cost">Costo</option>
                            <option value="general">General</option>
                        </select>
                        <select class="form-control" name="status">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar tag</button>
                            <button type="button" class="btn-ghost" data-recipe-tag-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Vista catalogo</h2>
                    <div class="admin-tools">
                        <button type="button" class="btn-ghost" data-recipe-tags-catalog-refresh>Actualizar catalogo</button>
                        <span class="chip" data-recipe-tags-catalog-count>0 activos</span>
                    </div>
                    <div data-recipe-tags-catalog class="muted" style="margin-top:8px">Cargando...</div>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'recipe-categories')
        <section data-admin-recipe-categories>
            <div class="alert" data-recipe-categories-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-recipe-categories-search placeholder="Buscar por nombre o descripcion">
                        <select class="form-control" data-recipe-categories-status>
                            <option value="">Todos los estados</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-recipe-categories-refresh>Actualizar</button>
                        <span class="chip" data-recipe-categories-count>0 categorias</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Categoria padre</th>
                                    <th>Estado</th>
                                    <th>Recetas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-recipe-categories-body>
                                <tr><td colspan="5" class="muted">Cargando categorias...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-recipe-categories-prev>Anterior</button>
                        <span class="muted" data-recipe-categories-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-recipe-categories-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    <h2 data-recipe-category-form-title>Nueva categoria</h2>
                    <form class="rbac-form" data-recipe-category-form>
                        <input type="hidden" name="id">
                        <input class="form-control" name="name" type="text" placeholder="Nombre (ej: Desayuno, Almuerzo, Saludable)" required>
                        <textarea class="form-control" name="description" rows="3" placeholder="Descripcion opcional"></textarea>
                        <select class="form-control" name="parent_id" data-recipe-category-parent>
                            <option value="">Sin categoria padre</option>
                        </select>
                        <select class="form-control" name="status">
                            <option value="active">Activa</option>
                            <option value="inactive">Inactiva</option>
                        </select>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn-main">Guardar categoria</button>
                            <button type="button" class="btn-ghost" data-recipe-category-reset>Limpiar</button>
                        </div>
                    </form>

                    <h2 style="margin-top:18px">Restaurar categoria</h2>
                    <div class="admin-tools">
                        <input class="form-control" type="number" min="1" data-recipe-category-restore-id placeholder="ID eliminado">
                        <button type="button" class="btn-ghost" data-recipe-category-restore-submit>Restaurar</button>
                    </div>
                </aside>
            </div>

            <article class="panel" style="margin-top:14px">
                <div class="admin-tools">
                    <h2 style="margin:0">Arbol de categorias activo</h2>
                    <button type="button" class="btn-ghost" data-recipe-categories-tree-refresh>Actualizar arbol</button>
                </div>
                <div data-recipe-categories-tree class="muted">Cargando arbol...</div>
            </article>
        </section>
    @elseif($screenKey === 'official-recipes')
        <section data-admin-official-recipes>
            <div class="alert" data-recipes-adm-message style="display:none"></div>
            <div class="rbac-layout">
                <article class="panel">
                    <div class="admin-tools">
                        <input class="form-control" type="search" data-recipes-adm-search placeholder="Buscar por nombre...">
                        <select class="form-control" data-recipes-adm-source-type>
                            <option value="">Todas las fuentes</option>
                            <option value="official">Oficial</option>
                            <option value="user">De usuario</option>
                            <option value="shared">Compartida</option>
                            <option value="external">Externa</option>
                        </select>
                        <select class="form-control" data-recipes-adm-is-official>
                            <option value="">Oficial: todas</option>
                            <option value="1">Solo oficiales</option>
                            <option value="0">No oficiales</option>
                        </select>
                        <select class="form-control" data-recipes-adm-status>
                            <option value="">Todos los estados</option>
                            <option value="active">Activas</option>
                            <option value="inactive">Inactivas</option>
                        </select>
                        <button type="button" class="btn-ghost" data-recipes-adm-refresh>Actualizar</button>
                        <span class="chip" data-recipes-adm-count>0 recetas</span>
                    </div>
                    <div style="overflow:auto">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Fuente</th>
                                    <th>Categoria</th>
                                    <th>Estado</th>
                                    <th>Contenido</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-recipes-adm-body>
                                <tr><td colspan="6" class="muted">Cargando recetas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination">
                        <button type="button" class="btn-ghost btn-sm" data-recipes-adm-prev>Anterior</button>
                        <span class="muted" data-recipes-adm-page>Pagina 1</span>
                        <button type="button" class="btn-ghost btn-sm" data-recipes-adm-next>Siguiente</button>
                    </div>
                </article>

                <aside class="panel">
                    {{-- Detalle --}}
                    <div data-recipes-adm-detail>
                        <p class="muted">Seleccioná una receta para ver el detalle o usá el formulario para crear una nueva.</p>
                    </div>

                    {{-- Formulario crear / editar --}}
                    <div data-recipes-adm-form-panel style="display:none">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                            <h2 style="margin:0" data-recipes-adm-form-title>Nueva receta oficial</h2>
                            <button type="button" class="btn-ghost btn-sm" data-recipes-adm-cancel>Cancelar</button>
                        </div>
                        <form data-recipes-adm-form style="display:flex;flex-direction:column;gap:7px">
                            <input type="hidden" name="id">
                            <input class="form-control" name="name" type="text" placeholder="Nombre *" required>
                            <textarea class="form-control" name="description" rows="2" placeholder="Descripcion"></textarea>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px">
                                <input class="form-control" name="servings" type="number" min="1" max="9999" placeholder="Porciones">
                                <select class="form-control" name="difficulty">
                                    <option value="">Dificultad</option>
                                    <option value="fácil">Fácil</option>
                                    <option value="media">Media</option>
                                    <option value="difícil">Difícil</option>
                                </select>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px">
                                <input class="form-control" name="prep_time_minutes" type="number" min="0" max="9999" placeholder="Prep. (min)">
                                <input class="form-control" name="cook_time_minutes" type="number" min="0" max="9999" placeholder="Coccion (min)">
                            </div>
                            <select class="form-control" name="category_id" data-recipes-adm-form-category>
                                <option value="">Sin categoria</option>
                            </select>
                            <select class="form-control" name="status">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                            </select>
                            <select class="form-control" name="source_type">
                                <option value="official">Oficial</option>
                                <option value="user">De usuario</option>
                                <option value="shared">Compartida</option>
                                <option value="external">Externa</option>
                            </select>
                            <input class="form-control" name="source_url" type="url" placeholder="URL fuente (externas)">
                            <input class="form-control" name="source_site" type="text" placeholder="Sitio fuente">
                            <input class="form-control" name="source_author" type="text" placeholder="Autor fuente">
                            <label style="display:flex;align-items:center;gap:8px;font-size:14px">
                                <input type="checkbox" name="is_official" value="1"> Marcar como oficial
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:14px">
                                <input type="checkbox" name="is_public" value="1"> Hacer publica
                            </label>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px">
                                <button type="submit" class="btn-main">Guardar receta</button>
                                <button type="button" class="btn-ghost" data-recipes-adm-cancel-2>Cancelar</button>
                            </div>
                        </form>
                    </div>

                    {{-- Boton nueva receta (visible en estado detalle/vacio) --}}
                    <div data-recipes-adm-new-btn-wrap style="margin-top:14px;padding-top:14px;border-top:1px solid #dde3e8">
                        <button type="button" class="btn-main" data-recipes-adm-new>+ Nueva receta oficial</button>
                    </div>
                </aside>
            </div>
        </section>
    @elseif($screenKey === 'recipe-import-text')
        <section class="rbac-layout" data-admin-recipe-import-text>
            <div style="display:flex;flex-direction:column;gap:14px">
                <article class="panel">
                    <h2>Importar receta por texto</h2>
                    <p class="muted" style="font-size:13px;margin:0 0 12px">Pegá el texto de una receta. El sistema intentará detectar el título, los ingredientes y los pasos automáticamente.</p>
                    <textarea data-importtxt-body class="form-control" rows="16"
                        style="width:100%;box-sizing:border-box;font-family:monospace;font-size:13px;resize:vertical"
                        placeholder="Tarta de manzana&#10;&#10;Ingredientes:&#10;- 3 manzanas&#10;- 200g de harina&#10;- 2 huevos&#10;&#10;Preparación:&#10;1. Pelar y cortar las manzanas.&#10;2. Mezclar la harina con los huevos.&#10;3. Armar la tarta y hornear 30 min a 180°C."></textarea>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:10px">
                        <button type="button" class="btn-main" data-importtxt-btn>Parsear receta</button>
                        <button type="button" class="btn-ghost btn-sm" data-importtxt-clear>Limpiar</button>
                        <span class="muted" data-importtxt-chars style="font-size:12px">0 / 20000</span>
                    </div>
                    <div data-importtxt-message style="display:none;font-size:13px;padding:8px 12px;border-radius:4px;margin-top:10px"></div>
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line)">
                        <p style="font-size:12px;font-weight:700;color:var(--muted);margin:0 0 6px">Formato sugerido:</p>
                        <pre style="font-size:11px;color:var(--muted);background:#f6f8f9;border-radius:6px;padding:10px;margin:0;white-space:pre-wrap">Nombre de la receta

Ingredientes:
- 2 tazas de harina
- 3 huevos
- 100 g de azúcar

Preparación:
1. Mezclar la harina con los huevos.
2. Agregar el azúcar y mezclar.
3. Hornear a 180°C por 30 minutos.</pre>
                    </div>
                </article>
            </div>
            <aside class="panel" data-importtxt-detail style="align-self:start">
                <p class="muted" style="font-size:13px">El resultado del parseo aparecerá aquí.</p>
            </aside>
        </section>

    @elseif($screenKey === 'recipe-import')
        <section class="rbac-layout" data-admin-recipe-import>
            <div style="display:flex;flex-direction:column;gap:14px">
                <article class="panel">
                    <h2>Importar receta por URL</h2>
                    <p class="muted" style="font-size:13px;margin:0 0 12px">Pegá la URL de una receta desde una fuente compatible. La importación puede tardar hasta 15 segundos.</p>
                    <div class="admin-tools">
                        <input type="url" class="form-control" data-import-url placeholder="https://cookpad.com/ar/recetas/..." style="flex:1;min-width:200px">
                        <button type="button" class="btn-main" data-import-btn>Importar</button>
                    </div>
                    <div data-import-message style="display:none;font-size:13px;padding:8px 12px;border-radius:4px;margin-top:10px"></div>
                    <div style="margin-top:16px">
                        <p style="font-size:12px;color:var(--muted);margin:0 0 8px;font-weight:700">Fuentes compatibles:</p>
                        <div data-import-sources style="display:flex;flex-wrap:wrap;gap:6px"></div>
                    </div>
                </article>
            </div>
            <aside class="panel" data-import-detail style="align-self:start">
                <p class="muted" style="font-size:13px">Pegá una URL para ver el resultado del parseo.</p>
            </aside>
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
