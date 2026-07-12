@php($error = ['code' => 500, 'title' => 'Error interno', 'message' => 'Tuvimos un problema inesperado en el servidor. Ya podés intentar nuevamente en unos minutos.', 'remate' => 'Algo explotó en la cocina, pero estamos limpiando.', 'action' => 'Reintentar más tarde', 'action_type' => 'reload'])
@include('errors.template', ['error' => $error])
