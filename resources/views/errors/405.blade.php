@php($error = ['code' => 405, 'title' => 'Acción no permitida', 'message' => 'Esta acción no está permitida para esta sección.', 'remate' => 'Querías cortar con cuchara. Se puede intentar, pero no corresponde.', 'action' => 'Volver atrás', 'action_type' => 'back'])
@include('errors.template', ['error' => $error])
