<?php use App\Core\Url; ?>
<div class="contenedor-2fa">
    <h1 class="h4 mb-2"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Verificación en dos pasos</h1>
    <div class="alert alert-warning small">
        La verificación en dos pasos todavía no está habilitada en este servidor. Avisa al administrador del sistema:
        falta configurar la llave <code>APP_KEY</code> en el archivo <code>.env</code>.
    </div>
    <a href="<?= Url::to('/mi-cuenta') ?>" class="btn btn-outline-secondary btn-sm">Volver a Mi cuenta</a>
</div>
