@php($error = ['code' => 403, 'title' => 'Sin permisos', 'message' => 'Ups, parece que no tenés permisos para acceder acá.', 'remate' => 'Este plato es solo para personal autorizado.', 'action' => 'Volver al inicio', 'action_type' => 'home'])
@include('errors.template', ['error' => $error])
