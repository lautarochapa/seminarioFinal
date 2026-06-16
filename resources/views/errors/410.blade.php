@php($error = ['code' => 410, 'title' => 'Ya no disponible', 'message' => 'Este contenido ya no está disponible.', 'remate' => 'Era plato del día, pero fue ayer.', 'action' => 'Volver al inicio', 'action_type' => 'home'])
@include('errors.template', ['error' => $error])
