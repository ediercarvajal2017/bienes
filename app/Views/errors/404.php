<?php

\App\Core\View::render('errors/_pagina', [
    'codigo' => 404,
    'titulo' => 'Página no encontrada',
    'mensaje' => 'La página que busca no existe o fue movida.',
]);
