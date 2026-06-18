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
