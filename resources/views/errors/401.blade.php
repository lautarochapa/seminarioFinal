@php($error = ['code' => 401, 'title' => 'Falta iniciar sesión', 'message' => 'Para entrar acá primero necesitás iniciar sesión.', 'remate' => 'La cocina está abierta, pero todavía no sabemos quién sos.', 'action' => 'Iniciar sesión', 'action_type' => 'login'])
@include('errors.template', ['error' => $error])
