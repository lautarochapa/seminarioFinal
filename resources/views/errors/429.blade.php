@php($error = ['code' => 429, 'title' => 'Demasiados intentos', 'message' => 'Hiciste muchas acciones en poco tiempo. Esperá un momento antes de volver a probar.', 'remate' => 'El mozo todavía está anotando el pedido anterior.', 'action' => 'Esperar y reintentar', 'action_type' => 'reload'])
@include('errors.template', ['error' => $error])
