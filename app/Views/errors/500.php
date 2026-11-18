<?php

\App\Core\View::render('errors/_pagina', [
    'codigo' => 500,
    'titulo' => 'Ocurrió un error inesperado',
    'mensaje' => 'MIA no pudo completar la operación por una falla interna, no por algo que hayas hecho mal.',
    'queHacer' => 'Vuelve atrás e inténtalo de nuevo en unos minutos. Si se repite, comparte con soporte el código de incidente que aparece abajo.',
    'incidente' => $incidente ?? null,
]);
