@php($error = ['code' => 418, 'title' => 'Soy una tetera', 'message' => 'No puedo preparar café porque soy una tetera.', 'remate' => 'Técnicamente correcto, gastronómicamente discutible.', 'action' => 'Volver al inicio', 'action_type' => 'home'])
@include('errors.template', ['error' => $error])
