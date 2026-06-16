@php($error = ['code' => 404, 'title' => 'Página no encontrada', 'message' => 'No encontramos la página que estás buscando. Puede que se haya movido o que el enlace esté mal escrito.', 'remate' => 'Buscamos hasta atrás de la heladera, pero nada.', 'action' => 'Ir al inicio', 'action_type' => 'home'])
@include('errors.template', ['error' => $error])
