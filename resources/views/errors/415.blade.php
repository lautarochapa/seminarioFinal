@php($error = ['code' => 415, 'title' => 'Formato no compatible', 'message' => 'El tipo de archivo o contenido no es compatible.', 'remate' => 'Nos trajiste ingredientes, pero no para esta receta.', 'action' => 'Cambiar formato', 'action_type' => 'back'])
@include('errors.template', ['error' => $error])
