<?php

\App\Core\View::render('errors/_pagina', [
    'codigo' => 500,
    'titulo' => 'Ocurrió un error inesperado',
    'mensaje' => 'No se pudo completar la operación. Intente de nuevo en unos minutos; si el problema continúa, avise a soporte.',
    'incidente' => $incidente ?? null,
]);
