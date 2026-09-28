<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Models\DispositivoConfiable;

$fecha = static fn (?string $valor): string => $valor ? date('d/m/Y', strtotime($valor) ?: time()) : '—';
?>
<h1 class="h4 mb-1">Mi cuenta</h1>
<p class="text-muted small mb-3">
    <?= htmlspecialchars(trim($usuario['nombres'] . ' ' . $usuario['apellidos']), ENT_QUOTES) ?> ·
    <?= htmlspecialchars($usuario['email'], ENT_QUOTES) ?> ·
    <span class="text-capitalize"><?= htmlspecialchars($usuario['rol_nombre'], ENT_QUOTES) ?></span>
</p>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small contenedor-cuenta"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small contenedor-cuenta" role="alert"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="contenedor-cuenta">
    <section class="card mb-3" aria-labelledby="tituloDosPasos">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <h2 class="h6 mb-2" id="tituloDosPasos"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Verificación en dos pasos</h2>
                <?php if ($dosFactoresActiva): ?>
                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Activa</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">Inactiva</span>
                <?php endif; ?>
            </div>

            <?php if ($dosFactoresActiva): ?>
                <p class="small text-muted">
                    Activa desde el <?= $fecha($usuario['totp_activado_en']) ?>. Te quedan
                    <strong><?= (int) $codigosDisponibles ?></strong> códigos de recuperación.
                </p>
                <?php if ($codigosDisponibles <= 3): ?>
                    <div class="alert alert-warning py-2 small">Te quedan pocos códigos de recuperación: genera códigos nuevos.</div>
                <?php endif; ?>

                <details class="mb-2">
                    <summary class="small">Generar códigos de recuperación nuevos</summary>
                    <form method="post" action="<?= Url::to('/2fa/codigos') ?>" class="row g-2 align-items-end mt-1">
                        <?= Csrf::field() ?>
                        <div class="col-sm-7">
                            <label class="form-label small" for="passwordCodigos">Tu contraseña actual</label>
                            <input type="password" name="password_actual" id="passwordCodigos" class="form-control form-control-sm" required autocomplete="current-password">
                        </div>
                        <div class="col-sm-5">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Generar (anula los anteriores)</button>
                        </div>
                    </form>
                </details>

                <details>
                    <summary class="small">Desactivar la verificación en dos pasos</summary>
                    <form method="post" action="<?= Url::to('/2fa/desactivar') ?>" class="row g-2 align-items-end mt-1"
                          data-confirmar="¿Desactivar la verificación en dos pasos? Tu cuenta quedará protegida solo con la contraseña.">
                        <?= Csrf::field() ?>
                        <div class="col-sm-5">
                            <label class="form-label small" for="passwordDesactivar">Contraseña actual</label>
                            <input type="password" name="password_actual" id="passwordDesactivar" class="form-control form-control-sm" required autocomplete="current-password">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small" for="codigoDesactivar">Código actual</label>
                            <input type="text" name="codigo" id="codigoDesactivar" class="form-control form-control-sm" required inputmode="numeric" autocomplete="one-time-code" maxlength="11">
                        </div>
                        <div class="col-sm-3">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">Desactivar</button>
                        </div>
                    </form>
                </details>

            <?php elseif (!$dosFactoresDisponible): ?>
                <p class="small text-muted mb-0">Todavía no está habilitada en este servidor.</p>
            <?php else: ?>
                <p class="small">
                    Opcional y recomendada: además de la contraseña, MIA te pedirá un código de 6 dígitos que genera
                    tu teléfono. Así, aunque alguien conozca tu contraseña, no podrá entrar sin tu teléfono.
                </p>
                <a href="<?= Url::to('/2fa/configurar') ?>" class="btn btn-primary btn-sm">Activar verificación en dos pasos</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($dosFactoresActiva): ?>
        <section class="card mb-3" aria-labelledby="tituloDispositivos">
            <div class="card-body">
                <h2 class="h6" id="tituloDispositivos"><i class="bi bi-laptop me-1" aria-hidden="true"></i>Dispositivos de confianza</h2>
                <p class="small text-muted">En estos navegadores no se pide el código durante 30 días. Quita los que no reconozcas.</p>
                <?php if ($dispositivos === []): ?>
                    <p class="small text-muted mb-0">Ninguno.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($dispositivos as $d): ?>
                            <li class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="small">
                                    <strong><?= htmlspecialchars(DispositivoConfiable::describirAgente($d['agente']), ENT_QUOTES) ?></strong>
                                    <div class="text-muted">
                                        Último uso: <?= $fecha($d['ultimo_uso_en'] ?? $d['creado_en']) ?> ·
                                        IP <?= htmlspecialchars((string) ($d['ip'] ?? '—'), ENT_QUOTES) ?> ·
                                        vence el <?= $fecha($d['expira_en']) ?>
                                    </div>
                                </div>
                                <form method="post" action="<?= Url::to('/2fa/dispositivos/' . (int) $d['id'] . '/revocar') ?>">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Quitar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card mb-3" id="contrasena" aria-labelledby="tituloContrasena">
        <div class="card-body">
            <h2 class="h6" id="tituloContrasena"><i class="bi bi-key me-1" aria-hidden="true"></i>Cambiar contraseña</h2>
            <form method="post" action="<?= Url::to('/mi-cuenta/contrasena') ?>" class="row g-2">
                <?= Csrf::field() ?>
                <input type="text" name="username" value="<?= htmlspecialchars($usuario['email'], ENT_QUOTES) ?>" autocomplete="username" class="d-none" aria-hidden="true" tabindex="-1">
                <div class="col-12">
                    <label class="form-label small" for="passwordActual">Contraseña actual</label>
                    <input type="password" name="password_actual" id="passwordActual" class="form-control" required autocomplete="current-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label small" for="passwordNueva">Contraseña nueva</label>
                    <input type="password" name="password_nueva" id="passwordNueva" class="form-control" required minlength="10" autocomplete="new-password" aria-describedby="ayudaPasswordNueva">
                </div>
                <div class="col-md-6">
                    <label class="form-label small" for="passwordConfirmacion">Confirmar contraseña nueva</label>
                    <input type="password" name="password_confirmacion" id="passwordConfirmacion" class="form-control" required minlength="10" autocomplete="new-password">
                </div>
                <div class="col-12">
                    <div id="ayudaPasswordNueva" class="form-text mt-0 mb-2">Mínimo 10 caracteres, con letras y números. No uses tu nombre, documento ni correo.</div>
                    <button type="submit" class="btn btn-primary btn-sm">Cambiar contraseña</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card mb-3" aria-labelledby="tituloPrivacidad">
        <div class="card-body">
            <h2 class="h6 mb-2" id="tituloPrivacidad"><i class="bi bi-file-earmark-lock me-1" aria-hidden="true"></i>Tus datos y privacidad</h2>
            <p class="small text-muted mb-2">
                <?php if (!empty($usuario['politica_aceptada_en'])): ?>
                    <?php $aceptadaEn = strtotime((string) $usuario['politica_aceptada_en']) ?: time(); ?>
                    Aceptaste la política de tratamiento de datos el <?= date('d/m/Y', $aceptadaEn) ?> a las <?= date('g:i a', $aceptadaEn) ?>.
                <?php else: ?>
                    Aún no hay registro de que hayas aceptado la política de tratamiento de datos.
                <?php endif; ?>
                Para corregir tus datos, pídeselo al administrador de MIA de tu institución.
            </p>
            <a href="<?= Url::to('/politica-de-datos') ?>" class="btn btn-outline-secondary btn-sm">Ver la política de datos</a>
        </div>
    </section>
</div>
