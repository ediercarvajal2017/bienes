<?php

\App\Core\View::render('errors/_pagina', [
    'codigo' => 503,
    'titulo' => 'Estamos actualizando MIA',
    'mensaje' => 'El sistema estará disponible de nuevo en unos minutos. Tu información está segura.',
    'queHacer' => 'Espera unos minutos y recarga la página.',
    'enlace' => '',
]);
