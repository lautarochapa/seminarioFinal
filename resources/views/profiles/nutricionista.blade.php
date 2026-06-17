@extends('layouts.app')

@section('content')
<div style="padding: 5px 15%;">
    <div data-professional-panel>
        <div class="legacy-panel">
            <h1 style="font-size:32px;line-height:1.15;margin:0 0 8px;font-weight:900;">Panel del dietologo</h1>
            <p class="muted" style="margin:0 0 12px;">Desde aca podras ver los usuarios que te autorizaron acceso, revisar su perfil y consultar o actualizar su planificacion cuando el permiso lo permita.</p>
            <div class="alert" data-professional-panel-message style="display:none"></div>
        </div>

        <div class="legacy-grid">
            <div>
                <div class="legacy-panel">
                    <h2 style="font-size:18px;font-weight:900;margin:0 0 12px;">Usuarios vinculados</h2>
                    <table class="legacy-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Perfil</th>
                                <th>Planes</th>
                                <th>Edicion</th>
                                <th>Accion</th>
                            </tr>
                        </thead>
                        <tbody data-professional-users-body>
                            <tr><td colspan="5" class="muted">Cargando usuarios autorizados...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="legacy-panel">
                    <h2 style="font-size:18px;font-weight:900;margin:0 0 12px;">Planes de comida</h2>
                    <table class="legacy-table">
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th>Desde</th>
                                <th>Hasta</th>
                                <th>Modo</th>
                                <th>Estado</th>
                                <th>Accion</th>
                            </tr>
                        </thead>
                        <tbody data-professional-plans-body>
                            <tr><td colspan="6" class="muted">Selecciona un usuario para ver sus planes.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="legacy-panel">
                    <h2 style="font-size:18px;font-weight:900;margin:0 0 12px;">Perfil autorizado</h2>
                    <div data-professional-profile>
                        <p class="muted" style="margin:0;">Selecciona un usuario autorizado para ver su perfil.</p>
                    </div>
                </div>

                <div class="legacy-panel">
                    <h2 style="font-size:18px;font-weight:900;margin:0 0 12px;">Editar plan de comida</h2>
                    <form data-professional-plan-form>
                        <input class="form-control" name="status" type="text" placeholder="Estado">
                        <input class="form-control" name="period_type" type="text" placeholder="Tipo de periodo">
                        <input class="form-control" name="start_date" type="date">
                        <input class="form-control" name="end_date" type="date">
                        <input class="form-control" name="mode" type="text" placeholder="Modo">
                        <input class="form-control" name="approved_at" type="datetime-local">
                        <textarea class="form-control" name="config_json" rows="6" placeholder="Config JSON"></textarea>
                        <div class="legacy-actions" style="margin-top:12px;">
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                            <button type="button" class="btn btn-secondary" data-professional-plan-reset>Limpiar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
