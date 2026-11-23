<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Models\DispositivoConfiable;

$fecha = static fn (?string $valor): string => $valor ? date('d/m/Y', strtotime($valor) ?: time()) : '—';
?>
<?= ui('encabezado_pagina', ['titulo' => 'Mi cuenta', 'icono' => 'cuenta',
    'subtitulo' => trim($usuario['nombres'] . ' ' . $usuario['apellidos']) . ' · ' . $usuario['email'] . ' · ' . ucfirst((string) $usuario['rol_nombre']), 'ayuda' => \App\Helpers\AyudaCampo::boton('mi_cuenta')]) ?>

<div class="contenedor-cuenta">
    <section class="card mb-3" aria-labelledby="tituloDosPasos">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0" id="tituloDosPasos"><?= icono('seguridad', 'me-1') ?>Verificación en dos pasos</h2><?= \App\Helpers\AyudaCampo::boton('dos_pasos') ?></div>
                <?php if ($dosFactoresActiva): ?>
                    <span class="badge badge-exito"><?= icono('exito', 'me-1') ?>Activa</span>
                <?php else: ?>
                    <span class="badge badge-neutro">Inactiva</span>
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
                            <?= \App\Helpers\AyudaCampo::etiqueta('passwordCodigos', 'Tu contraseña actual', 'confirmar_contrasena', true) ?>
                            <input type="password" name="password_actual" id="passwordCodigos" class="form-control form-control-sm" required autocomplete="current-password">
                        </div>
                        <div class="col-sm-5">
                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Generar (anula los anteriores)</button>
                        </div>
                    </form>
                </details>

                <details>
                    <summary class="small">Desactivar la verificación en dos pasos</summary>
                    <form method="post" action="<?= Url::to('/2fa/desactivar') ?>" class="row g-2 align-items-end mt-1"
                          data-confirmar="¿Desactivar la verificación en dos pasos? Tu cuenta quedará protegida solo con la contraseña.">
                        <?= Csrf::field() ?>
                        <div class="col-sm-5">
                            <?= \App\Helpers\AyudaCampo::etiqueta('passwordDesactivar', 'Contraseña actual', 'confirmar_contrasena', true) ?>
                            <input type="password" name="password_actual" id="passwordDesactivar" class="form-control form-control-sm" required autocomplete="current-password">
                        </div>
                        <div class="col-sm-4">
                            <?= \App\Helpers\AyudaCampo::etiqueta('codigoDesactivar', 'Código actual', 'codigo_dos_pasos', true) ?>
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
                <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0" id="tituloDispositivos"><?= icono('seguridad', 'me-1') ?>Dispositivos de confianza</h2><?= \App\Helpers\AyudaCampo::boton('dispositivos_confianza') ?></div>
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
            <h2 class="h6" id="tituloContrasena"><?= icono('seguridad', 'me-1') ?>Cambiar contraseña</h2>
            <form method="post" action="<?= Url::to('/mi-cuenta/contrasena') ?>" class="row g-2">
                <?= Csrf::field() ?>
                <input type="text" name="username" value="<?= htmlspecialchars($usuario['email'], ENT_QUOTES) ?>" autocomplete="username" class="d-none" aria-hidden="true" tabindex="-1">
                <div class="col-12">
                    <?= \App\Helpers\AyudaCampo::etiqueta('passwordActual', 'Contraseña actual', 'contrasena_actual', true) ?>
                    <input type="password" name="password_actual" id="passwordActual" class="form-control" required autocomplete="current-password">
                </div>
                <div class="col-md-6">
                    <?= \App\Helpers\AyudaCampo::etiqueta('passwordNueva', 'Contraseña nueva', 'contrasena_nueva', true) ?>
                    <input type="password" name="password_nueva" id="passwordNueva" class="form-control" required minlength="10" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <?= \App\Helpers\AyudaCampo::etiqueta('passwordConfirmacion', 'Confirmar contraseña nueva', 'contrasena_confirmacion', true) ?>
                    <input type="password" name="password_confirmacion" id="passwordConfirmacion" class="form-control" required minlength="10" autocomplete="new-password">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">Cambiar contraseña</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card mb-3" aria-labelledby="tituloPrivacidad">
        <div class="card-body">
            <div class="d-flex align-items-center gap-1 mb-2"><h2 class="h6 mb-0" id="tituloPrivacidad"><?= icono('seguridad', 'me-1') ?>Tus datos y privacidad</h2><?= \App\Helpers\AyudaCampo::boton('privacidad') ?></div>
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
