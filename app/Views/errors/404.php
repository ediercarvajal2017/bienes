<?php

\App\Core\View::render('errors/_pagina', [
    'codigo' => 404,
    'titulo' => 'Página no encontrada',
    'mensaje' => 'La página o el registro que buscas no existe.',
    'causas' => [
        'El enlace está incompleto o tiene un error de escritura.',
        'El registro se eliminó (un administrador puede restaurarlo desde la Papelera).',
        'El código QR es de un bien que ya no está registrado en MIA.',
    ],
    'queHacer' => 'Vuelve al panel y búscalo con «Buscar en todo MIA».',
]);
