@php($error = ['code' => 502, 'title' => 'Mala respuesta del servidor', 'message' => 'El servidor recibió una respuesta incorrecta de otro servicio.', 'remate' => 'La cocina pidió salsa y le mandaron sopa.', 'action' => 'Reintentar', 'action_type' => 'reload'])
@include('errors.template', ['error' => $error])
