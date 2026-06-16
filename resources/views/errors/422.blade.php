@php($error = ['code' => 422, 'title' => 'Datos inválidos', 'message' => 'Algunos datos no cumplen con lo esperado. Revisalos antes de continuar.', 'remate' => 'Casi sale, pero la sal cayó en el postre.', 'action' => 'Corregir datos', 'action_type' => 'back'])
@include('errors.template', ['error' => $error])
