<?php

\App\Core\View::render('errors/_pagina', [
    'codigo' => 403,
    'titulo' => 'Acceso no permitido',
    'mensaje' => 'Tu usuario no tiene permiso para entrar a esta sección o a este registro.',
    'causas' => [
        'Tu rol (por ejemplo, docente) no incluye esta función.',
        'El registro es de otra institución.',
        'Abriste un enlace que te compartió alguien con otro rol.',
    ],
    'queHacer' => 'Vuelve al panel. Si necesitas este acceso, pídeselo al rector o al administrador de MIA.',
]);
