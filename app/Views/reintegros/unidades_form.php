<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Services\ReintegroUnidadesService;

$e = static fn (?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$viejo ??= [];
$lote = $resumen['lote'];
$placa = (string) $lote['lote'];
$cantidadesPrevias = (array) ($viejo['cantidad'] ?? []);
$generarLote = ($viejo['generar_lote'] ?? '1') === '1';
$volverLista = Url::to('/reintegros/unidades') . (Auth::esSuperusuario() ? '?institucion=' . (int) $institucionId : '');
?>
<?php ob_start(); ?>
    <a href="<?= $e($volverLista) ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => 'Reintegrar por cantidad · Placa ' . (string) ($placa), 'icono' => 'reintegro', 'subtitulo' => (string) ($lote['descripcion']) . (string) (!empty($lote['categoria_nombre']) ? ' · ' . $lote['categoria_nombre'] : ''), 'acciones' => (string) ob_get_clean()]) ?>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small contenedor-md"><?= $e($error) ?></div><?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3" aria-label="Resumen del código">
    <span class="badge text-bg-light border fs-6 fw-normal">En cartera: <strong><?= (int) $lote['en_cartera'] ?></strong> de <?= (int) $lote['total'] ?></span>
    <span class="badge text-bg-light border fs-6 fw-normal">Reintegradas: <?= (int) $lote['reintegradas'] ?></span>
    <?php if ((int) $lote['dadas_de_baja'] > 0): ?>
        <span class="badge text-bg-light border fs-6 fw-normal">Dadas de baja: <?= (int) $lote['dadas_de_baja'] ?></span>
    <?php endif; ?>
</div>

<?php if (!empty($resumen['bienResumen'])): ?>
    <div class="alert alert-info small py-2 contenedor-md">
        Este código también está en la cartera como el bien <strong class="mono"><?= $e($resumen['bienResumen']['codigo_identificacion']) ?></strong>
        "<?= $e($resumen['bienResumen']['descripcion']) ?>". Al reintegrar, MIA le resta las unidades: su descripción y su valor
        quedan con lo que queda en cartera.
    </div>
<?php endif; ?>

<?php if ($resumen['motivo'] !== null): ?>
    <div class="alert alert-warning small contenedor-md">No se puede reintegrar: <?= $e($resumen['motivo']) ?>.</div>
<?php elseif ($resumen['ubicaciones'] === []): ?>
    <p class="text-muted">Este código ya no tiene unidades en cartera.</p>
<?php else: ?>
    <form class="contenedor-md" method="post" action="<?= $e(Url::to('/reintegros/unidades/' . rawurlencode($placa))) ?>" id="formUnidades">
        <?= Csrf::field() ?>
        <?php if (Auth::esSuperusuario()): ?><input type="hidden" name="institucion_id" value="<?= (int) $institucionId ?>"><?php endif; ?>

        <div class="table-responsive">
            <table class="table table-sm align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th>Ubicación</th>
                    <th class="text-end">Disponibles</th>
                    <th class="celda-cantidad">Reintegrar</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($resumen['ubicaciones'] as $u): ?>
                    <?php $clave = (string) $u['clave']; ?>
                    <tr>
                        <td data-label="Ubicación">
                            <?php if ($clave === ReintegroUnidadesService::CLAVE_SIN_ASIGNAR): ?>
                                <strong>Sin asignar</strong> <span class="text-muted small">(en la institución, sin espacio ni responsable)</span>
                            <?php else: ?>
                                <strong><?= $e($u['espacio_nombre'] ?? 'Sin espacio') ?></strong>
                                <div class="small text-muted">
                                    <?= $u['tipo'] === 'individual' ? 'Individual' : 'Grupal' ?><?= !empty($u['responsables_nombres']) ? ': ' . $e($u['responsables_nombres']) : '' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end" data-label="Disponibles"><?= (int) $u['disponibles'] ?></td>
                        <td data-label="Reintegrar">
                            <input type="number" name="cantidad[<?= $e($clave) ?>]" min="0" max="<?= (int) $u['disponibles'] ?>" step="1"
                                   value="<?= $e((string) ($cantidadesPrevias[$clave] ?? '0')) ?>"
                                   class="form-control form-control-sm cantidad-unidades" inputmode="numeric"
                                   aria-label="Unidades a reintegrar de <?= $e($clave === ReintegroUnidadesService::CLAVE_SIN_ASIGNAR ? 'Sin asignar' : ($u['espacio_nombre'] ?? $u['responsables_nombres'] ?? '')) ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted">MIA toma las unidades de cada ubicación empezando por los últimos consecutivos (por ejemplo <?= $e($placa) ?>-<?= str_pad((string) (int) $lote['total'], 3, '0', STR_PAD_LEFT) ?>).</p>

        <div class="card mb-3">
            <div class="card-body py-3">
                <h2 class="h6 mb-3">Datos del reintegro</h2>
                <div class="row g-3">
                    <div class="col-sm-4">
                        <label for="campoFecha" class="form-label small">Fecha del reintegro</label>
                        <input id="campoFecha" type="date" name="fecha" required max="<?= $e($hoy) ?>" class="form-control form-control-sm"
                               value="<?= $e($viejo['fecha'] ?? $hoy) ?>">
                    </div>
                    <div class="col-sm-8">
                        <label for="campoDestino" class="form-label small">Destino</label>
                        <input id="campoDestino" type="text" name="destino_texto" required maxlength="200" class="form-control form-control-sm"
                               value="<?= $e($viejo['destino_texto'] ?? 'Bodega de reintegro') ?>">
                    </div>
                    <div class="col-12">
                        <label for="campoObservaciones" class="form-label small">Observaciones (opcional)</label>
                        <input id="campoObservaciones" type="text" name="observaciones" maxlength="500" class="form-control form-control-sm"
                               value="<?= $e($viejo['observaciones'] ?? '') ?>" placeholder="Ej. Sillas con el espaldar partido">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="generar_lote" value="1" id="campoGenerarLote" <?= $generarLote ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="campoGenerarLote">
                                Generar ahora el lote de reintegro (comprobante FO-ADMI-009 con la placa y la cantidad)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="accion-fija-movil">
            <button type="submit" class="btn btn-primary" id="botonReintegrarUnidades" disabled>
                <?= icono('reintegro', 'me-1') ?>Reintegrar <span id="totalUnidades">0</span> unidad(es)
            </button>
        </div>
    </form>

    <script>
    (function () {
        const campos = document.querySelectorAll('.cantidad-unidades');
        const total = document.getElementById('totalUnidades');
        const boton = document.getElementById('botonReintegrarUnidades');

        function sumar() {
            let suma = 0;
            campos.forEach(function (c) {
                const n = parseInt(c.value, 10);
                suma += Number.isFinite(n) && n > 0 ? Math.min(n, parseInt(c.max, 10)) : 0;
            });
            total.textContent = suma;
            boton.disabled = suma === 0;
            return suma;
        }

        campos.forEach(function (c) { c.addEventListener('input', sumar); });
        document.getElementById('formUnidades').addEventListener('submit', function (evento) {
            const suma = sumar();
            if (suma === 0) {
                evento.preventDefault();
                return;
            }
            if (!window.miaConfirmarEnvio(evento, { mensaje: '¿Reintegrar ' + suma + ' unidad(es) de la placa ' + <?= json_encode($placa) ?> + '?', boton: 'Reintegrar' })) {
                return;
            }
        });
        sumar();
    })();
    </script>
<?php endif; ?>
