<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="contenedor-2fa">
    <h1 class="h4 mb-1"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Configurar la verificación en dos pasos</h1>
    <p class="text-muted small mb-3">
        Además de tu contraseña, MIA te pedirá un código de 6 dígitos que genera tu teléfono.
        Así, aunque alguien conozca tu contraseña, no podrá entrar sin tu teléfono. Es opcional: puedes
        desactivarla cuando quieras desde «Mi cuenta».
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small" role="alert"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <ol class="list-unstyled pasos-2fa">
        <li class="card mb-3">
            <div class="card-body">
                <h2 class="h6"><span class="badge text-bg-primary me-2">1</span>Instala una aplicación autenticadora</h2>
                <p class="small mb-0">
                    En tu teléfono, instala <strong>Google Authenticator</strong> o <strong>Microsoft Authenticator</strong>
                    (gratis en Play Store y App Store). También sirven Authy o el gestor de contraseñas del teléfono.
                </p>
            </div>
        </li>

        <li class="card mb-3">
            <div class="card-body">
                <h2 class="h6"><span class="badge text-bg-primary me-2">2</span>Escanea este código</h2>
                <p class="small">En la aplicación, toca <strong>«+»</strong> o <strong>«Agregar cuenta»</strong> y escanea el código:</p>
                <div class="text-center mb-2">
                    <img src="<?= htmlspecialchars($qr, ENT_QUOTES) ?>" alt="Código QR para la aplicación autenticadora" class="qr-2fa" width="240" height="240">
                </div>
                <details class="small">
                    <summary>¿Estás configurando desde este mismo teléfono? Escribe la clave a mano</summary>
                    <p class="mt-2 mb-1">Elige «Ingresar una clave de configuración», con el nombre <strong>MIA</strong> y esta clave (basada en tiempo):</p>
                    <code class="d-block user-select-all fs-6 p-2 bg-body-tertiary rounded text-break"><?= htmlspecialchars($claveManual, ENT_QUOTES) ?></code>
                </details>
            </div>
        </li>

        <li class="card mb-3">
            <div class="card-body">
                <h2 class="h6"><span class="badge text-bg-primary me-2">3</span>Escribe el código que muestra la aplicación</h2>
                <form method="post" action="<?= Url::to('/2fa/activar') ?>" class="row g-2 align-items-end">
                    <?= Csrf::field() ?>
                    <div class="col-sm-7">
                        <label class="form-label small" for="codigoActivar">Código de 6 dígitos</label>
                        <input type="text" name="codigo" id="codigoActivar" class="form-control form-control-lg text-center campo-codigo-2fa"
                               required inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="\d{3}\s?\d{3}" placeholder="123456">
                    </div>
                    <div class="col-sm-5">
                        <button type="submit" class="btn btn-primary btn-lg w-100">Activar</button>
                    </div>
                </form>
                <p class="small text-muted mt-2 mb-0">Luego te mostraremos 10 códigos de recuperación para entrar si pierdes el teléfono.</p>
            </div>
        </li>
    </ol>
</div>
