@php($error = ['code' => 503, 'title' => 'Servicio no disponible', 'message' => 'El sistema no está disponible en este momento. Puede estar en mantenimiento o con mucha demanda.', 'remate' => 'La cocina está saturada: todos pidieron ñoquis a la vez.', 'action' => 'Volver en unos minutos', 'action_type' => 'reload'])
@include('errors.template', ['error' => $error])
