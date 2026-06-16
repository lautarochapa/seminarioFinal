@php($error = ['code' => 400, 'title' => 'Pedido mal armado', 'message' => 'Ups, algo en la solicitud vino con datos incorrectos. Revisá la información e intentá de nuevo.', 'remate' => 'La receta llegó, pero con los ingredientes mezclados.', 'action' => 'Volver e intentar otra vez', 'action_type' => 'back'])
@include('errors.template', ['error' => $error])
