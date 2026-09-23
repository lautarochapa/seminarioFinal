<?php

return [
    'videos' => [
        'web' => [
            'title' => 'Demo web',
            'file' => 'videos/demo-web-20260923.mp4',
            'poster' => 'images/landing/demo-web-20260923.jpg',
            'duration' => '2:39',
            'description' => 'Capturas reales editadas por etapas, con datos de ejemplo y sin audio.',
            'steps' => [
                'Registro de una cuenta y creación del hogar.',
                'Carga de alimentos, receta propia y planificación del almuerzo.',
                'Lista de compras y registro de una compra por ARS 1.200.',
                'Presupuesto: ARS 10.000 asignados y ARS 8.800 disponibles.',
                'Preparación de cuatro porciones y consumo de 400 g de arroz.',
            ],
            'note' => 'En este ejemplo, el producto cargado manualmente no está vinculado al ingrediente de la receta. La compra y el consumo se realizan sobre el producto vinculado.',
        ],
        'android' => [
            'title' => 'Demo Android',
            'file' => null,
            'poster' => null,
            'duration' => null,
            'description' => 'Recorrido por la aplicación en un celular Android, con datos de ejemplo.',
            'steps' => [],
            'note' => null,
        ],
    ],
];
