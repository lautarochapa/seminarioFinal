@php($error = ['code' => 413, 'title' => 'Archivo demasiado grande', 'message' => 'El archivo que intentás subir es demasiado pesado. Probá con uno más liviano.', 'remate' => 'Esa milanesa no entra en el plato.', 'action' => 'Elegir otro archivo', 'action_type' => 'back'])
@include('errors.template', ['error' => $error])
