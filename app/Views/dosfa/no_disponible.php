<?php use App\Core\Url; ?>
<div class="contenedor-2fa">
    <?= ui('encabezado_pagina', ['titulo' => 'Verificación en dos pasos', 'icono' => 'seguridad']) ?>
    <div class="alert alert-warning small">
        La verificación en dos pasos todavía no está habilitada en este servidor. Avisa al administrador del sistema:
        falta configurar la llave <code>APP_KEY</code> en el archivo <code>.env</code>.
    </div>
    <a href="<?= Url::to('/mi-cuenta') ?>" class="btn btn-outline-secondary btn-sm">Volver a Mi cuenta</a>
</div>
