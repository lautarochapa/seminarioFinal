@php($error = ['code' => 409, 'title' => 'Conflicto de datos', 'message' => 'No pudimos completar la acción porque hay datos que entran en conflicto.', 'remate' => 'Dos cocineros tocaron la misma olla al mismo tiempo.', 'action' => 'Actualizar y probar de nuevo', 'action_type' => 'reload'])
@include('errors.template', ['error' => $error])
