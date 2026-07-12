@php($error = ['code' => 408, 'title' => 'Tiempo agotado', 'message' => 'La página tardó demasiado en responder. Probá nuevamente en unos segundos.', 'remate' => 'Se nos pasó el punto de cocción.', 'action' => 'Reintentar', 'action_type' => 'reload'])
@include('errors.template', ['error' => $error])
