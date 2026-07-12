@php($error = ['code' => 504, 'title' => 'Tiempo de espera agotado', 'message' => 'Otro servicio tardó demasiado en responder. Probá nuevamente más tarde.', 'remate' => 'El delivery salió, pero se perdió en el camino.', 'action' => 'Reintentar más tarde', 'action_type' => 'reload'])
@include('errors.template', ['error' => $error])
