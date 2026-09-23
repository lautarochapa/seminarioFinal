<?php

return [
    'videos' => [
        'web' => [
            'title' => 'Demo web',
            'file' => 'videos/demo-web-guiada-20260923.mp4',
            'poster' => 'images/landing/demo-web-guiada-20260923.jpg',
            'duration' => '2:09',
            'description' => 'Del registro a la compra y el consumo de alimentos: un recorrido narrado con capturas reales y datos de ejemplo.',
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
            'file' => 'videos/demo-android-guiada-20260923.mp4',
            'poster' => 'images/landing/demo-android-guiada-20260923.jpg',
            'subtitles' => 'videos/demo-android-guiada-20260923.vtt',
            'duration' => '1:32',
            'description' => 'Acompañá a Martín desde su cocina hasta la compra: una demo narrada de la app en un Samsung, con datos de ejemplo.',
            'steps' => [
                'Inicio del hogar y consulta de los alimentos disponibles.',
                'Receta de arroz casero y planificación de cuatro porciones.',
                'Lista de compras y registro de 400 g de arroz por ARS 1.200.',
                'Actualización del stock: 600 g de arroz disponibles.',
                'Presupuesto del hogar: ARS 2.400 gastados y ARS 7.600 disponibles.',
            ],
            'note' => 'Grabado en un Samsung S23 Ultra con la app 1.0.8. El recorrido está editado por etapas y utiliza una cuenta y un hogar de ejemplo.',
        ],
    ],
];
