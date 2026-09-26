<?php

\App\Core\View::render('errors/_pagina', [
    'codigo' => 403,
    'titulo' => 'Acceso no permitido',
    'mensaje' => 'Su usuario no tiene permiso para entrar a esta sección. Si cree que es un error, consulte con el rector o el administrador del sistema.',
]);
